<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DivineBet;
use App\Models\GameState;
use App\Models\Hero;
use App\Models\Player;
use App\Models\Region;
use App\Models\ResourceNode;
use App\Models\Settlement;
use App\Repositories\EventRepository;

class TemporalOmenService
{
    private const CONFIG_CATEGORY = 'omens';
    private const CONFIG_KEY_HISTORY = 'temporal_history';
    private const HISTORY_LIMIT = 20;
    private const RECENT_LIMIT = 8;
    private const ERA_LENGTH_YEARS = 100;

    private const HORIZONS = [
        'near' => [
            'label' => 'Near Future',
            'years' => 3,
            'baseCost' => 12,
            'confidence' => 'high',
            'summary' => 'A short reach with clear but narrow signs.',
        ],
        'generation' => [
            'label' => 'A Generation',
            'years' => 12,
            'baseCost' => 22,
            'confidence' => 'medium',
            'summary' => 'A wider reach where trends matter more than exact events.',
        ],
        'era' => [
            'label' => 'Era Edge',
            'years' => 0,
            'baseCost' => 34,
            'confidence' => 'low',
            'summary' => 'A long reach toward the era boundary, full of conditional outcomes.',
        ],
    ];

    private const TARGET_TYPES = [
        'world' => [
            'label' => 'World',
            'summary' => 'Read the broad pressure of the entire world.',
        ],
        'region' => [
            'label' => 'Region',
            'summary' => 'Read local prosperity, danger, resources, and settlements.',
        ],
        'hero' => [
            'label' => 'Hero',
            'summary' => 'Read a mortal thread for milestone, mortality, and regional pressure.',
        ],
    ];

    private ?array $historyCache = null;

    public function __construct(
        private ?GameConfigService $configService = null,
        private ?EventRepository $eventRepository = null,
        private ?EraPressureService $eraPressureService = null
    ) {
        $this->configService ??= GameConfigService::getInstance();
        $this->eventRepository ??= new EventRepository();
        $this->eraPressureService ??= new EraPressureService();
    }

    public function status(): array
    {
        $history = $this->loadHistory();

        return [
            'currentYear' => $this->currentYear(),
            'summary' => $this->summary($history),
            'targetOptions' => $this->targetOptions(),
            'horizonOptions' => $this->horizonOptions(),
            'currentOmen' => $history[0] ?? null,
            'recentOmens' => array_values(array_slice($history, 0, self::RECENT_LIMIT)),
        ];
    }

    public function advanceWorld(int $currentYear): array
    {
        $summary = [
            'processed' => 0,
            'changed' => 0,
            'events' => 0,
            'followUps' => [],
            'errors' => [],
        ];
        $history = $this->loadHistory();

        foreach ($history as $index => $omen) {
            if (!is_array($omen)) {
                continue;
            }

            $summary['processed']++;
            try {
                if (!$this->shouldResolveFollowUp($omen, $currentYear)) {
                    continue;
                }

                $followUp = $this->resolveOmenFollowUp($omen, $currentYear);
                $omen['resolvedYear'] = $currentYear;
                $omen['resolutionStatus'] = $followUp['outcome'];
                $omen['resolutionSummary'] = $followUp['summary'];
                $omen['resolutionEventId'] = $followUp['eventId'];
                $omen['followUps'] = $this->prependLimited(
                    is_array($omen['followUps'] ?? null) ? $omen['followUps'] : [],
                    $followUp,
                    4
                );
                $history[$index] = $omen;
                $summary['changed']++;
                $summary['events']++;
                $summary['followUps'][] = $followUp;
            } catch (\Throwable $error) {
                $summary['errors'][] = [
                    'id' => isset($omen['id']) ? (string)$omen['id'] : null,
                    'message' => $error->getMessage(),
                ];
            }
        }

        if ($summary['changed'] > 0) {
            $this->saveHistory($history);
        }

        return $summary;
    }

    public function read(array $payload): array
    {
        $targetType = $this->normalizeTargetType((string)($payload['targetType'] ?? 'world'));
        $horizon = $this->normalizeHorizon((string)($payload['horizon'] ?? 'near'));
        $targetId = is_string($payload['targetId'] ?? null) ? trim((string)$payload['targetId']) : null;
        $target = $this->resolveTarget($targetType, $targetId);
        $cost = $this->calculateCost($target, $horizon);
        $player = Player::getSinglePlayer();

        if (!$player->spendDivineFavor($cost)) {
            return [
                'success' => false,
                'message' => 'Insufficient divine favor',
                'cost' => $cost,
                'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
            ];
        }

        $history = $this->loadHistory();
        $omen = $this->buildOmen($target, $horizon, $cost, count($history));
        $event = $this->eventRepository->createEvent([
            'title' => 'Temporal Omen Read',
            'description' => $omen['summary'] . ' ' . ($omen['predictions'][0]['summary'] ?? ''),
            'type' => 'time_omen',
            'region_id' => $omen['relatedRegionIds'][0] ?? null,
            'related_region_ids' => $omen['relatedRegionIds'],
            'related_hero_ids' => $omen['relatedHeroIds'],
            'related_settlement_ids' => $omen['relatedSettlementIds'],
            'related_resource_ids' => $omen['relatedResourceIds'],
            'year' => $this->currentYear(),
        ]);
        $omen['eventId'] = $event->id;

        array_unshift($history, $omen);
        $this->saveHistory(array_slice($history, 0, self::HISTORY_LIMIT));

        return [
            'success' => true,
            'message' => $omen['summary'],
            'cost' => $cost,
            'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
            'omen' => $omen,
            'status' => $this->status(),
        ];
    }

    private function buildOmen(array $target, string $horizonKey, int $cost, int $historyCount): array
    {
        $currentYear = $this->currentYear();
        $horizonYears = $this->horizonYears($horizonKey, $currentYear);
        $targetYear = $currentYear + $horizonYears;
        $horizon = self::HORIZONS[$horizonKey];
        $seed = implode(':', [$target['type'], $target['id'] ?? 'world', $horizonKey, $currentYear, $historyCount]);
        $result = match ($target['type']) {
            'region' => $this->regionOmen($target, $targetYear, $horizon, $seed),
            'hero' => $this->heroOmen($target, $targetYear, $horizon, $seed),
            default => $this->worldOmen($targetYear, $horizon, $seed),
        };
        $predictions = $result['predictions'];
        $relatedRegionIds = $this->mergeIds($target['relatedRegionIds'], $this->collectRelatedIds($predictions, 'relatedRegionIds'));
        $relatedHeroIds = $this->mergeIds($target['relatedHeroIds'], $this->collectRelatedIds($predictions, 'relatedHeroIds'));
        $relatedSettlementIds = $this->collectRelatedIds($predictions, 'relatedSettlementIds');
        $relatedResourceIds = $this->collectRelatedIds($predictions, 'relatedResourceIds');
        $riskScore = $predictions === [] ? 0 : max(array_map(fn(array $prediction): int => (int)$prediction['riskScore'], $predictions));
        $riskBand = $this->riskBand($riskScore);

        return [
            'id' => 'omen-' . bin2hex(random_bytes(8)),
            'eventId' => null,
            'createdYear' => $currentYear,
            'targetYear' => $targetYear,
            'horizon' => $horizonKey,
            'horizonLabel' => $horizon['label'],
            'horizonYears' => $horizonYears,
            'targetType' => $target['type'],
            'targetId' => $target['id'],
            'targetName' => $target['name'],
            'cost' => $cost,
            'confidence' => $horizon['confidence'],
            'riskBand' => $riskBand,
            'summary' => $this->omenSummary($target, $horizon, $targetYear, $riskBand, $result['lead']),
            'signals' => $result['signals'],
            'predictions' => $predictions,
            'relatedRegionIds' => $relatedRegionIds,
            'relatedHeroIds' => $relatedHeroIds,
            'relatedSettlementIds' => $relatedSettlementIds,
            'relatedResourceIds' => $relatedResourceIds,
            'consistencyNote' => 'This omen forecasts current trajectories only. It does not rewind, branch, or mutate world state.',
        ];
    }

    private function worldOmen(int $targetYear, array $horizon, string $seed): array
    {
        $regions = Region::all();
        $settlements = Settlement::all();
        $heroes = Hero::all();
        $resources = ResourceNode::all();
        $activeBets = DivineBet::where('status', 'active')->get();
        $eraPressure = $this->eraPressureService->evaluate($targetYear);
        $highestTrigger = $eraPressure['highestTrigger'] ?? [];
        $strainedRegion = $regions->sortByDesc(fn(Region $region): int => $this->regionPressureScore($region))->first();
        $weakSettlement = $settlements->sortBy(fn(Settlement $settlement): int => (int)$settlement->prosperity + (int)$settlement->defensibility)->first();
        $unstableResource = $resources->sortBy(fn(ResourceNode $node): int => $node->status === 'active' ? 100 + (int)$node->output : (int)$node->output)->first();
        $predictions = [];

        $predictions[] = $this->prediction(
            'era_pressure',
            'Era pressure gathers',
            (string)($highestTrigger['summary'] ?? $eraPressure['summary']),
            (int)($highestTrigger['score'] ?? $eraPressure['pressureScore'] ?? 0),
            $horizon['confidence'],
            [
                'relatedRegionIds' => $eraPressure['relatedRegionIds'] ?? [],
                'relatedHeroIds' => $eraPressure['relatedHeroIds'] ?? [],
                'relatedSettlementIds' => $eraPressure['relatedSettlementIds'] ?? [],
                'relatedResourceIds' => $eraPressure['relatedResourceIds'] ?? [],
            ]
        );

        if ($strainedRegion instanceof Region) {
            $score = $this->regionPressureScore($strainedRegion);
            $predictions[] = $this->prediction(
                'regional_stress',
                "{$strainedRegion->name} strains the timeline",
                "{$strainedRegion->name} carries the sharpest pressure with chaos {$strainedRegion->chaos}, danger {$strainedRegion->danger_level}, and status {$strainedRegion->status}.",
                $score,
                $this->confidenceWithRoll($horizon['confidence'], $seed . ':region'),
                ['relatedRegionIds' => [(string)$strainedRegion->id]]
            );
        }

        if ($weakSettlement instanceof Settlement) {
            $score = max(0, 100 - ((int)$weakSettlement->prosperity + (int)$weakSettlement->defensibility));
            $predictions[] = $this->prediction(
                'settlement_survival',
                "{$weakSettlement->name} needs attention",
                "{$weakSettlement->name} is the weakest visible settlement thread: prosperity {$weakSettlement->prosperity}, defense {$weakSettlement->defensibility}, status {$weakSettlement->status}.",
                $score,
                $horizon['confidence'],
                [
                    'relatedRegionIds' => [(string)$weakSettlement->region_id],
                    'relatedSettlementIds' => [(string)$weakSettlement->id],
                ]
            );
        }

        if ($unstableResource instanceof ResourceNode) {
            $score = $unstableResource->status === 'active' ? max(15, 100 - (int)$unstableResource->output) : 70;
            $predictions[] = $this->prediction(
                'resource_pressure',
                "{$unstableResource->name} flickers",
                "{$unstableResource->name} may shape local scarcity with output {$unstableResource->output} and status {$unstableResource->status}.",
                $score,
                $horizon['confidence'],
                [
                    'relatedRegionIds' => [(string)$unstableResource->region_id],
                    'relatedResourceIds' => [(string)$unstableResource->id],
                ]
            );
        }

        if ($activeBets->count() > 0) {
            $expiring = $activeBets->filter(
                fn(DivineBet $bet): bool => ((int)$bet->placed_year + (int)$bet->timeframe) <= $targetYear
            )->count();
            $predictions[] = $this->prediction(
                'betting_window',
                'Wagers near resolution',
                "{$expiring} of {$activeBets->count()} active divine bets are likely to reach their resolution window by year {$targetYear}.",
                min(100, 35 + ($expiring * 12)),
                'medium',
                []
            );
        }

        return [
            'lead' => 'the broad timeline',
            'signals' => [
                $this->signal('Target year', (string)$targetYear, 'neutral'),
                $this->signal('Era pressure', (string)($eraPressure['pressureScore'] ?? 0) . '/100', $this->toneForScore((int)($eraPressure['pressureScore'] ?? 0))),
                $this->signal('Active bets', (string)$activeBets->count(), 'neutral'),
                $this->signal('Living heroes', (string)$heroes->where('is_alive', true)->count(), 'good'),
                $this->signal('World settlements', (string)$settlements->count(), 'neutral'),
            ],
            'predictions' => $predictions,
        ];
    }

    private function regionOmen(array $target, int $targetYear, array $horizon, string $seed): array
    {
        $region = Region::find($target['id']);
        if (!$region instanceof Region) {
            throw new \InvalidArgumentException('Region not found for omen.');
        }

        $settlements = Settlement::where('region_id', $region->id)->get();
        $resources = ResourceNode::where('region_id', $region->id)->get();
        $heroes = Hero::where('region_id', $region->id)->get();
        $pressureScore = $this->regionPressureScore($region);
        $weakSettlement = $settlements->sortBy(fn(Settlement $settlement): int => (int)$settlement->prosperity + (int)$settlement->defensibility)->first();
        $bestResource = $resources->sortByDesc(fn(ResourceNode $node): int => (int)$node->output)->first();
        $predictions = [
            $this->prediction(
                'regional_pressure',
                "{$region->name} trajectory",
                "{$region->name} points toward {$this->regionTrajectory($region)} by year {$targetYear}.",
                $pressureScore,
                $horizon['confidence'],
                ['relatedRegionIds' => [(string)$region->id]]
            ),
        ];

        if ($weakSettlement instanceof Settlement) {
            $predictions[] = $this->prediction(
                'settlement_survival',
                "{$weakSettlement->name} is the fragile point",
                "{$weakSettlement->name} will shape local survival pressure unless prosperity or defense improves.",
                max(0, 100 - ((int)$weakSettlement->prosperity + (int)$weakSettlement->defensibility)),
                $this->confidenceWithRoll($horizon['confidence'], $seed . ':settlement'),
                [
                    'relatedRegionIds' => [(string)$region->id],
                    'relatedSettlementIds' => [(string)$weakSettlement->id],
                ]
            );
        }

        if ($bestResource instanceof ResourceNode) {
            $predictions[] = $this->prediction(
                'resource_opportunity',
                "{$bestResource->name} anchors the region",
                "{$bestResource->name} is the clearest resource lever with output {$bestResource->output} and status {$bestResource->status}.",
                max(15, (int)$bestResource->output),
                $horizon['confidence'],
                [
                    'relatedRegionIds' => [(string)$region->id],
                    'relatedResourceIds' => [(string)$bestResource->id],
                ]
            );
        }

        if ($heroes->count() > 0) {
            $hero = $heroes->sortByDesc(fn(Hero $item): int => (int)$item->level)->first();
            if ($hero instanceof Hero) {
                $predictions[] = $this->prediction(
                    'hero_pressure',
                    "{$hero->name} can bend the local future",
                    "{$hero->name} is the strongest visible hero thread in {$region->name}, level {$hero->level}, status {$hero->status}.",
                    min(100, 35 + ((int)$hero->level * 8)),
                    'medium',
                    [
                        'relatedRegionIds' => [(string)$region->id],
                        'relatedHeroIds' => [(string)$hero->id],
                    ]
                );
            }
        }

        return [
            'lead' => $region->name,
            'signals' => [
                $this->signal('Prosperity', (string)$region->prosperity, $this->toneForInverseScore(100 - (int)$region->prosperity)),
                $this->signal('Chaos', (string)$region->chaos, $this->toneForScore((int)$region->chaos)),
                $this->signal('Danger', (string)$region->danger_level, $this->toneForScore((int)$region->danger_level)),
                $this->signal('Magic', (string)$region->magic_affinity, 'neutral'),
                $this->signal('Settlements', (string)$settlements->count(), 'neutral'),
                $this->signal('Resources', (string)$resources->count(), 'neutral'),
            ],
            'predictions' => $predictions,
        ];
    }

    private function heroOmen(array $target, int $targetYear, array $horizon, string $seed): array
    {
        $hero = Hero::find($target['id']);
        if (!$hero instanceof Hero) {
            throw new \InvalidArgumentException('Hero not found for omen.');
        }

        $region = $hero->region_id ? Region::find($hero->region_id) : null;
        $futureAge = (int)$hero->age + max(0, $targetYear - $this->currentYear());
        $milestoneLevel = $this->nextMilestone((int)$hero->level);
        $mortalityRisk = $this->heroMortalityRisk($hero, $futureAge, $region);
        $predictions = [
            $this->prediction(
                'hero_mortality',
                "{$hero->name}'s thread thins",
                "{$hero->name} will be about {$futureAge} by year {$targetYear}; age, status, and regional danger put mortality pressure at {$mortalityRisk}/100.",
                $mortalityRisk,
                $this->confidenceWithRoll($horizon['confidence'], $seed . ':mortality'),
                [
                    'relatedRegionIds' => $hero->region_id ? [(string)$hero->region_id] : [],
                    'relatedHeroIds' => [(string)$hero->id],
                ]
            ),
            $this->prediction(
                'hero_milestone',
                "{$hero->name} seeks level {$milestoneLevel}",
                "Visible momentum suggests level {$milestoneLevel} is the next major milestone for {$hero->name}.",
                min(100, 30 + ((int)$hero->level * 7)),
                $horizon['confidence'],
                [
                    'relatedRegionIds' => $hero->region_id ? [(string)$hero->region_id] : [],
                    'relatedHeroIds' => [(string)$hero->id],
                ]
            ),
        ];

        if ($region instanceof Region) {
            $predictions[] = $this->prediction(
                'regional_entanglement',
                "{$region->name} pulls on {$hero->name}",
                "{$region->name} has chaos {$region->chaos}, danger {$region->danger_level}, and magic {$region->magic_affinity}; those pressures will shape {$hero->name}'s choices.",
                $this->regionPressureScore($region),
                'medium',
                [
                    'relatedRegionIds' => [(string)$region->id],
                    'relatedHeroIds' => [(string)$hero->id],
                ]
            );
        }

        return [
            'lead' => $hero->name,
            'signals' => [
                $this->signal('Current age', (string)$hero->age, 'neutral'),
                $this->signal('Future age', (string)$futureAge, $futureAge >= 70 ? 'bad' : 'neutral'),
                $this->signal('Level', (string)$hero->level, 'good'),
                $this->signal('Status', (string)$hero->status, $hero->is_alive ? 'good' : 'bad'),
                $this->signal('Region danger', $region instanceof Region ? (string)$region->danger_level : 'Unknown', $region instanceof Region ? $this->toneForScore((int)$region->danger_level) : 'neutral'),
            ],
            'predictions' => $predictions,
        ];
    }

    private function resolveTarget(string $targetType, ?string $targetId): array
    {
        if ($targetType === 'world') {
            return [
                'type' => 'world',
                'id' => null,
                'name' => 'The World',
                'relatedRegionIds' => [],
                'relatedHeroIds' => [],
            ];
        }

        if ($targetType === 'region') {
            if (!$targetId) {
                throw new \InvalidArgumentException('A region target is required.');
            }
            $region = Region::find($targetId);
            if (!$region instanceof Region) {
                throw new \InvalidArgumentException("Region not found: {$targetId}");
            }

            return [
                'type' => 'region',
                'id' => (string)$region->id,
                'name' => (string)$region->name,
                'relatedRegionIds' => [(string)$region->id],
                'relatedHeroIds' => [],
            ];
        }

        if (!$targetId) {
            throw new \InvalidArgumentException('A hero target is required.');
        }
        $hero = Hero::find($targetId);
        if (!$hero instanceof Hero) {
            throw new \InvalidArgumentException("Hero not found: {$targetId}");
        }

        return [
            'type' => 'hero',
            'id' => (string)$hero->id,
            'name' => (string)$hero->name,
            'relatedRegionIds' => $hero->region_id ? [(string)$hero->region_id] : [],
            'relatedHeroIds' => [(string)$hero->id],
        ];
    }

    private function shouldResolveFollowUp(array $omen, int $currentYear): bool
    {
        if ((int)($omen['targetYear'] ?? PHP_INT_MAX) > $currentYear) {
            return false;
        }
        if (!empty($omen['resolutionEventId'])) {
            return false;
        }

        $followUps = is_array($omen['followUps'] ?? null) ? $omen['followUps'] : [];
        foreach ($followUps as $followUp) {
            if (is_array($followUp) && !empty($followUp['eventId'])) {
                return false;
            }
        }

        return true;
    }

    private function resolveOmenFollowUp(array $omen, int $currentYear): array
    {
        $predictedScore = $this->predictedRiskScore($omen);
        $actual = $this->actualRiskForOmen($omen, $currentYear);
        $delta = $actual['score'] - $predictedScore;
        $outcome = match (true) {
            abs($delta) <= 18 => 'fulfilled',
            $delta > 18 => 'darkened',
            default => 'averted',
        };
        $title = match ($outcome) {
            'fulfilled' => 'Temporal Omen Fulfilled',
            'darkened' => 'Temporal Omen Darkened',
            default => 'Temporal Omen Averted',
        };
        $summary = "{$title}: {$omen['summary']} Forecast risk {$predictedScore}/100; actual {$actual['label']} reads {$actual['score']}/100 in year {$currentYear}. {$actual['summary']}";
        $event = $this->eventRepository->createEvent([
            'title' => $title,
            'description' => $summary,
            'type' => 'time_omen_followup',
            'region_id' => ($omen['relatedRegionIds'] ?? [null])[0] ?? null,
            'related_region_ids' => $this->ids($omen['relatedRegionIds'] ?? []),
            'related_hero_ids' => $this->ids($omen['relatedHeroIds'] ?? []),
            'related_settlement_ids' => $this->ids($omen['relatedSettlementIds'] ?? []),
            'related_resource_ids' => $this->ids($omen['relatedResourceIds'] ?? []),
            'year' => $currentYear,
        ]);

        return [
            'id' => 'omen-followup-' . ($omen['id'] ?? bin2hex(random_bytes(4))) . '-' . $currentYear,
            'tool' => 'omen',
            'title' => $title,
            'omenId' => (string)($omen['id'] ?? ''),
            'targetType' => (string)($omen['targetType'] ?? 'world'),
            'targetId' => $omen['targetId'] ?? null,
            'targetName' => (string)($omen['targetName'] ?? 'The World'),
            'horizon' => (string)($omen['horizon'] ?? 'near'),
            'outcome' => $outcome,
            'predictedRiskScore' => $predictedScore,
            'actualRiskScore' => $actual['score'],
            'year' => $currentYear,
            'summary' => $summary,
            'eventId' => $event->id,
            'relatedRegionIds' => $this->ids($omen['relatedRegionIds'] ?? []),
            'relatedHeroIds' => $this->ids($omen['relatedHeroIds'] ?? []),
            'relatedSettlementIds' => $this->ids($omen['relatedSettlementIds'] ?? []),
            'relatedResourceIds' => $this->ids($omen['relatedResourceIds'] ?? []),
        ];
    }

    private function predictedRiskScore(array $omen): int
    {
        $predictions = is_array($omen['predictions'] ?? null) ? $omen['predictions'] : [];
        $scores = array_map(
            fn(array $prediction): int => (int)($prediction['riskScore'] ?? 0),
            array_filter($predictions, fn($prediction): bool => is_array($prediction))
        );

        return $scores === [] ? 0 : max($scores);
    }

    private function actualRiskForOmen(array $omen, int $currentYear): array
    {
        $targetType = (string)($omen['targetType'] ?? 'world');
        if ($targetType === 'region' && !empty($omen['targetId'])) {
            $region = Region::find((string)$omen['targetId']);
            if ($region instanceof Region) {
                $score = $this->regionPressureScore($region);
                return [
                    'label' => $region->name,
                    'score' => $score,
                    'summary' => "{$region->name} now has prosperity {$region->prosperity}, chaos {$region->chaos}, danger {$region->danger_level}, and status {$region->status}.",
                ];
            }
        }

        if ($targetType === 'hero' && !empty($omen['targetId'])) {
            $hero = Hero::find((string)$omen['targetId']);
            if ($hero instanceof Hero) {
                $region = $hero->region_id ? Region::find($hero->region_id) : null;
                $score = $this->heroMortalityRisk($hero, (int)$hero->age, $region instanceof Region ? $region : null);
                return [
                    'label' => $hero->name,
                    'score' => $score,
                    'summary' => "{$hero->name} is {$hero->status}, age {$hero->age}, level {$hero->level}.",
                ];
            }
        }

        $eraPressure = $this->eraPressureService->evaluate($currentYear);
        return [
            'label' => 'world pressure',
            'score' => (int)($eraPressure['pressureScore'] ?? 0),
            'summary' => (string)($eraPressure['summary'] ?? 'The broad timeline remains unclear.'),
        ];
    }

    private function calculateCost(array $target, string $horizonKey): int
    {
        $baseCost = (int)self::HORIZONS[$horizonKey]['baseCost'];
        $targetCost = match ($target['type']) {
            'world' => 8,
            'region' => 3,
            'hero' => 0,
            default => 0,
        };
        $modifier = 1.0;

        if ($target['type'] === 'region' && $target['id']) {
            $region = Region::find($target['id']);
            if ($region instanceof Region) {
                $resonance = max(0, min(100, (int)($region->divine_resonance ?? 50)));
                $modifier = max(0.85, min(1.15, 1 - (($resonance - 50) * 0.003)));
            }
        }

        return max(1, (int)round(($baseCost + $targetCost) * $modifier));
    }

    private function normalizeTargetType(string $targetType): string
    {
        $normalized = strtolower(trim($targetType));
        if (!isset(self::TARGET_TYPES[$normalized])) {
            throw new \InvalidArgumentException("Unsupported omen target type: {$targetType}");
        }

        return $normalized;
    }

    private function normalizeHorizon(string $horizon): string
    {
        $normalized = strtolower(trim($horizon));
        if (!isset(self::HORIZONS[$normalized])) {
            throw new \InvalidArgumentException("Unsupported omen horizon: {$horizon}");
        }

        return $normalized;
    }

    private function horizonYears(string $horizonKey, int $currentYear): int
    {
        $years = (int)self::HORIZONS[$horizonKey]['years'];
        if ($years > 0) {
            return $years;
        }

        $eraYear = (($currentYear - 1) % self::ERA_LENGTH_YEARS) + 1;
        return max(1, self::ERA_LENGTH_YEARS - $eraYear + 1);
    }

    private function targetOptions(): array
    {
        return array_map(
            fn(string $key, array $option): array => [
                'key' => $key,
                'label' => $option['label'],
                'summary' => $option['summary'],
            ],
            array_keys(self::TARGET_TYPES),
            self::TARGET_TYPES
        );
    }

    private function horizonOptions(): array
    {
        return array_map(
            fn(string $key, array $option): array => [
                'key' => $key,
                'label' => $option['label'],
                'years' => (int)$option['years'],
                'baseCost' => (int)$option['baseCost'],
                'confidence' => $option['confidence'],
                'summary' => $option['summary'],
            ],
            array_keys(self::HORIZONS),
            self::HORIZONS
        );
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
            'Temporal omen history and forecast records.'
        );
    }

    private function prediction(
        string $type,
        string $title,
        string $summary,
        int $riskScore,
        string $confidence,
        array $related = []
    ): array {
        $riskScore = max(0, min(100, $riskScore));

        return [
            'type' => $type,
            'title' => $title,
            'summary' => $summary,
            'riskScore' => $riskScore,
            'riskBand' => $this->riskBand($riskScore),
            'confidence' => $confidence,
            'relatedRegionIds' => array_values(array_unique($related['relatedRegionIds'] ?? [])),
            'relatedHeroIds' => array_values(array_unique($related['relatedHeroIds'] ?? [])),
            'relatedSettlementIds' => array_values(array_unique($related['relatedSettlementIds'] ?? [])),
            'relatedResourceIds' => array_values(array_unique($related['relatedResourceIds'] ?? [])),
        ];
    }

    private function signal(string $label, string $value, string $tone): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'tone' => $tone,
        ];
    }

    private function regionPressureScore(Region $region): int
    {
        return max(0, min(100, (int)round(
            ((int)$region->chaos * 0.38) +
            ((int)$region->danger_level * 0.42) +
            ((100 - (int)$region->prosperity) * 0.2)
        )));
    }

    private function regionTrajectory(Region $region): string
    {
        $score = $this->regionPressureScore($region);
        if ($score >= 70) {
            return 'crisis unless pressure eases';
        }
        if ((int)$region->prosperity >= 75 && (int)$region->chaos <= 35) {
            return 'a stronger civic footing';
        }
        if ((int)$region->magic_affinity >= 75) {
            return 'a magic-led transformation';
        }

        return 'continued instability and gradual drift';
    }

    private function heroMortalityRisk(Hero $hero, int $futureAge, ?Region $region): int
    {
        if (!$hero->is_alive || $hero->status !== 'living') {
            return 10;
        }

        $ageRisk = max(0, $futureAge - 45);
        $regionRisk = $region instanceof Region ? intdiv((int)$region->danger_level + (int)$region->chaos, 4) : 10;
        $levelBuffer = min(20, (int)$hero->level * 3);

        return max(5, min(95, 20 + $ageRisk + $regionRisk - $levelBuffer));
    }

    private function nextMilestone(int $level): int
    {
        if ($level < 5) {
            return 5;
        }
        if ($level < 10) {
            return 10;
        }

        return $level + 5;
    }

    private function confidenceWithRoll(string $baseConfidence, string $seed): string
    {
        $roll = abs(crc32($seed)) % 100;
        if ($baseConfidence === 'high' && $roll < 18) {
            return 'medium';
        }
        if ($baseConfidence === 'low' && $roll > 82) {
            return 'medium';
        }

        return $baseConfidence;
    }

    private function riskBand(int $score): string
    {
        if ($score >= 80) {
            return 'dire';
        }
        if ($score >= 60) {
            return 'dangerous';
        }
        if ($score >= 35) {
            return 'uncertain';
        }

        return 'favorable';
    }

    private function toneForScore(int $score): string
    {
        if ($score >= 70) {
            return 'bad';
        }
        if ($score >= 40) {
            return 'warn';
        }

        return 'good';
    }

    private function toneForInverseScore(int $score): string
    {
        if ($score >= 70) {
            return 'good';
        }
        if ($score >= 40) {
            return 'warn';
        }

        return 'bad';
    }

    private function omenSummary(array $target, array $horizon, int $targetYear, string $riskBand, string $lead): string
    {
        return "{$horizon['label']} omen for {$target['name']} reaches toward year {$targetYear}; {$lead} reads as {$riskBand}.";
    }

    private function collectRelatedIds(array $predictions, string $key): array
    {
        $ids = [];
        foreach ($predictions as $prediction) {
            foreach (($prediction[$key] ?? []) as $id) {
                if (is_string($id) && $id !== '') {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function mergeIds(array $base, array $extra): array
    {
        return array_values(array_unique(array_filter(array_merge($base, $extra), fn($id): bool => is_string($id) && $id !== '')));
    }

    private function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn($id): string => (string)$id, $ids),
            fn(string $id): bool => $id !== ''
        )));
    }

    private function summary(array $history): string
    {
        if (count($history) === 0) {
            return 'No temporal omens have been read.';
        }

        $latest = $history[0];
        return 'Latest temporal omen: ' . ($latest['summary'] ?? 'a future thread was read.');
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
