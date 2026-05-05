<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BettingRepository;
use App\Repositories\SettlementRepository;
use App\Repositories\LandmarkRepository;
use App\Repositories\HeroRepository;
use App\Repositories\RegionRepository;
use App\Utils\Logger;

class DivineBettingService
{
    private $bettingRepository;
    private $settlementRepository;
    private $landmarkRepository;
    private $heroRepository;
    private $regionRepository;
    private $oddsCalculator;

    public function __construct(
        BettingRepository $bettingRepository,
        SettlementRepository $settlementRepository,
        LandmarkRepository $landmarkRepository,
        HeroRepository $heroRepository,
        RegionRepository $regionRepository,
        OddsCalculationService $oddsCalculator
    ) {
        $this->bettingRepository = $bettingRepository;
        $this->settlementRepository = $settlementRepository;
        $this->landmarkRepository = $landmarkRepository;
        $this->heroRepository = $heroRepository;
        $this->regionRepository = $regionRepository;
        $this->oddsCalculator = $oddsCalculator;
    }

    public function generateBettingOpportunity($type)
    {
        try {
            $targets = [];
            switch ($type) {
                case 'settlement_growth':
                    $settlements = $this->settlementRepository->getAllSettlements(['status' => 'stable']);
                    $targets = array_map(function ($settlement) {
                        return [
                            'id' => $settlement['id'],
                            'baseOdds' => $this->calculateSettlementGrowthOdds($settlement)
                        ];
                    }, $settlements);
                    break;

                case 'landmark_discovery':
                    $landmarks = $this->landmarkRepository->getAllLandmarks(['status' => 'undiscovered']);
                    $targets = array_map(function ($landmark) {
                        return [
                            'id' => $landmark['id'],
                            'baseOdds' => $this->calculateDiscoveryOdds($landmark)
                        ];
                    }, $landmarks);
                    break;

                case 'hero_level_milestone':
                    $heroes = $this->heroRepository->getAllHeroes(['is_alive' => true]);
                    $targets = array_map(function ($hero) {
                        return [
                            'id' => $hero['id'],
                            'name' => $hero['name'],
                            'current_level' => $hero['level'],
                            'baseOdds' => $this->calculateHeroLevelOdds($hero)
                        ];
                    }, $heroes);
                    break;

                case 'hero_death':
                    $heroes = $this->heroRepository->getAllHeroes(['is_alive' => true]);
                    $targets = array_map(function ($hero) {
                        return [
                            'id' => $hero['id'],
                            'name' => $hero['name'],
                            'baseOdds' => $this->calculateHeroDeathOdds($hero)
                        ];
                    }, $heroes);
                    break;

                case 'region_danger_change':
                    $regions = $this->regionRepository->getAllRegions([]);
                    $targets = array_map(function ($region) {
                        return [
                            'id' => $region['id'],
                            'name' => $region['name'],
                            'current_danger' => $region['danger_level'] ?? 0,
                            'baseOdds' => $this->calculateDangerChangeOdds($region)
                        ];
                    }, $regions);
                    break;

                case 'prosperity_threshold':
                    $settlements = $this->settlementRepository->getAllSettlements([]);
                    $targets = array_map(function ($settlement) {
                        return [
                            'id' => $settlement['id'],
                            'name' => $settlement['name'],
                            'current_prosperity' => $settlement['prosperity'] ?? 0,
                            'baseOdds' => $this->calculateProsperityOdds($settlement)
                        ];
                    }, $settlements);
                    break;
            }

            return $this->selectBestOpportunities($targets);
        } catch (\Exception $e) {
            Logger::error("Error generating betting opportunity: " . $e->getMessage());
            throw $e;
        }
    }

    public function resolveBet($bet)
    {
        try {
            $outcome = $this->determineOutcome($bet);
            return $this->processOutcome($bet, $outcome);
        } catch (\Exception $e) {
            Logger::error("Error resolving bet: " . $e->getMessage());
            throw $e;
        }
    }

    private function calculateSettlementGrowthOdds($settlement)
    {
        $baseOdds = 0.5;

    // 50% base chance

        // Modify based on prosperity
        $prosperityModifier = ($settlement['prosperity'] - 50) / 100;
        $baseOdds += $prosperityModifier;

    // Modify based on population trend
        if ($settlement['population'] > 1000) {
            $baseOdds += 0.1;
        }
        if ($settlement['defensibility'] > 70) {
            $baseOdds += 0.1;
        }

        return max(0.1, min(0.9, $baseOdds));
    }

    private function calculateDiscoveryOdds($landmark)
    {
        $baseOdds = 0.3;

    // 30% base chance for discoveries

        // Modify based on magic level
        $magicModifier = $landmark['magicLevel'] / 200;

    // Higher magic level = easier to find
        $baseOdds += $magicModifier;

    // Modify based on danger level
        $dangerModifier = $landmark['dangerLevel'] / 150;
        $baseOdds -= $dangerModifier;

        return max(0.1, min(0.9, $baseOdds));
    }

    /**
     * Calculate odds for hero reaching a level milestone
     */
    private function calculateHeroLevelOdds($hero): float
    {
        $baseOdds = 0.4;

    // Lower level heroes have better chances to level up
        $levelModifier = max(0, 0.3 - ($hero['level'] / 100));
        $baseOdds += $levelModifier;

    // Role affects level speed
        if (in_array($hero['role'] ?? '', ['warrior', 'adventurer'])) {
            $baseOdds += 0.1;
        }

        return max(0.1, min(0.9, $baseOdds));
    }

    /**
     * Calculate odds for hero death
     */
    private function calculateHeroDeathOdds($hero): float
    {
        $baseOdds = 0.15;

    // Low base chance

        // Age increases death chance
        $ageModifier = ($hero['age'] ?? 25) / 200;
        $baseOdds += $ageModifier;

    // Low level heroes are more vulnerable
        if (($hero['level'] ?? 1) < 10) {
            $baseOdds += 0.1;
        }

        return max(0.05, min(0.6, $baseOdds));
    }

    /**
     * Calculate odds for region danger level change
     */
    private function calculateDangerChangeOdds($region): float
    {
        $baseOdds = 0.35;

    // High chaos regions are more volatile
        $chaosModifier = ($region['chaos'] ?? 0) / 200;
        $baseOdds += $chaosModifier;

    // Regions at extreme danger levels have less room to change
        $currentDanger = $region['danger_level'] ?? 5;
        if ($currentDanger <= 2 || $currentDanger >= 9) {
            $baseOdds *= 0.7;
        }

        return max(0.1, min(0.7, $baseOdds));
    }

    /**
     * Calculate odds for settlement reaching prosperity threshold
     */
    private function calculateProsperityOdds($settlement): float
    {
        $baseOdds = 0.45;

    // Current prosperity affects growth potential
        $currentProsperity = $settlement['prosperity'] ?? 50;
        if ($currentProsperity < 30) {
            $baseOdds += 0.2;

    // Room to grow
        } elseif ($currentProsperity > 80) {
            $baseOdds -= 0.2;

    // Near cap
        }

    // Population size affects growth
        if (($settlement['population'] ?? 0) > 500) {
            $baseOdds += 0.1;
        }

        return max(0.15, min(0.85, $baseOdds));
    }

    private function selectBestOpportunities($targets)
    {
        // Sort by odds and take top 5
        usort($targets, function ($a, $b) {
            return $b['baseOdds'] <=> $a['baseOdds'];
        });

        return array_slice($targets, 0, 5);
    }

    private function determineOutcome($bet)
    {
        $baseChance = $bet['current_odds'];

    // Adjust for timeframe
        if ($bet['timeframe'] > 5) {
            $baseChance *= 0.8;
        }
        if ($bet['timeframe'] < 3) {
            $baseChance *= 1.2;
        }

    // Adjust for confidence level
        switch ($bet['confidence']) {
            case 'certain':
                $baseChance *= 0.7;
                break;
            case 'likely':
                $baseChance *= 0.9;
                break;
            case 'unlikely':
                $baseChance *= 1.3;
                break;
        }

        $roll = mt_rand(1, 100) / 100;
        return $roll <= $baseChance;
    }

    private function processOutcome($bet, $success)
    {
        $payout = 0;
        if ($success) {
            $payout = round($bet['divine_favor_stake'] * $bet['potential_payout']);
        }

        return [
            'success' => $success,
            'payout' => $payout,
            'resolution_notes' => $this->generateResolutionNotes($bet, $success)
        ];
    }

    private function generateResolutionNotes($bet, $success)
    {
        $target = $this->getTargetDetails($bet['target_id']);
        $notes = [];

        if ($success) {
            $notes[] = "The divine vision proved true!";
            $notes[] = "Your insight into the fate of " . ($target['name'] ?? 'the target') . " was correct.";
        } else {
            $notes[] = "The threads of fate twisted in an unexpected direction.";
            $notes[] = "Your vision concerning " . ($target['name'] ?? 'the target') . " did not come to pass.";
        }

        return implode(" ", $notes);
    }

    private function getTargetDetails($targetId)
    {
        // Try each repository until we find the target
        return $this->settlementRepository->getSettlementById($targetId) ??
               $this->landmarkRepository->getLandmarkById($targetId) ??
               $this->heroRepository->getHeroById($targetId) ??
               $this->regionRepository->getRegionById($targetId) ??
               ['name' => 'Unknown Target'];
    }
}
