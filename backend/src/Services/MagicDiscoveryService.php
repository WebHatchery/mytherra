<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameState;
use App\Models\Hero;
use App\Models\Landmark;
use App\Models\Player;
use App\Models\Region;
use App\Models\ResourceNode;
use App\Models\Settlement;
use App\Repositories\EventRepository;

class MagicDiscoveryService
{
    private const CONFIG_CATEGORY = 'magic';
    private const CONFIG_KEY_STATE = 'discovery_state';
    private const RESEARCH_COST = 18;
    private const HISTORY_LIMIT = 8;

    private const PATHS = [
        'ley_weaving' => [
            'label' => 'Ley Weaving',
            'domain' => 'Region',
            'summary' => 'Channels regional ley lines into prosperity, travel, and magic pressure.',
            'hiddenSummary' => 'Look for high magic regions, magical springs, and arcane landmarks.',
            'betType' => 'magic_discovery',
        ],
        'spirit_compacts' => [
            'label' => 'Spirit Compacts',
            'domain' => 'Hero',
            'summary' => 'Binds prophets, sacred groves, and local spirits into durable obligations.',
            'hiddenSummary' => 'Look for prophets, sacred groves, temples, and mystical cultures.',
            'betType' => 'magic_discovery',
        ],
        'ruin_script' => [
            'label' => 'Ruin Script',
            'domain' => 'Landmark',
            'summary' => 'Deciphers ruins, towers, and ancient writing into research breakthroughs.',
            'hiddenSummary' => 'Look for scholars, ancient ruins, towers, and hidden landmarks.',
            'betType' => 'magic_discovery',
        ],
        'storm_rites' => [
            'label' => 'Storm Rites',
            'domain' => 'Region',
            'summary' => 'Turns weather, danger, and volatile magic into repeatable ritual practice.',
            'hiddenSummary' => 'Look for dangerous, chaotic, weather-shaken, or highly magical regions.',
            'betType' => 'magic_discovery',
        ],
        'civic_enchantment' => [
            'label' => 'Civic Enchantment',
            'domain' => 'Settlement',
            'summary' => 'Lets settlements carry culture, trade, and prosperity through enchanted civic works.',
            'hiddenSummary' => 'Look for prosperous settlements, mercantile culture, and agent-of-change heroes.',
            'betType' => 'magic_discovery',
        ],
    ];

    private ?array $stateCache = null;

    public function __construct(
        private ?GameConfigService $configService = null,
        private ?EventRepository $eventRepository = null
    ) {
        $this->configService ??= GameConfigService::getInstance();
        $this->eventRepository ??= new EventRepository();
    }

    public function status(): array
    {
        $state = $this->loadState();
        $paths = array_values(array_map(
            fn(string $key, array $path): array => $this->serializePath($key, $path, $state[$key] ?? []),
            array_keys(self::PATHS),
            self::PATHS
        ));

        return [
            'currentYear' => $this->currentYear(),
            'researchCost' => self::RESEARCH_COST,
            'summary' => $this->summary($paths),
            'pathOptions' => $this->pathOptions(),
            'paths' => $paths,
            'targetOptions' => $this->targetOptions(),
            'suggestedTargets' => $this->suggestedTargets($state),
            'bettingHooks' => $this->bettingHooks($paths),
        ];
    }

    public function pathsForTarget(string $targetId): array
    {
        $state = $this->loadState();
        $paths = [];

        foreach (self::PATHS as $key => $path) {
            $record = $state[$key] ?? $this->initialRecord($key);
            $target = $record['lastTarget'] ?? null;
            if (!is_array($target) || (string)($target['id'] ?? '') !== $targetId) {
                continue;
            }

            $paths[] = $this->serializePath($key, $path, $record);
        }

        $statusRank = ['known' => 3, 'emerging' => 2, 'hidden' => 1];
        usort($paths, function (array $left, array $right) use ($statusRank): int {
            $statusCompare = ($statusRank[$right['status']] ?? 0) <=> ($statusRank[$left['status']] ?? 0);
            if ($statusCompare !== 0) {
                return $statusCompare;
            }

            return ((int)$right['progress']) <=> ((int)$left['progress']);
        });

        return $paths;
    }

    public function research(array $payload): array
    {
        $targetType = $this->normalizeTargetType((string)($payload['targetType'] ?? 'region'));
        $targetId = trim((string)($payload['targetId'] ?? ''));
        if ($targetId === '') {
            throw new \InvalidArgumentException('A magic research target is required.');
        }

        $target = $this->resolveTarget($targetType, $targetId);
        $requestedPath = (string)($payload['path'] ?? 'auto');
        $pathKey = $requestedPath === 'auto' ? $this->bestPathForTarget($target) : $this->normalizePath($requestedPath);
        $evidence = $this->evidenceForPath($pathKey, $target);
        $player = Player::getSinglePlayer();

        if (!$player->spendDivineFavor(self::RESEARCH_COST)) {
            return [
                'success' => false,
                'message' => 'Insufficient divine favor',
                'cost' => self::RESEARCH_COST,
                'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
            ];
        }

        $state = $this->loadState();
        $record = $state[$pathKey] ?? $this->initialRecord($pathKey);
        $beforeStatus = (string)($record['status'] ?? 'hidden');
        $roll = abs(crc32($pathKey . ':' . $target['type'] . ':' . $target['id'] . ':' . $this->currentYear())) % 20;
        $progressGain = max(8, min(35, (int)round($evidence['score'] / 4) + $roll));
        $record['progress'] = min(100, (int)($record['progress'] ?? 0) + $progressGain);
        $record['evidenceScore'] = max((int)($record['evidenceScore'] ?? 0), $evidence['score']);
        $record['lastResearchedYear'] = $this->currentYear();
        $record['lastTarget'] = $target;
        $record['signals'] = $evidence['signals'];
        $record['status'] = $this->statusForProgress((int)$record['progress'], $evidence['score']);

        if ($record['status'] === 'known' && $beforeStatus !== 'known') {
            $record['discoveryYear'] = $this->currentYear();
            $this->applyDurableDiscovery($pathKey, $target);
        }

        $eventType = $record['status'] === 'known' && $beforeStatus !== 'known'
            ? 'magic_discovery'
            : 'magic_research';
        $event = $this->recordMagicEvent($pathKey, $record, $target, $eventType);
        $record['eventIds'] = array_values(array_unique(array_merge($record['eventIds'] ?? [], [$event['id']])));
        array_unshift($record['history'], [
            'eventId' => $event['id'],
            'title' => $event['title'],
            'summary' => $event['description'],
            'type' => $event['type'],
            'year' => $event['year'],
            'targetType' => $target['type'],
            'targetId' => $target['id'],
            'targetName' => $target['name'],
            'progress' => (int)$record['progress'],
            'status' => $record['status'],
        ]);
        $record['history'] = array_slice($record['history'], 0, self::HISTORY_LIMIT);
        $state[$pathKey] = $record;
        $this->saveState($state);

        $path = $this->serializePath($pathKey, self::PATHS[$pathKey], $record);

        return [
            'success' => true,
            'message' => $event['description'],
            'cost' => self::RESEARCH_COST,
            'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
            'path' => $path,
            'research' => [
                'target' => $target,
                'evidenceScore' => $evidence['score'],
                'progressGain' => $progressGain,
                'signals' => $evidence['signals'],
                'eventId' => $event['id'],
            ],
            'status' => $this->status(),
        ];
    }

    private function loadState(): array
    {
        if ($this->stateCache !== null) {
            return $this->stateCache;
        }

        $value = $this->configService->getConfig(self::CONFIG_CATEGORY, self::CONFIG_KEY_STATE, []);
        $state = is_array($value) ? $value : [];
        foreach (self::PATHS as $key => $_path) {
            if (!isset($state[$key]) || !is_array($state[$key])) {
                $state[$key] = $this->initialRecord($key);
                continue;
            }

            $state[$key] = array_merge($this->initialRecord($key), $state[$key]);
            $state[$key]['signals'] = is_array($state[$key]['signals']) ? $state[$key]['signals'] : [];
            $state[$key]['eventIds'] = is_array($state[$key]['eventIds']) ? $state[$key]['eventIds'] : [];
            $state[$key]['history'] = is_array($state[$key]['history']) ? $state[$key]['history'] : [];
        }

        $this->stateCache = $state;
        return $this->stateCache;
    }

    private function saveState(array $state): void
    {
        $this->stateCache = $state;
        $this->configService->setConfig(
            self::CONFIG_CATEGORY,
            self::CONFIG_KEY_STATE,
            $state,
            'array',
            'Hidden, emerging, and known magic discovery paths.'
        );
    }

    private function initialRecord(string $pathKey): array
    {
        return [
            'path' => $pathKey,
            'status' => 'hidden',
            'progress' => 0,
            'evidenceScore' => 0,
            'discoveryYear' => null,
            'lastResearchedYear' => null,
            'lastTarget' => null,
            'signals' => [],
            'eventIds' => [],
            'history' => [],
        ];
    }

    private function serializePath(string $key, array $path, array $record): array
    {
        $status = (string)($record['status'] ?? 'hidden');

        return [
            'key' => $key,
            'label' => $path['label'],
            'domain' => $path['domain'],
            'summary' => $path['summary'],
            'hiddenSummary' => $path['hiddenSummary'],
            'status' => $status,
            'progress' => (int)($record['progress'] ?? 0),
            'evidenceScore' => (int)($record['evidenceScore'] ?? 0),
            'discoveryYear' => $record['discoveryYear'] ?? null,
            'lastResearchedYear' => $record['lastResearchedYear'] ?? null,
            'lastTarget' => $record['lastTarget'] ?? null,
            'signals' => is_array($record['signals'] ?? null) ? array_values($record['signals']) : [],
            'eventIds' => is_array($record['eventIds'] ?? null) ? array_values($record['eventIds']) : [],
            'history' => is_array($record['history'] ?? null) ? array_values($record['history']) : [],
            'bettingHook' => $this->pathBettingHook($key, $record),
            'visibilitySummary' => $this->visibilitySummary($path, $status),
        ];
    }

    private function evidenceForPath(string $pathKey, array $target): array
    {
        $signals = [];
        $score = 12;

        $add = function (string $label, int $value, string $summary) use (&$score, &$signals): void {
            if ($value <= 0) {
                return;
            }
            $score += $value;
            $signals[] = [
                'label' => $label,
                'value' => '+' . $value,
                'summary' => $summary,
            ];
        };

        $region = $target['regionId'] ? Region::find($target['regionId']) : null;
        if ($region instanceof Region) {
            $add('Regional magic', intdiv((int)$region->magic_affinity, 5), "{$region->name} has magic affinity {$region->magic_affinity}.");
            if (in_array($region->status, ['mysterious', 'blessed', 'cursed'], true)) {
                $add('Strange status', 10, "{$region->name} is {$region->status}.");
            }
        }

        if ($target['type'] === 'region') {
            $settlementCount = Settlement::where('region_id', $target['id'])->count();
            $magicalResources = ResourceNode::where('region_id', $target['id'])
                ->whereIn('type', ['magical_spring', 'herb_garden'])
                ->count();
            $add('Settlement substrate', min(12, $settlementCount * 4), "{$settlementCount} settlements can carry research.");
            $add('Magical resources', min(20, $magicalResources * 10), "{$magicalResources} magical resource nodes are nearby.");
        }

        if ($target['type'] === 'hero') {
            $hero = Hero::find($target['id']);
            if ($hero instanceof Hero) {
                if ($hero->role === 'scholar') {
                    $add('Scholar role', 18, "{$hero->name} is a scholar.");
                }
                if ($hero->role === 'prophet') {
                    $add('Prophet role', 18, "{$hero->name} is a prophet.");
                }
                if ($hero->role === 'agent of change') {
                    $add('Agent role', 12, "{$hero->name} can reshape civic patterns.");
                }
                $add('Hero level', min(20, (int)$hero->level * 3), "{$hero->name} is level {$hero->level}.");
            }
        }

        if ($target['type'] === 'landmark') {
            $landmark = Landmark::find($target['id']);
            if ($landmark instanceof Landmark) {
                $add('Landmark magic', intdiv((int)$landmark->magic_level, 4), "{$landmark->name} has magic {$landmark->magic_level}.");
                if (in_array($landmark->type, ['ruin', 'tower', 'temple', 'grove'], true)) {
                    $add('Arcane landmark', 16, "{$landmark->name} is a {$landmark->type}.");
                }
                if (in_array('hidden', $landmark->traits ?? [], true) || in_array('ancient', $landmark->traits ?? [], true)) {
                    $add('Ancient signs', 12, "{$landmark->name} carries hidden or ancient traits.");
                }
            }
        }

        $pathBonus = match ($pathKey) {
            'ley_weaving' => $target['type'] === 'region' ? 12 : 0,
            'spirit_compacts' => $target['type'] === 'hero' ? 10 : 0,
            'ruin_script' => $target['type'] === 'landmark' ? 12 : 0,
            'storm_rites' => $region instanceof Region ? intdiv((int)$region->chaos + (int)$region->danger_level, 8) : 0,
            'civic_enchantment' => $target['type'] === 'region' ? min(15, Settlement::where('region_id', $target['id'])->count() * 5) : 0,
            default => 0,
        };
        $add('Path resonance', $pathBonus, self::PATHS[$pathKey]['label'] . ' resonates with this target.');

        return [
            'score' => max(0, min(100, $score)),
            'signals' => $signals,
        ];
    }

    private function bestPathForTarget(array $target): string
    {
        $bestPath = 'ley_weaving';
        $bestScore = -1;
        foreach (array_keys(self::PATHS) as $pathKey) {
            $score = $this->evidenceForPath($pathKey, $target)['score'];
            if ($score > $bestScore) {
                $bestPath = $pathKey;
                $bestScore = $score;
            }
        }

        return $bestPath;
    }

    private function applyDurableDiscovery(string $pathKey, array $target): void
    {
        $trait = 'magic_path_' . $pathKey;
        if ($target['regionId']) {
            $region = Region::find($target['regionId']);
            if ($region instanceof Region) {
                $region->magic_affinity = min(100, (int)$region->magic_affinity + 3);
                $region->addTrait($trait);
                $region->save();
            }
        }

        if ($target['type'] === 'hero') {
            $hero = Hero::find($target['id']);
            if ($hero instanceof Hero) {
                $feats = $hero->feats ?? [];
                $feat = 'Revealed ' . self::PATHS[$pathKey]['label'];
                if (!in_array($feat, $feats, true)) {
                    $feats[] = $feat;
                    $hero->feats = $feats;
                    $hero->save();
                }
            }
        }

        if ($target['type'] === 'landmark') {
            $landmark = Landmark::find($target['id']);
            if ($landmark instanceof Landmark) {
                $traits = $landmark->traits ?? [];
                if (!in_array($trait, $traits, true)) {
                    $traits[] = $trait;
                    $landmark->traits = $traits;
                    $landmark->magic_level = min(100, (int)$landmark->magic_level + 4);
                    $landmark->save();
                }
            }
        }
    }

    private function recordMagicEvent(string $pathKey, array $record, array $target, string $eventType): array
    {
        $path = self::PATHS[$pathKey];
        $isDiscovery = $eventType === 'magic_discovery';
        $title = $isDiscovery ? 'Magic Path Discovered: ' . $path['label'] : 'Magic Research Advances';
        $description = $isDiscovery
            ? "{$path['label']} became known through {$target['name']}."
            : "{$path['label']} research advanced through {$target['name']} to {$record['progress']}% progress.";

        $event = $this->eventRepository->createEvent([
            'title' => $title,
            'description' => $description,
            'type' => $eventType,
            'region_id' => $target['regionId'],
            'related_region_ids' => $target['regionId'] ? [$target['regionId']] : [],
            'related_hero_ids' => $target['type'] === 'hero' ? [$target['id']] : [],
            'related_landmark_ids' => $target['type'] === 'landmark' ? [$target['id']] : [],
            'year' => $this->currentYear(),
        ]);

        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'type' => $event->type,
            'year' => $event->year,
        ];
    }

    private function resolveTarget(string $targetType, string $targetId): array
    {
        if ($targetType === 'region') {
            $region = Region::find($targetId);
            if (!$region instanceof Region) {
                throw new \InvalidArgumentException("Region not found: {$targetId}");
            }
            return $this->target('region', (string)$region->id, (string)$region->name, (string)$region->id);
        }

        if ($targetType === 'hero') {
            $hero = Hero::find($targetId);
            if (!$hero instanceof Hero) {
                throw new \InvalidArgumentException("Hero not found: {$targetId}");
            }
            return $this->target('hero', (string)$hero->id, (string)$hero->name, $hero->region_id ? (string)$hero->region_id : null);
        }

        $landmark = Landmark::find($targetId);
        if (!$landmark instanceof Landmark) {
            throw new \InvalidArgumentException("Landmark not found: {$targetId}");
        }
        return $this->target('landmark', (string)$landmark->id, (string)$landmark->name, $landmark->region_id ? (string)$landmark->region_id : null);
    }

    private function target(string $type, string $id, string $name, ?string $regionId): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'name' => $name,
            'regionId' => $regionId,
        ];
    }

    private function suggestedTargets(array $state): array
    {
        $targets = [];
        foreach (Region::orderByDesc('magic_affinity')->take(3)->get() as $region) {
            $target = $this->target('region', (string)$region->id, (string)$region->name, (string)$region->id);
            $targets[] = array_merge($target, [
                'bestPath' => $this->bestPathForTarget($target),
                'reason' => "Magic {$region->magic_affinity}, chaos {$region->chaos}, status {$region->status}.",
            ]);
        }
        foreach (Hero::where('is_alive', true)->orderByDesc('level')->take(2)->get() as $hero) {
            $target = $this->target('hero', (string)$hero->id, (string)$hero->name, $hero->region_id ? (string)$hero->region_id : null);
            $targets[] = array_merge($target, [
                'bestPath' => $this->bestPathForTarget($target),
                'reason' => "{$hero->role}, level {$hero->level}.",
            ]);
        }
        foreach (Landmark::orderByDesc('magic_level')->take(2)->get() as $landmark) {
            $target = $this->target('landmark', (string)$landmark->id, (string)$landmark->name, $landmark->region_id ? (string)$landmark->region_id : null);
            $targets[] = array_merge($target, [
                'bestPath' => $this->bestPathForTarget($target),
                'reason' => "{$landmark->type}, magic {$landmark->magic_level}, status {$landmark->status}.",
            ]);
        }

        return array_slice($targets, 0, 6);
    }

    private function bettingHooks(array $paths): array
    {
        return array_values(array_filter(array_map(
            fn(array $path): ?array => $path['bettingHook'],
            $paths
        )));
    }

    private function pathBettingHook(string $pathKey, array $record): ?array
    {
        $target = $record['lastTarget'] ?? null;
        if (!is_array($target) || empty($target['id']) || ($record['status'] ?? 'hidden') !== 'emerging') {
            return null;
        }

        $progress = (int)($record['progress'] ?? 0);
        $pathLabel = self::PATHS[$pathKey]['label'];

        return [
            'id' => 'magic-hook-' . $pathKey,
            'path' => $pathKey,
            'title' => $pathLabel . ' Breakthrough',
            'summary' => "Will {$pathLabel} become known through " . ($target['name'] ?? 'the target') . '?',
            'betType' => self::PATHS[$pathKey]['betType'],
            'targetId' => (string)$target['id'],
            'targetType' => (string)$target['type'],
            'regionId' => $target['regionId'] ?? null,
            'progress' => $progress,
            'evidenceScore' => (int)($record['evidenceScore'] ?? 0),
            'minimumYears' => 1,
            'maximumYears' => $progress >= 70 ? 4 : 8,
            'confidence' => $progress >= 70 ? 'likely' : 'possible',
        ];
    }

    private function pathOptions(): array
    {
        return array_map(
            fn(string $key, array $path): array => [
                'key' => $key,
                'label' => $path['label'],
                'domain' => $path['domain'],
                'summary' => $path['summary'],
                'hiddenSummary' => $path['hiddenSummary'],
            ],
            array_keys(self::PATHS),
            self::PATHS
        );
    }

    private function targetOptions(): array
    {
        return [
            ['key' => 'region', 'label' => 'Region'],
            ['key' => 'hero', 'label' => 'Hero'],
            ['key' => 'landmark', 'label' => 'Landmark'],
        ];
    }

    private function statusForProgress(int $progress, int $evidenceScore): string
    {
        if ($progress >= 100 || $evidenceScore >= 82) {
            return 'known';
        }
        if ($progress >= 35 || $evidenceScore >= 55) {
            return 'emerging';
        }
        return 'hidden';
    }

    private function visibilitySummary(array $path, string $status): string
    {
        if ($status === 'known') {
            return $path['summary'];
        }
        if ($status === 'emerging') {
            return 'Emerging signs: ' . $path['hiddenSummary'];
        }
        return 'Hidden: ' . $path['hiddenSummary'];
    }

    private function summary(array $paths): string
    {
        $known = count(array_filter($paths, fn(array $path): bool => $path['status'] === 'known'));
        $emerging = count(array_filter($paths, fn(array $path): bool => $path['status'] === 'emerging'));
        return "{$known} magic paths known, {$emerging} emerging, " . count($paths) . ' tracked.';
    }

    private function normalizeTargetType(string $targetType): string
    {
        $normalized = strtolower(trim($targetType));
        if (!in_array($normalized, ['region', 'hero', 'landmark'], true)) {
            throw new \InvalidArgumentException("Unsupported magic research target type: {$targetType}");
        }
        return $normalized;
    }

    private function normalizePath(string $path): string
    {
        $normalized = strtolower(trim($path));
        if (!isset(self::PATHS[$normalized])) {
            throw new \InvalidArgumentException("Unsupported magic path: {$path}");
        }
        return $normalized;
    }

    private function currentYear(): int
    {
        return (int)(GameState::getCurrent()->current_year ?? 1);
    }
}
