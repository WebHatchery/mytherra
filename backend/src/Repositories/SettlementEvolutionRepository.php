<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\SettlementTypeConfig;
use App\Models\BuildingTypeConfig;
use App\Models\SettlementNamePool;
use App\Models\EvolutionParameter;

class SettlementEvolutionRepository
{
    public function getEvolutionParameters(): array
    {
        $params = EvolutionParameter::all()->keyBy('parameter');
        return [
            'BASE_GROWTH_RATE' => $params->get('base_growth_rate')->value,
            'MAX_GROWTH_RATE' => $params->get('max_growth_rate')->value,
            'PROSPERITY_GROWTH_MODIFIER' => $params->get('prosperity_growth_modifier')->value,
            'MIN_EVOLUTION_YEARS' => $params->get('min_evolution_years')->value,
            'PROSPERITY_THRESHOLD' => $params->get('prosperity_threshold')->value
        ];
    }

    public function getSettlementTypes(): array
    {
        $types = SettlementTypeConfig::all()->keyBy('code');
        return $types->map(function ($type) {
            return [
                'CODE' => $type->code,
                'DESCRIPTION' => $type->description,
                'MIN_POPULATION' => $type->min_population,
                'MAX_POPULATION' => $type->max_population,
                'MIN_BUILDINGS' => $type->min_buildings,
                'MAX_BUILDINGS' => $type->max_buildings,
                'BASE_DEFENSIBILITY' => $type->base_defensibility,
                'EVOLUTION_THRESHOLD' => $type->evolution_threshold
            ];
        })->toArray();
    }

    public function getBuildingTypes(): array
    {
        $buildings = BuildingTypeConfig::all();
        return $buildings->groupBy('category')->map(function ($categoryBuildings) {
            return $categoryBuildings->keyBy('code')->map(function ($building) {
                return [
                    'CODE' => $building->code,
                    'NAME' => $building->name,
                    'DESCRIPTION' => $building->description,
                    'BASE_COST' => $building->base_cost,
                    'MAINTENANCE' => $building->maintenance,
                    'PROSPERITY_BONUS' => $building->prosperity_bonus,
                    'DEFENSIBILITY_BONUS' => $building->defensibility_bonus
                ];
            })->toArray();
        })->toArray();
    }

    public function getNamePools(): array
    {
        $names = SettlementNamePool::all();
        return [
            'PREFIXES' => $names->where('type', 'prefix')->pluck('value')->toArray(),
            'SUFFIXES' => $names->where('type', 'suffix')->pluck('value')->toArray(),
            'SPECIAL_NAMES' => $names->where('type', 'special')->pluck('value')->toArray()
        ];
    }
}
