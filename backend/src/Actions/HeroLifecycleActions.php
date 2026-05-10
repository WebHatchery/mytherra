<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Hero;
use App\Repositories\HeroRepository;
use App\Repositories\SettlementRepository;
use App\Services\HeroLifecycleService;
use App\Core\Exceptions\ResourceNotFoundException;
use App\Helpers\Logger;

/**
 * Handles advanced hero operations (lifecycle, creation, etc.)
 */
class HeroLifecycleActions
{
    public function __construct(
        private HeroRepository $heroRepository,
        private SettlementRepository $settlementRepository,
        private HeroLifecycleService $heroLifecycleService
    ) {
    }

    /**
     * Process a hero's tick (lifecycle events)
     *
     * @param string $heroId The ID of the hero
     * @param int $currentYear The current game year
     * @return array{success: bool, data: array}
     * @throws ResourceNotFoundException if hero not found
     * @throws \RuntimeException if processing fails
     */
    public function processHeroTick(string $heroId, int $currentYear): array
    {
        try {
            $result = $this->heroLifecycleService->processTick($heroId, $currentYear);
            return ['success' => true, 'data' => $result];
        } catch (ResourceNotFoundException $error) {
            Logger::warning("Hero not found for tick processing", ['heroId' => $heroId]);
            throw $error;
        } catch (\Exception $error) {
            Logger::error('Error processing hero tick', [
                'heroId' => $heroId,
                'year' => $currentYear,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to process hero tick', 0, $error);
        }
    }

    /**
     * Create a new hero
     *
     * @param string $name Hero name
     * @param string $role Hero role
     * @param string $regionId Target region ID
     * @return array{success: bool, data: array}
     * @throws ResourceNotFoundException if region not found
     * @throws \InvalidArgumentException if role is invalid
     * @throws \RuntimeException if creation fails
     */
    public function createNewHero(string $name, string $role, string $regionId): array
    {
        try {
            $this->validateRegionExists($regionId);
            $this->validateHeroRole($role);

            $heroData = [
                'id' => $this->generateHeroId(),
                'name' => $name,
                'role' => $role,
                'region_id' => $regionId,
                'level' => 1,
                'age' => rand(20, 30),
                'is_alive' => true,
                'status' => 'living',
                'personality_traits' => [],
                'feats' => [],
                'description' => "A new {$role} seeking their destiny"
            ];

            $hero = $this->heroRepository->createHero($heroData);

            return [
                'success' => true,
                'data' => $this->enrichHeroData($hero)
            ];
        } catch (ResourceNotFoundException | \InvalidArgumentException $error) {
            throw $error;
        } catch (\Exception $error) {
            Logger::error('Error creating hero', [
                'name' => $name,
                'role' => $role,
                'regionId' => $regionId,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to create hero', 0, $error);
        }
    }

    /**
     * Generate a unique hero ID
     */
    private function generateHeroId(): string
    {
        return 'hero-' . bin2hex(random_bytes(8));
    }

    /**
     * Validate that a region exists
     *
     * @throws ResourceNotFoundException if region not found
     */
    private function validateRegionExists(string $regionId): void
    {
        $region = $this->settlementRepository->getRegionById($regionId);
        if (!$region) {
            throw new ResourceNotFoundException("Region not found: {$regionId}");
        }
    }

    /**
     * Validate a hero role
     *
     * @throws \InvalidArgumentException if role is invalid
     */
    private function validateHeroRole(string $role): void
    {
        if (!Hero::validateRole($role)) {
            throw new \InvalidArgumentException("Invalid hero role: {$role}");
        }
    }

    /**
     * Enrich hero data with computed properties
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
