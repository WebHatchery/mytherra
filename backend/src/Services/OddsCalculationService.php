<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use App\Utils\Logger;
use App\Repositories\OddsRepository;
use App\Repositories\BettingConfigRepository;
use App\Models\BetConfig;

class OddsCalculationService
{
    private $oddsRepository;
    private $configRepository;
    private $betTypeConfigs;
    private $confidenceLevels;

    public function __construct(OddsRepository $oddsRepository, BettingConfigRepository $configRepository)
    {
        $this->oddsRepository = $oddsRepository;
        $this->configRepository = $configRepository;

    // Load configurations from database
        $betTypes = BetConfig::getBetTypes();
        $this->betTypeConfigs = [];
        foreach ($betTypes as $type) {
            $this->betTypeConfigs[$type['code']] = [
                'description' => $type['description'],
                'baseOdds' => $type['base_odds'],
                'resolveConditions' => $type['resolve_conditions']
            ];
        }

        $confidenceLevels = BetConfig::getConfidenceLevels();
        $this->confidenceLevels = [];
        foreach ($confidenceLevels as $level) {
            $this->confidenceLevels[$level['code']] = [
                'description' => $level['description'],
                'oddsModifier' => $level['odds_modifier'],
                'stakeMultiplier' => $level['stake_multiplier']
            ];
        }
    }

    /**
     * Calculate the odds for a new divine bet based on various factors
     */
    public function calculateBetOdds($betType, $targetId, $timeframe, $confidence)
    {
        try {
            // Base odds from constants
            $baseOdds = $this->betTypeConfigs[$betType]['baseOdds'] ?? 3.0;
            $confidenceModifier = $this->confidenceLevels[$confidence]['oddsModifier'] ?? 1.0;

    // Adjust based on timeframe - shorter timeframes increase odds
            $timeframeModifier = $this->calculateTimeframeModifier($timeframe);

    // Calculate target-specific modifiers
            $targetModifier = $this->calculateTargetSpecificModifier($betType, $targetId);

    // Calculate final odds
            $calculatedOdds = $baseOdds * $confidenceModifier * $timeframeModifier * $targetModifier;

    // Round to 2 decimal places and ensure minimum odds of 1.1
            $finalOdds = max(round($calculatedOdds * 100) / 100, 1.1);

    // Calculate potential payout based on confidence stake multiplier
            $stakeMultiplier = $this->confidenceLevels[$confidence]['stakeMultiplier'] ?? 1.0;

            return [
                'odds' => $finalOdds,
                'potentialPayout' => $finalOdds * $stakeMultiplier
            ];
        } catch (Exception $e) {
            Logger::error("Error calculating bet odds: " . $e->getMessage());

    // Return default values on error
            return [
                'odds' => 2.0,
                'potentialPayout' => 2.0
            ];
        }
    }

    /**
     * Calculate modifier based on timeframe
     * Shorter timeframes are harder to predict, so odds are higher
     */
    private function calculateTimeframeModifier($timeframe)
    {
        try {
            return BetConfig::getTimeframeModifier($timeframe);
        } catch (Exception $e) {
            Logger::error("Error getting timeframe modifier: " . $e->getMessage());

    // Return default values based on timeframe if database lookup fails
            if ($timeframe <= 1) {
                return 1.5;
            }
            if ($timeframe <= 3) {
                return 1.2;
            }
            if ($timeframe <= 5) {
                return 1.0;
            }
            if ($timeframe <= 10) {
                return 0.8;
            }
            return 0.6;
        }
    }

    /**
     * Calculate modifiers specific to the bet target and type
     */
    private function calculateTargetSpecificModifier($betType, $targetId)
    {
        try {
            switch ($betType) {
                case 'settlement_growth':
                case 'settlement_transformation':
                    return $this->calculateSettlementModifier($targetId, $betType);

                case 'landmark_discovery':
                    return $this->calculateLandmarkModifier($targetId, $betType);

                case 'cultural_shift':
                case 'corruption_spread':
                    return $this->calculateRegionModifier($targetId, $betType);

                case 'hero_settlement_bond':
                case 'hero_location_visit':
                    return $this->calculateHeroModifier($targetId, $betType);

                default:
                    return 1.0;
            }
        } catch (Exception $e) {
            Logger::error("Error calculating target-specific modifier: " . $e->getMessage());
            return 1.0;
        }
    }

    /**
     * Calculate settlement-specific odds modifiers
     */
    private function calculateSettlementModifier($settlementId, $betType)
    {
        try {
            $stats = $this->oddsRepository->getTargetStats($settlementId, 'settlement');

            if (!$stats) {
                return 1.0;
            }

            $modifiers = $this->configRepository->getTargetTypeModifiers('settlement', $betType);
            if (empty($modifiers)) {
                return 1.0;
            }

            $finalModifier = 1.0;
            foreach ($modifiers as $mod) {
                if (
                    $this->evaluateCondition(
                        $this->getStatValue($stats, $mod['condition_field']),
                        $mod['condition_value'],
                        $mod['comparison_operator']
                    )
                ) {
                    if ($mod['modifier_type'] === 'multiply') {
                        $finalModifier *= $mod['modifier_value'];
                    } else {
                        $finalModifier += $mod['modifier_value'];
                    }
                }
            }

    // Ensure modifier stays within reasonable bounds
            return max(min($finalModifier, 2.5), 0.5);
        } catch (Exception $e) {
            Logger::error("Error calculating settlement modifier: " . $e->getMessage());
            return 1.0;
        }
    }

    /**
     * Calculate hero-specific odds modifiers
     */
    private function calculateHeroModifier($heroId, $betType)
    {
        try {
            $stats = $this->oddsRepository->getTargetStats($heroId, 'hero');

            if (!$stats) {
                return 1.0;
            }

            $modifiers = $this->configRepository->getTargetTypeModifiers('hero', $betType);
            if (empty($modifiers)) {
                return 1.0;
            }

            $finalModifier = 1.0;
            foreach ($modifiers as $mod) {
                if (
                    $this->evaluateCondition(
                        $this->getStatValue($stats, $mod['condition_field']),
                        $mod['condition_value'],
                        $mod['comparison_operator']
                    )
                ) {
                    if ($mod['modifier_type'] === 'multiply') {
                        $finalModifier *= $mod['modifier_value'];
                    } else {
                        $finalModifier += $mod['modifier_value'];
                    }
                }
            }

    // Ensure modifier stays within reasonable bounds
            return max(min($finalModifier, 2.5), 0.5);
        } catch (Exception $e) {
            Logger::error("Error calculating hero modifier: " . $e->getMessage());
            return 1.0;
        }
    }

    /**
     * Calculate region-specific odds modifiers
     */
    private function calculateRegionModifier($regionId, $betType)
    {
        try {
            $stats = $this->oddsRepository->getTargetStats($regionId, 'region');

            if (!$stats) {
                return 1.0;
            }

            $modifiers = $this->configRepository->getTargetTypeModifiers('region', $betType);
            if (empty($modifiers)) {
                return 1.0;
            }

            $finalModifier = 1.0;
            foreach ($modifiers as $mod) {
                if (
                    $this->evaluateCondition(
                        $this->getStatValue($stats, $mod['condition_field']),
                        $mod['condition_value'],
                        $mod['comparison_operator']
                    )
                ) {
                    if ($mod['modifier_type'] === 'multiply') {
                        $finalModifier *= $mod['modifier_value'];
                    } else {
                        $finalModifier += $mod['modifier_value'];
                    }
                }
            }

    // Ensure modifier stays within reasonable bounds
            return max(min($finalModifier, 2.5), 0.5);
        } catch (Exception $e) {
            Logger::error("Error calculating region modifier: " . $e->getMessage());
            return 1.0;
        }
    }

    /**
     * Calculate landmark-specific odds modifiers
     */
    private function calculateLandmarkModifier($landmarkId, $betType)
    {
        try {
            $stats = $this->oddsRepository->getTargetStats($landmarkId, 'landmark');

            if (!$stats) {
                return 1.0;
            }

            $modifiers = $this->configRepository->getTargetTypeModifiers('landmark', $betType);
            if (empty($modifiers)) {
                return 1.0;
            }

            $finalModifier = 1.0;
            foreach ($modifiers as $mod) {
                if (
                    $this->evaluateCondition(
                        $stats[$mod['condition_field']],
                        $mod['condition_value'],
                        $mod['comparison_operator']
                    )
                ) {
                    if ($mod['modifier_type'] === 'multiply') {
                        $finalModifier *= $mod['modifier_value'];
                    } else {
                        $finalModifier += $mod['modifier_value'];
                    }
                }
            }

            return max(min($finalModifier, 2.5), 0.5);
        } catch (Exception $e) {
            Logger::error("Error calculating landmark modifier: " . $e->getMessage());
            return 1.0;
        }
    }

    /**
     * Update odds for all active bets based on changing world conditions
     */
    public function updateActiveBetOdds($activeBets)
    {
        $updatedBets = [];

        foreach ($activeBets as $bet) {
            try {
                // Recalculate odds based on current world state
                $oddsResult = $this->calculateBetOdds(
                    $bet['betType'],
                    $bet['targetId'],
                    $bet['timeframe'],
                    $bet['confidence']
                );

    // Apply a max change of 20% to avoid wild fluctuations
                $maxChange = $bet['currentOdds'] * 0.2;
                $newOdds = max(
                    min($oddsResult['odds'], $bet['currentOdds'] + $maxChange),
                    $bet['currentOdds'] - $maxChange
                );

    // Ensure minimum odds of 1.1
                $newOdds = max($newOdds, 1.1);

    // Update the bet object
                $bet['currentOdds'] = round($newOdds, 2);

    // Recalculate potential payout based on new odds
                $stakeMultiplier = $this->confidenceConfigs[$bet['confidence']]['stakeMultiplier'];
                $bet['potentialPayout'] = (int)round($bet['divineFavorStake'] * $bet['currentOdds'] * $stakeMultiplier);

                $updatedBets[] = $bet;
            } catch (Exception $e) {
                Logger::error("Error updating odds for bet {$bet['id']}: " . $e->getMessage());
                $updatedBets[] = $bet;

    // Keep original bet if update fails
            }
        }

        return $updatedBets;
    }

    /**
     * Calculate probability of a bet winning based on current odds
     * This can be used for simulation or AI decision making
     */
    public function calculateWinProbability($odds)
    {
        // Convert betting odds to probability percentage
        // Example: odds of 2.0 = 50% chance, 1.5 = 66.7% chance, 3.0 = 33.3% chance
        return 100 / $odds;
    }

    /**
     * Helper method to evaluate a conditional modifier
     */
    private function evaluateCondition($statValue, $conditionValue, $operator)
    {
        switch ($operator) {
            case '>':
                return $statValue > $conditionValue;
            case '<':
                return $statValue < $conditionValue;
            case '>=':
                return $statValue >= $conditionValue;
            case '<=':
                return $statValue <= $conditionValue;
            case '=':
            case '==':
                return $statValue == $conditionValue;
            default:
                return false;
        }
    }

    /**
     * Helper method to safely get a stat value with default
     */
    private function getStatValue($stats, $field)
    {
        if (isset($stats[$field])) {
            return is_numeric($stats[$field]) ? (float)$stats[$field] : $stats[$field];
        }
        return 50;

    // Default middle value for numerical stats
    }
}
