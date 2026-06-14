<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DivineBet;
use App\Models\GameEvent;
use App\Models\GameState;
use App\Models\Hero;
use App\Models\HeroDeathReason;
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
        private ?GameConfigService $configService = null
    ) {
        $this->configService ??= GameConfigService::getInstance();
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

        $result = [
            'startedAt' => $startedAt,
            'completedAt' => null,
            'previousYear' => $previousYear,
            'currentYear' => $tickYear,
            'advancedYear' => $advanceYear,
            'regions' => ['processed' => 0, 'changed' => 0, 'events' => 0, 'errors' => []],
            'settlements' => ['processed' => 0, 'changed' => 0, 'events' => 0, 'errors' => []],
            'heroes' => ['processed' => 0, 'changed' => 0, 'events' => 0, 'errors' => []],
            'resources' => ['processed' => 0, 'changed' => 0, 'events' => 0, 'errors' => []],
            'bets' => ['processed' => 0, 'won' => 0, 'lost' => 0, 'expired' => 0, 'errors' => []],
            'divineFavor' => ['before' => 0, 'after' => 0, 'recovered' => self::DIVINE_FAVOR_PER_TICK],
            'errors' => []
        ];

        try {
            $result['regions'] = $this->processRegions($tickYear);
            $result['settlements'] = $this->processSettlements($tickYear);
            $result['resources'] = $this->processResources($tickYear);
            $result['heroes'] = $this->processHeroes($tickYear);
            $result['bets'] = $this->processActiveBets($tickYear);
            $result['divineFavor'] = $this->recoverDivineFavor();

            if ($advanceYear) {
                $gameState->current_year = $tickYear;
                $gameState->save();
            }

            $this->recordEvent(
                'World Tick Completed',
                "Year {$tickYear} advanced: {$result['regions']['changed']} regions, {$result['settlements']['changed']} settlements, {$result['heroes']['changed']} heroes, and {$result['bets']['processed']} bets changed state.",
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
        $summary = ['processed' => 0, 'changed' => 0, 'events' => 0, 'errors' => []];

        foreach (Region::all() as $region) {
            $summary['processed']++;
            try {
                $oldProsperity = (int)$region->prosperity;
                $oldChaos = (int)$region->chaos;
                $oldDanger = (int)($region->danger_level ?? 0);

                $prosperityDelta = $oldChaos > 70 ? -3 : ($oldChaos < 30 ? 2 : 1);
                $chaosDelta = $oldProsperity > 75 ? -2 : 1;
                $dangerDelta = $oldChaos >= 60 ? 2 : -1;

                $region->prosperity = $this->clamp($oldProsperity + $prosperityDelta);
                $region->chaos = $this->clamp($oldChaos + $chaosDelta);
                $region->danger_level = $this->clamp($oldDanger + $dangerDelta);

                if ($region->chaos >= 80 || $region->danger_level >= 80) {
                    $region->status = 'war_torn';
                } elseif ($region->prosperity >= 85 && $region->chaos <= 25) {
                    $region->status = 'flourishing';
                } elseif ($region->prosperity >= 70 && $region->chaos <= 45) {
                    $region->status = 'prosperous';
                } elseif ($region->chaos >= 65) {
                    $region->status = 'turbulent';
                }

                if (
                    $region->prosperity !== $oldProsperity ||
                    $region->chaos !== $oldChaos ||
                    $region->danger_level !== $oldDanger
                ) {
                    $region->save();
                    $summary['changed']++;
                }

                if (abs($region->prosperity - $oldProsperity) >= 3 || abs($region->chaos - $oldChaos) >= 3) {
                    $this->recordEvent(
                        'Regional Drift',
                        "{$region->name} shifted to prosperity {$region->prosperity}, chaos {$region->chaos}, and danger {$region->danger_level}.",
                        'region_tick',
                        $region->id,
                        [$region->id],
                        [],
                        $currentYear
                    );
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
        $summary = ['processed' => 0, 'changed' => 0, 'events' => 0, 'errors' => []];

        foreach (Settlement::all() as $settlement) {
            $summary['processed']++;
            try {
                $oldPopulation = (int)$settlement->population;
                $oldProsperity = (int)$settlement->prosperity;
                $region = $settlement->region;

                $regionProsperity = $region ? (int)$region->prosperity : 50;
                $regionChaos = $region ? (int)$region->chaos : 50;
                $growthRate = 0.02 + (($oldProsperity - 50) / 2500) + (($regionProsperity - 50) / 3000) - ($regionChaos / 5000);
                $growthRate = max(-0.03, min(0.08, $growthRate));
                $newPopulation = max(0, (int)round($oldPopulation * (1 + $growthRate)));

                $prosperityDelta = $regionChaos > 70 ? -3 : ($regionProsperity > 70 ? 2 : 1);
                $newProsperity = $this->clamp($oldProsperity + $prosperityDelta);

                $settlement->population = $newPopulation;
                $settlement->prosperity = $newProsperity;
                $settlement->status = $this->settlementStatusFor($newProsperity, $newPopulation);
                $settlement->last_event_year = $currentYear;
                $settlement->type = $this->settlementTypeFor($newPopulation, $settlement->type);

                if ($newPopulation !== $oldPopulation || $newProsperity !== $oldProsperity) {
                    $settlement->save();
                    $summary['changed']++;
                }

                if (abs($newPopulation - $oldPopulation) >= max(10, (int)round($oldPopulation * 0.04))) {
                    $this->recordEvent(
                        'Settlement Shift',
                        "{$settlement->name} changed from population {$oldPopulation} to {$newPopulation}.",
                        'settlement_tick',
                        $settlement->region_id,
                        [$settlement->region_id],
                        [],
                        $currentYear
                    );
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
        $summary = ['processed' => 0, 'changed' => 0, 'events' => 0, 'errors' => []];

        foreach (ResourceNode::all() as $node) {
            $summary['processed']++;
            try {
                $oldOutput = (int)$node->output;
                $status = $node->status;

                if ($status === 'active' || $status === 'blessed') {
                    $node->output = $this->clamp($oldOutput + random_int(-2, 3));
                } elseif ($status === 'contested' || $status === 'overworked') {
                    $node->output = $this->clamp($oldOutput - random_int(1, 4));
                } elseif ($status === 'corrupted') {
                    $node->output = $this->clamp($oldOutput - random_int(2, 5));
                }

                if ($node->output <= 5) {
                    $node->status = 'depleted';
                }

                if ($node->output !== $oldOutput || $node->status !== $status) {
                    $node->save();
                    $summary['changed']++;
                }

                if ($node->status !== $status) {
                    $this->recordEvent(
                        'Resource State Changed',
                        "{$node->name} is now {$node->status}.",
                        'resource_tick',
                        $node->region_id,
                        [$node->region_id],
                        [],
                        $currentYear
                    );
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
        $summary = ['processed' => 0, 'changed' => 0, 'events' => 0, 'errors' => []];

        foreach (Hero::where('is_alive', true)->get() as $hero) {
            $summary['processed']++;
            try {
                $oldLevel = (int)$hero->level;
                $oldRegionId = $hero->region_id;

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

                if ($this->shouldHeroDie($hero)) {
                    $hero->is_alive = false;
                    $hero->status = 'deceased';
                    $hero->death_reason = $this->getDeathReason();
                }

                $hero->save();
                $summary['changed']++;

                if ((int)$hero->level !== $oldLevel) {
                    $this->recordEvent(
                        'Hero Advanced',
                        "{$hero->name} reached level {$hero->level}.",
                        'hero_level',
                        $hero->region_id,
                        [$hero->region_id],
                        [$hero->id],
                        $currentYear
                    );
                    $summary['events']++;
                }

                if ($hero->region_id !== $oldRegionId) {
                    $this->recordEvent(
                        'Hero Travel',
                        "{$hero->name} traveled from {$oldRegionId} to {$hero->region_id}.",
                        'hero_travel',
                        $hero->region_id,
                        [$oldRegionId, $hero->region_id],
                        [$hero->id],
                        $currentYear
                    );
                    $summary['events']++;
                }

                if (!$hero->is_alive) {
                    $this->recordEvent(
                        'Hero Fallen',
                        "{$hero->name} died: {$hero->death_reason}.",
                        'hero_death',
                        $hero->region_id,
                        [$hero->region_id],
                        [$hero->id],
                        $currentYear
                    );
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

    public function processActiveBets(int $currentYear): array
    {
        $summary = ['processed' => 0, 'won' => 0, 'lost' => 0, 'expired' => 0, 'errors' => []];

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

                $this->recordEvent(
                    'Divine Bet Resolved',
                    $resolution['notes'],
                    'bet_resolution',
                    $resolution['regionId'],
                    $resolution['regionId'] ? [$resolution['regionId']] : [],
                    $resolution['heroId'] ? [$resolution['heroId']] : [],
                    $currentYear
                );
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
                $won = $target['type'] === 'region' && in_array($target['model']->cultural_influence, ['mystical', 'martial', 'mercantile'], true);
                $notes = $won
                    ? "{$target['model']->name} now shows a strong {$target['model']->cultural_influence} cultural identity."
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
                $won = $target['type'] === 'hero' && (int)$target['model']->level >= 5;
                $notes = $won
                    ? "{$target['model']->name} reached level {$target['model']->level}."
                    : "{$target['name']} has not reached a level milestone.";
                break;

            case 'hero_death':
                $won = $target['type'] === 'hero' && !$target['model']->is_alive;
                $notes = $won
                    ? "{$target['model']->name} died as predicted."
                    : "{$target['name']} still lives.";
                break;

            case 'region_danger_change':
                $won = $target['type'] === 'region' && ((int)$target['model']->danger_level >= 70 || (int)$target['model']->danger_level <= 10);
                $notes = $won
                    ? "{$target['model']->name} reached an extreme danger level of {$target['model']->danger_level}."
                    : "{$target['name']} has not reached an extreme danger state.";
                break;

            case 'prosperity_threshold':
                $won = $target['type'] === 'settlement' && (int)$target['model']->prosperity >= 80;
                $notes = $won
                    ? "{$target['model']->name} reached prosperity {$target['model']->prosperity}."
                    : "{$target['name']} has not reached the prosperity threshold.";
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

    private function refreshBetOdds(DivineBet $bet): void
    {
        $target = $this->findBetTarget($bet->target_id);
        if (!$target) {
            return;
        }

        $odds = DivineBet::getBaseOddsForType($bet->bet_type) * DivineBet::getConfidenceModifier($bet->confidence);

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

    private function settlementStatusFor(int $prosperity, int $population): string
    {
        if ($population <= 0) {
            return 'abandoned';
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

    private function recordEvent(
        string $title,
        string $description,
        string $type,
        ?string $regionId,
        array $relatedRegionIds,
        array $relatedHeroIds,
        int $year
    ): void {
        GameEvent::create([
            'id' => 'event-' . bin2hex(random_bytes(8)),
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'status' => 'completed',
            'region_id' => $regionId,
            'timestamp' => date('c'),
            'related_region_ids' => array_values(array_filter($relatedRegionIds)),
            'related_hero_ids' => array_values(array_filter($relatedHeroIds)),
            'year' => $year
        ]);
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
