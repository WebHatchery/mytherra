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
                "'pantheon_intervention','civilization_agenda'" .
                ") COLLATE utf8mb4_unicode_ci NOT NULL"
            );
        }

        if ($schema->hasTable('bet_types')) {
            Capsule::table('bet_types')->updateOrInsert(
                ['code' => 'civilization_agenda'],
                [
                    'description' => 'A bet on a visible civilization agenda becoming a recorded civic decision',
                    'base_odds' => 3.1,
                    'min_timeframe' => 1,
                    'max_timeframe' => 8,
                    'resolve_conditions' => 'A civilization behavior event matching the region agenda occurs within timeframe',
                    'is_active' => true,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
        }
    },
    'down' => function (): void {
        if (Capsule::schema()->hasTable('bet_types')) {
            Capsule::table('bet_types')
                ->where('code', 'civilization_agenda')
                ->delete();
        }
    }
];
