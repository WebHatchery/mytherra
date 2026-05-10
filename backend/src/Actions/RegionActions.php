<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Region;
use App\Repositories\RegionRepository;

class RegionActions
{
    private RegionRepository $regionRepository;

    public function __construct(RegionRepository $regionRepository)
    {
        $this->regionRepository = $regionRepository;
    }

    public function fetchAllRegions($filters = [])
    {
        try {
            return $this->regionRepository->getAllRegions($filters);
        } catch (\Exception $error) {
            error_log("Error in fetchAllRegions: " . $error->getMessage());
            throw new \Exception('Failed to fetch regions from database.');
        }
    }

    public function fetchRegionById(string $regionId)
    {
        try {
            $region = $this->regionRepository->getById($regionId);

            if (!$region) {
                throw new \App\Core\Exceptions\ResourceNotFoundException("Region with ID {$regionId} not found");
            }

            return $region;
        } catch (\App\Core\Exceptions\ResourceNotFoundException $error) {
            throw $error;

    // Re-throw ResourceNotFoundException as-is
        } catch (\Exception $error) {
            error_log("Error in fetchRegionById for ID {$regionId}: " . $error->getMessage());
            throw new \Exception('Failed to fetch region from database.');
        }
    }

    public function processRegionSystemicChanges(string $regionId, int $currentYear)
    {
        $region = Region::find($regionId);
        if (!$region) {
            throw new \Exception("Region with ID {$regionId} not found for systemic update.");
        }

    // Apply systemic changes logic here
        // This would include prosperity/chaos changes, status updates, etc.

        $region->save();
        return $region;
    }

    public function createNewRegion(string $creatorName, int $currentYear)
    {
        $regionId = 'region-' . bin2hex(random_bytes(8));

        $region = new Region([
            'id' => $regionId,
            'name' => $creatorName . "'s Domain",
            'color' => '#' . substr(md5($regionId), 0, 6),
            'prosperity' => 50,
            'chaos' => 50,
            'magic_affinity' => 50,
            'status' => 'developing',
            'event_ids' => []
        ]);
        $region->save();
        return $region;
    }

    /**
     * Get landmarks for a specific region
     */
    public function getLandmarksByRegion(string $regionId): array
    {
        // Import LandmarkActions to reuse the existing method
        $landmarkRepo = new \App\Repositories\LandmarkRepository(\App\Repositories\DatabaseService::getInstance());
        $landmarkActions = new \App\Actions\LandmarkActions($landmarkRepo);

        return $landmarkActions->getLandmarksByRegion($regionId);
    }
}
