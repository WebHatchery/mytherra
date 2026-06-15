<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DivineBet;
use App\Models\Hero;
use App\Models\InfluenceHistory;
use App\Models\Landmark;
use App\Models\Player;
use App\Models\Region;
use App\Models\ResourceNode;
use App\Models\Settlement;

class EraPressureService
{
    private const ERA_LENGTH_YEARS = 100;

    public static function currentEraForYear(int $year): int
    {
        return intdiv(max(0, $year - 1), self::ERA_LENGTH_YEARS) + 1;
    }

    public function evaluate(int $currentYear): array
    {
        $regions = Region::all();
        $settlements = Settlement::all();
        $heroes = Hero::all();
        $landmarks = Landmark::all();
        $resources = ResourceNode::all();

        $metrics = $this->metrics($regions, $settlements, $heroes, $landmarks, $resources, $currentYear);
        $triggers = [
            $this->cataclysmTrigger($metrics),
            $this->collapseTrigger($metrics),
            $this->conquestTrigger($metrics),
            $this->magicalRuptureTrigger($metrics),
            $this->divineWarTrigger($metrics),
        ];
        usort($triggers, fn(array $left, array $right): int => $right['score'] <=> $left['score']);

        $highestTrigger = $triggers[0];
        $pressureScore = (int)$highestTrigger['score'];
        $tier = $this->tierForScore($pressureScore);
        $warnings = array_values(array_map(
            fn(array $trigger): string => "{$trigger['label']}: {$trigger['summary']}",
            array_filter($triggers, fn(array $trigger): bool => (int)$trigger['score'] >= 55)
        ));
        if ($warnings === []) {
            $warnings[] = 'No era-ending condition is close to a warning threshold.';
        }

        return [
            'currentEra' => self::currentEraForYear($currentYear),
            'currentYear' => $currentYear,
            'eraYear' => (($currentYear - 1) % self::ERA_LENGTH_YEARS) + 1,
            'eraLengthYears' => self::ERA_LENGTH_YEARS,
            'pressureScore' => $pressureScore,
            'tier' => $tier,
            'tierLabel' => $this->tierLabel($tier),
            'summary' => "Era pressure is {$this->tierLabel($tier)} at {$pressureScore}/100. Primary risk: {$highestTrigger['label']} ({$highestTrigger['score']}/100).",
            'highestTrigger' => $highestTrigger,
            'triggers' => $triggers,
            'warnings' => $warnings,
            'relatedRegionIds' => $this->collectRelatedIds($triggers, 'relatedRegionIds'),
            'relatedHeroIds' => $this->collectRelatedIds($triggers, 'relatedHeroIds'),
            'relatedSettlementIds' => $this->collectRelatedIds($triggers, 'relatedSettlementIds'),
            'relatedLandmarkIds' => $this->collectRelatedIds($triggers, 'relatedLandmarkIds'),
            'relatedResourceIds' => $this->collectRelatedIds($triggers, 'relatedResourceIds'),
        ];
    }

    public function shouldRecordEvent(array $currentPressure, ?array $previousPressure): bool
    {
        $currentScore = (int)($currentPressure['pressureScore'] ?? 0);
        if ($currentScore < 55) {
            return false;
        }

        if (!$previousPressure) {
            return true;
        }

        $previousScore = (int)($previousPressure['pressureScore'] ?? 0);
        $previousTier = (string)($previousPressure['tier'] ?? '');
        $currentTier = (string)($currentPressure['tier'] ?? '');
        $previousTrigger = (string)($previousPressure['highestTrigger']['code'] ?? '');
        $currentTrigger = (string)($currentPressure['highestTrigger']['code'] ?? '');

        return $currentTier !== $previousTier ||
            $currentTrigger !== $previousTrigger ||
            abs($currentScore - $previousScore) >= 10;
    }

    public function eventTitle(array $pressure, ?array $previousPressure): string
    {
        $tier = (string)($pressure['tier'] ?? 'watchful');
        $triggerLabel = (string)($pressure['highestTrigger']['label'] ?? 'Era Pressure');
        $previousScore = (int)($previousPressure['pressureScore'] ?? 0);
        $currentScore = (int)($pressure['pressureScore'] ?? 0);

        if ($tier === 'breaking') {
            return 'Era Breaking Point';
        }
        if ($tier === 'critical') {
            return 'Era Crisis Warning';
        }
        if ($currentScore > $previousScore) {
            return "{$triggerLabel} Pressure Rises";
        }

        return 'Era Pressure Warning';
    }

    public function eventDescription(array $pressure): string
    {
        $trigger = $pressure['highestTrigger'] ?? [];
        $signals = array_map(
            fn(array $signal): string => "{$signal['label']} {$signal['value']}",
            array_slice($trigger['signals'] ?? [], 0, 4)
        );
        $signalText = $signals === [] ? '' : ' Signals: ' . implode('; ', $signals) . '.';

        return "{$pressure['summary']} {$trigger['summary']}{$signalText}";
    }

    private function metrics($regions, $settlements, $heroes, $landmarks, $resources, int $currentYear): array
    {
        $regionCount = max(1, $regions->count());
        $settlementCount = max(1, $settlements->count());
        $heroCount = max(1, $heroes->count());
        $landmarkCount = max(1, $landmarks->count());
        $resourceCount = max(1, $resources->count());

        $dangerRegions = $regions
            ->filter(fn(Region $region): bool => (int)$region->danger_level >= 65 || in_array($region->status, ['war_torn', 'cursed'], true));
        $lowProsperityRegions = $regions
            ->filter(fn(Region $region): bool => (int)$region->prosperity <= 35 || in_array($region->status, ['declining', 'abandoned'], true));
        $warRegions = $regions
            ->filter(fn(Region $region): bool => in_array($region->status, ['war_torn', 'turbulent'], true));
        $magicalRuptureRegions = $regions
            ->filter(fn(Region $region): bool => (int)$region->magic_affinity >= 75 && ((int)$region->chaos >= 55 || in_array($region->status, ['cursed', 'mysterious'], true)));
        $martialRegions = $regions
            ->filter(fn(Region $region): bool => (string)$region->cultural_influence === 'martial');

        $distressedSettlements = $settlements
            ->filter(fn(Settlement $settlement): bool => in_array($settlement->status, ['declining', 'struggling', 'abandoned', 'ruined'], true));
        $ruinedSettlements = $settlements
            ->filter(fn(Settlement $settlement): bool => in_array($settlement->status, ['abandoned', 'ruined'], true));

        $hazardousLandmarks = $landmarks
            ->filter(fn(Landmark $landmark): bool => $landmark->isCorrupted() || $landmark->isDangerous() || $landmark->status === Landmark::STATUS_HAUNTED);
        $ruptureLandmarks = $landmarks
            ->filter(fn(Landmark $landmark): bool => ($landmark->isMagical() && $landmark->isCorrupted()) || (int)$landmark->magic_level >= 80);

        $disruptedResources = $resources
            ->filter(fn(ResourceNode $node): bool => in_array($node->status, ['contested', 'corrupted', 'depleted', 'unstable'], true));
        $depletedResources = $resources
            ->filter(fn(ResourceNode $node): bool => in_array($node->status, ['depleted', 'corrupted'], true));
        $unstableMagicalResources = $resources
            ->filter(fn(ResourceNode $node): bool => ($node->isMagical() || $node->type === 'magical_spring') && in_array($node->status, ['unstable', 'corrupted'], true));

        $livingHeroes = $heroes->filter(fn(Hero $hero): bool => (bool)$hero->is_alive);
        $warriorHeroes = $livingHeroes->filter(fn(Hero $hero): bool => (string)$hero->role === 'warrior');
        $fallenHeroes = $heroes->filter(fn(Hero $hero): bool => !(bool)$hero->is_alive || in_array($hero->status, ['deceased', 'undead'], true));
        $recentInfluence = InfluenceHistory::where('game_year', '>=', max(1, $currentYear - 10))->get();
        $activeBets = DivineBet::where('status', 'active')->get();
        $playerFavor = (int)Player::getSinglePlayer()->divine_favor;

        return [
            'avgProsperity' => (int)round($regions->avg('prosperity') ?? 0),
            'avgChaos' => (int)round($regions->avg('chaos') ?? 0),
            'avgDanger' => (int)round($regions->avg('danger_level') ?? 0),
            'avgMagic' => (int)round($regions->avg('magic_affinity') ?? 0),
            'dangerRegions' => $dangerRegions,
            'lowProsperityRegions' => $lowProsperityRegions,
            'warRegions' => $warRegions,
            'magicalRuptureRegions' => $magicalRuptureRegions,
            'martialRegions' => $martialRegions,
            'distressedSettlements' => $distressedSettlements,
            'ruinedSettlements' => $ruinedSettlements,
            'hazardousLandmarks' => $hazardousLandmarks,
            'ruptureLandmarks' => $ruptureLandmarks,
            'disruptedResources' => $disruptedResources,
            'depletedResources' => $depletedResources,
            'unstableMagicalResources' => $unstableMagicalResources,
            'livingHeroes' => $livingHeroes,
            'warriorHeroes' => $warriorHeroes,
            'fallenHeroes' => $fallenHeroes,
            'recentInfluence' => $recentInfluence,
            'activeBets' => $activeBets,
            'activeStake' => (int)$activeBets->sum('divine_favor_stake'),
            'playerFavor' => $playerFavor,
            'regionCount' => $regionCount,
            'settlementCount' => $settlementCount,
            'heroCount' => $heroCount,
            'landmarkCount' => $landmarkCount,
            'resourceCount' => $resourceCount,
        ];
    }

    private function cataclysmTrigger(array $metrics): array
    {
        $score = $this->score(
            ($metrics['avgDanger'] * 0.42) +
            ($metrics['avgChaos'] * 0.22) +
            ($this->ratio($metrics['dangerRegions']->count(), $metrics['regionCount']) * 22) +
            ($this->ratio($metrics['hazardousLandmarks']->count(), $metrics['landmarkCount']) * 10) +
            ($this->ratio($metrics['disruptedResources']->count(), $metrics['resourceCount']) * 8)
        );

        return $this->trigger(
            'cataclysm',
            'Cataclysm',
            $score,
            "{$metrics['dangerRegions']->count()} regions and {$metrics['hazardousLandmarks']->count()} landmarks carry disaster pressure.",
            [
                ['label' => 'Average danger', 'value' => (string)$metrics['avgDanger']],
                ['label' => 'Average chaos', 'value' => (string)$metrics['avgChaos']],
                ['label' => 'Crisis regions', 'value' => (string)$metrics['dangerRegions']->count()],
                ['label' => 'Hazardous landmarks', 'value' => (string)$metrics['hazardousLandmarks']->count()],
            ],
            [
                'relatedRegionIds' => $metrics['dangerRegions']->pluck('id')->take(5)->values()->all(),
                'relatedLandmarkIds' => $metrics['hazardousLandmarks']->pluck('id')->take(5)->values()->all(),
                'relatedResourceIds' => $metrics['disruptedResources']->pluck('id')->take(5)->values()->all(),
            ]
        );
    }

    private function collapseTrigger(array $metrics): array
    {
        $score = $this->score(
            ((100 - $metrics['avgProsperity']) * 0.38) +
            ($this->ratio($metrics['lowProsperityRegions']->count(), $metrics['regionCount']) * 22) +
            ($this->ratio($metrics['distressedSettlements']->count(), $metrics['settlementCount']) * 26) +
            ($this->ratio($metrics['depletedResources']->count(), $metrics['resourceCount']) * 16)
        );

        return $this->trigger(
            'collapse',
            'Collapse',
            $score,
            "{$metrics['distressedSettlements']->count()} settlements and {$metrics['depletedResources']->count()} resources signal civic failure.",
            [
                ['label' => 'Average prosperity', 'value' => (string)$metrics['avgProsperity']],
                ['label' => 'Low-prosperity regions', 'value' => (string)$metrics['lowProsperityRegions']->count()],
                ['label' => 'Distressed settlements', 'value' => (string)$metrics['distressedSettlements']->count()],
                ['label' => 'Depleted resources', 'value' => (string)$metrics['depletedResources']->count()],
            ],
            [
                'relatedRegionIds' => $metrics['lowProsperityRegions']->pluck('id')->take(5)->values()->all(),
                'relatedSettlementIds' => $metrics['distressedSettlements']->pluck('id')->take(5)->values()->all(),
                'relatedResourceIds' => $metrics['depletedResources']->pluck('id')->take(5)->values()->all(),
            ]
        );
    }

    private function conquestTrigger(array $metrics): array
    {
        $score = $this->score(
            ($metrics['avgDanger'] * 0.24) +
            ($metrics['avgChaos'] * 0.18) +
            ($this->ratio($metrics['warRegions']->count(), $metrics['regionCount']) * 28) +
            ($this->ratio($metrics['martialRegions']->count(), $metrics['regionCount']) * 12) +
            ($this->ratio($metrics['warriorHeroes']->count(), max(1, $metrics['livingHeroes']->count())) * 12)
        );

        return $this->trigger(
            'conquest',
            'Conquest',
            $score,
            "{$metrics['warRegions']->count()} regions show war pressure and {$metrics['warriorHeroes']->count()} living warriors can shape campaigns.",
            [
                ['label' => 'War-torn regions', 'value' => (string)$metrics['warRegions']->count()],
                ['label' => 'Martial cultures', 'value' => (string)$metrics['martialRegions']->count()],
                ['label' => 'Living warriors', 'value' => (string)$metrics['warriorHeroes']->count()],
                ['label' => 'Average danger', 'value' => (string)$metrics['avgDanger']],
            ],
            [
                'relatedRegionIds' => $metrics['warRegions']->merge($metrics['martialRegions'])->pluck('id')->take(5)->values()->all(),
                'relatedHeroIds' => $metrics['warriorHeroes']->pluck('id')->take(5)->values()->all(),
            ]
        );
    }

    private function magicalRuptureTrigger(array $metrics): array
    {
        $score = $this->score(
            ($metrics['avgMagic'] * 0.36) +
            ($metrics['avgChaos'] * 0.18) +
            ($this->ratio($metrics['magicalRuptureRegions']->count(), $metrics['regionCount']) * 22) +
            ($this->ratio($metrics['unstableMagicalResources']->count(), $metrics['resourceCount']) * 16) +
            ($this->ratio($metrics['ruptureLandmarks']->count(), $metrics['landmarkCount']) * 12)
        );

        return $this->trigger(
            'magical_rupture',
            'Magical Rupture',
            $score,
            "{$metrics['magicalRuptureRegions']->count()} regions, {$metrics['unstableMagicalResources']->count()} resources, and {$metrics['ruptureLandmarks']->count()} landmarks strain the magical order.",
            [
                ['label' => 'Average magic', 'value' => (string)$metrics['avgMagic']],
                ['label' => 'Average chaos', 'value' => (string)$metrics['avgChaos']],
                ['label' => 'Rupture regions', 'value' => (string)$metrics['magicalRuptureRegions']->count()],
                ['label' => 'Unstable magical resources', 'value' => (string)$metrics['unstableMagicalResources']->count()],
            ],
            [
                'relatedRegionIds' => $metrics['magicalRuptureRegions']->pluck('id')->take(5)->values()->all(),
                'relatedLandmarkIds' => $metrics['ruptureLandmarks']->pluck('id')->take(5)->values()->all(),
                'relatedResourceIds' => $metrics['unstableMagicalResources']->pluck('id')->take(5)->values()->all(),
            ]
        );
    }

    private function divineWarTrigger(array $metrics): array
    {
        $score = $this->score(
            (min(100, $metrics['activeStake']) * 0.24) +
            (min(25, $metrics['recentInfluence']->count()) * 1.6) +
            ($this->ratio($metrics['fallenHeroes']->count(), $metrics['heroCount']) * 16) +
            (max(0, 100 - $metrics['playerFavor']) * 0.14)
        );

        return $this->trigger(
            'divine_war',
            'Divine War',
            $score,
            "{$metrics['activeBets']->count()} active bets, {$metrics['recentInfluence']->count()} recent influence records, and {$metrics['fallenHeroes']->count()} fallen heroes pull the divine game toward conflict.",
            [
                ['label' => 'Active divine stake', 'value' => (string)$metrics['activeStake']],
                ['label' => 'Recent influence', 'value' => (string)$metrics['recentInfluence']->count()],
                ['label' => 'Fallen heroes', 'value' => (string)$metrics['fallenHeroes']->count()],
                ['label' => 'Divine favor reserve', 'value' => (string)$metrics['playerFavor']],
            ],
            [
                'relatedHeroIds' => $metrics['fallenHeroes']->pluck('id')->take(5)->values()->all(),
                'relatedRegionIds' => $metrics['recentInfluence']
                    ->where('target_type', 'region')
                    ->pluck('target_id')
                    ->take(5)
                    ->values()
                    ->all(),
            ]
        );
    }

    private function trigger(
        string $code,
        string $label,
        int $score,
        string $summary,
        array $signals,
        array $relatedIds
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'score' => $score,
            'tier' => $this->tierForScore($score),
            'summary' => $summary,
            'signals' => $signals,
            'relatedRegionIds' => array_values(array_unique($relatedIds['relatedRegionIds'] ?? [])),
            'relatedHeroIds' => array_values(array_unique($relatedIds['relatedHeroIds'] ?? [])),
            'relatedSettlementIds' => array_values(array_unique($relatedIds['relatedSettlementIds'] ?? [])),
            'relatedLandmarkIds' => array_values(array_unique($relatedIds['relatedLandmarkIds'] ?? [])),
            'relatedResourceIds' => array_values(array_unique($relatedIds['relatedResourceIds'] ?? [])),
        ];
    }

    private function tierForScore(int $score): string
    {
        if ($score >= 85) {
            return 'breaking';
        }
        if ($score >= 70) {
            return 'critical';
        }
        if ($score >= 55) {
            return 'dangerous';
        }
        if ($score >= 35) {
            return 'watchful';
        }

        return 'quiet';
    }

    private function tierLabel(string $tier): string
    {
        return match ($tier) {
            'breaking' => 'at a breaking point',
            'critical' => 'critical',
            'dangerous' => 'dangerous',
            'watchful' => 'watchful',
            default => 'quiet',
        };
    }

    private function ratio(int $count, int $total): float
    {
        return $total > 0 ? $count / $total : 0.0;
    }

    private function score(float $value): int
    {
        return max(0, min(100, (int)round($value)));
    }

    private function collectRelatedIds(array $triggers, string $key): array
    {
        $ids = [];
        foreach ($triggers as $trigger) {
            foreach ($trigger[$key] ?? [] as $id) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
