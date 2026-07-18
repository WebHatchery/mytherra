<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use App\Utils\Logger;
use App\Repositories\OddsRepository;
use App\Repositories\BettingConfigRepository;
use App\Models\BetConfig;
use App\Models\DivineBet;
use App\Models\Landmark;
use App\Models\Region;

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

            $payoutProfile = $this->calculatePayoutProfile(1, $finalOdds, $confidence);

            return [
                'odds' => $finalOdds,
                'potentialPayout' => $payoutProfile['grossMultiplier'],
                'payoutProfile' => $payoutProfile
            ];
        } catch (Exception $e) {
            Logger::error("Error calculating bet odds: " . $e->getMessage());

    // Return default values on error
            return [
                'odds' => 2.0,
                'potentialPayout' => 2.0,
                'payoutProfile' => $this->calculatePayoutProfile(1, 2.0, $confidence)
            ];
        }
    }

    public function calculatePayoutProfile(int $stake, float $odds, string $confidence): array
    {
        $stakeMultiplier = $this->confidenceLevels[$confidence]['stakeMultiplier']
            ?? DivineBet::getStakeMultiplier($confidence);

        return DivineBet::calculatePayoutProfile($stake, $odds, $confidence, (float)$stakeMultiplier);
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
                case 'prosperity_threshold':
                    return $this->calculateSettlementModifier($targetId, $betType);

                case 'landmark_discovery':
                    return $this->calculateLandmarkModifier($targetId, $betType);

                case 'corruption_spread':
                    return $this->calculateCorruptionSpreadModifier($targetId);

                case 'cultural_shift':
                    return $this->calculateCultureShiftModifier($targetId);

                case 'hero_settlement_bond':
                case 'hero_location_visit':
                case 'hero_level_milestone':
                case 'hero_death':
                    return $this->calculateHeroModifier($targetId, $betType);

                case 'region_danger_change':
                    return $this->calculateRegionModifier($targetId, $betType);

                case 'magic_discovery':
                    return $this->calculateMagicDiscoveryModifier($targetId);

                case 'pantheon_intervention':
                    return $this->calculatePantheonInterventionModifier($targetId);

                case 'civilization_agenda':
                    return $this->calculateCivilizationAgendaModifier($targetId);

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

    private function calculateCultureShiftModifier(string $regionId): float
    {
        try {
            $region = Region::find($regionId);
            if (!$region instanceof Region) {
                return 1.2;
            }

            $currentCulture = (string)($region->cultural_influence ?? 'pastoral');
            $distinctCultures = ['scholarly', 'martial', 'mystical', 'mercantile'];
            $pressure = 0;
            $tradeRoutes = is_array($region->trade_routes ?? null) ? count($region->trade_routes) : 0;
            $pressure += min(4, $tradeRoutes);
            $pressure += (int)$region->magic_affinity >= 65 ? 2 : 0;
            $pressure += (int)$region->prosperity >= 70 ? 1 : 0;
            $pressure += (int)$region->danger_level >= 55 ? 1 : 0;
            $pressure += (int)$region->chaos >= 60 ? 1 : 0;

            foreach ($region->regional_traits ?? [] as $trait) {
                if (in_array((string)$trait, ['trade_culture_exchange', 'cross_region_lore', 'border_tension'], true)) {
                    $pressure++;
                }
                if (str_starts_with((string)$trait, 'myth_')) {
                    $pressure++;
                }
            }

            if (in_array($currentCulture, $distinctCultures, true)) {
                return 0.7;
            }

            return max(0.55, min(1.45, 1.35 - ($pressure / 12)));
        } catch (Exception $e) {
            Logger::error("Error calculating culture shift modifier: " . $e->getMessage());
            return 1.0;
        }
    }

    private function calculateCorruptionSpreadModifier(string $targetId): float
    {
        $landmark = Landmark::find($targetId);
        if ($landmark instanceof Landmark) {
            return $this->calculateLandmarkCorruptionModifier($landmark);
        }

        return $this->calculateRegionModifier($targetId, 'corruption_spread');
    }

    private function calculateLandmarkCorruptionModifier(Landmark $landmark): float
    {
        try {
            $risk = ((int)$landmark->danger_level * 0.55) + ((int)$landmark->magic_level * 0.30);
            if ((string)$landmark->status === Landmark::STATUS_HAUNTED) {
                $risk += 12;
            }
            if ((string)$landmark->status === Landmark::STATUS_ACTIVE) {
                $risk += 5;
            }
            foreach ($landmark->traits ?? [] as $trait) {
                if (in_array((string)$trait, [Landmark::TRAIT_PORTAL, Landmark::TRAIT_MAGICAL, Landmark::TRAIT_ANCIENT], true)) {
                    $risk += 5;
                }
            }

            if ((string)$landmark->status === Landmark::STATUS_CORRUPTED || $landmark->isCorrupted()) {
                return 0.55;
            }

            return max(0.55, min(1.55, 1.45 - ($risk / 110)));
        } catch (Exception $e) {
            Logger::error("Error calculating landmark corruption modifier: " . $e->getMessage());
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

    private function calculateMagicDiscoveryModifier(string $targetId): float
    {
        try {
            $path = (new MagicDiscoveryService())->pathsForTarget($targetId)[0] ?? null;
            if (!is_array($path)) {
                return 1.35;
            }

            $progress = max(0, min(100, (int)($path['progress'] ?? 0)));
            $evidence = max(0, min(100, (int)($path['evidenceScore'] ?? 0)));
            $status = (string)($path['status'] ?? 'hidden');

            if ($status === 'known') {
                return 0.55;
            }

            $readiness = ($progress * 0.7) + ($evidence * 0.3);
            $modifier = 1.45 - ($readiness / 120);

            return max(0.55, min(1.45, $modifier));
        } catch (Exception $e) {
            Logger::error("Error calculating magic discovery modifier: " . $e->getMessage());
            return 1.0;
        }
    }

    private function calculatePantheonInterventionModifier(string $regionId): float
    {
        try {
            $pressureScore = 0;
            foreach ((new PantheonService())->status()['pressure'] ?? [] as $entry) {
                if (!is_array($entry) || (string)($entry['targetRegionId'] ?? '') !== $regionId) {
                    continue;
                }

                $pressureScore = max($pressureScore, (int)($entry['pressureScore'] ?? 0));
            }

            if ($pressureScore <= 0) {
                return 1.35;
            }

            return max(0.55, min(1.45, 1.35 - ($pressureScore / 140)));
        } catch (Exception $e) {
            Logger::error("Error calculating pantheon intervention modifier: " . $e->getMessage());
            return 1.0;
        }
    }

    private function calculateCivilizationAgendaModifier(string $regionId): float
    {
        try {
            $agendaScore = 0;
            foreach ((new CivilizationBehaviorService())->status()['regionAgendas'] ?? [] as $agenda) {
                if (!is_array($agenda) || (string)($agenda['regionId'] ?? '') !== $regionId) {
                    continue;
                }

                $agendaScore = max($agendaScore, (int)($agenda['score'] ?? 0));
            }

            if ($agendaScore <= 0) {
                return 1.35;
            }

            return max(0.55, min(1.45, 1.42 - ($agendaScore / 130)));
        } catch (Exception $e) {
            Logger::error("Error calculating civilization agenda modifier: " . $e->getMessage());
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
                $payoutProfile = $this->calculatePayoutProfile(
                    (int)$bet['divineFavorStake'],
                    (float)$bet['currentOdds'],
                    (string)$bet['confidence']
                );
                $bet['potentialPayout'] = $payoutProfile['grossPayout'];
                $bet['payoutProfile'] = $payoutProfile;

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
