<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Hero;
use App\Repositories\HeroRepository;
use App\Core\Exceptions\ResourceNotFoundException;
use App\Helpers\Logger;

/**
 * Handles basic hero operations (fetching)
 */
class HeroActions
{
    public function __construct(
        private HeroRepository $heroRepository
    ) {
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
                'alignmentDescription' => $hero->alignment ? $hero->getAlignmentDescription() : null
            ]
        );
    }
}
