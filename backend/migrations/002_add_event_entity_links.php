<?php

use Illuminate\Database\Capsule\Manager as Capsule;

return [
    'up' => function () {
        $schema = Capsule::schema();

        foreach ([
            'related_settlement_ids',
            'related_landmark_ids',
            'related_resource_ids',
        ] as $column) {
            if (!$schema->hasColumn('game_events', $column)) {
                $schema->table('game_events', function ($table) use ($column) {
                    $table->json($column)->nullable()->after('related_hero_ids');
                });
            }
        }

        $appendLink = function (object $event, string $column, string $id): void {
            $current = [];
            if (isset($event->{$column}) && is_string($event->{$column}) && $event->{$column} !== '') {
                $decoded = json_decode($event->{$column}, true);
                if (is_array($decoded)) {
                    $current = array_values(array_filter($decoded, 'is_string'));
                }
            }

            if (!in_array($id, $current, true)) {
                $current[] = $id;
                Capsule::table('game_events')
                    ->where('id', $event->id)
                    ->update([$column => json_encode($current)]);
            }
        };

        $backfillByName = function (string $sourceTable, string $column) use ($appendLink): void {
            $entities = Capsule::table($sourceTable)->select('id', 'name')->get();
            foreach ($entities as $entity) {
                $events = Capsule::table('game_events')
                    ->select('id', 'title', 'description', $column)
                    ->where(function ($query) use ($entity) {
                        $query->where('title', 'like', '%' . $entity->name . '%')
                            ->orWhere('description', 'like', '%' . $entity->name . '%');
                    })
                    ->get();

                foreach ($events as $event) {
                    $appendLink($event, $column, $entity->id);
                }
            }
        };

        $backfillByName('settlements', 'related_settlement_ids');
        $backfillByName('landmarks', 'related_landmark_ids');
        $backfillByName('resource_nodes', 'related_resource_ids');
    },
    'down' => function () {
        $schema = Capsule::schema();
        foreach ([
            'related_resource_ids',
            'related_landmark_ids',
            'related_settlement_ids',
        ] as $column) {
            if ($schema->hasColumn('game_events', $column)) {
                $schema->table('game_events', function ($table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
];
