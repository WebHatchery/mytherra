<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Building;
use App\Repositories\BuildingRepository;
use App\Core\Exceptions\ResourceNotFoundException;
use App\Helpers\Logger;

/**
 * Handles basic building operations
 */
class BuildingActions
{
    public function __construct(
        private BuildingRepository $buildingRepository
    ) {
    }

    /**
     * Fetch all buildings with optional filters
     *
     * @param array $filters Optional filters for the query
     * @return array Array of building data
     * @throws \RuntimeException if database operation fails
     */
    public function fetchAllBuildings(array $filters = []): array
    {
        try {
            $query = Building::query();

    // Apply filters
            if (!empty($filters['settlementId'])) {
                $query->where('settlement_id', $filters['settlementId']);
            }

            if (!empty($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['minCondition'])) {
                $query->where('condition', '>=', $filters['minCondition']);
            }

            if (!empty($filters['maxCondition'])) {
                $query->where('condition', '<=', $filters['maxCondition']);
            }

    // Apply pagination
            $limit = $filters['limit'] ?? 20;
            $offset = $filters['offset'] ?? 0;

            $buildings = $query->skip($offset)->take($limit)->get();

            return $buildings->map(fn($building) => $building->toArray())->all();
        } catch (\Exception $error) {
            Logger::error('Error fetching buildings', [
                'filters' => $filters,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch buildings from database', 0, $error);
        }
    }

    /**
     * Fetch a building by ID
     *
     * @param string $buildingId The ID of the building to fetch
     * @return array Building data
     * @throws ResourceNotFoundException if building not found
     * @throws \RuntimeException if database operation fails
     */
    public function fetchBuildingById(string $buildingId): array
    {
        try {
            $building = $this->buildingRepository->getById($buildingId);

            if (!$building) {
                Logger::info("Building not found", ['buildingId' => $buildingId]);
                throw new ResourceNotFoundException("Building not found: {$buildingId}");
            }

    // Repository returns array data, not model instance
            return $building;
        } catch (ResourceNotFoundException $error) {
            throw $error;
        } catch (\Exception $error) {
            Logger::error('Error fetching building', [
                'buildingId' => $buildingId,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch building from database', 0, $error);
        }
    }

    /**
     * Create a new building
     *
     * @param array $buildingData The building data
     * @return array The created building data
     * @throws \RuntimeException if validation fails or database operation fails
     */
    public function createBuilding(array $buildingData): array
    {
        try {
            $building = new Building($buildingData);

    // Validate the building
            $errors = $building->validate();
            if (!empty($errors)) {
                throw new \RuntimeException('Validation failed: ' . implode(', ', $errors));
            }

            $building->save();

            Logger::info("Successfully created building", ['id' => $building->id]);
            return $building->toArray();
        } catch (\Exception $error) {
            Logger::error('Error creating building', [
                'data' => $buildingData,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to create building', 0, $error);
        }
    }

    /**
     * Update a building
     *
     * @param string $buildingId The ID of the building to update
     * @param array $updateData The update data
     * @return array The updated building data
     * @throws ResourceNotFoundException if building not found
     * @throws \RuntimeException if validation fails or database operation fails
     */
    public function updateBuilding(string $buildingId, array $updateData): array
    {
        try {
            $building = $this->buildingRepository->getById($buildingId);

            if (!$building) {
                Logger::info("Building not found", ['buildingId' => $buildingId]);
                throw new ResourceNotFoundException("Building not found: {$buildingId}");
            }

            if (!($building instanceof Building)) {
                throw new \RuntimeException("Invalid building data returned from repository");
            }

            $building->fill($updateData);

    // Validate the building
            $errors = $building->validate();
            if (!empty($errors)) {
                throw new \RuntimeException('Validation failed: ' . implode(', ', $errors));
            }

            $building->save();

            Logger::info("Successfully updated building", ['id' => $buildingId]);
            return $building->toArray();
        } catch (ResourceNotFoundException $error) {
            throw $error;
        } catch (\Exception $error) {
            Logger::error('Error updating building', [
                'buildingId' => $buildingId,
                'data' => $updateData,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to update building', 0, $error);
        }
    }

    /**
     * Delete a building
     *
     * @param string $buildingId The ID of the building to delete
     * @return bool True if deleted successfully
     * @throws ResourceNotFoundException if building not found
     * @throws \RuntimeException if database operation fails
     */
    public function deleteBuilding(string $buildingId): bool
    {
        try {
            $building = $this->buildingRepository->getById($buildingId);

            if (!$building) {
                Logger::info("Building not found", ['buildingId' => $buildingId]);
                throw new ResourceNotFoundException("Building not found: {$buildingId}");
            }

            if (!($building instanceof Building)) {
                throw new \RuntimeException("Invalid building data returned from repository");
            }

            $building->delete();

            Logger::info("Successfully deleted building", ['id' => $buildingId]);
            return true;
        } catch (ResourceNotFoundException $error) {
            throw $error;
        } catch (\Exception $error) {
            Logger::error('Error deleting building', [
                'buildingId' => $buildingId,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to delete building', 0, $error);
        }
    }
}
