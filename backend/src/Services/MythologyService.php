<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameEvent;
use App\Models\GameState;
use App\Models\Hero;
use App\Models\Landmark;
use App\Models\Player;
use App\Models\Region;
use App\Repositories\EventRepository;

class MythologyService
{
    private const CONFIG_CATEGORY = 'mythology';
    private const CONFIG_KEY_STATE = 'myth_index';
    private const PROMOTION_COST = 22;
    private const MYTH_LIMIT = 24;
    private const CANDIDATE_LIMIT = 8;
    private const ECHO_COOLDOWN_YEARS = 4;
    private const ECHO_THRESHOLD = 58;
    private const ECHO_LIMIT_PER_TICK = 3;

    private ?array $stateCache = null;

    public function __construct(
        private ?GameConfigService $configService = null,
        private ?EventRepository $eventRepository = null
    ) {
        $this->configService ??= GameConfigService::getInstance();
        $this->eventRepository ??= new EventRepository();
    }

    public function status(): array
    {
        $myths = $this->loadState();
        $candidates = $this->mythCandidates($myths);

        return [
            'currentYear' => $this->currentYear(),
            'promotionCost' => self::PROMOTION_COST,
            'summary' => $this->summary($myths, $candidates),
            'evolutionSummary' => $this->evolutionSummary($myths),
            'recentEchoes' => $this->recentEchoes($myths),
            'mythTypeOptions' => $this->mythTypeOptions(),
            'myths' => array_values($myths),
            'candidates' => $candidates,
            'influenceSummary' => $this->influenceSummary($myths),
        ];
    }

    public function promote(array $payload): array
    {
        $eventId = trim((string)($payload['eventId'] ?? ''));
        if ($eventId === '') {
            throw new \InvalidArgumentException('A source event is required to promote a myth.');
        }

        $event = GameEvent::find($eventId);
        if (!$event instanceof GameEvent) {
            throw new \InvalidArgumentException("Event not found: {$eventId}");
        }

        $state = $this->loadState();
        foreach ($state as $existingMyth) {
            if (($existingMyth['sourceEventId'] ?? null) === $eventId) {
                return [
                    'success' => true,
                    'message' => 'This myth has already been promoted.',
                    'cost' => 0,
                    'remainingDivineFavor' => (int)Player::getSinglePlayer()->divine_favor,
                    'myth' => $existingMyth,
                    'status' => $this->status(),
                ];
            }
        }

        $candidate = $this->candidateFromEvent($event);
        if ($candidate['score'] < 25) {
            throw new \InvalidArgumentException('This event is not significant enough to become a myth yet.');
        }

        $player = Player::getSinglePlayer();
        if (!$player->spendDivineFavor(self::PROMOTION_COST)) {
            return [
                'success' => false,
                'message' => 'Insufficient divine favor',
                'cost' => self::PROMOTION_COST,
                'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
            ];
        }

        $effects = $this->applyMythEffects($candidate);
        $promotionEvent = $this->recordPromotionEvent($candidate, $effects);
        $myth = $this->mythRecord($candidate, $effects, (string)$promotionEvent['id']);
        array_unshift($state, $myth);
        $state = array_slice($state, 0, self::MYTH_LIMIT);
        $this->saveState($state);

        return [
            'success' => true,
            'message' => $promotionEvent['description'],
            'cost' => self::PROMOTION_COST,
            'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
            'myth' => $myth,
            'status' => $this->status(),
        ];
    }

    public function advanceWorld(int $currentYear, int $maxEchoes = self::ECHO_LIMIT_PER_TICK): array
    {
        $state = $this->loadState();
        $summary = [
            'processed' => count($state),
            'changed' => 0,
            'events' => 0,
            'echoes' => [],
            'errors' => [],
        ];

        if ($state === []) {
            return $summary;
        }

        foreach ($state as $index => $myth) {
            if ($summary['events'] >= $maxEchoes) {
                break;
            }

            try {
                $echo = $this->evolveMythIfReady($myth, $currentYear);
                if ($echo === null) {
                    continue;
                }

                $state[$index] = $echo['myth'];
                $summary['changed']++;
                $summary['events']++;
                $summary['echoes'][] = $echo['summary'];
            } catch (\Throwable $error) {
                $summary['errors'][] = [
                    'mythId' => (string)($myth['id'] ?? ''),
                    'message' => $error->getMessage(),
                ];
            }
        }

        if ($summary['changed'] > 0) {
            $this->saveState($state);
        }

        return $summary;
    }

    private function loadState(): array
    {
        if ($this->stateCache !== null) {
            return $this->stateCache;
        }

        $value = $this->configService->getConfig(self::CONFIG_CATEGORY, self::CONFIG_KEY_STATE, []);
        $state = is_array($value) ? $value : [];
        $this->stateCache = array_values(array_filter($state, fn($myth): bool => is_array($myth)));

        return $this->stateCache;
    }

    private function saveState(array $state): void
    {
        $this->stateCache = $state;
        $this->configService->setConfig(
            self::CONFIG_CATEGORY,
            self::CONFIG_KEY_STATE,
            $state,
            'array',
            'Promoted myths, legends, and remembered interventions.'
        );
    }

    private function mythCandidates(array $existingMyths): array
    {
        $promotedEventIds = array_flip(array_filter(array_map(
            fn(array $myth): ?string => isset($myth['sourceEventId']) ? (string)$myth['sourceEventId'] : null,
            $existingMyths
        )));

        return GameEvent::orderByRaw('COALESCE(year, 0) DESC')
            ->orderByDesc('timestamp')
            ->orderByDesc('created_at')
            ->take(120)
            ->get()
            ->map(fn(GameEvent $event): array => $this->candidateFromEvent($event))
            ->filter(fn(array $candidate): bool => $candidate['score'] >= 25 && !isset($promotedEventIds[$candidate['eventId']]))
            ->sortByDesc('score')
            ->take(self::CANDIDATE_LIMIT)
            ->values()
            ->all();
    }

    private function candidateFromEvent(GameEvent $event): array
    {
        $type = (string)$event->type;
        $currentYear = $this->currentYear();
        $eventYear = (int)($event->year ?? $currentYear);
        $relatedRegionIds = $this->ids($event->related_region_ids ?? []);
        $relatedHeroIds = $this->ids($event->related_hero_ids ?? []);
        $relatedLandmarkIds = $this->ids($event->related_landmark_ids ?? []);
        $relatedSettlementIds = $this->ids($event->related_settlement_ids ?? []);
        $relatedResourceIds = $this->ids($event->related_resource_ids ?? []);
        $allRelatedIds = array_filter(array_merge(
            [$event->region_id],
            $relatedRegionIds,
            $relatedHeroIds,
            $relatedLandmarkIds,
            $relatedSettlementIds,
            $relatedResourceIds
        ));

        $baseScore = match ($type) {
            'era_transition', 'era_generation', 'era_descendant' => 58,
            'era_pressure', 'time_omen' => 45,
            'magic_discovery', 'magic_progression' => 52,
            'champion_quest_completed', 'champion_rivalry_resolved', 'champion_rivalry_escalated' => 56,
            'champion_designated', 'champion_cultivated' => 46,
            'artifact_consequence', 'weather_consequence', 'time_omen_followup', 'artifact_chain', 'weather_chain', 'time_omen_chain', 'pantheon_intervention', 'pantheon_counterplay', 'pantheon_relationship_arc', 'civilization_diplomacy' => 50,
            'artifact_created', 'artifact_empowered', 'artifact_corrupted', 'artifact_stolen', 'artifact_stabilized', 'artifact_transferred' => 44,
            'weather_influence', 'divine_influence' => 40,
            'hero_level', 'hero_death' => 38,
            'bet_resolution' => 36,
            'region_tick', 'settlement_tick', 'resource_tick' => 28,
            default => 12,
        };
        $baseScore += min(18, count($allRelatedIds) * 3);
        $baseScore += max(0, 14 - max(0, $currentYear - $eventYear));
        if (str_contains(strtolower((string)$event->title), 'discovered')) {
            $baseScore += 8;
        }
        if (str_contains(strtolower((string)$event->description), 'threshold')) {
            $baseScore += 6;
        }

        $mythType = $this->mythTypeForEvent($type);
        $score = max(0, min(100, $baseScore));

        return [
            'eventId' => (string)$event->id,
            'title' => (string)$event->title,
            'summary' => (string)$event->description,
            'sourceType' => $type,
            'mythType' => $mythType,
            'mythTypeLabel' => $this->mythTypeLabel($mythType),
            'score' => $score,
            'strength' => $this->strengthLabel($score),
            'year' => $event->year !== null ? (int)$event->year : null,
            'regionId' => $event->region_id ? (string)$event->region_id : null,
            'relatedRegionIds' => $relatedRegionIds,
            'relatedHeroIds' => $relatedHeroIds,
            'relatedSettlementIds' => $relatedSettlementIds,
            'relatedLandmarkIds' => $relatedLandmarkIds,
            'relatedResourceIds' => $relatedResourceIds,
            'reason' => $this->candidateReason($event, $score, $mythType),
            'influencePreview' => $this->influencePreview($mythType, $relatedRegionIds, $relatedHeroIds, $relatedLandmarkIds, $event->region_id ? (string)$event->region_id : null),
        ];
    }

    private function applyMythEffects(array $candidate): array
    {
        $mythType = (string)$candidate['mythType'];
        $trait = 'myth_' . $mythType;
        $regionIds = array_values(array_unique(array_filter(array_merge(
            [$candidate['regionId'] ?? null],
            $candidate['relatedRegionIds'] ?? []
        ))));
        $heroIds = $this->ids($candidate['relatedHeroIds'] ?? []);
        $landmarkIds = $this->ids($candidate['relatedLandmarkIds'] ?? []);
        $effects = [
            'regions' => [],
            'heroes' => [],
            'landmarks' => [],
            'futureEventSignals' => ['mythic_memory', $trait],
        ];

        foreach ($regionIds as $regionId) {
            $region = Region::find($regionId);
            if (!$region instanceof Region) {
                continue;
            }

            $region->addTrait('mythic_memory');
            $region->addTrait($trait);
            if ($mythType === 'heroic_legend' || $mythType === 'divine_intervention') {
                $region->cultural_influence = 'mystical';
            }
            $region->save();
            $effects['regions'][] = [
                'id' => (string)$region->id,
                'name' => (string)$region->name,
                'trait' => $trait,
                'summary' => "{$region->name} now carries {$this->mythTypeLabel($mythType)} as regional memory.",
            ];
        }

        foreach ($heroIds as $heroId) {
            $hero = Hero::find($heroId);
            if (!$hero instanceof Hero) {
                continue;
            }

            $feats = $hero->feats ?? [];
            $feat = 'Mythic Reputation: ' . $candidate['title'];
            if (!in_array($feat, $feats, true)) {
                $feats[] = $feat;
                $hero->feats = $feats;
                $hero->level = min(100, (int)($hero->level ?? 1) + 1);
                $hero->save();
            }
            $effects['heroes'][] = [
                'id' => (string)$hero->id,
                'name' => (string)$hero->name,
                'summary' => "{$hero->name}'s reputation now carries this myth.",
            ];
        }

        foreach ($landmarkIds as $landmarkId) {
            $landmark = Landmark::find($landmarkId);
            if (!$landmark instanceof Landmark) {
                continue;
            }

            $traits = $landmark->traits ?? [];
            if (!in_array('mythic_anchor', $traits, true)) {
                $traits[] = 'mythic_anchor';
            }
            if (!in_array($trait, $traits, true)) {
                $traits[] = $trait;
            }
            $landmark->traits = $traits;
            $landmark->magic_level = min(100, (int)$landmark->magic_level + 2);
            $landmark->save();
            $effects['landmarks'][] = [
                'id' => (string)$landmark->id,
                'name' => (string)$landmark->name,
                'summary' => "{$landmark->name} became a mythic anchor.",
            ];
        }

        return $effects;
    }

    private function recordPromotionEvent(array $candidate, array $effects): array
    {
        $regionIds = array_values(array_unique(array_filter(array_merge(
            [$candidate['regionId'] ?? null],
            $candidate['relatedRegionIds'] ?? [],
            array_map(fn(array $region): string => (string)$region['id'], $effects['regions'] ?? [])
        ))));
        $description = "{$candidate['title']} passed from event into {$candidate['mythTypeLabel']}. " .
            $this->effectSummary($effects);

        $event = $this->eventRepository->createEvent([
            'title' => 'Myth Promoted: ' . $candidate['title'],
            'description' => $description,
            'type' => 'myth_promoted',
            'region_id' => $candidate['regionId'] ?? ($regionIds[0] ?? null),
            'related_region_ids' => $regionIds,
            'related_hero_ids' => $this->ids($candidate['relatedHeroIds'] ?? []),
            'related_settlement_ids' => $this->ids($candidate['relatedSettlementIds'] ?? []),
            'related_landmark_ids' => $this->ids($candidate['relatedLandmarkIds'] ?? []),
            'related_resource_ids' => $this->ids($candidate['relatedResourceIds'] ?? []),
            'year' => $this->currentYear(),
        ]);

        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'type' => $event->type,
            'year' => $event->year,
        ];
    }

    private function mythRecord(array $candidate, array $effects, string $promotionEventId): array
    {
        return [
            'id' => 'myth-' . bin2hex(random_bytes(6)),
            'sourceEventId' => $candidate['eventId'],
            'promotionEventId' => $promotionEventId,
            'title' => $candidate['title'],
            'summary' => $candidate['summary'],
            'mythType' => $candidate['mythType'],
            'mythTypeLabel' => $candidate['mythTypeLabel'],
            'sourceType' => $candidate['sourceType'],
            'strength' => $candidate['strength'],
            'score' => $candidate['score'],
            'sourceYear' => $candidate['year'],
            'promotedYear' => $this->currentYear(),
            'regionId' => $candidate['regionId'],
            'relatedRegionIds' => $candidate['relatedRegionIds'],
            'relatedHeroIds' => $candidate['relatedHeroIds'],
            'relatedSettlementIds' => $candidate['relatedSettlementIds'],
            'relatedLandmarkIds' => $candidate['relatedLandmarkIds'],
            'relatedResourceIds' => $candidate['relatedResourceIds'],
            'reason' => $candidate['reason'],
            'influenceSummary' => $this->effectSummary($effects),
            'effects' => $effects,
            'echoCount' => 0,
            'lastEchoYear' => null,
            'latestEcho' => null,
            'evolutionHistory' => [],
            'autonomousEvolution' => 'This myth has not echoed autonomously yet.',
        ];
    }

    private function evolveMythIfReady(array $myth, int $currentYear): ?array
    {
        $promotedYear = (int)($myth['promotedYear'] ?? $currentYear);
        $lastEchoYear = isset($myth['lastEchoYear']) && $myth['lastEchoYear'] !== null
            ? (int)$myth['lastEchoYear']
            : $promotedYear;
        if (($currentYear - $lastEchoYear) < self::ECHO_COOLDOWN_YEARS) {
            return null;
        }

        $activity = $this->recentRelatedActivity($myth, $currentYear);
        $ageBonus = min(10, max(0, (int)floor(($currentYear - $promotedYear) / 8)));
        $echoCount = (int)($myth['echoCount'] ?? 0);
        $echoFatigue = min(12, $echoCount * 3);
        $resonance = min(
            100,
            max(0, (int)($myth['score'] ?? 0)) + $ageBonus + ($activity['count'] * 5) - $echoFatigue
        );

        if ($resonance < self::ECHO_THRESHOLD) {
            return null;
        }

        $effects = $this->applyMythEchoEffects($myth, $resonance);
        $regionIds = $this->mythRegionIds($myth, $effects);
        $heroIds = $this->ids(array_merge(
            $myth['relatedHeroIds'] ?? [],
            array_map(fn(array $hero): string => (string)($hero['id'] ?? ''), $effects['heroes'] ?? [])
        ));
        $settlementIds = $this->ids($myth['relatedSettlementIds'] ?? []);
        $landmarkIds = $this->ids(array_merge(
            $myth['relatedLandmarkIds'] ?? [],
            array_map(fn(array $landmark): string => (string)($landmark['id'] ?? ''), $effects['landmarks'] ?? [])
        ));
        $resourceIds = $this->ids($myth['relatedResourceIds'] ?? []);
        $summary = $this->mythEchoSummary($myth, $resonance, $activity, $effects);

        $event = $this->eventRepository->createEvent([
            'title' => 'Myth Echo: ' . (string)($myth['title'] ?? 'Untitled Myth'),
            'description' => $summary,
            'type' => 'myth_echo',
            'region_id' => $myth['regionId'] ?? ($regionIds[0] ?? null),
            'related_region_ids' => $regionIds,
            'related_hero_ids' => $heroIds,
            'related_settlement_ids' => $settlementIds,
            'related_landmark_ids' => $landmarkIds,
            'related_resource_ids' => $resourceIds,
            'year' => $currentYear,
        ]);

        $echo = [
            'id' => 'myth-echo-' . bin2hex(random_bytes(5)),
            'year' => $currentYear,
            'eventId' => (string)$event->id,
            'resonance' => $resonance,
            'activityCount' => $activity['count'],
            'summary' => $summary,
            'effects' => $effects,
            'signals' => $activity['signals'],
        ];

        $history = is_array($myth['evolutionHistory'] ?? null) ? $myth['evolutionHistory'] : [];
        array_unshift($history, $echo);
        $myth['evolutionHistory'] = array_slice($history, 0, 6);
        $myth['latestEcho'] = $echo;
        $myth['lastEchoYear'] = $currentYear;
        $myth['echoCount'] = $echoCount + 1;
        $myth['score'] = min(100, (int)($myth['score'] ?? 0) + 2 + min(3, count($effects['regions'] ?? [])));
        $myth['strength'] = $this->strengthLabel((int)$myth['score']);
        $myth['autonomousEvolution'] = $this->autonomousEvolutionText($myth);

        return [
            'myth' => $myth,
            'summary' => [
                'id' => $echo['id'],
                'mythId' => (string)($myth['id'] ?? ''),
                'title' => (string)($myth['title'] ?? ''),
                'mythType' => (string)($myth['mythType'] ?? 'chronicle'),
                'mythTypeLabel' => (string)($myth['mythTypeLabel'] ?? $this->mythTypeLabel((string)($myth['mythType'] ?? 'chronicle'))),
                'year' => $currentYear,
                'eventId' => (string)$event->id,
                'resonance' => $resonance,
                'summary' => $summary,
                'affected' => [
                    'regions' => count($effects['regions'] ?? []),
                    'heroes' => count($effects['heroes'] ?? []),
                    'landmarks' => count($effects['landmarks'] ?? []),
                ],
            ],
        ];
    }

    private function recentRelatedActivity(array $myth, int $currentYear): array
    {
        $regionIds = $this->mythRegionIds($myth);
        $heroIds = $this->ids($myth['relatedHeroIds'] ?? []);
        $settlementIds = $this->ids($myth['relatedSettlementIds'] ?? []);
        $landmarkIds = $this->ids($myth['relatedLandmarkIds'] ?? []);
        $resourceIds = $this->ids($myth['relatedResourceIds'] ?? []);
        $fromYear = max(1, $currentYear - 8);

        if ($regionIds === [] && $heroIds === [] && $settlementIds === [] && $landmarkIds === [] && $resourceIds === []) {
            return [
                'count' => 0,
                'signals' => [],
            ];
        }

        $query = GameEvent::whereBetween('year', [$fromYear, $currentYear])
            ->where('type', '!=', 'myth_echo');

        $query->where(function ($inner) use ($regionIds, $heroIds, $settlementIds, $landmarkIds, $resourceIds) {
            foreach ($regionIds as $regionId) {
                $inner->orWhere('region_id', $regionId)
                    ->orWhereJsonContains('related_region_ids', $regionId);
            }
            foreach ($heroIds as $heroId) {
                $inner->orWhereJsonContains('related_hero_ids', $heroId);
            }
            foreach ($settlementIds as $settlementId) {
                $inner->orWhereJsonContains('related_settlement_ids', $settlementId);
            }
            foreach ($landmarkIds as $landmarkId) {
                $inner->orWhereJsonContains('related_landmark_ids', $landmarkId);
            }
            foreach ($resourceIds as $resourceId) {
                $inner->orWhereJsonContains('related_resource_ids', $resourceId);
            }
        });

        $events = $query->orderByDesc('year')->take(6)->get();

        return [
            'count' => $events->count(),
            'signals' => $events
                ->map(fn(GameEvent $event): string => (string)$event->title)
                ->filter()
                ->values()
                ->take(4)
                ->all(),
        ];
    }

    private function applyMythEchoEffects(array $myth, int $resonance): array
    {
        $mythType = (string)($myth['mythType'] ?? 'chronicle');
        $trait = 'myth_echo_' . $mythType;
        $effects = [
            'regions' => [],
            'heroes' => [],
            'landmarks' => [],
        ];

        foreach ($this->mythRegionIds($myth) as $regionId) {
            $region = Region::find($regionId);
            if (!$region instanceof Region) {
                continue;
            }

            $before = [
                'prosperity' => (int)$region->prosperity,
                'chaos' => (int)$region->chaos,
                'danger' => (int)($region->danger_level ?? 0),
                'magic' => (int)$region->magic_affinity,
            ];
            $region->addTrait('myth_echo');
            $region->addTrait($trait);
            $deltas = $this->regionEchoDeltas($mythType, $resonance);
            $region->prosperity = $this->bounded((int)$region->prosperity + $deltas['prosperity']);
            $region->chaos = $this->bounded((int)$region->chaos + $deltas['chaos']);
            $region->danger_level = $this->bounded((int)($region->danger_level ?? 0) + $deltas['danger']);
            $region->magic_affinity = $this->bounded((int)$region->magic_affinity + $deltas['magic']);
            $region->save();

            $effects['regions'][] = [
                'id' => (string)$region->id,
                'name' => (string)$region->name,
                'trait' => $trait,
                'summary' => "{$region->name} was shaped by an autonomous myth echo.",
                'before' => $before,
                'after' => [
                    'prosperity' => (int)$region->prosperity,
                    'chaos' => (int)$region->chaos,
                    'danger' => (int)$region->danger_level,
                    'magic' => (int)$region->magic_affinity,
                ],
            ];
        }

        foreach ($this->ids($myth['relatedHeroIds'] ?? []) as $heroId) {
            $hero = Hero::find($heroId);
            if (!$hero instanceof Hero || !(bool)$hero->is_alive) {
                continue;
            }

            $feat = 'Myth Echo: ' . (string)($myth['title'] ?? 'Untitled Myth');
            $feats = is_array($hero->feats ?? null) ? $hero->feats : [];
            if (!in_array($feat, $feats, true)) {
                $feats[] = $feat;
                $hero->feats = $feats;
                $hero->level = min(100, (int)$hero->level + 1);
                $hero->save();
            }

            $effects['heroes'][] = [
                'id' => (string)$hero->id,
                'name' => (string)$hero->name,
                'summary' => "{$hero->name} carries a renewed myth echo.",
            ];
        }

        foreach ($this->ids($myth['relatedLandmarkIds'] ?? []) as $landmarkId) {
            $landmark = Landmark::find($landmarkId);
            if (!$landmark instanceof Landmark) {
                continue;
            }

            $traits = is_array($landmark->traits ?? null) ? $landmark->traits : [];
            foreach (['myth_echo', $trait] as $landmarkTrait) {
                if (!in_array($landmarkTrait, $traits, true)) {
                    $traits[] = $landmarkTrait;
                }
            }
            $landmark->traits = $traits;
            $landmark->magic_level = min(100, (int)$landmark->magic_level + 1);
            $landmark->save();
            $effects['landmarks'][] = [
                'id' => (string)$landmark->id,
                'name' => (string)$landmark->name,
                'summary' => "{$landmark->name} resonates with a renewed myth echo.",
            ];
        }

        return $effects;
    }

    private function regionEchoDeltas(string $mythType, int $resonance): array
    {
        $scale = $resonance >= 82 ? 2 : 1;

        return match ($mythType) {
            'heroic_legend', 'divine_intervention', 'discovery_myth' => [
                'prosperity' => $scale,
                'chaos' => -$scale,
                'danger' => -1,
                'magic' => $scale,
            ],
            'martyr_legend', 'world_change', 'relic_myth' => [
                'prosperity' => 0,
                'chaos' => $scale,
                'danger' => $scale,
                'magic' => $scale,
            ],
            'weather_sign', 'omen_cycle', 'era_myth' => [
                'prosperity' => 0,
                'chaos' => 0,
                'danger' => 0,
                'magic' => $scale,
            ],
            default => [
                'prosperity' => 0,
                'chaos' => 0,
                'danger' => 0,
                'magic' => 1,
            ],
        };
    }

    private function mythRegionIds(array $myth, array $effects = []): array
    {
        return $this->ids(array_merge(
            [$myth['regionId'] ?? null],
            $myth['relatedRegionIds'] ?? [],
            array_map(fn(array $region): string => (string)($region['id'] ?? ''), $effects['regions'] ?? [])
        ));
    }

    private function mythEchoSummary(array $myth, int $resonance, array $activity, array $effects): string
    {
        $activityText = $activity['count'] > 0
            ? $activity['count'] . ' recent linked event(s)'
            : 'the age and strength of the myth itself';

        return (string)($myth['title'] ?? 'A promoted myth') .
            " echoed autonomously at {$resonance}/100 resonance from {$activityText}, shaping " .
            count($effects['regions'] ?? []) . ' region(s), ' .
            count($effects['heroes'] ?? []) . ' hero(es), and ' .
            count($effects['landmarks'] ?? []) . ' landmark(s).';
    }

    private function autonomousEvolutionText(array $myth): string
    {
        $echoCount = (int)($myth['echoCount'] ?? 0);
        $lastEchoYear = $myth['lastEchoYear'] ?? null;
        if ($echoCount <= 0 || $lastEchoYear === null) {
            return 'This myth has not echoed autonomously yet.';
        }

        return "This myth has echoed {$echoCount} time(s), most recently in year {$lastEchoYear}.";
    }

    private function recentEchoes(array $myths): array
    {
        $echoes = [];
        foreach ($myths as $myth) {
            foreach (($myth['evolutionHistory'] ?? []) as $echo) {
                if (is_array($echo)) {
                    $echoes[] = $echo + [
                        'mythId' => (string)($myth['id'] ?? ''),
                        'mythTitle' => (string)($myth['title'] ?? ''),
                    ];
                }
            }
        }

        usort(
            $echoes,
            fn(array $left, array $right): int => ((int)($right['year'] ?? 0)) <=> ((int)($left['year'] ?? 0))
        );

        return array_slice($echoes, 0, 6);
    }

    private function evolutionSummary(array $myths): string
    {
        $echoCount = array_sum(array_map(fn(array $myth): int => (int)($myth['echoCount'] ?? 0), $myths));
        if ($echoCount <= 0) {
            return 'No promoted myth has echoed autonomously yet.';
        }

        return count($myths) . " promoted myth(s) have produced {$echoCount} autonomous echo(es).";
    }

    private function mythTypeForEvent(string $type): string
    {
        return match ($type) {
            'hero_level', 'champion_designated', 'champion_cultivated', 'champion_quest_completed', 'champion_rivalry_resolved' => 'heroic_legend',
            'champion_rivalry_escalated' => 'world_change',
            'hero_death' => 'martyr_legend',
            'artifact_created', 'artifact_empowered', 'artifact_corrupted', 'artifact_stolen', 'artifact_stabilized', 'artifact_transferred', 'artifact_consequence', 'artifact_chain' => 'relic_myth',
            'magic_discovery', 'magic_progression' => 'discovery_myth',
            'weather_influence', 'weather_consequence', 'weather_chain' => 'weather_sign',
            'time_omen', 'time_omen_followup', 'time_omen_chain', 'era_pressure' => 'omen_cycle',
            'era_transition', 'era_generation', 'era_descendant' => 'era_myth',
            'divine_influence', 'pantheon_intervention', 'pantheon_counterplay', 'pantheon_relationship_arc' => 'divine_intervention',
            'bet_resolution' => 'divine_wager',
            'region_tick', 'settlement_tick', 'resource_tick', 'civilization_diplomacy' => 'world_change',
            default => 'chronicle',
        };
    }

    private function mythTypeLabel(string $type): string
    {
        return match ($type) {
            'heroic_legend' => 'Heroic Legend',
            'martyr_legend' => 'Martyr Legend',
            'relic_myth' => 'Relic Myth',
            'discovery_myth' => 'Discovery Myth',
            'weather_sign' => 'Weather Sign',
            'omen_cycle' => 'Omen Cycle',
            'era_myth' => 'Era Myth',
            'divine_intervention' => 'Divine Intervention',
            'divine_wager' => 'Divine Wager',
            'world_change' => 'World Change',
            default => 'Chronicle',
        };
    }

    private function mythTypeOptions(): array
    {
        return array_map(
            fn(string $key): array => ['key' => $key, 'label' => $this->mythTypeLabel($key)],
            [
                'heroic_legend',
                'martyr_legend',
                'relic_myth',
                'discovery_myth',
                'weather_sign',
                'omen_cycle',
                'era_myth',
                'divine_intervention',
                'divine_wager',
                'world_change',
                'chronicle',
            ]
        );
    }

    private function strengthLabel(int $score): string
    {
        if ($score >= 80) {
            return 'mythic';
        }
        if ($score >= 60) {
            return 'strong';
        }
        if ($score >= 40) {
            return 'forming';
        }

        return 'faint';
    }

    private function candidateReason(GameEvent $event, int $score, string $mythType): string
    {
        return "{$event->title} can become {$this->mythTypeLabel($mythType)} because its event weight is {$score}/100 and it remains linked to visible world entities.";
    }

    private function influencePreview(
        string $mythType,
        array $regionIds,
        array $heroIds,
        array $landmarkIds,
        ?string $primaryRegionId
    ): string {
        $regionCount = count(array_unique(array_filter(array_merge([$primaryRegionId], $regionIds))));
        $heroCount = count($heroIds);
        $landmarkCount = count($landmarkIds);

        return "{$this->mythTypeLabel($mythType)} would add mythic regional memory to {$regionCount} region(s), reputation to {$heroCount} hero(es), and anchor traits to {$landmarkCount} landmark(s).";
    }

    private function effectSummary(array $effects): string
    {
        return count($effects['regions'] ?? []) . ' region(s), ' .
            count($effects['heroes'] ?? []) . ' hero(es), and ' .
            count($effects['landmarks'] ?? []) . ' landmark(s) now carry the myth.';
    }

    private function influenceSummary(array $myths): string
    {
        $regions = [];
        $heroes = [];
        $landmarks = [];
        foreach ($myths as $myth) {
            foreach (($myth['effects']['regions'] ?? []) as $region) {
                $regions[$region['id'] ?? ''] = true;
            }
            foreach (($myth['effects']['heroes'] ?? []) as $hero) {
                $heroes[$hero['id'] ?? ''] = true;
            }
            foreach (($myth['effects']['landmarks'] ?? []) as $landmark) {
                $landmarks[$landmark['id'] ?? ''] = true;
            }
        }

        return count(array_filter(array_keys($regions))) . ' region(s), ' .
            count(array_filter(array_keys($heroes))) . ' hero(es), and ' .
            count(array_filter(array_keys($landmarks))) . ' landmark(s) have explicit mythic influence.';
    }

    private function summary(array $myths, array $candidates): string
    {
        return count($myths) . ' promoted myths and ' . count($candidates) . ' candidate legends are visible.';
    }

    private function ids(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn($value): string => (string)$value,
            $values
        ))));
    }

    private function bounded(int $value, int $min = 0, int $max = 100): int
    {
        return max($min, min($max, $value));
    }

    private function currentYear(): int
    {
        return (int)(GameState::getCurrent()->current_year ?? 1);
    }
}
