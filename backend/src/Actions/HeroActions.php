<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\GameEvent;
use App\Models\Hero;
use App\Models\Settlement;
use App\Repositories\HeroRepository;
use App\Services\ChampionService;
use App\Core\Exceptions\ResourceNotFoundException;
use App\Helpers\Logger;

/**
 * Handles basic hero operations (fetching)
 */
class HeroActions
{
    public function __construct(
        private HeroRepository $heroRepository,
        private ?ChampionService $championService = null
    ) {
        $this->championService ??= new ChampionService($this->heroRepository);
    }

    /**
     * Fetch all heroes with optional filters
     *
     * @param array $filters Optional filters for the query
     * @return array Array of enriched hero data
     * @throws \RuntimeException if database operation fails
     */
    public function fetchAllHeroes(array $filters = []): array
    {
        try {
            $heroes = $this->heroRepository->getAllHeroes($filters);

            return array_map(
                fn($hero) => $this->enrichHeroData($hero),
                $heroes
            );
        } catch (\Exception $error) {
            Logger::error('Error fetching heroes', [
                'filters' => $filters,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch heroes from database', 0, $error);
        }
    }

    /**
     * Fetch a hero by ID
     *
     * @param string $heroId The ID of the hero to fetch
     * @return array Enriched hero data
     * @throws ResourceNotFoundException if hero not found
     * @throws \RuntimeException if database operation fails
     */
    public function fetchHeroById(string $heroId): array
    {
        try {
            $hero = $this->heroRepository->getById($heroId);

            if (!$hero) {
                Logger::info("Hero not found", ['heroId' => $heroId]);
                throw new ResourceNotFoundException("Hero not found: {$heroId}");
            }

            if (!($hero instanceof Hero)) {
                throw new \RuntimeException("Invalid hero data returned from repository");
            }

            return $this->enrichHeroData($hero);
        } catch (ResourceNotFoundException $error) {
            throw $error;
        } catch (\Exception $error) {
            Logger::error('Error fetching hero', [
                'heroId' => $heroId,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch hero from database', 0, $error);
        }
    }

    public function fetchChampionStatus(): array
    {
        return $this->championService->status();
    }

    public function designateChampion(string $heroId): array
    {
        return $this->championService->designate($heroId);
    }

    public function cultivateChampion(string $heroId, string $focus): array
    {
        return $this->championService->cultivate($heroId, $focus);
    }

    /**
     * Enrich hero data with computed properties
     *
     * @param Hero $hero The hero to enrich
     * @return array Hero data with computed properties
     */
    private function enrichHeroData(Hero $hero): array
    {
        return array_merge(
            $hero->toArray(),
            [
                'roleDetails' => $hero->getRoleConfig(),
                'alignmentDescription' => $hero->alignment ? $hero->getAlignmentDescription() : null,
                'influenceActionCosts' => $hero->getInfluenceCosts(),
                'lifecycleSummary' => $this->buildLifecycleSummary($hero),
                'relationshipContext' => $this->buildRelationshipContext($hero),
                'recentHistory' => $this->buildRecentHeroHistory($hero),
                'championStatus' => $this->championService->championStatusForHero($hero),
            ]
        );
    }

    private function buildLifecycleSummary(Hero $hero): array
    {
        $level = (int)($hero->level ?? 1);
        $age = (int)($hero->age ?? 20);
        $isAlive = $hero->isAlive();
        $status = $hero->status ?: ($isAlive ? 'living' : 'deceased');
        $levelCategory = $hero->getLevelCategory();
        $ageCategory = $hero->getAgeCategory();
        $nextMilestoneLevel = $this->nextMilestoneLevel($level);
        $levelsToMilestone = $nextMilestoneLevel ? max(0, $nextMilestoneLevel - $level) : null;

        return [
            'levelCategory' => $levelCategory,
            'ageCategory' => $ageCategory,
            'statusLabel' => $this->formatLabel($status),
            'summary' => "{$levelCategory} {$hero->role}, level {$level}, {$ageCategory} at age {$age}.",
            'isMilestoneLevel' => $hero->isMilestoneLevel(),
            'nextMilestoneLevel' => $nextMilestoneLevel,
            'levelsToMilestone' => $levelsToMilestone,
            'featCount' => count($hero->feats ?? []),
            'mortalityPressure' => $this->mortalityPressure($hero),
            'alignmentDescription' => $hero->alignment ? $hero->getAlignmentDescription() : 'True Neutral',
            'deathReason' => $hero->death_reason,
        ];
    }

    private function buildRelationshipContext(Hero $hero): array
    {
        $region = $hero->region;
        $settlements = $hero->region_id
            ? Settlement::where('region_id', $hero->region_id)
                ->orderByDesc('population')
                ->orderBy('name')
                ->limit(3)
                ->get()
            : collect();
        $settlementCount = $hero->region_id
            ? Settlement::where('region_id', $hero->region_id)->count()
            : 0;
        $livingHeroesInRegion = $hero->region_id
            ? Hero::where('region_id', $hero->region_id)->where('is_alive', true)->count()
            : 0;
        $peerHeroCount = max(0, $livingHeroesInRegion - ($hero->isAlive() ? 1 : 0));
        $regionName = $region?->name ?? $hero->region_id;

        return [
            'region' => $region ? [
                'id' => $region->id,
                'name' => $region->name,
                'status' => $region->status,
                'prosperity' => (int)$region->prosperity,
                'chaos' => (int)$region->chaos,
                'dangerLevel' => (int)($region->danger_level ?? 0),
            ] : null,
            'settlementCount' => $settlementCount,
            'nearbySettlements' => $settlements
                ->map(fn(Settlement $settlement): array => [
                    'id' => $settlement->id,
                    'name' => $settlement->name,
                    'type' => $settlement->type,
                    'status' => $settlement->status,
                    'population' => (int)$settlement->population,
                ])
                ->values()
                ->all(),
            'peerHeroCount' => $peerHeroCount,
            'recentSettlementInteractions' => $this->recentSettlementInteractions($hero),
            'relationshipSummary' => $this->buildRelationshipSummary(
                $regionName ?: 'an unknown region',
                $settlementCount,
                $peerHeroCount
            ),
        ];
    }

    private function recentSettlementInteractions(Hero $hero): array
    {
        return $hero->settlementInteractions()
            ->with(['settlement', 'landmark'])
            ->orderByDesc('started_year')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get()
            ->map(function ($interaction): array {
                $target = $interaction->settlement ?: $interaction->landmark;

                return [
                    'id' => $interaction->id,
                    'targetType' => $interaction->settlement_id ? 'settlement' : 'landmark',
                    'targetId' => $interaction->settlement_id ?: $interaction->landmark_id,
                    'targetName' => $target?->name,
                    'action' => $interaction->action,
                    'interactionType' => $interaction->interaction_type,
                    'startedYear' => $interaction->started_year !== null
                        ? (int)$interaction->started_year
                        : null,
                    'duration' => $interaction->duration !== null ? (int)$interaction->duration : null,
                    'success' => $interaction->success === null ? null : (bool)$interaction->success,
                    'outcomeDescription' => $interaction->outcome_description,
                ];
            })
            ->values()
            ->all();
    }

    private function buildRecentHeroHistory(Hero $hero): array
    {
        $query = GameEvent::whereJsonContains('related_hero_ids', $hero->id);

        return [
            'eventCount' => (clone $query)->count(),
            'recentEvents' => $query
                ->orderByRaw('COALESCE(year, 0) DESC')
                ->orderByDesc('timestamp')
                ->orderByDesc('created_at')
                ->limit(4)
                ->get()
                ->map(fn(GameEvent $event): array => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'type' => $event->type,
                    'status' => $event->status,
                    'regionId' => $event->region_id,
                    'year' => $event->year,
                    'timestamp' => $event->timestamp,
                ])
                ->values()
                ->all(),
        ];
    }

    private function nextMilestoneLevel(int $level): ?int
    {
        foreach ([5, 10, 25, 50, 75, 100] as $milestone) {
            if ($level < $milestone) {
                return $milestone;
            }
        }

        return null;
    }

    private function mortalityPressure(Hero $hero): string
    {
        if (!$hero->isAlive()) {
            return 'ended';
        }

        $age = (int)($hero->age ?? 20);
        if ($age >= 80) {
            return 'extreme';
        }
        if ($age >= 65) {
            return 'high';
        }
        if ($age >= 45) {
            return 'rising';
        }

        return 'low';
    }

    private function buildRelationshipSummary(string $regionName, int $settlementCount, int $peerHeroCount): string
    {
        $settlementLabel = $settlementCount === 1 ? '1 settlement' : "{$settlementCount} settlements";
        $peerLabel = $peerHeroCount === 1 ? '1 other living hero' : "{$peerHeroCount} other living heroes";

        return "Currently tied to {$regionName}, with {$settlementLabel} and {$peerLabel} nearby.";
    }

    private function formatLabel(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
