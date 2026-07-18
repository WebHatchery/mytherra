<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameState;
use App\Models\Hero;
use App\Models\Landmark;
use App\Models\Player;
use App\Models\Region;
use App\Models\ResourceNode;
use App\Models\Settlement;
use App\Repositories\EventRepository;

class PantheonService
{
    private const CONFIG_CATEGORY = 'pantheon';
    private const CONFIG_KEY_STATE = 'state';
    private const HISTORY_LIMIT = 18;
    private const ARC_HISTORY_LIMIT = 16;
    private const ARC_COOLDOWN_YEARS = 3;
    private const COUNTERPLAY_COSTS = [
        'appease' => 12,
        'challenge' => 18,
    ];

    private const ROSTER = [
        'auralis_greenhand' => [
            'id' => 'auralis_greenhand',
            'name' => 'Auralis Greenhand',
            'domain' => 'prosperity',
            'alignment' => 'generous',
            'goal' => 'Restore civic stability and reward productive settlements.',
            'strategy' => 'Bless weak economies, settle disrupted resources, and lift regional confidence.',
            'allyId' => 'thalassa_veiled',
            'rivalId' => 'vorag_bonecrown',
        ],
        'vorag_bonecrown' => [
            'id' => 'vorag_bonecrown',
            'name' => 'Vorag Bonecrown',
            'domain' => 'strife',
            'alignment' => 'ruthless',
            'goal' => 'Expose fragile borders and force heroes into conflict.',
            'strategy' => 'Stir contested resources, harden defenses, and raise danger where power is soft.',
            'allyId' => 'morva_ashveil',
            'rivalId' => 'auralis_greenhand',
        ],
        'thalassa_veiled' => [
            'id' => 'thalassa_veiled',
            'name' => 'Thalassa Veiled',
            'domain' => 'secrets',
            'alignment' => 'inscrutable',
            'goal' => 'Uncover hidden lore and pull mortal attention toward arcane sites.',
            'strategy' => 'Awaken magical landmarks, favor scholars, and thicken mystery in resonant regions.',
            'allyId' => 'auralis_greenhand',
            'rivalId' => 'morva_ashveil',
        ],
        'morva_ashveil' => [
            'id' => 'morva_ashveil',
            'name' => 'Morva Ashveil',
            'domain' => 'entropy',
            'alignment' => 'cold',
            'goal' => 'Break overextended golden ages before they become permanent.',
            'strategy' => 'Drain rich settlements, exhaust high-output resources, and leave scars for later eras.',
            'allyId' => 'vorag_bonecrown',
            'rivalId' => 'thalassa_veiled',
        ],
    ];

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
        $state = $this->loadState();
        $pressure = $this->pressureMap();
        $recentInterventions = $this->recentInterventions($state);
        $deities = $this->deities($pressure, $recentInterventions);
        $topActor = $this->topActor($deities);
        $relationships = $this->relationships();
        $politics = $this->politics($relationships, $pressure, $recentInterventions);
        $relationshipArcs = $this->relationshipArcs($state);

        return [
            'currentYear' => $this->currentYear(),
            'summary' => $this->summary($deities, $recentInterventions),
            'deities' => $deities,
            'pressure' => array_values($pressure),
            'topActor' => $topActor,
            'recentInterventions' => $recentInterventions,
            'relationships' => $relationships,
            'politics' => $politics,
            'relationshipArcs' => $relationshipArcs,
            'bettingHooks' => $this->bettingHooks($pressure, $politics),
            'counterplay' => $this->counterplayStatus($state),
        ];
    }

    public function advanceWorld(int $currentYear, int $limit = 1): array
    {
        $summary = [
            'processed' => 0,
            'changed' => 0,
            'events' => 0,
            'interventions' => [],
            'arcs' => [],
            'errors' => [],
        ];

        $candidates = $this->rankedPressures($currentYear);
        if ($candidates === []) {
            return $summary;
        }

        foreach (array_slice($candidates, 0, max(1, $limit)) as $candidate) {
            $summary['processed']++;

            if ((int)$candidate['pressureScore'] < 25) {
                continue;
            }

            try {
                $intervention = $this->applyIntervention($candidate, $currentYear);
                $summary['interventions'][] = $intervention;
                $summary['changed'] += $this->changeCount($intervention['changes'] ?? []);
                $summary['events']++;
                $this->recordIntervention($intervention);
            } catch (\Throwable $error) {
                $summary['errors'][] = [
                    'deityId' => $candidate['deityId'] ?? null,
                    'regionId' => $candidate['targetRegionId'] ?? null,
                    'message' => $error->getMessage(),
                ];
            }
        }

        $arcSummary = $this->advanceRelationshipArcs($currentYear);
        $summary['changed'] += $arcSummary['changed'];
        $summary['events'] += $arcSummary['events'];
        $summary['arcs'] = $arcSummary['arcs'];
        $summary['errors'] = array_merge($summary['errors'], $arcSummary['errors']);

        return $summary;
    }

    public function counterplay(string $deityId, array $payload): array
    {
        $deity = self::ROSTER[$deityId] ?? null;
        if ($deity === null) {
            return [
                'success' => false,
                'message' => 'Unknown pantheon actor',
                'status' => $this->status(),
            ];
        }

        $mode = is_string($payload['mode'] ?? null) ? (string)$payload['mode'] : '';
        if (!array_key_exists($mode, self::COUNTERPLAY_COSTS)) {
            return [
                'success' => false,
                'message' => 'Choose appease or challenge counterplay.',
                'status' => $this->status(),
            ];
        }

        $cost = self::COUNTERPLAY_COSTS[$mode];
        $player = Player::getSinglePlayer();
        if (!$player->spendDivineFavor($cost)) {
            return [
                'success' => false,
                'message' => 'Insufficient divine favor',
                'cost' => $cost,
                'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
                'status' => $this->status(),
            ];
        }

        $currentYear = $this->currentYear();
        $pressure = $this->pressureMap()[$deityId] ?? null;
        $state = $this->loadState();
        $counterplay = is_array($state['counterplay'] ?? null) ? $state['counterplay'] : [];
        $deityState = is_array($counterplay[$deityId] ?? null) ? $counterplay[$deityId] : [];
        $effective = $this->effectiveCounterplayFromRaw($deityState, $currentYear);

        if ($mode === 'appease') {
            $effective['appeasement'] = $this->clamp((int)$effective['appeasement'] + 14, 0, 60);
            $effective['defiance'] = $this->clamp((int)$effective['defiance'] - 5, 0, 60);
        } else {
            $effective['defiance'] = $this->clamp((int)$effective['defiance'] + 16, 0, 60);
            $effective['appeasement'] = $this->clamp((int)$effective['appeasement'] - 4, 0, 60);
        }

        $effective['lastActionYear'] = $currentYear;
        $effective['lastMode'] = $mode;
        $counterplay[$deityId] = $effective;
        $state['counterplay'] = $counterplay;

        $action = $this->recordCounterplayAction($state, $deity, $mode, $cost, $currentYear, $pressure);
        $this->saveState($state);

        return [
            'success' => true,
            'message' => $action['summary'],
            'cost' => $cost,
            'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
            'action' => $action,
            'status' => $this->status(),
        ];
    }

    private function applyIntervention(array $pressure, int $currentYear): array
    {
        $deityId = (string)$pressure['deityId'];
        $deity = self::ROSTER[$deityId] ?? null;
        if ($deity === null) {
            throw new \InvalidArgumentException("Unknown pantheon actor: {$deityId}");
        }

        $region = Region::find((string)$pressure['targetRegionId']);
        if (!$region instanceof Region) {
            throw new \InvalidArgumentException("Region not found: {$pressure['targetRegionId']}");
        }

        $changes = match ((string)$deity['domain']) {
            'prosperity' => $this->applyProsperity($region, $currentYear),
            'strife' => $this->applyStrife($region, $currentYear),
            'secrets' => $this->applySecrets($region, $currentYear),
            'entropy' => $this->applyEntropy($region, $currentYear),
            default => $this->emptyChanges(),
        };

        $related = $this->relatedIdsFromChanges($changes, (string)$region->id);
        $effectSummary = $this->effectSummary($changes);
        $title = 'Pantheon Intervention: ' . (string)$deity['name'];
        $description = "{$deity['name']} pressed the domain of {$deity['domain']} into {$region->name}. {$pressure['summary']} {$effectSummary}";
        $event = $this->eventRepository->createEvent([
            'title' => $title,
            'description' => $description,
            'type' => 'pantheon_intervention',
            'region_id' => (string)$region->id,
            'related_region_ids' => $related['regions'],
            'related_hero_ids' => $related['heroes'],
            'related_settlement_ids' => $related['settlements'],
            'related_landmark_ids' => $related['landmarks'],
            'related_resource_ids' => $related['resources'],
            'year' => $currentYear,
        ]);

        return [
            'id' => 'pantheon-' . $deityId . '-' . $currentYear . '-' . bin2hex(random_bytes(4)),
            'year' => $currentYear,
            'deityId' => $deityId,
            'deityName' => (string)$deity['name'],
            'domain' => (string)$deity['domain'],
            'alignment' => (string)$deity['alignment'],
            'strategy' => (string)$deity['strategy'],
            'pressureScore' => (int)$pressure['pressureScore'],
            'pressureTier' => $this->pressureTier((int)$pressure['pressureScore']),
            'targetRegionId' => (string)$region->id,
            'targetRegionName' => (string)$region->name,
            'title' => $title,
            'summary' => $description,
            'eventId' => (string)$event->id,
            'changes' => $changes,
            'relatedRegionIds' => $related['regions'],
            'relatedSettlementIds' => $related['settlements'],
            'relatedHeroIds' => $related['heroes'],
            'relatedLandmarkIds' => $related['landmarks'],
            'relatedResourceIds' => $related['resources'],
        ];
    }

    private function applyProsperity(Region $region, int $currentYear): array
    {
        $changes = $this->emptyChanges();
        $this->changeRegion($changes, $region, function (Region $target): void {
            $target->prosperity = $this->clamp((int)$target->prosperity + 4);
            $target->chaos = $this->clamp((int)$target->chaos - 2);
            $target->danger_level = $this->clamp((int)$target->danger_level - 1);
            $target->divine_resonance = $this->clamp((int)($target->divine_resonance ?? 50) + 2);
            $target->status = $this->deriveRegionStatus($target);
            $target->addTrait('pantheon_blessed');
        });

        $settlement = Settlement::where('region_id', $region->id)
            ->orderBy('prosperity')
            ->orderByDesc('population')
            ->first();
        if ($settlement instanceof Settlement) {
            $this->changeSettlement($changes, $settlement, function (Settlement $target) use ($currentYear): void {
                $target->population = max(0, (int)$target->population + max(10, (int)round((int)$target->population * 0.01)));
                $target->prosperity = $this->clamp((int)$target->prosperity + 5);
                $target->defensibility = $this->clamp((int)$target->defensibility + 1);
                $target->status = $this->deriveSettlementStatus($target);
                $target->traits = $this->addUnique($target->traits ?? [], 'prosperity_blessed');
                $target->last_event_year = $currentYear;
            });
        }

        $node = ResourceNode::where('region_id', $region->id)
            ->whereIn('status', ['contested', 'corrupted', 'depleted', 'overworked', 'unstable'])
            ->orderBy('output')
            ->first()
            ?? ResourceNode::where('region_id', $region->id)->orderBy('output')->first();
        if ($node instanceof ResourceNode) {
            $this->changeResource($changes, $node, function (ResourceNode $target): void {
                $target->output = $this->clamp((int)$target->output + 5);
                $target->status = (int)$target->output >= 76 ? 'status-flourishing' : 'active';
            });
        }

        return $changes;
    }

    private function applyStrife(Region $region, int $currentYear): array
    {
        $changes = $this->emptyChanges();
        $this->changeRegion($changes, $region, function (Region $target): void {
            $target->prosperity = $this->clamp((int)$target->prosperity - 2);
            $target->chaos = $this->clamp((int)$target->chaos + 4);
            $target->danger_level = $this->clamp((int)$target->danger_level + 4);
            $target->status = $this->deriveRegionStatus($target);
            $target->addTrait('pantheon_rivalries');
        });

        $settlement = Settlement::where('region_id', $region->id)
            ->orderByDesc('population')
            ->first();
        if ($settlement instanceof Settlement) {
            $this->changeSettlement($changes, $settlement, function (Settlement $target) use ($currentYear): void {
                $target->prosperity = $this->clamp((int)$target->prosperity - 2);
                $target->defensibility = $this->clamp((int)$target->defensibility + 3);
                $target->status = $this->deriveSettlementStatus($target);
                $target->traits = $this->addUnique($target->traits ?? [], 'war_watch');
                $target->last_event_year = $currentYear;
            });
        }

        $node = ResourceNode::where('region_id', $region->id)
            ->whereNotIn('status', ['corrupted', 'depleted'])
            ->orderByDesc('output')
            ->first();
        if ($node instanceof ResourceNode) {
            $this->changeResource($changes, $node, function (ResourceNode $target): void {
                $target->output = $this->clamp((int)$target->output - 2);
                $target->status = 'contested';
            });
        }

        $hero = Hero::where('region_id', $region->id)
            ->where('is_alive', true)
            ->whereIn('role', ['warrior', 'agent of change'])
            ->orderByDesc('level')
            ->first();
        if ($hero instanceof Hero) {
            $this->changeHero($changes, $hero, function (Hero $target) use ($currentYear): void {
                $target->level = min(100, (int)$target->level + 1);
                $target->feats = $this->addUnique($target->feats ?? [], "Answered Vorag's war-sign in year {$currentYear}");
            });
        }

        return $changes;
    }

    private function applySecrets(Region $region, int $currentYear): array
    {
        $changes = $this->emptyChanges();
        $this->changeRegion($changes, $region, function (Region $target): void {
            $target->magic_affinity = $this->clamp((int)$target->magic_affinity + 5);
            $target->chaos = $this->clamp((int)$target->chaos + 1);
            $target->cultural_influence = 'mystical';
            $target->status = $this->deriveRegionStatus($target, 'secrets');
            $target->addTrait('veiled_lore');
        });

        $landmark = Landmark::where('region_id', $region->id)
            ->orderByDesc('magic_level')
            ->first();
        if ($landmark instanceof Landmark) {
            $this->changeLandmark($changes, $landmark, function (Landmark $target): void {
                $target->magic_level = $this->clamp((int)$target->magic_level + 4);
                $target->danger_level = $this->clamp((int)$target->danger_level + 1);
                $target->status = (int)$target->magic_level >= 75 ? 'awakened' : 'unstable';
                $target->traits = $this->addUnique($target->traits ?? [], Landmark::TRAIT_MAGICAL);
            });
        }

        $hero = Hero::where('region_id', $region->id)
            ->where('is_alive', true)
            ->whereIn('role', ['scholar', 'prophet'])
            ->orderByDesc('level')
            ->first();
        if ($hero instanceof Hero) {
            $this->changeHero($changes, $hero, function (Hero $target) use ($currentYear): void {
                $target->level = min(100, (int)$target->level + 1);
                $target->feats = $this->addUnique($target->feats ?? [], "Interpreted Thalassa's hidden sign in year {$currentYear}");
            });
        }

        $node = ResourceNode::where('region_id', $region->id)
            ->where('type', 'magical_spring')
            ->orderByDesc('output')
            ->first();
        if ($node instanceof ResourceNode) {
            $this->changeResource($changes, $node, function (ResourceNode $target): void {
                $target->output = $this->clamp((int)$target->output + 3);
                $target->status = 'unstable';
            });
        }

        return $changes;
    }

    private function applyEntropy(Region $region, int $currentYear): array
    {
        $changes = $this->emptyChanges();
        $this->changeRegion($changes, $region, function (Region $target): void {
            $target->prosperity = $this->clamp((int)$target->prosperity - 4);
            $target->chaos = $this->clamp((int)$target->chaos + 2);
            $target->danger_level = $this->clamp((int)$target->danger_level + 2);
            $target->status = $this->deriveRegionStatus($target);
            $target->addTrait('ashveil_decay');
        });

        $settlement = Settlement::where('region_id', $region->id)
            ->orderByDesc('prosperity')
            ->first();
        if ($settlement instanceof Settlement) {
            $this->changeSettlement($changes, $settlement, function (Settlement $target) use ($currentYear): void {
                $populationLoss = max(0, (int)round((int)$target->population * 0.01));
                $target->population = max(0, (int)$target->population - $populationLoss);
                $target->prosperity = $this->clamp((int)$target->prosperity - 5);
                $target->status = $this->deriveSettlementStatus($target);
                $target->traits = $this->addUnique($target->traits ?? [], 'ash_tithe');
                $target->last_event_year = $currentYear;
            });
        }

        $node = ResourceNode::where('region_id', $region->id)
            ->orderByDesc('output')
            ->first();
        if ($node instanceof ResourceNode) {
            $this->changeResource($changes, $node, function (ResourceNode $target): void {
                $target->output = $this->clamp((int)$target->output - 6);
                $target->status = (int)$target->output <= 12 ? 'depleted' : 'overworked';
            });
        }

        $landmark = Landmark::where('region_id', $region->id)
            ->orderByDesc('danger_level')
            ->first();
        if ($landmark instanceof Landmark) {
            $this->changeLandmark($changes, $landmark, function (Landmark $target): void {
                $target->danger_level = $this->clamp((int)$target->danger_level + 3);
                $target->status = (int)$target->danger_level >= 75 ? 'corrupted' : 'unstable';
            });
        }

        return $changes;
    }

    private function pressureMap(): array
    {
        $pressures = [];
        foreach (self::ROSTER as $deityId => $deity) {
            $pressures[$deityId] = $this->pressureForDeity($deityId, $deity);
        }

        return $pressures;
    }

    private function rankedPressures(int $currentYear): array
    {
        $items = array_values($this->pressureMap());
        usort($items, function (array $left, array $right) use ($currentYear): int {
            $leftScore = (int)$left['pressureScore'] + $this->tieBreak($left, $currentYear);
            $rightScore = (int)$right['pressureScore'] + $this->tieBreak($right, $currentYear);

            return ($rightScore <=> $leftScore)
                ?: strcmp((string)$left['deityId'], (string)$right['deityId']);
        });

        return $items;
    }

    private function pressureForDeity(string $deityId, array $deity): array
    {
        $best = null;
        foreach (Region::with(['settlements', 'heroes', 'landmarks', 'resourceNodes'])->get() as $region) {
            $stats = $this->regionStats($region);
            $entry = match ((string)$deity['domain']) {
                'prosperity' => $this->prosperityPressure($region, $stats),
                'strife' => $this->strifePressure($region, $stats),
                'secrets' => $this->secretsPressure($region, $stats),
                'entropy' => $this->entropyPressure($region, $stats),
                default => ['score' => 0, 'summary' => 'No pressure was detected.', 'signals' => []],
            };

            $rawScore = $this->clamp((int)$entry['score']);
            $counterplay = $this->effectiveCounterplay($deityId);
            $pressureReduction = (int)$counterplay['pressureReduction'];
            $score = $this->clamp($rawScore - $pressureReduction);
            $signals = $entry['signals'];
            if ($pressureReduction > 0) {
                $signals[] = $this->signal(
                    'Player counterplay',
                    '-' . $pressureReduction,
                    (string)$counterplay['summary']
                );
            }

            $candidate = [
                'deityId' => $deityId,
                'deityName' => (string)$deity['name'],
                'domain' => (string)$deity['domain'],
                'pressureScore' => $score,
                'rawPressureScore' => $rawScore,
                'pressureTier' => $this->pressureTier($score),
                'targetRegionId' => (string)$region->id,
                'targetRegionName' => (string)$region->name,
                'summary' => (string)$entry['summary'],
                'signals' => $signals,
                'counterplay' => $counterplay,
                'relatedRegionIds' => [(string)$region->id],
                'relatedSettlementIds' => $stats['settlementIds'],
                'relatedHeroIds' => $stats['heroIds'],
                'relatedLandmarkIds' => $stats['landmarkIds'],
                'relatedResourceIds' => $stats['resourceIds'],
            ];

            if ($best === null || (int)$candidate['pressureScore'] > (int)$best['pressureScore']) {
                $best = $candidate;
            }
        }

        return $best ?? [
            'deityId' => $deityId,
            'deityName' => (string)$deity['name'],
            'domain' => (string)$deity['domain'],
            'pressureScore' => 0,
            'pressureTier' => 'quiet',
            'targetRegionId' => null,
            'targetRegionName' => null,
            'summary' => 'No regions are available for pantheon pressure.',
            'signals' => [],
            'counterplay' => $this->effectiveCounterplay($deityId),
            'relatedRegionIds' => [],
            'relatedSettlementIds' => [],
            'relatedHeroIds' => [],
            'relatedLandmarkIds' => [],
            'relatedResourceIds' => [],
        ];
    }

    private function prosperityPressure(Region $region, array $stats): array
    {
        $score = 14
            + max(0, 62 - (int)$region->prosperity) * 0.45
            + max(0, 58 - $stats['avgSettlementProsperity']) * 0.35
            + $stats['distressedSettlements'] * 8
            + $stats['disruptedResources'] * 6
            + max(0, (int)$region->chaos - 35) * 0.2;

        return [
            'score' => $score,
            'summary' => "Prosperity pressure centers on {$region->name}: {$stats['distressedSettlements']} distressed settlements, {$stats['disruptedResources']} disrupted resources, prosperity {$region->prosperity}/100.",
            'signals' => [
                $this->signal('Regional prosperity', (string)$region->prosperity, 'Lower prosperity invites Auralis to stabilize the region.'),
                $this->signal('Distressed settlements', (string)$stats['distressedSettlements'], 'Weak settlements are likely blessing targets.'),
                $this->signal('Disrupted resources', (string)$stats['disruptedResources'], 'Damaged resources can be restored for visible pressure.'),
            ],
        ];
    }

    private function strifePressure(Region $region, array $stats): array
    {
        $score = 12
            + (int)$region->chaos * 0.34
            + (int)$region->danger_level * 0.36
            + $stats['contestedResources'] * 10
            + max(0, 55 - $stats['avgSettlementDefense']) * 0.24
            + $stats['warriorHeroes'] * 6;

        return [
            'score' => $score,
            'summary' => "Strife pressure targets {$region->name}: chaos {$region->chaos}/100, danger {$region->danger_level}/100, {$stats['contestedResources']} contested resources.",
            'signals' => [
                $this->signal('Chaos', (string)$region->chaos, 'High chaos gives Vorag a foothold.'),
                $this->signal('Danger', (string)$region->danger_level, 'Regional danger can become open conflict.'),
                $this->signal('Martial heroes', (string)$stats['warriorHeroes'], 'Warriors and agents of change can carry conflict.'),
            ],
        ];
    }

    private function secretsPressure(Region $region, array $stats): array
    {
        $score = 12
            + (int)$region->magic_affinity * 0.38
            + $stats['magicalLandmarks'] * 7
            + $stats['magicalResources'] * 8
            + $stats['scholarHeroes'] * 7
            + ($stats['culture'] === 'mystical' ? 10 : 0);

        return [
            'score' => $score,
            'summary' => "Secret pressure gathers in {$region->name}: magic {$region->magic_affinity}/100, {$stats['magicalLandmarks']} magical landmarks, {$stats['scholarHeroes']} scholars or prophets.",
            'signals' => [
                $this->signal('Magic affinity', (string)$region->magic_affinity, 'Ambient magic makes hidden lore easier to surface.'),
                $this->signal('Arcane sites', (string)($stats['magicalLandmarks'] + $stats['magicalResources']), 'Magical places become intervention anchors.'),
                $this->signal('Lore keepers', (string)$stats['scholarHeroes'], 'Scholars and prophets can interpret signs.'),
            ],
        ];
    }

    private function entropyPressure(Region $region, array $stats): array
    {
        $score = 10
            + (int)$region->prosperity * 0.28
            + $stats['prosperousSettlements'] * 7
            + $stats['highOutputResources'] * 7
            + max(0, 40 - (int)$region->chaos) * 0.18
            + ($region->status === 'flourishing' || $region->status === 'prosperous' ? 8 : 0);

        return [
            'score' => $score,
            'summary' => "Entropy pressure watches {$region->name}: prosperity {$region->prosperity}/100, {$stats['prosperousSettlements']} prosperous settlements, {$stats['highOutputResources']} rich resources.",
            'signals' => [
                $this->signal('Regional prosperity', (string)$region->prosperity, 'High prosperity draws Morva toward decay.'),
                $this->signal('Prosperous settlements', (string)$stats['prosperousSettlements'], 'Rich settlements are prime targets.'),
                $this->signal('High-output resources', (string)$stats['highOutputResources'], 'Abundant production can be exhausted.'),
            ],
        ];
    }

    private function regionStats(Region $region): array
    {
        $settlements = $region->settlements;
        $resources = $region->resourceNodes;
        $heroes = $region->heroes->filter(fn(Hero $hero): bool => (bool)$hero->is_alive);
        $landmarks = $region->landmarks;

        return [
            'settlementIds' => $this->ids($settlements->pluck('id')->all()),
            'resourceIds' => $this->ids($resources->pluck('id')->all()),
            'heroIds' => $this->ids($heroes->pluck('id')->all()),
            'landmarkIds' => $this->ids($landmarks->pluck('id')->all()),
            'avgSettlementProsperity' => $settlements->count() > 0
                ? (int)round((float)$settlements->avg('prosperity'))
                : 50,
            'avgSettlementDefense' => $settlements->count() > 0
                ? (int)round((float)$settlements->avg('defensibility'))
                : 35,
            'distressedSettlements' => $settlements
                ->filter(fn(Settlement $settlement): bool => in_array((string)$settlement->status, ['declining', 'struggling', 'abandoned', 'ruined'], true))
                ->count(),
            'prosperousSettlements' => $settlements
                ->filter(fn(Settlement $settlement): bool => (int)$settlement->prosperity >= 68 || in_array((string)$settlement->status, ['thriving', 'prosperous'], true))
                ->count(),
            'disruptedResources' => $resources
                ->filter(fn(ResourceNode $node): bool => in_array((string)$node->status, ['contested', 'corrupted', 'depleted', 'overworked', 'unstable'], true))
                ->count(),
            'contestedResources' => $resources
                ->filter(fn(ResourceNode $node): bool => (string)$node->status === 'contested')
                ->count(),
            'highOutputResources' => $resources
                ->filter(fn(ResourceNode $node): bool => (int)$node->output >= 68)
                ->count(),
            'magicalResources' => $resources
                ->filter(fn(ResourceNode $node): bool => (string)$node->type === 'magical_spring' || $node->isMagical())
                ->count(),
            'magicalLandmarks' => $landmarks
                ->filter(fn(Landmark $landmark): bool => (int)$landmark->magic_level >= 45 || in_array(Landmark::TRAIT_MAGICAL, $landmark->traits ?? [], true))
                ->count(),
            'warriorHeroes' => $heroes
                ->filter(fn(Hero $hero): bool => in_array((string)$hero->role, ['warrior', 'agent of change'], true))
                ->count(),
            'scholarHeroes' => $heroes
                ->filter(fn(Hero $hero): bool => in_array((string)$hero->role, ['scholar', 'prophet'], true))
                ->count(),
            'culture' => (string)($region->cultural_influence ?? 'pastoral'),
        ];
    }

    private function deities(array $pressure, array $recentInterventions): array
    {
        return array_map(function (array $deity) use ($pressure, $recentInterventions): array {
            $latest = null;
            $count = 0;
            foreach ($recentInterventions as $intervention) {
                if (($intervention['deityId'] ?? null) !== $deity['id']) {
                    continue;
                }
                $latest ??= $intervention;
                $count++;
            }

            $pressureEntry = $pressure[$deity['id']] ?? [];

            return array_merge($deity, [
                'pressureScore' => (int)($pressureEntry['pressureScore'] ?? 0),
                'pressureTier' => (string)($pressureEntry['pressureTier'] ?? 'quiet'),
                'targetRegionId' => $pressureEntry['targetRegionId'] ?? null,
                'targetRegionName' => $pressureEntry['targetRegionName'] ?? null,
                'interventionCount' => $count,
                'latestIntervention' => $latest,
                'counterplay' => $pressureEntry['counterplay'] ?? $this->effectiveCounterplay((string)$deity['id']),
            ]);
        }, array_values(self::ROSTER));
    }

    private function topActor(array $deities): ?array
    {
        if ($deities === []) {
            return null;
        }

        usort(
            $deities,
            fn(array $left, array $right): int => ((int)$right['pressureScore'] <=> (int)$left['pressureScore'])
                ?: strcmp((string)$left['name'], (string)$right['name'])
        );

        return $deities[0];
    }

    private function relationships(): array
    {
        $items = [];
        foreach (self::ROSTER as $deity) {
            $sourceCounterplay = $this->effectiveCounterplay((string)$deity['id']);
            $allyTension = $this->relationshipTension('ally', $sourceCounterplay);
            $rivalTension = $this->relationshipTension('rival', $sourceCounterplay);

            $items[] = [
                'sourceId' => (string)$deity['id'],
                'sourceName' => (string)$deity['name'],
                'targetId' => (string)$deity['allyId'],
                'targetName' => (string)(self::ROSTER[$deity['allyId']]['name'] ?? $deity['allyId']),
                'stance' => 'ally',
                'tension' => $allyTension,
                'summary' => "{$deity['name']} usually reinforces " . (string)(self::ROSTER[$deity['allyId']]['name'] ?? $deity['allyId']) . ". {$sourceCounterplay['relationshipSummary']}",
            ];
            $items[] = [
                'sourceId' => (string)$deity['id'],
                'sourceName' => (string)$deity['name'],
                'targetId' => (string)$deity['rivalId'],
                'targetName' => (string)(self::ROSTER[$deity['rivalId']]['name'] ?? $deity['rivalId']),
                'stance' => 'rival',
                'tension' => $rivalTension,
                'summary' => "{$deity['name']} contests " . (string)(self::ROSTER[$deity['rivalId']]['name'] ?? $deity['rivalId']) . ". {$sourceCounterplay['relationshipSummary']}",
            ];
        }

        return $items;
    }

    private function politics(array $relationships, array $pressure, array $recentInterventions): array
    {
        $escalations = [];
        foreach ($relationships as $relationship) {
            $sourceId = (string)($relationship['sourceId'] ?? '');
            $sourcePressure = $pressure[$sourceId] ?? null;
            $pressureScore = is_array($sourcePressure) ? (int)($sourcePressure['pressureScore'] ?? 0) : 0;
            $tension = (int)($relationship['tension'] ?? 0);
            $stance = (string)($relationship['stance'] ?? 'rival');
            $stage = $this->politicalStage($stance, $tension, $pressureScore);
            $interventionCount = count(array_filter(
                $recentInterventions,
                fn(array $intervention): bool => ($intervention['deityId'] ?? null) === $sourceId
            ));

            $targetRegionName = is_array($sourcePressure)
                ? ($sourcePressure['targetRegionName'] ?? null)
                : null;
            $targetRegionId = is_array($sourcePressure)
                ? ($sourcePressure['targetRegionId'] ?? null)
                : null;

            $summary = "{$relationship['sourceName']} holds a {$stage} {$stance} posture toward {$relationship['targetName']}.";
            if (is_string($targetRegionName) && $targetRegionName !== '') {
                $summary .= " Pressure is centered on {$targetRegionName} at {$pressureScore}/100.";
            }
            if ($interventionCount > 0) {
                $summary .= " {$interventionCount} recent intervention(s) make the posture more visible.";
            }

            $escalations[] = [
                'sourceId' => $sourceId,
                'sourceName' => (string)($relationship['sourceName'] ?? ''),
                'targetId' => (string)($relationship['targetId'] ?? ''),
                'targetName' => (string)($relationship['targetName'] ?? ''),
                'stance' => $stance,
                'tension' => $tension,
                'stage' => $stage,
                'pressureScore' => $pressureScore,
                'targetRegionId' => is_string($targetRegionId) ? $targetRegionId : null,
                'targetRegionName' => is_string($targetRegionName) ? $targetRegionName : null,
                'interventionCount' => $interventionCount,
                'betType' => 'pantheon_intervention',
                'summary' => $summary,
            ];
        }

        usort(
            $escalations,
            fn(array $left, array $right): int => ((int)$right['pressureScore'] + (int)$right['tension'])
                <=> ((int)$left['pressureScore'] + (int)$left['tension'])
        );

        $active = array_values(array_filter(
            $escalations,
            fn(array $entry): bool => !in_array((string)$entry['stage'], ['quiet_alliance', 'watchful'], true)
        ));

        return [
            'summary' => $active === []
                ? 'Pantheon politics are watchful with no major escalation.'
                : count($active) . ' pantheon political front(s) are escalating around active pressure.',
            'escalations' => $escalations,
        ];
    }

    private function bettingHooks(array $pressure, array $politics): array
    {
        $politicsByDeity = [];
        foreach (($politics['escalations'] ?? []) as $escalation) {
            if (!is_array($escalation)) {
                continue;
            }

            $sourceId = (string)($escalation['sourceId'] ?? '');
            if ($sourceId === '') {
                continue;
            }

            $current = $politicsByDeity[$sourceId] ?? null;
            if ($current === null || (int)($escalation['tension'] ?? 0) > (int)($current['tension'] ?? 0)) {
                $politicsByDeity[$sourceId] = $escalation;
            }
        }

        $items = array_values(array_filter(
            $pressure,
            fn(array $entry): bool => (int)($entry['pressureScore'] ?? 0) >= 25 && is_string($entry['targetRegionId'] ?? null)
        ));
        usort(
            $items,
            fn(array $left, array $right): int => ((int)$right['pressureScore'] <=> (int)$left['pressureScore'])
                ?: strcmp((string)$left['deityName'], (string)$right['deityName'])
        );

        $hooks = [];
        foreach (array_slice($items, 0, 4) as $entry) {
            $score = (int)$entry['pressureScore'];
            $deityId = (string)$entry['deityId'];
            $regionId = (string)$entry['targetRegionId'];
            $political = $politicsByDeity[$deityId] ?? null;
            $confidence = match (true) {
                $score >= 75 => 'likely',
                $score >= 50 => 'possible',
                default => 'long_shot',
            };
            $maximumYears = match (true) {
                $score >= 75 => 4,
                $score >= 50 => 6,
                default => 8,
            };
            $stage = is_array($political) ? (string)($political['stage'] ?? 'watchful') : 'watchful';
            $relationshipStance = is_array($political) ? (string)($political['stance'] ?? 'rival') : null;

            $hooks[] = [
                'id' => 'pantheon-intervention-' . $deityId . '-' . $regionId,
                'title' => 'Pantheon Intervention: ' . (string)$entry['deityName'],
                'summary' => "{$entry['deityName']} has {$score}/100 {$entry['domain']} pressure over {$entry['targetRegionName']}; a direct intervention can resolve this wager.",
                'betType' => 'pantheon_intervention',
                'targetId' => $regionId,
                'regionId' => $regionId,
                'regionName' => (string)$entry['targetRegionName'],
                'deityId' => $deityId,
                'deityName' => (string)$entry['deityName'],
                'domain' => (string)$entry['domain'],
                'pressureScore' => $score,
                'pressureTier' => (string)$entry['pressureTier'],
                'confidence' => $confidence,
                'minimumYears' => 1,
                'maximumYears' => $maximumYears,
                'relationshipStance' => $relationshipStance,
                'politicalStage' => $stage,
                'riskSummary' => "Pressure tier {$entry['pressureTier']} with {$stage} pantheon politics.",
            ];
        }

        return $hooks;
    }

    private function politicalStage(string $stance, int $tension, int $pressureScore): string
    {
        if ($stance === 'ally') {
            if ($tension >= 58 || $pressureScore >= 78) {
                return 'strained_alliance';
            }
            if ($pressureScore >= 55) {
                return 'active_alliance';
            }
            if ($tension <= 18) {
                return 'quiet_alliance';
            }

            return 'watchful';
        }

        if ($tension >= 72 || $pressureScore >= 78) {
            return 'open_rivalry';
        }
        if ($tension >= 56 || $pressureScore >= 55) {
            return 'rising_tension';
        }

        return 'watchful';
    }

    private function summary(array $deities, array $recentInterventions): string
    {
        if ($deities === []) {
            return 'No pantheon actors are configured.';
        }

        $topActor = $this->topActor($deities);
        if ($topActor === null) {
            return 'Pantheon pressure is quiet.';
        }

        return "AI pantheon pressure is led by {$topActor['name']} ({$topActor['domain']}) at {$topActor['pressureScore']}/100 over {$topActor['targetRegionName']}. " .
            count($recentInterventions) . ' recent interventions are recorded.';
    }

    private function recordIntervention(array $intervention): void
    {
        $state = $this->loadState();
        $history = $this->recentInterventions($state);
        array_unshift($history, $intervention);
        $state['recentInterventions'] = array_slice($history, 0, self::HISTORY_LIMIT);
        $state['lastInterventionYear'] = $intervention['year'];
        $state['lastInterventionId'] = $intervention['id'];

        $this->saveState($state);
    }

    private function recentInterventions(array $state): array
    {
        $items = $state['recentInterventions'] ?? [];
        return is_array($items)
            ? array_values(array_filter($items, fn($item): bool => is_array($item)))
            : [];
    }

    private function advanceRelationshipArcs(int $currentYear): array
    {
        $summary = [
            'processed' => 0,
            'changed' => 0,
            'events' => 0,
            'arcs' => [],
            'errors' => [],
        ];

        $state = $this->loadState();
        $politics = $this->politics(
            $this->relationships(),
            $this->pressureMap(),
            $this->recentInterventions($state)
        );

        foreach ($this->relationshipArcCandidates($politics) as $candidate) {
            $summary['processed']++;
            $currentArc = $this->relationshipArcForCandidate($state, $candidate);
            $lastAdvancedYear = $currentArc === null ? null : (int)($currentArc['lastAdvancedYear'] ?? 0);
            if ($lastAdvancedYear !== null && $currentYear - $lastAdvancedYear < self::ARC_COOLDOWN_YEARS) {
                continue;
            }

            try {
                $arc = $this->resolveRelationshipArc($candidate, $currentArc, $currentYear);
                $summary['arcs'][] = $arc;
                $summary['changed'] += $this->changeCount($arc['changes'] ?? []);
                $summary['events']++;
                break;
            } catch (\Throwable $error) {
                $summary['errors'][] = [
                    'sourceId' => $candidate['sourceId'] ?? null,
                    'targetId' => $candidate['targetId'] ?? null,
                    'regionId' => $candidate['targetRegionId'] ?? null,
                    'message' => $error->getMessage(),
                ];
            }
        }

        return $summary;
    }

    private function relationshipArcCandidates(array $politics): array
    {
        $candidates = [];
        foreach (($politics['escalations'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $stage = (string)($entry['stage'] ?? 'watchful');
            $targetRegionId = $entry['targetRegionId'] ?? null;
            if (in_array($stage, ['quiet_alliance', 'watchful'], true) || !is_string($targetRegionId) || $targetRegionId === '') {
                continue;
            }

            if ((string)($entry['sourceId'] ?? '') === '' || (string)($entry['targetId'] ?? '') === '') {
                continue;
            }

            $candidates[] = $entry;
        }

        usort(
            $candidates,
            fn(array $left, array $right): int => $this->relationshipArcPriority($right)
                <=> $this->relationshipArcPriority($left)
        );

        return $candidates;
    }

    private function relationshipArcPriority(array $candidate): int
    {
        $stageWeight = match ((string)($candidate['stage'] ?? 'watchful')) {
            'open_rivalry' => 44,
            'strained_alliance' => 38,
            'active_alliance' => 30,
            'rising_tension' => 28,
            default => 0,
        };

        return $stageWeight
            + (int)($candidate['pressureScore'] ?? 0)
            + (int)($candidate['tension'] ?? 0)
            + ((int)($candidate['interventionCount'] ?? 0) * 4);
    }

    private function resolveRelationshipArc(array $candidate, ?array $currentArc, int $currentYear): array
    {
        $regionId = (string)($candidate['targetRegionId'] ?? '');
        $region = Region::find($regionId);
        if (!$region instanceof Region) {
            throw new \InvalidArgumentException("Region not found for pantheon relationship arc: {$regionId}");
        }

        $changes = $this->applyRelationshipArcEffects($candidate, $region, $currentYear);
        $related = $this->relatedIdsFromChanges($changes, (string)$region->id);
        $effectSummary = $this->effectSummary($changes);
        $title = $this->relationshipArcTitle($candidate);
        $summary = $this->relationshipArcSummary($candidate, $region, $currentArc, $effectSummary);
        $event = $this->eventRepository->createEvent([
            'title' => $title,
            'description' => $summary,
            'type' => 'pantheon_relationship_arc',
            'region_id' => (string)$region->id,
            'related_region_ids' => $related['regions'],
            'related_hero_ids' => $related['heroes'],
            'related_settlement_ids' => $related['settlements'],
            'related_landmark_ids' => $related['landmarks'],
            'related_resource_ids' => $related['resources'],
            'year' => $currentYear,
        ]);

        $sourceId = (string)$candidate['sourceId'];
        $targetId = (string)$candidate['targetId'];
        $stance = (string)($candidate['stance'] ?? 'rival');
        $eventIds = $this->ids(array_merge(
            [(string)$event->id],
            is_array($currentArc['eventIds'] ?? null) ? $currentArc['eventIds'] : []
        ));
        $stepCount = (int)($currentArc['stepCount'] ?? 0) + 1;
        $arc = [
            'id' => $currentArc['id'] ?? ('pantheon-arc-' . $sourceId . '-' . $targetId . '-' . $stance . '-' . bin2hex(random_bytes(4))),
            'arcKey' => $this->relationshipArcKey($sourceId, $targetId, $stance),
            'year' => $currentYear,
            'sourceId' => $sourceId,
            'sourceName' => (string)($candidate['sourceName'] ?? (self::ROSTER[$sourceId]['name'] ?? $sourceId)),
            'targetId' => $targetId,
            'targetName' => (string)($candidate['targetName'] ?? (self::ROSTER[$targetId]['name'] ?? $targetId)),
            'stance' => $stance,
            'stage' => (string)($candidate['stage'] ?? 'watchful'),
            'momentum' => $this->relationshipArcMomentum($candidate, $currentArc),
            'tension' => (int)($candidate['tension'] ?? 0),
            'pressureScore' => (int)($candidate['pressureScore'] ?? 0),
            'targetRegionId' => (string)$region->id,
            'targetRegionName' => (string)$region->name,
            'startedYear' => (int)($currentArc['startedYear'] ?? $currentYear),
            'lastAdvancedYear' => $currentYear,
            'stepCount' => $stepCount,
            'title' => $title,
            'summary' => $summary,
            'eventId' => (string)$event->id,
            'eventIds' => array_slice($eventIds, 0, 6),
            'changes' => $changes,
            'relatedRegionIds' => $related['regions'],
            'relatedSettlementIds' => $related['settlements'],
            'relatedHeroIds' => $related['heroes'],
            'relatedLandmarkIds' => $related['landmarks'],
            'relatedResourceIds' => $related['resources'],
            'nextPressure' => "Can advance again after " . self::ARC_COOLDOWN_YEARS . " years if this front stays escalated.",
        ];

        $this->recordRelationshipArc($arc);

        return $arc;
    }

    private function applyRelationshipArcEffects(array $candidate, Region $region, int $currentYear): array
    {
        $changes = $this->emptyChanges();
        $sourceId = (string)($candidate['sourceId'] ?? '');
        $sourceDomain = (string)(self::ROSTER[$sourceId]['domain'] ?? '');
        $stance = (string)($candidate['stance'] ?? 'rival');
        $stage = (string)($candidate['stage'] ?? 'watchful');

        $this->changeRegion($changes, $region, function (Region $target) use ($sourceDomain, $stance, $stage): void {
            if ($stance === 'ally') {
                $target->divine_resonance = $this->clamp((int)($target->divine_resonance ?? 50) + 2);
                if ($stage === 'strained_alliance') {
                    $target->chaos = $this->clamp((int)$target->chaos + 1);
                    $target->danger_level = $this->clamp((int)$target->danger_level + 1);
                    $target->addTrait('pantheon_strained_alliance');
                } else {
                    $target->prosperity = $this->clamp((int)$target->prosperity + 2);
                    $target->danger_level = $this->clamp((int)$target->danger_level - 1);
                    $target->addTrait('pantheon_alliance_front');
                }
            } else {
                $target->chaos = $this->clamp((int)$target->chaos + ($stage === 'open_rivalry' ? 3 : 2));
                $target->danger_level = $this->clamp((int)$target->danger_level + ($stage === 'open_rivalry' ? 2 : 1));
                $target->divine_resonance = $this->clamp((int)($target->divine_resonance ?? 50) + 1);
                $target->addTrait($stage === 'open_rivalry' ? 'pantheon_open_rivalry' : 'pantheon_rival_front');
            }

            if ($sourceDomain === 'prosperity') {
                $target->prosperity = $this->clamp((int)$target->prosperity + 1);
            } elseif ($sourceDomain === 'strife') {
                $target->danger_level = $this->clamp((int)$target->danger_level + 1);
            } elseif ($sourceDomain === 'secrets') {
                $target->magic_affinity = $this->clamp((int)$target->magic_affinity + 2);
            } elseif ($sourceDomain === 'entropy') {
                $target->prosperity = $this->clamp((int)$target->prosperity - 1);
                $target->chaos = $this->clamp((int)$target->chaos + 1);
            }

            $target->status = $this->deriveRegionStatus($target);
        });

        return $changes;
    }

    private function relationshipArcs(array $state): array
    {
        $items = $state['relationshipArcs'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $arcs = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $sourceId = (string)($item['sourceId'] ?? '');
            $targetId = (string)($item['targetId'] ?? '');
            $stance = (string)($item['stance'] ?? 'rival');
            if ($sourceId === '' || $targetId === '') {
                continue;
            }

            $eventIds = is_array($item['eventIds'] ?? null)
                ? $item['eventIds']
                : (is_string($item['eventId'] ?? null) ? [$item['eventId']] : []);
            $changes = $this->normalizeChangeGroups($item['changes'] ?? []);

            $arcs[] = [
                'id' => (string)($item['id'] ?? $this->relationshipArcKey($sourceId, $targetId, $stance)),
                'arcKey' => (string)($item['arcKey'] ?? $this->relationshipArcKey($sourceId, $targetId, $stance)),
                'year' => (int)($item['year'] ?? $item['lastAdvancedYear'] ?? $this->currentYear()),
                'sourceId' => $sourceId,
                'sourceName' => (string)($item['sourceName'] ?? (self::ROSTER[$sourceId]['name'] ?? $sourceId)),
                'targetId' => $targetId,
                'targetName' => (string)($item['targetName'] ?? (self::ROSTER[$targetId]['name'] ?? $targetId)),
                'stance' => $stance,
                'stage' => (string)($item['stage'] ?? 'watchful'),
                'momentum' => (int)($item['momentum'] ?? 0),
                'tension' => (int)($item['tension'] ?? 0),
                'pressureScore' => (int)($item['pressureScore'] ?? 0),
                'targetRegionId' => isset($item['targetRegionId']) ? (string)$item['targetRegionId'] : null,
                'targetRegionName' => isset($item['targetRegionName']) ? (string)$item['targetRegionName'] : null,
                'startedYear' => (int)($item['startedYear'] ?? $item['year'] ?? $this->currentYear()),
                'lastAdvancedYear' => (int)($item['lastAdvancedYear'] ?? $item['year'] ?? $this->currentYear()),
                'stepCount' => (int)($item['stepCount'] ?? 1),
                'title' => (string)($item['title'] ?? $this->relationshipArcTitle($item)),
                'summary' => (string)($item['summary'] ?? ''),
                'eventId' => isset($item['eventId']) ? (string)$item['eventId'] : ($eventIds[0] ?? null),
                'eventIds' => array_slice($this->ids($eventIds), 0, 6),
                'changes' => $changes,
                'relatedRegionIds' => $this->ids(is_array($item['relatedRegionIds'] ?? null) ? $item['relatedRegionIds'] : []),
                'relatedSettlementIds' => $this->ids(is_array($item['relatedSettlementIds'] ?? null) ? $item['relatedSettlementIds'] : []),
                'relatedHeroIds' => $this->ids(is_array($item['relatedHeroIds'] ?? null) ? $item['relatedHeroIds'] : []),
                'relatedLandmarkIds' => $this->ids(is_array($item['relatedLandmarkIds'] ?? null) ? $item['relatedLandmarkIds'] : []),
                'relatedResourceIds' => $this->ids(is_array($item['relatedResourceIds'] ?? null) ? $item['relatedResourceIds'] : []),
                'nextPressure' => (string)($item['nextPressure'] ?? ''),
            ];
        }

        usort(
            $arcs,
            fn(array $left, array $right): int => ((int)$right['lastAdvancedYear'] <=> (int)$left['lastAdvancedYear'])
                ?: ((int)$right['momentum'] <=> (int)$left['momentum'])
        );

        return array_slice($arcs, 0, self::ARC_HISTORY_LIMIT);
    }

    private function recordRelationshipArc(array $arc): void
    {
        $state = $this->loadState();
        $arcKey = (string)$arc['arcKey'];
        $history = array_values(array_filter(
            $this->relationshipArcs($state),
            fn(array $item): bool => (string)($item['arcKey'] ?? '') !== $arcKey
        ));
        array_unshift($history, $arc);
        $state['relationshipArcs'] = array_slice($history, 0, self::ARC_HISTORY_LIMIT);
        $state['lastRelationshipArcYear'] = $arc['lastAdvancedYear'];
        $state['lastRelationshipArcId'] = $arc['id'];

        $this->saveState($state);
    }

    private function relationshipArcForCandidate(array $state, array $candidate): ?array
    {
        $arcKey = $this->relationshipArcKey(
            (string)($candidate['sourceId'] ?? ''),
            (string)($candidate['targetId'] ?? ''),
            (string)($candidate['stance'] ?? 'rival')
        );

        foreach ($this->relationshipArcs($state) as $arc) {
            if ((string)($arc['arcKey'] ?? '') === $arcKey) {
                return $arc;
            }
        }

        return null;
    }

    private function relationshipArcKey(string $sourceId, string $targetId, string $stance): string
    {
        return $sourceId . ':' . $targetId . ':' . $stance;
    }

    private function relationshipArcMomentum(array $candidate, ?array $currentArc): int
    {
        $base = (int)round(((int)($candidate['pressureScore'] ?? 0) + (int)($candidate['tension'] ?? 0)) / 2);
        $previous = $currentArc === null ? 0 : (int)($currentArc['momentum'] ?? 0);

        return $this->clamp(max($base, $previous + 6));
    }

    private function relationshipArcTitle(array $candidate): string
    {
        $stance = (string)($candidate['stance'] ?? 'rival');
        $source = (string)($candidate['sourceName'] ?? $candidate['sourceId'] ?? 'Pantheon actor');
        $target = (string)($candidate['targetName'] ?? $candidate['targetId'] ?? 'another deity');

        return 'Pantheon ' . ($stance === 'ally' ? 'Alliance' : 'Rivalry') . " Arc: {$source} and {$target}";
    }

    private function relationshipArcSummary(array $candidate, Region $region, ?array $currentArc, string $effectSummary): string
    {
        $source = (string)($candidate['sourceName'] ?? $candidate['sourceId'] ?? 'A deity');
        $target = (string)($candidate['targetName'] ?? $candidate['targetId'] ?? 'another deity');
        $stance = (string)($candidate['stance'] ?? 'rival');
        $stage = (string)($candidate['stage'] ?? 'watchful');
        $step = (int)($currentArc['stepCount'] ?? 0) + 1;
        $verb = $stance === 'ally'
            ? ($stage === 'strained_alliance' ? 'strained an alliance with' : 'reinforced an alliance with')
            : ($stage === 'open_rivalry' ? 'opened a rivalry front against' : 'pushed rivalry tension against');
        $continuity = $step === 1
            ? 'This begins a persistent pantheon political arc.'
            : "This advances persistent pantheon arc step {$step}.";

        return "{$source} {$verb} {$target} around {$region->name}. {$candidate['summary']} {$continuity} {$effectSummary}";
    }

    private function normalizeChangeGroups(mixed $value): array
    {
        $changes = $this->emptyChanges();
        if (!is_array($value)) {
            return $changes;
        }

        foreach (array_keys($changes) as $key) {
            if (isset($value[$key]) && is_array($value[$key])) {
                $changes[$key] = array_values(array_filter($value[$key], fn($item): bool => is_array($item)));
            }
        }

        return $changes;
    }

    private function recordCounterplayAction(
        array &$state,
        array $deity,
        string $mode,
        int $cost,
        int $currentYear,
        ?array $pressure
    ): array {
        $targetRegionId = is_array($pressure) ? ($pressure['targetRegionId'] ?? null) : null;
        $targetRegionName = is_array($pressure) ? ($pressure['targetRegionName'] ?? null) : null;
        $targetLabel = $targetRegionName ? " over {$targetRegionName}" : '';
        $verb = $mode === 'appease' ? 'appeased' : 'challenged';
        $title = 'Pantheon ' . ($mode === 'appease' ? 'Appeased' : 'Challenged') . ': ' . (string)$deity['name'];
        $summary = "The player {$verb} {$deity['name']}{$targetLabel}, spending {$cost} Divine Favor to reduce near-term {$deity['domain']} pressure.";
        $event = $this->eventRepository->createEvent([
            'title' => $title,
            'description' => $summary,
            'type' => 'pantheon_counterplay',
            'region_id' => is_string($targetRegionId) ? $targetRegionId : null,
            'related_region_ids' => is_string($targetRegionId) ? [$targetRegionId] : [],
            'related_hero_ids' => [],
            'related_settlement_ids' => [],
            'related_landmark_ids' => [],
            'related_resource_ids' => [],
            'year' => $currentYear,
        ]);

        $action = [
            'id' => 'pantheon-counterplay-' . (string)$deity['id'] . '-' . $currentYear . '-' . bin2hex(random_bytes(4)),
            'year' => $currentYear,
            'deityId' => (string)$deity['id'],
            'deityName' => (string)$deity['name'],
            'domain' => (string)$deity['domain'],
            'mode' => $mode,
            'cost' => $cost,
            'targetRegionId' => is_string($targetRegionId) ? $targetRegionId : null,
            'targetRegionName' => is_string($targetRegionName) ? $targetRegionName : null,
            'title' => $title,
            'summary' => $summary,
            'eventId' => (string)$event->id,
        ];

        $history = $this->recentCounterplayActions($state);
        array_unshift($history, $action);
        $state['recentCounterplay'] = array_slice($history, 0, self::HISTORY_LIMIT);

        return $action;
    }

    private function recentCounterplayActions(array $state): array
    {
        $items = $state['recentCounterplay'] ?? [];
        return is_array($items)
            ? array_values(array_filter($items, fn($item): bool => is_array($item)))
            : [];
    }

    private function counterplayStatus(array $state): array
    {
        $byDeity = [];
        foreach (array_keys(self::ROSTER) as $deityId) {
            $byDeity[] = $this->effectiveCounterplay($deityId);
        }

        $active = array_values(array_filter(
            $byDeity,
            fn(array $entry): bool => (int)$entry['pressureReduction'] > 0
        ));

        return [
            'summary' => count($active) === 0
                ? 'No active player counterplay is suppressing pantheon pressure.'
                : count($active) . ' pantheon actor(s) are being suppressed by recent player counterplay.',
            'costs' => self::COUNTERPLAY_COSTS,
            'byDeity' => $byDeity,
            'recentActions' => $this->recentCounterplayActions($state),
        ];
    }

    private function effectiveCounterplay(string $deityId): array
    {
        $state = $this->loadState();
        $counterplay = is_array($state['counterplay'] ?? null) ? $state['counterplay'] : [];
        $raw = is_array($counterplay[$deityId] ?? null) ? $counterplay[$deityId] : [];

        return array_merge(
            ['deityId' => $deityId, 'deityName' => (string)(self::ROSTER[$deityId]['name'] ?? $deityId)],
            $this->effectiveCounterplayFromRaw($raw, $this->currentYear())
        );
    }

    private function effectiveCounterplayFromRaw(array $raw, int $currentYear): array
    {
        $lastActionYear = (int)($raw['lastActionYear'] ?? $currentYear);
        $decay = max(0, $currentYear - $lastActionYear) * 2;
        $appeasement = max(0, (int)($raw['appeasement'] ?? 0) - $decay);
        $defiance = max(0, (int)($raw['defiance'] ?? 0) - $decay);
        $pressureReduction = min(35, $appeasement + intdiv($defiance, 2));
        $summary = $pressureReduction > 0
            ? "Appeasement {$appeasement}, defiance {$defiance}; pressure is reduced by {$pressureReduction} until the action fades."
            : 'No active appeasement or challenge is shaping this deity.';

        return [
            'appeasement' => $appeasement,
            'defiance' => $defiance,
            'pressureReduction' => $pressureReduction,
            'lastActionYear' => $lastActionYear,
            'lastMode' => is_string($raw['lastMode'] ?? null) ? (string)$raw['lastMode'] : null,
            'summary' => $summary,
            'relationshipSummary' => $pressureReduction > 0
                ? "Player counterplay is currently lowering pressure by {$pressureReduction}."
                : 'No player counterplay is currently shaping this bond.',
        ];
    }

    private function relationshipTension(string $stance, array $counterplay): int
    {
        $base = $stance === 'ally' ? 24 : 52;
        $defiance = (int)($counterplay['defiance'] ?? 0);
        $appeasement = (int)($counterplay['appeasement'] ?? 0);

        return $this->clamp($base + $defiance - intdiv($appeasement, 2));
    }

    private function loadState(): array
    {
        if ($this->stateCache !== null) {
            return $this->stateCache;
        }

        $value = $this->configService->getConfig(self::CONFIG_CATEGORY, self::CONFIG_KEY_STATE, []);
        $this->stateCache = is_array($value) ? $value : [];

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
            'Recent AI pantheon interventions and pressure history.'
        );
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
            'regions' => ['prosperity', 'chaos', 'dangerLevel', 'magicAffinity', 'status'],
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
            : implode(', ', $parts) . ' changed under pantheon pressure.';
    }

    private function changeCount(array $changes): int
    {
        return array_sum(array_map(
            fn($items): int => is_array($items) ? count($items) : 0,
            $changes
        ));
    }

    private function relatedIdsFromChanges(array $changes, string $regionId): array
    {
        return [
            'regions' => $this->ids(array_merge(
                [$regionId],
                array_map(fn(array $change): string => (string)$change['id'], $changes['regions'])
            )),
            'settlements' => $this->ids(array_map(fn(array $change): string => (string)$change['id'], $changes['settlements'])),
            'heroes' => $this->ids(array_map(fn(array $change): string => (string)$change['id'], $changes['heroes'])),
            'landmarks' => $this->ids(array_map(fn(array $change): string => (string)$change['id'], $changes['landmarks'])),
            'resources' => $this->ids(array_map(fn(array $change): string => (string)$change['id'], $changes['resources'])),
        ];
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
            'divineResonance' => (int)($region->divine_resonance ?? 50),
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
            'traits' => $settlement->traits ?? [],
            'specializations' => $settlement->specializations ?? [],
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

    private function deriveRegionStatus(Region $region, string $source = ''): string
    {
        $prosperity = (int)$region->prosperity;
        $chaos = (int)$region->chaos;
        $danger = (int)$region->danger_level;
        $magic = (int)$region->magic_affinity;

        if ($source === 'secrets' || ($magic >= 75 && $chaos >= 28)) {
            return 'mysterious';
        }
        if ($prosperity >= 88 && $chaos <= 25 && $danger <= 25) {
            return 'flourishing';
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

    private function pressureTier(int $score): string
    {
        if ($score >= 75) {
            return 'dominant';
        }
        if ($score >= 55) {
            return 'active';
        }
        if ($score >= 35) {
            return 'watch';
        }

        return 'quiet';
    }

    private function signal(string $label, string $value, string $summary): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'summary' => $summary,
        ];
    }

    private function effectiveResourceOutput(ResourceNode $node): int
    {
        try {
            return (int)$node->getEffectiveOutput();
        } catch (\Throwable) {
            return (int)$node->output;
        }
    }

    private function tieBreak(array $pressure, int $currentYear): int
    {
        $seed = implode(':', [
            (string)($pressure['deityId'] ?? ''),
            (string)($pressure['targetRegionId'] ?? ''),
            (string)$currentYear,
        ]);

        return (int)(crc32($seed) % 7);
    }

    private function currentYear(): int
    {
        return (int)(GameState::getCurrent()->current_year ?? 1);
    }

    private function addUnique(array $values, string $value): array
    {
        if (!in_array($value, $values, true)) {
            $values[] = $value;
        }

        return array_values(array_unique(array_map(fn($item): string => (string)$item, $values)));
    }

    private function ids(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn($value): string => (string)$value,
            $values
        ))));
    }

    private function clamp(int|float $value, int $min = 0, int $max = 100): int
    {
        return (int)max($min, min($max, round($value)));
    }
}
