<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameState;
use App\Models\Player;
use App\Models\Region;
use App\Models\ResourceNode;
use App\Models\Settlement;
use App\Repositories\EventRepository;

class WeatherInfluenceService
{
    private const CONFIG_CATEGORY = 'weather';
    private const CONFIG_KEY_HISTORY = 'influence_history';
    private const HISTORY_LIMIT = 20;
    private const RECENT_LIMIT = 8;

    private const INTENSITIES = [
        'minor' => [
            'label' => 'Minor',
            'costMultiplier' => 1.0,
            'effectMultiplier' => 1.0,
            'chance' => 65,
            'risk' => 8,
            'summary' => 'A light, local nudge with limited fallout.',
        ],
        'strong' => [
            'label' => 'Strong',
            'costMultiplier' => 1.55,
            'effectMultiplier' => 1.55,
            'chance' => 82,
            'risk' => 18,
            'summary' => 'A visible regional shift with meaningful side effects.',
        ],
        'severe' => [
            'label' => 'Severe',
            'costMultiplier' => 2.2,
            'effectMultiplier' => 2.15,
            'chance' => 100,
            'risk' => 34,
            'summary' => 'A forceful climate turn that almost always leaves a mark.',
        ],
    ];

    private const PATTERNS = [
        'gentle_rains' => [
            'label' => 'Gentle Rains',
            'baseCost' => 14,
            'summary' => 'Refreshes fields, wells, roadsides, and renewable resource nodes.',
            'region' => ['prosperity' => 5, 'chaos' => -2, 'dangerLevel' => -3, 'magicAffinity' => 0],
            'settlement' => ['prosperity' => 5, 'defensibility' => 0, 'populationPercent' => 1],
            'resource' => ['output' => 6],
            'travelEffect' => 'Travel becomes slower on soft roads, but safer as water and forage recover.',
            'conflictEffect' => 'Raids lose momentum while supply pressure eases.',
            'riskSummary' => 'Flooded low roads can briefly isolate smaller settlements.',
        ],
        'drought' => [
            'label' => 'Drought',
            'baseCost' => 16,
            'summary' => 'Drains rivers, stresses food supply, and pressures settlements.',
            'region' => ['prosperity' => -6, 'chaos' => 4, 'dangerLevel' => 5, 'magicAffinity' => 0],
            'settlement' => ['prosperity' => -6, 'defensibility' => -1, 'populationPercent' => -2],
            'resource' => ['output' => -8],
            'travelEffect' => 'Dry roads move quickly, but water scarcity makes long travel riskier.',
            'conflictEffect' => 'Scarcity raises the chance of raids, unrest, and contested supplies.',
            'riskSummary' => 'Shortages can cascade into migration and resource depletion.',
        ],
        'protective_winds' => [
            'label' => 'Protective Winds',
            'baseCost' => 13,
            'summary' => 'Screens roads and settlements with favorable winds.',
            'region' => ['prosperity' => 1, 'chaos' => -3, 'dangerLevel' => -6, 'magicAffinity' => 0],
            'settlement' => ['prosperity' => 1, 'defensibility' => 7, 'populationPercent' => 0],
            'resource' => ['output' => 1],
            'travelEffect' => 'Routes are shielded from ambush and foul weather.',
            'conflictEffect' => 'Sieges and raids struggle against defensive weather.',
            'riskSummary' => 'Overcorrected winds can stall trade and river travel.',
        ],
        'tempest' => [
            'label' => 'Tempest',
            'baseCost' => 18,
            'summary' => 'Unleashes storms that disrupt travel, resources, and conflict.',
            'region' => ['prosperity' => -4, 'chaos' => 7, 'dangerLevel' => 7, 'magicAffinity' => 1],
            'settlement' => ['prosperity' => -5, 'defensibility' => 2, 'populationPercent' => -1],
            'resource' => ['output' => -7],
            'travelEffect' => 'Roads, crossings, and ports become hazardous until the storm front passes.',
            'conflictEffect' => 'Open conflict scatters, but opportunistic violence rises in the aftermath.',
            'riskSummary' => 'Storm damage can destabilize resources and exposed settlements.',
        ],
        'arcane_mist' => [
            'label' => 'Arcane Mist',
            'baseCost' => 20,
            'summary' => 'Thickens the veil, amplifying magic while making outcomes stranger.',
            'region' => ['prosperity' => 1, 'chaos' => 3, 'dangerLevel' => 2, 'magicAffinity' => 8],
            'settlement' => ['prosperity' => 2, 'defensibility' => -1, 'populationPercent' => 0],
            'resource' => ['output' => 4],
            'travelEffect' => 'Travelers move unseen, but routes become harder to read.',
            'conflictEffect' => 'Skirmishes become unpredictable around illusions, omens, and wild magic.',
            'riskSummary' => 'Wild magic can twist resources or leave the region more mysterious.',
        ],
    ];

    private ?array $historyCache = null;

    public function __construct(
        private ?GameConfigService $configService = null,
        private ?EventRepository $eventRepository = null
    ) {
        $this->configService ??= GameConfigService::getInstance();
        $this->eventRepository ??= new EventRepository();
    }

    public function status(): array
    {
        $history = $this->loadHistory();

        return [
            'currentYear' => $this->currentYear(),
            'summary' => $this->summary($history),
            'patternOptions' => $this->patternOptions(),
            'intensityOptions' => $this->intensityOptions(),
            'currentInfluence' => $history[0] ?? null,
            'recentInfluences' => array_values(array_slice($history, 0, self::RECENT_LIMIT)),
        ];
    }

    public function advanceWorld(int $currentYear): array
    {
        $summary = [
            'processed' => 0,
            'changed' => 0,
            'events' => 0,
            'consequences' => [],
            'chains' => [],
            'errors' => [],
        ];
        $history = $this->loadHistory();

        foreach ($history as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $summary['processed']++;
            try {
                $changed = false;
                $chainResults = $this->advanceWeatherChains($entry, $currentYear);
                foreach ($chainResults as $chainResult) {
                    $summary['events']++;
                    $summary['chains'][] = $chainResult;
                    $changed = true;
                }

                if ($this->shouldResolveConsequence($entry, $currentYear)) {
                    $consequence = $this->resolveWeatherConsequence($entry, $currentYear);
                    if ($consequence !== null) {
                        $entry['lastConsequenceYear'] = $currentYear;
                        $entry['consequences'] = $this->prependLimited(
                            is_array($entry['consequences'] ?? null) ? $entry['consequences'] : [],
                            $consequence,
                            6
                        );
                        $chain = $this->seedWeatherChain($entry, $consequence, $currentYear);
                        if ($chain !== null) {
                            $entry['chains'] = $this->prependLimited(
                                is_array($entry['chains'] ?? null) ? $entry['chains'] : [],
                                $chain,
                                6
                            );
                        }
                        $summary['events']++;
                        $summary['consequences'][] = $consequence;
                        $changed = true;
                    }
                }

                if ($changed) {
                    $history[$index] = $entry;
                    $summary['changed']++;
                }
            } catch (\Throwable $error) {
                $summary['errors'][] = [
                    'id' => isset($entry['id']) ? (string)$entry['id'] : null,
                    'message' => $error->getMessage(),
                ];
            }
        }

        if ($summary['changed'] > 0) {
            $this->saveHistory($history);
        }

        return $summary;
    }

    public function nudge(array $payload): array
    {
        $regionId = trim((string)($payload['regionId'] ?? ''));
        if ($regionId === '') {
            throw new \InvalidArgumentException('A target region is required.');
        }

        $region = Region::find($regionId);
        if (!$region instanceof Region) {
            throw new \InvalidArgumentException("Region not found: {$regionId}");
        }

        $patternKey = $this->normalizePattern((string)($payload['pattern'] ?? 'gentle_rains'));
        $intensityKey = $this->normalizeIntensity((string)($payload['intensity'] ?? 'minor'));
        $pattern = self::PATTERNS[$patternKey];
        $intensity = self::INTENSITIES[$intensityKey];
        $cost = $this->calculateCost($region, $patternKey, $intensityKey);
        $player = Player::getSinglePlayer();

        if (!$player->spendDivineFavor($cost)) {
            return [
                'success' => false,
                'message' => 'Insufficient divine favor',
                'cost' => $cost,
                'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
            ];
        }

        $year = $this->currentYear();
        $history = $this->loadHistory();
        $seedBase = implode(':', [$region->id, $patternKey, $intensityKey, $year, count($history)]);
        $regionBefore = $this->regionSnapshot($region);
        $settlementChanges = $this->applySettlementEffects((string)$region->id, $pattern, $intensity, $seedBase);
        $resourceChanges = $this->applyResourceEffects((string)$region->id, $patternKey, $pattern, $intensity, $seedBase);

        $this->applyRegionEffects($region, $patternKey, $pattern, $intensity);
        $riskOutcome = $this->resolveRiskOutcome($region, $patternKey, $pattern, $intensity, $seedBase);
        $regionAfter = $this->regionSnapshot($region->fresh() ?? $region);
        $summary = $this->buildInfluenceSummary($pattern, $intensity, $regionAfter, $riskOutcome);
        $relatedSettlementIds = array_values(array_unique(array_map(
            fn(array $change): string => (string)$change['id'],
            $settlementChanges
        )));
        $relatedResourceIds = array_values(array_unique(array_map(
            fn(array $change): string => (string)$change['id'],
            $resourceChanges
        )));
        $event = $this->eventRepository->createEvent([
            'title' => 'Divine Weather Nudge: ' . $pattern['label'],
            'description' => $summary . ' ' . $pattern['travelEffect'] . ' ' . $pattern['conflictEffect'],
            'type' => 'weather_influence',
            'region_id' => $region->id,
            'related_region_ids' => [$region->id],
            'related_settlement_ids' => $relatedSettlementIds,
            'related_resource_ids' => $relatedResourceIds,
            'year' => $year,
        ]);

        $entry = [
            'id' => 'weather-' . bin2hex(random_bytes(8)),
            'eventId' => $event->id,
            'year' => $year,
            'regionId' => (string)$regionAfter['id'],
            'regionName' => (string)$regionAfter['name'],
            'pattern' => $patternKey,
            'patternLabel' => $pattern['label'],
            'intensity' => $intensityKey,
            'intensityLabel' => $intensity['label'],
            'cost' => $cost,
            'summary' => $summary,
            'travelEffect' => $pattern['travelEffect'],
            'conflictEffect' => $pattern['conflictEffect'],
            'riskOutcome' => $riskOutcome,
            'regionBefore' => $regionBefore,
            'regionAfter' => $regionAfter,
            'regionChange' => $this->diffSnapshots($regionBefore, $regionAfter),
            'settlementChanges' => $settlementChanges,
            'resourceChanges' => $resourceChanges,
        ];

        array_unshift($history, $entry);
        $this->saveHistory(array_slice($history, 0, self::HISTORY_LIMIT));

        return [
            'success' => true,
            'message' => $summary,
            'cost' => $cost,
            'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
            'influence' => $entry,
            'status' => $this->status(),
        ];
    }

    private function loadHistory(): array
    {
        if ($this->historyCache !== null) {
            return $this->historyCache;
        }

        $value = $this->configService->getConfig(self::CONFIG_CATEGORY, self::CONFIG_KEY_HISTORY, []);
        $this->historyCache = is_array($value) ? array_values($value) : [];

        return $this->historyCache;
    }

    private function saveHistory(array $history): void
    {
        $this->historyCache = array_values($history);
        $this->configService->setConfig(
            self::CONFIG_CATEGORY,
            self::CONFIG_KEY_HISTORY,
            $this->historyCache,
            'array',
            'Divine weather and climate influence history.'
        );
    }

    private function normalizePattern(string $pattern): string
    {
        $normalized = strtolower(trim($pattern));
        if (!isset(self::PATTERNS[$normalized])) {
            throw new \InvalidArgumentException("Unsupported weather pattern: {$pattern}");
        }

        return $normalized;
    }

    private function normalizeIntensity(string $intensity): string
    {
        $normalized = strtolower(trim($intensity));
        if (!isset(self::INTENSITIES[$normalized])) {
            throw new \InvalidArgumentException("Unsupported weather intensity: {$intensity}");
        }

        return $normalized;
    }

    private function calculateCost(Region $region, string $patternKey, string $intensityKey): int
    {
        $pattern = self::PATTERNS[$patternKey];
        $intensity = self::INTENSITIES[$intensityKey];
        $resonance = max(0, min(100, (int)($region->divine_resonance ?? 50)));
        $resonanceModifier = max(0.82, min(1.18, 1 - (($resonance - 50) * 0.0035)));

        return max(1, (int)round($pattern['baseCost'] * $intensity['costMultiplier'] * $resonanceModifier));
    }

    private function applyRegionEffects(
        Region $region,
        string $patternKey,
        array $pattern,
        array $intensity
    ): void {
        $effects = $pattern['region'];
        $region->prosperity = $this->clamp((int)$region->prosperity + $this->scaleDelta($effects['prosperity'], $intensity));
        $region->chaos = $this->clamp((int)$region->chaos + $this->scaleDelta($effects['chaos'], $intensity));
        $region->danger_level = $this->clamp((int)$region->danger_level + $this->scaleDelta($effects['dangerLevel'], $intensity));
        $region->magic_affinity = $this->clamp((int)$region->magic_affinity + $this->scaleDelta($effects['magicAffinity'], $intensity));
        $region->status = $this->deriveRegionStatus($region, $patternKey);
        $region->save();
    }

    private function applySettlementEffects(
        string $regionId,
        array $pattern,
        array $intensity,
        string $seedBase
    ): array {
        $changes = [];
        $settlements = Settlement::where('region_id', $regionId)->orderByDesc('population')->take(4)->get();

        foreach ($settlements as $index => $settlement) {
            if (!$settlement instanceof Settlement) {
                continue;
            }

            $roll = $this->roll($seedBase . ':settlement:' . $settlement->id);
            if ($roll > (int)$intensity['chance'] && $index > 0) {
                continue;
            }

            $before = $this->settlementSnapshot($settlement);
            $effects = $pattern['settlement'];
            $settlement->prosperity = $this->clamp(
                (int)$settlement->prosperity + $this->scaleDelta($effects['prosperity'], $intensity)
            );
            $settlement->defensibility = $this->clamp(
                (int)$settlement->defensibility + $this->scaleDelta($effects['defensibility'], $intensity)
            );

            $populationPercent = $this->scaleDelta($effects['populationPercent'], $intensity);
            if ($populationPercent !== 0) {
                $populationDelta = (int)round(max(1, (int)$settlement->population) * ($populationPercent / 100));
                if ($populationDelta === 0) {
                    $populationDelta = $populationPercent > 0 ? 1 : -1;
                }
                $settlement->population = max(0, (int)$settlement->population + $populationDelta);
            }

            $settlement->status = $this->deriveSettlementStatus($settlement);
            $settlement->last_event_year = $this->currentYear();
            $settlement->save();
            $after = $this->settlementSnapshot($settlement->fresh() ?? $settlement);
            $changes[] = array_merge($after, [
                'before' => $before,
                'after' => $after,
                'change' => $this->diffSnapshots($before, $after),
            ]);
        }

        return $changes;
    }

    private function applyResourceEffects(
        string $regionId,
        string $patternKey,
        array $pattern,
        array $intensity,
        string $seedBase
    ): array {
        $changes = [];
        $nodes = ResourceNode::where('region_id', $regionId)->orderByDesc('output')->take(4)->get();

        foreach ($nodes as $index => $node) {
            if (!$node instanceof ResourceNode) {
                continue;
            }

            $roll = $this->roll($seedBase . ':resource:' . $node->id);
            if ($roll > (int)$intensity['chance'] && $index > 0) {
                continue;
            }

            $before = $this->resourceSnapshot($node);
            $node->output = $this->clamp(
                (int)$node->output + $this->scaleDelta($pattern['resource']['output'], $intensity)
            );
            $node->status = $this->deriveResourceStatus($node, $patternKey, $roll);
            $node->save();
            $after = $this->resourceSnapshot($node->fresh() ?? $node);
            $changes[] = array_merge($after, [
                'before' => $before,
                'after' => $after,
                'change' => $this->diffSnapshots($before, $after),
            ]);
        }

        return $changes;
    }

    private function resolveRiskOutcome(
        Region $region,
        string $patternKey,
        array $pattern,
        array $intensity,
        string $seedBase
    ): array {
        $roll = $this->roll($seedBase . ':risk');
        $riskChance = (int)$intensity['risk'] + ($patternKey === 'arcane_mist' ? 8 : 0);

        if ($roll >= $riskChance) {
            return [
                'type' => 'none',
                'summary' => 'No additional weather backlash occurred.',
            ];
        }

        if ($patternKey === 'protective_winds') {
            $region->prosperity = $this->clamp((int)$region->prosperity - 1);
        } else {
            $region->chaos = $this->clamp((int)$region->chaos + 2);
            $region->danger_level = $this->clamp((int)$region->danger_level + 2);
        }

        if ($patternKey === 'arcane_mist') {
            $region->magic_affinity = $this->clamp((int)$region->magic_affinity + 2);
        }

        $region->status = $this->deriveRegionStatus($region, $patternKey);
        $region->save();

        return [
            'type' => 'backlash',
            'summary' => $pattern['riskSummary'],
        ];
    }

    private function shouldResolveConsequence(array $entry, int $currentYear): bool
    {
        $createdYear = (int)($entry['year'] ?? $currentYear);
        if ($createdYear >= $currentYear || $currentYear > $createdYear + 5) {
            return false;
        }
        if ((int)($entry['lastConsequenceYear'] ?? 0) >= $currentYear) {
            return false;
        }

        $consequences = is_array($entry['consequences'] ?? null) ? $entry['consequences'] : [];
        foreach ($consequences as $consequence) {
            if (is_array($consequence) && (int)($consequence['year'] ?? 0) === $currentYear) {
                return false;
            }
        }

        $patternKey = $this->normalizePattern((string)($entry['pattern'] ?? 'gentle_rains'));
        $intensityKey = $this->normalizeIntensity((string)($entry['intensity'] ?? 'minor'));
        $age = max(1, $currentYear - $createdYear);
        $baseChance = 10 + (int)self::INTENSITIES[$intensityKey]['risk'] + ($age * 4);
        $baseChance += in_array($patternKey, ['tempest', 'drought', 'arcane_mist'], true) ? 10 : 0;

        return $baseChance >= 70 || $this->roll("weather-consequence:{$entry['id']}:{$currentYear}") < min(80, $baseChance);
    }

    private function resolveWeatherConsequence(array $entry, int $currentYear): ?array
    {
        $regionId = (string)($entry['regionId'] ?? '');
        $region = $regionId !== '' ? Region::find($regionId) : null;
        if (!$region instanceof Region) {
            return null;
        }

        $patternKey = $this->normalizePattern((string)($entry['pattern'] ?? 'gentle_rains'));
        $intensityKey = $this->normalizeIntensity((string)($entry['intensity'] ?? 'minor'));
        $pattern = self::PATTERNS[$patternKey];
        $intensity = self::INTENSITIES[$intensityKey];
        $multiplier = $this->followThroughMultiplier($intensityKey);
        $before = $this->regionSnapshot($region);
        $this->applyWeatherFollowThroughToRegion($region, $patternKey, $pattern, $multiplier);
        $region->save();
        $after = $this->regionSnapshot($region->fresh() ?? $region);

        $settlementChanges = $this->applyWeatherFollowThroughToSettlements(
            (string)$region->id,
            $pattern,
            $multiplier,
            "weather:{$entry['id']}:settlements:{$currentYear}",
            $currentYear
        );
        $resourceChanges = $this->applyWeatherFollowThroughToResources(
            (string)$region->id,
            $patternKey,
            $pattern,
            $multiplier,
            "weather:{$entry['id']}:resources:{$currentYear}"
        );
        $relatedSettlementIds = array_values(array_unique(array_map(
            fn(array $change): string => (string)$change['id'],
            $settlementChanges
        )));
        $relatedResourceIds = array_values(array_unique(array_map(
            fn(array $change): string => (string)$change['id'],
            $resourceChanges
        )));
        $summary = $this->weatherConsequenceSummary(
            $entry,
            $pattern,
            $intensity,
            $before,
            $after,
            $settlementChanges,
            $resourceChanges,
            $currentYear
        );
        $event = $this->eventRepository->createEvent([
            'title' => 'Weather Consequence: ' . $pattern['label'],
            'description' => $summary,
            'type' => 'weather_consequence',
            'region_id' => $region->id,
            'related_region_ids' => [(string)$region->id],
            'related_settlement_ids' => $relatedSettlementIds,
            'related_resource_ids' => $relatedResourceIds,
            'year' => $currentYear,
        ]);

        return [
            'id' => 'weather-consequence-' . ($entry['id'] ?? bin2hex(random_bytes(4))) . '-' . $currentYear,
            'tool' => 'weather',
            'title' => $event->title,
            'weatherId' => (string)($entry['id'] ?? ''),
            'pattern' => $patternKey,
            'patternLabel' => $pattern['label'],
            'intensity' => $intensityKey,
            'intensityLabel' => $intensity['label'],
            'regionId' => (string)$region->id,
            'regionName' => (string)$region->name,
            'year' => $currentYear,
            'summary' => $summary,
            'eventId' => $event->id,
            'regionBefore' => $before,
            'regionAfter' => $after,
            'regionChange' => $this->diffSnapshots($before, $after),
            'settlementChanges' => $settlementChanges,
            'resourceChanges' => $resourceChanges,
            'relatedRegionIds' => [(string)$region->id],
            'relatedSettlementIds' => $relatedSettlementIds,
            'relatedResourceIds' => $relatedResourceIds,
        ];
    }

    private function seedWeatherChain(array $entry, array $consequence, int $currentYear): ?array
    {
        if ($this->hasActiveWeatherChain($entry['chains'] ?? [])) {
            return null;
        }

        $patternKey = $this->normalizePattern((string)($entry['pattern'] ?? 'gentle_rains'));
        $intensityKey = $this->normalizeIntensity((string)($entry['intensity'] ?? 'minor'));
        $risk = (int)self::INTENSITIES[$intensityKey]['risk'];
        $touches = count($consequence['settlementChanges'] ?? []) + count($consequence['resourceChanges'] ?? []);

        if ($risk < 18 && $touches === 0 && !in_array($patternKey, ['drought', 'tempest', 'arcane_mist'], true)) {
            return null;
        }

        $maxSteps = $intensityKey === 'severe' || in_array($patternKey, ['drought', 'tempest'], true) ? 3 : 2;
        $weatherId = (string)($entry['id'] ?? bin2hex(random_bytes(4)));

        return [
            'id' => 'weather-chain-' . $weatherId . '-' . $currentYear,
            'tool' => 'weather',
            'status' => 'active',
            'startedYear' => $currentYear,
            'lastAdvancedYear' => null,
            'nextYear' => $currentYear + 1,
            'step' => 0,
            'maxSteps' => $maxSteps,
            'sourceEventId' => (string)($consequence['eventId'] ?? ''),
            'eventIds' => array_values(array_filter([(string)($consequence['eventId'] ?? '')])),
            'title' => 'Weather Chain: ' . ($entry['patternLabel'] ?? self::PATTERNS[$patternKey]['label']),
            'latestSummary' => "A {$maxSteps}-step weather chain began from {$consequence['title']}.",
        ];
    }

    private function advanceWeatherChains(array &$entry, int $currentYear): array
    {
        $chains = is_array($entry['chains'] ?? null) ? array_values($entry['chains']) : [];
        $results = [];

        foreach ($chains as $index => $chain) {
            if (!is_array($chain) || !$this->shouldAdvanceWeatherChain($chain, $currentYear)) {
                continue;
            }

            [$updatedChain, $result] = $this->resolveWeatherChainStep($entry, $chain, $currentYear);
            $chains[$index] = $updatedChain;
            $results[] = $result;
        }

        if ($results !== []) {
            $entry['chains'] = array_slice(array_values($chains), 0, 6);
        }

        return $results;
    }

    private function resolveWeatherChainStep(array $entry, array $chain, int $currentYear): array
    {
        $regionId = (string)($entry['regionId'] ?? '');
        $region = $regionId !== '' ? Region::find($regionId) : null;
        if (!$region instanceof Region) {
            throw new \RuntimeException('Weather chain target region no longer exists.');
        }

        $step = max(1, (int)($chain['step'] ?? 0) + 1);
        $maxSteps = max($step, (int)($chain['maxSteps'] ?? 2));
        $patternKey = $this->normalizePattern((string)($entry['pattern'] ?? 'gentle_rains'));
        $intensityKey = $this->normalizeIntensity((string)($entry['intensity'] ?? 'minor'));
        $pattern = self::PATTERNS[$patternKey];
        $intensity = self::INTENSITIES[$intensityKey];
        $multiplier = max(0.18, $this->followThroughMultiplier($intensityKey) - ($step * 0.08));
        $before = $this->regionSnapshot($region);
        $this->applyWeatherFollowThroughToRegion($region, $patternKey, $pattern, $multiplier);
        $region->save();
        $after = $this->regionSnapshot($region->fresh() ?? $region);
        $settlementChanges = $this->applyWeatherFollowThroughToSettlements(
            (string)$region->id,
            $pattern,
            $multiplier,
            "weather-chain:{$chain['id']}:settlements:{$currentYear}",
            $currentYear
        );
        $resourceChanges = $this->applyWeatherFollowThroughToResources(
            (string)$region->id,
            $patternKey,
            $pattern,
            $multiplier,
            "weather-chain:{$chain['id']}:resources:{$currentYear}"
        );
        $relatedSettlementIds = array_values(array_unique(array_map(
            fn(array $change): string => (string)$change['id'],
            $settlementChanges
        )));
        $relatedResourceIds = array_values(array_unique(array_map(
            fn(array $change): string => (string)$change['id'],
            $resourceChanges
        )));
        $finished = $step >= $maxSteps;
        $title = 'Weather Chain: ' . $pattern['label'];
        $summary = "{$intensity['label']} {$pattern['label']} continued as chain step {$step}/{$maxSteps} in {$after['name']}: prosperity {$before['prosperity']}->{$after['prosperity']}, chaos {$before['chaos']}->{$after['chaos']}, danger {$before['dangerLevel']}->{$after['dangerLevel']}, magic {$before['magicAffinity']}->{$after['magicAffinity']}. "
            . count($settlementChanges) . ' settlements and ' . count($resourceChanges) . ' resources echoed the chain.'
            . ($finished ? ' The weather chain resolved.' : ' More follow-through remains possible.');
        $event = $this->eventRepository->createEvent([
            'title' => $title,
            'description' => $summary,
            'type' => 'weather_chain',
            'region_id' => $region->id,
            'related_region_ids' => [(string)$region->id],
            'related_settlement_ids' => $relatedSettlementIds,
            'related_resource_ids' => $relatedResourceIds,
            'year' => $currentYear,
        ]);

        $chain['step'] = $step;
        $chain['lastAdvancedYear'] = $currentYear;
        $chain['nextYear'] = $finished ? null : $currentYear + ($patternKey === 'drought' ? 2 : 1);
        $chain['status'] = $finished ? 'completed' : 'active';
        $chain['latestSummary'] = $summary;
        $eventIds = is_array($chain['eventIds'] ?? null) ? $chain['eventIds'] : [];
        $eventIds[] = $event->id;
        $chain['eventIds'] = array_slice(array_values($eventIds), -6);

        return [
            $chain,
            [
                'id' => (string)$chain['id'] . '-step-' . $step . '-' . $currentYear,
                'tool' => 'weather',
                'title' => $title,
                'weatherId' => (string)($entry['id'] ?? ''),
                'pattern' => $patternKey,
                'patternLabel' => $pattern['label'],
                'intensity' => $intensityKey,
                'intensityLabel' => $intensity['label'],
                'regionId' => (string)$region->id,
                'regionName' => (string)$region->name,
                'year' => $currentYear,
                'summary' => $summary,
                'eventId' => $event->id,
                'chainId' => (string)$chain['id'],
                'chainStep' => $step,
                'chainMaxSteps' => $maxSteps,
                'chainStatus' => (string)$chain['status'],
                'sourceEventId' => (string)($chain['sourceEventId'] ?? ''),
                'nextYear' => $chain['nextYear'],
                'regionBefore' => $before,
                'regionAfter' => $after,
                'regionChange' => $this->diffSnapshots($before, $after),
                'settlementChanges' => $settlementChanges,
                'resourceChanges' => $resourceChanges,
                'relatedRegionIds' => [(string)$region->id],
                'relatedSettlementIds' => $relatedSettlementIds,
                'relatedResourceIds' => $relatedResourceIds,
            ],
        ];
    }

    private function hasActiveWeatherChain(mixed $chains): bool
    {
        if (!is_array($chains)) {
            return false;
        }

        foreach ($chains as $chain) {
            if (is_array($chain) && ($chain['status'] ?? null) === 'active') {
                return true;
            }
        }

        return false;
    }

    private function shouldAdvanceWeatherChain(array $chain, int $currentYear): bool
    {
        if (($chain['status'] ?? null) !== 'active') {
            return false;
        }

        if ((int)($chain['lastAdvancedYear'] ?? 0) >= $currentYear) {
            return false;
        }

        return (int)($chain['nextYear'] ?? PHP_INT_MAX) <= $currentYear;
    }

    private function followThroughMultiplier(string $intensityKey): float
    {
        return match ($intensityKey) {
            'severe' => 0.65,
            'strong' => 0.45,
            default => 0.30,
        };
    }

    private function applyWeatherFollowThroughToRegion(
        Region $region,
        string $patternKey,
        array $pattern,
        float $multiplier
    ): void {
        $regionEffects = $pattern['region'];
        $region->prosperity = $this->clamp((int)$region->prosperity + $this->scaleFollowThrough($regionEffects['prosperity'], $multiplier));
        $region->chaos = $this->clamp((int)$region->chaos + $this->scaleFollowThrough($regionEffects['chaos'], $multiplier));
        $region->danger_level = $this->clamp((int)$region->danger_level + $this->scaleFollowThrough($regionEffects['dangerLevel'], $multiplier));
        $region->magic_affinity = $this->clamp((int)$region->magic_affinity + $this->scaleFollowThrough($regionEffects['magicAffinity'], $multiplier));
        $region->status = $this->deriveRegionStatus($region, $patternKey);
    }

    private function applyWeatherFollowThroughToSettlements(
        string $regionId,
        array $pattern,
        float $multiplier,
        string $seedBase,
        int $currentYear
    ): array {
        $changes = [];
        $settlements = Settlement::where('region_id', $regionId)->orderByDesc('population')->take(2)->get();
        foreach ($settlements as $settlement) {
            if (!$settlement instanceof Settlement || $this->roll($seedBase . ':' . $settlement->id) >= 78) {
                continue;
            }

            $before = $this->settlementSnapshot($settlement);
            $effects = $pattern['settlement'];
            $settlement->prosperity = $this->clamp((int)$settlement->prosperity + $this->scaleFollowThrough($effects['prosperity'], $multiplier));
            $settlement->defensibility = $this->clamp((int)$settlement->defensibility + $this->scaleFollowThrough($effects['defensibility'], $multiplier));
            $settlement->status = $this->deriveSettlementStatus($settlement);
            $settlement->last_event_year = $currentYear;
            $settlement->save();
            $after = $this->settlementSnapshot($settlement->fresh() ?? $settlement);
            $changes[] = array_merge($after, [
                'before' => $before,
                'after' => $after,
                'change' => $this->diffSnapshots($before, $after),
            ]);
        }

        return $changes;
    }

    private function applyWeatherFollowThroughToResources(
        string $regionId,
        string $patternKey,
        array $pattern,
        float $multiplier,
        string $seedBase
    ): array {
        $changes = [];
        $nodes = ResourceNode::where('region_id', $regionId)->orderByDesc('output')->take(2)->get();
        foreach ($nodes as $node) {
            if (!$node instanceof ResourceNode || $this->roll($seedBase . ':' . $node->id) >= 82) {
                continue;
            }

            $before = $this->resourceSnapshot($node);
            $node->output = $this->clamp((int)$node->output + $this->scaleFollowThrough($pattern['resource']['output'], $multiplier));
            $node->status = $this->deriveResourceStatus($node, $patternKey, $this->roll($seedBase . ':status:' . $node->id));
            $node->save();
            $after = $this->resourceSnapshot($node->fresh() ?? $node);
            $changes[] = array_merge($after, [
                'before' => $before,
                'after' => $after,
                'change' => $this->diffSnapshots($before, $after),
            ]);
        }

        return $changes;
    }

    private function scaleFollowThrough(int $delta, float $multiplier): int
    {
        if ($delta === 0) {
            return 0;
        }

        $scaled = max(1, (int)round(abs($delta) * $multiplier));
        return $delta < 0 ? -$scaled : $scaled;
    }

    private function weatherConsequenceSummary(
        array $entry,
        array $pattern,
        array $intensity,
        array $before,
        array $after,
        array $settlementChanges,
        array $resourceChanges,
        int $currentYear
    ): string {
        return "{$intensity['label']} {$pattern['label']} from year {$entry['year']} echoed into {$after['name']} in year {$currentYear}: prosperity {$before['prosperity']}->{$after['prosperity']}, chaos {$before['chaos']}->{$after['chaos']}, danger {$before['dangerLevel']}->{$after['dangerLevel']}, magic {$before['magicAffinity']}->{$after['magicAffinity']}. "
            . count($settlementChanges) . ' settlements and ' . count($resourceChanges) . ' resources recorded delayed weather effects.';
    }

    private function deriveRegionStatus(Region $region, string $patternKey): string
    {
        $prosperity = (int)$region->prosperity;
        $chaos = (int)$region->chaos;
        $danger = (int)$region->danger_level;
        $magic = (int)$region->magic_affinity;

        if ($prosperity >= 88 && $chaos <= 25 && $danger <= 25) {
            return 'flourishing';
        }

        if ($patternKey === 'gentle_rains' && $prosperity >= 68 && $chaos <= 35 && $danger <= 35) {
            return 'blessed';
        }

        if ($prosperity >= 70 && $chaos <= 42) {
            return 'prosperous';
        }

        if ($chaos >= 78 || $danger >= 78) {
            return 'war_torn';
        }

        if ($chaos >= 55 || $danger >= 55) {
            return 'turbulent';
        }

        if ($prosperity <= 20 && $danger <= 35) {
            return 'abandoned';
        }

        if ($prosperity <= 32) {
            return 'declining';
        }

        if ($patternKey === 'arcane_mist' || ($magic >= 75 && $chaos >= 30)) {
            return 'mysterious';
        }

        return 'stable';
    }

    private function deriveSettlementStatus(Settlement $settlement): string
    {
        $prosperity = (int)$settlement->prosperity;
        $population = (int)$settlement->population;

        if ($population <= 0 && $prosperity <= 5) {
            return 'ruined';
        }

        if ($population <= 0) {
            return 'abandoned';
        }

        if ($prosperity >= 82) {
            return 'thriving';
        }

        if ($prosperity >= 65) {
            return 'prosperous';
        }

        if ($prosperity >= 36) {
            return 'stable';
        }

        if ($prosperity >= 20) {
            return 'declining';
        }

        return 'struggling';
    }

    private function deriveResourceStatus(ResourceNode $node, string $patternKey, int $roll): string
    {
        $output = (int)$node->output;

        if ($output <= 5) {
            return 'depleted';
        }

        if ($patternKey === 'tempest') {
            return $roll < 48 ? 'unstable' : 'contested';
        }

        if ($patternKey === 'drought') {
            return $output <= 18 && $roll < 60 ? 'depleted' : 'overworked';
        }

        if ($patternKey === 'arcane_mist') {
            return $roll < 45 ? 'unstable' : 'blessed';
        }

        if ($patternKey === 'protective_winds') {
            return in_array($node->status, ['contested', 'unstable'], true) ? 'active' : (string)$node->status;
        }

        if ($output >= 82) {
            return 'blessed';
        }

        if ($output >= 70) {
            return 'status-flourishing';
        }

        return in_array($node->status, ['depleted', 'corrupted'], true) ? 'active' : (string)$node->status;
    }

    private function regionSnapshot(Region $region): array
    {
        return [
            'id' => (string)$region->id,
            'name' => (string)$region->name,
            'prosperity' => (int)$region->prosperity,
            'chaos' => (int)$region->chaos,
            'magicAffinity' => (int)$region->magic_affinity,
            'dangerLevel' => (int)$region->danger_level,
            'status' => (string)$region->status,
            'climateType' => (string)$region->climate_type,
        ];
    }

    private function settlementSnapshot(Settlement $settlement): array
    {
        return [
            'id' => (string)$settlement->id,
            'name' => (string)$settlement->name,
            'population' => (int)$settlement->population,
            'prosperity' => (int)$settlement->prosperity,
            'defensibility' => (int)$settlement->defensibility,
            'status' => (string)$settlement->status,
        ];
    }

    private function resourceSnapshot(ResourceNode $node): array
    {
        return [
            'id' => (string)$node->id,
            'name' => (string)$node->name,
            'type' => (string)$node->type,
            'output' => (int)$node->output,
            'effectiveOutput' => $node->getEffectiveOutput(),
            'status' => (string)$node->status,
        ];
    }

    private function diffSnapshots(array $before, array $after): array
    {
        $diff = [];
        foreach ($after as $key => $value) {
            if (!array_key_exists($key, $before) || $before[$key] === $value) {
                continue;
            }

            $diff[$key] = [
                'before' => $before[$key],
                'after' => $value,
                'delta' => is_numeric($before[$key]) && is_numeric($value)
                    ? (int)$value - (int)$before[$key]
                    : null,
            ];
        }

        return $diff;
    }

    private function patternOptions(): array
    {
        return array_map(
            fn(string $key, array $pattern): array => [
                'key' => $key,
                'label' => $pattern['label'],
                'summary' => $pattern['summary'],
                'baseCost' => (int)$pattern['baseCost'],
                'travelEffect' => $pattern['travelEffect'],
                'conflictEffect' => $pattern['conflictEffect'],
            ],
            array_keys(self::PATTERNS),
            self::PATTERNS
        );
    }

    private function intensityOptions(): array
    {
        return array_map(
            fn(string $key, array $intensity): array => [
                'key' => $key,
                'label' => $intensity['label'],
                'costMultiplier' => (float)$intensity['costMultiplier'],
                'effectMultiplier' => (float)$intensity['effectMultiplier'],
                'risk' => (int)$intensity['risk'],
                'summary' => $intensity['summary'],
            ],
            array_keys(self::INTENSITIES),
            self::INTENSITIES
        );
    }

    private function scaleDelta(int $delta, array $intensity): int
    {
        if ($delta === 0) {
            return 0;
        }

        $scaled = (int)round(abs($delta) * (float)$intensity['effectMultiplier']);
        return $delta < 0 ? -max(1, $scaled) : max(1, $scaled);
    }

    private function clamp(int $value): int
    {
        return max(0, min(100, $value));
    }

    private function roll(string $seed): int
    {
        return abs(crc32($seed)) % 100;
    }

    private function buildInfluenceSummary(array $pattern, array $intensity, array $region, array $riskOutcome): string
    {
        $risk = $riskOutcome['type'] === 'none' ? '' : ' Backlash: ' . $riskOutcome['summary'];

        return "{$intensity['label']} {$pattern['label']} reshaped {$region['name']} weather in year "
            . $this->currentYear() . ".{$risk}";
    }

    private function summary(array $history): string
    {
        if (count($history) === 0) {
            return 'No divine weather influences have been invoked.';
        }

        $latest = $history[0];
        return 'Latest weather influence: ' . ($latest['summary'] ?? 'weather shifted.');
    }

    private function currentYear(): int
    {
        return (int)(GameState::getCurrent()->current_year ?? 1);
    }

    private function prependLimited(array $items, array $item, int $limit): array
    {
        array_unshift($items, $item);
        return array_slice(array_values($items), 0, $limit);
    }
}
