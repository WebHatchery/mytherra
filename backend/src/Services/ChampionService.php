<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameEvent;
use App\Models\GameState;
use App\Models\Hero;
use App\Models\Landmark;
use App\Models\Player;
use App\Models\Region;
use App\Models\Settlement;
use App\Repositories\HeroRepository;

class ChampionService
{
    private const CONFIG_CATEGORY = 'champions';
    private const CONFIG_KEY_ROSTER = 'roster';
    private const MAX_CHAMPIONS = 3;
    private const DESIGNATION_COST = 25;
    private const CULTIVATION_BASE_COST = 15;

    private const FOCUS_OPTIONS = [
        'quest' => [
            'label' => 'Quest',
            'summary' => 'Pushes the champion into a public trial that can seed future myths.',
            'bondGain' => 18,
            'levelGain' => 1,
            'costModifier' => 0,
        ],
        'defense' => [
            'label' => 'Defense',
            'summary' => 'Binds the champion to protect their region during dangerous years.',
            'bondGain' => 14,
            'levelGain' => 1,
            'costModifier' => 0,
        ],
        'research' => [
            'label' => 'Research',
            'summary' => 'Guides the champion toward hidden lore and magical discovery.',
            'bondGain' => 12,
            'levelGain' => 0,
            'costModifier' => 5,
        ],
        'rivalry' => [
            'label' => 'Rivalry',
            'summary' => 'Turns the champion into a visible rival for regional enemies.',
            'bondGain' => 16,
            'levelGain' => 1,
            'costModifier' => 5,
        ],
    ];

    private ?array $rosterCache = null;

    public function __construct(
        private ?HeroRepository $heroRepository = null,
        private ?GameConfigService $configService = null
    ) {
        $this->heroRepository ??= new HeroRepository();
        $this->configService ??= GameConfigService::getInstance();
    }

    public function status(): array
    {
        $roster = $this->loadRoster();
        $champions = [];

        foreach ($roster as $heroId => $profile) {
            $hero = $this->heroRepository->getById((string)$heroId);
            $champions[] = $this->serializeChampionProfile($profile, $hero);
        }
        $recentOutcomes = $this->recentOutcomes($champions);

        return [
            'rosterLimit' => self::MAX_CHAMPIONS,
            'activeCount' => count($roster),
            'designationCost' => self::DESIGNATION_COST,
            'cultivationBaseCost' => self::CULTIVATION_BASE_COST,
            'focusOptions' => $this->focusOptions(),
            'champions' => $champions,
            'recentOutcomes' => $recentOutcomes,
            'bettingHooks' => $this->bettingHooks($champions),
            'legacyHooks' => $this->legacyHooks($champions, $recentOutcomes),
        ];
    }

    public function championStatusForHero(Hero $hero): array
    {
        $roster = $this->loadRoster();
        $profile = $roster[$hero->id] ?? null;
        $isChampion = is_array($profile);

        return [
            'isChampion' => $isChampion,
            'profile' => $isChampion ? $this->serializeChampionProfile($profile, $hero) : null,
            'rosterLimit' => self::MAX_CHAMPIONS,
            'activeChampionCount' => count($roster),
            'designationCost' => self::DESIGNATION_COST,
            'cultivationCosts' => $this->cultivationCosts($profile),
            'focusOptions' => $this->focusOptions(),
            'eligible' => $this->isEligibleForDesignation($hero, $roster),
            'eligibilityReason' => $this->designationEligibilityReason($hero, $roster),
        ];
    }

    public function championProfileForHero(Hero $hero): ?array
    {
        $profile = $this->loadRoster()[$hero->id] ?? null;

        return is_array($profile) ? $this->serializeChampionProfile($profile, $hero) : null;
    }

    public function designate(string $heroId): array
    {
        $hero = $this->requireHero($heroId);
        $roster = $this->loadRoster();

        if (isset($roster[$hero->id])) {
            throw new \InvalidArgumentException("{$hero->name} is already a champion.");
        }

        $eligibilityReason = $this->designationEligibilityReason($hero, $roster);
        if ($eligibilityReason !== null) {
            throw new \InvalidArgumentException($eligibilityReason);
        }

        $player = Player::getSinglePlayer();
        if (!$player->spendDivineFavor(self::DESIGNATION_COST)) {
            return [
                'success' => false,
                'message' => 'Insufficient divine favor',
                'cost' => self::DESIGNATION_COST,
                'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
            ];
        }

        $year = $this->currentYear();
        $hero->level = min(100, (int)($hero->level ?? 1) + 1);
        $hero->influence_last_action = 'Designate Champion';
        $hero->addFeat("Designated as a divine champion in year {$year}.");
        $this->heroRepository->saveHero($hero);

        $profile = [
            'heroId' => $hero->id,
            'name' => $hero->name,
            'role' => $hero->role,
            'regionId' => $hero->region_id,
            'designatedYear' => $year,
            'rank' => 1,
            'bond' => 10,
            'focus' => 'quest',
            'questsCompleted' => 0,
            'rivalryPressure' => 0,
            'eventIds' => [],
            'currentQuest' => $this->buildCurrentQuest($hero, 'quest', $year, 20),
            'lastCultivatedYear' => null,
        ];

        $eventId = $this->recordChampionEvent(
            $hero,
            'Champion Designated',
            "{$hero->name} was marked as a mortal champion in year {$year}.",
            'champion_designated'
        );
        $profile['eventIds'][] = $eventId;
        $roster[$hero->id] = $profile;
        $this->saveRoster($roster);

        $freshHero = $hero->fresh();

        return [
            'success' => true,
            'message' => "{$hero->name} has been designated as a mortal champion.",
            'cost' => self::DESIGNATION_COST,
            'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
            'champion' => $this->serializeChampionProfile($profile, $freshHero),
            'status' => $this->status(),
            'target' => $freshHero?->toArray(),
        ];
    }

    public function cultivate(string $heroId, string $focus): array
    {
        $focus = $this->normalizeFocus($focus);
        $hero = $this->requireHero($heroId);
        $roster = $this->loadRoster();
        $profile = $roster[$hero->id] ?? null;

        if (!is_array($profile)) {
            throw new \InvalidArgumentException("{$hero->name} is not a champion.");
        }

        if (!$hero->isAlive()) {
            throw new \InvalidArgumentException('Only living champions can be cultivated.');
        }

        $cost = $this->cultivationCost($profile, $focus);
        $player = Player::getSinglePlayer();
        if (!$player->spendDivineFavor($cost)) {
            return [
                'success' => false,
                'message' => 'Insufficient divine favor',
                'cost' => $cost,
                'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
            ];
        }

        $year = $this->currentYear();
        $focusConfig = self::FOCUS_OPTIONS[$focus];
        $profile['focus'] = $focus;
        $profile['bond'] = min(100, (int)($profile['bond'] ?? 0) + (int)$focusConfig['bondGain']);
        $profile['questsCompleted'] = (int)($profile['questsCompleted'] ?? 0) + ($focus === 'quest' ? 1 : 0);
        $profile['rivalryPressure'] = $this->nextRivalryPressure($profile, $focus);
        $profile['rank'] = $this->nextRank($profile);
        $profile['currentQuest'] = $this->buildCurrentQuest(
            $hero,
            $focus,
            $year,
            min(95, 30 + ((int)$profile['rank'] * 8))
        );
        $profile['lastCultivatedYear'] = $year;

        $hero->level = min(100, (int)($hero->level ?? 1) + (int)$focusConfig['levelGain']);
        $hero->influence_last_action = 'Cultivate Champion';
        $hero->addFeat($this->cultivationFeat($focus, $year));
        $this->applyFocusAlignment($hero, $focus);
        $this->heroRepository->saveHero($hero);

        $eventId = $this->recordChampionEvent(
            $hero,
            'Champion Cultivated',
            "{$hero->name} was cultivated toward {$focusConfig['label']} in year {$year}.",
            'champion_cultivated'
        );
        $profile['eventIds'] = array_values(array_unique([
            ...(is_array($profile['eventIds'] ?? null) ? $profile['eventIds'] : []),
            $eventId,
        ]));
        $roster[$hero->id] = $profile;
        $this->saveRoster($roster);

        $freshHero = $hero->fresh();

        return [
            'success' => true,
            'message' => "{$hero->name}'s champion bond deepened toward {$focusConfig['label']}.",
            'cost' => $cost,
            'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
            'champion' => $this->serializeChampionProfile($profile, $freshHero),
            'status' => $this->status(),
            'target' => $freshHero?->toArray(),
        ];
    }

    public function advanceWorld(int $currentYear): array
    {
        $summary = ['processed' => 0, 'changed' => 0, 'events' => 0, 'outcomes' => [], 'errors' => []];
        $roster = $this->loadRoster();
        $changed = false;

        foreach ($roster as $heroId => $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $summary['processed']++;
            try {
                $hero = $this->heroRepository->getById((string)$heroId);
                if (!$hero instanceof Hero) {
                    $summary['errors'][] = ['id' => (string)$heroId, 'message' => 'Champion hero no longer exists.'];
                    unset($roster[$heroId]);
                    $changed = true;
                    continue;
                }

                $result = $this->advanceChampionProfile($profile, $hero, $currentYear);
                if ($result['changed']) {
                    $roster[$hero->id] = $result['profile'];
                    $summary['changed']++;
                    $changed = true;
                }
                if (is_array($result['outcome'])) {
                    $summary['events']++;
                    $summary['outcomes'][] = $result['outcome'];
                }
            } catch (\Throwable $error) {
                $summary['errors'][] = ['id' => (string)$heroId, 'message' => $error->getMessage()];
            }
        }

        if ($changed) {
            $this->saveRoster($roster);
        }

        return $summary;
    }

    private function loadRoster(): array
    {
        if ($this->rosterCache !== null) {
            return $this->rosterCache;
        }

        $value = $this->configService->getConfig(self::CONFIG_CATEGORY, self::CONFIG_KEY_ROSTER, []);
        $this->rosterCache = is_array($value) ? $value : [];

        return $this->rosterCache;
    }

    private function saveRoster(array $roster): void
    {
        $this->rosterCache = $roster;
        $this->configService->setConfig(
            self::CONFIG_CATEGORY,
            self::CONFIG_KEY_ROSTER,
            $roster,
            'array',
            'Mortal champion roster and cultivation state.'
        );
    }

    private function requireHero(string $heroId): Hero
    {
        $hero = $this->heroRepository->getById($heroId);

        if (!$hero instanceof Hero) {
            throw new \InvalidArgumentException("Hero not found: {$heroId}");
        }

        return $hero;
    }

    private function isEligibleForDesignation(Hero $hero, array $roster): bool
    {
        return $this->designationEligibilityReason($hero, $roster) === null;
    }

    private function designationEligibilityReason(Hero $hero, array $roster): ?string
    {
        if (isset($roster[$hero->id])) {
            return "{$hero->name} is already a champion.";
        }

        if (!$hero->isAlive()) {
            return 'Only living heroes can become champions.';
        }

        if (count($roster) >= self::MAX_CHAMPIONS) {
            return 'The champion roster is full.';
        }

        return null;
    }

    private function focusOptions(): array
    {
        return array_map(
            fn(string $key, array $option): array => [
                'key' => $key,
                'label' => $option['label'],
                'summary' => $option['summary'],
            ],
            array_keys(self::FOCUS_OPTIONS),
            self::FOCUS_OPTIONS
        );
    }

    private function cultivationCosts(?array $profile): array
    {
        $costs = [];

        foreach (array_keys(self::FOCUS_OPTIONS) as $focus) {
            $costs[$focus] = $this->cultivationCost($profile ?? ['rank' => 1], $focus);
        }

        return $costs;
    }

    private function cultivationCost(array $profile, string $focus): int
    {
        $rank = max(1, (int)($profile['rank'] ?? 1));
        $modifier = (int)(self::FOCUS_OPTIONS[$focus]['costModifier'] ?? 0);

        return self::CULTIVATION_BASE_COST + ($rank * 5) + $modifier;
    }

    private function normalizeFocus(string $focus): string
    {
        $normalized = strtolower(trim($focus));

        if (!isset(self::FOCUS_OPTIONS[$normalized])) {
            throw new \InvalidArgumentException("Unsupported champion focus: {$focus}");
        }

        return $normalized;
    }

    private function nextRank(array $profile): int
    {
        $bondRank = 1 + intdiv((int)($profile['bond'] ?? 0), 25);
        $questRank = 1 + intdiv((int)($profile['questsCompleted'] ?? 0), 3);

        return min(10, max((int)($profile['rank'] ?? 1), $bondRank, $questRank));
    }

    private function nextRivalryPressure(array $profile, string $focus): int
    {
        $current = (int)($profile['rivalryPressure'] ?? 0);

        return match ($focus) {
            'defense' => max(0, $current - 8),
            'rivalry' => min(100, $current + 18),
            'research' => min(100, $current + 3),
            default => min(100, $current + 6),
        };
    }

    private function buildCurrentQuest(Hero $hero, string $focus, int $year, int $progress): array
    {
        $focusConfig = self::FOCUS_OPTIONS[$focus];

        return [
            'focus' => $focus,
            'title' => "{$hero->name}'s {$focusConfig['label']} Charge",
            'summary' => $focusConfig['summary'],
            'startedYear' => $year,
            'progress' => $progress,
        ];
    }

    private function advanceChampionProfile(array $profile, Hero $hero, int $currentYear): array
    {
        if (!$hero->isAlive()) {
            return ['profile' => $profile, 'changed' => false, 'outcome' => null];
        }

        $focus = $this->normalizeFocus((string)($profile['focus'] ?? 'quest'));
        $quest = is_array($profile['currentQuest'] ?? null)
            ? $profile['currentQuest']
            : $this->buildCurrentQuest($hero, $focus, $currentYear, 15);
        $oldProgress = max(0, min(100, (int)($quest['progress'] ?? 0)));
        $progress = min(100, $oldProgress + $this->questProgressDelta($profile, $hero, $focus));
        $quest['focus'] = $focus;
        $quest['progress'] = $progress;
        $profile['currentQuest'] = $quest;

        if ($progress < 100) {
            return ['profile' => $profile, 'changed' => $progress !== $oldProgress, 'outcome' => null];
        }

        $outcome = $this->resolveChampionOutcome($profile, $hero, $focus, $currentYear);
        $profile = $outcome['profile'];

        return ['profile' => $profile, 'changed' => true, 'outcome' => $outcome['outcome']];
    }

    private function questProgressDelta(array $profile, Hero $hero, string $focus): int
    {
        $rank = max(1, (int)($profile['rank'] ?? 1));
        $bond = max(0, min(100, (int)($profile['bond'] ?? 0)));
        $level = max(1, (int)($hero->level ?? 1));
        $focusBonus = match ($focus) {
            'quest' => 5,
            'defense' => 3,
            'research' => 2,
            'rivalry' => 4,
            default => 0,
        };

        return max(8, min(35, 7 + ($rank * 3) + intdiv($bond, 12) + intdiv($level, 8) + $focusBonus));
    }

    private function resolveChampionOutcome(array $profile, Hero $hero, string $focus, int $currentYear): array
    {
        $region = $hero->region;
        $settlement = $region ? Settlement::where('region_id', $region->id)->orderByDesc('population')->first() : null;
        $landmark = $region ? Landmark::where('region_id', $region->id)->orderByDesc('magic_level')->first() : null;
        $changes = [];
        $eventType = 'champion_quest_completed';
        $title = 'Champion Quest Completed';
        $result = 'completed';
        $legacyType = 'champion_legend';

        $hero->level = min(100, (int)($hero->level ?? 1) + ($focus === 'research' ? 0 : 1));
        $hero->addFeat($this->outcomeFeat($focus, $currentYear));
        $changes[] = "{$hero->name} gained a champion feat";

        if ($region instanceof Region) {
            match ($focus) {
                'defense' => $this->applyRegionDeltas($region, -1, -4, 1, 0, 'champion_defended'),
                'research' => $this->applyRegionDeltas($region, 1, 0, 1, 4, 'champion_research'),
                'rivalry' => null,
                default => $this->applyRegionDeltas($region, -1, -1, 2, 1, 'champion_tale'),
            };
            if ($focus !== 'rivalry') {
                $changes[] = "{$region->name} absorbed {$focus} champion pressure";
                $region->save();
            }
        }

        if ($focus === 'defense' && $settlement instanceof Settlement) {
            $settlement->defensibility = $this->clamp((int)$settlement->defensibility + 5);
            $settlement->prosperity = $this->clamp((int)$settlement->prosperity + 1);
            $settlement->traits = $this->addValue($settlement->traits ?? [], 'champion_guarded');
            $settlement->save();
            $changes[] = "{$settlement->name} gained champion defenses";
        }

        if ($focus === 'research' && $landmark instanceof Landmark) {
            $landmark->magic_level = $this->clamp((int)$landmark->magic_level + 3);
            $landmark->traits = $this->addValue($landmark->traits ?? [], 'champion_researched');
            $landmark->save();
            $changes[] = "{$landmark->name} gained researched magic";
        }

        if ($focus === 'rivalry' && $region instanceof Region) {
            $rivalry = $this->resolveRivalry($profile, $hero, $region);
            $eventType = $rivalry['eventType'];
            $title = $rivalry['title'];
            $result = $rivalry['result'];
            $legacyType = $rivalry['legacyType'];
            $changes = array_merge($changes, $rivalry['changes']);
        }

        $profile['questsCompleted'] = (int)($profile['questsCompleted'] ?? 0) + 1;
        $profile['bond'] = min(100, (int)($profile['bond'] ?? 0) + ($result === 'escalated' ? 4 : 8));
        if ($focus !== 'rivalry') {
            $profile['rivalryPressure'] = $this->nextRivalryPressure($profile, $focus);
        }
        $profile['rank'] = $this->nextRank($profile);
        $profile['currentQuest'] = $this->buildCurrentQuest($hero, $focus, $currentYear, 12 + ((int)$profile['rank'] * 2));
        $profile['lastOutcomeYear'] = $currentYear;

        $hero->influence_last_action = 'Champion Outcome';
        $this->heroRepository->saveHero($hero);

        $summary = $this->outcomeSummary($hero, $focus, $result, $changes);
        $eventId = $this->recordChampionEvent(
            $hero,
            $title,
            $summary,
            $eventType,
            $currentYear,
            $settlement instanceof Settlement ? [$settlement->id] : [],
            $landmark instanceof Landmark ? [$landmark->id] : []
        );

        $outcome = [
            'id' => 'champion-outcome-' . bin2hex(random_bytes(6)),
            'eventId' => $eventId,
            'type' => $eventType,
            'title' => $title,
            'summary' => $summary,
            'effectSummary' => implode('; ', array_unique($changes)),
            'result' => $result,
            'focus' => $focus,
            'focusLabel' => self::FOCUS_OPTIONS[$focus]['label'],
            'year' => $currentYear,
            'heroId' => $hero->id,
            'heroName' => $hero->name,
            'regionId' => $hero->region_id,
            'regionName' => $region?->name,
            'legacyType' => $legacyType,
            'legacyReason' => "{$hero->name}'s {$legacyType} is now visible to era continuity.",
            'relatedSettlementIds' => $settlement instanceof Settlement ? [$settlement->id] : [],
            'relatedLandmarkIds' => $landmark instanceof Landmark ? [$landmark->id] : [],
        ];

        $profile['eventIds'] = array_values(array_unique([
            ...(is_array($profile['eventIds'] ?? null) ? $profile['eventIds'] : []),
            $eventId,
        ]));
        $profile['outcomes'] = array_slice([
            $outcome,
            ...(is_array($profile['outcomes'] ?? null) ? $profile['outcomes'] : []),
        ], 0, 8);

        return ['profile' => $profile, 'outcome' => $outcome];
    }

    private function resolveRivalry(array &$profile, Hero $hero, Region $region): array
    {
        $pressure = max(0, min(100, (int)($profile['rivalryPressure'] ?? 0)));
        $strength = (int)($profile['bond'] ?? 0) + ((int)($profile['rank'] ?? 1) * 8) + ((int)$hero->level * 2);
        $threat = $pressure + intdiv((int)$region->danger_level, 2) + intdiv((int)$region->chaos, 3);

        if ($strength >= $threat) {
            $region->danger_level = $this->clamp((int)$region->danger_level - 5);
            $region->chaos = $this->clamp((int)$region->chaos - 3);
            $region->prosperity = $this->clamp((int)$region->prosperity + 1);
            $region->addTrait('rivalry_settled');
            $region->save();
            $profile['rivalryPressure'] = max(0, $pressure - 35);

            return [
                'eventType' => 'champion_rivalry_resolved',
                'title' => 'Champion Rivalry Resolved',
                'result' => 'resolved',
                'legacyType' => 'rivalry_legend',
                'changes' => ["{$hero->name} settled a rivalry in {$region->name}", "{$region->name} became safer"],
            ];
        }

        $region->danger_level = $this->clamp((int)$region->danger_level + 4);
        $region->chaos = $this->clamp((int)$region->chaos + 3);
        $region->addTrait('champion_rivalry');
        $region->save();
        $profile['rivalryPressure'] = min(100, $pressure + 8);

        return [
            'eventType' => 'champion_rivalry_escalated',
            'title' => 'Champion Rivalry Escalated',
            'result' => 'escalated',
            'legacyType' => 'rivalry_scar',
            'changes' => ["{$hero->name}'s rivalry escalated in {$region->name}", "{$region->name} grew more dangerous"],
        ];
    }

    private function applyRegionDeltas(
        Region $region,
        int $chaosDelta,
        int $dangerDelta,
        int $prosperityDelta,
        int $magicDelta,
        string $trait
    ): void {
        $region->chaos = $this->clamp((int)$region->chaos + $chaosDelta);
        $region->danger_level = $this->clamp((int)$region->danger_level + $dangerDelta);
        $region->prosperity = $this->clamp((int)$region->prosperity + $prosperityDelta);
        $region->magic_affinity = $this->clamp((int)$region->magic_affinity + $magicDelta);
        $region->addTrait($trait);
    }

    private function outcomeFeat(string $focus, int $year): string
    {
        $label = self::FOCUS_OPTIONS[$focus]['label'];

        return "Completed a champion {$label} outcome in year {$year}.";
    }

    private function outcomeSummary(Hero $hero, string $focus, string $result, array $changes): string
    {
        $label = self::FOCUS_OPTIONS[$focus]['label'];
        $effectSummary = $changes === [] ? 'No durable change was recorded.' : implode('; ', array_unique($changes)) . '.';

        return "{$hero->name}'s champion {$label} charge {$result}. {$effectSummary}";
    }

    private function cultivationFeat(string $focus, int $year): string
    {
        $label = self::FOCUS_OPTIONS[$focus]['label'];

        return "Cultivated as a champion of {$label} in year {$year}.";
    }

    private function applyFocusAlignment(Hero $hero, string $focus): void
    {
        match ($focus) {
            'defense' => $hero->updateAlignment(1, -2, 'Champion defense cultivation'),
            'research' => $hero->updateAlignment(1, 1, 'Champion research cultivation'),
            'rivalry' => $hero->updateAlignment(-1, 3, 'Champion rivalry cultivation'),
            default => $hero->updateAlignment(2, 0, 'Champion quest cultivation'),
        };
    }

    private function serializeChampionProfile(array $profile, ?Hero $hero): array
    {
        $focus = $this->normalizeFocus((string)($profile['focus'] ?? 'quest'));
        $region = $hero?->region;
        $name = $hero?->name ?? (string)($profile['name'] ?? 'Unknown Champion');
        $rank = max(1, (int)($profile['rank'] ?? 1));
        $bond = max(0, min(100, (int)($profile['bond'] ?? 0)));

        return [
            'heroId' => $hero?->id ?? (string)($profile['heroId'] ?? ''),
            'name' => $name,
            'role' => $hero?->role ?? (string)($profile['role'] ?? 'undecided'),
            'regionId' => $hero?->region_id ?? ($profile['regionId'] ?? null),
            'regionName' => $region?->name,
            'designatedYear' => (int)($profile['designatedYear'] ?? 1),
            'rank' => $rank,
            'bond' => $bond,
            'focus' => $focus,
            'focusLabel' => self::FOCUS_OPTIONS[$focus]['label'],
            'questsCompleted' => (int)($profile['questsCompleted'] ?? 0),
            'rivalryPressure' => (int)($profile['rivalryPressure'] ?? 0),
            'eventIds' => is_array($profile['eventIds'] ?? null) ? array_values($profile['eventIds']) : [],
            'currentQuest' => is_array($profile['currentQuest'] ?? null) ? $profile['currentQuest'] : null,
            'outcomes' => is_array($profile['outcomes'] ?? null) ? array_values($profile['outcomes']) : [],
            'latestOutcome' => is_array(($profile['outcomes'][0] ?? null)) ? $profile['outcomes'][0] : null,
            'lastCultivatedYear' => $profile['lastCultivatedYear'] ?? null,
            'lastOutcomeYear' => $profile['lastOutcomeYear'] ?? null,
            'summary' => $this->profileSummary($name, $rank, $bond, $profile),
        ];
    }

    private function recordChampionEvent(
        Hero $hero,
        string $title,
        string $description,
        string $type,
        ?int $year = null,
        array $relatedSettlementIds = [],
        array $relatedLandmarkIds = []
    ): string {
        $eventId = 'event-' . bin2hex(random_bytes(8));

        GameEvent::create([
            'id' => $eventId,
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'status' => 'completed',
            'region_id' => $hero->region_id,
            'timestamp' => date('c'),
            'related_region_ids' => $hero->region_id ? [$hero->region_id] : [],
            'related_hero_ids' => [$hero->id],
            'related_settlement_ids' => $relatedSettlementIds,
            'related_landmark_ids' => $relatedLandmarkIds,
            'related_resource_ids' => [],
            'year' => $year ?? $this->currentYear(),
        ]);

        return $eventId;
    }

    private function recentOutcomes(array $champions): array
    {
        $outcomes = [];
        foreach ($champions as $champion) {
            foreach (($champion['outcomes'] ?? []) as $outcome) {
                if (is_array($outcome)) {
                    $outcomes[] = $outcome;
                }
            }
        }

        usort($outcomes, fn(array $left, array $right): int => ((int)($right['year'] ?? 0)) <=> ((int)($left['year'] ?? 0)));

        return array_slice($outcomes, 0, 8);
    }

    private function bettingHooks(array $champions): array
    {
        $hooks = [];
        foreach ($champions as $champion) {
            $quest = is_array($champion['currentQuest'] ?? null) ? $champion['currentQuest'] : null;
            if ($quest !== null && (int)($quest['progress'] ?? 0) >= 40) {
                $hooks[] = [
                    'id' => 'champion-quest-' . $champion['heroId'],
                    'title' => 'Champion Trial: ' . $champion['name'],
                    'summary' => "{$champion['name']}'s {$champion['focusLabel']} charge is {$quest['progress']}% complete.",
                    'betType' => 'hero_level_milestone',
                    'targetId' => $champion['heroId'],
                    'targetType' => 'hero',
                    'regionId' => $champion['regionId'] ?? null,
                    'minimumYears' => 1,
                    'maximumYears' => 4,
                    'confidence' => (int)$quest['progress'] >= 75 ? 'likely' : 'possible',
                ];
            }

            if ((int)($champion['rivalryPressure'] ?? 0) >= 35 && !empty($champion['regionId'])) {
                $hooks[] = [
                    'id' => 'champion-rivalry-' . $champion['heroId'],
                    'title' => 'Champion Rivalry: ' . $champion['name'],
                    'summary' => "{$champion['name']}'s rivalry pressure is {$champion['rivalryPressure']}/100 in {$champion['regionName']}.",
                    'betType' => 'region_danger_change',
                    'targetId' => $champion['regionId'],
                    'targetType' => 'region',
                    'regionId' => $champion['regionId'],
                    'minimumYears' => 1,
                    'maximumYears' => 5,
                    'confidence' => 'possible',
                ];
            }
        }

        return array_slice($hooks, 0, 4);
    }

    private function legacyHooks(array $champions, array $recentOutcomes): array
    {
        $hooks = [];
        foreach ($champions as $champion) {
            if (!empty($champion['heroId'])) {
                $hooks[] = [
                    'heroId' => $champion['heroId'],
                    'name' => $champion['name'],
                    'legacyType' => (int)$champion['rank'] >= 4 ? 'champion_reincarnation_seed' : 'champion_memory',
                    'reason' => "{$champion['name']} is rank {$champion['rank']} with bond {$champion['bond']}/100.",
                    'eventId' => $champion['eventIds'][0] ?? null,
                ];
            }
        }

        foreach ($recentOutcomes as $outcome) {
            $hooks[] = [
                'heroId' => $outcome['heroId'] ?? null,
                'name' => $outcome['heroName'] ?? 'Champion',
                'legacyType' => $outcome['legacyType'] ?? 'champion_legend',
                'reason' => $outcome['legacyReason'] ?? ($outcome['summary'] ?? 'Champion outcome can shape era memory.'),
                'eventId' => $outcome['eventId'] ?? null,
            ];
        }

        return array_slice($hooks, 0, 8);
    }

    private function profileSummary(string $name, int $rank, int $bond, array $profile): string
    {
        $latestOutcome = is_array(($profile['outcomes'][0] ?? null)) ? $profile['outcomes'][0] : null;
        if ($latestOutcome !== null) {
            return "{$name} is a rank {$rank} champion with bond {$bond}/100; latest outcome: {$latestOutcome['title']}.";
        }

        return "{$name} is a rank {$rank} champion with bond {$bond}/100.";
    }

    private function addValue(array $values, string $value): array
    {
        if (!in_array($value, $values, true)) {
            $values[] = $value;
        }

        return array_values($values);
    }

    private function clamp(int|float $value, int $min = 0, int $max = 100): int
    {
        return max($min, min($max, (int)round($value)));
    }

    private function currentYear(): int
    {
        return (int)(GameState::getCurrent()->current_year ?? 1);
    }
}
