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
            'era_transition', 'era_generation' => 58,
            'era_pressure', 'time_omen' => 45,
            'magic_discovery' => 52,
            'champion_quest_completed', 'champion_rivalry_resolved', 'champion_rivalry_escalated' => 56,
            'champion_designated', 'champion_cultivated' => 46,
            'artifact_consequence', 'weather_consequence', 'time_omen_followup', 'pantheon_intervention', 'pantheon_counterplay' => 50,
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
        ];
    }

    private function mythTypeForEvent(string $type): string
    {
        return match ($type) {
            'hero_level', 'champion_designated', 'champion_cultivated', 'champion_quest_completed', 'champion_rivalry_resolved' => 'heroic_legend',
            'champion_rivalry_escalated' => 'world_change',
            'hero_death' => 'martyr_legend',
            'artifact_created', 'artifact_empowered', 'artifact_corrupted', 'artifact_stolen', 'artifact_stabilized', 'artifact_transferred', 'artifact_consequence' => 'relic_myth',
            'magic_discovery' => 'discovery_myth',
            'weather_influence', 'weather_consequence' => 'weather_sign',
            'time_omen', 'time_omen_followup', 'era_pressure' => 'omen_cycle',
            'era_transition', 'era_generation' => 'era_myth',
            'divine_influence', 'pantheon_intervention', 'pantheon_counterplay' => 'divine_intervention',
            'bet_resolution' => 'divine_wager',
            'region_tick', 'settlement_tick', 'resource_tick' => 'world_change',
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

    private function currentYear(): int
    {
        return (int)(GameState::getCurrent()->current_year ?? 1);
    }
}
