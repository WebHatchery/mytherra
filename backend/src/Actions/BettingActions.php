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
use App\Services\GameLoopService;

class BettingActions
{
    public function __construct(private BettingRepository $repository, private OddsCalculationService $oddsCalculator, private DivineBettingService $divineBettingService)
    {
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
            $potentialPayoutMultiplier = $oddsResult['potentialPayout'];
            $calculatedPayout = (int)floor($betData['divineFavorStake'] * $potentialPayoutMultiplier);

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
                'corruption_spread'
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
            case 'region_danger_change':
                return $sampleTargets['region'][0]['id'] ?? 'sample-region-id';
            default:
                return 'sample-id';
        }
    }

    private function buildSpeculationEvents(): array
    {
        $events = [];

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

        return array_slice($events, 0, 12);
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

        return [
            'id' => $id,
            'description' => $description,
            'targetId' => $targetId,
            'currentOdds' => $odds,
            'minimumStake' => $minimumStake,
            'potentialPayout' => (int)floor($minimumStake * (float)$oddsResult['potentialPayout'])
        ];
    }
}
