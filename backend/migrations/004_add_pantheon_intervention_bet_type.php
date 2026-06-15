<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function (): void {
        $schema = Capsule::schema();
        $connection = Capsule::connection();

        if ($schema->hasTable('divine_bets') && $connection->getDriverName() === 'mysql') {
            $connection->statement(
                "ALTER TABLE divine_bets MODIFY bet_type ENUM(" .
                "'settlement_growth','landmark_discovery','cultural_shift'," .
                "'hero_settlement_bond','hero_location_visit','settlement_transformation'," .
                "'corruption_spread','hero_level_milestone','hero_death'," .
                "'region_danger_change','war_outcome','prosperity_threshold','magic_discovery'," .
                "'pantheon_intervention'" .
                ") COLLATE utf8mb4_unicode_ci NOT NULL"
            );
        }

        if ($schema->hasTable('bet_types')) {
            Capsule::table('bet_types')->updateOrInsert(
                ['code' => 'pantheon_intervention'],
                [
                    'description' => 'A bet on an AI deity intervening in a pressured region',
                    'base_odds' => 3.2,
                    'min_timeframe' => 1,
                    'max_timeframe' => 10,
                    'resolve_conditions' => 'An AI pantheon intervention event targets the region within timeframe',
                    'is_active' => true,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        }
    },
    'down' => function (): void {
        if (Capsule::schema()->hasTable('bet_types')) {
            Capsule::table('bet_types')
                ->where('code', 'pantheon_intervention')
                ->delete();
        }
    }
];
