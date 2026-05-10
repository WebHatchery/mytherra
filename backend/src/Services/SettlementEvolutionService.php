<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettlementRepository;
use App\Repositories\RegionRepository;
use App\Repositories\LandmarkRepository;
use App\Repositories\BuildingRepository;
use App\Repositories\SettlementEvolutionRepository;
use App\Models\SettlementEvolutionConfig;
use App\Utils\Logger;
use Exception;

class SettlementEvolutionService
{
    private $settlementRepository;
    private $regionRepository;
    private $landmarkRepository;
    private $buildingRepository;
    private $evolutionRepository;
    private $evolutionConfig;
    private $specializations;
    private $traits;
    private $evolutionTypes;
    private $evolutionParams;
    private $settlementTypes;

    public function __construct(
        SettlementRepository $settlementRepository,
        RegionRepository $regionRepository,
        LandmarkRepository $landmarkRepository,
        BuildingRepository $buildingRepository,
        SettlementEvolutionRepository $evolutionRepository
    ) {
        $this->settlementRepository = $settlementRepository;
        $this->regionRepository = $regionRepository;
        $this->landmarkRepository = $landmarkRepository;
        $this->buildingRepository = $buildingRepository;
        $this->evolutionRepository = $evolutionRepository;

    // Load configuration data from database
        $this->evolutionConfig = SettlementEvolutionConfig::getEvolutionThresholds();
        $this->specializations = SettlementEvolutionConfig::getSpecializations();
        $this->traits = SettlementEvolutionConfig::getTraits();
        $this->evolutionTypes = SettlementEvolutionConfig::getEvolutionTypes();

    // Load evolution parameters from repository
        $this->evolutionParams = $this->evolutionRepository->getEvolutionParameters();
        $this->settlementTypes = $this->evolutionRepository->getSettlementTypes();
    }

    /**
     * Process settlement evolution for all settlements in a region
     */
    public function processRegionEvolution(string $regionId, int $currentYear): array
    {
        try {
            $settlements = $this->settlementRepository->getSettlementsByRegion($regionId);
            $evolutionResults = [];

            foreach ($settlements as $settlement) {
                $evolutionResults[$settlement['id']] = $this->processSettlementEvolution($settlement, $currentYear);
            }

    // Update region total population
            $this->updateRegionPopulation($regionId);

            return $evolutionResults;
        } catch (Exception $e) {
            Logger::error("Error processing region evolution: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process evolution for a single settlement
     */
    private function processSettlementEvolution(array $settlement, int $currentYear): array
    {
        try {
            $changes = [];

    // Calculate prosperity changes
            $prosperityModifier = 1.0;
            if ($settlement['prosperity'] > 0) {
                $prosperityChange = rand(-5, 10);

    // Base change
                $prosperityModifier = $this->calculateProsperityModifier($settlement);
                $prosperityChange *= $prosperityModifier;

                $newProsperity = min(100, max(0, $settlement['prosperity'] + $prosperityChange));
                if ($newProsperity != $settlement['prosperity']) {
                    $changes['prosperity'] = $newProsperity;
                }
            }

    // Calculate population changes using stored evolution parameters
            $baseGrowthRate = $this->evolutionParams['BASE_GROWTH_RATE'];
            $maxGrowthRate = $this->evolutionParams['MAX_GROWTH_RATE'];
            $prosperityGrowthMod = $this->evolutionParams['PROSPERITY_GROWTH_MODIFIER'];

            $growthRate = $baseGrowthRate * (1 + ($settlement['prosperity'] / 100 * $prosperityGrowthMod));
            $growthRate = min($growthRate, $maxGrowthRate);

            $populationChange = ceil($settlement['population'] * $growthRate);
            $maxPopulation = $this->getMaxPopulation($settlement['type']);

            $newPopulation = min($maxPopulation, $settlement['population'] + $populationChange);
            if ($newPopulation != $settlement['population']) {
                $changes['population'] = $newPopulation;
            }

    // Check for evolution type changes
            if ($typeEvolution = $this->checkSettlementTypeEvolution($settlement, $changes)) {
                $changes = array_merge($changes, $typeEvolution);
            }

    // Update status based on prosperity
            if (isset($changes['prosperity'])) {
                if ($changes['prosperity'] < 25) {
                    $changes['status'] = 'declining';
                } elseif ($changes['prosperity'] > 75) {
                    $changes['status'] = 'thriving';
                }
            }

            return $changes;
        } catch (Exception $e) {
            Logger::error("Error processing settlement evolution: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if settlement can evolve to next type
     */
    private function checkSettlementTypeEvolution(array $settlement, array $changes): ?array
    {
        $currentType = $settlement['type'];
        $population = $changes['population'] ?? $settlement['population'];
        $prosperity = $changes['prosperity'] ?? $settlement['prosperity'];

        foreach ($this->evolutionConfig as $threshold) {
            if (
                $threshold['settlement_type'] === $currentType &&
                $population >= $threshold['min_population'] &&
                $prosperity >= $threshold['min_prosperity']
            ) {
                $requiredBuildings = json_decode($threshold['required_buildings'], true);
                $hasRequiredBuildings = $this->checkRequiredBuildings($settlement['id'], $requiredBuildings);

                if ($hasRequiredBuildings) {
                    return [
                        'type' => $threshold['next_type'],
                        'status' => 'growing'
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Check if settlement has required buildings for evolution
     */
    private function checkRequiredBuildings(string $settlementId, array $requiredTypes): bool
    {
        $buildings = $this->buildingRepository->getBuildingsBySettlement($settlementId);
        $buildingTypes = array_column($buildings, 'type');

        foreach ($requiredTypes as $type) {
            if (!in_array($type, $buildingTypes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get minimum population for settlement type
     */
    private function getMinPopulation(string $type): int
    {
        $types = SettlementConstants::getTypes();
        return $types[strtoupper($type)]['MIN_POPULATION'] ?? 50;
    }

    /**
     * Get maximum population for settlement type
     */
    private function getMaxPopulation(string $type): int
    {
        return $this->settlementTypes[$type]['MAX_POPULATION'] ?? 0;
    }

    /**
     * Calculate prosperity modifier based on settlement specializations and traits
     */
    private function calculateProsperityModifier(array $settlement): float
    {
        $modifier = 1.0;

    // Consider specializations
        $specializations = json_decode($settlement['specializations'] ?? '[]', true);
        foreach ($specializations as $spec) {
            if (isset($this->specializations[$spec]['prosperity_modifier'])) {
                $modifier *= $this->specializations[$spec]['prosperity_modifier'];
            }
        }

    // Consider traits
        $traits = json_decode($settlement['traits'] ?? '[]', true);
        foreach ($traits as $trait) {
            if (isset($this->traits[$trait]['prosperity_modifier'])) {
                $modifier *= $this->traits[$trait]['prosperity_modifier'];
            }
        }

        return $modifier;
    }

    /**
     * Update region's total population
     */
    private function updateRegionPopulation(string $regionId): void
    {
        $settlements = $this->settlementRepository->getSettlementsByRegion($regionId);
        $totalPopulation = array_reduce($settlements, function ($sum, $settlement) {
            return $sum + $settlement['population'];
        }, 0);

        $this->regionRepository->updatePopulation($regionId, $totalPopulation);
    }
}
