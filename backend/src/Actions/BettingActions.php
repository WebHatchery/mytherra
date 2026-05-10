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
        try {
// Validate target entity exists
            $targetExists = $this->repository->validateTargetEntity($betData['targetId']);
            if (!$targetExists) {
                throw new Exception("Target entity with ID {$betData['targetId']} not found");
            }

    // Calculate odds and potential payout
            $oddsResult = $this->oddsCalculator->calculateBetOdds($betData['betType'], $betData['targetId'], $betData['timeframe'], $betData['confidence']);
            $currentOdds = $oddsResult['odds'];
            $potentialPayoutMultiplier = $oddsResult['potentialPayout'];
            $calculatedPayout = (int)floor($betData['divineFavorStake'] * $potentialPayoutMultiplier);

    // Get current game year and generate UUID
            $gameYear = $this->getCurrentGameYear();

    // Create bet using repository
            $bet = [
                'player_id' => $betData['playerId'] ?? 'SINGLE_PLAYER',
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
            return $this->repository->getSpeculationEvents($filters);
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
            return $this->repository->processExpiredBets($this->getCurrentGameYear());
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
                return $sampleTargets['settlement'] ?? 'sample-settlement-id';
            case 'landmark_discovery':
                return $sampleTargets['landmark'] ?? 'sample-landmark-id';
            case 'hero_settlement_bond':
            case 'hero_location_visit':
                return $sampleTargets['hero'] ?? 'sample-hero-id';
            case 'corruption_spread':
                return $sampleTargets['region'] ?? 'sample-region-id';
            default:
                return 'sample-id';
        }
    }
}
