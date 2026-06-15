<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DivineBet;
use App\Models\GameEvent;
use App\Models\GameState;
use App\Models\Hero;
use App\Models\Landmark;
use App\Models\Region;
use App\Models\ResourceNode;
use App\Models\Settlement;
use Illuminate\Database\Capsule\Manager as DB;

class EraTransitionService
{
    private const ERA_LENGTH_YEARS = 100;
    private const HISTORY_LIMIT = 12;

    public function __construct(
        private ?GameConfigService $configService = null,
        private ?EraPressureService $eraPressureService = null,
        private ?EraLegacyService $eraLegacyService = null,
        private ?EraComparisonService $eraComparisonService = null,
        private ?EraGenerationService $eraGenerationService = null
    ) {
        $this->configService ??= GameConfigService::getInstance();
        $this->eraPressureService ??= new EraPressureService();
        $this->eraLegacyService ??= new EraLegacyService($this->eraPressureService);
        $this->eraComparisonService ??= new EraComparisonService($this->configService);
        $this->eraGenerationService ??= new EraGenerationService();
    }

    public function status(int $currentYear, ?array $eraPressure = null, ?array $eraLegacy = null): array
    {
        $eraPressure ??= $this->eraPressureService->evaluate($currentYear);
        $eraLegacy ??= $this->eraLegacyService->evaluate($currentYear, $eraPressure);
        $history = $this->history();
        $lastCompletedEra = $this->lastCompletedEra($history);
        $currentEra = EraPressureService::currentEraForYear($currentYear);
        $eraYear = (($currentYear - 1) % self::ERA_LENGTH_YEARS) + 1;
        $calendarDueEra = $currentYear > 1 && $eraYear !== self::ERA_LENGTH_YEARS
            ? max(0, $currentEra - 1)
            : $currentEra;
        if ($calendarDueEra <= $lastCompletedEra) {
            $calendarDueEra = $currentEra;
        }

        $pressureBreaking = (string)($eraPressure['tier'] ?? '') === 'breaking';
        $calendarEligible = $calendarDueEra > $lastCompletedEra &&
            $currentYear >= ($calendarDueEra * self::ERA_LENGTH_YEARS);
        $pressureEligible = $pressureBreaking && $currentEra > $lastCompletedEra;
        $eligible = $calendarEligible || $pressureEligible;
        $transitionEra = $pressureEligible ? $currentEra : $calendarDueEra;
        if ($transitionEra <= $lastCompletedEra) {
            $transitionEra = $lastCompletedEra + 1;
        }

        $nextEra = $transitionEra + 1;
        $nextEraYear = ($transitionEra * self::ERA_LENGTH_YEARS) + 1;
        $triggerReason = $pressureEligible
            ? 'Era pressure reached a breaking point.'
            : ($calendarEligible ? "Era {$transitionEra} reached its calendar boundary." : 'No era transition is currently due.');

        return [
            'currentEra' => $currentEra,
            'currentYear' => $currentYear,
            'eraYear' => $eraYear,
            'lastCompletedEra' => $lastCompletedEra,
            'transitionEra' => $transitionEra,
            'nextEra' => $nextEra,
            'eraEndYear' => $transitionEra * self::ERA_LENGTH_YEARS,
            'nextEraYear' => $nextEraYear,
            'eligible' => $eligible,
            'requiresForce' => !$eligible,
            'triggerReason' => $triggerReason,
            'summary' => $eligible
                ? "Era {$transitionEra} is ready to close; Era {$nextEra} can begin in year {$nextEraYear}."
                : "Era transition is watchful. {$triggerReason}",
            'pressureScore' => (int)($eraPressure['pressureScore'] ?? 0),
            'continuityScore' => (int)($eraLegacy['continuityScore'] ?? 0),
            'preview' => $this->preview($transitionEra, $nextEra, $nextEraYear, $eraPressure, $eraLegacy),
            'history' => $history,
        ];
    }

    public function transition(bool $force = false): array
    {
        $gameState = GameState::getCurrent();
        $currentYear = (int)$gameState->current_year;
        $eraPressure = $this->eraPressureService->evaluate($currentYear);
        $eraLegacy = $this->eraLegacyService->evaluate($currentYear, $eraPressure);
        $status = $this->status($currentYear, $eraPressure, $eraLegacy);

        if (!$force && !$status['eligible']) {
            throw new \RuntimeException('Era transition is not due yet. Pass force=true from an admin route to close the era early.');
        }

        if ($force && !$status['eligible']) {
            $status['triggerReason'] = 'Era transition was forced by an administrator.';
            $status['requiresForce'] = false;
        }

        $transitionEra = (int)$status['transitionEra'];
        $nextEra = (int)$status['nextEra'];
        $nextEraYear = (int)$status['nextEraYear'];

        return DB::connection()->transaction(function () use (
            $gameState,
            $currentYear,
            $transitionEra,
            $nextEra,
            $nextEraYear,
            $eraPressure,
            $eraLegacy,
            $status,
            $force
        ): array {
            $beforeSnapshot = $this->eraComparisonService->worldSnapshot($currentYear);
            $changes = [
                'regions' => $this->transformRegions($eraLegacy, $transitionEra, $nextEra),
                'settlements' => $this->transformSettlements($eraLegacy, $transitionEra, $nextEra),
                'heroes' => $this->transformHeroes($eraLegacy, $transitionEra, $nextEra),
                'landmarks' => $this->transformLandmarks($eraLegacy, $transitionEra, $nextEra),
                'resources' => $this->transformResources($eraLegacy, $transitionEra, $nextEra),
                'bets' => $this->transformBets($eraLegacy, $transitionEra, $nextEra, max($currentYear, $nextEraYear)),
            ];
            $changes['generation'] = $this->eraGenerationService->generate(
                $transitionEra,
                $nextEra,
                max($currentYear, $nextEraYear),
                $eraLegacy,
                $eraPressure
            );
            $this->refreshRegionPopulationTotals();

            $newCurrentYear = max($currentYear, $nextEraYear);
            $gameState->current_year = $newCurrentYear;
            $gameState->save();
            $afterSnapshot = $this->eraComparisonService->worldSnapshot($newCurrentYear);

            $eventId = $this->recordTransitionEvent(
                $transitionEra,
                $nextEra,
                $newCurrentYear,
                $status['triggerReason'],
                $eraPressure,
                $eraLegacy
            );
            $historyEntry = $this->historyEntry(
                $transitionEra,
                $nextEra,
                $currentYear,
                $newCurrentYear,
                $status['triggerReason'],
                $force,
                $eventId,
                $eraPressure,
                $eraLegacy,
                $changes,
                $beforeSnapshot,
                $afterSnapshot
            );
            $history = $this->prependHistory($historyEntry);
            $this->configService->setConfig(
                'era',
                'transition_history',
                $history,
                'array',
                'Completed era transitions and carry-forward summaries'
            );

            return [
                'currentEra' => EraPressureService::currentEraForYear($newCurrentYear),
                'currentYear' => $newCurrentYear,
                'eligible' => true,
                'transitionEra' => $transitionEra,
                'completedEra' => $transitionEra,
                'nextEra' => $nextEra,
                'nextEraYear' => $nextEraYear,
                'eventId' => $eventId,
                'forced' => $force,
                'triggerReason' => $status['triggerReason'],
                'summary' => $historyEntry['summary'],
                'changes' => $changes,
                'historyEntry' => $historyEntry,
                'history' => $history,
            ];
        });
    }

    public function history(): array
    {
        $history = $this->configService->getConfig('era', 'transition_history', []);
        return is_array($history) ? array_values($history) : [];
    }

    private function preview(
        int $transitionEra,
        int $nextEra,
        int $nextEraYear,
        array $eraPressure,
        array $eraLegacy
    ): array {
        return [
            'completedEra' => $transitionEra,
            'nextEra' => $nextEra,
            'nextEraYear' => $nextEraYear,
            'pressureTrigger' => $eraPressure['highestTrigger']['label'] ?? 'Unknown',
            'readinessLabel' => $eraLegacy['readinessLabel'] ?? 'unknown continuity',
            'counts' => [
                'regions' => Region::count(),
                'settlements' => Settlement::count(),
                'heroes' => Hero::count(),
                'landmarks' => Landmark::count(),
                'resources' => ResourceNode::count(),
                'heroLegacies' => count($eraLegacy['heroLegacies'] ?? []),
                'bloodlineSeeds' => count($eraLegacy['bloodlineSeeds'] ?? []),
                'landmarkLegacies' => count($eraLegacy['landmarkLegacies'] ?? []),
                'worldScars' => count($eraLegacy['worldScars'] ?? []),
                'carriedMyths' => count($eraLegacy['carriedMyths'] ?? []),
                'eraSpanningBets' => count($eraLegacy['eraSpanningBets'] ?? []),
            ],
            'carryForward' => [
                'heroes' => array_map(
                    fn(array $hero): string => (string)$hero['name'],
                    array_slice($eraLegacy['heroLegacies'] ?? [], 0, 5)
                ),
                'landmarks' => array_map(
                    fn(array $landmark): string => (string)$landmark['name'],
                    array_slice($eraLegacy['landmarkLegacies'] ?? [], 0, 5)
                ),
                'scars' => array_map(
                    fn(array $scar): string => (string)$scar['targetName'],
                    array_slice($eraLegacy['worldScars'] ?? [], 0, 5)
                ),
                'myths' => array_map(
                    fn(array $myth): string => (string)$myth['title'],
                    array_slice($eraLegacy['carriedMyths'] ?? [], 0, 5)
                ),
            ],
        ];
    }

    private function transformRegions(array $eraLegacy, int $transitionEra, int $nextEra): array
    {
        $scarRegionIds = $this->idsFromItems($eraLegacy['worldScars'] ?? [], 'regionId');
        $legacyRegionIds = $eraLegacy['relatedRegionIds'] ?? [];
        $changed = [];

        foreach (Region::all() as $region) {
            $before = $this->regionState($region);
            $isScarred = in_array($region->id, $scarRegionIds, true);
            $isLegacy = in_array($region->id, $legacyRegionIds, true);
            $prosperity = $this->clamp(((int)$region->prosperity * ($isScarred ? 0.45 : 0.62)) + ($isLegacy ? 18 : 9) + random_int(-6, 8));
            $chaos = $this->clamp(((int)$region->chaos * 0.42) + ($isScarred ? 22 : 8) + random_int(-5, 10));
            $danger = $this->clamp(((int)$region->danger_level * 0.48) + ($isScarred ? 26 : 6) + random_int(-4, 12));
            $magic = $this->clamp(((int)$region->magic_affinity * 0.58) + ($isLegacy ? 14 : 6) + random_int(-8, 12));
            $region->prosperity = $prosperity;
            $region->chaos = $chaos;
            $region->danger_level = $danger;
            $region->magic_affinity = $magic;
            $region->status = $this->regionStatusFor($prosperity, $chaos, $danger, $magic);
            $region->regional_traits = $this->traitsAfterTransition(
                $region->regional_traits ?? [],
                $transitionEra,
                $nextEra,
                $isLegacy,
                $isScarred
            );
            $region->population_total = (int)Settlement::where('region_id', $region->id)->sum('population');
            $region->save();
            $after = $this->regionState($region);
            $changed[] = [
                'id' => $region->id,
                'name' => $region->name,
                'before' => $before,
                'after' => $after,
                'summary' => "{$region->name} entered Era {$nextEra} as {$region->status}.",
            ];
        }

        return ['processed' => count($changed), 'changed' => count($changed), 'changes' => $changed];
    }

    private function transformSettlements(array $eraLegacy, int $transitionEra, int $nextEra): array
    {
        $legacySettlementIds = $this->idsFromItems($eraLegacy['bloodlineSeeds'] ?? [], 'settlementId');
        $scarSettlementIds = $this->idsFromItems($eraLegacy['worldScars'] ?? [], 'settlementId');
        $changed = [];

        foreach (Settlement::all() as $settlement) {
            $before = $this->settlementState($settlement);
            $isLegacy = in_array($settlement->id, $legacySettlementIds, true);
            $isScarred = in_array($settlement->id, $scarSettlementIds, true);
            $populationFactor = $isScarred ? 0.34 : ($isLegacy ? 0.78 : 0.55);
            $population = max($isScarred ? 0 : 25, (int)round((int)$settlement->population * $populationFactor));
            $prosperity = $this->clamp(((int)$settlement->prosperity * ($isScarred ? 0.45 : 0.6)) + ($isLegacy ? 18 : 8) + random_int(-5, 8));
            $defense = $this->clamp(((int)$settlement->defensibility * 0.58) + ($isLegacy ? 14 : 6) + random_int(-4, 8));
            $settlement->population = $population;
            $settlement->prosperity = $prosperity;
            $settlement->defensibility = $defense;
            $settlement->status = $this->settlementStatusFor($prosperity, $population, $defense, $isScarred);
            $settlement->type = $this->settlementTypeFor($population, (string)$settlement->type);
            $settlement->traits = $this->traitsAfterTransition(
                $settlement->traits ?? [],
                $transitionEra,
                $nextEra,
                $isLegacy,
                $isScarred
            );
            $settlement->last_event_year = max($transitionEra * self::ERA_LENGTH_YEARS, 1);
            $settlement->save();
            $after = $this->settlementState($settlement);
            $changed[] = [
                'id' => $settlement->id,
                'name' => $settlement->name,
                'regionId' => $settlement->region_id,
                'before' => $before,
                'after' => $after,
                'summary' => "{$settlement->name} carried into Era {$nextEra} as a {$settlement->type} with {$settlement->status} status.",
            ];
        }

        return ['processed' => count($changed), 'changed' => count($changed), 'changes' => $changed];
    }

    private function refreshRegionPopulationTotals(): void
    {
        foreach (Region::all() as $region) {
            $region->population_total = (int)Settlement::where('region_id', $region->id)->sum('population');
            $region->save();
        }
    }

    private function transformHeroes(array $eraLegacy, int $transitionEra, int $nextEra): array
    {
        $legacyHeroIds = $this->idsFromItems($eraLegacy['heroLegacies'] ?? [], 'heroId');
        $bloodlineHeroIds = $this->idsFromItems($eraLegacy['bloodlineSeeds'] ?? [], 'sourceHeroId');
        $changed = [];

        foreach (Hero::all() as $hero) {
            $before = $this->heroState($hero);
            $isLegacy = in_array($hero->id, $legacyHeroIds, true) || in_array($hero->id, $bloodlineHeroIds, true);
            $oldLevel = (int)($hero->level ?? 1);
            if ($isLegacy) {
                $hero->is_alive = true;
                $hero->status = 'living';
                $hero->death_reason = null;
                $hero->age = random_int(18, 34);
                $hero->level = max(1, min(18, (int)round($oldLevel * 0.45) + random_int(1, 4)));
                $hero->feats = $this->appendUnique($hero->feats ?? [], "Reborn into Era {$nextEra} from Era {$transitionEra} legacy");
            } else {
                $hero->age = (int)($hero->age ?? 20) + random_int(8, 25);
                $hero->level = max(1, (int)round($oldLevel * 0.5));
                if ((int)$hero->age >= 75 || random_int(1, 100) <= 35) {
                    $hero->is_alive = false;
                    $hero->status = 'deceased';
                    $hero->death_reason = "Passed into legend during the close of Era {$transitionEra}";
                }
                $hero->feats = $this->appendUnique($hero->feats ?? [], "Remembered at the dawn of Era {$nextEra}");
            }
            $hero->save();
            $after = $this->heroState($hero);
            $changed[] = [
                'id' => $hero->id,
                'name' => $hero->name,
                'regionId' => $hero->region_id,
                'legacy' => $isLegacy,
                'before' => $before,
                'after' => $after,
                'summary' => $isLegacy
                    ? "{$hero->name} reincarnated into Era {$nextEra}."
                    : "{$hero->name} became a fading memory of Era {$transitionEra}.",
            ];
        }

        return ['processed' => count($changed), 'changed' => count($changed), 'changes' => $changed];
    }

    private function transformLandmarks(array $eraLegacy, int $transitionEra, int $nextEra): array
    {
        $legacyLandmarkIds = $this->idsFromItems($eraLegacy['landmarkLegacies'] ?? [], 'landmarkId');
        $scarLandmarkIds = $this->idsFromItems($eraLegacy['worldScars'] ?? [], 'landmarkId');
        $changed = [];

        foreach (Landmark::all() as $landmark) {
            $before = $this->landmarkState($landmark);
            $isLegacy = in_array($landmark->id, $legacyLandmarkIds, true);
            $isScarred = in_array($landmark->id, $scarLandmarkIds, true);
            $landmark->magic_level = $this->clamp(((int)$landmark->magic_level * 0.72) + ($isLegacy ? 14 : 4) + random_int(-5, 8));
            $landmark->danger_level = $this->clamp(((int)$landmark->danger_level * 0.62) + ($isScarred ? 22 : 2) + random_int(-4, 8));
            $landmark->status = $this->landmarkStatusFor($landmark, $isLegacy, $isScarred);
            $landmark->traits = $this->traitsAfterTransition(
                $landmark->traits ?? [],
                $transitionEra,
                $nextEra,
                $isLegacy,
                $isScarred
            );
            $landmark->save();
            $after = $this->landmarkState($landmark);
            $changed[] = [
                'id' => $landmark->id,
                'name' => $landmark->name,
                'regionId' => $landmark->region_id,
                'before' => $before,
                'after' => $after,
                'summary' => "{$landmark->name} persisted into Era {$nextEra} as {$landmark->status}.",
            ];
        }

        return ['processed' => count($changed), 'changed' => count($changed), 'changes' => $changed];
    }

    private function transformResources(array $eraLegacy, int $transitionEra, int $nextEra): array
    {
        $scarResourceIds = $this->idsFromItems($eraLegacy['worldScars'] ?? [], 'resourceId');
        $changed = [];

        foreach (ResourceNode::all() as $resource) {
            $before = $this->resourceState($resource);
            $isScarred = in_array($resource->id, $scarResourceIds, true);
            $resource->output = $this->clamp(((int)$resource->output * ($isScarred ? 0.45 : 0.65)) + random_int(8, 24));
            $resource->status = $this->resourceStatusFor($resource, $isScarred);
            $resource->save();
            $after = $this->resourceState($resource);
            $changed[] = [
                'id' => $resource->id,
                'name' => $resource->name,
                'regionId' => $resource->region_id,
                'settlementId' => $resource->settlement_id,
                'before' => $before,
                'after' => $after,
                'summary' => "{$resource->name} reformed as {$resource->status} with output {$resource->output}.",
            ];
        }

        return ['processed' => count($changed), 'changed' => count($changed), 'changes' => $changed];
    }

    private function transformBets(array $eraLegacy, int $transitionEra, int $nextEra, int $resolvedYear): array
    {
        $carriedBetIds = $this->idsFromItems($eraLegacy['eraSpanningBets'] ?? [], 'betId');
        $carried = [];
        $expired = [];

        foreach (DivineBet::where('status', 'active')->get() as $bet) {
            if (in_array($bet->id, $carriedBetIds, true)) {
                $bet->resolution_notes = trim((string)($bet->resolution_notes ?? '') . " Carried from Era {$transitionEra} into Era {$nextEra}.");
                $bet->save();
                $carried[] = [
                    'id' => $bet->id,
                    'description' => $bet->description,
                    'summary' => "{$bet->description} remains active across the era boundary.",
                ];
                continue;
            }

            $bet->resolve(
                'expired',
                $resolvedYear,
                "Era {$transitionEra} ended before this prophecy could resolve."
            );
            $expired[] = [
                'id' => $bet->id,
                'description' => $bet->description,
                'summary' => "{$bet->description} expired with Era {$transitionEra}.",
            ];
        }

        return [
            'processed' => count($carried) + count($expired),
            'carried' => count($carried),
            'expired' => count($expired),
            'carriedBets' => $carried,
            'expiredBets' => $expired,
        ];
    }

    private function recordTransitionEvent(
        int $transitionEra,
        int $nextEra,
        int $currentYear,
        string $triggerReason,
        array $eraPressure,
        array $eraLegacy
    ): string {
        $eventId = 'event-' . bin2hex(random_bytes(8));
        $summary = "Era {$transitionEra} ended and Era {$nextEra} began in year {$currentYear}. {$triggerReason} " .
            "{$eraLegacy['summary']} {$eraPressure['summary']}";

        GameEvent::create([
            'id' => $eventId,
            'title' => "Era {$transitionEra} Closes",
            'description' => $summary,
            'type' => 'era_transition',
            'status' => 'completed',
            'region_id' => null,
            'timestamp' => date('c'),
            'related_region_ids' => $eraLegacy['relatedRegionIds'] ?? [],
            'related_hero_ids' => $eraLegacy['relatedHeroIds'] ?? [],
            'related_settlement_ids' => $eraLegacy['relatedSettlementIds'] ?? [],
            'related_landmark_ids' => $eraLegacy['relatedLandmarkIds'] ?? [],
            'related_resource_ids' => $eraLegacy['relatedResourceIds'] ?? [],
            'year' => $currentYear,
        ]);

        return $eventId;
    }

    private function historyEntry(
        int $transitionEra,
        int $nextEra,
        int $previousYear,
        int $currentYear,
        string $triggerReason,
        bool $force,
        string $eventId,
        array $eraPressure,
        array $eraLegacy,
        array $changes,
        array $beforeSnapshot,
        array $afterSnapshot
    ): array {
        $summary = "Era {$transitionEra} closed into Era {$nextEra}: " .
            "{$changes['heroes']['changed']} heroes, {$changes['settlements']['changed']} settlements, " .
            "{$changes['regions']['changed']} regions, {$changes['resources']['changed']} resources, and " .
            "{$changes['bets']['carried']} bets carried forward. " .
            (string)($changes['generation']['summary'] ?? '');
        $transitionDelta = $this->eraComparisonService->compareSnapshots($beforeSnapshot, $afterSnapshot);

        return [
            'id' => 'era-transition-' . bin2hex(random_bytes(6)),
            'completedEra' => $transitionEra,
            'nextEra' => $nextEra,
            'previousYear' => $previousYear,
            'currentYear' => $currentYear,
            'transitionedAt' => date('c'),
            'triggerReason' => $triggerReason,
            'forced' => $force,
            'eventId' => $eventId,
            'pressureScore' => (int)($eraPressure['pressureScore'] ?? 0),
            'continuityScore' => (int)($eraLegacy['continuityScore'] ?? 0),
            'summary' => $summary,
            'carried' => [
                'heroes' => array_map(fn(array $hero): string => (string)$hero['name'], array_slice($eraLegacy['heroLegacies'] ?? [], 0, 6)),
                'landmarks' => array_map(fn(array $landmark): string => (string)$landmark['name'], array_slice($eraLegacy['landmarkLegacies'] ?? [], 0, 6)),
                'scars' => array_map(fn(array $scar): string => (string)$scar['targetName'], array_slice($eraLegacy['worldScars'] ?? [], 0, 6)),
                'myths' => array_map(fn(array $myth): string => (string)$myth['title'], array_slice($eraLegacy['carriedMyths'] ?? [], 0, 6)),
            ],
            'changeCounts' => [
                'regions' => $changes['regions']['changed'],
                'settlements' => $changes['settlements']['changed'],
                'heroes' => $changes['heroes']['changed'],
                'landmarks' => $changes['landmarks']['changed'],
                'resources' => $changes['resources']['changed'],
                'carriedBets' => $changes['bets']['carried'],
                'expiredBets' => $changes['bets']['expired'],
                'generatedSettlements' => count($changes['generation']['settlements'] ?? []),
                'generatedHeroes' => count($changes['generation']['heroes'] ?? []),
                'generatedLandmarks' => count($changes['generation']['landmarks'] ?? []),
                'generatedResources' => count($changes['generation']['resources'] ?? []),
            ],
            'generated' => [
                'eventId' => $changes['generation']['eventId'] ?? null,
                'summary' => $changes['generation']['summary'] ?? null,
                'settlements' => $changes['generation']['settlements'] ?? [],
                'heroes' => $changes['generation']['heroes'] ?? [],
                'landmarks' => $changes['generation']['landmarks'] ?? [],
                'resources' => $changes['generation']['resources'] ?? [],
            ],
            'beforeSnapshot' => $beforeSnapshot,
            'afterSnapshot' => $afterSnapshot,
            'transitionDelta' => $transitionDelta,
            'comparisonSummary' => $this->eraComparisonService->comparisonSummary($transitionDelta),
        ];
    }

    private function prependHistory(array $entry): array
    {
        $history = $this->history();
        array_unshift($history, $entry);
        return array_slice($history, 0, self::HISTORY_LIMIT);
    }

    private function lastCompletedEra(array $history): int
    {
        $completed = array_map(
            fn(array $entry): int => (int)($entry['completedEra'] ?? 0),
            $history
        );

        return $completed === [] ? 0 : max($completed);
    }

    private function idsFromItems(array $items, string $key): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn(array $item): ?string => isset($item[$key]) && $item[$key] !== null ? (string)$item[$key] : null,
            $items
        ))));
    }

    private function traitsAfterTransition(array $traits, int $transitionEra, int $nextEra, bool $isLegacy, bool $isScarred): array
    {
        $result = array_slice(array_values(array_unique(array_filter($traits))), 0, 5);
        $result[] = "legacy_of_era_{$transitionEra}";
        $result[] = "era_{$nextEra}_born";
        if ($isLegacy) {
            $result[] = 'legacy_anchor';
        }
        if ($isScarred) {
            $result[] = 'era_scarred';
        }

        return array_values(array_unique($result));
    }

    private function appendUnique(array $items, string $item): array
    {
        $items[] = $item;
        return array_values(array_unique(array_filter($items)));
    }

    private function regionStatusFor(int $prosperity, int $chaos, int $danger, int $magic): string
    {
        if ($magic >= 75 && $chaos >= 45) {
            return 'mysterious';
        }
        if ($danger >= 75 || $chaos >= 75) {
            return 'turbulent';
        }
        if ($prosperity >= 82 && $chaos <= 30 && $danger <= 35) {
            return 'flourishing';
        }
        if ($prosperity >= 65) {
            return 'prosperous';
        }
        if ($prosperity <= 25) {
            return 'declining';
        }

        return 'stable';
    }

    private function settlementStatusFor(int $prosperity, int $population, int $defense, bool $isScarred): string
    {
        if ($population <= 0) {
            return 'abandoned';
        }
        if ($isScarred && $prosperity <= 30) {
            return 'ruined';
        }
        if ($prosperity >= 80) {
            return 'thriving';
        }
        if ($prosperity >= 60 || $defense >= 70) {
            return 'prosperous';
        }
        if ($prosperity <= 25) {
            return 'struggling';
        }
        if ($prosperity <= 40) {
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

    private function landmarkStatusFor(Landmark $landmark, bool $isLegacy, bool $isScarred): string
    {
        if ($isScarred && (int)$landmark->danger_level >= 65) {
            return $landmark->status === Landmark::STATUS_CORRUPTED ? Landmark::STATUS_CORRUPTED : Landmark::STATUS_HAUNTED;
        }
        if ($isLegacy && ($landmark->isSacred() || (int)$landmark->magic_level >= 70)) {
            return Landmark::STATUS_BLESSED;
        }
        if ((int)$landmark->magic_level >= 55) {
            return Landmark::STATUS_ACTIVE;
        }

        return Landmark::STATUS_WEATHERED;
    }

    private function resourceStatusFor(ResourceNode $resource, bool $isScarred): string
    {
        if ($isScarred) {
            return $resource->isMagical() ? 'unstable' : 'contested';
        }
        if ((int)$resource->output >= 80) {
            return 'status-flourishing';
        }
        if ($resource->isMagical() && random_int(1, 100) <= 25) {
            return 'blessed';
        }

        return 'active';
    }

    private function regionState(Region $region): array
    {
        return [
            'prosperity' => (int)$region->prosperity,
            'chaos' => (int)$region->chaos,
            'dangerLevel' => (int)$region->danger_level,
            'magicAffinity' => (int)$region->magic_affinity,
            'status' => (string)$region->status,
            'traits' => $region->regional_traits ?? [],
        ];
    }

    private function settlementState(Settlement $settlement): array
    {
        return [
            'population' => (int)$settlement->population,
            'prosperity' => (int)$settlement->prosperity,
            'defensibility' => (int)$settlement->defensibility,
            'type' => (string)$settlement->type,
            'status' => (string)$settlement->status,
            'traits' => $settlement->traits ?? [],
        ];
    }

    private function heroState(Hero $hero): array
    {
        return [
            'level' => (int)($hero->level ?? 1),
            'age' => (int)($hero->age ?? 20),
            'isAlive' => (bool)($hero->is_alive ?? true),
            'status' => (string)($hero->status ?? 'living'),
            'feats' => $hero->feats ?? [],
        ];
    }

    private function landmarkState(Landmark $landmark): array
    {
        return [
            'magicLevel' => (int)$landmark->magic_level,
            'dangerLevel' => (int)$landmark->danger_level,
            'status' => (string)$landmark->status,
            'traits' => $landmark->traits ?? [],
        ];
    }

    private function resourceState(ResourceNode $resource): array
    {
        return [
            'output' => (int)$resource->output,
            'status' => (string)$resource->status,
        ];
    }

    private function clamp(int|float $value, int $min = 0, int $max = 100): int
    {
        return (int)max($min, min($max, round($value)));
    }
}
