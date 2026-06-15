<?php

declare(strict_types=1);

namespace App\InitData;

class ResourceNodeData
{
    public static function getData(): array
    {
        return [
            [
                'id' => 'resource-001',
                'region_id' => 'region-001',
                'settlement_id' => 'settlement-001',
                'type' => 'magical_spring',
                'name' => 'Crystal Wellspring',
                'output' => 82,
                'status' => 'blessed',
            ],
            [
                'id' => 'resource-002',
                'region_id' => 'region-001',
                'settlement_id' => 'settlement-002',
                'type' => 'forest',
                'name' => 'Fellwood Timberline',
                'output' => 56,
                'status' => 'active',
            ],
            [
                'id' => 'resource-003',
                'region_id' => 'region-001',
                'settlement_id' => 'settlement-003',
                'type' => 'herb_garden',
                'name' => 'Observatory Herbarium',
                'output' => 44,
                'status' => 'active',
            ],
            [
                'id' => 'resource-004',
                'region_id' => 'region-002',
                'settlement_id' => null,
                'type' => 'fishing',
                'name' => 'Gold Coast Fisheries',
                'output' => 68,
                'status' => 'active',
            ],
            [
                'id' => 'resource-005',
                'region_id' => 'region-002',
                'settlement_id' => null,
                'type' => 'quarry',
                'name' => 'Sunmarket Quarry',
                'output' => 53,
                'status' => 'contested',
            ],
            [
                'id' => 'resource-006',
                'region_id' => 'region-003',
                'settlement_id' => null,
                'type' => 'magical_spring',
                'name' => 'Moonlit Leyspring',
                'output' => 78,
                'status' => 'unstable',
            ],
            [
                'id' => 'resource-007',
                'region_id' => 'region-003',
                'settlement_id' => null,
                'type' => 'herb_garden',
                'name' => 'Silverleaf Gardens',
                'output' => 48,
                'status' => 'active',
            ],
        ];
    }
}
