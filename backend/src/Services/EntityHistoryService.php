<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DivineBet;
use App\Models\GameEvent;
use App\Models\Hero;
use App\Models\Landmark;
use App\Models\Region;
use App\Models\ResourceNode;
use App\Models\Settlement;
use Illuminate\Database\Eloquent\Collection;

class EntityHistoryService
{
    public function getSummary(int $limitPerType = 6, int $eventsPerEntity = 3, ?string $regionId = null): array
    {
        $limitPerType = max(1, min(12, $limitPerType));
        $eventsPerEntity = max(1, min(6, $eventsPerEntity));

        $regions = Region::orderBy('name');
        $settlements = Settlement::orderBy('name');
        $landmarks = Landmark::orderBy('name');
        $resources = ResourceNode::orderBy('name');
        $heroes = Hero::orderBy('name');

        if ($regionId) {
            $regions->where('id', $regionId);
            $settlements->where('region_id', $regionId);
            $landmarks->where('region_id', $regionId);
            $resources->where('region_id', $regionId);
            $heroes->where('region_id', $regionId);
        }

        return [
            'generatedAt' => date('c'),
            'limits' => [
                'entitiesPerType' => $limitPerType,
                'eventsPerEntity' => $eventsPerEntity,
                'regionId' => $regionId,
            ],
            'coverageNotes' => [
                'regions' => 'Region events use direct region IDs and related region IDs.',
                'heroes' => 'Hero events use related hero IDs.',
                'settlements' => 'Settlement events use direct related settlement IDs, with regional context as a fallback.',
                'landmarks' => 'Landmark events use direct related landmark IDs, with regional context as a fallback.',
                'resources' => 'Resource events use direct related resource IDs, with regional context as a fallback.',
                'bets' => 'Bet history is summarized from divine bet rows and target lookups.',
            ],
            'entities' => [
                'regions' => $this->summarizeCollection('region', $regions->take($limitPerType)->get(), $eventsPerEntity),
                'settlements' => $this->summarizeCollection('settlement', $settlements->take($limitPerType)->get(), $eventsPerEntity),
                'landmarks' => $this->summarizeCollection('landmark', $landmarks->take($limitPerType)->get(), $eventsPerEntity),
                'resources' => $this->summarizeCollection('resource', $resources->take($limitPerType)->get(), $eventsPerEntity),
                'heroes' => $this->summarizeCollection('hero', $heroes->take($limitPerType)->get(), $eventsPerEntity),
            ],
            'bets' => $this->summarizeBets($limitPerType),
        ];
    }

    private function summarizeCollection(string $entityType, Collection $entities, int $eventLimit): array
    {
        return $entities
            ->map(fn($entity) => $this->summarizeEntity($entityType, $entity, $eventLimit))
            ->values()
            ->all();
    }

    private function summarizeEntity(string $entityType, mixed $entity, int $eventLimit): array
    {
        $regionId = $entityType === 'region' ? $entity->id : ($entity->region_id ?? null);
        $directEvents = $this->directEventsForEntity($entityType, $entity, $eventLimit);
        $events = $directEvents;
        $historyStatus = $directEvents->isEmpty() ? 'none' : 'direct';

        if ($directEvents->count() < $eventLimit && $regionId) {
            $contextEvents = $this->regionContextEvents(
                $regionId,
                $eventLimit - $directEvents->count(),
                $directEvents->pluck('id')->all()
            );

            if (!$contextEvents->isEmpty()) {
                $events = $directEvents->concat($contextEvents);
                $historyStatus = $directEvents->isEmpty() ? 'region_context' : 'direct_with_region_context';
            }
        }

        $lastEvent = $events->first();

        return [
            'id' => $entity->id,
            'name' => $entity->name,
            'entityType' => $entityType,
            'regionId' => $regionId,
            'currentState' => $this->currentStateFor($entityType, $entity),
            'historyStatus' => $historyStatus,
            'historyNote' => $this->historyNoteFor($historyStatus, $entityType),
            'directEventCount' => $directEvents->count(),
            'shownEventCount' => $events->count(),
            'lastEventYear' => $lastEvent?->year,
            'lastEventTitle' => $lastEvent?->title,
            'lastEventDescription' => $lastEvent?->description,
            'recentEvents' => $events
                ->take($eventLimit)
                ->map(fn(GameEvent $event) => $this->eventSummary($event, $directEvents->contains('id', $event->id) ? 'direct' : 'region_context'))
                ->values()
                ->all(),
        ];
    }

    private function directEventsForEntity(string $entityType, mixed $entity, int $limit): Collection
    {
        $query = GameEvent::query();

        $query->where(function ($inner) use ($entityType, $entity) {
            if ($entityType === 'region') {
                $inner->where('region_id', $entity->id)
                    ->orWhereJsonContains('related_region_ids', $entity->id);
            } elseif ($entityType === 'hero') {
                $inner->whereJsonContains('related_hero_ids', $entity->id);
            } elseif ($entityType === 'settlement') {
                $inner->whereJsonContains('related_settlement_ids', $entity->id);
            } elseif ($entityType === 'landmark') {
                $inner->whereJsonContains('related_landmark_ids', $entity->id);
            } elseif ($entityType === 'resource') {
                $inner->whereJsonContains('related_resource_ids', $entity->id);
            }
        });

        return $this->latestEvents($query, $limit);
    }

    private function regionContextEvents(string $regionId, int $limit, array $excludeIds): Collection
    {
        if ($limit <= 0) {
            return new Collection();
        }

        $query = GameEvent::query()
            ->where(function ($inner) use ($regionId) {
                $inner->where('region_id', $regionId)
                    ->orWhereJsonContains('related_region_ids', $regionId);
            });

        if ($excludeIds !== []) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $this->latestEvents($query, $limit);
    }

    private function latestEvents($query, int $limit): Collection
    {
        return $query
            ->orderByRaw('COALESCE(year, 0) DESC')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    private function eventSummary(GameEvent $event, string $matchType): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'type' => $event->type,
            'status' => $event->status,
            'regionId' => $event->region_id,
            'relatedRegionIds' => $event->related_region_ids ?? [],
            'relatedHeroIds' => $event->related_hero_ids ?? [],
            'relatedSettlementIds' => $event->related_settlement_ids ?? [],
            'relatedLandmarkIds' => $event->related_landmark_ids ?? [],
            'relatedResourceIds' => $event->related_resource_ids ?? [],
            'year' => $event->year,
            'timestamp' => $event->timestamp,
            'matchType' => $matchType,
        ];
    }

    private function currentStateFor(string $entityType, mixed $entity): array
    {
        return match ($entityType) {
            'region' => [
                'summary' => "Prosperity {$entity->prosperity}, chaos {$entity->chaos}, danger {$entity->danger_level}, {$entity->status}.",
                'signals' => [
                    ['label' => 'Prosperity', 'value' => (string)$entity->prosperity],
                    ['label' => 'Chaos', 'value' => (string)$entity->chaos],
                    ['label' => 'Danger', 'value' => (string)$entity->danger_level],
                    ['label' => 'Status', 'value' => $this->formatLabel((string)$entity->status)],
                ],
            ],
            'settlement' => [
                'summary' => "{$entity->population} people, prosperity {$entity->prosperity}, {$entity->status} {$entity->type}.",
                'signals' => [
                    ['label' => 'Population', 'value' => (string)$entity->population],
                    ['label' => 'Prosperity', 'value' => (string)$entity->prosperity],
                    ['label' => 'Status', 'value' => $this->formatLabel((string)$entity->status)],
                    ['label' => 'Type', 'value' => $this->formatLabel((string)$entity->type)],
                ],
            ],
            'landmark' => [
                'summary' => "{$entity->status} {$entity->type}, magic {$entity->magic_level}, danger {$entity->danger_level}.",
                'signals' => [
                    ['label' => 'Magic', 'value' => (string)$entity->magic_level],
                    ['label' => 'Danger', 'value' => (string)$entity->danger_level],
                    ['label' => 'Status', 'value' => $this->formatLabel((string)$entity->status)],
                    ['label' => 'Type', 'value' => $this->formatLabel((string)$entity->type)],
                ],
            ],
            'resource' => [
                'summary' => "{$entity->type} output {$entity->output}, {$entity->status}.",
                'signals' => [
                    ['label' => 'Output', 'value' => (string)$entity->output],
                    ['label' => 'Status', 'value' => $this->formatLabel((string)$entity->status)],
                    ['label' => 'Type', 'value' => $this->formatLabel((string)$entity->type)],
                ],
            ],
            'hero' => [
                'summary' => "Level {$entity->level}, age {$entity->age}, {$entity->status} {$entity->role}.",
                'signals' => [
                    ['label' => 'Level', 'value' => (string)$entity->level],
                    ['label' => 'Age', 'value' => (string)$entity->age],
                    ['label' => 'Status', 'value' => $this->formatLabel((string)$entity->status)],
                    ['label' => 'Role', 'value' => $this->formatLabel((string)$entity->role)],
                ],
            ],
            default => ['summary' => 'Unknown state.', 'signals' => []],
        };
    }

    private function summarizeBets(int $limit): array
    {
        $statusCounts = DivineBet::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return [
            'total' => (int)DivineBet::count(),
            'active' => (int)($statusCounts['active'] ?? 0),
            'won' => (int)($statusCounts['won'] ?? 0),
            'lost' => (int)($statusCounts['lost'] ?? 0),
            'expired' => (int)($statusCounts['expired'] ?? 0),
            'recentActive' => DivineBet::where('status', 'active')
                ->orderByDesc('placed_year')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(fn(DivineBet $bet) => $this->betSummary($bet))
                ->values()
                ->all(),
            'recentResolved' => DivineBet::whereIn('status', ['won', 'lost', 'expired'])
                ->orderByDesc('resolved_year')
                ->orderByDesc('updated_at')
                ->limit($limit)
                ->get()
                ->map(fn(DivineBet $bet) => $this->betSummary($bet))
                ->values()
                ->all(),
        ];
    }

    private function betSummary(DivineBet $bet): array
    {
        $target = $this->findBetTarget($bet->target_id);

        return [
            'id' => $bet->id,
            'description' => $bet->description,
            'betType' => $bet->bet_type,
            'targetId' => $bet->target_id,
            'targetName' => $target['name'] ?? null,
            'targetType' => $target['type'] ?? null,
            'status' => $bet->status,
            'placedYear' => (int)$bet->placed_year,
            'resolvedYear' => $bet->resolved_year ? (int)$bet->resolved_year : null,
            'currentOdds' => (float)$bet->current_odds,
            'stake' => (int)$bet->divine_favor_stake,
            'potentialPayout' => (int)$bet->potential_payout,
            'resolutionNotes' => $bet->resolution_notes,
        ];
    }

    private function findBetTarget(string $targetId): ?array
    {
        if ($settlement = Settlement::find($targetId)) {
            return ['type' => 'settlement', 'name' => $settlement->name];
        }
        if ($hero = Hero::find($targetId)) {
            return ['type' => 'hero', 'name' => $hero->name];
        }
        if ($region = Region::find($targetId)) {
            return ['type' => 'region', 'name' => $region->name];
        }
        if ($resource = ResourceNode::find($targetId)) {
            return ['type' => 'resource', 'name' => $resource->name];
        }
        if ($landmark = Landmark::find($targetId)) {
            return ['type' => 'landmark', 'name' => $landmark->name];
        }

        return null;
    }

    private function historyNoteFor(string $historyStatus, string $entityType): string
    {
        return match ($historyStatus) {
            'direct' => 'Direct events are available for this entity.',
            'direct_with_region_context' => 'Direct events are shown with nearby regional context.',
            'region_context' => "{$this->formatLabel($entityType)} has no direct events yet, so regional context is shown.",
            default => 'No matching events have been recorded yet.',
        };
    }

    private function formatLabel(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value));
    }
}
