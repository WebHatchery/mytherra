<?php

namespace App\Actions;

use App\Models\Landmark;
use App\Repositories\LandmarkRepository;
use App\Core\Exceptions\ResourceNotFoundException;
use App\Helpers\Logger;

/**
 * Handles landmark operations
 */
class LandmarkActions
{
    public function __construct(
        private LandmarkRepository $landmarkRepository
    ) {
    }

    /**
     * Fetch all landmarks with optional filtering
     *
     * @param array $filters Optional filters for the query
     * @return array Array of landmark data
     * @throws \RuntimeException if database operation fails
     */
    public function fetchAllLandmarks(array $filters = []): array
    {
        try {
            $query = Landmark::query();

    // Apply filters
            if (!empty($filters['regionId'])) {
                $query->where('region_id', $filters['regionId']);
            }

            if (!empty($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['minDangerLevel'])) {
                $query->where('dangerLevel', '>=', $filters['minDangerLevel']);
            }

            if (!empty($filters['maxDangerLevel'])) {
                $query->where('dangerLevel', '<=', $filters['maxDangerLevel']);
            }

            if (!empty($filters['minMagicLevel'])) {
                $query->where('magicLevel', '>=', $filters['minMagicLevel']);
            }

            if (!empty($filters['maxMagicLevel'])) {
                $query->where('magicLevel', '<=', $filters['maxMagicLevel']);
            }

            if (!empty($filters['discoveredYear'])) {
                $query->where('discoveredYear', '<=', $filters['discoveredYear']);
            }

    // Apply pagination
            $limit = $filters['limit'] ?? 20;
            $offset = $filters['offset'] ?? 0;

            $landmarks = $query->skip($offset)->take($limit)->get();

            return $landmarks->map(fn($landmark) => $landmark->toArray())->all();
        } catch (\Exception $error) {
            Logger::error('Error fetching landmarks', [
                'filters' => $filters,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch landmarks from database', 0, $error);
        }
    }

    /**
     * Fetch a landmark by ID
     *
     * @param string $landmarkId The ID of the landmark to fetch
     * @return array Landmark data
     * @throws ResourceNotFoundException if landmark not found
     * @throws \RuntimeException if database operation fails
     */
    public function fetchLandmarkById(string $landmarkId): array
    {
        try {
            $landmark = $this->landmarkRepository->getById($landmarkId);

            if (!$landmark) {
                Logger::info("Landmark not found", ['landmarkId' => $landmarkId]);
                throw new ResourceNotFoundException("Landmark not found: {$landmarkId}");
            }

    // Repository returns array data, not model instance
            return $landmark;
        } catch (ResourceNotFoundException $error) {
            throw $error;
        } catch (\Exception $error) {
            Logger::error('Error fetching landmark', [
            'landmarkId' => $landmarkId,
            'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch landmark from database', 0, $error);
        }
    }

    /**
     * Create a new landmark
     *
     * @param array $landmarkData The landmark data
     * @return array The created landmark data
     * @throws \RuntimeException if validation fails or database operation fails
     */
    public function createLandmark(array $landmarkData): array
    {
        try {
            $landmark = new Landmark($landmarkData);

    // Validate the landmark
            $errors = $landmark->validate();
            if (!empty($errors)) {
                throw new \RuntimeException('Validation failed: ' . implode(', ', $errors));
            }

            $landmark->save();

            Logger::info("Successfully created landmark", ['id' => $landmark->id]);
            return $landmark->toArray();
        } catch (\Exception $error) {
            Logger::error('Error creating landmark', [
            'data' => $landmarkData,
            'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to create landmark', 0, $error);
        }
    }

    /**
     * Update a landmark
     *
     * @param string $landmarkId The ID of the landmark to update
     * @param array $updateData The update data
     * @return array The updated landmark data
     * @throws ResourceNotFoundException if landmark not found
     * @throws \RuntimeException if validation fails or database operation fails
     */
    public function updateLandmark(string $landmarkId, array $updateData): array
    {
        try {
            $landmark = $this->landmarkRepository->getById($landmarkId);

            if (!$landmark) {
                Logger::info("Landmark not found", ['landmarkId' => $landmarkId]);
                throw new ResourceNotFoundException("Landmark not found: {$landmarkId}");
            }

            if (!($landmark instanceof Landmark)) {
                throw new \RuntimeException("Invalid landmark data returned from repository");
            }

            $landmark->fill($updateData);

    // Validate the landmark
            $errors = $landmark->validate();
            if (!empty($errors)) {
                throw new \RuntimeException('Validation failed: ' . implode(', ', $errors));
            }

            $landmark->save();

            Logger::info("Successfully updated landmark", ['id' => $landmarkId]);
            return $landmark->toArray();
        } catch (ResourceNotFoundException $error) {
            throw $error;
        } catch (\Exception $error) {
            Logger::error('Error updating landmark', [
            'landmarkId' => $landmarkId,
            'data' => $updateData,
            'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to update landmark', 0, $error);
        }
    }

    /**
     * Delete a landmark
     *
     * @param string $landmarkId The ID of the landmark to delete
     * @return bool True if deleted successfully, false otherwise
     * @throws ResourceNotFoundException if landmark not found
     * @throws \RuntimeException if database operation fails
     */
    public function deleteLandmark(string $landmarkId): bool
    {
        try {
            $landmark = $this->landmarkRepository->getById($landmarkId);
            if (!$landmark) {
                Logger::info("Landmark not found", ['landmarkId' => $landmarkId]);
                throw new ResourceNotFoundException("Landmark not found: {$landmarkId}");
            }

            $landmark->delete();

            Logger::info("Successfully deleted landmark", ['id' => $landmarkId]);
            return true;
        } catch (ResourceNotFoundException $error) {
            throw $error;
        } catch (\Exception $error) {
            Logger::error('Error deleting landmark', [
            'landmarkId' => $landmarkId,
            'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to delete landmark', 0, $error);
        }
    }

    /**
     * Get landmarks by region
     *
     * @param string $regionId The ID of the region
     * @return array Array of landmark data for the region
     * @throws \RuntimeException if database operation fails
     */
    public function getLandmarksByRegion(string $regionId): array
    {
        try {
            $landmarks = Landmark::where('region_id', $regionId)->get();

            return $landmarks->map(fn($landmark) => $landmark->toArray())->all();
        } catch (\Exception $error) {
            Logger::error('Error fetching landmarks by region', [
            'regionId' => $regionId,
            'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch landmarks for region', 0, $error);
        }
    }

    /**
     * Discover a landmark
     *
     * @param string $landmarkId The ID of the landmark to discover
     * @param int $discoveredYear The year the landmark was discovered
     * @return array The discovered landmark data
     * @throws ResourceNotFoundException if landmark not found
     * @throws \RuntimeException if database operation fails
     */
    public function discoverLandmark(string $landmarkId, int $discoveredYear): array
    {
        try {
            $landmark = $this->landmarkRepository->getById($landmarkId);
            if (!$landmark) {
                Logger::info("Landmark not found", ['landmarkId' => $landmarkId]);
                throw new ResourceNotFoundException("Landmark not found: {$landmarkId}");
            }

            $landmark->discoveredYear = $discoveredYear;
            $landmark->status = 'discovered';
            $landmark->save();

            Logger::info("Successfully discovered landmark", ['id' => $landmarkId]);
            return $landmark->toArray();
        } catch (ResourceNotFoundException $error) {
            throw $error;
        } catch (\Exception $error) {
            Logger::error('Error discovering landmark', [
            'landmarkId' => $landmarkId,
            'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to discover landmark', 0, $error);
        }
    }
}
