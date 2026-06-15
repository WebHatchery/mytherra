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

class EraLegacyService
{
    private const ERA_LENGTH_YEARS = 100;

    public function __construct(
        private ?EraPressureService $eraPressureService = null,
        private ?ChampionService $championService = null
    ) {
        $this->eraPressureService ??= new EraPressureService();
        $this->championService ??= new ChampionService();
    }

    public function evaluate(int $currentYear, ?array $eraPressure = null): array
    {
        $eraPressure ??= $this->eraPressureService->evaluate($currentYear);
        $currentEra = EraPressureService::currentEraForYear($currentYear);
        $eraEndYear = $currentEra * self::ERA_LENGTH_YEARS;
        $nextEraYear = $eraEndYear + 1;

        $regions = Region::all()->keyBy('id');
        $settlements = Settlement::all();
        $settlementsByRegion = $settlements->groupBy('region_id');
        $heroes = Hero::all();
        $landmarks = Landmark::all();
        $resources = ResourceNode::all();
        $championStatus = $this->championService->status();
        $championsByHero = [];
        foreach (($championStatus['champions'] ?? []) as $champion) {
            if (is_array($champion) && !empty($champion['heroId'])) {
                $championsByHero[(string)$champion['heroId']] = $champion;
            }
        }

        $heroLegacies = $this->heroLegacies($heroes, $regions, $championsByHero);
        $bloodlineSeeds = $this->bloodlineSeeds($heroes, $regions, $settlementsByRegion);
        $landmarkLegacies = $this->landmarkLegacies($landmarks, $regions);
        $worldScars = $this->worldScars($regions, $settlements, $landmarks, $resources);
        $carriedMyths = $this->carriedMyths($currentYear, $eraPressure);
        $eraSpanningBets = $this->eraSpanningBets($currentYear, $eraEndYear);
        $continuityScore = $this->continuityScore(
            $heroLegacies,
            $bloodlineSeeds,
            $landmarkLegacies,
            $worldScars,
            $carriedMyths,
            $eraSpanningBets,
            (int)($eraPressure['pressureScore'] ?? 0)
        );
        $readinessTier = $this->readinessTier($continuityScore);

        return [
            'currentEra' => $currentEra,
            'currentYear' => $currentYear,
            'eraYear' => (($currentYear - 1) % self::ERA_LENGTH_YEARS) + 1,
            'eraEndYear' => $eraEndYear,
            'nextEraYear' => $nextEraYear,
            'yearsUntilNextEra' => max(0, $nextEraYear - $currentYear),
            'continuityScore' => $continuityScore,
            'readinessTier' => $readinessTier,
            'readinessLabel' => $this->readinessLabel($readinessTier),
            'summary' => $this->summary($continuityScore, $readinessTier, $heroLegacies, $worldScars, $eraSpanningBets),
            'heroLegacies' => $heroLegacies,
            'bloodlineSeeds' => $bloodlineSeeds,
            'landmarkLegacies' => $landmarkLegacies,
            'worldScars' => $worldScars,
            'carriedMyths' => $carriedMyths,
            'eraSpanningBets' => $eraSpanningBets,
            'relatedRegionIds' => $this->collectRelatedIds([
                $heroLegacies,
                $bloodlineSeeds,
                $landmarkLegacies,
                $worldScars,
                $carriedMyths,
                $eraSpanningBets,
            ], 'regionId'),
            'relatedHeroIds' => $this->collectRelatedIds([$heroLegacies, $bloodlineSeeds], 'heroId', 'sourceHeroId'),
            'relatedSettlementIds' => $this->collectRelatedIds([$bloodlineSeeds, $worldScars], 'settlementId'),
            'relatedLandmarkIds' => $this->collectRelatedIds([$landmarkLegacies, $worldScars], 'landmarkId'),
            'relatedResourceIds' => $this->collectRelatedIds([$worldScars], 'resourceId'),
        ];
    }

    private function heroLegacies($heroes, $regions, array $championsByHero): array
    {
        return $heroes
            ->map(function (Hero $hero) use ($regions, $championsByHero): array {
                $featCount = count($hero->feats ?? []);
                $level = (int)($hero->level ?? 1);
                $isAlive = (bool)($hero->is_alive ?? true);
                $status = (string)($hero->status ?? ($isAlive ? 'living' : 'deceased'));
                $champion = $championsByHero[(string)$hero->id] ?? null;
                $championRank = is_array($champion) ? (int)($champion['rank'] ?? 0) : 0;
                $championBond = is_array($champion) ? (int)($champion['bond'] ?? 0) : 0;
                $championOutcomeCount = is_array($champion) && is_array($champion['outcomes'] ?? null)
                    ? count($champion['outcomes'])
                    : 0;
                $score = ($level * 3) + ($featCount * 8);
                $score += $isAlive ? 5 : 18;
                $score += match ($status) {
                    'ascended' => 28,
                    'undead' => 20,
                    'deceased' => 12,
                    default => 0,
                };
                $score += $championRank > 0 ? min(24, ($championRank * 5) + intdiv($championBond, 12) + ($championOutcomeCount * 4)) : 0;

                $region = $regions->get($hero->region_id);
                $legacyType = match (true) {
                    $championRank >= 4 || $championOutcomeCount >= 2 => 'champion_reincarnation_seed',
                    $championRank > 0 => 'champion_legend',
                    $status === 'ascended' || $level >= 25 => 'reincarnation_seed',
                    !$isAlive || $status === 'undead' => 'restless_echo',
                    $featCount >= 2 || $level >= 10 => 'ancestor_legend',
                    default => 'regional_memory',
                };
                $reason = "{$hero->name} has level {$level}, {$featCount} recorded feats, and {$status} status.";
                if (is_array($champion)) {
                    $reason .= " Champion rank {$championRank}, bond {$championBond}/100, and {$championOutcomeCount} outcome(s) make this an explicit champion legacy.";
                }
                $signals = [
                    ['label' => 'Level', 'value' => (string)$level],
                    ['label' => 'Feats', 'value' => (string)$featCount],
                    ['label' => 'Role', 'value' => (string)$hero->role],
                    ['label' => 'Status', 'value' => $status],
                ];
                if (is_array($champion)) {
                    $signals[] = ['label' => 'Champion rank', 'value' => (string)$championRank];
                    $signals[] = ['label' => 'Champion bond', 'value' => "{$championBond}/100"];
                    $signals[] = ['label' => 'Champion focus', 'value' => (string)($champion['focusLabel'] ?? 'Champion')];
                    $signals[] = ['label' => 'Champion outcomes', 'value' => (string)$championOutcomeCount];
                }

                return [
                    'score' => $score,
                    'data' => [
                        'heroId' => $hero->id,
                        'name' => $hero->name,
                        'regionId' => $hero->region_id,
                        'regionName' => $region?->name,
                        'role' => $hero->role,
                        'level' => $level,
                        'status' => $status,
                        'legacyType' => $legacyType,
                        'strength' => $this->strengthLabel($score),
                        'reason' => $reason,
                        'signals' => $signals,
                    ],
                ];
            })
            ->filter(fn(array $candidate): bool => $candidate['score'] >= 12)
            ->sortByDesc('score')
            ->take(6)
            ->map(fn(array $candidate): array => $candidate['data'])
            ->values()
            ->all();
    }

    private function bloodlineSeeds($heroes, $regions, $settlementsByRegion): array
    {
        return $heroes
            ->filter(fn(Hero $hero): bool => (bool)($hero->is_alive ?? true) || (int)($hero->level ?? 1) >= 10)
            ->map(function (Hero $hero) use ($regions, $settlementsByRegion): array {
                $region = $regions->get($hero->region_id);
                $regionalSettlements = $settlementsByRegion->get($hero->region_id, collect());
                $settlement = $regionalSettlements->sortByDesc('population')->first();
                $regionName = $region?->name ?? 'an unknown region';
                $level = (int)($hero->level ?? 1);
                $featCount = count($hero->feats ?? []);
                $score = ($level * 3) + ($featCount * 6) + (int)round(((int)($settlement?->population ?? 0)) / 1000);
                $lineageType = match ((string)$hero->role) {
                    'warrior' => 'champion_bloodline',
                    'scholar' => 'scholarly_lineage',
                    'prophet' => 'prophetic_lineage',
                    'agent of change' => 'reformer_lineage',
                    default => 'regional_lineage',
                };

                return [
                    'score' => $score,
                    'data' => [
                        'id' => "bloodline-{$hero->id}",
                        'name' => "{$hero->name} Lineage",
                        'sourceHeroId' => $hero->id,
                        'regionId' => $hero->region_id,
                        'regionName' => $region?->name,
                        'settlementId' => $settlement?->id,
                        'settlementName' => $settlement?->name,
                        'lineageType' => $lineageType,
                        'strength' => $this->strengthLabel($score),
                        'reason' => "{$hero->name}'s role and reputation could seed a {$lineageType} in {$regionName}.",
                        'signals' => [
                            ['label' => 'Source hero', 'value' => $hero->name],
                            ['label' => 'Region', 'value' => $regionName],
                            ['label' => 'Nearest civic anchor', 'value' => $settlement?->name ?? 'None'],
                        ],
                    ],
                ];
            })
            ->filter(fn(array $candidate): bool => $candidate['score'] >= 10)
            ->sortByDesc('score')
            ->take(4)
            ->map(fn(array $candidate): array => $candidate['data'])
            ->values()
            ->all();
    }

    private function landmarkLegacies($landmarks, $regions): array
    {
        return $landmarks
            ->map(function (Landmark $landmark) use ($regions): array {
                $traits = $landmark->traits ?? [];
                $magic = (int)($landmark->magic_level ?? 0);
                $danger = (int)($landmark->danger_level ?? 0);
                $score = (int)round(($magic * 0.55) + ($danger * 0.3));
                $score += $landmark->isAncient() ? 18 : 0;
                $score += $landmark->isSacred() ? 16 : 0;
                $score += $landmark->isCorrupted() ? 18 : 0;
                $score += in_array('portal', $traits, true) ? 18 : 0;
                $region = $regions->get($landmark->region_id);
                $legacyType = match (true) {
                    $landmark->isCorrupted() || $danger >= 70 => 'scarred_site',
                    $landmark->isSacred() => 'sacred_anchor',
                    $magic >= 60 || in_array('portal', $traits, true) => 'mythic_anchor',
                    $landmark->isAncient() => 'ancient_memory',
                    default => 'local_landmark',
                };

                return [
                    'score' => $score,
                    'data' => [
                        'landmarkId' => $landmark->id,
                        'name' => $landmark->name,
                        'regionId' => $landmark->region_id,
                        'regionName' => $region?->name,
                        'type' => $landmark->type,
                        'status' => $landmark->status,
                        'legacyType' => $legacyType,
                        'strength' => $this->strengthLabel($score),
                        'reason' => "{$landmark->name} carries magic {$magic}, danger {$danger}, and {$legacyType} continuity.",
                        'signals' => [
                            ['label' => 'Magic', 'value' => (string)$magic],
                            ['label' => 'Danger', 'value' => (string)$danger],
                            ['label' => 'Status', 'value' => (string)$landmark->status],
                            ['label' => 'Traits', 'value' => $traits === [] ? 'None' : implode(', ', array_slice($traits, 0, 3))],
                        ],
                    ],
                ];
            })
            ->filter(fn(array $candidate): bool => $candidate['score'] >= 20)
            ->sortByDesc('score')
            ->take(6)
            ->map(fn(array $candidate): array => $candidate['data'])
            ->values()
            ->all();
    }

    private function worldScars($regions, $settlements, $landmarks, $resources): array
    {
        $scars = [];

        foreach ($regions as $region) {
            $severity = max(
                (int)$region->danger_level,
                (int)$region->chaos,
                100 - (int)$region->prosperity
            );
            if ($severity < 55 && !in_array($region->status, ['war_torn', 'cursed', 'declining', 'abandoned'], true)) {
                continue;
            }
            $scars[] = [
                'severity' => $severity,
                'data' => [
                    'id' => "scar-region-{$region->id}",
                    'targetType' => 'region',
                    'targetId' => $region->id,
                    'targetName' => $region->name,
                    'regionId' => $region->id,
                    'scarType' => $this->regionScarType($region),
                    'severity' => $this->strengthLabel($severity),
                    'reason' => "{$region->name} carries danger {$region->danger_level}, chaos {$region->chaos}, prosperity {$region->prosperity}, and status {$region->status}.",
                ],
            ];
        }

        foreach ($settlements as $settlement) {
            if (!in_array($settlement->status, ['declining', 'struggling', 'abandoned', 'ruined'], true)) {
                continue;
            }
            $severity = max(45, 100 - (int)$settlement->prosperity, 100 - (int)$settlement->defensibility);
            $scars[] = [
                'severity' => $severity,
                'data' => [
                    'id' => "scar-settlement-{$settlement->id}",
                    'targetType' => 'settlement',
                    'targetId' => $settlement->id,
                    'targetName' => $settlement->name,
                    'settlementId' => $settlement->id,
                    'regionId' => $settlement->region_id,
                    'scarType' => 'civic_collapse',
                    'severity' => $this->strengthLabel($severity),
                    'reason' => "{$settlement->name} remains {$settlement->status} with prosperity {$settlement->prosperity} and defense {$settlement->defensibility}.",
                ],
            ];
        }

        foreach ($landmarks as $landmark) {
            if (!$landmark->isCorrupted() && !$landmark->isDangerous() && $landmark->status !== Landmark::STATUS_HAUNTED) {
                continue;
            }
            $severity = max((int)$landmark->danger_level, $landmark->isCorrupted() ? 75 : 45);
            $scars[] = [
                'severity' => $severity,
                'data' => [
                    'id' => "scar-landmark-{$landmark->id}",
                    'targetType' => 'landmark',
                    'targetId' => $landmark->id,
                    'targetName' => $landmark->name,
                    'landmarkId' => $landmark->id,
                    'regionId' => $landmark->region_id,
                    'scarType' => $landmark->isCorrupted() ? 'corruption_site' : 'haunted_place',
                    'severity' => $this->strengthLabel($severity),
                    'reason' => "{$landmark->name} may persist as a {$landmark->status} landmark with danger {$landmark->danger_level}.",
                ],
            ];
        }

        foreach ($resources as $resource) {
            if (!in_array($resource->status, ['contested', 'corrupted', 'depleted', 'unstable'], true)) {
                continue;
            }
            $severity = match ((string)$resource->status) {
                'corrupted' => 80,
                'depleted' => 70,
                'unstable' => 65,
                default => 55,
            };
            $scars[] = [
                'severity' => $severity,
                'data' => [
                    'id' => "scar-resource-{$resource->id}",
                    'targetType' => 'resource',
                    'targetId' => $resource->id,
                    'targetName' => $resource->name,
                    'resourceId' => $resource->id,
                    'settlementId' => $resource->settlement_id,
                    'regionId' => $resource->region_id,
                    'scarType' => "resource_{$resource->status}",
                    'severity' => $this->strengthLabel($severity),
                    'reason' => "{$resource->name} is {$resource->status} with output {$resource->output}.",
                ],
            ];
        }

        usort($scars, fn(array $left, array $right): int => $right['severity'] <=> $left['severity']);

        return array_values(array_map(
            fn(array $candidate): array => $candidate['data'],
            array_slice($scars, 0, 8)
        ));
    }

    private function carriedMyths(int $currentYear, array $eraPressure): array
    {
        $preferredTypes = [
            'era_pressure',
            'hero_level',
            'hero_death',
            'bet_resolution',
            'divine_influence',
            'champion_quest_completed',
            'champion_rivalry_resolved',
            'champion_rivalry_escalated',
            'artifact_consequence',
            'weather_consequence',
            'time_omen_followup',
            'pantheon_intervention',
            'pantheon_counterplay',
            'region_tick',
            'settlement_tick',
            'resource_tick',
        ];

        return GameEvent::orderBy('year', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(80)
            ->get()
            ->map(function (GameEvent $event) use ($preferredTypes, $currentYear, $eraPressure): array {
                $relatedPressureIds = array_merge(
                    $eraPressure['relatedRegionIds'] ?? [],
                    $eraPressure['relatedHeroIds'] ?? [],
                    $eraPressure['relatedSettlementIds'] ?? [],
                    $eraPressure['relatedLandmarkIds'] ?? [],
                    $eraPressure['relatedResourceIds'] ?? []
                );
                $eventRelatedIds = array_merge(
                    [$event->region_id],
                    $event->related_region_ids ?? [],
                    $event->related_hero_ids ?? [],
                    $event->related_settlement_ids ?? [],
                    $event->related_landmark_ids ?? [],
                    $event->related_resource_ids ?? []
                );
                $score = in_array($event->type, $preferredTypes, true) ? 30 : 8;
                $score += array_intersect(array_filter($eventRelatedIds), $relatedPressureIds) !== [] ? 18 : 0;
                $score += max(0, 15 - max(0, $currentYear - (int)($event->year ?? $currentYear)));

                return [
                    'score' => $score,
                    'data' => [
                        'eventId' => $event->id,
                        'title' => $event->title,
                        'type' => $event->type,
                        'year' => $event->year,
                        'regionId' => $event->region_id,
                        'mythType' => $this->mythTypeForEvent((string)$event->type),
                        'reason' => $this->carriedMythReason($event),
                        'relatedRegionIds' => $event->related_region_ids ?? [],
                        'relatedHeroIds' => $event->related_hero_ids ?? [],
                        'relatedSettlementIds' => $event->related_settlement_ids ?? [],
                        'relatedLandmarkIds' => $event->related_landmark_ids ?? [],
                        'relatedResourceIds' => $event->related_resource_ids ?? [],
                    ],
                ];
            })
            ->filter(fn(array $candidate): bool => $candidate['score'] >= 20)
            ->sortByDesc('score')
            ->take(6)
            ->map(fn(array $candidate): array => $candidate['data'])
            ->values()
            ->all();
    }

    private function eraSpanningBets(int $currentYear, int $eraEndYear): array
    {
        return DivineBet::where('status', 'active')
            ->get()
            ->map(function (DivineBet $bet) use ($currentYear, $eraEndYear): array {
                $expiresYear = (int)$bet->placed_year + (int)$bet->timeframe;
                $spansNextEra = $expiresYear > $eraEndYear;
                $score = ($spansNextEra ? 60 : 15) + min(25, (int)round((int)$bet->divine_favor_stake / 4));
                $score += match ((string)$bet->confidence) {
                    'long_shot' => 12,
                    'near_certain' => 8,
                    'likely' => 5,
                    default => 0,
                };

                return [
                    'score' => $score,
                    'data' => [
                        'betId' => $bet->id,
                        'description' => $bet->description,
                        'betType' => $bet->bet_type,
                        'targetId' => $bet->target_id,
                        'stake' => (int)$bet->divine_favor_stake,
                        'placedYear' => (int)$bet->placed_year,
                        'timeframe' => (int)$bet->timeframe,
                        'expiresYear' => $expiresYear,
                        'yearsRemaining' => max(0, $expiresYear - $currentYear),
                        'spansNextEra' => $spansNextEra,
                        'reason' => $spansNextEra
                            ? "This wager expires in year {$expiresYear}, beyond the current era boundary."
                            : "This wager has enough stake or confidence to matter to era continuity.",
                    ],
                ];
            })
            ->filter(fn(array $candidate): bool => $candidate['score'] >= 30)
            ->sortByDesc('score')
            ->take(6)
            ->map(fn(array $candidate): array => $candidate['data'])
            ->values()
            ->all();
    }

    private function continuityScore(
        array $heroLegacies,
        array $bloodlineSeeds,
        array $landmarkLegacies,
        array $worldScars,
        array $carriedMyths,
        array $eraSpanningBets,
        int $eraPressureScore
    ): int {
        $score = 0;
        $score += min(24, count($heroLegacies) * 4);
        $score += min(16, count($bloodlineSeeds) * 4);
        $score += min(18, count($landmarkLegacies) * 3);
        $score += min(16, count($worldScars) * 2);
        $score += min(18, count($carriedMyths) * 3);
        $score += min(12, count($eraSpanningBets) * 4);
        $score += (int)round($eraPressureScore * 0.12);

        return max(0, min(100, $score));
    }

    private function readinessTier(int $score): string
    {
        if ($score >= 80) {
            return 'mythic';
        }
        if ($score >= 60) {
            return 'strong';
        }
        if ($score >= 35) {
            return 'forming';
        }

        return 'thin';
    }

    private function readinessLabel(string $tier): string
    {
        return match ($tier) {
            'mythic' => 'mythic continuity',
            'strong' => 'strong continuity',
            'forming' => 'forming continuity',
            default => 'thin continuity',
        };
    }

    private function summary(
        int $score,
        string $tier,
        array $heroLegacies,
        array $worldScars,
        array $eraSpanningBets
    ): string {
        return "Era legacy is {$this->readinessLabel($tier)} at {$score}/100: " .
            count($heroLegacies) . ' hero legacies, ' .
            count($worldScars) . ' world scars, and ' .
            count($eraSpanningBets) . ' era-spanning bets are visible.';
    }

    private function strengthLabel(int $score): string
    {
        if ($score >= 80) {
            return 'mythic';
        }
        if ($score >= 60) {
            return 'strong';
        }
        if ($score >= 35) {
            return 'notable';
        }

        return 'faint';
    }

    private function regionScarType(Region $region): string
    {
        if ((int)$region->danger_level >= 70 || $region->status === 'war_torn') {
            return 'war_scar';
        }
        if ((int)$region->chaos >= 70 || $region->status === 'cursed') {
            return 'chaos_scar';
        }
        if ((int)$region->prosperity <= 35) {
            return 'collapse_scar';
        }

        return 'unsettled_memory';
    }

    private function mythTypeForEvent(string $type): string
    {
        return match ($type) {
            'era_pressure' => 'omen',
            'hero_level' => 'heroic_legend',
            'champion_quest_completed', 'champion_rivalry_resolved' => 'heroic_legend',
            'champion_rivalry_escalated' => 'rivalry_scar',
            'artifact_consequence' => 'relic_myth',
            'weather_consequence' => 'weather_sign',
            'time_omen_followup' => 'omen',
            'pantheon_intervention', 'pantheon_counterplay' => 'divine_intervention',
            'hero_death' => 'martyr_legend',
            'bet_resolution' => 'divine_wager',
            'divine_influence' => 'divine_intervention',
            'region_tick', 'settlement_tick', 'resource_tick' => 'world_change',
            default => 'chronicle',
        };
    }

    private function carriedMythReason(GameEvent $event): string
    {
        return match ((string)$event->type) {
            'artifact_consequence' => "{$event->title} is an artifact consequence with enough entity links to shape next-era relic memory.",
            'weather_consequence' => "{$event->title} is a delayed weather scar that can explain climate memory across the era boundary.",
            'time_omen_followup' => "{$event->title} resolved a temporal omen, giving era continuity an explicit prophecy thread.",
            'pantheon_intervention' => "{$event->title} is direct AI pantheon pressure that can carry divine politics into the next era.",
            'pantheon_counterplay' => "{$event->title} is direct player counterplay against AI pantheon pressure, preserving divine politics as era memory.",
            default => "{$event->title} is recent, linked, or important enough to become carried myth.",
        };
    }

    private function collectRelatedIds(array $groups, string $primaryKey, ?string $secondaryKey = null): array
    {
        $ids = [];
        foreach ($groups as $group) {
            foreach ($group as $item) {
                if (!empty($item[$primaryKey])) {
                    $ids[] = $item[$primaryKey];
                }
                if ($secondaryKey !== null && !empty($item[$secondaryKey])) {
                    $ids[] = $item[$secondaryKey];
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
