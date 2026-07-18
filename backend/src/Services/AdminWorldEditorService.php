<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameEvent;
use App\Models\GameState;
use App\Models\Hero;
use App\Models\Landmark;
use App\Models\Region;
use App\Models\ResourceNode;
use App\Models\Settlement;
use Illuminate\Database\Eloquent\Model;

class AdminWorldEditorService
{
    private const ENTITY_TYPES = ['regions', 'settlements', 'landmarks', 'resources', 'heroes'];
    private const REGION_STATUSES = [
        'peaceful',
        'corrupt',
        'abandoned',
        'warring',
        'flourishing',
        'prosperous',
        'stable',
        'turbulent',
        'declining',
        'war_torn',
        'mysterious',
        'blessed',
        'cursed',
    ];
    private const REGION_CLIMATES = ['temperate', 'arctic', 'tropical', 'arid', 'magical'];
    private const REGION_CULTURES = ['scholarly', 'martial', 'mystical', 'mercantile', 'pastoral'];
    private const SETTLEMENT_TYPES = ['hamlet', 'village', 'town', 'city', 'metropolis', 'outpost', 'stronghold'];
    private const SETTLEMENT_STATUSES = ['thriving', 'prosperous', 'stable', 'declining', 'struggling', 'abandoned', 'ruined'];
    private const HERO_ROLES = ['scholar', 'warrior', 'prophet', 'agent of change', 'undecided'];
    private const HERO_STATUSES = ['living', 'deceased', 'undead', 'ascended'];
    private const LANDMARK_TYPES = ['temple', 'ruin', 'forest', 'mountain', 'river', 'monument', 'dungeon', 'tower', 'battlefield', 'grove'];
    private const LANDMARK_STATUSES = ['pristine', 'weathered', 'corrupted', 'blessed', 'haunted', 'active'];
    private const RESOURCE_TYPES = ['mine', 'quarry', 'forest', 'farmland', 'fishing', 'magical_spring', 'herb_garden'];
    private const RESOURCE_STATUSES = ['active', 'depleted', 'contested', 'corrupted', 'status-flourishing', 'overworked', 'blessed', 'unstable'];

    public function status(): array
    {
        return [
            'currentYear' => $this->currentYear(),
            'entityTypes' => self::ENTITY_TYPES,
            'summary' => [
                'regions' => Region::count(),
                'settlements' => Settlement::count(),
                'landmarks' => Landmark::count(),
                'resources' => ResourceNode::count(),
                'heroes' => Hero::count(),
            ],
            'options' => [
                'regions' => [
                    'statuses' => self::REGION_STATUSES,
                    'climateTypes' => self::REGION_CLIMATES,
                    'culturalInfluences' => self::REGION_CULTURES,
                ],
                'settlements' => [
                    'types' => self::SETTLEMENT_TYPES,
                    'statuses' => self::SETTLEMENT_STATUSES,
                ],
                'landmarks' => [
                    'types' => self::LANDMARK_TYPES,
                    'statuses' => self::LANDMARK_STATUSES,
                ],
                'resources' => [
                    'types' => self::RESOURCE_TYPES,
                    'statuses' => self::RESOURCE_STATUSES,
                ],
                'heroes' => [
                    'roles' => self::HERO_ROLES,
                    'statuses' => self::HERO_STATUSES,
                ],
            ],
            'entities' => [
                'regions' => Region::orderBy('name')->take(80)->get()->toArray(),
                'settlements' => Settlement::orderBy('name')->take(80)->get()->toArray(),
                'landmarks' => Landmark::orderBy('name')->take(80)->get()->toArray(),
                'resources' => ResourceNode::orderBy('name')->take(80)->get()->toArray(),
                'heroes' => Hero::orderBy('name')->take(80)->get()->toArray(),
            ],
            'auditLog' => $this->auditLog(),
        ];
    }

    public function create(string $entityType, array $payload): array
    {
        $type = $this->normalizeEntityType($entityType);
        $model = match ($type) {
            'regions' => Region::create($this->regionData($payload, true)),
            'settlements' => Settlement::create($this->settlementData($payload, true)),
            'landmarks' => Landmark::create($this->landmarkData($payload, true)),
            'resources' => ResourceNode::create($this->resourceData($payload, true)),
            'heroes' => Hero::create($this->heroData($payload, true)),
            default => throw new \InvalidArgumentException("Unsupported world editor entity type: {$entityType}"),
        };

        $eventId = $this->recordEditEvent('created', $type, $model);

        return $this->mutationResponse($type, 'created', $model, $eventId);
    }

    public function update(string $entityType, string $id, array $payload): array
    {
        $type = $this->normalizeEntityType($entityType);
        $model = $this->findModel($type, $id);
        if (!$model instanceof Model) {
            throw new \InvalidArgumentException($this->label($type) . " not found: {$id}");
        }

        $data = match ($type) {
            'regions' => $this->regionData($payload, false),
            'settlements' => $this->settlementData($payload, false),
            'landmarks' => $this->landmarkData($payload, false),
            'resources' => $this->resourceData($payload, false),
            'heroes' => $this->heroData($payload, false),
            default => throw new \InvalidArgumentException("Unsupported world editor entity type: {$entityType}"),
        };

        if ($data === []) {
            throw new \InvalidArgumentException('At least one editable field is required.');
        }

        $model->fill($data);
        $model->save();
        $eventId = $this->recordEditEvent('updated', $type, $model);

        return $this->mutationResponse($type, 'updated', $model->fresh(), $eventId);
    }

    public function preview(string $entityType, array $payload): array
    {
        $type = $this->normalizeEntityType($entityType);
        $mode = $this->previewMode($payload);
        $model = null;

        if ($mode === 'update') {
            $id = $this->requiredString($payload, 'id');
            $model = $this->findModel($type, $id);
            if (!$model instanceof Model) {
                throw new \InvalidArgumentException($this->label($type) . " not found: {$id}");
            }
            $data = match ($type) {
                'regions' => $this->regionData($payload, false),
                'settlements' => $this->settlementData($payload, false),
                'landmarks' => $this->landmarkData($payload, false),
                'resources' => $this->resourceData($payload, false),
                'heroes' => $this->heroData($payload, false),
                default => throw new \InvalidArgumentException("Unsupported world editor entity type: {$entityType}"),
            };

            if ($data === []) {
                throw new \InvalidArgumentException('At least one editable field is required.');
            }
        } else {
            $data = match ($type) {
                'regions' => $this->regionData($payload, true),
                'settlements' => $this->settlementData($payload, true),
                'landmarks' => $this->landmarkData($payload, true),
                'resources' => $this->resourceData($payload, true),
                'heroes' => $this->heroData($payload, true),
                default => throw new \InvalidArgumentException("Unsupported world editor entity type: {$entityType}"),
            };
        }

        $compatibility = $this->compatibilityPreview($type, $mode, $data, $model);

        return [
            'entityType' => $type,
            'mode' => $mode,
            'wouldPersist' => false,
            'isValid' => true,
            'entityId' => (string)($data['id'] ?? $model?->getKey() ?? ''),
            'summary' => $this->label($type) . " {$mode} preview passed validation with "
                . count($data) . ' editable fields.',
            'appliedFields' => array_keys($data),
            'compatibility' => $compatibility,
        ];
    }

    private function regionData(array $payload, bool $creating): array
    {
        $data = [];
        if ($creating) {
            $id = $this->idValue($payload, 'region');
            $this->assertNewId(Region::class, $id);
            $data['id'] = $id;
            $data['name'] = $this->requiredString($payload, 'name');
            $data['color'] = $this->color($payload, '#64748b');
            $data['prosperity'] = $this->intValue($payload, 'prosperity', 50, 0, 100);
            $data['chaos'] = $this->intValue($payload, 'chaos', 25, 0, 100);
            $data['magic_affinity'] = $this->intValue($payload, 'magicAffinity', 50, 0, 100);
            $data['status'] = $this->enumValue($payload, 'status', self::REGION_STATUSES, 'stable');
            $data['danger_level'] = $this->intValue($payload, 'dangerLevel', 25, 0, 100);
            $data['population_total'] = $this->intValue($payload, 'populationTotal', 0, 0, PHP_INT_MAX);
            $data['climate_type'] = $this->enumValue($payload, 'climateType', self::REGION_CLIMATES, 'temperate');
            $data['cultural_influence'] = $this->enumValue($payload, 'culturalInfluence', self::REGION_CULTURES, 'pastoral');
            $data['divine_resonance'] = $this->intValue($payload, 'divineResonance', 50, 0, 100);
            $data['event_ids'] = $this->stringList($this->value($payload, 'eventIds') ?? []);
            $data['tags'] = $this->stringList($this->value($payload, 'tags') ?? []);
            $data['regional_traits'] = $this->stringList($this->value($payload, 'regionalTraits') ?? []);
            $data['trade_routes'] = $this->stringList($this->value($payload, 'tradeRoutes') ?? []);
            return $data;
        }

        $this->maybeString($payload, $data, 'name');
        if ($this->has($payload, 'color')) {
            $data['color'] = $this->color($payload, '#64748b');
        }
        $this->maybeInt($payload, $data, 'prosperity', 'prosperity', 0, 100);
        $this->maybeInt($payload, $data, 'chaos', 'chaos', 0, 100);
        $this->maybeInt($payload, $data, 'magicAffinity', 'magic_affinity', 0, 100);
        $this->maybeEnum($payload, $data, 'status', 'status', self::REGION_STATUSES);
        $this->maybeInt($payload, $data, 'dangerLevel', 'danger_level', 0, 100);
        $this->maybeInt($payload, $data, 'populationTotal', 'population_total', 0, PHP_INT_MAX);
        $this->maybeEnum($payload, $data, 'climateType', 'climate_type', self::REGION_CLIMATES);
        $this->maybeEnum($payload, $data, 'culturalInfluence', 'cultural_influence', self::REGION_CULTURES);
        $this->maybeInt($payload, $data, 'divineResonance', 'divine_resonance', 0, 100);
        $this->maybeStringList($payload, $data, 'eventIds', 'event_ids');
        $this->maybeStringList($payload, $data, 'tags', 'tags');
        $this->maybeStringList($payload, $data, 'regionalTraits', 'regional_traits');
        $this->maybeStringList($payload, $data, 'tradeRoutes', 'trade_routes');

        return $data;
    }

    private function settlementData(array $payload, bool $creating): array
    {
        $data = [];
        if ($creating) {
            $id = $this->idValue($payload, 'settlement');
            $this->assertNewId(Settlement::class, $id);
            $data['id'] = $id;
            $data['region_id'] = $this->requiredRegionId($payload);
            $data['name'] = $this->requiredString($payload, 'name');
            $data['type'] = $this->enumValue($payload, 'type', self::SETTLEMENT_TYPES, 'village');
            $data['population'] = $this->intValue($payload, 'population', 100, 0, PHP_INT_MAX);
            $data['prosperity'] = $this->intValue($payload, 'prosperity', 50, 0, 100);
            $data['defensibility'] = $this->intValue($payload, 'defensibility', 35, 0, 100);
            $data['status'] = $this->enumValue($payload, 'status', self::SETTLEMENT_STATUSES, 'stable');
            $data['specializations'] = $this->stringList($this->value($payload, 'specializations') ?? []);
            $data['events'] = $this->stringList($this->value($payload, 'events') ?? []);
            $data['founded_year'] = $this->intValue($payload, 'foundedYear', $this->currentYear(), 1, PHP_INT_MAX);
            $data['last_event_year'] = $this->nullableInt($payload, 'lastEventYear', 1, PHP_INT_MAX);
            $data['traits'] = $this->stringList($this->value($payload, 'traits') ?? []);
            return $data;
        }

        if ($this->has($payload, 'regionId')) {
            $data['region_id'] = $this->requiredRegionId($payload);
        }
        $this->maybeString($payload, $data, 'name');
        $this->maybeEnum($payload, $data, 'type', 'type', self::SETTLEMENT_TYPES);
        $this->maybeInt($payload, $data, 'population', 'population', 0, PHP_INT_MAX);
        $this->maybeInt($payload, $data, 'prosperity', 'prosperity', 0, 100);
        $this->maybeInt($payload, $data, 'defensibility', 'defensibility', 0, 100);
        $this->maybeEnum($payload, $data, 'status', 'status', self::SETTLEMENT_STATUSES);
        $this->maybeStringList($payload, $data, 'specializations', 'specializations');
        $this->maybeStringList($payload, $data, 'events', 'events');
        $this->maybeInt($payload, $data, 'foundedYear', 'founded_year', 1, PHP_INT_MAX);
        if ($this->has($payload, 'lastEventYear')) {
            $data['last_event_year'] = $this->nullableInt($payload, 'lastEventYear', 1, PHP_INT_MAX);
        }
        $this->maybeStringList($payload, $data, 'traits', 'traits');

        return $data;
    }

    private function landmarkData(array $payload, bool $creating): array
    {
        $data = [];
        if ($creating) {
            $id = $this->idValue($payload, 'landmark');
            $this->assertNewId(Landmark::class, $id);
            $data['id'] = $id;
            $data['region_id'] = $this->requiredRegionId($payload);
            $data['name'] = $this->requiredString($payload, 'name');
            $data['type'] = $this->enumValue($payload, 'type', self::LANDMARK_TYPES, 'monument');
            $data['description'] = $this->stringValue($payload, 'description', '');
            $data['status'] = $this->enumValue($payload, 'status', self::LANDMARK_STATUSES, 'weathered');
            $data['magic_level'] = $this->intValue($payload, 'magicLevel', 25, 0, 100);
            $data['danger_level'] = $this->intValue($payload, 'dangerLevel', 20, 0, 100);
            $data['discovered_year'] = $this->nullableInt($payload, 'discoveredYear', 1, PHP_INT_MAX);
            $data['last_visited_year'] = $this->nullableInt($payload, 'lastVisitedYear', 1, PHP_INT_MAX);
            $data['associated_events'] = $this->stringList($this->value($payload, 'associatedEvents') ?? []);
            $data['traits'] = $this->stringList($this->value($payload, 'traits') ?? []);
            return $data;
        }

        if ($this->has($payload, 'regionId')) {
            $data['region_id'] = $this->requiredRegionId($payload);
        }
        $this->maybeString($payload, $data, 'name');
        $this->maybeEnum($payload, $data, 'type', 'type', self::LANDMARK_TYPES);
        $this->maybeString($payload, $data, 'description');
        $this->maybeEnum($payload, $data, 'status', 'status', self::LANDMARK_STATUSES);
        $this->maybeInt($payload, $data, 'magicLevel', 'magic_level', 0, 100);
        $this->maybeInt($payload, $data, 'dangerLevel', 'danger_level', 0, 100);
        if ($this->has($payload, 'discoveredYear')) {
            $data['discovered_year'] = $this->nullableInt($payload, 'discoveredYear', 1, PHP_INT_MAX);
        }
        if ($this->has($payload, 'lastVisitedYear')) {
            $data['last_visited_year'] = $this->nullableInt($payload, 'lastVisitedYear', 1, PHP_INT_MAX);
        }
        $this->maybeStringList($payload, $data, 'associatedEvents', 'associated_events');
        $this->maybeStringList($payload, $data, 'traits', 'traits');

        return $data;
    }

    private function resourceData(array $payload, bool $creating): array
    {
        $data = [];
        if ($creating) {
            $id = $this->idValue($payload, 'resource');
            $this->assertNewId(ResourceNode::class, $id);
            $regionId = $this->requiredRegionId($payload);
            $settlementId = $this->settlementId($payload, $regionId);
            $data['id'] = $id;
            $data['region_id'] = $regionId;
            $data['settlement_id'] = $settlementId;
            $data['name'] = $this->requiredString($payload, 'name');
            $data['type'] = $this->enumValue($payload, 'type', self::RESOURCE_TYPES, 'mine');
            $data['output'] = $this->intValue($payload, 'outputValue', 50, 0, 100);
            $data['status'] = $this->enumValue($payload, 'status', self::RESOURCE_STATUSES, 'active');
            return $data;
        }

        $regionId = null;
        if ($this->has($payload, 'regionId')) {
            $regionId = $this->requiredRegionId($payload);
            $data['region_id'] = $regionId;
        }
        if ($this->has($payload, 'settlementId')) {
            $data['settlement_id'] = $this->settlementId($payload, $regionId);
        }
        $this->maybeString($payload, $data, 'name');
        $this->maybeEnum($payload, $data, 'type', 'type', self::RESOURCE_TYPES);
        $this->maybeInt($payload, $data, 'outputValue', 'output', 0, 100);
        $this->maybeEnum($payload, $data, 'status', 'status', self::RESOURCE_STATUSES);

        return $data;
    }

    private function heroData(array $payload, bool $creating): array
    {
        $data = [];
        if ($creating) {
            $id = $this->idValue($payload, 'hero');
            $this->assertNewId(Hero::class, $id);
            $isAlive = $this->boolValue($payload, 'isAlive', true);
            $data['id'] = $id;
            $data['region_id'] = $this->requiredRegionId($payload);
            $data['name'] = $this->requiredString($payload, 'name');
            $data['role'] = $this->enumValue($payload, 'role', self::HERO_ROLES, 'undecided');
            $data['description'] = $this->stringValue($payload, 'description', '');
            $data['feats'] = $this->stringList($this->value($payload, 'feats') ?? []);
            $data['level'] = $this->intValue($payload, 'level', 1, 1, 100);
            $data['age'] = $this->intValue($payload, 'age', 20, 0, 500);
            $data['is_alive'] = $isAlive;
            $data['death_reason'] = $this->nullableString($payload, 'deathReason');
            $data['personality_traits'] = $this->stringList($this->value($payload, 'personalityTraits') ?? []);
            $data['alignment'] = $this->alignment($payload);
            $data['status'] = $this->enumValue($payload, 'status', self::HERO_STATUSES, $isAlive ? 'living' : 'deceased');
            return $data;
        }

        if ($this->has($payload, 'regionId')) {
            $data['region_id'] = $this->requiredRegionId($payload);
        }
        $this->maybeString($payload, $data, 'name');
        $this->maybeEnum($payload, $data, 'role', 'role', self::HERO_ROLES);
        $this->maybeString($payload, $data, 'description');
        $this->maybeStringList($payload, $data, 'feats', 'feats');
        $this->maybeInt($payload, $data, 'level', 'level', 1, 100);
        $this->maybeInt($payload, $data, 'age', 'age', 0, 500);
        if ($this->has($payload, 'isAlive')) {
            $data['is_alive'] = $this->boolValue($payload, 'isAlive', true);
        }
        if ($this->has($payload, 'deathReason')) {
            $data['death_reason'] = $this->nullableString($payload, 'deathReason');
        }
        $this->maybeStringList($payload, $data, 'personalityTraits', 'personality_traits');
        if ($this->has($payload, 'alignment')) {
            $data['alignment'] = $this->alignment($payload);
        }
        $this->maybeEnum($payload, $data, 'status', 'status', self::HERO_STATUSES);

        return $data;
    }

    private function mutationResponse(string $type, string $action, Model $model, string $eventId): array
    {
        return [
            'entityType' => $type,
            'action' => $action,
            'entity' => $model->toArray(),
            'eventId' => $eventId,
            'summary' => $this->label($type) . " {$action}: " . (string)($model->getAttribute('name') ?? $model->getKey()),
            'status' => $this->status(),
        ];
    }

    private function recordEditEvent(string $action, string $type, Model $model): string
    {
        $eventId = 'event-' . bin2hex(random_bytes(8));
        $name = (string)($model->getAttribute('name') ?? $model->getKey());
        $regionId = $this->regionIdForModel($type, $model);

        GameEvent::create([
            'id' => $eventId,
            'title' => 'Admin World Edit',
            'description' => $this->label($type) . " {$name} was {$action} by an admin world editor action.",
            'type' => 'admin_world_edit',
            'status' => 'completed',
            'region_id' => $regionId,
            'timestamp' => date('c'),
            'related_region_ids' => $regionId ? [$regionId] : [],
            'related_hero_ids' => $type === 'heroes' ? [(string)$model->getKey()] : [],
            'related_settlement_ids' => $type === 'settlements' ? [(string)$model->getKey()] : [],
            'related_landmark_ids' => $type === 'landmarks' ? [(string)$model->getKey()] : [],
            'related_resource_ids' => $type === 'resources' ? [(string)$model->getKey()] : [],
            'year' => $this->currentYear(),
        ]);

        return $eventId;
    }

    private function auditLog(): array
    {
        return GameEvent::where('type', 'admin_world_edit')
            ->orderByRaw('COALESCE(year, 0) DESC')
            ->orderByDesc('created_at')
            ->take(12)
            ->get()
            ->map(fn(GameEvent $event): array => $this->auditEntry($event))
            ->values()
            ->all();
    }

    private function auditEntry(GameEvent $event): array
    {
        $entityType = $this->auditEntityType($event);

        return [
            'id' => (string)$event->id,
            'title' => (string)$event->title,
            'description' => (string)$event->description,
            'status' => (string)$event->status,
            'year' => $event->year !== null ? (int)$event->year : null,
            'createdAt' => $event->created_at !== null ? (string)$event->created_at : null,
            'regionId' => $event->region_id !== null ? (string)$event->region_id : null,
            'entityType' => $entityType,
            'entityId' => $this->auditEntityId($event, $entityType),
            'eventUrl' => '/events/' . rawurlencode((string)$event->id),
            'timelineUrl' => $this->auditTimelineUrl($event, $entityType),
            'relatedRegionIds' => $this->ids($event->related_region_ids ?? []),
            'relatedHeroIds' => $this->ids($event->related_hero_ids ?? []),
            'relatedSettlementIds' => $this->ids($event->related_settlement_ids ?? []),
            'relatedLandmarkIds' => $this->ids($event->related_landmark_ids ?? []),
            'relatedResourceIds' => $this->ids($event->related_resource_ids ?? []),
        ];
    }

    private function auditEntityType(GameEvent $event): string
    {
        if ($this->ids($event->related_hero_ids ?? []) !== []) {
            return 'heroes';
        }
        if ($this->ids($event->related_settlement_ids ?? []) !== []) {
            return 'settlements';
        }
        if ($this->ids($event->related_landmark_ids ?? []) !== []) {
            return 'landmarks';
        }
        if ($this->ids($event->related_resource_ids ?? []) !== []) {
            return 'resources';
        }

        return 'regions';
    }

    private function auditEntityId(GameEvent $event, string $entityType): ?string
    {
        $ids = match ($entityType) {
            'heroes' => $this->ids($event->related_hero_ids ?? []),
            'settlements' => $this->ids($event->related_settlement_ids ?? []),
            'landmarks' => $this->ids($event->related_landmark_ids ?? []),
            'resources' => $this->ids($event->related_resource_ids ?? []),
            default => $this->ids($event->related_region_ids ?? []),
        };

        return $ids[0] ?? null;
    }

    private function auditTimelineUrl(GameEvent $event, string $entityType): string
    {
        $entityId = $this->auditEntityId($event, $entityType);
        $queryKey = match ($entityType) {
            'heroes' => 'heroId',
            'settlements' => 'settlementId',
            'landmarks' => 'landmarkId',
            'resources' => 'resourceId',
            default => 'regionId',
        };

        if ($entityId !== null) {
            return '/events?' . http_build_query([$queryKey => $entityId]);
        }

        return '/events?' . http_build_query(['type' => 'admin_world_edit']);
    }

    private function ids(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(fn(mixed $value): string => trim((string)$value), $values),
            fn(string $value): bool => $value !== ''
        )));
    }

    private function previewMode(array $payload): string
    {
        $mode = $this->stringValue($payload, 'mode', 'create');
        if (!in_array($mode, ['create', 'update'], true)) {
            throw new \InvalidArgumentException('mode must be one of: create, update');
        }

        return $mode;
    }

    private function compatibilityPreview(string $type, string $mode, array $data, ?Model $model): array
    {
        $warnings = [];
        $signals = [];
        $notes = [];
        $relatedIds = [
            'regions' => [],
            'settlements' => [],
            'landmarks' => [],
            'resources' => [],
            'heroes' => [],
        ];

        $regionId = (string)($data['region_id'] ?? $model?->getAttribute('region_id') ?? '');
        if ($type === 'regions') {
            $regionId = (string)($data['id'] ?? $model?->getKey() ?? $regionId);
        }
        if ($regionId !== '') {
            $relatedIds['regions'][] = $regionId;
        }

        $entityId = (string)($data['id'] ?? $model?->getKey() ?? '');
        if ($entityId !== '' && isset($relatedIds[$type])) {
            $relatedIds[$type][] = $entityId;
        }

        if (!empty($data['settlement_id'])) {
            $relatedIds['settlements'][] = (string)$data['settlement_id'];
        }

        $affectedSystems = match ($type) {
            'regions' => ['region drift', 'culture pressure', 'divine influence', 'betting odds', 'era pressure'],
            'settlements' => ['settlement growth', 'resource pressure', 'civic pressure', 'regional prosperity', 'era legacy'],
            'landmarks' => ['magic pressure', 'civic pressure', 'corruption risk', 'myth anchors', 'betting odds'],
            'resources' => ['resource output', 'settlement growth', 'regional prosperity', 'regional danger', 'resource bets'],
            'heroes' => ['hero lifecycle', 'civic pressure', 'champion systems', 'myth candidates', 'era legacy'],
            default => ['simulation state'],
        };

        $this->addNumericSignals($signals, $warnings, $data, [
            'prosperity' => ['Prosperity', 20, 85],
            'chaos' => ['Chaos', 15, 80],
            'magic_affinity' => ['Magic affinity', 10, 85],
            'danger_level' => ['Danger', 20, 80],
            'divine_resonance' => ['Divine resonance', 15, 85],
            'defensibility' => ['Defensibility', 20, 85],
            'population' => ['Population', 1, 10000],
            'population_total' => ['Population total', 1, 100000],
            'output' => ['Output', 15, 85],
            'magic_level' => ['Magic level', 15, 85],
            'level' => ['Hero level', 1, 50],
            'age' => ['Hero age', 1, 120],
        ]);

        $status = (string)($data['status'] ?? $model?->getAttribute('status') ?? '');
        if ($status !== '') {
            $signals[] = ['label' => 'Status', 'value' => $status, 'tone' => $this->statusTone($status)];
            if (in_array($status, ['corrupt', 'corrupted', 'cursed', 'haunted', 'warring', 'war_torn', 'abandoned', 'ruined', 'depleted', 'contested', 'unstable', 'overworked', 'undead'], true)) {
                $warnings[] = "Status {$status} can push dangerous or destabilizing tick outcomes.";
            }
        }

        if ($type === 'regions' && !empty($data['trade_routes'])) {
            $notes[] = 'Trade routes can add inter-region culture pressure if the route IDs match existing regions.';
            foreach ($this->ids($data['trade_routes']) as $routeId) {
                $relatedIds['regions'][] = $routeId;
            }
        }

        if ($type === 'heroes') {
            $isAlive = $data['is_alive'] ?? $model?->getAttribute('is_alive');
            if ($isAlive === false || $status === 'deceased') {
                $warnings[] = 'Non-living heroes stop contributing normal civic pressure and may affect legacy selection.';
            }
            $alignment = $data['alignment'] ?? $model?->getAttribute('alignment') ?? null;
            if (is_array($alignment)) {
                foreach (['good', 'chaotic'] as $axis) {
                    $value = (int)($alignment[$axis] ?? 50);
                    $signals[] = ['label' => 'Alignment ' . $axis, 'value' => (string)$value, 'tone' => $value >= 80 || $value <= 20 ? 'warning' : 'neutral'];
                    if ($value >= 90 || $value <= 10) {
                        $warnings[] = "Extreme {$axis} alignment can skew future hero behavior and mythology.";
                    }
                }
            }
        }

        if ($type === 'settlements' && in_array($status, ['abandoned', 'ruined', 'struggling', 'declining'], true)) {
            $warnings[] = 'Weak settlement status can reduce regional prosperity and raise era-collapse pressure.';
        }

        if ($type === 'landmarks' && in_array($status, ['corrupted', 'haunted', 'active'], true)) {
            $warnings[] = 'Landmark status can influence magic pressure, civic pressure, and corruption betting signals.';
        }

        if ($type === 'resources' && in_array($status, ['depleted', 'contested', 'corrupted', 'unstable', 'overworked'], true)) {
            $warnings[] = 'Resource status can suppress settlement growth and increase regional danger.';
        }

        if ($mode === 'create') {
            $notes[] = 'Preview validates create payloads but does not reserve the ID until save.';
        } else {
            $notes[] = 'Preview validates update payloads against the current entity but does not record an admin audit event.';
        }

        foreach ($relatedIds as $key => $values) {
            $relatedIds[$key] = array_values(array_unique(array_filter($values)));
        }

        return [
            'riskTier' => $this->riskTier($warnings),
            'affectedSystems' => $affectedSystems,
            'warnings' => array_values(array_unique($warnings)),
            'signals' => $signals,
            'notes' => $notes,
            'relatedIds' => $relatedIds,
        ];
    }

    private function addNumericSignals(array &$signals, array &$warnings, array $data, array $fields): void
    {
        foreach ($fields as $field => [$label, $low, $high]) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = (int)$data[$field];
            $tone = $value <= $low || $value >= $high ? 'warning' : 'neutral';
            $signals[] = ['label' => $label, 'value' => (string)$value, 'tone' => $tone];
            if ($value <= $low) {
                $warnings[] = "{$label} is low enough to affect simulation stability.";
            } elseif ($value >= $high) {
                $warnings[] = "{$label} is high enough to amplify downstream simulation effects.";
            }
        }
    }

    private function statusTone(string $status): string
    {
        return in_array($status, ['thriving', 'prosperous', 'flourishing', 'blessed', 'active', 'living'], true)
            ? 'positive'
            : (in_array($status, ['corrupt', 'corrupted', 'cursed', 'haunted', 'warring', 'war_torn', 'abandoned', 'ruined', 'depleted', 'contested', 'unstable', 'overworked', 'undead'], true)
                ? 'warning'
                : 'neutral');
    }

    private function riskTier(array $warnings): string
    {
        $count = count(array_unique($warnings));
        if ($count >= 4) {
            return 'high';
        }
        if ($count >= 2) {
            return 'medium';
        }

        return $count === 1 ? 'low' : 'stable';
    }

    private function findModel(string $type, string $id): ?Model
    {
        return match ($type) {
            'regions' => Region::find($id),
            'settlements' => Settlement::find($id),
            'landmarks' => Landmark::find($id),
            'resources' => ResourceNode::find($id),
            'heroes' => Hero::find($id),
            default => null,
        };
    }

    private function normalizeEntityType(string $entityType): string
    {
        $normalized = strtolower(str_replace(['_', '-'], '', trim($entityType)));

        return match ($normalized) {
            'region', 'regions' => 'regions',
            'settlement', 'settlements' => 'settlements',
            'landmark', 'landmarks' => 'landmarks',
            'resource', 'resources', 'resourcenode', 'resourcenodes' => 'resources',
            'hero', 'heroes' => 'heroes',
            default => throw new \InvalidArgumentException("Unsupported world editor entity type: {$entityType}"),
        };
    }

    private function label(string $type): string
    {
        return match ($type) {
            'regions' => 'Region',
            'settlements' => 'Settlement',
            'landmarks' => 'Landmark',
            'resources' => 'Resource',
            'heroes' => 'Hero',
            default => 'Entity',
        };
    }

    private function regionIdForModel(string $type, Model $model): ?string
    {
        if ($type === 'regions') {
            return (string)$model->getKey();
        }

        $regionId = $model->getAttribute('region_id');
        return is_string($regionId) && $regionId !== '' ? $regionId : null;
    }

    private function currentYear(): int
    {
        return (int)(GameState::getCurrent()->current_year ?? 1);
    }

    private function value(array $payload, string $camel): mixed
    {
        $snake = strtolower((string)preg_replace('/[A-Z]/', '_$0', lcfirst($camel)));
        if (array_key_exists($camel, $payload)) {
            return $payload[$camel];
        }
        if (array_key_exists($snake, $payload)) {
            return $payload[$snake];
        }
        return null;
    }

    private function has(array $payload, string $camel): bool
    {
        $snake = strtolower((string)preg_replace('/[A-Z]/', '_$0', lcfirst($camel)));
        return array_key_exists($camel, $payload) || array_key_exists($snake, $payload);
    }

    private function idValue(array $payload, string $prefix): string
    {
        $raw = $this->value($payload, 'id');
        if (is_string($raw) && trim($raw) !== '') {
            return $this->cleanId($raw);
        }

        return $prefix . '-' . bin2hex(random_bytes(5));
    }

    private function cleanId(string $value): string
    {
        $id = strtolower(trim($value));
        $id = (string)preg_replace('/[^a-z0-9_-]+/', '-', $id);
        $id = trim($id, '-_');
        if ($id === '') {
            throw new \InvalidArgumentException('Entity id cannot be empty.');
        }
        return $id;
    }

    private function assertNewId(string $modelClass, string $id): void
    {
        if ($modelClass::find($id)) {
            throw new \InvalidArgumentException("Entity id already exists: {$id}");
        }
    }

    private function requiredString(array $payload, string $field): string
    {
        $value = $this->stringValue($payload, $field, '');
        if ($value === '') {
            throw new \InvalidArgumentException("{$field} is required.");
        }
        return $value;
    }

    private function stringValue(array $payload, string $field, string $default): string
    {
        $value = $this->value($payload, $field);
        if ($value === null) {
            return $default;
        }
        if (!is_scalar($value)) {
            throw new \InvalidArgumentException("{$field} must be a string.");
        }
        return trim((string)$value);
    }

    private function nullableString(array $payload, string $field): ?string
    {
        $value = $this->value($payload, $field);
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_scalar($value)) {
            throw new \InvalidArgumentException("{$field} must be a string.");
        }
        return trim((string)$value);
    }

    private function maybeString(array $payload, array &$data, string $field): void
    {
        if ($this->has($payload, $field)) {
            $data[strtolower((string)preg_replace('/[A-Z]/', '_$0', lcfirst($field)))] = $this->stringValue($payload, $field, '');
        }
    }

    private function intValue(array $payload, string $field, int $default, int $min, int $max): int
    {
        $value = $this->value($payload, $field);
        if ($value === null || $value === '') {
            return $default;
        }
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException("{$field} must be numeric.");
        }
        $intValue = (int)$value;
        if ($intValue < $min || $intValue > $max) {
            throw new \InvalidArgumentException("{$field} must be between {$min} and {$max}.");
        }
        return $intValue;
    }

    private function nullableInt(array $payload, string $field, int $min, int $max): ?int
    {
        $value = $this->value($payload, $field);
        if ($value === null || $value === '') {
            return null;
        }
        return $this->intValue($payload, $field, 0, $min, $max);
    }

    private function maybeInt(array $payload, array &$data, string $field, string $column, int $min, int $max): void
    {
        if ($this->has($payload, $field)) {
            $data[$column] = $this->intValue($payload, $field, 0, $min, $max);
        }
    }

    private function enumValue(array $payload, string $field, array $allowed, string $default): string
    {
        $value = $this->stringValue($payload, $field, $default);
        if (!in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException("{$field} must be one of: " . implode(', ', $allowed));
        }
        return $value;
    }

    private function maybeEnum(array $payload, array &$data, string $field, string $column, array $allowed): void
    {
        if ($this->has($payload, $field)) {
            $data[$column] = $this->enumValue($payload, $field, $allowed, '');
        }
    }

    private function boolValue(array $payload, string $field, bool $default): bool
    {
        $value = $this->value($payload, $field);
        if ($value === null || $value === '') {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function color(array $payload, string $default): string
    {
        $color = $this->stringValue($payload, 'color', $default);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            throw new \InvalidArgumentException('color must be a hex value such as #64748b.');
        }
        return $color;
    }

    private function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = array_filter(array_map('trim', explode(',', $value)));
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException('List fields must be arrays or comma-separated strings.');
        }

        return array_values(array_unique(array_filter(
            array_map(fn(mixed $item): string => trim((string)$item), $value),
            fn(string $item): bool => $item !== ''
        )));
    }

    private function maybeStringList(array $payload, array &$data, string $field, string $column): void
    {
        if ($this->has($payload, $field)) {
            $data[$column] = $this->stringList($this->value($payload, $field));
        }
    }

    private function requiredRegionId(array $payload): string
    {
        $regionId = $this->stringValue($payload, 'regionId', '');
        if ($regionId === '' || !Region::find($regionId)) {
            throw new \InvalidArgumentException("regionId must reference an existing region.");
        }
        return $regionId;
    }

    private function settlementId(array $payload, ?string $regionId): ?string
    {
        $settlementId = $this->nullableString($payload, 'settlementId');
        if ($settlementId === null) {
            return null;
        }

        $settlement = Settlement::find($settlementId);
        if (!$settlement instanceof Settlement) {
            throw new \InvalidArgumentException("settlementId must reference an existing settlement.");
        }
        if ($regionId !== null && (string)$settlement->region_id !== $regionId) {
            throw new \InvalidArgumentException("settlementId must belong to the selected region.");
        }

        return $settlementId;
    }

    private function alignment(array $payload): array
    {
        $value = $this->value($payload, 'alignment');
        if ($value === null) {
            return ['good' => 50, 'chaotic' => 50, 'lastChange' => 'Admin world editor baseline'];
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException('alignment must be an object.');
        }

        return [
            'good' => $this->boundedArrayInt($value, 'good', 50, 0, 100),
            'chaotic' => $this->boundedArrayInt($value, 'chaotic', 50, 0, 100),
            'lastChange' => is_string($value['lastChange'] ?? null) ? trim($value['lastChange']) : 'Admin world editor edit',
        ];
    }

    private function boundedArrayInt(array $value, string $field, int $default, int $min, int $max): int
    {
        $raw = $value[$field] ?? $default;
        if (!is_numeric($raw)) {
            throw new \InvalidArgumentException("alignment.{$field} must be numeric.");
        }
        $intValue = (int)$raw;
        if ($intValue < $min || $intValue > $max) {
            throw new \InvalidArgumentException("alignment.{$field} must be between {$min} and {$max}.");
        }
        return $intValue;
    }
}
