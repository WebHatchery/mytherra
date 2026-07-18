<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DivineBet;
use App\Models\GameEvent;
use App\Models\GameState;
use App\Models\Hero;
use App\Models\HeroDeathReason;
use App\Models\HeroSettlementInteraction;
use App\Models\Landmark;
use App\Models\Player;
use App\Models\Region;
use App\Models\ResourceNode;
use App\Models\Settlement;
use App\Repositories\DatabaseService;
use App\Utils\Logger;

class GameLoopService
{
    private const DIVINE_FAVOR_PER_TICK = 10;

    public function __construct(
        private ?GameConfigService $configService = null,
        private ?EraPressureService $eraPressureService = null,
        private ?EraLegacyService $eraLegacyService = null,
        private ?EraTransitionService $eraTransitionService = null,
        private ?CivilizationBehaviorService $civilizationBehaviorService = null,
        private ?ChampionService $championService = null,
        private ?ArtifactService $artifactService = null,
        private ?WeatherInfluenceService $weatherInfluenceService = null,
        private ?TemporalOmenService $temporalOmenService = null,
        private ?PantheonService $pantheonService = null,
        private ?MagicDiscoveryService $magicDiscoveryService = null,
        private ?MythologyService $mythologyService = null
    ) {
        $this->configService ??= GameConfigService::getInstance();
        $this->eraPressureService ??= new EraPressureService();
        $this->eraLegacyService ??= new EraLegacyService($this->eraPressureService);
        $this->eraTransitionService ??= new EraTransitionService(
            $this->configService,
            $this->eraPressureService,
            $this->eraLegacyService
        );
        $this->civilizationBehaviorService ??= new CivilizationBehaviorService($this->configService);
        $this->championService ??= new ChampionService(null, $this->configService);
        $this->artifactService ??= new ArtifactService($this->configService);
        $this->weatherInfluenceService ??= new WeatherInfluenceService($this->configService);
        $this->temporalOmenService ??= new TemporalOmenService($this->configService, null, $this->eraPressureService);
        $this->pantheonService ??= new PantheonService($this->configService);
        $this->magicDiscoveryService ??= new MagicDiscoveryService($this->configService);
        $this->mythologyService ??= new MythologyService($this->configService);
    }

    public function isEnabled(): bool
    {
        return (bool)$this->configService->getConfig('simulation', 'enabled', true);
    }

    public function setEnabled(bool $enabled): array
    {
        $this->configService->setConfig(
            'simulation',
            'enabled',
            $enabled,
            'boolean',
            'Whether the automated game loop should continue scheduling ticks'
        );

        return $this->getRuntimeStatus();
    }

    public function processTick(bool $advanceYear = true): array
    {
        $startedAt = date('c');
        $gameState = GameState::getCurrent();
        $previousYear = (int)$gameState->current_year;
        $tickYear = $advanceYear ? $previousYear + 1 : $previousYear;
        $previousTick = $this->configService->getConfig('simulation', 'last_tick_result', []);
        $previousEraPressure = is_array($previousTick) && isset($previousTick['eraPressure']) && is_array($previousTick['eraPressure'])
            ? $previousTick['eraPressure']
            : null;

        $result = [
            'startedAt' => $startedAt,
            'completedAt' => null,
            'previousYear' => $previousYear,
            'currentYear' => $tickYear,
            'advancedYear' => $advanceYear,
            'regions' => ['processed' => 0, 'changed' => 0, 'events' => 0, 'changes' => [], 'errors' => []],
            'settlements' => ['processed' => 0, 'changed' => 0, 'events' => 0, 'changes' => [], 'errors' => []],
            'heroes' => ['processed' => 0, 'changed' => 0, 'events' => 0, 'changes' => [], 'errors' => []],
            'champions' => ['processed' => 0, 'changed' => 0, 'events' => 0, 'outcomes' => [], 'errors' => []],
            'divineTools' => ['processed' => 0, 'changed' => 0, 'events' => 0, 'consequences' => [], 'chains' => [], 'errors' => []],
            'resources' => ['processed' => 0, 'changed' => 0, 'events' => 0, 'changes' => [], 'errors' => []],
            'civilization' => ['processed' => 0, 'decisions' => [], 'events' => 0, 'errors' => []],
            'pantheon' => ['processed' => 0, 'changed' => 0, 'events' => 0, 'interventions' => [], 'arcs' => [], 'errors' => []],
            'magicDiscovery' => ['processed' => 0, 'changed' => 0, 'events' => 0, 'progressions' => [], 'errors' => []],
            'mythology' => ['processed' => 0, 'changed' => 0, 'events' => 0, 'echoes' => [], 'errors' => []],
            'bets' => ['processed' => 0, 'won' => 0, 'lost' => 0, 'expired' => 0, 'resolved' => [], 'errors' => []],
            'divineFavor' => ['before' => 0, 'after' => 0, 'recovered' => self::DIVINE_FAVOR_PER_TICK],
            'eraPressure' => null,
            'eraLegacy' => null,
            'eraTransition' => null,
            'errors' => []
        ];

        try {
            $result['regions'] = $this->processRegions($tickYear);
            $result['settlements'] = $this->processSettlements($tickYear);
            $result['resources'] = $this->processResources($tickYear);
            $result['heroes'] = $this->processHeroes($tickYear);
            $result['champions'] = $this->championService->advanceWorld($tickYear);
            $result['divineTools'] = $this->processDivineToolConsequences($tickYear);
            $result['civilization'] = $this->civilizationBehaviorService->advanceWorld($tickYear, 1);
            $result['pantheon'] = $this->pantheonService->advanceWorld($tickYear, 1);
            $result['magicDiscovery'] = $this->magicDiscoveryService->advanceWorld($tickYear, 2);
            $result['mythology'] = $this->mythologyService->advanceWorld($tickYear, 2);
            $result['bets'] = $this->processActiveBets($tickYear);
            $result['divineFavor'] = $this->recoverDivineFavor();

            if ($advanceYear) {
                $gameState->current_year = $tickYear;
                $gameState->save();
            }

            $result['eraPressure'] = $this->eraPressureService->evaluate($tickYear);
            $result['eraLegacy'] = $this->eraLegacyService->evaluate($tickYear, $result['eraPressure']);
            $result['eraTransition'] = $this->eraTransitionService->status(
                $tickYear,
                $result['eraPressure'],
                $result['eraLegacy']
            );
            $eraPressureEventId = $this->recordEraPressureEventIfNeeded(
                $result['eraPressure'],
                $previousEraPressure,
                $tickYear
            );
            if ($eraPressureEventId !== null) {
                $result['eraPressure']['eventId'] = $eraPressureEventId;
            }
            if (($result['eraTransition']['eligible'] ?? false) === true) {
                $result['eraTransition'] = $this->eraTransitionService->transition(false);
                $result['currentYear'] = $result['eraTransition']['currentYear'];
            }

            $this->recordEvent(
                'World Tick Completed',
                "Year {$tickYear} advanced: {$result['regions']['changed']} regions, {$result['settlements']['changed']} settlements, {$result['heroes']['changed']} heroes, {$result['champions']['events']} champion outcomes, {$result['divineTools']['events']} divine tool consequences, {$result['civilization']['events']} civilization decisions, {$result['pantheon']['events']} pantheon interventions/arcs, {$result['magicDiscovery']['events']} magic progressions, {$result['mythology']['events']} myth echoes, and {$result['bets']['processed']} bets changed state. {$result['eraPressure']['summary']} {$result['eraLegacy']['summary']} {$result['eraTransition']['summary']}",
                'game_tick',
                null,
                [],
                [],
                $tickYear
            );
        } catch (\Throwable $error) {
            $result['errors'][] = $error->getMessage();
            Logger::error('Game tick failed', ['error' => $error->getMessage()]);
            throw $error;
        } finally {
            $result['completedAt'] = date('c');
            $this->storeTickResult($result);
        }

        return $result;
    }

    public function getRuntimeStatus(): array
    {
        $lastTick = $this->configService->getConfig('simulation', 'last_tick_result', []);
        $queueHealth = $this->getQueueHealth();

        return [
            'enabled' => $this->isEnabled(),
            'lastTickAt' => is_array($lastTick) ? ($lastTick['completedAt'] ?? null) : null,
            'lastTickResult' => $lastTick,
            'queue' => $queueHealth
        ];
    }

    public function processRegions(int $currentYear): array
    {
        $summary = ['processed' => 0, 'changed' => 0, 'events' => 0, 'changes' => [], 'errors' => []];

        foreach (Region::all() as $region) {
            $summary['processed']++;
            try {
                $oldProsperity = (int)$region->prosperity;
                $oldChaos = (int)$region->chaos;
                $oldDanger = (int)($region->danger_level ?? 0);
                $oldStatus = $region->status;
                $oldMagicAffinity = (int)$region->magic_affinity;
                $oldCulturalInfluence = (string)($region->cultural_influence ?? 'pastoral');
                $oldRegionalTraits = array_values(array_filter($region->regional_traits ?? []));

                $resourcePressure = $this->resourcePressureForRegion($region->id);
                $settlementPressure = $this->settlementPressureForRegion($region->id);
                $magicCulturePressure = $this->magicCulturePressureForRegion($region);
                $prosperityDelta = ($oldChaos > 70 ? -3 : ($oldChaos < 30 ? 2 : 1))
                    + $resourcePressure['prosperityDelta']
                    + $settlementPressure['prosperityDelta']
                    + $magicCulturePressure['prosperityDelta'];
                $chaosDelta = ($oldProsperity > 75 ? -2 : 1)
                    + $settlementPressure['chaosDelta']
                    + $magicCulturePressure['chaosDelta'];
                $dangerDelta = ($oldChaos >= 60 ? 2 : -1)
                    + $resourcePressure['dangerDelta']
                    + $settlementPressure['dangerDelta']
                    + $magicCulturePressure['dangerDelta'];

                $region->prosperity = $this->clamp($oldProsperity + $prosperityDelta);
                $region->chaos = $this->clamp($oldChaos + $chaosDelta);
                $region->danger_level = $this->clamp($oldDanger + $dangerDelta);
                $region->magic_affinity = $this->clamp($oldMagicAffinity + $magicCulturePressure['magicDelta']);
                $region->cultural_influence = $magicCulturePressure['culturalInfluence'];
                $region->regional_traits = $this->regionalTraitsAfterMagicCulturePressure(
                    $oldRegionalTraits,
                    $magicCulturePressure
                );
                $region->status = $this->regionStatusFor(
                    (int)$region->prosperity,
                    (int)$region->chaos,
                    (int)$region->danger_level,
                    (int)$region->magic_affinity,
                    $oldStatus
                );

                if (
                    $region->prosperity !== $oldProsperity ||
                    $region->chaos !== $oldChaos ||
                    $region->danger_level !== $oldDanger ||
                    $region->status !== $oldStatus ||
                    $region->magic_affinity !== $oldMagicAffinity ||
                    $region->cultural_influence !== $oldCulturalInfluence ||
                    $region->regional_traits !== $oldRegionalTraits
                ) {
                    $region->save();
                    $summary['changed']++;
                    $reason = "{$resourcePressure['summary']} {$settlementPressure['summary']} {$magicCulturePressure['summary']}";
                    $this->appendChange($summary, [
                        'id' => $region->id,
                        'name' => $region->name,
                        'type' => 'region',
                        'summary' => "{$region->name}: prosperity {$oldProsperity}->{$region->prosperity}, chaos {$oldChaos}->{$region->chaos}, danger {$oldDanger}->{$region->danger_level}, magic {$oldMagicAffinity}->{$region->magic_affinity}, culture {$oldCulturalInfluence}->{$region->cultural_influence}, status {$oldStatus}->{$region->status}.",
                        'before' => [
                            'prosperity' => $oldProsperity,
                            'chaos' => $oldChaos,
                            'dangerLevel' => $oldDanger,
                            'magicAffinity' => $oldMagicAffinity,
                            'culturalInfluence' => $oldCulturalInfluence,
                            'regionalTraits' => $oldRegionalTraits,
                            'status' => $oldStatus,
                        ],
                        'after' => [
                            'prosperity' => (int)$region->prosperity,
                            'chaos' => (int)$region->chaos,
                            'dangerLevel' => (int)$region->danger_level,
                            'magicAffinity' => (int)$region->magic_affinity,
                            'culturalInfluence' => (string)$region->cultural_influence,
                            'regionalTraits' => $region->regional_traits ?? [],
                            'status' => $region->status,
                        ],
                        'reason' => $reason,
                    ]);
                }

                $thresholdNotes = $this->regionThresholdNotes(
                    $oldProsperity,
                    (int)$region->prosperity,
                    $oldChaos,
                    (int)$region->chaos,
                    $oldDanger,
                    (int)$region->danger_level,
                    $oldStatus,
                    (string)$region->status,
                    $oldMagicAffinity,
                    (int)$region->magic_affinity,
                    $oldCulturalInfluence,
                    (string)$region->cultural_influence,
                    $oldRegionalTraits,
                    $region->regional_traits ?? []
                );

                if (
                    abs($region->prosperity - $oldProsperity) >= 3 ||
                    abs($region->chaos - $oldChaos) >= 3 ||
                    abs($region->danger_level - $oldDanger) >= 3 ||
                    abs($region->magic_affinity - $oldMagicAffinity) >= 3 ||
                    $region->cultural_influence !== $oldCulturalInfluence ||
                    $region->status !== $oldStatus ||
                    $thresholdNotes !== []
                ) {
                    $eventId = $this->recordEvent(
                        $this->regionEventTitle(
                            $oldStatus,
                            (string)$region->status,
                            $oldMagicAffinity,
                            (int)$region->magic_affinity,
                            $oldCulturalInfluence,
                            (string)$region->cultural_influence,
                            $thresholdNotes
                        ),
                        "{$region->name} shifted to prosperity {$region->prosperity}, chaos {$region->chaos}, danger {$region->danger_level}, magic {$region->magic_affinity}, culture {$region->cultural_influence}, and status {$region->status}. {$resourcePressure['summary']} {$settlementPressure['summary']} {$magicCulturePressure['summary']} {$this->sentenceFromNotes($thresholdNotes)}",
                        'region_tick',
                        $region->id,
                        $this->ids(array_merge(
                            [(string)$region->id],
                            $magicCulturePressure['relatedRegionIds'] ?? []
                        )),
                        $magicCulturePressure['heroIds'],
                        $currentYear,
                        $magicCulturePressure['settlementIds'],
                        $magicCulturePressure['landmarkIds'],
                        $magicCulturePressure['resourceIds']
                    );
                    $this->attachEventToLatestChange($summary, $region->id, $eventId);
                    $summary['events']++;
                }
            } catch (\Throwable $error) {
                $summary['errors'][] = ['id' => $region->id, 'message' => $error->getMessage()];
                Logger::error('Region tick failed', ['regionId' => $region->id, 'error' => $error->getMessage()]);
            }
        }

        return $summary;
    }

    public function processSettlements(int $currentYear): array
    {
        $summary = ['processed' => 0, 'changed' => 0, 'events' => 0, 'changes' => [], 'errors' => []];

        foreach (Settlement::all() as $settlement) {
            $summary['processed']++;
            try {
                $oldPopulation = (int)$settlement->population;
                $oldProsperity = (int)$settlement->prosperity;
                $oldDefensibility = (int)$settlement->defensibility;
                $region = $settlement->region;
                $oldStatus = $settlement->status;
                $oldType = $settlement->type;
                $oldSpecializations = $settlement->specializations ?? [];
                $oldTraits = $settlement->traits ?? [];

                $regionProsperity = $region ? (int)$region->prosperity : 50;
                $regionChaos = $region ? (int)$region->chaos : 50;
                $regionDanger = $region ? (int)$region->danger_level : 25;
                $resourcePressure = $this->resourcePressureForSettlement($settlement);
                $defensePressure = $this->defensePressureForSettlement($settlement, $regionDanger);
                $civicPressure = $this->civicPressureForSettlement($settlement);
                $growthRate = 0.02
                    + (($oldProsperity - 50) / 2500)
                    + (($regionProsperity - 50) / 3000)
                    - ($regionChaos / 5000)
                    + $resourcePressure['growthModifier']
                    + $defensePressure['growthModifier']
                    + $civicPressure['growthModifier'];
                $growthRate = max(-0.03, min(0.08, $growthRate));
                $newPopulation = max(0, (int)round($oldPopulation * (1 + $growthRate)));

                $prosperityDelta = ($regionChaos > 70 ? -3 : ($regionProsperity > 70 ? 2 : 1))
                    + $resourcePressure['prosperityDelta']
                    + $defensePressure['prosperityDelta']
                    + $civicPressure['prosperityDelta'];
                $newProsperity = $this->clamp($oldProsperity + $prosperityDelta);
                $newDefensibility = $this->clamp(
                    $oldDefensibility
                    + $defensePressure['defensibilityDelta']
                    + $civicPressure['defensibilityDelta']
                );
                $newSpecializations = $this->settlementSpecializationsAfterResourcePressure(
                    $oldSpecializations,
                    $resourcePressure
                );
                $newSpecializations = $this->settlementSpecializationsAfterCivicPressure(
                    $newSpecializations,
                    $civicPressure
                );
                $newTraits = $this->settlementTraitsAfterCivicPressure(
                    $oldTraits,
                    $civicPressure
                );
                $newTraits = $this->settlementTraitsAfterDefensePressure(
                    $newTraits,
                    $newDefensibility,
                    $regionDanger
                );

                $settlement->population = $newPopulation;
                $settlement->prosperity = $newProsperity;
                $settlement->defensibility = $newDefensibility;
                $settlement->status = $this->settlementStatusFor(
                    $newProsperity,
                    $newPopulation,
                    $newDefensibility,
                    $regionDanger,
                    $oldStatus
                );
                $settlement->last_event_year = $currentYear;
                $settlement->type = $this->settlementTypeFor($newPopulation, $settlement->type);
                $settlement->specializations = $newSpecializations;
                $settlement->traits = $newTraits;

                $changed = $newPopulation !== $oldPopulation ||
                    $newProsperity !== $oldProsperity ||
                    $newDefensibility !== $oldDefensibility ||
                    $settlement->status !== $oldStatus ||
                    $settlement->type !== $oldType ||
                    $newSpecializations !== $oldSpecializations ||
                    $newTraits !== $oldTraits;

                if ($changed) {
                    $settlement->save();
                    $summary['changed']++;
                    $reason = "{$resourcePressure['summary']} {$defensePressure['summary']} {$civicPressure['summary']}";
                    $this->appendChange($summary, [
                        'id' => $settlement->id,
                        'name' => $settlement->name,
                        'type' => 'settlement',
                        'regionId' => $settlement->region_id,
                        'summary' => "{$settlement->name}: population {$oldPopulation}->{$newPopulation}, prosperity {$oldProsperity}->{$newProsperity}, defense {$oldDefensibility}->{$newDefensibility}, status {$oldStatus}->{$settlement->status}.",
                        'before' => [
                            'population' => $oldPopulation,
                            'prosperity' => $oldProsperity,
                            'defensibility' => $oldDefensibility,
                            'status' => $oldStatus,
                            'type' => $oldType,
                        ],
                        'after' => [
                            'population' => $newPopulation,
                            'prosperity' => $newProsperity,
                            'defensibility' => $newDefensibility,
                            'status' => $settlement->status,
                            'type' => $settlement->type,
                        ],
                        'reason' => $reason,
                    ]);
                }

                $thresholdNotes = $this->settlementThresholdNotes(
                    $oldPopulation,
                    $newPopulation,
                    $oldProsperity,
                    $newProsperity,
                    $oldDefensibility,
                    $newDefensibility,
                    $oldStatus,
                    (string)$settlement->status,
                    $oldType,
                    (string)$settlement->type,
                    $oldSpecializations,
                    $newSpecializations,
                    $oldTraits,
                    $newTraits
                );

                if (
                    abs($newPopulation - $oldPopulation) >= max(10, (int)round($oldPopulation * 0.04)) ||
                    abs($newProsperity - $oldProsperity) >= 3 ||
                    abs($newDefensibility - $oldDefensibility) >= 2 ||
                    $settlement->status !== $oldStatus ||
                    $settlement->type !== $oldType ||
                    $thresholdNotes !== []
                ) {
                    $eventId = $this->recordEvent(
                        $this->settlementEventTitle($oldStatus, (string)$settlement->status, $oldType, (string)$settlement->type, $thresholdNotes),
                        "{$settlement->name} changed from population {$oldPopulation} to {$newPopulation}, prosperity {$oldProsperity} to {$newProsperity}, defense {$oldDefensibility} to {$newDefensibility}, and status {$oldStatus} to {$settlement->status}. {$resourcePressure['summary']} {$defensePressure['summary']} {$civicPressure['summary']} {$this->sentenceFromNotes($thresholdNotes)}",
                        'settlement_tick',
                        $settlement->region_id,
                        [$settlement->region_id],
                        $civicPressure['heroIds'],
                        $currentYear,
                        [$settlement->id],
                        $civicPressure['landmarkIds']
                    );
                    $this->attachEventToLatestChange($summary, $settlement->id, $eventId);
                    $summary['events']++;
                }
            } catch (\Throwable $error) {
                $summary['errors'][] = ['id' => $settlement->id, 'message' => $error->getMessage()];
                Logger::error('Settlement tick failed', ['settlementId' => $settlement->id, 'error' => $error->getMessage()]);
            }
        }

        $this->refreshRegionPopulationTotals();

        return $summary;
    }

    public function processResources(int $currentYear): array
    {
        $summary = ['processed' => 0, 'changed' => 0, 'events' => 0, 'changes' => [], 'errors' => []];

        foreach (ResourceNode::all() as $node) {
            $summary['processed']++;
            try {
                $oldOutput = (int)$node->output;
                $oldStatus = $node->status;
                $oldScarcityTier = $this->resourceScarcityTier($oldOutput, (string)$oldStatus);
                $outcome = $this->resourceTickOutcome($node, $oldOutput, $oldStatus);

                $node->output = $outcome['output'];
                $node->status = $outcome['status'];
                $resourceChanged = $node->output !== $oldOutput || $node->status !== $oldStatus;
                if ($resourceChanged) {
                    $node->save();
                }
                $settlementImpact = $this->applyResourceScarcitySettlementImpact(
                    $node,
                    $oldOutput,
                    (string)$oldStatus,
                    $outcome,
                    $currentYear
                );
                $thresholdNotes = $this->resourceThresholdNotes(
                    $oldOutput,
                    (int)$node->output,
                    (string)$oldStatus,
                    (string)$node->status,
                    $oldScarcityTier,
                    (string)$outcome['scarcityTier'],
                    $settlementImpact
                );

                if ($resourceChanged) {
                    $summary['changed']++;
                    $this->appendChange($summary, [
                        'id' => $node->id,
                        'name' => $node->name,
                        'type' => 'resource',
                        'regionId' => $node->region_id,
                        'summary' => "{$node->name}: output {$oldOutput}->{$node->output}, status {$oldStatus}->{$node->status}.",
                        'before' => [
                            'output' => $oldOutput,
                            'status' => $oldStatus,
                            'scarcityTier' => $oldScarcityTier,
                        ],
                        'after' => [
                            'output' => (int)$node->output,
                            'status' => $node->status,
                            'effectiveOutput' => $this->effectiveResourceOutput($node),
                            'scarcityTier' => $outcome['scarcityTier'],
                        ],
                        'reason' => $outcome['reason'] . ' ' . $settlementImpact['summary'],
                    ]);
                }

                if (
                    $node->status !== $oldStatus ||
                    abs((int)$node->output - $oldOutput) >= 4 ||
                    $thresholdNotes !== [] ||
                    ($settlementImpact['changed'] ?? false)
                ) {
                    $eventId = $this->recordEvent(
                        $this->resourceEventTitle(
                            $oldStatus,
                            (string)$node->status,
                            $oldOutput,
                            (int)$node->output,
                            $oldScarcityTier,
                            (string)$outcome['scarcityTier']
                        ),
                        "{$node->name} shifted from {$oldStatus} output {$oldOutput} ({$oldScarcityTier}) to {$node->status} output {$node->output} ({$outcome['scarcityTier']}). {$outcome['reason']} {$settlementImpact['summary']} {$this->sentenceFromNotes($thresholdNotes)}",
                        'resource_tick',
                        $node->region_id,
                        [$node->region_id],
                        $this->resourceRelatedHeroIds($node, (string)$node->status),
                        $currentYear,
                        $node->settlement_id ? [$node->settlement_id] : [],
                        [],
                        [$node->id]
                    );
                    $this->attachEventToLatestChange($summary, $node->id, $eventId);
                    $summary['events']++;
                }
            } catch (\Throwable $error) {
                $summary['errors'][] = ['id' => $node->id, 'message' => $error->getMessage()];
                Logger::error('Resource tick failed', ['resourceNodeId' => $node->id, 'error' => $error->getMessage()]);
            }
        }

        return $summary;
    }

    public function processHeroes(int $currentYear): array
    {
        $summary = ['processed' => 0, 'changed' => 0, 'events' => 0, 'changes' => [], 'errors' => []];

        foreach (Hero::where('is_alive', true)->get() as $hero) {
            $summary['processed']++;
            try {
                $oldLevel = (int)$hero->level;
                $oldRegionId = $hero->region_id;
                $oldAge = (int)$hero->age;
                $oldStatus = $hero->status;
                $oldAlignment = $this->normalHeroAlignment($hero->alignment);
                $oldTraits = array_values(array_filter($hero->personality_traits ?? []));

                $hero->age = (int)$hero->age + 1;

                if ($this->roll($hero->calculateLevelUpChance())) {
                    $hero->level = min(100, $oldLevel + 1);
                    if ($hero->isMilestoneLevel()) {
                        $hero->addFeat("Reached level {$hero->level} in year {$currentYear}.");
                    }
                }

                if ($this->roll(0.12)) {
                    $newRegion = Region::where('id', '!=', $hero->region_id)->inRandomOrder()->first();
                    if ($newRegion) {
                        $hero->region_id = $newRegion->id;
                    }
                }

                $identityPressure = $this->heroIdentityPressure($hero);
                $this->applyHeroIdentityPressure($hero, $identityPressure);
                $settlementInteraction = $this->recordHeroSettlementInteraction(
                    $hero,
                    $identityPressure,
                    $currentYear
                );

                if ($this->shouldHeroDie($hero)) {
                    $hero->is_alive = false;
                    $hero->status = 'deceased';
                    $hero->death_reason = $this->getDeathReason();
                }

                $hero->save();
                $summary['changed']++;
                $newAlignment = $this->normalHeroAlignment($hero->alignment);
                $newTraits = array_values(array_filter($hero->personality_traits ?? []));
                $heroChanges = [];
                if ((int)$hero->level !== $oldLevel) {
                    $heroChanges[] = "level {$oldLevel}->{$hero->level}";
                }
                if ($hero->region_id !== $oldRegionId) {
                    $heroChanges[] = "region {$oldRegionId}->{$hero->region_id}";
                }
                if ($hero->status !== $oldStatus) {
                    $heroChanges[] = "status {$oldStatus}->{$hero->status}";
                }
                if (!$hero->is_alive) {
                    $heroChanges[] = 'died';
                }
                if ($newAlignment !== $oldAlignment) {
                    $heroChanges[] = "alignment {$oldAlignment['good']}/{$oldAlignment['chaotic']}->{$newAlignment['good']}/{$newAlignment['chaotic']}";
                }
                if ($newTraits !== $oldTraits) {
                    $addedTraits = array_values(array_diff($newTraits, $oldTraits));
                    if ($addedTraits !== []) {
                        $heroChanges[] = 'traits +' . implode(', ', $addedTraits);
                    }
                }
                if ($settlementInteraction !== null) {
                    $heroChanges[] = "settlement bond {$settlementInteraction['targetName']}";
                }

                if ($heroChanges !== []) {
                    $this->appendChange($summary, [
                        'id' => $hero->id,
                        'name' => $hero->name,
                        'type' => 'hero',
                        'regionId' => $hero->region_id,
                        'summary' => "{$hero->name}: " . implode(', ', $heroChanges) . '.',
                        'before' => [
                            'age' => $oldAge,
                            'level' => $oldLevel,
                            'regionId' => $oldRegionId,
                            'status' => $oldStatus,
                            'alignment' => $oldAlignment,
                            'personalityTraits' => $oldTraits,
                        ],
                        'after' => [
                            'age' => (int)$hero->age,
                            'level' => (int)$hero->level,
                            'regionId' => $hero->region_id,
                            'status' => $hero->status,
                            'isAlive' => (bool)$hero->is_alive,
                            'alignment' => $newAlignment,
                            'personalityTraits' => $newTraits,
                        ],
                        'reason' => $identityPressure['summary'],
                    ]);
                }

                if ((int)$hero->level !== $oldLevel) {
                    $eventId = $this->recordEvent(
                        'Hero Advanced',
                        "{$hero->name} reached level {$hero->level}.",
                        'hero_level',
                        $hero->region_id,
                        [$hero->region_id],
                        [$hero->id],
                        $currentYear
                    );
                    $this->attachEventToLatestChange($summary, $hero->id, $eventId);
                    $summary['events']++;
                }

                if ($hero->region_id !== $oldRegionId) {
                    $eventId = $this->recordEvent(
                        'Hero Travel',
                        "{$hero->name} traveled from {$oldRegionId} to {$hero->region_id}.",
                        'hero_travel',
                        $hero->region_id,
                        [$oldRegionId, $hero->region_id],
                        [$hero->id],
                        $currentYear
                    );
                    $this->attachEventToLatestChange($summary, $hero->id, $eventId);
                    $summary['events']++;
                }

                if (
                    ($identityPressure['shouldRecordEvent'] ?? false) &&
                    ($settlementInteraction !== null || $newAlignment !== $oldAlignment || $newTraits !== $oldTraits)
                ) {
                    $interactionSummary = $settlementInteraction
                        ? " {$settlementInteraction['outcomeDescription']}"
                        : '';
                    $eventId = $this->recordEvent(
                        'Hero Civic Bond',
                        "{$hero->name} responded to regional pressure. {$identityPressure['summary']}{$interactionSummary}",
                        'hero_civic_bond',
                        $hero->region_id,
                        [$hero->region_id],
                        [$hero->id],
                        $currentYear,
                        $identityPressure['settlementIds'],
                        [],
                        $identityPressure['resourceIds']
                    );
                    $this->attachEventToLatestChange($summary, $hero->id, $eventId);
                    $summary['events']++;
                }

                if (!$hero->is_alive) {
                    $eventId = $this->recordEvent(
                        'Hero Fallen',
                        "{$hero->name} died: {$hero->death_reason}.",
                        'hero_death',
                        $hero->region_id,
                        [$hero->region_id],
                        [$hero->id],
                        $currentYear
                    );
                    $this->attachEventToLatestChange($summary, $hero->id, $eventId);
                    $summary['events']++;
                }
            } catch (\Throwable $error) {
                $summary['errors'][] = ['id' => $hero->id, 'message' => $error->getMessage()];
                Logger::error('Hero tick failed', ['heroId' => $hero->id, 'error' => $error->getMessage()]);
            }
        }

        return $summary;
    }

    public function processExpiredBets(int $currentYear): array
    {
        return $this->processActiveBets($currentYear);
    }

    private function processDivineToolConsequences(int $currentYear): array
    {
        $summary = [
            'processed' => 0,
            'changed' => 0,
            'events' => 0,
            'artifacts' => null,
            'weather' => null,
            'omens' => null,
            'consequences' => [],
            'chains' => [],
            'errors' => [],
        ];

        $sections = [
            'artifacts' => fn(): array => $this->artifactService->advanceWorld($currentYear),
            'weather' => fn(): array => $this->weatherInfluenceService->advanceWorld($currentYear),
            'omens' => fn(): array => $this->temporalOmenService->advanceWorld($currentYear),
        ];

        foreach ($sections as $key => $resolver) {
            try {
                $section = $resolver();
                $summary[$key] = $section;
                $summary['processed'] += (int)($section['processed'] ?? 0);
                $summary['changed'] += (int)($section['changed'] ?? 0);
                $summary['events'] += (int)($section['events'] ?? 0);
                $summary['consequences'] = array_values(array_merge(
                    $summary['consequences'],
                    $section['consequences'] ?? [],
                    $section['followUps'] ?? [],
                    $section['chains'] ?? []
                ));
                $summary['chains'] = array_values(array_merge($summary['chains'], $section['chains'] ?? []));
                foreach (($section['errors'] ?? []) as $error) {
                    $summary['errors'][] = is_array($error)
                        ? array_merge(['tool' => $key], $error)
                        : ['tool' => $key, 'message' => (string)$error];
                }
            } catch (\Throwable $error) {
                $summary['errors'][] = ['tool' => $key, 'message' => $error->getMessage()];
            }
        }

        return $summary;
    }

    public function processActiveBets(int $currentYear): array
    {
        $summary = ['processed' => 0, 'won' => 0, 'lost' => 0, 'expired' => 0, 'resolved' => [], 'errors' => []];

        foreach (DivineBet::where('status', 'active')->get() as $bet) {
            try {
                $resolution = $this->evaluateBet($bet, $currentYear);
                if ($resolution['status'] === 'active') {
                    $this->refreshBetOdds($bet);
                    continue;
                }

                $bet->resolve($resolution['status'], $currentYear, $resolution['notes']);
                $summary['processed']++;
                $summary[$resolution['status']]++;

                if ($resolution['status'] === 'won') {
                    $player = Player::firstOrCreate(
                        ['id' => $bet->player_id ?: 'SINGLE_PLAYER'],
                        ['divine_favor' => 100]
                    );
                    $player->addDivineFavor((int)$bet->potential_payout);
                }

                $targetLinks = $this->relatedEventIdsForTarget($this->findBetTarget($bet->target_id));
                $relatedRegionIds = array_values(array_unique(array_filter(array_merge(
                    $resolution['regionId'] ? [$resolution['regionId']] : [],
                    $targetLinks['regions']
                ))));
                $relatedHeroIds = array_values(array_unique(array_filter(array_merge(
                    $resolution['heroId'] ? [$resolution['heroId']] : [],
                    $targetLinks['heroes']
                ))));

                $eventId = $this->recordEvent(
                    'Divine Bet Resolved',
                    $resolution['notes'],
                    'bet_resolution',
                    $resolution['regionId'],
                    $relatedRegionIds,
                    $relatedHeroIds,
                    $currentYear,
                    $targetLinks['settlements'],
                    $targetLinks['landmarks'],
                    $targetLinks['resources']
                );

                $this->appendResolvedBet($summary, [
                    'id' => $bet->id,
                    'description' => $bet->description,
                    'targetId' => $bet->target_id,
                    'betType' => $bet->bet_type,
                    'status' => $resolution['status'],
                    'notes' => $resolution['notes'],
                    'resolvedYear' => $currentYear,
                    'regionId' => $resolution['regionId'],
                    'heroId' => $resolution['heroId'],
                    'payout' => $resolution['status'] === 'won' ? (int)$bet->potential_payout : 0,
                    'eventId' => $eventId,
                ]);
            } catch (\Throwable $error) {
                $summary['errors'][] = ['id' => $bet->id, 'message' => $error->getMessage()];
                Logger::error('Bet resolution failed', ['betId' => $bet->id, 'error' => $error->getMessage()]);
            }
        }

        return $summary;
    }

    private function evaluateBet(DivineBet $bet, int $currentYear): array
    {
        $expired = $bet->hasExpired($currentYear);
        $target = $this->findBetTarget($bet->target_id);
        if (!$target) {
            return [
                'status' => $expired ? 'expired' : 'active',
                'notes' => "The target for {$bet->description} no longer exists.",
                'regionId' => null,
                'heroId' => null
            ];
        }

        $won = false;
        $notes = '';
        $regionId = $target['regionId'] ?? null;
        $heroId = $target['type'] === 'hero' ? $target['model']->id : null;

        switch ($bet->bet_type) {
            case 'settlement_growth':
                $won = $target['type'] === 'settlement' &&
                    ((int)$target['model']->population >= 2000 || in_array($target['model']->type, ['town', 'city', 'metropolis'], true));
                $notes = $won
                    ? "{$target['model']->name} grew into a major settlement."
                    : "{$target['model']->name} has not yet shown enough growth.";
                break;

            case 'landmark_discovery':
                $won = $this->hasLandmarkDiscovery($target, (int)$bet->placed_year, $currentYear);
                $notes = $won
                    ? "A landmark tied to {$target['name']} was discovered in the predicted window."
                    : "No matching landmark discovery has happened for {$target['name']}.";
                break;

            case 'cultural_shift':
                $cultureShiftEvent = $target['type'] === 'region'
                    ? $this->recentCultureShiftForRegion((string)$target['model']->id, (int)$bet->placed_year, $currentYear)
                    : null;
                $won = $target['type'] === 'region' && (
                    in_array($target['model']->cultural_influence, ['scholarly', 'mystical', 'martial', 'mercantile'], true) ||
                    $cultureShiftEvent instanceof GameEvent
                );
                $notes = $won
                    ? ($cultureShiftEvent instanceof GameEvent
                        ? "{$target['model']->name} recorded a culture-shift event: {$cultureShiftEvent->title}."
                        : "{$target['model']->name} now shows a strong {$target['model']->cultural_influence} cultural identity.")
                    : "{$target['name']} has not shifted culture enough.";
                break;

            case 'hero_settlement_bond':
            case 'hero_location_visit':
                $won = $target['type'] === 'hero' && $this->hasRecentHeroEvent($target['model']->id, $bet->placed_year, $currentYear);
                $notes = $won
                    ? "{$target['model']->name} became part of a recorded location or settlement event."
                    : "{$target['name']} has no matching recorded visit or bond yet.";
                break;

            case 'settlement_transformation':
                $won = $target['type'] === 'settlement' &&
                    (in_array($target['model']->status, ['thriving', 'prosperous', 'ruined', 'abandoned'], true) || in_array($target['model']->type, ['city', 'metropolis'], true));
                $notes = $won
                    ? "{$target['model']->name} transformed into {$target['model']->status} {$target['model']->type}."
                    : "{$target['name']} has not transformed enough.";
                break;

            case 'corruption_spread':
                $won = ($target['type'] === 'region' && ((int)$target['model']->chaos >= 70 || in_array($target['model']->status, ['cursed', 'war_torn'], true))) ||
                    ($target['type'] === 'landmark' && $target['model']->status === 'corrupted') ||
                    ($target['type'] === 'resource' && $target['model']->status === 'corrupted');
                $notes = $won
                    ? "Corruption overtook {$target['name']}."
                    : "Corruption has not overtaken {$target['name']}.";
                break;

            case 'hero_level_milestone':
                $championOutcome = $target['type'] === 'hero'
                    ? $this->recentChampionOutcomeForHero((string)$target['model']->id, (int)$bet->placed_year, $currentYear)
                    : null;
                $won = $target['type'] === 'hero' && ((int)$target['model']->level >= 5 || $championOutcome instanceof GameEvent);
                $notes = $won
                    ? ($championOutcome instanceof GameEvent
                        ? "{$target['model']->name} completed a champion outcome: {$championOutcome->title}."
                        : "{$target['model']->name} reached level {$target['model']->level}.")
                    : "{$target['name']} has not reached a level or champion milestone.";
                break;

            case 'hero_death':
                $won = $target['type'] === 'hero' && !$target['model']->is_alive;
                $notes = $won
                    ? "{$target['model']->name} died as predicted."
                    : "{$target['name']} still lives.";
                break;

            case 'region_danger_change':
                $championRivalry = $target['type'] === 'region'
                    ? $this->recentChampionRivalryForRegion((string)$target['model']->id, (int)$bet->placed_year, $currentYear)
                    : null;
                $won = $target['type'] === 'region' && (
                    (int)$target['model']->danger_level >= 70 ||
                    (int)$target['model']->danger_level <= 10 ||
                    $championRivalry instanceof GameEvent
                );
                $notes = $won
                    ? ($championRivalry instanceof GameEvent
                        ? "{$target['model']->name} changed through champion rivalry: {$championRivalry->title}."
                        : "{$target['model']->name} reached an extreme danger level of {$target['model']->danger_level}.")
                    : "{$target['name']} has not reached an extreme danger state or champion rivalry outcome.";
                break;

            case 'prosperity_threshold':
                $won = $target['type'] === 'settlement' && (int)$target['model']->prosperity >= 80;
                $notes = $won
                    ? "{$target['model']->name} reached prosperity {$target['model']->prosperity}."
                    : "{$target['name']} has not reached the prosperity threshold.";
                break;

            case 'magic_discovery':
                $resolution = $this->evaluateMagicDiscoveryBet(
                    (string)$target['model']->id,
                    (string)$bet->description,
                    (int)$bet->placed_year,
                    $currentYear
                );
                $won = $resolution['won'];
                $notes = $resolution['notes'];
                break;

            case 'pantheon_intervention':
                $pantheonEvent = $target['type'] === 'region'
                    ? $this->recentPantheonInterventionForRegion((string)$target['model']->id, (int)$bet->placed_year, $currentYear)
                    : null;
                $won = $pantheonEvent instanceof GameEvent;
                $notes = $won
                    ? "{$target['model']->name} drew pantheon intervention: {$pantheonEvent->title}."
                    : "{$target['name']} has not drawn a pantheon intervention yet.";
                break;

            case 'civilization_agenda':
                $civilizationEvent = $target['type'] === 'region'
                    ? $this->recentCivilizationAgendaForRegion((string)$target['model']->id, (int)$bet->placed_year, $currentYear, (string)$bet->description)
                    : null;
                $won = $civilizationEvent instanceof GameEvent;
                $notes = $won
                    ? "{$target['model']->name} followed a civic agenda: {$civilizationEvent->title}."
                    : "{$target['name']} has not recorded the predicted civic agenda yet.";
                break;

            default:
                $notes = "{$bet->description} has no implemented resolution rule.";
        }

        if ($won) {
            return ['status' => 'won', 'notes' => $notes, 'regionId' => $regionId, 'heroId' => $heroId];
        }

        if ($expired) {
            return [
                'status' => 'lost',
                'notes' => $notes . " The prediction window closed in year {$currentYear}.",
                'regionId' => $regionId,
                'heroId' => $heroId
            ];
        }

        return ['status' => 'active', 'notes' => $notes, 'regionId' => $regionId, 'heroId' => $heroId];
    }

    private function evaluateMagicDiscoveryBet(string $targetId, string $description, int $placedYear, int $currentYear): array
    {
        $paths = $this->magicDiscoveryService->pathsForTarget($targetId);
        if ($paths === []) {
            return [
                'won' => false,
                'notes' => 'No emerging magic path is currently tied to the wagered target.',
            ];
        }

        $matchingPaths = array_values(array_filter(
            $paths,
            function (array $path) use ($description): bool {
                $label = (string)($path['label'] ?? '');
                return $label !== '' && stripos($description, $label) !== false;
            }
        ));
        if ($matchingPaths !== []) {
            $paths = $matchingPaths;
        }

        foreach ($paths as $path) {
            $discoveryYear = $this->magicPathDiscoveryYearInWindow($path, $placedYear, $currentYear);
            if ($discoveryYear === null) {
                continue;
            }

            return [
                'won' => true,
                'notes' => "{$path['label']} became known through the wagered target in year {$discoveryYear}.",
            ];
        }

        $best = $paths[0];
        return [
            'won' => false,
            'notes' => "{$best['label']} is {$best['status']} at {$best['progress']}% progress and has not become known inside the prediction window.",
        ];
    }

    private function magicPathDiscoveryYearInWindow(array $path, int $placedYear, int $currentYear): ?int
    {
        $discoveryYear = isset($path['discoveryYear']) ? (int)$path['discoveryYear'] : null;
        if (
            (string)($path['status'] ?? '') === 'known' &&
            $discoveryYear !== null &&
            $discoveryYear >= $placedYear &&
            $discoveryYear <= $currentYear
        ) {
            return $discoveryYear;
        }

        foreach (($path['history'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $year = (int)($entry['year'] ?? 0);
            if (
                (string)($entry['type'] ?? '') === 'magic_discovery' &&
                $year >= $placedYear &&
                $year <= $currentYear
            ) {
                return $year;
            }
        }

        return null;
    }

    private function findBetTarget(string $targetId): ?array
    {
        if ($settlement = Settlement::find($targetId)) {
            return ['type' => 'settlement', 'name' => $settlement->name, 'model' => $settlement, 'regionId' => $settlement->region_id];
        }
        if ($hero = Hero::find($targetId)) {
            return ['type' => 'hero', 'name' => $hero->name, 'model' => $hero, 'regionId' => $hero->region_id];
        }
        if ($region = Region::find($targetId)) {
            return ['type' => 'region', 'name' => $region->name, 'model' => $region, 'regionId' => $region->id];
        }
        if ($resource = ResourceNode::find($targetId)) {
            return ['type' => 'resource', 'name' => $resource->name, 'model' => $resource, 'regionId' => $resource->region_id];
        }
        if ($landmark = \App\Models\Landmark::find($targetId)) {
            return ['type' => 'landmark', 'name' => $landmark->name, 'model' => $landmark, 'regionId' => $landmark->region_id];
        }

        return null;
    }

    private function relatedEventIdsForTarget(?array $target): array
    {
        $links = [
            'regions' => [],
            'heroes' => [],
            'settlements' => [],
            'landmarks' => [],
            'resources' => [],
        ];

        if (!$target) {
            return $links;
        }

        $model = $target['model'];
        if (!empty($target['regionId'])) {
            $links['regions'][] = $target['regionId'];
        }

        switch ($target['type']) {
            case 'region':
                $links['regions'][] = $model->id;
                break;
            case 'hero':
                $links['heroes'][] = $model->id;
                break;
            case 'settlement':
                $links['settlements'][] = $model->id;
                break;
            case 'landmark':
                $links['landmarks'][] = $model->id;
                break;
            case 'resource':
                $links['resources'][] = $model->id;
                if (!empty($model->settlement_id)) {
                    $links['settlements'][] = $model->settlement_id;
                }
                break;
        }

        foreach ($links as $key => $ids) {
            $links[$key] = array_values(array_unique(array_filter($ids)));
        }

        return $links;
    }

    private function hasLandmarkDiscovery(array $target, int $placedYear, int $currentYear): bool
    {
        if ($target['type'] === 'landmark') {
            $year = $target['model']->discovered_year;
            return $year !== null && (int)$year >= $placedYear && (int)$year <= $currentYear;
        }

        if ($target['type'] === 'region') {
            return \App\Models\Landmark::where('region_id', $target['model']->id)
                ->whereNotNull('discovered_year')
                ->whereBetween('discovered_year', [$placedYear, $currentYear])
                ->exists();
        }

        return false;
    }

    private function hasRecentHeroEvent(string $heroId, int $placedYear, int $currentYear): bool
    {
        return GameEvent::whereJsonContains('related_hero_ids', $heroId)
            ->whereBetween('year', [$placedYear, $currentYear])
            ->exists();
    }

    private function recentChampionOutcomeForHero(string $heroId, int $placedYear, int $currentYear): ?GameEvent
    {
        return GameEvent::whereJsonContains('related_hero_ids', $heroId)
            ->whereIn('type', [
                'champion_quest_completed',
                'champion_rivalry_resolved',
                'champion_rivalry_escalated',
            ])
            ->whereBetween('year', [$placedYear, $currentYear])
            ->orderBy('year', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    private function recentChampionRivalryForRegion(string $regionId, int $placedYear, int $currentYear): ?GameEvent
    {
        return GameEvent::where(function ($query) use ($regionId): void {
            $query->where('region_id', $regionId)
                ->orWhereJsonContains('related_region_ids', $regionId);
        })
            ->whereIn('type', ['champion_rivalry_resolved', 'champion_rivalry_escalated'])
            ->whereBetween('year', [$placedYear, $currentYear])
            ->orderBy('year', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    private function recentCultureShiftForRegion(string $regionId, int $placedYear, int $currentYear): ?GameEvent
    {
        return GameEvent::where(function ($query) use ($regionId): void {
            $query->where('region_id', $regionId)
                ->orWhereJsonContains('related_region_ids', $regionId);
        })
            ->where('type', 'region_tick')
            ->where('title', 'Regional Culture Shift')
            ->whereBetween('year', [$placedYear, $currentYear])
            ->orderBy('year', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    private function recentPantheonInterventionForRegion(string $regionId, int $placedYear, int $currentYear): ?GameEvent
    {
        return GameEvent::where(function ($query) use ($regionId): void {
            $query->where('region_id', $regionId)
                ->orWhereJsonContains('related_region_ids', $regionId);
        })
            ->where('type', 'pantheon_intervention')
            ->whereBetween('year', [$placedYear, $currentYear])
            ->orderBy('year', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    private function recentCivilizationAgendaForRegion(string $regionId, int $placedYear, int $currentYear, string $description): ?GameEvent
    {
        $events = GameEvent::where(function ($query) use ($regionId): void {
            $query->where('region_id', $regionId)
                ->orWhereJsonContains('related_region_ids', $regionId);
        })
            ->where('type', 'civilization_behavior')
            ->whereBetween('year', [$placedYear, $currentYear])
            ->orderBy('year', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($events as $event) {
            $eventLabel = trim(str_replace('Civilization Agenda:', '', (string)$event->title));
            if ($eventLabel !== '' && stripos($description, $eventLabel) !== false) {
                return $event;
            }
        }

        return $events->first();
    }

    private function refreshBetOdds(DivineBet $bet): void
    {
        $target = $this->findBetTarget($bet->target_id);
        if (!$target) {
            return;
        }

        $odds = DivineBet::getBaseOddsForType($bet->bet_type) * DivineBet::getConfidenceModifier($bet->confidence);

        if ($bet->bet_type === 'magic_discovery') {
            $odds *= $this->magicDiscoveryOddsModifier((string)$target['model']->id);
            $bet->updateOdds(max(1.1, round($odds, 2)));
            return;
        }

        if ($bet->bet_type === 'pantheon_intervention' && $target['type'] === 'region') {
            $odds *= $this->pantheonInterventionOddsModifier((string)$target['model']->id);
            $bet->updateOdds(max(1.1, round($odds, 2)));
            return;
        }

        if ($bet->bet_type === 'civilization_agenda' && $target['type'] === 'region') {
            $odds *= $this->civilizationAgendaOddsModifier((string)$target['model']->id);
            $bet->updateOdds(max(1.1, round($odds, 2)));
            return;
        }

        if ($target['type'] === 'region') {
            $chaos = (int)$target['model']->chaos;
            $prosperity = (int)$target['model']->prosperity;
            $danger = (int)$target['model']->danger_level;
            if (in_array($bet->bet_type, ['corruption_spread', 'region_danger_change'], true)) {
                $odds *= max(0.5, 1.5 - (($chaos + $danger) / 180));
            } else {
                $odds *= max(0.6, 1.4 - ($prosperity / 150));
            }
        } elseif ($target['type'] === 'hero') {
            $level = (int)$target['model']->level;
            $age = (int)$target['model']->age;
            $odds *= $bet->bet_type === 'hero_death'
                ? max(0.6, 1.8 - ($age / 100))
                : max(0.5, 1.4 - ($level / 60));
        } elseif ($target['type'] === 'settlement') {
            $prosperity = (int)$target['model']->prosperity;
            $population = (int)$target['model']->population;
            $odds *= max(0.5, 1.5 - (($prosperity / 140) + min($population, 5000) / 10000));
        }

        $bet->updateOdds(max(1.1, round($odds, 2)));
    }

    private function magicDiscoveryOddsModifier(string $targetId): float
    {
        $path = $this->magicDiscoveryService->pathsForTarget($targetId)[0] ?? null;
        if (!is_array($path)) {
            return 1.35;
        }

        $progress = max(0, min(100, (int)($path['progress'] ?? 0)));
        $evidence = max(0, min(100, (int)($path['evidenceScore'] ?? 0)));
        $readiness = ($progress * 0.7) + ($evidence * 0.3);

        return max(0.55, min(1.45, 1.45 - ($readiness / 120)));
    }

    private function pantheonInterventionOddsModifier(string $regionId): float
    {
        try {
            $pressureScore = 0;
            foreach ($this->pantheonService->status()['pressure'] ?? [] as $entry) {
                if (!is_array($entry) || (string)($entry['targetRegionId'] ?? '') !== $regionId) {
                    continue;
                }

                $pressureScore = max($pressureScore, (int)($entry['pressureScore'] ?? 0));
            }

            if ($pressureScore <= 0) {
                return 1.35;
            }

            return max(0.55, min(1.45, 1.35 - ($pressureScore / 140)));
        } catch (\Throwable $error) {
            Logger::error('Error calculating pantheon intervention odds modifier', ['error' => $error->getMessage()]);
            return 1.0;
        }
    }

    private function civilizationAgendaOddsModifier(string $regionId): float
    {
        try {
            $agendaScore = 0;
            foreach ($this->civilizationBehaviorService->status()['regionAgendas'] ?? [] as $agenda) {
                if (!is_array($agenda) || (string)($agenda['regionId'] ?? '') !== $regionId) {
                    continue;
                }

                $agendaScore = max($agendaScore, (int)($agenda['score'] ?? 0));
            }

            if ($agendaScore <= 0) {
                return 1.35;
            }

            return max(0.55, min(1.45, 1.42 - ($agendaScore / 130)));
        } catch (\Throwable $error) {
            Logger::error('Error calculating civilization agenda odds modifier', ['error' => $error->getMessage()]);
            return 1.0;
        }
    }

    private function resourceTickOutcome(ResourceNode $node, int $oldOutput, string $oldStatus): array
    {
        $region = $node->region;
        $settlement = $node->settlement;
        $type = $node->resourceNodeType;
        $renewalRate = $type ? (int)$type->renewal_rate : 0;
        $isRenewable = $renewalRate > 0;
        $regionProsperity = $region ? (int)$region->prosperity : 50;
        $regionChaos = $region ? (int)$region->chaos : 50;
        $regionDanger = $region ? (int)$region->danger_level : 25;
        $magicAffinity = $region ? (int)$region->magic_affinity : 50;
        $settlementPopulation = $settlement ? (int)$settlement->population : 0;
        $settlementProsperity = $settlement ? (int)$settlement->prosperity : 50;
        $settlementDefense = $settlement ? (int)$settlement->defensibility : 35;
        $settlementDemand = $settlement ? min(3, max(0, intdiv($settlementPopulation, 2000))) : 0;
        if ($settlement && $settlementProsperity >= 75) {
            $settlementDemand++;
        }

        $renewalRecovery = $isRenewable ? max(1, (int)ceil($renewalRate / 20)) : 0;
        $dangerPressure = max(0, intdiv(max($regionChaos, $regionDanger) - 45, 15));
        $status = $oldStatus;
        $delta = 0;
        $notes = [
            "Region pressure used prosperity {$regionProsperity}, chaos {$regionChaos}, and danger {$regionDanger}.",
        ];

        if ($settlement) {
            $notes[] = "{$settlement->name} added demand {$settlementDemand} and defense {$settlementDefense}.";
        }

        switch ($oldStatus) {
            case 'active':
                $delta = random_int(-2, 3) + $renewalRecovery - max(0, $settlementDemand - 1);
                if ($oldOutput >= 78 && $regionProsperity >= 70 && $regionDanger <= 45) {
                    $status = 'status-flourishing';
                    $notes[] = 'Stable conditions pushed the site into flourishing output.';
                } elseif ($settlementDemand >= 3 && $oldOutput <= 70) {
                    $status = 'overworked';
                    $notes[] = 'Nearby demand overworked the site.';
                } elseif ($dangerPressure >= 2 && $this->roll(0.12 + ($dangerPressure * 0.06))) {
                    $status = 'contested';
                    $notes[] = 'Danger around the region made extraction contested.';
                } elseif ($node->isMagical() && $regionChaos >= 60 && $this->roll(0.14)) {
                    $status = 'unstable';
                    $notes[] = 'Arcane pressure made the resource unstable.';
                }
                break;

            case 'blessed':
                $delta = random_int(0, 4) + $renewalRecovery;
                if ($regionChaos >= 75 && $this->roll(0.10)) {
                    $status = 'active';
                    $notes[] = 'Regional chaos weakened the blessing.';
                }
                break;

            case 'status-flourishing':
                $delta = random_int(-1, 2) + $renewalRecovery;
                if ($regionDanger >= 60 || $regionChaos >= 60 || $oldOutput < 65) {
                    $status = 'active';
                    $notes[] = 'The flourishing surge settled back into normal production.';
                }
                break;

            case 'overworked':
                $delta = -random_int(2, 5) + $renewalRecovery - ($settlementDemand >= 3 ? 1 : 0);
                if ($oldOutput <= 25) {
                    $status = 'depleted';
                    $notes[] = 'Overuse exhausted the remaining reserves.';
                } elseif ($settlementDemand <= 1 && $regionProsperity >= 55) {
                    $status = 'active';
                    $notes[] = 'Reduced demand let the site stabilize.';
                }
                break;

            case 'contested':
                $delta = -random_int(1, 5);
                $securityChance = $settlement
                    ? (($settlementDefense + $regionProsperity - $regionDanger) / 220)
                    : (($regionProsperity - $regionDanger) / 260);
                if ($this->roll(max(0.05, min(0.45, $securityChance)))) {
                    $status = 'active';
                    $delta += 2;
                    $notes[] = 'Local defenses secured the contested site.';
                } elseif ($regionChaos >= 65 && $this->roll(0.14 + ($dangerPressure * 0.03))) {
                    $status = 'corrupted';
                    $notes[] = 'Unresolved conflict let corruption take root.';
                }
                break;

            case 'corrupted':
                $delta = -random_int(2, 6);
                $cleanseChance = (($regionProsperity + $magicAffinity) - ($regionChaos + $regionDanger)) / 230;
                if ($this->roll(max(0.02, min(0.30, $cleanseChance)))) {
                    $status = 'unstable';
                    $delta += 3;
                    $notes[] = 'Prosperity and magic partially cleansed the corruption.';
                } elseif ($oldOutput <= 10) {
                    $status = 'depleted';
                    $notes[] = 'Corruption consumed the last usable output.';
                }
                break;

            case 'unstable':
                $delta = random_int(-7, 7) + ($magicAffinity >= 75 ? 1 : 0);
                if ($oldOutput <= 20) {
                    $status = 'depleted';
                    $notes[] = 'Instability collapsed the site into depletion.';
                } elseif ($regionChaos >= 70 && $this->roll(0.20)) {
                    $status = 'corrupted';
                    $notes[] = 'Chaotic magic corrupted the unstable site.';
                } elseif ($oldOutput >= 65 && $regionChaos < 55) {
                    $status = 'active';
                    $notes[] = 'The unstable output settled into usable production.';
                }
                break;

            case 'depleted':
                $delta = $isRenewable ? random_int(1, 3) + $renewalRecovery : 0;
                if (!$isRenewable && $regionProsperity >= 80 && $this->roll(0.08)) {
                    $delta = 1;
                    $notes[] = 'Exceptional regional investment found a thin remaining seam.';
                }
                if ($isRenewable && $oldOutput + $delta >= 20) {
                    $status = 'active';
                    $notes[] = 'Renewal restored enough output to reopen the site.';
                }
                break;

            default:
                $delta = random_int(-1, 1);
                break;
        }

        $output = $this->clamp($oldOutput + $delta);
        if ($output <= 5) {
            $status = 'depleted';
        }
        if ($status === 'status-flourishing' && $output < 60) {
            $status = 'active';
        }
        $scarcityTier = $this->resourceScarcityTier($output, $status);
        $notes[] = "Scarcity tier resolved as {$scarcityTier}.";

        return [
            'output' => $output,
            'status' => $status,
            'scarcityTier' => $scarcityTier,
            'reason' => implode(' ', array_unique($notes)),
        ];
    }

    private function resourceEventTitle(
        string $oldStatus,
        string $newStatus,
        int $oldOutput,
        int $newOutput,
        string $oldScarcityTier = 'stable',
        string $newScarcityTier = 'stable'
    ): string {
        if (
            in_array($newScarcityTier, ['critical', 'collapsed'], true) &&
            $newScarcityTier !== $oldScarcityTier
        ) {
            return 'Resource Scarcity Critical';
        }
        if (
            in_array($oldScarcityTier, ['critical', 'collapsed'], true) &&
            in_array($newScarcityTier, ['stable', 'abundant'], true)
        ) {
            return 'Resource Scarcity Relief';
        }
        if ($newStatus === 'depleted' && $oldStatus !== 'depleted') {
            return 'Resource Depleted';
        }
        if ($oldStatus === 'depleted' && $newStatus !== 'depleted') {
            return 'Resource Recovered';
        }
        if ($newStatus === 'corrupted' && $oldStatus !== 'corrupted') {
            return 'Resource Corrupted';
        }
        if ($newStatus === 'contested' && $oldStatus !== 'contested') {
            return 'Resource Contested';
        }
        if ($newStatus === 'status-flourishing' && $oldStatus !== 'status-flourishing') {
            return 'Resource Flourishes';
        }
        if ($newStatus === 'active' && $oldStatus !== 'active') {
            return 'Resource Stabilized';
        }
        if ($oldOutput !== $newOutput) {
            return 'Resource Output Shift';
        }

        return 'Resource State Changed';
    }

    private function resourceScarcityTier(int $output, string $status): string
    {
        if ($status === 'depleted' || $output <= 5) {
            return 'collapsed';
        }
        if ($status === 'corrupted' || $output <= 20) {
            return 'critical';
        }
        if (in_array($status, ['contested', 'overworked', 'unstable'], true) || $output <= 40) {
            return 'strained';
        }
        if (in_array($status, ['blessed', 'status-flourishing'], true) || $output >= 75) {
            return 'abundant';
        }

        return 'stable';
    }

    private function resourceThresholdNotes(
        int $oldOutput,
        int $newOutput,
        string $oldStatus,
        string $newStatus,
        string $oldScarcityTier,
        string $newScarcityTier,
        array $settlementImpact
    ): array {
        $notes = [];

        if ($oldScarcityTier !== $newScarcityTier) {
            $notes[] = "Scarcity tier changed from {$oldScarcityTier} to {$newScarcityTier}.";
        }
        if ($this->crossedDown($oldOutput, $newOutput, 40)) {
            $notes[] = 'Output fell into strained resource range.';
        }
        if ($this->crossedDown($oldOutput, $newOutput, 20)) {
            $notes[] = 'Output fell into critical scarcity range.';
        }
        if ($this->crossedUp($oldOutput, $newOutput, 75)) {
            $notes[] = 'Output recovered to abundant resource range.';
        }
        if ($oldStatus !== $newStatus && in_array($newStatus, ['depleted', 'contested', 'corrupted', 'overworked'], true)) {
            $notes[] = "Status now creates strategic pressure: {$newStatus}.";
        }
        if (($settlementImpact['changed'] ?? false) === true) {
            $notes[] = 'Linked settlement survival metrics changed.';
        }

        return $notes;
    }

    private function applyResourceScarcitySettlementImpact(
        ResourceNode $node,
        int $oldOutput,
        string $oldStatus,
        array $outcome,
        int $currentYear
    ): array {
        $settlement = $node->settlement;
        if (!$settlement instanceof Settlement) {
            return [
                'changed' => false,
                'summary' => 'No settlement directly depended on this resource.',
            ];
        }

        $newOutput = (int)$outcome['output'];
        $newStatus = (string)$outcome['status'];
        $oldScarcityTier = $this->resourceScarcityTier($oldOutput, $oldStatus);
        $newScarcityTier = (string)$outcome['scarcityTier'];
        $changedEnough = $oldStatus !== $newStatus ||
            $oldScarcityTier !== $newScarcityTier ||
            abs($newOutput - $oldOutput) >= 4;

        if (!$changedEnough) {
            return [
                'changed' => false,
                'summary' => "{$settlement->name} absorbed the resource change without immediate survival impact.",
            ];
        }

        $oldProsperity = (int)$settlement->prosperity;
        $oldDefensibility = (int)$settlement->defensibility;
        $oldStatusValue = (string)$settlement->status;
        $oldTraits = $settlement->traits ?? [];
        $prosperityDelta = 0;
        $defensibilityDelta = 0;
        $traitSignals = [];

        if (in_array($newScarcityTier, ['critical', 'collapsed'], true)) {
            $prosperityDelta -= $newScarcityTier === 'collapsed' ? 2 : 1;
            $traitSignals[] = 'resource_scarcity';
        } elseif ($newScarcityTier === 'strained') {
            $prosperityDelta -= 1;
            $traitSignals[] = 'resource_watch';
        } elseif (
            in_array($oldScarcityTier, ['critical', 'collapsed', 'strained'], true) &&
            in_array($newScarcityTier, ['stable', 'abundant'], true)
        ) {
            $prosperityDelta += 1;
            $traitSignals[] = 'resource_recovery';
        }

        if ($newStatus === 'contested') {
            $defensibilityDelta += 1;
            $traitSignals[] = 'resource_conflict';
        }
        if (in_array($newStatus, ['corrupted', 'unstable'], true)) {
            $prosperityDelta -= 1;
            $defensibilityDelta -= 1;
            $traitSignals[] = 'hazard_watch';
        }
        if (in_array($newStatus, ['blessed', 'status-flourishing'], true)) {
            $prosperityDelta += 1;
            $traitSignals[] = 'resource_boon';
        }

        $settlement->prosperity = $this->clamp($oldProsperity + $prosperityDelta);
        $settlement->defensibility = $this->clamp($oldDefensibility + $defensibilityDelta);
        $settlement->traits = $this->settlementTraitsAfterResourceScarcity($oldTraits, $traitSignals);
        $settlement->last_event_year = $currentYear;
        $regionDanger = $node->region ? (int)$node->region->danger_level : 25;
        $settlement->status = $this->settlementStatusFor(
            (int)$settlement->prosperity,
            (int)$settlement->population,
            (int)$settlement->defensibility,
            $regionDanger,
            $oldStatusValue
        );

        $changed = (int)$settlement->prosperity !== $oldProsperity ||
            (int)$settlement->defensibility !== $oldDefensibility ||
            $settlement->status !== $oldStatusValue ||
            $settlement->traits !== $oldTraits;

        if ($changed) {
            $settlement->save();
        }

        return [
            'changed' => $changed,
            'settlementId' => (string)$settlement->id,
            'summary' => $changed
                ? "{$settlement->name} reacted to resource scarcity: prosperity {$oldProsperity}->{$settlement->prosperity}, defense {$oldDefensibility}->{$settlement->defensibility}, status {$oldStatusValue}->{$settlement->status}."
                : "{$settlement->name} watched the resource pressure but did not cross a settlement threshold.",
        ];
    }

    private function settlementTraitsAfterResourceScarcity(array $traits, array $traitSignals): array
    {
        $result = array_values(array_unique(array_filter($traits)));

        foreach ($traitSignals as $trait) {
            if (!is_string($trait) || $trait === '' || in_array($trait, $result, true)) {
                continue;
            }

            $result[] = $trait;
            if (count($result) >= 8) {
                break;
            }
        }

        return $result;
    }

    private function resourceRelatedHeroIds(ResourceNode $node, string $status): array
    {
        if (!in_array($status, ['depleted', 'contested', 'corrupted', 'overworked', 'unstable'], true)) {
            return [];
        }

        return Hero::where('region_id', $node->region_id)
            ->where('is_alive', true)
            ->orderByDesc('level')
            ->limit(4)
            ->pluck('id')
            ->values()
            ->all();
    }

    private function settlementPressureForRegion(string $regionId): array
    {
        $settlements = Settlement::where('region_id', $regionId)->get();
        if ($settlements->isEmpty()) {
            return [
                'prosperityDelta' => 0,
                'chaosDelta' => 0,
                'dangerDelta' => 0,
                'summary' => 'No settlement pressure was recorded.',
            ];
        }

        $thriving = 0;
        $distressed = 0;
        $ruined = 0;
        $averageDefense = (int)round($settlements->avg('defensibility') ?? 0);
        foreach ($settlements as $settlement) {
            if (in_array($settlement->status, ['thriving', 'prosperous'], true)) {
                $thriving++;
            }
            if (in_array($settlement->status, ['declining', 'struggling', 'abandoned', 'ruined'], true)) {
                $distressed++;
            }
            if (in_array($settlement->status, ['abandoned', 'ruined'], true)) {
                $ruined++;
            }
        }

        $prosperityDelta = min(2, $thriving) - min(3, $distressed);
        $chaosDelta = min(3, $distressed) - min(2, $thriving);
        $dangerDelta = $ruined + ($averageDefense >= 65 ? -1 : 0);

        return [
            'prosperityDelta' => max(-3, min(2, $prosperityDelta)),
            'chaosDelta' => max(-2, min(3, $chaosDelta)),
            'dangerDelta' => max(-1, min(3, $dangerDelta)),
            'summary' => "Settlement pressure: {$thriving} thriving/prosperous, {$distressed} distressed, {$ruined} ruined or abandoned, average defense {$averageDefense}.",
        ];
    }

    private function magicCulturePressureForRegion(Region $region): array
    {
        $heroes = Hero::where('region_id', $region->id)
            ->where('is_alive', true)
            ->orderByDesc('level')
            ->get();
        $landmarks = Landmark::where('region_id', $region->id)
            ->get()
            ->filter(fn(Landmark $landmark): bool => $landmark->discovered_year !== null || $landmark->isAccessible());
        $resources = ResourceNode::where('region_id', $region->id)->get();
        $settlements = Settlement::where('region_id', $region->id)->get();

        $currentCulture = (string)($region->cultural_influence ?? 'pastoral');
        $cultureScores = [
            'scholarly' => 0,
            'martial' => 0,
            'mystical' => 0,
            'mercantile' => 0,
            'pastoral' => 0,
        ];
        if (!array_key_exists($currentCulture, $cultureScores)) {
            $currentCulture = 'pastoral';
        }
        $cultureScores[$currentCulture] += 2;

        $magicDelta = 0;
        $prosperityDelta = 0;
        $chaosDelta = 0;
        $dangerDelta = 0;
        $traitSignals = [];
        $magicAffinity = (int)$region->magic_affinity;
        $interRegionCulturePressure = $this->interRegionCulturePressureForRegion($region);

        if ($magicAffinity >= 70) {
            $cultureScores['mystical'] += 2;
            $traitSignals[] = 'emerging_mysticism';
        } elseif ($magicAffinity <= 35) {
            $cultureScores['pastoral'] += 1;
        }
        if ((int)$region->prosperity >= 75) {
            $cultureScores['mercantile'] += 1;
            $cultureScores['scholarly'] += 1;
        }
        if ((int)$region->danger_level >= 60) {
            $cultureScores['martial'] += 1;
        }

        $heroRoles = [];
        foreach ($heroes as $hero) {
            $heroRoles[] = (string)$hero->role;
            if ((int)$hero->level >= 20) {
                $prosperityDelta++;
            }
        }
        $roleCounts = array_count_values($heroRoles);
        if (($roleCounts['scholar'] ?? 0) > 0) {
            $cultureScores['scholarly'] += 2 + min(2, $roleCounts['scholar']);
            $magicDelta += 1;
            $prosperityDelta += 1;
            $traitSignals[] = 'arcane_research';
        }
        if (($roleCounts['prophet'] ?? 0) > 0) {
            $cultureScores['mystical'] += 2 + min(2, $roleCounts['prophet']);
            $magicDelta += 1;
            $chaosDelta -= 1;
            $traitSignals[] = 'prophetic_tradition';
        }
        if (($roleCounts['warrior'] ?? 0) > 0) {
            $cultureScores['martial'] += 2 + min(2, $roleCounts['warrior']);
            $dangerDelta -= 1;
            $traitSignals[] = 'martial_tradition';
        }
        if (($roleCounts['agent of change'] ?? 0) > 0) {
            $cultureScores['mercantile'] += 1;
            $cultureScores['scholarly'] += 1;
            $chaosDelta += 1;
        }

        $magicalLandmarks = 0;
        $hazardousLandmarks = 0;
        $sacredLandmarks = 0;
        $strategicLandmarks = 0;
        foreach ($landmarks as $landmark) {
            $landmarkMagic = (int)$landmark->magic_level;
            $landmarkDanger = (int)$landmark->danger_level;
            if ($landmark->isMagical() || $landmarkMagic >= 45) {
                $magicalLandmarks++;
                $magicDelta += $landmarkMagic >= 75 ? 2 : 1;
                $cultureScores['mystical'] += 2;
                $cultureScores['scholarly'] += 1;
                $traitSignals[] = 'leyline_focus';
            }
            if ($landmark->isSacred() || $landmark->status === Landmark::STATUS_BLESSED) {
                $sacredLandmarks++;
                $prosperityDelta += 1;
                $chaosDelta -= 1;
                $cultureScores['mystical'] += 1;
                $cultureScores['pastoral'] += 1;
                $traitSignals[] = 'sacred_traditions';
            }
            if ($landmark->hasTrait(Landmark::TRAIT_STRATEGIC) || $landmark->type === Landmark::TYPE_BATTLEFIELD) {
                $strategicLandmarks++;
                $cultureScores['martial'] += 2;
                $dangerDelta -= 1;
                $traitSignals[] = 'strategic_watch';
            }
            if (
                $landmark->isCorrupted() ||
                $landmarkDanger >= 60 ||
                $landmark->status === Landmark::STATUS_HAUNTED
            ) {
                $hazardousLandmarks++;
                $chaosDelta += 1;
                $dangerDelta += 1;
                $prosperityDelta -= 1;
                $cultureScores['martial'] += 1;
                $cultureScores['mystical'] += 1;
                $traitSignals[] = 'volatile_magic';
            }
        }

        $magicalResources = 0;
        $unstableResources = 0;
        foreach ($resources as $resource) {
            $resourceType = (string)$resource->type;
            $isMagicalResource = $resource->isMagical() || $resourceType === 'magical_spring';
            if ($isMagicalResource) {
                $magicalResources++;
                $magicDelta += (int)$resource->output >= 70 ? 2 : 1;
                $cultureScores['mystical'] += 2;
                $cultureScores['scholarly'] += 1;
                $traitSignals[] = 'arcane_resource';
            }
            if ($resourceType === 'herb_garden') {
                $cultureScores['scholarly'] += 1;
                $cultureScores['pastoral'] += 1;
            }
            if (in_array($resourceType, ['farmland', 'fishing', 'forest'], true)) {
                $cultureScores['pastoral'] += 1;
            }
            if (in_array($resourceType, ['mine', 'quarry'], true)) {
                $cultureScores['mercantile'] += 1;
            }
            if (in_array($resource->status, ['unstable', 'corrupted', 'contested'], true)) {
                $unstableResources++;
                $chaosDelta += 1;
                $dangerDelta += 1;
                if ($isMagicalResource || in_array($resource->status, ['unstable', 'corrupted'], true)) {
                    $traitSignals[] = 'volatile_magic';
                } else {
                    $cultureScores['martial'] += 1;
                    $traitSignals[] = 'resource_conflict';
                }
            } elseif (in_array($resource->status, ['blessed', 'status-flourishing'], true)) {
                $prosperityDelta += 1;
            }
        }

        foreach ($settlements as $settlement) {
            foreach ($settlement->specializations ?? [] as $specialization) {
                if (in_array($specialization, ['magic_research', 'landmark_research', 'heroic_scholarship', 'herbalism'], true)) {
                    $cultureScores['scholarly'] += 2;
                    $magicDelta += 1;
                    $traitSignals[] = 'arcane_research';
                }
                if (in_array($specialization, ['trade', 'market', 'mining', 'stonecraft'], true)) {
                    $cultureScores['mercantile'] += 2;
                }
                if (in_array($specialization, ['farming', 'fishing', 'woodworking'], true)) {
                    $cultureScores['pastoral'] += 2;
                }
                if (in_array($specialization, ['defense', 'military', 'watch', 'fortification'], true)) {
                    $cultureScores['martial'] += 2;
                }
            }
            foreach ($settlement->traits ?? [] as $trait) {
                if (in_array($trait, ['prophetic_guidance', 'pilgrimage_site', 'legendary_patronage'], true)) {
                    $cultureScores['mystical'] += 1;
                }
                if (in_array($trait, ['fortified', 'hardened_defenses', 'hero_guarded'], true)) {
                    $cultureScores['martial'] += 1;
                }
            }
            if (in_array($settlement->status, ['thriving', 'prosperous'], true)) {
                $cultureScores['mercantile'] += 1;
                $prosperityDelta += 1;
            }
            if (in_array($settlement->status, ['declining', 'struggling', 'abandoned', 'ruined'], true)) {
                $chaosDelta += 1;
            }
        }

        if ($magicalLandmarks === 0 && $magicalResources === 0 && $magicAffinity > 55) {
            $magicDelta -= 1;
        }
        if ($magicAffinity >= 65 && ((int)$region->chaos >= 65 || $hazardousLandmarks + $unstableResources >= 2)) {
            $chaosDelta += 1;
            $traitSignals[] = 'volatile_magic';
        }

        foreach ($region->regional_traits ?? [] as $regionalTrait) {
            if ($regionalTrait === 'mythic_memory' || str_starts_with((string)$regionalTrait, 'myth_')) {
                $cultureScores['mystical'] += 1;
                $traitSignals[] = 'mythic_tradition';
            }
            if (in_array($regionalTrait, ['myth_heroic_legend', 'myth_martyr_legend'], true)) {
                $cultureScores['martial'] += 1;
            }
            if ($regionalTrait === 'myth_discovery_myth' || $regionalTrait === 'myth_relic_myth') {
                $magicDelta += 1;
                $cultureScores['scholarly'] += 1;
            }
        }

        foreach ($interRegionCulturePressure['cultureScores'] as $culture => $score) {
            if (!array_key_exists($culture, $cultureScores)) {
                continue;
            }

            $cultureScores[$culture] += (int)$score;
        }
        $magicDelta += (int)$interRegionCulturePressure['magicDelta'];
        $prosperityDelta += (int)$interRegionCulturePressure['prosperityDelta'];
        $chaosDelta += (int)$interRegionCulturePressure['chaosDelta'];
        $dangerDelta += (int)$interRegionCulturePressure['dangerDelta'];
        $traitSignals = array_merge($traitSignals, $interRegionCulturePressure['traitSignals']);

        arsort($cultureScores);
        $topCulture = (string)array_key_first($cultureScores);
        $topCultureScore = (int)($cultureScores[$topCulture] ?? 0);
        $currentCultureScore = (int)($cultureScores[$currentCulture] ?? 0);
        $newCulture = $currentCulture;
        if ($topCulture !== $currentCulture && $topCultureScore >= 4 && $topCultureScore >= $currentCultureScore + 3) {
            $newCulture = $topCulture;
        }

        $traitSignals = array_values(array_unique($traitSignals));
        $magicDelta = max(-4, min(5, $magicDelta));
        $prosperityDelta = max(-3, min(3, $prosperityDelta));
        $chaosDelta = max(-3, min(3, $chaosDelta));
        $dangerDelta = max(-2, min(3, $dangerDelta));
        $magicText = $magicDelta > 0 ? "+{$magicDelta}" : (string)$magicDelta;
        $cultureText = $newCulture === $currentCulture ? $currentCulture : "{$currentCulture}->{$newCulture}";
        $traitText = $traitSignals === [] ? 'none' : implode(', ', array_slice($traitSignals, 0, 4));

        return [
            'magicDelta' => $magicDelta,
            'prosperityDelta' => $prosperityDelta,
            'chaosDelta' => $chaosDelta,
            'dangerDelta' => $dangerDelta,
            'culturalInfluence' => $newCulture,
            'traitSignals' => $traitSignals,
            'heroIds' => $heroes->pluck('id')->take(8)->values()->all(),
            'settlementIds' => $settlements->pluck('id')->take(8)->values()->all(),
            'landmarkIds' => $landmarks->pluck('id')->take(8)->values()->all(),
            'resourceIds' => $resources->pluck('id')->take(8)->values()->all(),
            'relatedRegionIds' => $interRegionCulturePressure['regionIds'],
            'summary' => "Magic/culture pressure: {$heroes->count()} living heroes, {$landmarks->count()} known landmarks ({$magicalLandmarks} magical, {$sacredLandmarks} sacred, {$strategicLandmarks} strategic, {$hazardousLandmarks} hazardous), {$magicalResources} magical resources, {$settlements->count()} settlements; magic {$magicText}, culture {$cultureText}, traits {$traitText}. {$interRegionCulturePressure['summary']}",
        ];
    }

    private function interRegionCulturePressureForRegion(Region $region): array
    {
        $directRouteIds = $this->ids($region->trade_routes ?? []);
        $reverseRouteIds = [];

        foreach (Region::where('id', '!=', (string)$region->id)->get() as $candidate) {
            if (in_array((string)$region->id, $this->ids($candidate->trade_routes ?? []), true)) {
                $reverseRouteIds[] = (string)$candidate->id;
            }
        }

        $connectedIds = $this->ids(array_merge($directRouteIds, $reverseRouteIds));
        if ($connectedIds === []) {
            return $this->emptyInterRegionCulturePressure('Inter-region culture pressure: no active trade routes.');
        }

        $connectedRegions = Region::whereIn('id', $connectedIds)->get();
        if ($connectedRegions->isEmpty()) {
            return $this->emptyInterRegionCulturePressure('Inter-region culture pressure: trade routes point to no active regions.');
        }

        $cultureScores = [
            'scholarly' => 0,
            'martial' => 0,
            'mystical' => 0,
            'mercantile' => 0,
            'pastoral' => 0,
        ];
        $magicDelta = 0;
        $prosperityDelta = 0;
        $chaosDelta = 0;
        $dangerDelta = 0;
        $traitSignals = [];
        $signals = [];
        $regionIds = [];

        foreach ($connectedRegions as $connectedRegion) {
            $regionIds[] = (string)$connectedRegion->id;
            $culture = (string)($connectedRegion->cultural_influence ?? 'pastoral');
            if (!array_key_exists($culture, $cultureScores)) {
                $culture = 'pastoral';
            }

            $prosperity = (int)$connectedRegion->prosperity;
            $population = (int)($connectedRegion->population_total ?? 0);
            $chaos = (int)$connectedRegion->chaos;
            $danger = (int)$connectedRegion->danger_level;
            $magicAffinity = (int)$connectedRegion->magic_affinity;
            $strength = 1
                + ($prosperity >= 70 ? 1 : 0)
                + ($population >= 2000 ? 1 : 0);
            $strength = min(3, $strength);
            $cultureScores[$culture] += $strength;
            $signals[] = "{$connectedRegion->name} {$culture} +{$strength}";

            if ($culture === 'mercantile' && $prosperity >= 60) {
                $prosperityDelta++;
                $traitSignals[] = 'trade_culture_exchange';
            }
            if (in_array($culture, ['scholarly', 'mystical'], true) && $magicAffinity >= 55) {
                $magicDelta++;
                $traitSignals[] = 'cross_region_lore';
            }
            if ($culture === 'martial' || $chaos >= 65 || $danger >= 65) {
                $cultureScores['martial']++;
                $dangerDelta++;
                if ($chaos >= 65) {
                    $chaosDelta++;
                }
                $traitSignals[] = 'border_tension';
            }
            if ($culture === 'pastoral' && $chaos <= 35) {
                $chaosDelta--;
            }
        }

        return [
            'cultureScores' => $cultureScores,
            'magicDelta' => max(-1, min(2, $magicDelta)),
            'prosperityDelta' => max(-1, min(2, $prosperityDelta)),
            'chaosDelta' => max(-2, min(2, $chaosDelta)),
            'dangerDelta' => max(0, min(2, $dangerDelta)),
            'traitSignals' => array_values(array_unique($traitSignals)),
            'regionIds' => $this->ids($regionIds),
            'summary' => 'Inter-region culture pressure: ' . $connectedRegions->count() .
                ' connected region(s); ' . implode(', ', array_slice($signals, 0, 4)) . '.',
        ];
    }

    private function emptyInterRegionCulturePressure(string $summary): array
    {
        return [
            'cultureScores' => [
                'scholarly' => 0,
                'martial' => 0,
                'mystical' => 0,
                'mercantile' => 0,
                'pastoral' => 0,
            ],
            'magicDelta' => 0,
            'prosperityDelta' => 0,
            'chaosDelta' => 0,
            'dangerDelta' => 0,
            'traitSignals' => [],
            'regionIds' => [],
            'summary' => $summary,
        ];
    }

    private function regionalTraitsAfterMagicCulturePressure(array $traits, array $magicCulturePressure): array
    {
        $result = array_values(array_unique(array_filter($traits)));

        foreach ($magicCulturePressure['traitSignals'] ?? [] as $trait) {
            if (!is_string($trait) || $trait === '' || in_array($trait, $result, true)) {
                continue;
            }

            $result[] = $trait;
            if (count($result) >= 8) {
                break;
            }
        }

        return $result;
    }

    private function regionStatusFor(
        int $prosperity,
        int $chaos,
        int $danger,
        int $magicAffinity,
        string $currentStatus
    ): string {
        if ($chaos >= 80 || $danger >= 80) {
            return 'war_torn';
        }
        if ($prosperity <= 15 && $danger >= 60) {
            return 'abandoned';
        }
        if ($magicAffinity >= 75 && $chaos >= 65 && $danger >= 55) {
            return 'cursed';
        }
        if ($prosperity >= 85 && $chaos <= 25 && $danger <= 45) {
            return 'flourishing';
        }
        if ($prosperity >= 70 && $chaos <= 45) {
            return 'prosperous';
        }
        if ($chaos >= 65 || $danger >= 60) {
            return 'turbulent';
        }
        if ($prosperity <= 35) {
            return 'declining';
        }

        return in_array($currentStatus, ['mysterious', 'blessed'], true) ? $currentStatus : 'stable';
    }

    private function regionThresholdNotes(
        int $oldProsperity,
        int $newProsperity,
        int $oldChaos,
        int $newChaos,
        int $oldDanger,
        int $newDanger,
        string $oldStatus,
        string $newStatus,
        int $oldMagicAffinity,
        int $newMagicAffinity,
        string $oldCulturalInfluence,
        string $newCulturalInfluence,
        array $oldRegionalTraits,
        array $newRegionalTraits
    ): array {
        $notes = [];
        if ($oldStatus !== $newStatus) {
            $notes[] = "Regional status changed from {$oldStatus} to {$newStatus}.";
        }
        if ($this->crossedUp($oldProsperity, $newProsperity, 85)) {
            $notes[] = 'Prosperity crossed the flourishing threshold.';
        }
        if ($this->crossedDown($oldProsperity, $newProsperity, 35)) {
            $notes[] = 'Prosperity fell into decline pressure.';
        }
        if ($this->crossedUp($oldChaos, $newChaos, 65)) {
            $notes[] = 'Chaos crossed the turbulence threshold.';
        }
        if ($this->crossedUp($oldDanger, $newDanger, 60)) {
            $notes[] = 'Danger crossed the regional crisis threshold.';
        }
        if ($this->crossedUp($oldMagicAffinity, $newMagicAffinity, 75)) {
            $notes[] = 'Magic affinity crossed the high-magic threshold.';
        }
        if ($this->crossedDown($oldMagicAffinity, $newMagicAffinity, 35)) {
            $notes[] = 'Magic affinity faded below the low-magic threshold.';
        }
        if ($oldCulturalInfluence !== $newCulturalInfluence) {
            $notes[] = "Regional culture shifted from {$oldCulturalInfluence} to {$newCulturalInfluence}.";
        }

        $addedTraits = array_values(array_diff($newRegionalTraits, $oldRegionalTraits));
        if ($addedTraits !== []) {
            $notes[] = 'New regional trait: ' . implode(', ', $addedTraits) . '.';
        }

        return $notes;
    }

    private function regionEventTitle(
        string $oldStatus,
        string $newStatus,
        int $oldMagicAffinity,
        int $newMagicAffinity,
        string $oldCulturalInfluence,
        string $newCulturalInfluence,
        array $thresholdNotes
    ): string {
        if ($oldCulturalInfluence !== $newCulturalInfluence) {
            return 'Regional Culture Shift';
        }
        if (abs($newMagicAffinity - $oldMagicAffinity) >= 3) {
            return $newMagicAffinity > $oldMagicAffinity ? 'Regional Magic Surge' : 'Regional Magic Waning';
        }
        if ($oldStatus !== $newStatus || $thresholdNotes !== []) {
            return 'Regional Threshold Crossed';
        }

        return 'Regional Drift';
    }

    private function defensePressureForSettlement(Settlement $settlement, int $regionDanger): array
    {
        $defensibility = (int)$settlement->defensibility;
        $prosperity = (int)$settlement->prosperity;
        $dangerDeficit = max(0, $regionDanger - $defensibility);
        $dangerPenalty = min(3, intdiv($dangerDeficit, 20));
        $wellDefended = $defensibility >= 65 && $defensibility >= $regionDanger - 10;
        $defensibilityDelta = 0;

        if ($regionDanger >= 55 && $prosperity >= 45) {
            $defensibilityDelta += $prosperity >= 70 ? 2 : 1;
        }
        if ($regionDanger >= 75 && $prosperity <= 35 && $defensibility < 45) {
            $defensibilityDelta -= 1;
        }

        return [
            'growthModifier' => ($wellDefended ? 0.004 : 0.0) - ($dangerPenalty * 0.006),
            'prosperityDelta' => ($wellDefended ? 1 : 0) - $dangerPenalty,
            'defensibilityDelta' => max(-2, min(2, $defensibilityDelta)),
            'summary' => "Defense pressure: region danger {$regionDanger} against defensibility {$defensibility} produced {$dangerPenalty} threat signals.",
        ];
    }

    private function civicPressureForSettlement(Settlement $settlement): array
    {
        $heroes = Hero::where('region_id', $settlement->region_id)
            ->where('is_alive', true)
            ->orderByDesc('level')
            ->get();
        $landmarks = Landmark::where('region_id', $settlement->region_id)
            ->get()
            ->filter(fn(Landmark $landmark): bool => $landmark->discovered_year !== null || $landmark->isAccessible());

        $growthModifier = 0.0;
        $prosperityDelta = 0;
        $defensibilityDelta = 0;
        $heroRoles = [];
        $specializationSignals = [];
        $traitSignals = [];

        foreach ($heroes as $hero) {
            $heroRoles[] = $hero->role;
        }

        $roleCounts = array_count_values($heroRoles);
        if (($roleCounts['scholar'] ?? 0) > 0) {
            $growthModifier += 0.003;
            $prosperityDelta += 1;
            $specializationSignals[] = 'heroic_scholarship';
        }
        if (($roleCounts['prophet'] ?? 0) > 0) {
            $growthModifier += 0.002;
            $prosperityDelta += 1;
            $traitSignals[] = 'prophetic_guidance';
        }
        if (($roleCounts['warrior'] ?? 0) > 0) {
            $defensibilityDelta += 1;
            $traitSignals[] = 'hero_guarded';
        }
        if (($roleCounts['agent of change'] ?? 0) > 0) {
            $growthModifier += 0.002;
            $specializationSignals[] = 'reformist_civic';
        }

        $highestHeroLevel = (int)($heroes->max('level') ?? 0);
        if ($highestHeroLevel >= 10) {
            $prosperityDelta += 1;
        }
        if ($highestHeroLevel >= 25) {
            $defensibilityDelta += 1;
            $traitSignals[] = 'legendary_patronage';
        }

        $beneficialLandmarks = 0;
        $hazardousLandmarks = 0;
        $magicalLandmarks = 0;
        $strategicLandmarks = 0;

        foreach ($landmarks as $landmark) {
            if ($landmark->isSacred() || $landmark->status === Landmark::STATUS_BLESSED) {
                $beneficialLandmarks++;
                $growthModifier += 0.002;
                $prosperityDelta += 1;
                $traitSignals[] = 'pilgrimage_site';
            }

            if ($landmark->isMagical() || (int)$landmark->magic_level >= 60) {
                $magicalLandmarks++;
                $prosperityDelta += 1;
                $specializationSignals[] = 'landmark_research';
            }

            if ($landmark->hasTrait(Landmark::TRAIT_STRATEGIC) || $landmark->type === Landmark::TYPE_BATTLEFIELD) {
                $strategicLandmarks++;
                $defensibilityDelta += 1;
                $traitSignals[] = 'landmark_watch';
            }

            if ($landmark->isCorrupted() || $landmark->isDangerous() || $landmark->status === Landmark::STATUS_HAUNTED) {
                $hazardousLandmarks++;
                $growthModifier -= 0.004;
                $prosperityDelta -= 1;
                $defensibilityDelta += (int)$settlement->prosperity >= 45 ? 1 : -1;
            }
        }

        $growthModifier = max(-0.018, min(0.020, $growthModifier));
        $prosperityDelta = max(-3, min(3, $prosperityDelta));
        $defensibilityDelta = max(-2, min(3, $defensibilityDelta));
        $heroCount = $heroes->count();
        $landmarkCount = $landmarks->count();
        $growthPercent = round($growthModifier * 100, 1);
        $heroSummary = $heroCount === 1 ? '1 living hero' : "{$heroCount} living heroes";
        $landmarkSummary = $landmarkCount === 1 ? '1 known landmark' : "{$landmarkCount} known landmarks";

        return [
            'growthModifier' => $growthModifier,
            'prosperityDelta' => $prosperityDelta,
            'defensibilityDelta' => $defensibilityDelta,
            'specializationSignals' => array_values(array_unique($specializationSignals)),
            'traitSignals' => array_values(array_unique($traitSignals)),
            'heroIds' => $heroes->pluck('id')->take(8)->values()->all(),
            'landmarkIds' => $landmarks->pluck('id')->take(8)->values()->all(),
            'summary' => "Civic pressure: {$heroSummary}, highest hero level {$highestHeroLevel}, {$landmarkSummary}, {$beneficialLandmarks} beneficial, {$magicalLandmarks} magical, {$strategicLandmarks} strategic, {$hazardousLandmarks} hazardous; growth {$growthPercent}%, prosperity {$prosperityDelta}, defense {$defensibilityDelta}.",
        ];
    }

    private function settlementSpecializationsAfterResourcePressure(array $specializations, array $resourcePressure): array
    {
        $result = array_values(array_unique(array_filter($specializations)));
        if (($resourcePressure['averageOutput'] ?? 0) < 55) {
            return $result;
        }

        $mapping = [
            'magical_spring' => 'magic_research',
            'forest' => 'woodworking',
            'farmland' => 'farming',
            'fishing' => 'fishing',
            'mine' => 'mining',
            'quarry' => 'stonecraft',
            'herb_garden' => 'herbalism',
        ];

        foreach ($resourcePressure['resourceTypes'] ?? [] as $type) {
            $specialization = $mapping[$type] ?? null;
            if ($specialization && !in_array($specialization, $result, true)) {
                $result[] = $specialization;
            }
            if (count($result) >= 4) {
                break;
            }
        }

        return $result;
    }

    private function settlementSpecializationsAfterCivicPressure(array $specializations, array $civicPressure): array
    {
        $result = array_values(array_unique(array_filter($specializations)));
        foreach ($civicPressure['specializationSignals'] ?? [] as $specialization) {
            if (!in_array($specialization, $result, true)) {
                $result[] = $specialization;
            }
            if (count($result) >= 5) {
                break;
            }
        }

        return $result;
    }

    private function settlementTraitsAfterCivicPressure(array $traits, array $civicPressure): array
    {
        $result = array_values(array_unique(array_filter($traits)));
        foreach ($civicPressure['traitSignals'] ?? [] as $trait) {
            if (!in_array($trait, $result, true)) {
                $result[] = $trait;
            }
        }

        return $result;
    }

    private function settlementTraitsAfterDefensePressure(array $traits, int $defensibility, int $regionDanger): array
    {
        $result = array_values(array_unique(array_filter($traits)));
        if ($regionDanger >= 55 && $defensibility >= 65 && !in_array('fortified', $result, true)) {
            $result[] = 'fortified';
        }
        if ($regionDanger >= 70 && $defensibility >= 80 && !in_array('hardened_defenses', $result, true)) {
            $result[] = 'hardened_defenses';
        }

        return $result;
    }

    private function settlementThresholdNotes(
        int $oldPopulation,
        int $newPopulation,
        int $oldProsperity,
        int $newProsperity,
        int $oldDefensibility,
        int $newDefensibility,
        string $oldStatus,
        string $newStatus,
        string $oldType,
        string $newType,
        array $oldSpecializations,
        array $newSpecializations,
        array $oldTraits,
        array $newTraits
    ): array {
        $notes = [];
        if ($oldStatus !== $newStatus) {
            $notes[] = "Settlement status changed from {$oldStatus} to {$newStatus}.";
        }
        if ($oldType !== $newType) {
            $notes[] = "Settlement type changed from {$oldType} to {$newType}.";
        }
        if ($this->crossedUp($oldProsperity, $newProsperity, 80)) {
            $notes[] = 'Prosperity crossed the thriving threshold.';
        }
        if ($this->crossedDown($oldProsperity, $newProsperity, 35)) {
            $notes[] = 'Prosperity fell into hardship.';
        }
        if ($this->crossedUp($oldDefensibility, $newDefensibility, 70)) {
            $notes[] = 'Defenses crossed the fortified threshold.';
        }
        if ($this->crossedDown($oldPopulation, $newPopulation, 100)) {
            $notes[] = 'Population fell below village scale.';
        }

        $addedSpecializations = array_values(array_diff($newSpecializations, $oldSpecializations));
        if ($addedSpecializations !== []) {
            $notes[] = 'New specialization: ' . implode(', ', $addedSpecializations) . '.';
        }
        $addedTraits = array_values(array_diff($newTraits, $oldTraits));
        if ($addedTraits !== []) {
            $notes[] = 'New trait: ' . implode(', ', $addedTraits) . '.';
        }

        return $notes;
    }

    private function settlementEventTitle(
        string $oldStatus,
        string $newStatus,
        string $oldType,
        string $newType,
        array $thresholdNotes
    ): string {
        if ($newStatus === 'ruined' && $oldStatus !== 'ruined') {
            return 'Settlement Ruined';
        }
        if ($oldStatus === 'ruined' && $newStatus !== 'ruined') {
            return 'Settlement Recovered';
        }
        if ($oldType !== $newType) {
            return 'Settlement Expanded';
        }
        foreach ($thresholdNotes as $note) {
            if (str_contains($note, 'specialization')) {
                return 'Settlement Specialized';
            }
            if (str_contains($note, 'fortified') || str_contains($note, 'trait')) {
                return 'Settlement Fortified';
            }
        }

        return $thresholdNotes === [] ? 'Settlement Shift' : 'Settlement Threshold Crossed';
    }

    private function resourcePressureForRegion(string $regionId): array
    {
        $nodes = ResourceNode::where('region_id', $regionId)->get();
        if ($nodes->isEmpty()) {
            return [
                'growthModifier' => 0.0,
                'prosperityDelta' => 0,
                'dangerDelta' => 0,
                'summary' => 'No active resource pressure was recorded.',
            ];
        }

        $effectiveOutputs = [];
        $contested = 0;
        $corrupted = 0;
        $unstable = 0;
        foreach ($nodes as $node) {
            $effectiveOutputs[] = $this->effectiveResourceOutput($node);
            if ($node->status === 'contested') {
                $contested++;
            }
            if ($node->status === 'corrupted') {
                $corrupted++;
            }
            if ($node->status === 'unstable') {
                $unstable++;
            }
        }

        $averageOutput = (int)round(array_sum($effectiveOutputs) / max(1, count($effectiveOutputs)));
        $prosperityDelta = match (true) {
            $averageOutput >= 75 => 2,
            $averageOutput >= 55 => 1,
            $averageOutput <= 20 => -2,
            $averageOutput <= 35 => -1,
            default => 0,
        };
        $dangerDelta = ($contested * 2) + ($corrupted * 4) + $unstable - ($averageOutput >= 75 ? 1 : 0);

        return [
            'growthModifier' => 0.0,
            'prosperityDelta' => $prosperityDelta,
            'dangerDelta' => max(-1, min(6, $dangerDelta)),
            'summary' => "Resource pressure: average effective output {$averageOutput} across {$nodes->count()} nodes; contested {$contested}, corrupted {$corrupted}, unstable {$unstable}.",
        ];
    }

    private function resourcePressureForSettlement(Settlement $settlement): array
    {
        $nodes = ResourceNode::where('region_id', $settlement->region_id)
            ->where(function ($query) use ($settlement) {
                $query->where('settlement_id', $settlement->id)
                    ->orWhereNull('settlement_id');
            })
            ->get();

        if ($nodes->isEmpty()) {
            return [
                'growthModifier' => 0.0,
                'prosperityDelta' => 0,
                'dangerDelta' => 0,
                'averageOutput' => 0,
                'pressurePenalty' => 0,
                'resourceTypes' => [],
                'summary' => 'No nearby resource pressure affected the settlement.',
            ];
        }

        $effectiveOutputs = [];
        $pressurePenalty = 0;
        $resourceTypes = [];
        foreach ($nodes as $node) {
            $effectiveOutputs[] = $this->effectiveResourceOutput($node);
            $resourceTypes[] = $node->type;
            if (in_array($node->status, ['contested', 'corrupted', 'overworked', 'depleted'], true)) {
                $pressurePenalty++;
            }
        }

        $averageOutput = (int)round(array_sum($effectiveOutputs) / max(1, count($effectiveOutputs)));
        $growthModifier = match (true) {
            $averageOutput >= 75 => 0.012,
            $averageOutput >= 55 => 0.006,
            $averageOutput <= 20 => -0.018,
            $averageOutput <= 35 => -0.008,
            default => 0.0,
        };
        $growthModifier -= min(0.018, $pressurePenalty * 0.006);

        $prosperityDelta = match (true) {
            $averageOutput >= 75 => 2,
            $averageOutput >= 55 => 1,
            $averageOutput <= 20 => -2,
            $averageOutput <= 35 => -1,
            default => 0,
        };
        $prosperityDelta -= min(2, $pressurePenalty);

        return [
            'growthModifier' => $growthModifier,
            'prosperityDelta' => max(-3, min(3, $prosperityDelta)),
            'dangerDelta' => $pressurePenalty,
            'averageOutput' => $averageOutput,
            'pressurePenalty' => $pressurePenalty,
            'resourceTypes' => array_values(array_unique($resourceTypes)),
            'summary' => "Nearby resources averaged {$averageOutput} effective output across {$nodes->count()} nodes with {$pressurePenalty} disruption signals.",
        ];
    }

    private function effectiveResourceOutput(ResourceNode $node): int
    {
        $modifier = match ($node->status) {
            'depleted' => 0.0,
            'contested' => 0.3,
            'corrupted' => 0.1,
            'status-flourishing' => 1.5,
            'overworked' => 0.7,
            'blessed' => 1.3,
            'unstable' => 0.8,
            default => 1.0,
        };

        return $this->clamp((int)$node->output * $modifier);
    }

    private function heroIdentityPressure(Hero $hero): array
    {
        $region = $hero->region;
        $settlement = $this->heroFocusSettlement($hero);
        $regionProsperity = $region ? (int)$region->prosperity : 50;
        $regionChaos = $region ? (int)$region->chaos : 50;
        $regionDanger = $region ? (int)$region->danger_level : 25;
        $resources = $hero->region_id ? ResourceNode::where('region_id', $hero->region_id)->get() : [];
        $strainedResourceIds = [];
        $strainedResourceCount = 0;
        $corruptedResourceCount = 0;

        foreach ($resources as $resource) {
            if (!in_array($resource->status, ['depleted', 'contested', 'corrupted', 'overworked', 'unstable'], true)) {
                continue;
            }

            $strainedResourceCount++;
            $strainedResourceIds[] = (string)$resource->id;
            if ($resource->status === 'corrupted') {
                $corruptedResourceCount++;
            }
        }

        $role = (string)$hero->role;
        $interactionType = null;
        $action = null;
        $goodDelta = 0;
        $chaoticDelta = 0;
        $settlementProsperityDelta = 0;
        $settlementDefensibilityDelta = 0;
        $traitSignals = [];
        $settlementTraitSignals = [];
        $pressureLabel = 'stable regional conditions';

        if ($strainedResourceCount > 0) {
            $pressureLabel = "resource scarcity ({$strainedResourceCount} strained nodes)";
            [$interactionType, $action, $goodDelta, $chaoticDelta, $settlementProsperityDelta, $settlementDefensibilityDelta, $traitSignals, $settlementTraitSignals] =
                $this->heroResourceScarcityResponse($role, $settlement, $corruptedResourceCount);
        } elseif ($regionChaos >= 65 || $regionDanger >= 60) {
            $pressureLabel = "regional crisis (chaos {$regionChaos}, danger {$regionDanger})";
            [$interactionType, $action, $goodDelta, $chaoticDelta, $settlementProsperityDelta, $settlementDefensibilityDelta, $traitSignals, $settlementTraitSignals] =
                $this->heroCrisisResponse($role, $settlement);
        } elseif ($regionProsperity >= 70 && $settlement instanceof Settlement) {
            $pressureLabel = "civic growth (prosperity {$regionProsperity})";
            [$interactionType, $action, $goodDelta, $chaoticDelta, $settlementProsperityDelta, $settlementDefensibilityDelta, $traitSignals, $settlementTraitSignals] =
                $this->heroProsperityResponse($role, $settlement);
        }

        $hasAction = $settlement instanceof Settlement && is_string($interactionType) && is_string($action);
        $successChance = 0.5;
        if ($settlement instanceof Settlement) {
            $successChance = 0.38
                + min(0.25, ((int)$hero->level / 120))
                + ((int)$settlement->prosperity / 500)
                + ((int)$settlement->defensibility / 600)
                - ($regionDanger / 500);
        }

        $traitText = $traitSignals === [] ? 'none' : implode(', ', array_slice($traitSignals, 0, 4));
        $actionText = $hasAction ? $action : 'no settlement action';
        $summary = "Hero identity pressure: {$pressureLabel}; action {$actionText}; alignment good {$goodDelta}, chaotic {$chaoticDelta}; traits {$traitText}.";

        return [
            'settlement' => $settlement,
            'settlementIds' => $settlement instanceof Settlement ? [(string)$settlement->id] : [],
            'resourceIds' => $this->ids($strainedResourceIds),
            'interactionType' => $interactionType,
            'action' => $action,
            'successChance' => max(0.15, min(0.88, $successChance)),
            'duration' => $interactionType === 'establish_base' ? 3 : 1,
            'goodDelta' => max(-3, min(3, $goodDelta)),
            'chaoticDelta' => max(-3, min(3, $chaoticDelta)),
            'traitSignals' => array_values(array_unique($traitSignals)),
            'settlementTraitSignals' => array_values(array_unique($settlementTraitSignals)),
            'settlementProsperityDelta' => max(-2, min(2, $settlementProsperityDelta)),
            'settlementDefensibilityDelta' => max(-2, min(2, $settlementDefensibilityDelta)),
            'shouldRecordEvent' => $hasAction || $traitSignals !== [] || $strainedResourceCount > 0,
            'summary' => $summary,
        ];
    }

    private function heroResourceScarcityResponse(string $role, ?Settlement $settlement, int $corruptedResources): array
    {
        $settlementName = $settlement?->name ?? 'the region';

        return match ($role) {
            'warrior' => [
                'quest',
                "Protected {$settlementName} resource routes",
                1,
                -1,
                0,
                1,
                ['resource_guardian', 'protective'],
                ['hero_guarded', 'resource_watch'],
            ],
            'scholar' => [
                'research',
                "Mapped failing resource flows for {$settlementName}",
                1,
                0,
                1,
                0,
                ['resource_scholar', 'analytical'],
                ['resource_stewardship'],
            ],
            'prophet' => [
                $corruptedResources > 0 ? 'corruption_cleanse' : 'quest',
                "Led rites against scarcity around {$settlementName}",
                2,
                -1,
                1,
                0,
                ['restorative_mystic', 'compassionate'],
                ['cleansing_rites'],
            ],
            'agent of change' => [
                'trade',
                "Brokered resource relief for {$settlementName}",
                1,
                1,
                1,
                0,
                ['crisis_broker', 'practical'],
                ['resource_relief'],
            ],
            default => [
                'visit',
                "Surveyed resource strain near {$settlementName}",
                1,
                0,
                0,
                0,
                ['watchful'],
                ['resource_watch'],
            ],
        };
    }

    private function heroCrisisResponse(string $role, ?Settlement $settlement): array
    {
        $settlementName = $settlement?->name ?? 'the region';

        return match ($role) {
            'warrior' => [
                'quest',
                "Organized crisis defense for {$settlementName}",
                1,
                -1,
                0,
                2,
                ['guardian', 'battle_ready'],
                ['hero_guarded', 'crisis_sheltered'],
            ],
            'scholar' => [
                'research',
                "Catalogued crisis patterns around {$settlementName}",
                0,
                -1,
                1,
                0,
                ['crisis_cartographer', 'analytical'],
                ['watch_records'],
            ],
            'prophet' => [
                'quest',
                "Steadied fearful omens in {$settlementName}",
                2,
                -1,
                1,
                0,
                ['crisis_oracle', 'steadfast'],
                ['prophetic_guidance'],
            ],
            'agent of change' => [
                'establish_base',
                "Rallied emergency reforms in {$settlementName}",
                0,
                2,
                1,
                1,
                ['decisive', 'reformist'],
                ['reformist_civic'],
            ],
            default => [
                'visit',
                "Kept watch over {$settlementName}",
                1,
                0,
                0,
                1,
                ['watchful'],
                ['hero_guarded'],
            ],
        };
    }

    private function heroProsperityResponse(string $role, Settlement $settlement): array
    {
        return match ($role) {
            'scholar' => [
                'research',
                "Expanded civic learning in {$settlement->name}",
                1,
                -1,
                1,
                0,
                ['civic_scholar', 'curious'],
                ['heroic_scholarship'],
            ],
            'prophet' => [
                'visit',
                "Blessed civic rites in {$settlement->name}",
                2,
                -1,
                1,
                0,
                ['spiritual_mentor'],
                ['pilgrimage_site'],
            ],
            'warrior' => [
                'quest',
                "Turned prosperity into patrol discipline for {$settlement->name}",
                1,
                -1,
                0,
                1,
                ['honorable', 'guardian'],
                ['landmark_watch'],
            ],
            'agent of change' => [
                'trade',
                "Opened new civic bargains in {$settlement->name}",
                1,
                1,
                1,
                0,
                ['civic_broker', 'ambitious'],
                ['trade_culture_exchange'],
            ],
            default => [
                'visit',
                "Built civic ties in {$settlement->name}",
                1,
                0,
                0,
                0,
                ['civic_minded'],
                ['civic_minded'],
            ],
        };
    }

    private function applyHeroIdentityPressure(Hero $hero, array $identityPressure): void
    {
        $goodDelta = (int)($identityPressure['goodDelta'] ?? 0);
        $chaoticDelta = (int)($identityPressure['chaoticDelta'] ?? 0);

        if ($goodDelta !== 0 || $chaoticDelta !== 0) {
            $alignment = $this->normalHeroAlignment($hero->alignment);
            $alignment['good'] = $this->clamp($alignment['good'] + $goodDelta);
            $alignment['chaotic'] = $this->clamp($alignment['chaotic'] + $chaoticDelta);
            $alignment['lastChange'] = $identityPressure['summary'];
            $hero->alignment = $alignment;
        }

        $hero->personality_traits = $this->heroTraitsAfterIdentityPressure(
            $hero->personality_traits ?? [],
            $identityPressure
        );
    }

    private function recordHeroSettlementInteraction(Hero $hero, array $identityPressure, int $currentYear): ?array
    {
        $settlement = $identityPressure['settlement'] ?? null;
        $interactionType = $identityPressure['interactionType'] ?? null;
        $action = $identityPressure['action'] ?? null;

        if (!$settlement instanceof Settlement || !is_string($interactionType) || !is_string($action)) {
            return null;
        }
        if ($this->heroSettlementInteractionExists($hero, $settlement, $interactionType, $currentYear)) {
            return null;
        }

        try {
            $success = $this->roll((float)$identityPressure['successChance']);
            $outcomeDescription = $success
                ? "{$hero->name} succeeded: {$action}."
                : "{$hero->name} attempted to help {$settlement->name}, but the outcome remained uncertain.";

            HeroSettlementInteraction::create([
                'id' => 'hsi-' . bin2hex(random_bytes(8)),
                'hero_id' => $hero->id,
                'settlement_id' => $settlement->id,
                'landmark_id' => null,
                'action' => $action,
                'started_year' => $currentYear,
                'duration' => (int)$identityPressure['duration'],
                'success' => $success,
                'outcome_description' => $outcomeDescription,
                'interaction_type' => $interactionType,
            ]);

            if ($success) {
                $oldProsperity = (int)$settlement->prosperity;
                $oldDefensibility = (int)$settlement->defensibility;
                $oldTraits = $settlement->traits ?? [];
                $settlement->prosperity = $this->clamp(
                    $oldProsperity + (int)$identityPressure['settlementProsperityDelta']
                );
                $settlement->defensibility = $this->clamp(
                    $oldDefensibility + (int)$identityPressure['settlementDefensibilityDelta']
                );
                $settlement->traits = $this->settlementTraitsAfterHeroBond(
                    $oldTraits,
                    $identityPressure['settlementTraitSignals'] ?? []
                );

                if (
                    (int)$settlement->prosperity !== $oldProsperity ||
                    (int)$settlement->defensibility !== $oldDefensibility ||
                    $settlement->traits !== $oldTraits
                ) {
                    $settlement->save();
                }
            }

            return [
                'targetId' => (string)$settlement->id,
                'targetName' => (string)$settlement->name,
                'interactionType' => $interactionType,
                'action' => $action,
                'success' => $success,
                'outcomeDescription' => $outcomeDescription,
            ];
        } catch (\Throwable $error) {
            Logger::error('Hero settlement interaction failed', [
                'heroId' => $hero->id,
                'settlementId' => $settlement->id,
                'error' => $error->getMessage(),
            ]);
            return null;
        }
    }

    private function heroSettlementInteractionExists(
        Hero $hero,
        Settlement $settlement,
        string $interactionType,
        int $currentYear
    ): bool {
        return HeroSettlementInteraction::where('hero_id', $hero->id)
            ->where('settlement_id', $settlement->id)
            ->where('interaction_type', $interactionType)
            ->where('started_year', $currentYear)
            ->exists();
    }

    private function heroFocusSettlement(Hero $hero): ?Settlement
    {
        if (!$hero->region_id) {
            return null;
        }

        $query = Settlement::where('region_id', $hero->region_id);

        return match ((string)$hero->role) {
            'warrior' => $query->orderBy('defensibility')->orderByDesc('population')->first(),
            'scholar', 'prophet' => $query->orderByDesc('prosperity')->orderByDesc('population')->first(),
            'agent of change' => $query->orderByDesc('population')->orderByDesc('prosperity')->first(),
            default => $query->orderByDesc('population')->first(),
        };
    }

    private function normalHeroAlignment(mixed $alignment): array
    {
        $alignment = is_array($alignment) ? $alignment : [];
        $result = [
            'good' => $this->clamp((int)($alignment['good'] ?? 50)),
            'chaotic' => $this->clamp((int)($alignment['chaotic'] ?? 50)),
        ];

        if (isset($alignment['lastChange']) && is_string($alignment['lastChange'])) {
            $result['lastChange'] = $alignment['lastChange'];
        }

        return $result;
    }

    private function heroTraitsAfterIdentityPressure(array $traits, array $identityPressure): array
    {
        $result = array_values(array_unique(array_filter($traits)));

        foreach ($identityPressure['traitSignals'] ?? [] as $trait) {
            if (!is_string($trait) || $trait === '' || in_array($trait, $result, true)) {
                continue;
            }

            $result[] = $trait;
            if (count($result) >= 8) {
                break;
            }
        }

        return $result;
    }

    private function settlementTraitsAfterHeroBond(array $traits, array $traitSignals): array
    {
        $result = array_values(array_unique(array_filter($traits)));

        foreach ($traitSignals as $trait) {
            if (!is_string($trait) || $trait === '' || in_array($trait, $result, true)) {
                continue;
            }

            $result[] = $trait;
            if (count($result) >= 8) {
                break;
            }
        }

        return $result;
    }

    private function ids(array $values): array
    {
        $ids = [];

        foreach ($values as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $id = trim((string)$value);
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function appendChange(array &$summary, array $change, int $limit = 12): void
    {
        if (count($summary['changes']) >= $limit) {
            return;
        }

        $summary['changes'][] = $change;
    }

    private function attachEventToLatestChange(array &$summary, string $entityId, string $eventId): void
    {
        for ($index = count($summary['changes']) - 1; $index >= 0; $index--) {
            if (($summary['changes'][$index]['id'] ?? null) !== $entityId) {
                continue;
            }

            $summary['changes'][$index]['eventId'] ??= $eventId;
            $summary['changes'][$index]['eventIds'] ??= [];
            $summary['changes'][$index]['eventIds'][] = $eventId;
            return;
        }
    }

    private function appendResolvedBet(array &$summary, array $resolved, int $limit = 12): void
    {
        if (count($summary['resolved']) >= $limit) {
            return;
        }

        $summary['resolved'][] = $resolved;
    }

    private function recordEraPressureEventIfNeeded(
        array $eraPressure,
        ?array $previousEraPressure,
        int $currentYear
    ): ?string {
        if (!$this->eraPressureService->shouldRecordEvent($eraPressure, $previousEraPressure)) {
            return null;
        }

        return $this->recordEvent(
            $this->eraPressureService->eventTitle($eraPressure, $previousEraPressure),
            $this->eraPressureService->eventDescription($eraPressure),
            'era_pressure',
            null,
            $eraPressure['relatedRegionIds'] ?? [],
            $eraPressure['relatedHeroIds'] ?? [],
            $currentYear,
            $eraPressure['relatedSettlementIds'] ?? [],
            $eraPressure['relatedLandmarkIds'] ?? [],
            $eraPressure['relatedResourceIds'] ?? []
        );
    }

    private function recoverDivineFavor(): array
    {
        $player = Player::getSinglePlayer();
        $before = (int)$player->divine_favor;
        $player->addDivineFavor(self::DIVINE_FAVOR_PER_TICK);

        return [
            'before' => $before,
            'after' => (int)$player->fresh()->divine_favor,
            'recovered' => self::DIVINE_FAVOR_PER_TICK
        ];
    }

    private function refreshRegionPopulationTotals(): void
    {
        foreach (Region::all() as $region) {
            Region::where('id', $region->id)->update([
                'population_total' => Settlement::where('region_id', $region->id)->sum('population')
            ]);
        }
    }

    private function storeTickResult(array $result): void
    {
        $this->configService->setConfig(
            'simulation',
            'last_tick_result',
            $result,
            'array',
            'Most recent game-loop tick result'
        );
    }

    private function getQueueHealth(): array
    {
        try {
            $pdo = DatabaseService::getInstance()->getPdo();
            $jobs = $this->tableExists('jobs') ? (int)$pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn() : null;
            $failed = $this->tableExists('failed_jobs') ? (int)$pdo->query('SELECT COUNT(*) FROM failed_jobs')->fetchColumn() : null;

            return [
                'jobs' => $jobs,
                'failedJobs' => $failed,
                'available' => $jobs !== null && $failed !== null
            ];
        } catch (\Throwable $error) {
            return [
                'jobs' => null,
                'failedJobs' => null,
                'available' => false,
                'error' => $error->getMessage()
            ];
        }
    }

    private function tableExists(string $table): bool
    {
        $pdo = DatabaseService::getInstance()->getPdo();
        $stmt = $pdo->prepare('SHOW TABLES LIKE :table');
        $stmt->execute(['table' => $table]);
        return (bool)$stmt->fetchColumn();
    }

    private function settlementStatusFor(
        int $prosperity,
        int $population,
        int $defensibility = 50,
        int $regionDanger = 25,
        string $currentStatus = 'stable'
    ): string {
        if ($population <= 0) {
            return 'abandoned';
        }
        if ($currentStatus === 'ruined' && ($prosperity < 35 || $population < 50)) {
            return 'ruined';
        }
        if ($regionDanger >= 85 && $defensibility <= 30 && $prosperity <= 30) {
            return 'ruined';
        }
        if ($regionDanger >= 75 && $defensibility <= 25 && $prosperity <= 40) {
            return 'struggling';
        }
        if ($prosperity <= 15 && $regionDanger >= 65) {
            return 'ruined';
        }
        if ($prosperity >= 80) {
            return 'thriving';
        }
        if ($prosperity >= 60) {
            return 'prosperous';
        }
        if ($prosperity <= 20) {
            return 'struggling';
        }
        if ($prosperity <= 35) {
            return 'declining';
        }
        return 'stable';
    }

    private function settlementTypeFor(int $population, string $currentType): string
    {
        if ($population >= 10001) {
            return 'metropolis';
        }
        if ($population >= 2001) {
            return 'city';
        }
        if ($population >= 501) {
            return 'town';
        }
        if ($population >= 101) {
            return 'village';
        }
        return in_array($currentType, ['outpost', 'stronghold'], true) ? $currentType : 'hamlet';
    }

    private function shouldHeroDie(Hero $hero): bool
    {
        $age = (int)$hero->age;
        $level = (int)$hero->level;
        $lifeExpectancy = 70 + ($level * 2);
        if ($age > $lifeExpectancy && $this->roll(0.20)) {
            return true;
        }

        $regionDanger = $hero->region ? (int)$hero->region->danger_level : 25;
        $dangerChance = max(0.005, ($regionDanger / 1000) - ($level / 3000));
        return $this->roll($dangerChance);
    }

    private function getDeathReason(): string
    {
        $reason = HeroDeathReason::where('is_active', true)->inRandomOrder()->first();
        return $reason ? $reason->description : 'Died under mysterious circumstances';
    }

    private function crossedUp(int $oldValue, int $newValue, int $threshold): bool
    {
        return $oldValue < $threshold && $newValue >= $threshold;
    }

    private function crossedDown(int $oldValue, int $newValue, int $threshold): bool
    {
        return $oldValue >= $threshold && $newValue < $threshold;
    }

    private function sentenceFromNotes(array $notes): string
    {
        if ($notes === []) {
            return '';
        }

        return 'Threshold notes: ' . implode(' ', $notes);
    }

    private function recordEvent(
        string $title,
        string $description,
        string $type,
        ?string $regionId,
        array $relatedRegionIds,
        array $relatedHeroIds,
        int $year,
        array $relatedSettlementIds = [],
        array $relatedLandmarkIds = [],
        array $relatedResourceIds = []
    ): string {
        $eventId = 'event-' . bin2hex(random_bytes(8));

        GameEvent::create([
            'id' => $eventId,
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'status' => 'completed',
            'region_id' => $regionId,
            'timestamp' => date('c'),
            'related_region_ids' => array_values(array_filter($relatedRegionIds)),
            'related_hero_ids' => array_values(array_filter($relatedHeroIds)),
            'related_settlement_ids' => array_values(array_filter($relatedSettlementIds)),
            'related_landmark_ids' => array_values(array_filter($relatedLandmarkIds)),
            'related_resource_ids' => array_values(array_filter($relatedResourceIds)),
            'year' => $year
        ]);

        return $eventId;
    }

    private function roll(float $chance): bool
    {
        return random_int(1, 10000) <= (int)round(max(0, min(1, $chance)) * 10000);
    }

    private function clamp(int|float $value, int $min = 0, int $max = 100): int
    {
        return (int)max($min, min($max, round($value)));
    }
}
