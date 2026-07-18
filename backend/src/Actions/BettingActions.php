<?php

declare(strict_types=1);

// f:\WebDevelopment\Mytherra\backend\src\Actions\BettingActions.php

namespace App\Actions;

use Exception;
use App\Repositories\BettingRepository;
use App\Services\OddsCalculationService;
use App\Services\DivineBettingService;
use App\Core\Exceptions\ResourceNotFoundException;
use App\Utils\Logger;
use App\Models\DivineBet;
use App\Models\Hero;
use App\Models\Landmark;
use App\Models\Player;
use App\Models\Region;
use App\Models\ResourceNode;
use App\Models\Settlement;
use App\Services\ChampionService;
use App\Services\CivilizationBehaviorService;
use App\Services\GameLoopService;
use App\Services\MagicDiscoveryService;
use App\Services\PantheonService;

class BettingActions
{
    public function __construct(
        private BettingRepository $repository,
        private OddsCalculationService $oddsCalculator,
        private DivineBettingService $divineBettingService,
        private ?ChampionService $championService = null,
        private ?PantheonService $pantheonService = null,
        private ?CivilizationBehaviorService $civilizationBehaviorService = null
    ) {
        $this->championService ??= new ChampionService();
        $this->pantheonService ??= new PantheonService();
        $this->civilizationBehaviorService ??= new CivilizationBehaviorService();
    }

    /**
     * Create a new divine bet with validation
     */
    public function createDivineBet($betData)
    {
        $player = null;
        $stake = (int)$betData['divineFavorStake'];
        $stakeSpent = false;

        try {
// Validate target entity exists
            $targetExists = $this->repository->validateTargetEntity($betData['targetId']);
            if (!$targetExists) {
                throw new Exception("Target entity with ID {$betData['targetId']} not found");
            }

            $playerId = $betData['playerId'] ?? 'SINGLE_PLAYER';
            $player = Player::firstOrCreate(
                ['id' => $playerId],
                ['divine_favor' => 100]
            );

            if (!$player->spendDivineFavor($stake)) {
                throw new Exception("Insufficient divine favor to stake {$stake}");
            }
            $stakeSpent = true;

    // Calculate odds and potential payout
            $oddsResult = $this->oddsCalculator->calculateBetOdds($betData['betType'], $betData['targetId'], $betData['timeframe'], $betData['confidence']);
            $currentOdds = $oddsResult['odds'];
            $payoutProfile = $this->oddsCalculator->calculatePayoutProfile(
                $stake,
                (float)$currentOdds,
                (string)$betData['confidence']
            );
            $calculatedPayout = $payoutProfile['grossPayout'];

    // Get current game year and generate UUID
            $gameYear = $this->getCurrentGameYear();

    // Create bet using repository
            $bet = [
                'player_id' => $playerId,
                'bet_type' => $betData['betType'],
                'target_id' => $betData['targetId'],
                'description' => $betData['description'],
                'timeframe' => $betData['timeframe'],
                'confidence' => $betData['confidence'],
                'divine_favor_stake' => $betData['divineFavorStake'],
                'potential_payout' => $calculatedPayout,
                'current_odds' => $currentOdds,
                'status' => 'active',
                'placed_year' => $gameYear
            ];
            $betId = $this->repository->createBet($bet);
            return $this->repository->getBetById($betId);
        } catch (Exception $e) {
            if ($player && $stakeSpent) {
                $player->addDivineFavor($stake);
            }
            Logger::error("Error creating divine bet: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Place a new divine bet (alias for createDivineBet for controller compatibility)
     */
    public function placeDivineBet($betData)
    {
        return $this->createDivineBet($betData);
    }

    /**
     * Fetch all divine bets with filtering
     */
    public function fetchAllDivineBets($filters = [])
    {
        try {
            return $this->repository->getAllBets($filters);
        } catch (Exception $e) {
            Logger::error("Error fetching divine bets: " . $e->getMessage());
            throw $e;
        }
    }

    public function fetchDivineBetSummary(array $filters = []): array
    {
        try {
            return $this->repository->getBetSummary($filters);
        } catch (Exception $e) {
            Logger::error("Error fetching divine bet summary: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch divine bet by ID
     */
    public function fetchDivineBetById($betId)
    {
        try {
            $bet = $this->repository->getBetById($betId);
            if (!$bet) {
                throw new ResourceNotFoundException("Divine bet not found with ID: $betId");
            }
            return $bet;
        } catch (ResourceNotFoundException $e) {
            Logger::error("Divine bet not found: " . $e->getMessage());
            throw $e;
        } catch (Exception $e) {
            Logger::error("Error fetching divine bet: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch speculation events for betting opportunities
     */
    public function fetchSpeculationEvents($filters = [])
    {
        try {
            return $this->buildSpeculationEvents();
        } catch (Exception $e) {
            Logger::error("Error fetching speculation events: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch current betting odds
     */
    public function fetchBettingOdds()
    {
        try {
// Get sample targets for odds calculation
            $sampleTargets = $this->repository->getSampleTargets();

    // Generate sample odds for different bet types
            $betTypes = [
                'settlement_growth', 'landmark_discovery', 'cultural_shift',
                'hero_settlement_bond', 'hero_location_visit', 'settlement_transformation',
                'corruption_spread', 'hero_level_milestone', 'hero_death',
                'region_danger_change', 'prosperity_threshold', 'magic_discovery',
                'pantheon_intervention', 'civilization_agenda'
            ];
            $defaultTimeframe = 5;
            $defaultConfidence = 'possible';
            $oddsData = [];
            foreach ($betTypes as $betType) {
                try {
                    $targetId = $this->getTargetIdForBetType($betType, $sampleTargets);
                    $oddsResult = $this->oddsCalculator->calculateBetOdds($betType, $targetId, $defaultTimeframe, $defaultConfidence);
                    $probability = $this->oddsCalculator->calculateWinProbability($oddsResult['odds']);
                    $oddsData[$betType] = [
                        'probability' => round($probability / 100, 2),
                        'payout' => round($oddsResult['potentialPayout'], 2),
                        'confidence' => $defaultConfidence
                    ];
                } catch (Exception $e) {
                    Logger::error("Error calculating odds for {$betType}: " . $e->getMessage());
                    $oddsData[$betType] = [
                    'probability' => 0.5,
                    'payout' => 2.0,
                    'confidence' => $defaultConfidence
                    ];
                }
            }

            $formattedOddsData = [];
            foreach ($oddsData as $betType => $data) {
                $formattedOddsData[] = [
                    'eventId' => $betType, // Using betType as eventId for now since it's event-based odds
                    'odds' => ['standard' => $data['payout']], // varying structure based on frontend expectation
                    'lastUpdated' => date('c')
                ];
            }

            return $formattedOddsData;
        } catch (Exception $e) {
            Logger::error("Error fetching betting odds: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process expired bets and update their status
     */
    public function processExpiredBets()
    {
        try {
            return (new GameLoopService())->processActiveBets($this->getCurrentGameYear());
        } catch (Exception $e) {
            Logger::error("Error processing expired bets: " . $e->getMessage());
            throw $e;
        }
    }

    private function getCurrentGameYear(): int
    {
        try {
            $gameState = \App\Models\GameState::getCurrent();
            return $gameState->current_year;
        } catch (\Exception $e) {
            Logger::error("Error getting current game year: " . $e->getMessage());
            return 1;
        }
    }

    private function getTargetIdForBetType(string $betType, array $sampleTargets): string
    {
        switch ($betType) {
            case 'settlement_growth':
            case 'settlement_transformation':
            case 'prosperity_threshold':
                return $sampleTargets['settlement'][0]['id'] ?? 'sample-settlement-id';
            case 'landmark_discovery':
                return $sampleTargets['landmark'][0]['id'] ?? $sampleTargets['region'][0]['id'] ?? 'sample-landmark-id';
            case 'hero_settlement_bond':
            case 'hero_location_visit':
            case 'hero_level_milestone':
            case 'hero_death':
                return $sampleTargets['hero'][0]['id'] ?? 'sample-hero-id';
            case 'corruption_spread':
            case 'cultural_shift':
            case 'region_danger_change':
            case 'civilization_agenda':
                return $sampleTargets['region'][0]['id'] ?? 'sample-region-id';
            case 'magic_discovery':
                return $sampleTargets['region'][0]['id'] ?? $sampleTargets['landmark'][0]['id'] ?? 'sample-magic-target-id';
            case 'pantheon_intervention':
                return $sampleTargets['region'][0]['id'] ?? 'sample-pantheon-region-id';
            default:
                return 'sample-id';
        }
    }

    private function buildSpeculationEvents(): array
    {
        $events = [];

        foreach (array_slice($this->championService->status()['bettingHooks'] ?? [], 0, 4) as $hook) {
            if (empty($hook['id']) || empty($hook['targetId']) || empty($hook['betType'])) {
                continue;
            }

            $events[] = $this->makeSpeculationEvent(
                (string)$hook['id'],
                (string)$hook['title'],
                (string)$hook['summary'],
                (string)$hook['betType'],
                (string)$hook['targetId'],
                !empty($hook['regionId']) ? (string)$hook['regionId'] : null,
                (int)($hook['minimumYears'] ?? 1),
                (int)($hook['maximumYears'] ?? 5),
                [
                    $this->makeBettingOption(
                        (string)$hook['id'],
                        (string)$hook['summary'],
                        (string)$hook['targetId'],
                        (string)$hook['betType'],
                        10,
                        (int)($hook['maximumYears'] ?? 5),
                        (string)($hook['confidence'] ?? 'possible')
                    )
                ]
            );
        }

        foreach (array_slice((new MagicDiscoveryService())->status()['bettingHooks'] ?? [], 0, 3) as $hook) {
            if (empty($hook['path']) || empty($hook['targetId']) || empty($hook['betType'])) {
                continue;
            }

            $minimumYears = (int)($hook['minimumYears'] ?? 1);
            $maximumYears = (int)($hook['maximumYears'] ?? 8);

            $events[] = $this->makeSpeculationEvent(
                'magic-discovery-' . $hook['path'] . '-' . $hook['targetId'],
                (string)$hook['title'],
                (string)$hook['summary'],
                (string)$hook['betType'],
                (string)$hook['targetId'],
                !empty($hook['regionId']) ? (string)$hook['regionId'] : null,
                $minimumYears,
                $maximumYears,
                [
                    $this->makeBettingOption(
                        'magic-thread-' . $hook['path'],
                        (string)$hook['summary'],
                        (string)$hook['targetId'],
                        (string)$hook['betType'],
                        10,
                        $maximumYears,
                        (string)($hook['confidence'] ?? 'possible')
                    )
                ]
            );
        }

        foreach (array_slice($this->pantheonService->status()['bettingHooks'] ?? [], 0, 3) as $hook) {
            if (empty($hook['id']) || empty($hook['targetId']) || empty($hook['betType'])) {
                continue;
            }

            $minimumYears = (int)($hook['minimumYears'] ?? 1);
            $maximumYears = (int)($hook['maximumYears'] ?? 8);

            $events[] = $this->makeSpeculationEvent(
                (string)$hook['id'],
                (string)$hook['title'],
                (string)$hook['summary'],
                (string)$hook['betType'],
                (string)$hook['targetId'],
                !empty($hook['regionId']) ? (string)$hook['regionId'] : null,
                $minimumYears,
                $maximumYears,
                [
                    $this->makeBettingOption(
                        (string)$hook['id'],
                        (string)$hook['summary'],
                        (string)$hook['targetId'],
                        (string)$hook['betType'],
                        10,
                        $maximumYears,
                        (string)($hook['confidence'] ?? 'possible')
                    )
                ]
            );
        }

        foreach ($this->civilizationAgendaCandidates() as $candidate) {
            $agenda = $candidate['agenda'];
            $regionId = (string)$agenda['regionId'];
            $regionName = (string)$agenda['regionName'];
            $behavior = (string)$agenda['dominantBehavior'];
            $behaviorLabel = (string)$agenda['dominantBehaviorLabel'];
            $score = (int)$agenda['score'];
            $maximumYears = (int)$candidate['maximumYears'];

            $events[] = $this->makeSpeculationEvent(
                'civilization-agenda-' . $regionId . '-' . $behavior,
                'Civilization Agenda: ' . $regionName,
                "{$regionName} shows {$behaviorLabel} pressure at {$score}/100. Will its civic agenda become a recorded decision?",
                'civilization_agenda',
                $regionId,
                $regionId,
                1,
                $maximumYears,
                [
                    $this->makeBettingOption(
                        'civilization-' . $behavior,
                        "{$regionName} follows a {$behaviorLabel} agenda",
                        $regionId,
                        'civilization_agenda',
                        10,
                        $maximumYears,
                        (string)$candidate['confidence']
                    )
                ]
            );
        }

        foreach ($this->cultureShiftCandidates() as $candidate) {
            /** @var Region $region */
            $region = $candidate['region'];
            $targetCulture = (string)$candidate['targetCulture'];
            $confidence = (string)$candidate['confidence'];
            $maximumYears = (int)$candidate['maximumYears'];

            $events[] = $this->makeSpeculationEvent(
                'culture-shift-' . $region->id . '-' . $targetCulture,
                'Culture Shift: ' . $region->name,
                "{$region->name} is {$region->cultural_influence} with {$candidate['summary']} Will it develop a stronger {$targetCulture} identity?",
                'cultural_shift',
                $region->id,
                $region->id,
                2,
                $maximumYears,
                [
                    $this->makeBettingOption(
                        'culture-' . $targetCulture,
                        "{$region->name} develops a distinct {$targetCulture} culture",
                        $region->id,
                        'cultural_shift',
                        10,
                        $maximumYears,
                        $confidence
                    )
                ]
            );
        }

        foreach (Settlement::orderBy('prosperity', 'desc')->take(3)->get() as $settlement) {
            $events[] = $this->makeSpeculationEvent(
                'settlement-growth-' . $settlement->id,
                'Settlement Growth: ' . $settlement->name,
                "{$settlement->name} has {$settlement->population} people and prosperity {$settlement->prosperity}. Will it become a major settlement?",
                'settlement_growth',
                $settlement->id,
                $settlement->region_id,
                2,
                6,
                [
                    $this->makeBettingOption('growth-major', "Back {$settlement->name} to grow into a major settlement", $settlement->id, 'settlement_growth', 10, 6, 'possible')
                ]
            );

            $events[] = $this->makeSpeculationEvent(
                'prosperity-threshold-' . $settlement->id,
                'Prosperity Threshold: ' . $settlement->name,
                "{$settlement->name} is at prosperity {$settlement->prosperity}. Will it reach prosperity 80?",
                'prosperity_threshold',
                $settlement->id,
                $settlement->region_id,
                1,
                5,
                [
                    $this->makeBettingOption('prosperity-80', "{$settlement->name} reaches prosperity 80", $settlement->id, 'prosperity_threshold', 10, 5, 'possible')
                ]
            );
        }

        foreach (Hero::orderBy('level', 'desc')->take(3)->get() as $hero) {
            $events[] = $this->makeSpeculationEvent(
                'hero-milestone-' . $hero->id,
                'Hero Milestone: ' . $hero->name,
                "{$hero->name} is level {$hero->level}. Will they reach a meaningful milestone?",
                'hero_level_milestone',
                $hero->id,
                $hero->region_id,
                1,
                5,
                [
                    $this->makeBettingOption('hero-level-5', "{$hero->name} reaches level 5 or higher", $hero->id, 'hero_level_milestone', 8, 5, 'possible')
                ]
            );

            $events[] = $this->makeSpeculationEvent(
                'hero-death-' . $hero->id,
                'Hero Mortality: ' . $hero->name,
                "{$hero->name} is {$hero->age} years old and level {$hero->level}. Will death find them?",
                'hero_death',
                $hero->id,
                $hero->region_id,
                1,
                8,
                [
                    $this->makeBettingOption('hero-dies', "{$hero->name} dies within the prediction window", $hero->id, 'hero_death', 12, 8, 'long_shot')
                ]
            );
        }

        foreach (Region::orderBy('chaos', 'desc')->take(3)->get() as $region) {
            $events[] = $this->makeSpeculationEvent(
                'corruption-spread-' . $region->id,
                'Corruption Spread: ' . $region->name,
                "{$region->name} has chaos {$region->chaos} and danger {$region->danger_level}. Will corruption overtake it?",
                'corruption_spread',
                $region->id,
                $region->id,
                2,
                7,
                [
                    $this->makeBettingOption('region-corrupts', "{$region->name} becomes cursed or war-torn", $region->id, 'corruption_spread', 10, 7, 'possible')
                ]
            );

            $events[] = $this->makeSpeculationEvent(
                'danger-change-' . $region->id,
                'Danger Extreme: ' . $region->name,
                "{$region->name} currently has danger {$region->danger_level}. Will it reach an extreme danger state?",
                'region_danger_change',
                $region->id,
                $region->id,
                1,
                6,
                [
                    $this->makeBettingOption('danger-extreme', "{$region->name} reaches extreme danger", $region->id, 'region_danger_change', 8, 6, 'possible')
                ]
            );
        }

        foreach (Landmark::whereNull('discovered_year')->take(2)->get() as $landmark) {
            $events[] = $this->makeSpeculationEvent(
                'landmark-discovery-' . $landmark->id,
                'Landmark Discovery: ' . $landmark->name,
                "{$landmark->name} remains hidden. Will mortals uncover it?",
                'landmark_discovery',
                $landmark->id,
                $landmark->region_id,
                1,
                6,
                [
                    $this->makeBettingOption('landmark-found', "{$landmark->name} is discovered", $landmark->id, 'landmark_discovery', 12, 6, 'long_shot')
                ]
            );
        }

        foreach ($this->landmarkCorruptionCandidates() as $candidate) {
            /** @var Landmark $landmark */
            $landmark = $candidate['landmark'];
            $confidence = (string)$candidate['confidence'];
            $maximumYears = (int)$candidate['maximumYears'];

            $events[] = $this->makeSpeculationEvent(
                'landmark-corruption-' . $landmark->id,
                'Landmark Corruption: ' . $landmark->name,
                "{$landmark->name} is {$landmark->status} {$landmark->type} with magic {$landmark->magic_level}, danger {$landmark->danger_level}, and {$candidate['summary']} Will corruption overtake it?",
                'corruption_spread',
                $landmark->id,
                $landmark->region_id,
                2,
                $maximumYears,
                [
                    $this->makeBettingOption(
                        'landmark-corrupted',
                        "{$landmark->name} becomes corrupted",
                        $landmark->id,
                        'corruption_spread',
                        10,
                        $maximumYears,
                        $confidence
                    )
                ]
            );
        }

        foreach (ResourceNode::whereIn('status', ['active', 'contested', 'corrupted'])->take(2)->get() as $resource) {
            $events[] = $this->makeSpeculationEvent(
                'resource-disruption-' . $resource->id,
                'Resource Disruption: ' . $resource->name,
                "{$resource->name} produces {$resource->output} output and is {$resource->status}. Will corruption spread here?",
                'corruption_spread',
                $resource->id,
                $resource->region_id,
                1,
                5,
                [
                    $this->makeBettingOption('resource-corrupted', "{$resource->name} becomes corrupted", $resource->id, 'corruption_spread', 10, 5, 'possible')
                ]
            );
        }

        return array_slice($events, 0, 25);
    }

    private function makeSpeculationEvent(
        string $id,
        string $title,
        string $description,
        string $betType,
        string $targetId,
        ?string $regionId,
        int $minimumYears,
        int $maximumYears,
        array $options
    ): array {
        return [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'eventType' => $betType,
            'targetId' => $targetId,
            'regionId' => $regionId,
            'targetState' => $this->buildTargetState($targetId),
            'oddsFactors' => $this->collectEventOddsFactors($options),
            'timeframe' => [
                'minimum' => $minimumYears,
                'maximum' => $maximumYears
            ],
            'bettingOptions' => $options,
            'createdAt' => date('c')
        ];
    }

    private function makeBettingOption(
        string $id,
        string $description,
        string $targetId,
        string $betType,
        int $minimumStake,
        int $timeframe,
        string $confidence
    ): array {
        $oddsResult = $this->oddsCalculator->calculateBetOdds($betType, $targetId, $timeframe, $confidence);
        $odds = (float)$oddsResult['odds'];
        $payoutProfile = $this->oddsCalculator->calculatePayoutProfile(
            $minimumStake,
            $odds,
            $confidence
        );

        return [
            'id' => $id,
            'description' => $description,
            'betType' => $betType,
            'targetId' => $targetId,
            'currentOdds' => $odds,
            'minimumStake' => $minimumStake,
            'potentialPayout' => $payoutProfile['grossPayout'],
            'payoutProfile' => $payoutProfile,
            'timeframe' => $timeframe,
            'confidence' => $confidence,
            'targetState' => $this->buildTargetState($targetId),
            'oddsFactors' => $this->buildOddsFactors($betType, $targetId, $timeframe, $confidence, $odds)
        ];
    }

    private function buildTargetState(string $targetId): ?array
    {
        if ($settlement = Settlement::find($targetId)) {
            return [
                'type' => 'settlement',
                'name' => $settlement->name,
                'summary' => "{$settlement->name}: {$settlement->population} people, prosperity {$settlement->prosperity}, {$settlement->status} {$settlement->type}.",
                'signals' => [
                    ['label' => 'Population', 'value' => (string)$settlement->population],
                    ['label' => 'Prosperity', 'value' => (string)$settlement->prosperity],
                    ['label' => 'Status', 'value' => $this->formatLabel($settlement->status)],
                    ['label' => 'Type', 'value' => $this->formatLabel($settlement->type)],
                ],
            ];
        }

        if ($hero = Hero::find($targetId)) {
            $signals = [
                ['label' => 'Level', 'value' => (string)$hero->level],
                ['label' => 'Age', 'value' => (string)$hero->age],
                ['label' => 'Status', 'value' => $this->formatLabel((string)$hero->status)],
                ['label' => 'Alive', 'value' => $hero->is_alive ? 'Yes' : 'No'],
            ];
            $summary = "{$hero->name}: level {$hero->level}, age {$hero->age}, {$hero->status}.";
            $champion = $this->championService->championProfileForHero($hero);
            if ($champion !== null) {
                $quest = is_array($champion['currentQuest'] ?? null) ? $champion['currentQuest'] : null;
                $signals[] = ['label' => 'Champion rank', 'value' => (string)$champion['rank']];
                $signals[] = ['label' => 'Champion bond', 'value' => $champion['bond'] . '/100'];
                $signals[] = ['label' => 'Champion focus', 'value' => (string)$champion['focusLabel']];
                if ($quest !== null) {
                    $signals[] = ['label' => 'Quest progress', 'value' => (string)($quest['progress'] ?? 0) . '%'];
                }
                $signals[] = ['label' => 'Rivalry pressure', 'value' => (string)$champion['rivalryPressure']];
                $summary .= " Champion rank {$champion['rank']}, {$champion['focusLabel']} focus, bond {$champion['bond']}/100.";
            }

            return [
                'type' => 'hero',
                'name' => $hero->name,
                'summary' => $summary,
                'signals' => $signals,
            ];
        }

        if ($region = Region::find($targetId)) {
            $signals = [
                ['label' => 'Prosperity', 'value' => (string)$region->prosperity],
                ['label' => 'Chaos', 'value' => (string)$region->chaos],
                ['label' => 'Danger', 'value' => (string)$region->danger_level],
                ['label' => 'Magic', 'value' => (string)$region->magic_affinity],
                ['label' => 'Culture', 'value' => $this->formatLabel((string)$region->cultural_influence)],
                ['label' => 'Trade routes', 'value' => (string)count($region->trade_routes ?? [])],
            ];
            $summary = "{$region->name}: prosperity {$region->prosperity}, chaos {$region->chaos}, danger {$region->danger_level}, magic {$region->magic_affinity}, culture {$region->cultural_influence}.";
            $agenda = $this->civilizationAgendaForRegion((string)$region->id);
            if ($agenda !== null) {
                $signals[] = ['label' => 'Civic agenda', 'value' => (string)$agenda['dominantBehaviorLabel']];
                $signals[] = ['label' => 'Agenda score', 'value' => (string)$agenda['score'] . '/100'];
                $signals[] = ['label' => 'Agenda priority', 'value' => $this->formatLabel((string)$agenda['priorityTier'])];
                $summary .= " Civic agenda: {$agenda['dominantBehaviorLabel']} at {$agenda['score']}/100.";
            }

            return [
                'type' => 'region',
                'name' => $region->name,
                'summary' => $summary,
                'signals' => $signals,
            ];
        }

        if ($resource = ResourceNode::find($targetId)) {
            return [
                'type' => 'resource',
                'name' => $resource->name,
                'summary' => "{$resource->name}: {$resource->type} output {$resource->output}, status {$resource->status}.",
                'signals' => [
                    ['label' => 'Type', 'value' => $this->formatLabel($resource->type)],
                    ['label' => 'Output', 'value' => (string)$resource->output],
                    ['label' => 'Status', 'value' => $this->formatLabel($resource->status)],
                ],
            ];
        }

        if ($landmark = Landmark::find($targetId)) {
            return [
                'type' => 'landmark',
                'name' => $landmark->name,
                'summary' => "{$landmark->name}: {$landmark->status} {$landmark->type}, magic {$landmark->magic_level}, danger {$landmark->danger_level}.",
                'signals' => [
                    ['label' => 'Type', 'value' => $this->formatLabel($landmark->type)],
                    ['label' => 'Status', 'value' => $this->formatLabel($landmark->status)],
                    ['label' => 'Magic', 'value' => (string)$landmark->magic_level],
                    ['label' => 'Danger', 'value' => (string)$landmark->danger_level],
                    ['label' => 'Discovery', 'value' => $landmark->discovered_year === null ? 'Hidden' : 'Known'],
                    ['label' => 'Traits', 'value' => (string)count($landmark->traits ?? [])],
                ],
            ];
        }

        return null;
    }

    private function buildOddsFactors(string $betType, string $targetId, int $timeframe, string $confidence, float $odds): array
    {
        $factors = [
            [
                'label' => 'Base odds',
                'value' => number_format(DivineBet::getBaseOddsForType($betType), 1) . ':1',
                'effect' => DivineBet::BET_TYPE_CONFIGS[$betType]['description'] ?? 'Bet type baseline',
            ],
            [
                'label' => 'Confidence',
                'value' => $this->formatLabel($confidence),
                'effect' => 'Higher confidence lowers payout; long shots raise it.',
            ],
            [
                'label' => 'Timeframe',
                'value' => "{$timeframe} years",
                'effect' => $timeframe <= 3 ? 'Short prediction window raises risk.' : 'Longer window gives the world more time to meet the condition.',
            ],
            [
                'label' => 'Current odds',
                'value' => number_format($odds, 2) . ':1',
                'effect' => 'Final calculated payout rate for this option.',
            ],
            [
                'label' => 'Payout tuning',
                'value' => $this->oddsCalculator->calculatePayoutProfile(10, $odds, $confidence)['grossMultiplier'] . 'x',
                'effect' => 'Gross payout is capped and dampened by confidence so extreme odds stay playable.',
            ],
        ];

        if ($settlement = Settlement::find($targetId)) {
            $factors[] = ['label' => 'Population', 'value' => (string)$settlement->population, 'effect' => 'Larger settlements are closer to growth thresholds.'];
            $factors[] = ['label' => 'Prosperity', 'value' => (string)$settlement->prosperity, 'effect' => 'Prosperity supports growth and threshold bets.'];
        } elseif ($hero = Hero::find($targetId)) {
            $factors[] = ['label' => 'Level', 'value' => (string)$hero->level, 'effect' => 'Experienced heroes are closer to milestones and safer from danger.'];
            $factors[] = ['label' => 'Age', 'value' => (string)$hero->age, 'effect' => 'Older heroes face higher mortality pressure.'];
            $champion = $this->championService->championProfileForHero($hero);
            if ($champion !== null) {
                $quest = is_array($champion['currentQuest'] ?? null) ? $champion['currentQuest'] : null;
                $factors[] = ['label' => 'Champion rank', 'value' => (string)$champion['rank'], 'effect' => 'Higher rank speeds autonomous champion outcomes.'];
                $factors[] = ['label' => 'Champion bond', 'value' => $champion['bond'] . '/100', 'effect' => 'Bond increases quest follow-through and era legacy weight.'];
                if ($quest !== null) {
                    $factors[] = ['label' => 'Quest progress', 'value' => (string)($quest['progress'] ?? 0) . '%', 'effect' => 'Completed champion quests can resolve hero milestone wagers.'];
                }
            }
        } elseif ($region = Region::find($targetId)) {
            $factors[] = ['label' => 'Chaos', 'value' => (string)$region->chaos, 'effect' => 'Chaos raises corruption and instability risk.'];
            $factors[] = ['label' => 'Danger', 'value' => (string)$region->danger_level, 'effect' => 'Danger pushes extreme-state predictions closer.'];
            $factors[] = ['label' => 'Magic affinity', 'value' => (string)$region->magic_affinity, 'effect' => 'Magic-heavy regions are more volatile.'];
            if ($betType === 'cultural_shift') {
                $cultureForecast = $this->cultureShiftForecastForRegion($region);
                $factors[] = ['label' => 'Current culture', 'value' => $this->formatLabel((string)$region->cultural_influence), 'effect' => 'This wager resolves when the region develops a distinct non-pastoral culture.'];
                $factors[] = ['label' => 'Trade routes', 'value' => (string)count($region->trade_routes ?? []), 'effect' => 'Connected regions can now push culture across trade routes.'];
                if ($cultureForecast !== null) {
                    $factors[] = ['label' => 'Forecast culture', 'value' => $this->formatLabel((string)$cultureForecast['targetCulture']), 'effect' => (string)$cultureForecast['summary']];
                }
            }
            if ($betType === 'civilization_agenda') {
                $agenda = $this->civilizationAgendaForRegion((string)$region->id);
                if ($agenda !== null) {
                    $factors[] = ['label' => 'Dominant agenda', 'value' => (string)$agenda['dominantBehaviorLabel'], 'effect' => (string)$agenda['summary']];
                    $factors[] = ['label' => 'Agenda pressure', 'value' => (string)$agenda['score'] . '/100', 'effect' => 'Higher pressure makes an automated civic decision more likely before the window closes.'];
                    $factors[] = ['label' => 'Priority tier', 'value' => $this->formatLabel((string)$agenda['priorityTier']), 'effect' => 'Urgent and active agendas are processed sooner by the world tick.'];
                    $signalSummary = $this->civilizationSignalSummary($agenda);
                    if ($signalSummary !== '') {
                        $factors[] = ['label' => 'Agenda signals', 'value' => $signalSummary, 'effect' => 'Visible score signals explain the civic pressure behind this forecast.'];
                    }
                }
            }
        } elseif ($resource = ResourceNode::find($targetId)) {
            $factors[] = ['label' => 'Output', 'value' => (string)$resource->output, 'effect' => 'Low output makes disruption and depletion more likely.'];
            $factors[] = ['label' => 'Status', 'value' => $this->formatLabel($resource->status), 'effect' => 'Contested, corrupted, and unstable nodes carry more risk.'];
        } elseif ($landmark = Landmark::find($targetId)) {
            $factors[] = ['label' => 'Magic', 'value' => (string)$landmark->magic_level, 'effect' => 'High magic can draw discoveries and disruption.'];
            $factors[] = ['label' => 'Danger', 'value' => (string)$landmark->danger_level, 'effect' => 'Danger can delay safe discovery.'];
            if ($betType === 'corruption_spread') {
                $risk = $this->landmarkCorruptionRisk($landmark);
                $factors[] = ['label' => 'Corruption risk', 'value' => (string)$risk['score'] . '/100', 'effect' => (string)$risk['summary']];
                $factors[] = ['label' => 'Landmark status', 'value' => $this->formatLabel((string)$landmark->status), 'effect' => 'Haunted, active, magical, and dangerous landmarks are more likely to cross into corruption.'];
            }
        }

        if ($betType === 'magic_discovery') {
            $path = (new MagicDiscoveryService())->pathsForTarget($targetId)[0] ?? null;
            if (is_array($path)) {
                $factors[] = ['label' => 'Magic path', 'value' => (string)$path['label'], 'effect' => 'This wager resolves when the tracked path becomes known.'];
                $factors[] = ['label' => 'Path status', 'value' => $this->formatLabel((string)$path['status']), 'effect' => 'Only emerging paths create live discovery wagers.'];
                $factors[] = ['label' => 'Path progress', 'value' => (string)$path['progress'] . '%', 'effect' => 'Higher progress means the breakthrough is closer and pays less.'];
                $factors[] = ['label' => 'Evidence', 'value' => (string)$path['evidenceScore'], 'effect' => 'Evidence comes from the latest researched target state.'];
            }
        }

        if ($betType === 'pantheon_intervention') {
            $hook = $this->pantheonHookForRegion($targetId);
            if ($hook !== null) {
                $factors[] = ['label' => 'Pantheon actor', 'value' => (string)$hook['deityName'], 'effect' => 'This deity currently has the strongest visible pressure over the region.'];
                $factors[] = ['label' => 'Pressure tier', 'value' => $this->formatLabel((string)$hook['pressureTier']), 'effect' => 'Higher pressure makes a direct intervention more likely and lowers payout.'];
                $factors[] = ['label' => 'Political stage', 'value' => $this->formatLabel((string)$hook['politicalStage']), 'effect' => 'Escalating pantheon politics can turn pressure into action.'];
            }
        }

        return $factors;
    }

    private function pantheonHookForRegion(string $regionId): ?array
    {
        foreach ($this->pantheonService->status()['bettingHooks'] ?? [] as $hook) {
            if (!is_array($hook) || (string)($hook['targetId'] ?? '') !== $regionId) {
                continue;
            }

            return $hook;
        }

        return null;
    }

    private function civilizationAgendaCandidates(): array
    {
        $candidates = [];
        foreach ($this->civilizationBehaviorService->status()['regionAgendas'] ?? [] as $agenda) {
            if (!is_array($agenda)) {
                continue;
            }

            $score = (int)($agenda['score'] ?? 0);
            if ($score < 35 || empty($agenda['regionId']) || empty($agenda['dominantBehavior'])) {
                continue;
            }

            $candidates[] = [
                'agenda' => $agenda,
                'score' => $score,
                'confidence' => $score >= 75 ? 'likely' : 'possible',
                'maximumYears' => $score >= 75 ? 4 : 6,
            ];
        }

        usort(
            $candidates,
            fn(array $left, array $right): int => ((int)$right['score'] <=> (int)$left['score'])
                ?: strcmp((string)($left['agenda']['regionName'] ?? ''), (string)($right['agenda']['regionName'] ?? ''))
        );

        return array_slice($candidates, 0, 3);
    }

    private function civilizationAgendaForRegion(string $regionId): ?array
    {
        foreach ($this->civilizationBehaviorService->status()['regionAgendas'] ?? [] as $agenda) {
            if (!is_array($agenda) || (string)($agenda['regionId'] ?? '') !== $regionId) {
                continue;
            }

            return $agenda;
        }

        return null;
    }

    private function civilizationSignalSummary(array $agenda): string
    {
        $signals = [];
        foreach (array_slice($agenda['signals'] ?? [], 0, 3) as $signal) {
            if (!is_array($signal) || empty($signal['label']) || !isset($signal['value'])) {
                continue;
            }

            $signals[] = $signal['label'] . ' ' . $signal['value'];
        }

        return implode(', ', $signals);
    }

    private function cultureShiftCandidates(): array
    {
        $candidates = [];
        foreach (Region::orderByDesc('magic_affinity')->get() as $region) {
            $candidate = $this->cultureShiftForecastForRegion($region);
            if ($candidate === null) {
                continue;
            }

            $candidates[] = array_merge($candidate, ['region' => $region]);
        }

        usort(
            $candidates,
            fn(array $left, array $right): int => ((int)$right['score'] <=> (int)$left['score'])
                ?: strcmp((string)$left['region']->name, (string)$right['region']->name)
        );

        return array_slice($candidates, 0, 3);
    }

    private function cultureShiftForecastForRegion(Region $region): ?array
    {
        $currentCulture = (string)($region->cultural_influence ?? 'pastoral');
        if (!in_array($currentCulture, ['pastoral', 'stable', 'peaceful', 'scholarly'], true)) {
            return null;
        }

        $scores = [
            'scholarly' => 0,
            'martial' => 0,
            'mystical' => 0,
            'mercantile' => 0,
        ];
        $routeSignals = [];
        foreach ($this->connectedCultureRegions($region) as $connectedRegion) {
            $culture = (string)($connectedRegion->cultural_influence ?? 'pastoral');
            if (!array_key_exists($culture, $scores)) {
                continue;
            }

            $strength = 1
                + ((int)$connectedRegion->prosperity >= 70 ? 1 : 0)
                + ((int)($connectedRegion->population_total ?? 0) >= 2000 ? 1 : 0);
            $scores[$culture] += min(3, $strength);
            $routeSignals[] = "{$connectedRegion->name} exports {$culture}";
        }

        if ((int)$region->magic_affinity >= 65) {
            $scores['mystical'] += 2;
            $scores['scholarly'] += 1;
        }
        if ((int)$region->danger_level >= 55 || (int)$region->chaos >= 60) {
            $scores['martial'] += 2;
        }
        if ((int)$region->prosperity >= 70) {
            $scores['mercantile'] += 1;
            $scores['scholarly'] += 1;
        }
        foreach ($region->regional_traits ?? [] as $trait) {
            if (str_starts_with((string)$trait, 'myth_') || in_array($trait, ['mythic_memory', 'cross_region_lore'], true)) {
                $scores['mystical'] += 1;
                $scores['scholarly'] += 1;
            }
            if (in_array($trait, ['border_tension', 'martial_tradition'], true)) {
                $scores['martial'] += 1;
            }
            if ($trait === 'trade_culture_exchange') {
                $scores['mercantile'] += 1;
            }
        }

        arsort($scores);
        $targetCulture = (string)array_key_first($scores);
        $score = (int)($scores[$targetCulture] ?? 0);
        if ($score < 3 || $targetCulture === $currentCulture) {
            return null;
        }

        return [
            'targetCulture' => $targetCulture,
            'score' => $score,
            'confidence' => $score >= 6 ? 'likely' : 'possible',
            'maximumYears' => $score >= 6 ? 5 : 7,
            'summary' => $routeSignals === []
                ? "local pressure favors {$targetCulture} culture"
                : implode(', ', array_slice($routeSignals, 0, 2)) . "; pressure favors {$targetCulture} culture",
        ];
    }

    private function connectedCultureRegions(Region $region): array
    {
        $directRouteIds = array_values(array_unique(array_filter(array_map(
            fn($value): string => (string)$value,
            $region->trade_routes ?? []
        ))));
        $reverseRouteIds = [];
        foreach (Region::where('id', '!=', (string)$region->id)->get() as $candidate) {
            $candidateRoutes = array_values(array_unique(array_filter(array_map(
                fn($value): string => (string)$value,
                $candidate->trade_routes ?? []
            ))));
            if (in_array((string)$region->id, $candidateRoutes, true)) {
                $reverseRouteIds[] = (string)$candidate->id;
            }
        }

        $connectedIds = array_values(array_unique(array_merge($directRouteIds, $reverseRouteIds)));
        if ($connectedIds === []) {
            return [];
        }

        return Region::whereIn('id', $connectedIds)->get()->all();
    }

    private function landmarkCorruptionCandidates(): array
    {
        $candidates = [];
        foreach (Landmark::orderByDesc('danger_level')->orderByDesc('magic_level')->get() as $landmark) {
            if ($landmark->isCorrupted() || (string)$landmark->status === Landmark::STATUS_CORRUPTED) {
                continue;
            }
            if ($landmark->discovered_year === null && !$landmark->isAccessible()) {
                continue;
            }

            $risk = $this->landmarkCorruptionRisk($landmark);
            if ((int)$risk['score'] < 45) {
                continue;
            }

            $score = (int)$risk['score'];
            $candidates[] = [
                'landmark' => $landmark,
                'score' => $score,
                'summary' => $risk['summary'],
                'confidence' => $score >= 75 ? 'likely' : 'possible',
                'maximumYears' => $score >= 75 ? 5 : 8,
            ];
        }

        usort(
            $candidates,
            fn(array $left, array $right): int => ((int)$right['score'] <=> (int)$left['score'])
                ?: strcmp((string)$left['landmark']->name, (string)$right['landmark']->name)
        );

        return array_slice($candidates, 0, 3);
    }

    private function landmarkCorruptionRisk(Landmark $landmark): array
    {
        $score = (int)round(((int)$landmark->danger_level * 0.55) + ((int)$landmark->magic_level * 0.30));
        $signals = [];

        if ((int)$landmark->danger_level >= 60) {
            $signals[] = 'high danger';
            $score += 10;
        }
        if ((int)$landmark->magic_level >= 70) {
            $signals[] = 'high magic';
            $score += 8;
        }
        if ((string)$landmark->status === Landmark::STATUS_HAUNTED) {
            $signals[] = 'haunted status';
            $score += 12;
        }
        if ((string)$landmark->status === Landmark::STATUS_ACTIVE) {
            $signals[] = 'active power';
            $score += 5;
        }
        foreach ($landmark->traits ?? [] as $trait) {
            if (in_array((string)$trait, [Landmark::TRAIT_PORTAL, Landmark::TRAIT_MAGICAL, Landmark::TRAIT_ANCIENT], true)) {
                $signals[] = $this->formatLabel((string)$trait);
                $score += 5;
            }
        }

        $signals = array_values(array_unique($signals));

        return [
            'score' => max(0, min(100, $score)),
            'summary' => $signals === []
                ? 'low visible landmark risk'
                : 'risk signals: ' . implode(', ', array_slice($signals, 0, 4)),
        ];
    }

    private function collectEventOddsFactors(array $options): array
    {
        $factors = [];
        $seen = [];

        foreach ($options as $option) {
            foreach (($option['oddsFactors'] ?? []) as $factor) {
                $key = ($factor['label'] ?? '') . '|' . ($factor['value'] ?? '');
                if ($key === '|' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $factors[] = $factor;
                if (count($factors) >= 8) {
                    return $factors;
                }
            }
        }

        return $factors;
    }

    private function formatLabel(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value));
    }
}
