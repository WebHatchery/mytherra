<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Hero;
use App\Models\Region;
use App\Models\Settlement;
use App\Models\DivineBet;
use App\Models\GameEvent;
use App\Models\GameState;
use App\Models\GameConfig;
use App\Models\Building;
use App\Models\Landmark;
use App\Models\ResourceNode;

class ExportService
{
    private const CHRONICLE_ENTITY_FILTERS = [
        'regionId',
        'heroId',
        'settlementId',
        'landmarkId',
        'resourceId',
        'era',
    ];

    private const CHRONICLE_MAJOR_TYPES = [
        'era_transition',
        'era_descendant',
        'era_pressure',
        'magic_discovery',
        'magic_progression',
        'myth_promoted',
        'myth_echo',
        'civilization_behavior',
        'civilization_diplomacy',
        'pantheon_intervention',
        'pantheon_relationship_arc',
        'champion_quest_completed',
        'champion_rivalry_resolved',
        'champion_rivalry_escalated',
        'artifact_consequence',
        'artifact_chain',
        'weather_consequence',
        'weather_chain',
        'time_omen_followup',
        'time_omen_chain',
        'divine_bet_resolved',
        'bet_resolution',
        'admin_world_edit',
    ];
    private const PUBLIC_SHARE_CATEGORY = 'public_chronicle_shares';
    private const PUBLIC_SHARE_RETENTION_DAYS = 30;
    private const PUBLIC_SHARE_VISIBILITY = 'public_link';

    private function timestamp(): string
    {
        return (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
    }

    /**
     * Export full world snapshot
     */
    public function exportFullSnapshot(): array
    {
        $gameState = GameState::getCurrent();
        $currentYear = (int)($gameState->current_year ?? 1);
        $eraPressureService = new EraPressureService();
        $eraPressure = $eraPressureService->evaluate($currentYear);
        $eraLegacyService = new EraLegacyService($eraPressureService);
        $eraLegacy = $eraLegacyService->evaluate($currentYear, $eraPressure);
        $eraComparisonService = new EraComparisonService();
        $eraTransition = (new EraTransitionService(null, $eraPressureService, $eraLegacyService, $eraComparisonService))
            ->status($currentYear, $eraPressure, $eraLegacy);
        $eraComparison = $eraComparisonService->current($currentYear);
        $artifacts = (new ArtifactService())->status();
        $temporalOmens = (new TemporalOmenService())->status();
        $weather = (new WeatherInfluenceService())->status();
        $magicDiscovery = (new MagicDiscoveryService())->status();
        $mythology = (new MythologyService())->status();
        $civilization = (new CivilizationBehaviorService())->status();
        $champions = (new ChampionService())->status();
        $pantheon = (new PantheonService())->status();

        return [
            'exportedAt' => $this->timestamp(),
            'version' => '1.0',
            'gameState' => [
                'era' => EraPressureService::currentEraForYear($currentYear),
                'year' => $currentYear,
                'tick' => $gameState->tick ?? 0,
                'eraPressure' => $eraPressure,
                'eraLegacy' => $eraLegacy,
                'eraTransition' => $eraTransition,
                'eraComparison' => $eraComparison,
                'artifacts' => $artifacts,
                'temporalOmens' => $temporalOmens,
                'weather' => $weather,
                'magicDiscovery' => $magicDiscovery,
                'mythology' => $mythology,
                'civilization' => $civilization,
                'champions' => $champions,
                'pantheon' => $pantheon,
            ],
            'regions' => $this->exportRegions(),
            'heroes' => $this->exportHeroes(),
            'settlements' => $this->exportSettlements(),
            'buildings' => $this->exportBuildings(),
            'landmarks' => $this->exportLandmarks(),
            'resourceNodes' => $this->exportResourceNodes(),
            'artifacts' => $artifacts['artifacts'] ?? [],
            'temporalOmens' => $temporalOmens['recentOmens'] ?? [],
            'weather' => $weather['recentInfluences'] ?? [],
            'magicDiscovery' => $magicDiscovery['paths'] ?? [],
            'mythology' => $mythology['myths'] ?? [],
            'civilization' => $civilization['recentDecisions'] ?? [],
            'champions' => $champions['champions'] ?? [],
            'pantheon' => $pantheon,
            'divineBets' => $this->exportDivineBets(),
            'events' => $this->exportEvents()
        ];
    }

    /**
     * Export by specific type
     */
    public function exportByType(string $type): array
    {
        $methodMap = [
            'regions' => 'exportRegions',
            'heroes' => 'exportHeroes',
            'settlements' => 'exportSettlements',
            'buildings' => 'exportBuildings',
            'landmarks' => 'exportLandmarks',
            'resources' => 'exportResourceNodes',
            'artifacts' => 'exportArtifacts',
            'omens' => 'exportTemporalOmens',
            'weather' => 'exportWeather',
            'magic' => 'exportMagicDiscovery',
            'myths' => 'exportMythology',
            'civilization' => 'exportCivilization',
            'champions' => 'exportChampions',
            'pantheon' => 'exportPantheon',
            'chronicle' => 'exportChronicleShare',
            'chronicle-replay' => 'exportChronicleReplay',
            'chronicleReplay' => 'exportChronicleReplay',
            'bets' => 'exportDivineBets',
            'events' => 'exportEvents'
        ];

        if (!isset($methodMap[$type])) {
            throw new \InvalidArgumentException("Unknown export type: {$type}");
        }

        return [
            'exportedAt' => $this->timestamp(),
            'type' => $type,
            'data' => $this->{$methodMap[$type]}()
        ];
    }

    public function exportChronicleShare(array $filters = []): array
    {
        $normalizedFilters = $this->normalizeChronicleFilters($filters);
        $gameState = GameState::getCurrent();
        $currentYear = (int)($gameState->current_year ?? 1);
        $eraPressure = (new EraPressureService())->evaluate($currentYear);
        $events = $this->chronicleEvents($normalizedFilters);
        $timeline = array_map(fn(GameEvent $event): array => $this->formatChronicleEvent($event), $events);
        $highlights = array_values(array_filter(
            $timeline,
            fn(array $event): bool => in_array($event['type'], self::CHRONICLE_MAJOR_TYPES, true)
        ));

        if ($highlights === []) {
            $highlights = array_slice($timeline, 0, min(5, count($timeline)));
        } else {
            $highlights = array_slice($highlights, 0, 8);
        }

        $topEventTypes = $this->topEventTypes($timeline);
        $yearRange = $this->yearRange($timeline);

        return [
            'exportedAt' => $this->timestamp(),
            'version' => '1.0',
            'packageType' => 'chronicle_share',
            'filters' => $normalizedFilters,
            'headline' => $this->chronicleHeadline($currentYear, $timeline, $normalizedFilters),
            'shareText' => $this->chronicleShareText($currentYear, $timeline, $topEventTypes, $eraPressure),
            'summary' => [
                'currentYear' => $currentYear,
                'currentEra' => EraPressureService::currentEraForYear($currentYear),
                'eventCount' => count($timeline),
                'highlightCount' => count($highlights),
                'yearRange' => $yearRange,
                'topEventTypes' => $topEventTypes,
                'worldCounts' => [
                    'regions' => Region::count(),
                    'settlements' => Settlement::count(),
                    'landmarks' => Landmark::count(),
                    'resources' => ResourceNode::count(),
                    'heroes' => Hero::count(),
                    'activeBets' => DivineBet::where('status', 'active')->count(),
                ],
            ],
            'eraContext' => [
                'currentEra' => EraPressureService::currentEraForYear($currentYear),
                'currentYear' => $currentYear,
                'topTrigger' => $eraPressure['highestTrigger']['label'] ?? null,
                'pressureScore' => $eraPressure['pressureScore'] ?? null,
                'tier' => $eraPressure['tier'] ?? null,
                'tierLabel' => $eraPressure['tierLabel'] ?? null,
                'warnings' => array_slice($eraPressure['warnings'] ?? [], 0, 5),
            ],
            'entitySpotlight' => $this->chronicleEntitySpotlight($timeline),
            'bettingHighlights' => $this->chronicleBettingHighlights($timeline),
            'highlights' => $highlights,
            'timeline' => $timeline,
        ];
    }

    public function exportChronicleReplay(array $filters = []): array
    {
        $normalizedFilters = $this->normalizeChronicleFilters($filters);
        $events = array_reverse($this->chronicleEvents($normalizedFilters));
        $timeline = array_map(fn(GameEvent $event): array => $this->formatChronicleEvent($event), $events);
        $frames = [];
        $runningTypes = [];
        $entityCounts = [
            'regions' => [],
            'heroes' => [],
            'settlements' => [],
            'landmarks' => [],
            'resources' => [],
        ];

        foreach ($timeline as $index => $event) {
            $type = (string)($event['type'] ?? 'unknown');
            $runningTypes[$type] = ($runningTypes[$type] ?? 0) + 1;
            foreach ($entityCounts as $key => $values) {
                foreach ($event['relatedIds'][$key] ?? [] as $id) {
                    $entityCounts[$key][(string)$id] = true;
                }
            }

            $frames[] = [
                'index' => $index + 1,
                'eventId' => (string)$event['id'],
                'year' => $event['year'],
                'era' => $event['era'],
                'title' => (string)$event['title'],
                'description' => (string)$event['description'],
                'type' => $type,
                'status' => (string)$event['status'],
                'timelineUrl' => (string)$event['timelineUrl'],
                'relatedIds' => $event['relatedIds'],
                'beatSummary' => $this->replayBeatSummary($event, $index + 1, count($timeline)),
                'runningContext' => [
                    'frame' => $index + 1,
                    'totalFrames' => count($timeline),
                    'dominantEventType' => $this->dominantEventType($runningTypes),
                    'entityCounts' => array_map(
                        fn(array $ids): int => count($ids),
                        $entityCounts
                    ),
                ],
            ];
        }

        return [
            'exportedAt' => $this->timestamp(),
            'version' => '1.0',
            'packageType' => 'chronicle_replay',
            'filters' => $normalizedFilters,
            'summary' => [
                'frameCount' => count($frames),
                'yearRange' => $this->yearRange($timeline),
                'topEventTypes' => $this->topEventTypes($timeline),
                'playbackOrder' => 'oldest_to_newest',
            ],
            'controls' => [
                'supportsStep' => true,
                'supportsScrub' => true,
                'supportsEntityFilters' => true,
            ],
            'frames' => $frames,
        ];
    }

    public function publishChronicleShare(array $filters = [], array $authUser = []): array
    {
        $shareId = $this->shareId();
        $createdAtDate = new \DateTimeImmutable();
        $createdAt = $createdAtDate->format(\DateTimeInterface::ATOM);
        $expiresAt = $createdAtDate
            ->modify('+' . self::PUBLIC_SHARE_RETENTION_DAYS . ' days')
            ->format(\DateTimeInterface::ATOM);
        $package = $this->exportChronicleShare($filters);
        $snapshot = [
            'shareId' => $shareId,
            'shareUrl' => '/chronicle-share/' . rawurlencode($shareId),
            'createdAt' => $createdAt,
            'expiresAt' => $expiresAt,
            'createdBy' => $this->shareCreator($authUser),
            'governance' => $this->shareGovernance([
                'createdAt' => $createdAt,
                'expiresAt' => $expiresAt,
            ]),
            'package' => $package,
        ];

        GameConfigService::getInstance()->setConfig(
            self::PUBLIC_SHARE_CATEGORY,
            $shareId,
            $snapshot,
            'array',
            'Public chronicle share snapshot generated from a curated export package with retention governance.'
        );

        return $snapshot;
    }

    public function getPublicChronicleShare(string $shareId): array
    {
        $shareId = strtolower(trim($shareId));
        if (!preg_match('/^[a-z0-9]{12}$/', $shareId)) {
            throw new \InvalidArgumentException('Chronicle share not found.');
        }

        $snapshot = GameConfigService::getInstance()->getConfig(
            self::PUBLIC_SHARE_CATEGORY,
            $shareId,
            ['missing' => true]
        );

        if (!is_array($snapshot) || ($snapshot['missing'] ?? false) === true) {
            throw new \InvalidArgumentException('Chronicle share not found.');
        }

        $governance = $this->shareGovernance($snapshot);
        if (($governance['visibilityStatus'] ?? 'active') !== 'active') {
            throw new \InvalidArgumentException('Chronicle share not found.');
        }

        return [
            'shareId' => (string)($snapshot['shareId'] ?? $shareId),
            'shareUrl' => (string)($snapshot['shareUrl'] ?? ('/chronicle-share/' . rawurlencode($shareId))),
            'createdAt' => (string)($snapshot['createdAt'] ?? ''),
            'expiresAt' => (string)($governance['expiresAt'] ?? ''),
            'createdBy' => is_array($snapshot['createdBy'] ?? null) ? $snapshot['createdBy'] : null,
            'governance' => $governance,
            'package' => is_array($snapshot['package'] ?? null) ? $snapshot['package'] : [],
        ];
    }

    public function listPublicChronicleShares(array $authUser = []): array
    {
        $currentUserId = $this->shareUserId($authUser);
        $isAdmin = $this->isAdminUser($authUser);
        $shares = GameConfig::where('category', self::PUBLIC_SHARE_CATEGORY)
            ->orderByDesc('updated_at')
            ->take($isAdmin ? 50 : 20)
            ->get()
            ->map(function (GameConfig $config) use ($currentUserId, $isAdmin): ?array {
                $snapshot = json_decode((string)$config->value, true);
                if (!is_array($snapshot)) {
                    return null;
                }

                $createdBy = is_array($snapshot['createdBy'] ?? null) ? $snapshot['createdBy'] : [];
                $creatorId = (string)($createdBy['id'] ?? '');
                if (!$isAdmin && $creatorId !== $currentUserId) {
                    return null;
                }

                $package = is_array($snapshot['package'] ?? null) ? $snapshot['package'] : [];
                $summary = is_array($package['summary'] ?? null) ? $package['summary'] : [];
                $governance = $this->shareGovernance($snapshot);
                $visibilityStatus = (string)($governance['visibilityStatus'] ?? 'active');

                return [
                    'shareId' => (string)($snapshot['shareId'] ?? $config->key),
                    'shareUrl' => (string)($snapshot['shareUrl'] ?? ('/chronicle-share/' . rawurlencode((string)$config->key))),
                    'createdAt' => (string)($snapshot['createdAt'] ?? $config->created_at?->toIso8601String() ?? ''),
                    'expiresAt' => (string)($governance['expiresAt'] ?? ''),
                    'createdBy' => $createdBy ?: null,
                    'governance' => $governance,
                    'headline' => (string)($package['headline'] ?? 'Mytherra Chronicle'),
                    'currentYear' => isset($summary['currentYear']) ? (int)$summary['currentYear'] : null,
                    'eventCount' => isset($summary['eventCount']) ? (int)$summary['eventCount'] : null,
                    'highlightCount' => isset($summary['highlightCount']) ? (int)$summary['highlightCount'] : null,
                    'visibilityStatus' => $visibilityStatus,
                    'isExpired' => (bool)($governance['isExpired'] ?? false),
                    'canRevoke' => ($isAdmin || $creatorId === $currentUserId) && $visibilityStatus !== 'revoked',
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'shares' => $shares,
            'canManageAll' => $isAdmin,
        ];
    }

    public function revokePublicChronicleShare(string $shareId, array $authUser = []): array
    {
        $shareId = strtolower(trim($shareId));
        if (!preg_match('/^[a-z0-9]{12}$/', $shareId)) {
            throw new \InvalidArgumentException('Chronicle share not found.');
        }

        $config = GameConfig::where('category', self::PUBLIC_SHARE_CATEGORY)
            ->where('key', $shareId)
            ->first();
        if (!$config instanceof GameConfig) {
            throw new \InvalidArgumentException('Chronicle share not found.');
        }

        $snapshot = json_decode((string)$config->value, true);
        if (!is_array($snapshot)) {
            throw new \InvalidArgumentException('Chronicle share not found.');
        }

        $createdBy = is_array($snapshot['createdBy'] ?? null) ? $snapshot['createdBy'] : [];
        $creatorId = (string)($createdBy['id'] ?? '');
        $currentUserId = $this->shareUserId($authUser);
        if (!$this->isAdminUser($authUser) && $creatorId !== $currentUserId) {
            throw new \InvalidArgumentException('Chronicle share not found.');
        }

        $revokedAt = $this->timestamp();
        $snapshot['revokedAt'] = $snapshot['revokedAt'] ?? $revokedAt;
        $snapshot['revokedBy'] = $snapshot['revokedBy'] ?? $this->shareCreator($authUser);
        $snapshot['governance'] = $this->shareGovernance($snapshot);

        GameConfigService::getInstance()->setConfig(
            self::PUBLIC_SHARE_CATEGORY,
            $shareId,
            $snapshot,
            'array',
            'Revoked public chronicle share snapshot retained for governance audit.'
        );

        return [
            'shareId' => $shareId,
            'revoked' => true,
            'revokedAt' => (string)$snapshot['revokedAt'],
            'visibilityStatus' => 'revoked',
        ];
    }

    /**
     * Export all regions with relationships
     */
    private function exportRegions(): array
    {
        return Region::with(['settlements', 'landmarks', 'heroes'])
            ->get()
            ->map(function ($region) {
                return [
                    'id' => $region->id,
                    'name' => $region->name,
                    'color' => $region->color,
                    'prosperity' => $region->prosperity,
                    'chaos' => $region->chaos,
                    'magic_affinity' => $region->magic_affinity,
                    'status' => $region->status,
                    'danger_level' => $region->danger_level,
                    'population_total' => $region->population_total,
                    'climate_type' => $region->climate_type,
                    'cultural_influence' => $region->cultural_influence,
                    'divine_resonance' => $region->divine_resonance,
                    'regional_traits' => $region->regional_traits,
                    'settlement_count' => $region->settlements->count(),
                    'hero_count' => $region->heroes->count(),
                    'landmark_count' => $region->landmarks->count()
                ];
            })
            ->toArray();
    }

    /**
     * Export all heroes
     */
    private function exportHeroes(): array
    {
        return Hero::with('region')
            ->get()
            ->map(function ($hero) {
                return [
                    'id' => $hero->id,
                    'name' => $hero->name,
                    'region_id' => $hero->region_id,
                    'region_name' => $hero->region?->name,
                    'role' => $hero->role,
                    'level' => $hero->level,
                    'age' => $hero->age,
                    'is_alive' => $hero->is_alive,
                    'status' => $hero->status,
                    'alignment' => $hero->alignment,
                    'personality_traits' => $hero->personality_traits,
                    'feats' => $hero->feats,
                    'death_reason' => $hero->death_reason
                ];
            })
            ->toArray();
    }

    /**
     * Export all settlements
     */
    private function exportSettlements(): array
    {
        return Settlement::with(['buildings'])
            ->get()
            ->map(function ($settlement) {
                return [
                    'id' => $settlement->id,
                    'name' => $settlement->name,
                    'region_id' => $settlement->region_id,
                    'type' => $settlement->type,
                    'population' => $settlement->population,
                    'prosperity' => $settlement->prosperity,
                    'defensibility' => $settlement->defensibility,
                    'status' => $settlement->status,
                    'building_count' => $settlement->buildings->count()
                ];
            })
            ->toArray();
    }

    /**
     * Export all buildings
     */
    private function exportBuildings(): array
    {
        return Building::all()
            ->map(function ($building) {
                return [
                    'id' => $building->id,
                    'name' => $building->name,
                    'settlement_id' => $building->settlement_id,
                    'type' => $building->type,
                    'condition' => $building->condition,
                    'status' => $building->status
                ];
            })
            ->toArray();
    }

    /**
     * Export all landmarks
     */
    private function exportLandmarks(): array
    {
        return Landmark::all()
            ->map(function ($landmark) {
                return [
                    'id' => $landmark->id,
                    'name' => $landmark->name,
                    'region_id' => $landmark->region_id,
                    'type' => $landmark->type,
                    'status' => $landmark->status,
                    'discovered_year' => $landmark->discovered_year
                ];
            })
            ->toArray();
    }

    /**
     * Export all resource nodes
     */
    private function exportResourceNodes(): array
    {
        return ResourceNode::all()
            ->map(function ($node) {
                return [
                    'id' => $node->id,
                    'name' => $node->name,
                    'region_id' => $node->region_id,
                    'settlement_id' => $node->settlement_id,
                    'type' => $node->type,
                    'status' => $node->status,
                    'output' => $node->output,
                    'effective_output' => $node->getEffectiveOutput()
                ];
            })
            ->toArray();
    }

    private function exportArtifacts(): array
    {
        $status = (new ArtifactService())->status();

        return $status['artifacts'] ?? [];
    }

    private function exportTemporalOmens(): array
    {
        $status = (new TemporalOmenService())->status();

        return $status['recentOmens'] ?? [];
    }

    private function exportWeather(): array
    {
        $status = (new WeatherInfluenceService())->status();

        return $status['recentInfluences'] ?? [];
    }

    private function exportMagicDiscovery(): array
    {
        $status = (new MagicDiscoveryService())->status();

        return $status['paths'] ?? [];
    }

    private function exportMythology(): array
    {
        $status = (new MythologyService())->status();

        return $status['myths'] ?? [];
    }

    private function exportCivilization(): array
    {
        return (new CivilizationBehaviorService())->status();
    }

    private function exportChampions(): array
    {
        return (new ChampionService())->status();
    }

    private function exportPantheon(): array
    {
        return (new PantheonService())->status();
    }

    private function normalizeChronicleFilters(array $filters): array
    {
        $limit = isset($filters['limit']) && is_numeric($filters['limit'])
            ? (int)$filters['limit']
            : 40;

        $normalized = ['limit' => min(max($limit, 10), 100)];

        foreach (self::CHRONICLE_ENTITY_FILTERS as $field) {
            $value = $filters[$field] ?? $filters[$this->snakeCase($field)] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $normalized[$field] = $field === 'era' && is_numeric($value)
                ? max(1, (int)$value)
                : trim((string)$value);
        }

        return $normalized;
    }

    /**
     * @return GameEvent[]
     */
    private function chronicleEvents(array $filters): array
    {
        $query = GameEvent::query();

        $regionId = $filters['regionId'] ?? null;
        if ($regionId) {
            $query->where(function ($inner) use ($regionId) {
                $inner->where('region_id', $regionId)
                    ->orWhereJsonContains('related_region_ids', $regionId);
            });
        }

        foreach (
            [
            'heroId' => 'related_hero_ids',
            'settlementId' => 'related_settlement_ids',
            'landmarkId' => 'related_landmark_ids',
            'resourceId' => 'related_resource_ids',
            ] as $field => $column
        ) {
            if (!empty($filters[$field])) {
                $query->whereJsonContains($column, $filters[$field]);
            }
        }

        if (!empty($filters['era'])) {
            $eraNumber = max(1, (int)$filters['era']);
            $query->whereBetween('year', [(($eraNumber - 1) * 100) + 1, $eraNumber * 100]);
        }

        return $query
            ->orderByRaw('COALESCE(year, 0) DESC')
            ->orderByDesc('timestamp')
            ->orderByDesc('created_at')
            ->take($filters['limit'])
            ->get()
            ->all();
    }

    private function formatChronicleEvent(GameEvent $event): array
    {
        $regionIds = array_values(array_unique(array_filter(array_merge(
            $event->region_id ? [(string)$event->region_id] : [],
            $this->stringArray($event->related_region_ids)
        ))));
        $heroIds = $this->stringArray($event->related_hero_ids);
        $settlementIds = $this->stringArray($event->related_settlement_ids);
        $landmarkIds = $this->stringArray($event->related_landmark_ids);
        $resourceIds = $this->stringArray($event->related_resource_ids);

        return [
            'id' => $event->id,
            'year' => $event->year,
            'era' => $event->year ? EraPressureService::currentEraForYear((int)$event->year) : null,
            'title' => $event->title,
            'description' => $event->description,
            'type' => $event->type,
            'status' => $event->status,
            'timelineUrl' => '/events/' . rawurlencode((string)$event->id),
            'filters' => [
                'regions' => array_map(fn(string $id): string => '/events?regionId=' . rawurlencode($id), $regionIds),
                'heroes' => array_map(fn(string $id): string => '/events?heroId=' . rawurlencode($id), $heroIds),
                'settlements' => array_map(fn(string $id): string => '/events?settlementId=' . rawurlencode($id), $settlementIds),
                'landmarks' => array_map(fn(string $id): string => '/events?landmarkId=' . rawurlencode($id), $landmarkIds),
                'resources' => array_map(fn(string $id): string => '/events?resourceId=' . rawurlencode($id), $resourceIds),
            ],
            'relatedIds' => [
                'regions' => $regionIds,
                'heroes' => $heroIds,
                'settlements' => $settlementIds,
                'landmarks' => $landmarkIds,
                'resources' => $resourceIds,
            ],
            'createdAt' => $event->created_at?->toIso8601String(),
        ];
    }

    private function chronicleEntitySpotlight(array $timeline): array
    {
        $ids = [
            'regions' => [],
            'heroes' => [],
            'settlements' => [],
            'landmarks' => [],
            'resources' => [],
        ];

        foreach ($timeline as $event) {
            foreach ($ids as $type => $values) {
                $ids[$type] = array_merge($ids[$type], $event['relatedIds'][$type] ?? []);
            }
        }

        foreach ($ids as $type => $values) {
            $ids[$type] = array_slice(array_values(array_unique($values)), 0, 8);
        }

        return [
            'regions' => $this->spotlightModels(Region::class, $ids['regions'], ['id', 'name', 'status', 'prosperity', 'chaos', 'danger_level']),
            'heroes' => $this->spotlightModels(Hero::class, $ids['heroes'], ['id', 'name', 'role', 'level', 'status', 'region_id']),
            'settlements' => $this->spotlightModels(Settlement::class, $ids['settlements'], ['id', 'name', 'type', 'status', 'region_id', 'population']),
            'landmarks' => $this->spotlightModels(Landmark::class, $ids['landmarks'], ['id', 'name', 'type', 'status', 'region_id']),
            'resources' => $this->spotlightModels(ResourceNode::class, $ids['resources'], ['id', 'name', 'type', 'status', 'region_id', 'output']),
        ];
    }

    private function spotlightModels(string $modelClass, array $ids, array $fields): array
    {
        if ($ids === []) {
            return [];
        }

        return $modelClass::whereIn('id', $ids)
            ->get()
            ->map(function ($model) use ($fields): array {
                $data = [];
                foreach ($fields as $field) {
                    $data[$this->camelCase($field)] = $model->{$field} ?? null;
                }
                return $data;
            })
            ->toArray();
    }

    private function chronicleBettingHighlights(array $timeline): array
    {
        $bets = DivineBet::query()
            ->whereIn('status', ['active', 'won', 'lost', 'expired'])
            ->orderByRaw('COALESCE(resolved_year, placed_year, 0) DESC')
            ->orderByDesc('updated_at')
            ->take(8)
            ->get();

        return $bets->map(function (DivineBet $bet): array {
            return [
                'id' => $bet->id,
                'type' => $bet->bet_type,
                'targetId' => $bet->target_id,
                'description' => $bet->description,
                'status' => $bet->status,
                'stake' => $bet->divine_favor_stake,
                'potentialPayout' => $bet->potential_payout,
                'placedYear' => $bet->placed_year,
                'resolvedYear' => $bet->resolved_year,
                'resolutionNotes' => $bet->resolution_notes,
            ];
        })->toArray();
    }

    private function topEventTypes(array $timeline): array
    {
        $counts = [];
        foreach ($timeline as $event) {
            $type = (string)($event['type'] ?? 'unknown');
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        arsort($counts);

        return array_slice($counts, 0, 6, true);
    }

    private function yearRange(array $timeline): array
    {
        $years = array_values(array_filter(
            array_map(fn(array $event): ?int => isset($event['year']) ? (int)$event['year'] : null, $timeline),
            fn(?int $year): bool => $year !== null
        ));

        return [
            'from' => $years ? min($years) : null,
            'to' => $years ? max($years) : null,
        ];
    }

    private function chronicleHeadline(int $currentYear, array $timeline, array $filters): string
    {
        $scope = [];
        foreach (self::CHRONICLE_ENTITY_FILTERS as $filter) {
            if (!empty($filters[$filter])) {
                $scope[] = $filter . ':' . $filters[$filter];
            }
        }

        $scopeText = $scope ? ' for ' . implode(', ', $scope) : '';

        return 'Mytherra Chronicle, Year ' . $currentYear . $scopeText . ' (' . count($timeline) . ' events)';
    }

    private function chronicleShareText(int $currentYear, array $timeline, array $topEventTypes, array $eraPressure): string
    {
        $latest = $timeline[0]['title'] ?? 'No recent events recorded';
        $topTypes = implode(', ', array_keys($topEventTypes)) ?: 'no dominant event type';
        $pressure = $eraPressure['highestTrigger']['label'] ?? ($eraPressure['tierLabel'] ?? 'stable pressure');

        return "Mytherra Year {$currentYear}: {$latest}. Recent themes: {$topTypes}. Era pressure: {$pressure}.";
    }

    private function replayBeatSummary(array $event, int $frame, int $totalFrames): string
    {
        $year = $event['year'] !== null ? 'Year ' . $event['year'] : 'Unknown year';
        $type = $this->formatEventType((string)($event['type'] ?? 'event'));
        $description = trim((string)($event['description'] ?? ''));
        $description = $description !== ''
            ? substr($description, 0, 180)
            : 'No description recorded.';

        return "Frame {$frame}/{$totalFrames}: {$year} {$type}. {$description}";
    }

    private function dominantEventType(array $counts): ?string
    {
        if ($counts === []) {
            return null;
        }

        arsort($counts);
        return (string)array_key_first($counts);
    }

    private function formatEventType(string $type): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $type));
    }

    private function stringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn(mixed $item): string => trim((string)$item), $value),
            fn(string $item): bool => $item !== ''
        ));
    }

    private function snakeCase(string $field): string
    {
        return strtolower((string)preg_replace('/[A-Z]/', '_$0', lcfirst($field)));
    }

    private function camelCase(string $field): string
    {
        return preg_replace_callback(
            '/_([a-z])/',
            fn(array $matches): string => strtoupper($matches[1]),
            $field
        ) ?? $field;
    }

    private function shareId(): string
    {
        return bin2hex(random_bytes(6));
    }

    private function shareCreator(array $authUser): array
    {
        return [
            'id' => $this->shareUserId($authUser),
            'displayName' => (string)($authUser['display_name'] ?? $authUser['username'] ?? 'Unknown'),
            'role' => (string)($authUser['role'] ?? 'user'),
            'isGuest' => (bool)($authUser['is_guest'] ?? false),
        ];
    }

    private function shareGovernance(array $snapshot): array
    {
        $existing = is_array($snapshot['governance'] ?? null) ? $snapshot['governance'] : [];
        $createdAt = (string)($snapshot['createdAt'] ?? '');
        $expiresAt = (string)($snapshot['expiresAt'] ?? $existing['expiresAt'] ?? '');
        if ($expiresAt === '' && $createdAt !== '') {
            try {
                $expiresAt = (new \DateTimeImmutable($createdAt))
                    ->modify('+' . self::PUBLIC_SHARE_RETENTION_DAYS . ' days')
                    ->format(\DateTimeInterface::ATOM);
            } catch (\Exception) {
                $expiresAt = '';
            }
        }

        $revokedAt = (string)($snapshot['revokedAt'] ?? $existing['revokedAt'] ?? '');
        $revokedBy = is_array($snapshot['revokedBy'] ?? null)
            ? $snapshot['revokedBy']
            : (is_array($existing['revokedBy'] ?? null) ? $existing['revokedBy'] : null);
        $isExpired = $this->shareExpired($expiresAt);
        $visibilityStatus = $revokedAt !== ''
            ? 'revoked'
            : ($isExpired ? 'expired' : 'active');

        return [
            'visibility' => (string)($existing['visibility'] ?? self::PUBLIC_SHARE_VISIBILITY),
            'visibilityStatus' => $visibilityStatus,
            'retentionDays' => (int)($existing['retentionDays'] ?? self::PUBLIC_SHARE_RETENTION_DAYS),
            'expiresAt' => $expiresAt,
            'isExpired' => $isExpired,
            'isRevoked' => $visibilityStatus === 'revoked',
            'revokedAt' => $revokedAt !== '' ? $revokedAt : null,
            'revokedBy' => $revokedBy,
            'policySummary' => $this->sharePolicySummary($visibilityStatus, $expiresAt),
        ];
    }

    private function shareExpired(string $expiresAt): bool
    {
        if ($expiresAt === '') {
            return false;
        }

        try {
            return new \DateTimeImmutable($expiresAt) <= new \DateTimeImmutable();
        } catch (\Exception) {
            return false;
        }
    }

    private function sharePolicySummary(string $visibilityStatus, string $expiresAt): string
    {
        if ($visibilityStatus === 'revoked') {
            return 'Public link has been revoked and is retained for share-governance audit.';
        }

        if ($visibilityStatus === 'expired') {
            return 'Public link has expired under the 30-day share-governance retention policy.';
        }

        return $expiresAt !== ''
            ? 'Public link remains active until ' . $expiresAt . ' under the 30-day share-governance policy.'
            : 'Public link remains active under the share-governance policy.';
    }

    private function shareUserId(array $authUser): string
    {
        return (string)($authUser['user_id'] ?? $authUser['id'] ?? '');
    }

    private function isAdminUser(array $authUser): bool
    {
        $roles = $authUser['roles'] ?? [];
        if (is_string($roles)) {
            $roles = [$roles];
        }

        return ($authUser['role'] ?? null) === 'admin'
            || (is_array($roles) && in_array('admin', $roles, true));
    }

    /**
     * Export all divine bets
     */
    private function exportDivineBets(): array
    {
        return DivineBet::all()
            ->map(function ($bet) {
                return [
                    'id' => $bet->id,
                    'player_id' => $bet->player_id,
                    'bet_type' => $bet->bet_type,
                    'target_id' => $bet->target_id,
                    'description' => $bet->description,
                    'timeframe' => $bet->timeframe,
                    'confidence' => $bet->confidence,
                    'divine_favor_stake' => $bet->divine_favor_stake,
                    'potential_payout' => $bet->potential_payout,
                    'current_odds' => $bet->current_odds,
                    'status' => $bet->status,
                    'placed_year' => $bet->placed_year,
                    'resolved_year' => $bet->resolved_year,
                    'resolution_notes' => $bet->resolution_notes,
                    'placed_at' => $bet->created_at?->toIso8601String(),
                    'updated_at' => $bet->updated_at?->toIso8601String()
                ];
            })
            ->toArray();
    }

    /**
     * Export recent events (last 1000)
     */
    private function exportEvents(): array
    {
        return GameEvent::orderBy('created_at', 'desc')
            ->take(1000)
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'type' => $event->type,
                    'title' => $event->title,
                    'description' => $event->description,
                    'region_id' => $event->region_id,
                    'related_region_ids' => $event->related_region_ids,
                    'related_hero_ids' => $event->related_hero_ids,
                    'related_settlement_ids' => $event->related_settlement_ids,
                    'related_landmark_ids' => $event->related_landmark_ids,
                    'related_resource_ids' => $event->related_resource_ids,
                    'year' => $event->year,
                    'created_at' => $event->created_at?->toIso8601String()
                ];
            })
            ->toArray();
    }
}
