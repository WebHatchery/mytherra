<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameState;
use App\Models\Hero;
use App\Models\Landmark;
use App\Models\Region;
use App\Models\ResourceNode;
use App\Models\Settlement;
use App\Repositories\EventRepository;

class CivilizationBehaviorService
{
    private const CONFIG_CATEGORY = 'civilization';
    private const CONFIG_KEY_DECISIONS = 'behavior_decisions';
    private const HISTORY_LIMIT = 16;

    private ?array $decisionCache = null;

    public function __construct(
        private ?GameConfigService $configService = null,
        private ?EventRepository $eventRepository = null
    ) {
        $this->configService ??= GameConfigService::getInstance();
        $this->eventRepository ??= new EventRepository();
    }

    public function status(): array
    {
        $agendas = $this->regionAgendas();
        $topAgenda = $agendas[0] ?? null;
        $recentDecisions = $this->loadDecisions();

        return [
            'currentYear' => $this->currentYear(),
            'summary' => $this->summary($agendas, $recentDecisions),
            'behaviorOptions' => $this->behaviorOptions(),
            'topAgenda' => $topAgenda,
            'regionAgendas' => $agendas,
            'recentDecisions' => $recentDecisions,
            'behaviorCounts' => $this->behaviorCounts($agendas),
        ];
    }

    public function advance(array $payload = []): array
    {
        $regionId = trim((string)($payload['regionId'] ?? $payload['region_id'] ?? ''));
        $agenda = $regionId !== ''
            ? $this->agendaForRegionId($regionId)
            : ($this->regionAgendas()[0] ?? null);

        if ($agenda === null) {
            throw new \InvalidArgumentException('No civilization agenda is available.');
        }

        $decision = $this->applyAgenda($agenda, $this->currentYear(), 'player');

        return [
            'success' => true,
            'message' => $decision['effectSummary'],
            'decision' => $decision,
            'status' => $this->status(),
        ];
    }

    public function advanceWorld(int $currentYear, int $limit = 1): array
    {
        $summary = [
            'processed' => 0,
            'decisions' => [],
            'events' => 0,
            'errors' => [],
        ];

        foreach (array_slice($this->regionAgendas(), 0, max(1, $limit)) as $agenda) {
            $summary['processed']++;
            if ((int)$agenda['score'] < 35) {
                continue;
            }

            try {
                $decision = $this->applyAgenda($agenda, $currentYear, 'tick');
                $summary['decisions'][] = $decision;
                $summary['events']++;
            } catch (\Throwable $error) {
                $summary['errors'][] = [
                    'regionId' => $agenda['regionId'] ?? null,
                    'message' => $error->getMessage(),
                ];
            }
        }

        return $summary;
    }

    public function regionAgendas(): array
    {
        $agendas = [];

        foreach (Region::with(['settlements', 'heroes', 'landmarks', 'resourceNodes'])->get() as $region) {
            $agendas[] = $this->agendaForRegion($region);
        }

        usort(
            $agendas,
            fn(array $left, array $right): int => ($right['score'] <=> $left['score'])
                ?: strcmp((string)$left['regionName'], (string)$right['regionName'])
        );

        return $agendas;
    }

    private function agendaForRegionId(string $regionId): ?array
    {
        $region = Region::with(['settlements', 'heroes', 'landmarks', 'resourceNodes'])->find($regionId);
        if (!$region instanceof Region) {
            throw new \InvalidArgumentException("Region not found: {$regionId}");
        }

        return $this->agendaForRegion($region);
    }

    private function agendaForRegion(Region $region): array
    {
        $stats = $this->statsForRegion($region);
        $scores = [
            'expansion' => $this->scoreExpansion($region, $stats),
            'defense' => $this->scoreDefense($region, $stats),
            'trade' => $this->scoreTrade($region, $stats),
            'rivalry' => $this->scoreRivalry($region, $stats),
            'research' => $this->scoreResearch($region, $stats),
            'recovery' => $this->scoreRecovery($region, $stats),
        ];

        uasort(
            $scores,
            fn(array $left, array $right): int => ($right['score'] <=> $left['score'])
                ?: strcmp((string)$left['key'], (string)$right['key'])
        );
        $dominant = array_values($scores)[0];

        return [
            'regionId' => (string)$region->id,
            'regionName' => (string)$region->name,
            'dominantBehavior' => $dominant['key'],
            'dominantBehaviorLabel' => $dominant['label'],
            'score' => $dominant['score'],
            'priorityTier' => $this->priorityTier((int)$dominant['score']),
            'summary' => $this->agendaSummary($region, $dominant),
            'signals' => $dominant['signals'],
            'scores' => array_values($scores),
            'stats' => $stats['public'],
            'relatedRegionIds' => [(string)$region->id],
            'relatedSettlementIds' => $stats['settlementIds'],
            'relatedHeroIds' => $stats['heroIds'],
            'relatedLandmarkIds' => $stats['landmarkIds'],
            'relatedResourceIds' => $stats['resourceIds'],
        ];
    }

    private function statsForRegion(Region $region): array
    {
        $settlements = $region->settlements;
        $resources = $region->resourceNodes;
        $heroes = $region->heroes->filter(fn(Hero $hero): bool => (bool)$hero->is_alive);
        $landmarks = $region->landmarks;

        $settlementCount = $settlements->count();
        $resourceCount = $resources->count();
        $heroCount = $heroes->count();
        $landmarkCount = $landmarks->count();
        $avgSettlementProsperity = $settlementCount > 0
            ? (int)round((float)$settlements->avg('prosperity'))
            : 50;
        $avgSettlementDefense = $settlementCount > 0
            ? (int)round((float)$settlements->avg('defensibility'))
            : 35;
        $distressedSettlements = $settlements
            ->filter(fn(Settlement $settlement): bool => in_array((string)$settlement->status, ['declining', 'struggling', 'abandoned', 'ruined'], true));
        $ruinedSettlements = $settlements
            ->filter(fn(Settlement $settlement): bool => in_array((string)$settlement->status, ['abandoned', 'ruined'], true));
        $prosperousSettlements = $settlements
            ->filter(fn(Settlement $settlement): bool => in_array((string)$settlement->status, ['thriving', 'prosperous'], true) || (int)$settlement->prosperity >= 70);
        $disruptedResources = $resources
            ->filter(fn(ResourceNode $node): bool => in_array((string)$node->status, ['contested', 'corrupted', 'depleted', 'overworked', 'unstable'], true));
        $productiveResources = $resources
            ->filter(fn(ResourceNode $node): bool => !in_array((string)$node->status, ['corrupted', 'depleted'], true) && $this->effectiveResourceOutput($node) >= 35);
        $magicalResources = $resources
            ->filter(fn(ResourceNode $node): bool => $node->isMagical() || (string)$node->type === 'magical_spring');
        $magicalLandmarks = $landmarks
            ->filter(fn(Landmark $landmark): bool => (int)($landmark->magic_level ?? 0) >= 45 || in_array('magical', $landmark->traits ?? [], true));
        $dangerousLandmarks = $landmarks
            ->filter(fn(Landmark $landmark): bool => (int)($landmark->danger_level ?? 0) >= 45 || in_array('strategic', $landmark->traits ?? [], true));
        $sacredLandmarks = $landmarks
            ->filter(fn(Landmark $landmark): bool => (string)$landmark->type === 'temple' || (string)$landmark->status === 'blessed' || in_array('holy_site', $landmark->traits ?? [], true));
        $roleCounts = [];

        foreach ($heroes as $hero) {
            $role = (string)($hero->role ?? 'undecided');
            $roleCounts[$role] = ($roleCounts[$role] ?? 0) + 1;
        }

        $specializations = [];
        foreach ($settlements as $settlement) {
            foreach (($settlement->specializations ?? []) as $specialization) {
                $specializations[(string)$specialization] = true;
            }
        }

        return [
            'settlements' => $settlements,
            'resources' => $resources,
            'heroes' => $heroes,
            'landmarks' => $landmarks,
            'settlementIds' => $this->ids($settlements->pluck('id')->all()),
            'resourceIds' => $this->ids($resources->pluck('id')->all()),
            'heroIds' => $this->ids($heroes->pluck('id')->all()),
            'landmarkIds' => $this->ids($landmarks->pluck('id')->all()),
            'settlementCount' => $settlementCount,
            'resourceCount' => $resourceCount,
            'heroCount' => $heroCount,
            'landmarkCount' => $landmarkCount,
            'totalPopulation' => (int)$settlements->sum('population'),
            'avgSettlementProsperity' => $avgSettlementProsperity,
            'avgSettlementDefense' => $avgSettlementDefense,
            'distressedSettlements' => $distressedSettlements->count(),
            'ruinedSettlements' => $ruinedSettlements->count(),
            'prosperousSettlements' => $prosperousSettlements->count(),
            'disruptedResources' => $disruptedResources->count(),
            'productiveResources' => $productiveResources->count(),
            'magicalResources' => $magicalResources->count(),
            'resourceOutput' => (int)$resources->sum(fn(ResourceNode $node): int => $this->effectiveResourceOutput($node)),
            'magicalLandmarks' => $magicalLandmarks->count(),
            'dangerousLandmarks' => $dangerousLandmarks->count(),
            'sacredLandmarks' => $sacredLandmarks->count(),
            'roleCounts' => $roleCounts,
            'specializations' => array_keys($specializations),
            'culture' => (string)($region->cultural_influence ?? 'pastoral'),
            'public' => [
                'prosperity' => (int)$region->prosperity,
                'chaos' => (int)$region->chaos,
                'dangerLevel' => (int)$region->danger_level,
                'magicAffinity' => (int)$region->magic_affinity,
                'culture' => (string)($region->cultural_influence ?? 'pastoral'),
                'settlements' => $settlementCount,
                'resources' => $resourceCount,
                'heroes' => $heroCount,
                'landmarks' => $landmarkCount,
                'population' => (int)$settlements->sum('population'),
                'avgSettlementProsperity' => $avgSettlementProsperity,
                'avgSettlementDefense' => $avgSettlementDefense,
                'productiveResources' => $productiveResources->count(),
                'disruptedResources' => $disruptedResources->count(),
            ],
        ];
    }

    private function scoreExpansion(Region $region, array $stats): array
    {
        $score = 16
            + ((int)$region->prosperity * 0.28)
            + ($stats['avgSettlementProsperity'] * 0.18)
            + min(18, $stats['totalPopulation'] / 600)
            + ($stats['prosperousSettlements'] * 6)
            + ($stats['productiveResources'] * 3)
            - ((int)$region->chaos * 0.12)
            - ((int)$region->danger_level * 0.1);

        return $this->scoreEntry('expansion', $score, [
            $this->signal('Regional prosperity', (string)$region->prosperity, 'Stable wealth supports new civic works.'),
            $this->signal('Population base', (string)$stats['totalPopulation'], 'Larger populations push borders and settlements outward.'),
            $this->signal('Prosperous settlements', (string)$stats['prosperousSettlements'], 'Thriving towns can sponsor expansion.'),
        ]);
    }

    private function scoreDefense(Region $region, array $stats): array
    {
        $warriors = $stats['roleCounts']['warrior'] ?? 0;
        $score = 18
            + ((int)$region->danger_level * 0.42)
            + ((int)$region->chaos * 0.26)
            + max(0, 55 - $stats['avgSettlementDefense']) * 0.35
            + ($warriors * 8)
            + ($stats['dangerousLandmarks'] * 5)
            + ($stats['disruptedResources'] * 3);

        return $this->scoreEntry('defense', $score, [
            $this->signal('Danger', (string)$region->danger_level, 'Regional threat raises defensive pressure.'),
            $this->signal('Average defense', (string)$stats['avgSettlementDefense'], 'Low settlement defenses demand walls and patrols.'),
            $this->signal('Warrior heroes', (string)$warriors, 'Warriors can organize defenses.'),
        ]);
    }

    private function scoreTrade(Region $region, array $stats): array
    {
        $tradeSpecializations = count(array_intersect($stats['specializations'], ['trade', 'market', 'crafting', 'agriculture']));
        $cultureBoost = $stats['culture'] === 'mercantile' ? 18 : 0;
        $score = 12
            + ((int)$region->prosperity * 0.24)
            + ($stats['productiveResources'] * 7)
            + min(15, $stats['resourceOutput'] / 20)
            + ($tradeSpecializations * 6)
            + $cultureBoost
            - ($stats['disruptedResources'] * 3);

        return $this->scoreEntry('trade', $score, [
            $this->signal('Productive resources', (string)$stats['productiveResources'], 'Reliable output creates exchange routes.'),
            $this->signal('Resource output', (string)$stats['resourceOutput'], 'Higher output makes trade worthwhile.'),
            $this->signal('Culture', $this->formatLabel($stats['culture']), 'Mercantile culture favors markets and caravans.'),
        ]);
    }

    private function scoreRivalry(Region $region, array $stats): array
    {
        $warriors = $stats['roleCounts']['warrior'] ?? 0;
        $score = 10
            + ((int)$region->chaos * 0.42)
            + ((int)$region->danger_level * 0.28)
            + ($warriors * 7)
            + ($stats['dangerousLandmarks'] * 4)
            + ($stats['disruptedResources'] * 5)
            + ($stats['culture'] === 'martial' ? 16 : 0);

        return $this->scoreEntry('rivalry', $score, [
            $this->signal('Chaos', (string)$region->chaos, 'Unstable politics create rival power blocs.'),
            $this->signal('Disrupted resources', (string)$stats['disruptedResources'], 'Contested or corrupted resources invite conflict.'),
            $this->signal('Martial force', (string)$warriors, 'Warriors can carry rivalry into campaigns.'),
        ]);
    }

    private function scoreResearch(Region $region, array $stats): array
    {
        $scholars = ($stats['roleCounts']['scholar'] ?? 0) + ($stats['roleCounts']['prophet'] ?? 0);
        $researchSpecializations = count(array_intersect($stats['specializations'], ['magic_research', 'astrology', 'crafting']));
        $score = 12
            + ((int)$region->magic_affinity * 0.42)
            + ($stats['magicalResources'] * 8)
            + ($stats['magicalLandmarks'] * 7)
            + ($stats['sacredLandmarks'] * 3)
            + ($scholars * 8)
            + ($researchSpecializations * 5)
            + ($stats['culture'] === 'mystical' ? 14 : 0);

        return $this->scoreEntry('research', $score, [
            $this->signal('Magic affinity', (string)$region->magic_affinity, 'Ambient magic makes research more practical.'),
            $this->signal('Scholars and prophets', (string)$scholars, 'Lore-minded heroes can formalize discoveries.'),
            $this->signal('Magical sites', (string)($stats['magicalResources'] + $stats['magicalLandmarks']), 'Arcane resources and landmarks provide evidence.'),
        ]);
    }

    private function scoreRecovery(Region $region, array $stats): array
    {
        $score = 8
            + max(0, 62 - (int)$region->prosperity) * 0.55
            + max(0, (int)$region->chaos - 35) * 0.2
            + max(0, (int)$region->danger_level - 35) * 0.18
            + ($stats['distressedSettlements'] * 9)
            + ($stats['ruinedSettlements'] * 8)
            + ($stats['disruptedResources'] * 6);

        return $this->scoreEntry('recovery', $score, [
            $this->signal('Distressed settlements', (string)$stats['distressedSettlements'], 'Failing settlements pull civic focus toward repair.'),
            $this->signal('Disrupted resources', (string)$stats['disruptedResources'], 'Damaged resource nodes need restoration.'),
            $this->signal('Regional prosperity', (string)$region->prosperity, 'Low prosperity increases recovery pressure.'),
        ]);
    }

    private function scoreEntry(string $key, float $score, array $signals): array
    {
        return [
            'key' => $key,
            'label' => $this->behaviorLabel($key),
            'score' => $this->clampScore($score),
            'signals' => $signals,
        ];
    }

    private function applyAgenda(array $agenda, int $year, string $source): array
    {
        $region = Region::find((string)$agenda['regionId']);
        if (!$region instanceof Region) {
            throw new \InvalidArgumentException("Region not found: {$agenda['regionId']}");
        }

        $changes = match ((string)$agenda['dominantBehavior']) {
            'expansion' => $this->applyExpansion($region),
            'defense' => $this->applyDefense($region),
            'trade' => $this->applyTrade($region, $year),
            'rivalry' => $this->applyRivalry($region, $year),
            'research' => $this->applyResearch($region, $year),
            'recovery' => $this->applyRecovery($region),
            default => $this->emptyChanges(),
        };
        $effectSummary = $this->effectSummary($changes);
        $relatedIds = $this->relatedIdsFromChanges($changes, $agenda);
        $event = $this->eventRepository->createEvent([
            'title' => 'Civilization Agenda: ' . $agenda['dominantBehaviorLabel'],
            'description' => "{$region->name} followed a {$agenda['dominantBehaviorLabel']} agenda. {$agenda['summary']} {$effectSummary}",
            'type' => 'civilization_behavior',
            'region_id' => (string)$region->id,
            'related_region_ids' => [(string)$region->id],
            'related_hero_ids' => $relatedIds['heroes'],
            'related_settlement_ids' => $relatedIds['settlements'],
            'related_landmark_ids' => $relatedIds['landmarks'],
            'related_resource_ids' => $relatedIds['resources'],
            'year' => $year,
        ]);

        $decision = [
            'id' => 'civ-' . bin2hex(random_bytes(6)),
            'year' => $year,
            'source' => $source,
            'regionId' => (string)$region->id,
            'regionName' => (string)$region->name,
            'behavior' => (string)$agenda['dominantBehavior'],
            'behaviorLabel' => (string)$agenda['dominantBehaviorLabel'],
            'score' => (int)$agenda['score'],
            'priorityTier' => (string)$agenda['priorityTier'],
            'summary' => (string)$agenda['summary'],
            'signalSummary' => $this->signalSummary($agenda['signals'] ?? []),
            'effectSummary' => $effectSummary,
            'eventId' => (string)$event->id,
            'changes' => $changes,
            'relatedRegionIds' => [(string)$region->id],
            'relatedSettlementIds' => $relatedIds['settlements'],
            'relatedHeroIds' => $relatedIds['heroes'],
            'relatedLandmarkIds' => $relatedIds['landmarks'],
            'relatedResourceIds' => $relatedIds['resources'],
        ];

        $history = $this->loadDecisions();
        array_unshift($history, $decision);
        $this->saveDecisions(array_slice($history, 0, self::HISTORY_LIMIT));

        return $decision;
    }

    private function applyExpansion(Region $region): array
    {
        $changes = $this->emptyChanges();
        $this->changeRegion($changes, $region, function (Region $target): void {
            $target->prosperity = $this->clamp((int)$target->prosperity + 2);
            $target->chaos = $this->clamp((int)$target->chaos - 1);
            $target->addTrait('civic_expansion');
        });

        $settlement = Settlement::where('region_id', $region->id)
            ->whereNotIn('status', ['abandoned', 'ruined'])
            ->orderByDesc('prosperity')
            ->orderByDesc('population')
            ->first();

        if ($settlement instanceof Settlement) {
            $this->changeSettlement($changes, $settlement, function (Settlement $target): void {
                $growth = max(30, (int)round((int)$target->population * 0.04));
                $target->population = max(0, (int)$target->population + $growth);
                $target->prosperity = $this->clamp((int)$target->prosperity + 2);
                $target->specializations = $this->addUnique($target->specializations ?? [], 'civic_growth');
                $target->status = $this->settlementStatusFor($target);
            });
        }

        return $changes;
    }

    private function applyDefense(Region $region): array
    {
        $changes = $this->emptyChanges();
        $this->changeRegion($changes, $region, function (Region $target): void {
            $target->danger_level = $this->clamp((int)$target->danger_level - 3);
            $target->chaos = $this->clamp((int)$target->chaos - 1);
            $target->addTrait('civic_watch');
        });

        foreach (
            Settlement::where('region_id', $region->id)
                ->orderBy('defensibility')
                ->take(2)
                ->get() as $settlement
        ) {
            $this->changeSettlement($changes, $settlement, function (Settlement $target): void {
                $target->defensibility = $this->clamp((int)$target->defensibility + 5);
                $target->traits = $this->addUnique($target->traits ?? [], 'watch_posts');
                if ((int)$target->defensibility >= 70) {
                    $target->traits = $this->addUnique($target->traits ?? [], 'fortified');
                }
            });
        }

        return $changes;
    }

    private function applyTrade(Region $region, int $year): array
    {
        $changes = $this->emptyChanges();
        $this->changeRegion($changes, $region, function (Region $target) use ($year): void {
            $target->prosperity = $this->clamp((int)$target->prosperity + 2);
            $target->cultural_influence = 'mercantile';
            $target->trade_routes = $this->addUnique($target->trade_routes ?? [], 'civic-route-' . $year);
        });

        foreach (
            ResourceNode::where('region_id', $region->id)
                ->whereNotIn('status', ['corrupted', 'depleted'])
                ->orderByDesc('output')
                ->take(2)
                ->get() as $node
        ) {
            $this->changeResource($changes, $node, function (ResourceNode $target): void {
                $target->output = $this->clamp((int)$target->output + 4);
                if ((string)$target->status === 'contested') {
                    $target->status = 'active';
                }
            });
        }

        $settlement = Settlement::where('region_id', $region->id)
            ->orderByDesc('prosperity')
            ->first();
        if ($settlement instanceof Settlement) {
            $this->changeSettlement($changes, $settlement, function (Settlement $target): void {
                $target->prosperity = $this->clamp((int)$target->prosperity + 2);
                $target->specializations = $this->addUnique($target->specializations ?? [], 'trade');
                $target->status = $this->settlementStatusFor($target);
            });
        }

        return $changes;
    }

    private function applyRivalry(Region $region, int $year): array
    {
        $changes = $this->emptyChanges();
        $this->changeRegion($changes, $region, function (Region $target): void {
            $target->chaos = $this->clamp((int)$target->chaos + 3);
            $target->danger_level = $this->clamp((int)$target->danger_level + 2);
            $target->cultural_influence = 'martial';
            $target->addTrait('rival_power_blocs');
        });

        $hero = Hero::where('region_id', $region->id)
            ->where('is_alive', true)
            ->whereIn('role', ['warrior', 'agent of change'])
            ->orderByDesc('level')
            ->first();
        if ($hero instanceof Hero) {
            $this->changeHero($changes, $hero, function (Hero $target) use ($year): void {
                $target->level = min(100, (int)$target->level + 1);
                $target->feats = $this->addUnique($target->feats ?? [], "Carried a civic rivalry in year {$year}");
            });
        }

        $settlement = Settlement::where('region_id', $region->id)
            ->orderByDesc('population')
            ->first();
        if ($settlement instanceof Settlement) {
            $this->changeSettlement($changes, $settlement, function (Settlement $target): void {
                $target->defensibility = $this->clamp((int)$target->defensibility + 2);
                $target->prosperity = $this->clamp((int)$target->prosperity - 1);
            });
        }

        return $changes;
    }

    private function applyResearch(Region $region, int $year): array
    {
        $changes = $this->emptyChanges();
        $this->changeRegion($changes, $region, function (Region $target): void {
            $target->magic_affinity = $this->clamp((int)$target->magic_affinity + 3);
            $target->cultural_influence = 'mystical';
            $target->addTrait('civic_research');
        });

        foreach (
            Hero::where('region_id', $region->id)
                ->where('is_alive', true)
                ->whereIn('role', ['scholar', 'prophet'])
                ->orderByDesc('level')
                ->take(2)
                ->get() as $hero
        ) {
            $this->changeHero($changes, $hero, function (Hero $target) use ($year): void {
                $target->level = min(100, (int)$target->level + 1);
                $target->feats = $this->addUnique($target->feats ?? [], "Advanced civic research in year {$year}");
            });
        }

        $landmark = Landmark::where('region_id', $region->id)
            ->orderByDesc('magic_level')
            ->first();
        if ($landmark instanceof Landmark) {
            $this->changeLandmark($changes, $landmark, function (Landmark $target): void {
                $target->magic_level = $this->clamp((int)$target->magic_level + 3);
                $target->traits = $this->addUnique($target->traits ?? [], 'research_anchor');
            });
        }

        $settlement = Settlement::where('region_id', $region->id)
            ->orderByDesc('prosperity')
            ->first();
        if ($settlement instanceof Settlement) {
            $this->changeSettlement($changes, $settlement, function (Settlement $target): void {
                $target->specializations = $this->addUnique($target->specializations ?? [], 'magic_research');
            });
        }

        return $changes;
    }

    private function applyRecovery(Region $region): array
    {
        $changes = $this->emptyChanges();
        $this->changeRegion($changes, $region, function (Region $target): void {
            $target->prosperity = $this->clamp((int)$target->prosperity + 3);
            $target->chaos = $this->clamp((int)$target->chaos - 2);
            $target->danger_level = $this->clamp((int)$target->danger_level - 2);
            $target->addTrait('civic_recovery');
        });

        foreach (
            Settlement::where('region_id', $region->id)
                ->whereIn('status', ['declining', 'struggling', 'abandoned', 'ruined'])
                ->orderBy('prosperity')
                ->take(2)
                ->get() as $settlement
        ) {
            $this->changeSettlement($changes, $settlement, function (Settlement $target): void {
                $target->population = max((int)$target->population + 25, (int)$target->population);
                $target->prosperity = $this->clamp((int)$target->prosperity + 5);
                $target->status = $this->settlementStatusFor($target);
                $target->traits = $this->addUnique($target->traits ?? [], 'rebuilding');
            });
        }

        foreach (
            ResourceNode::where('region_id', $region->id)
                ->whereIn('status', ['contested', 'corrupted', 'depleted', 'overworked', 'unstable'])
                ->orderBy('output')
                ->take(2)
                ->get() as $node
        ) {
            $this->changeResource($changes, $node, function (ResourceNode $target): void {
                $target->output = $this->clamp((int)$target->output + 4);
                $target->status = in_array((string)$target->status, ['corrupted', 'depleted'], true)
                    ? 'unstable'
                    : 'active';
            });
        }

        return $changes;
    }

    private function changeRegion(array &$changes, Region $region, callable $mutate): void
    {
        $before = $this->regionSnapshot($region);
        $mutate($region);
        $region->save();
        $after = $this->regionSnapshot($region->fresh() ?? $region);
        $this->appendChange($changes, 'regions', $before, $after);
    }

    private function changeSettlement(array &$changes, Settlement $settlement, callable $mutate): void
    {
        $before = $this->settlementSnapshot($settlement);
        $mutate($settlement);
        $settlement->save();
        $after = $this->settlementSnapshot($settlement->fresh() ?? $settlement);
        $this->appendChange($changes, 'settlements', $before, $after);
    }

    private function changeResource(array &$changes, ResourceNode $node, callable $mutate): void
    {
        $before = $this->resourceSnapshot($node);
        $mutate($node);
        $node->save();
        $after = $this->resourceSnapshot($node->fresh() ?? $node);
        $this->appendChange($changes, 'resources', $before, $after);
    }

    private function changeHero(array &$changes, Hero $hero, callable $mutate): void
    {
        $before = $this->heroSnapshot($hero);
        $mutate($hero);
        $hero->save();
        $after = $this->heroSnapshot($hero->fresh() ?? $hero);
        $this->appendChange($changes, 'heroes', $before, $after);
    }

    private function changeLandmark(array &$changes, Landmark $landmark, callable $mutate): void
    {
        $before = $this->landmarkSnapshot($landmark);
        $mutate($landmark);
        $landmark->save();
        $after = $this->landmarkSnapshot($landmark->fresh() ?? $landmark);
        $this->appendChange($changes, 'landmarks', $before, $after);
    }

    private function appendChange(array &$changes, string $type, array $before, array $after): void
    {
        if ($before === $after) {
            return;
        }

        $changes[$type][] = [
            'id' => $after['id'],
            'name' => $after['name'],
            'before' => $before,
            'after' => $after,
            'summary' => $this->changeSummary($type, $before, $after),
        ];
    }

    private function changeSummary(string $type, array $before, array $after): string
    {
        $labels = [
            'regions' => ['prosperity', 'chaos', 'dangerLevel', 'magicAffinity', 'culture'],
            'settlements' => ['population', 'prosperity', 'defensibility', 'status'],
            'resources' => ['output', 'status', 'effectiveOutput'],
            'heroes' => ['level', 'role', 'status'],
            'landmarks' => ['magicLevel', 'dangerLevel', 'status'],
        ];
        $parts = [];

        foreach ($labels[$type] ?? [] as $key) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $parts[] = "{$key} {$before[$key]}->{$after[$key]}";
            }
        }

        return $after['name'] . ': ' . ($parts === [] ? 'metadata changed' : implode(', ', $parts)) . '.';
    }

    private function regionSnapshot(Region $region): array
    {
        return [
            'id' => (string)$region->id,
            'name' => (string)$region->name,
            'prosperity' => (int)$region->prosperity,
            'chaos' => (int)$region->chaos,
            'dangerLevel' => (int)$region->danger_level,
            'magicAffinity' => (int)$region->magic_affinity,
            'culture' => (string)$region->cultural_influence,
            'status' => (string)$region->status,
            'traits' => $region->regional_traits ?? [],
        ];
    }

    private function settlementSnapshot(Settlement $settlement): array
    {
        return [
            'id' => (string)$settlement->id,
            'name' => (string)$settlement->name,
            'regionId' => (string)$settlement->region_id,
            'population' => (int)$settlement->population,
            'prosperity' => (int)$settlement->prosperity,
            'defensibility' => (int)$settlement->defensibility,
            'status' => (string)$settlement->status,
            'specializations' => $settlement->specializations ?? [],
            'traits' => $settlement->traits ?? [],
        ];
    }

    private function resourceSnapshot(ResourceNode $node): array
    {
        return [
            'id' => (string)$node->id,
            'name' => (string)$node->name,
            'regionId' => (string)$node->region_id,
            'settlementId' => $node->settlement_id ? (string)$node->settlement_id : null,
            'type' => (string)$node->type,
            'output' => (int)$node->output,
            'effectiveOutput' => $this->effectiveResourceOutput($node),
            'status' => (string)$node->status,
        ];
    }

    private function heroSnapshot(Hero $hero): array
    {
        return [
            'id' => (string)$hero->id,
            'name' => (string)$hero->name,
            'regionId' => (string)$hero->region_id,
            'role' => (string)$hero->role,
            'level' => (int)$hero->level,
            'status' => (string)$hero->status,
            'feats' => $hero->feats ?? [],
        ];
    }

    private function landmarkSnapshot(Landmark $landmark): array
    {
        return [
            'id' => (string)$landmark->id,
            'name' => (string)$landmark->name,
            'regionId' => (string)$landmark->region_id,
            'type' => (string)$landmark->type,
            'magicLevel' => (int)$landmark->magic_level,
            'dangerLevel' => (int)$landmark->danger_level,
            'status' => (string)$landmark->status,
            'traits' => $landmark->traits ?? [],
        ];
    }

    private function relatedIdsFromChanges(array $changes, array $agenda): array
    {
        return [
            'settlements' => $this->ids(array_merge(
                $agenda['relatedSettlementIds'] ?? [],
                array_map(fn(array $change): string => (string)$change['id'], $changes['settlements'])
            )),
            'heroes' => $this->ids(array_merge(
                $agenda['relatedHeroIds'] ?? [],
                array_map(fn(array $change): string => (string)$change['id'], $changes['heroes'])
            )),
            'landmarks' => $this->ids(array_merge(
                $agenda['relatedLandmarkIds'] ?? [],
                array_map(fn(array $change): string => (string)$change['id'], $changes['landmarks'])
            )),
            'resources' => $this->ids(array_merge(
                $agenda['relatedResourceIds'] ?? [],
                array_map(fn(array $change): string => (string)$change['id'], $changes['resources'])
            )),
        ];
    }

    private function emptyChanges(): array
    {
        return [
            'regions' => [],
            'settlements' => [],
            'resources' => [],
            'heroes' => [],
            'landmarks' => [],
        ];
    }

    private function effectSummary(array $changes): string
    {
        $labels = [
            'regions' => 'region',
            'settlements' => 'settlement',
            'resources' => 'resource',
            'heroes' => 'hero',
            'landmarks' => 'landmark',
        ];
        $parts = [];

        foreach ($labels as $key => $label) {
            $count = count($changes[$key] ?? []);
            if ($count > 0) {
                $parts[] = "{$count} {$label}" . ($count === 1 ? '' : 's');
            }
        }

        return $parts === []
            ? 'No durable stat change was needed.'
            : implode(', ', $parts) . ' changed through the civic agenda.';
    }

    private function summary(array $agendas, array $recentDecisions): string
    {
        if ($agendas === []) {
            return 'No regions are available for civilization behavior.';
        }

        $top = $agendas[0];

        return count($agendas) . " regional agendas are visible; {$top['regionName']} currently leads with {$top['dominantBehaviorLabel']} at {$top['score']}/100. " .
            count($recentDecisions) . ' recent decisions are recorded.';
    }

    private function behaviorCounts(array $agendas): array
    {
        $counts = array_fill_keys(array_keys($this->behaviorOptionMap()), 0);
        foreach ($agendas as $agenda) {
            $key = (string)($agenda['dominantBehavior'] ?? '');
            if (isset($counts[$key])) {
                $counts[$key]++;
            }
        }

        return $counts;
    }

    private function behaviorOptions(): array
    {
        return array_map(
            fn(string $key, array $option): array => [
                'key' => $key,
                'label' => $option['label'],
                'summary' => $option['summary'],
            ],
            array_keys($this->behaviorOptionMap()),
            array_values($this->behaviorOptionMap())
        );
    }

    private function behaviorOptionMap(): array
    {
        return [
            'expansion' => [
                'label' => 'Expansion',
                'summary' => 'Prosperous settlements push population, routes, and civic reach outward.',
            ],
            'defense' => [
                'label' => 'Defense',
                'summary' => 'Danger, weak defenses, warriors, and strategic sites create patrol and fortification pressure.',
            ],
            'trade' => [
                'label' => 'Trade',
                'summary' => 'Productive resources and mercantile culture turn into routes, output, and market specialization.',
            ],
            'rivalry' => [
                'label' => 'Rivalry',
                'summary' => 'Chaos, contested resources, martial culture, and warriors harden local power blocs.',
            ],
            'research' => [
                'label' => 'Research',
                'summary' => 'Magic, scholars, prophets, and arcane sites produce civic research pressure.',
            ],
            'recovery' => [
                'label' => 'Recovery',
                'summary' => 'Distressed settlements and damaged resources pull regions toward repair.',
            ],
        ];
    }

    private function behaviorLabel(string $behavior): string
    {
        return $this->behaviorOptionMap()[$behavior]['label'] ?? $this->formatLabel($behavior);
    }

    private function agendaSummary(Region $region, array $dominant): string
    {
        $signalSummaries = array_slice(array_map(
            fn(array $signal): string => $signal['summary'],
            $dominant['signals']
        ), 0, 2);

        return "{$region->name} leans toward {$dominant['label']} because " .
            strtolower(implode(' ', $signalSummaries));
    }

    private function signal(string $label, string $value, string $summary): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'summary' => $summary,
        ];
    }

    private function signalSummary(array $signals): string
    {
        return implode('; ', array_map(
            fn(array $signal): string => "{$signal['label']}: {$signal['value']}",
            array_slice($signals, 0, 3)
        ));
    }

    private function priorityTier(int $score): string
    {
        if ($score >= 75) {
            return 'urgent';
        }
        if ($score >= 55) {
            return 'active';
        }
        if ($score >= 35) {
            return 'watch';
        }

        return 'quiet';
    }

    private function settlementStatusFor(Settlement $settlement): string
    {
        $population = (int)$settlement->population;
        $prosperity = (int)$settlement->prosperity;

        if ($population <= 0) {
            return 'abandoned';
        }
        if ($population < 50 && $prosperity < 20) {
            return 'ruined';
        }
        if ($prosperity >= 82 && $population >= 500) {
            return 'thriving';
        }
        if ($prosperity >= 68) {
            return 'prosperous';
        }
        if ($prosperity <= 24) {
            return 'declining';
        }
        if ($prosperity <= 38) {
            return 'struggling';
        }

        return 'stable';
    }

    private function addUnique(array $values, string $value): array
    {
        if (!in_array($value, $values, true)) {
            $values[] = $value;
        }

        return array_values(array_unique(array_map(fn($item): string => (string)$item, $values)));
    }

    private function effectiveResourceOutput(ResourceNode $node): int
    {
        try {
            return (int)$node->getEffectiveOutput();
        } catch (\Throwable) {
            return (int)$node->output;
        }
    }

    private function clamp(int $value): int
    {
        return max(0, min(100, $value));
    }

    private function clampScore(float $value): int
    {
        return max(0, min(100, (int)round($value)));
    }

    private function formatLabel(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }

    private function ids(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn($value): string => (string)$value,
            $values
        ))));
    }

    private function loadDecisions(): array
    {
        if ($this->decisionCache !== null) {
            return $this->decisionCache;
        }

        $value = $this->configService->getConfig(self::CONFIG_CATEGORY, self::CONFIG_KEY_DECISIONS, []);
        $this->decisionCache = is_array($value)
            ? array_values(array_filter($value, fn($decision): bool => is_array($decision)))
            : [];

        return $this->decisionCache;
    }

    private function saveDecisions(array $decisions): void
    {
        $this->decisionCache = $decisions;
        $this->configService->setConfig(
            self::CONFIG_CATEGORY,
            self::CONFIG_KEY_DECISIONS,
            $decisions,
            'array',
            'Recent autonomous civilization behavior decisions.'
        );
    }

    private function currentYear(): int
    {
        return (int)(GameState::getCurrent()->current_year ?? 1);
    }
}
