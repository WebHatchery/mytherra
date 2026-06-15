<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DivineBet;
use App\Models\Hero;
use App\Models\Landmark;
use App\Models\Region;
use App\Models\ResourceNode;
use App\Models\Settlement;

class EraComparisonService
{
    public function __construct(private ?GameConfigService $configService = null)
    {
        $this->configService ??= GameConfigService::getInstance();
    }

    public function current(int $currentYear): array
    {
        $history = $this->transitionHistory();
        $currentSnapshot = $this->worldSnapshot($currentYear);
        $latestComparison = $this->latestComparison($history, $currentSnapshot);

        return [
            'currentEra' => EraPressureService::currentEraForYear($currentYear),
            'currentYear' => $currentYear,
            'generatedAt' => date('c'),
            'summary' => $this->statusSummary($currentSnapshot, $latestComparison, count($history)),
            'historyCount' => count($history),
            'currentSnapshot' => $currentSnapshot,
            'latestComparison' => $latestComparison,
        ];
    }

    public function worldSnapshot(int $year): array
    {
        $regions = Region::all();
        $settlements = Settlement::all();
        $heroes = Hero::all();
        $landmarks = Landmark::all();
        $resources = ResourceNode::all();
        $bets = DivineBet::all();

        $livingHeroes = $heroes->filter(
            fn(Hero $hero): bool => (bool)($hero->is_alive ?? true) && (string)($hero->status ?? 'living') !== 'deceased'
        )->count();
        $activeBets = $bets->filter(fn(DivineBet $bet): bool => (string)$bet->status === 'active');
        $resourceEffectiveOutput = $resources->map(
            fn(ResourceNode $resource): int => $resource->getEffectiveOutput()
        );

        $snapshot = [
            'year' => $year,
            'era' => EraPressureService::currentEraForYear($year),
            'regions' => [
                'count' => $regions->count(),
                'avgProsperity' => $this->average($regions->avg('prosperity')),
                'avgChaos' => $this->average($regions->avg('chaos')),
                'avgDanger' => $this->average($regions->avg('danger_level')),
                'avgMagic' => $this->average($regions->avg('magic_affinity')),
                'totalPopulation' => (int)$regions->sum('population_total'),
                'statusDistribution' => $this->distribution($regions, 'status'),
            ],
            'settlements' => [
                'count' => $settlements->count(),
                'population' => (int)$settlements->sum('population'),
                'avgProsperity' => $this->average($settlements->avg('prosperity')),
                'avgDefensibility' => $this->average($settlements->avg('defensibility')),
                'statusDistribution' => $this->distribution($settlements, 'status'),
            ],
            'heroes' => [
                'count' => $heroes->count(),
                'living' => $livingHeroes,
                'fallen' => max(0, $heroes->count() - $livingHeroes),
                'avgLevel' => $this->average($heroes->avg('level')),
                'legendary' => $heroes->filter(fn(Hero $hero): bool => (int)($hero->level ?? 1) >= 25)->count(),
                'statusDistribution' => $this->distribution($heroes, 'status'),
            ],
            'landmarks' => [
                'count' => $landmarks->count(),
                'avgMagic' => $this->average($landmarks->avg('magic_level')),
                'avgDanger' => $this->average($landmarks->avg('danger_level')),
                'statusDistribution' => $this->distribution($landmarks, 'status'),
            ],
            'resources' => [
                'count' => $resources->count(),
                'avgOutput' => $this->average($resources->avg('output')),
                'avgEffectiveOutput' => $this->average($resourceEffectiveOutput->avg()),
                'disrupted' => $resources->filter(
                    fn(ResourceNode $resource): bool => !in_array((string)$resource->status, ['active', 'blessed', 'status-flourishing'], true)
                )->count(),
                'statusDistribution' => $this->distribution($resources, 'status'),
            ],
            'bets' => [
                'count' => $bets->count(),
                'active' => $activeBets->count(),
                'resolved' => max(0, $bets->count() - $activeBets->count()),
                'activeStake' => (int)$activeBets->sum('divine_favor_stake'),
                'statusDistribution' => $this->distribution($bets, 'status'),
            ],
        ];

        $snapshot['signals'] = $this->snapshotSignals($snapshot);

        return $snapshot;
    }

    public function compareSnapshots(array $before, array $after): array
    {
        $metrics = [
            ['key' => 'population', 'label' => 'Population', 'path' => ['settlements', 'population'], 'unit' => 'people'],
            ['key' => 'livingHeroes', 'label' => 'Living Heroes', 'path' => ['heroes', 'living'], 'unit' => 'heroes'],
            ['key' => 'avgHeroLevel', 'label' => 'Average Hero Level', 'path' => ['heroes', 'avgLevel'], 'unit' => 'levels'],
            ['key' => 'avgProsperity', 'label' => 'Regional Prosperity', 'path' => ['regions', 'avgProsperity'], 'unit' => 'score'],
            ['key' => 'avgChaos', 'label' => 'Regional Chaos', 'path' => ['regions', 'avgChaos'], 'unit' => 'score'],
            ['key' => 'avgDanger', 'label' => 'Regional Danger', 'path' => ['regions', 'avgDanger'], 'unit' => 'score'],
            ['key' => 'avgMagic', 'label' => 'Regional Magic', 'path' => ['regions', 'avgMagic'], 'unit' => 'score'],
            ['key' => 'landmarkMagic', 'label' => 'Landmark Magic', 'path' => ['landmarks', 'avgMagic'], 'unit' => 'score'],
            ['key' => 'resourceOutput', 'label' => 'Resource Output', 'path' => ['resources', 'avgEffectiveOutput'], 'unit' => 'score'],
            ['key' => 'activeBets', 'label' => 'Active Bets', 'path' => ['bets', 'active'], 'unit' => 'bets'],
        ];

        return array_map(function (array $metric) use ($before, $after): array {
            $beforeValue = $this->numericPath($before, $metric['path']);
            $afterValue = $this->numericPath($after, $metric['path']);
            $delta = round($afterValue - $beforeValue, 2);

            return [
                'key' => $metric['key'],
                'label' => $metric['label'],
                'before' => $beforeValue,
                'after' => $afterValue,
                'delta' => $delta,
                'direction' => $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat'),
                'unit' => $metric['unit'],
            ];
        }, $metrics);
    }

    private function transitionHistory(): array
    {
        $history = $this->configService->getConfig('era', 'transition_history', []);
        return is_array($history) ? array_values($history) : [];
    }

    private function latestComparison(array $history, array $currentSnapshot): ?array
    {
        foreach ($history as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $beforeSnapshot = $entry['beforeSnapshot'] ?? null;
            $afterSnapshot = $entry['afterSnapshot'] ?? null;
            if (!is_array($beforeSnapshot) || !is_array($afterSnapshot)) {
                continue;
            }

            $transitionDelta = isset($entry['transitionDelta']) && is_array($entry['transitionDelta'])
                ? $entry['transitionDelta']
                : $this->compareSnapshots($beforeSnapshot, $afterSnapshot);

            return [
                'id' => $entry['id'] ?? null,
                'completedEra' => (int)($entry['completedEra'] ?? ($beforeSnapshot['era'] ?? 0)),
                'nextEra' => (int)($entry['nextEra'] ?? ($afterSnapshot['era'] ?? 0)),
                'previousYear' => (int)($entry['previousYear'] ?? ($beforeSnapshot['year'] ?? 0)),
                'currentYear' => (int)($entry['currentYear'] ?? ($afterSnapshot['year'] ?? 0)),
                'transitionedAt' => $entry['transitionedAt'] ?? null,
                'eventId' => $entry['eventId'] ?? null,
                'summary' => $entry['comparisonSummary'] ?? $this->comparisonSummary($transitionDelta),
                'beforeSnapshot' => $beforeSnapshot,
                'afterSnapshot' => $afterSnapshot,
                'transitionDelta' => $transitionDelta,
                'currentDelta' => $this->compareSnapshots($afterSnapshot, $currentSnapshot),
            ];
        }

        return null;
    }

    public function comparisonSummary(array $delta): string
    {
        $largest = null;
        foreach ($delta as $metric) {
            if (!is_array($metric)) {
                continue;
            }

            if ($largest === null || abs((float)$metric['delta']) > abs((float)$largest['delta'])) {
                $largest = $metric;
            }
        }

        if ($largest === null || (float)$largest['delta'] === 0.0) {
            return 'The era transition left headline world metrics largely unchanged.';
        }

        $direction = (float)$largest['delta'] > 0 ? 'rose' : 'fell';
        $amount = abs((float)$largest['delta']);
        return "{$largest['label']} {$direction} by {$amount} across the era boundary.";
    }

    private function statusSummary(array $currentSnapshot, ?array $latestComparison, int $historyCount): string
    {
        if ($latestComparison === null) {
            return "Era {$currentSnapshot['era']} has no completed comparison yet; current world metrics are being tracked for the next rollover.";
        }

        return "Era comparison is tracking {$historyCount} completed rollover" .
            ($historyCount === 1 ? '' : 's') . ". {$latestComparison['summary']}";
    }

    private function snapshotSignals(array $snapshot): array
    {
        return [
            [
                'label' => 'Population',
                'value' => number_format((int)$snapshot['settlements']['population']),
            ],
            [
                'label' => 'Living Heroes',
                'value' => "{$snapshot['heroes']['living']} of {$snapshot['heroes']['count']}",
            ],
            [
                'label' => 'Regional Risk',
                'value' => $this->riskLabel(
                    ((float)$snapshot['regions']['avgChaos'] + (float)$snapshot['regions']['avgDanger']) / 2
                ),
            ],
            [
                'label' => 'Magic Climate',
                'value' => $this->magicLabel((float)$snapshot['regions']['avgMagic']),
            ],
            [
                'label' => 'Active Bets',
                'value' => (string)$snapshot['bets']['active'],
            ],
        ];
    }

    private function distribution(iterable $items, string $field): array
    {
        $distribution = [];
        foreach ($items as $item) {
            $value = trim((string)($item->{$field} ?? 'unknown'));
            if ($value === '') {
                $value = 'unknown';
            }

            $distribution[$value] = ($distribution[$value] ?? 0) + 1;
        }

        ksort($distribution);
        return $distribution;
    }

    private function numericPath(array $source, array $path): float
    {
        $value = $source;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return 0.0;
            }

            $value = $value[$key];
        }

        return round((float)$value, 2);
    }

    private function average(mixed $value): float
    {
        if ($value === null || $value === false || $value === '') {
            return 0.0;
        }

        return round((float)$value, 2);
    }

    private function riskLabel(float $score): string
    {
        if ($score >= 70) {
            return 'Severe';
        }
        if ($score >= 50) {
            return 'High';
        }
        if ($score >= 30) {
            return 'Managed';
        }

        return 'Calm';
    }

    private function magicLabel(float $score): string
    {
        if ($score >= 70) {
            return 'Surging';
        }
        if ($score >= 45) {
            return 'Active';
        }
        if ($score >= 20) {
            return 'Subtle';
        }

        return 'Dormant';
    }
}
