<?php

declare(strict_types=1);

namespace App\Repositories;

use Exception;
use App\Utils\Logger;
use App\Models\Region;

class RegionRepository
{
    /**
     * Fetch region by ID with proper JSON decoding
     */
    public function getById($id)
    {
        try {
            $region = Region::find($id);
            return $region ? $this->enrichRegionData($region) : false;
        } catch (Exception $e) {
            Logger::error("Error fetching region by ID: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch region by ID
     */
    public function getRegionById($id)
    {
        return $this->getById($id);
    }

    /**
     * Fetch multiple regions by array of IDs
     */
    public function getRegionsByIds(array $ids)
    {
        try {
            return Region::whereIn('id', $ids)
                ->get()
                ->map(fn(Region $region) => $this->enrichRegionData($region))
                ->all();
        } catch (Exception $e) {
            Logger::error("Error fetching regions by IDs: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all regions optionally filtered by properties
     */
    public function getAllRegions($filters = [], $limit = 20, $offset = 0)
    {
        try {
            $query = Region::query();

    // Apply filters using Eloquent
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['corruptionLevel'])) {
                $query->where('corruption_level', $filters['corruptionLevel']);
            }

            if (!empty($filters['culturalInfluence'])) {
                $query->where('cultural_influence', $filters['culturalInfluence']);
            }

    // Apply ordering and pagination
            $regions = $query
                ->orderBy('name', 'ASC')
                ->skip($offset)
                ->take($limit)
                ->get();

            return $regions
                ->map(fn(Region $region) => $this->enrichRegionData($region))
                ->all();
        } catch (Exception $e) {
            Logger::error("Error fetching regions: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Save or update a region
     */
    public function saveRegion($regionData)
    {
        try {
            $requiredFields = ['name', 'status'];
            foreach ($requiredFields as $field) {
                if (empty($regionData[$field])) {
                    throw new Exception("Required field '$field' is missing");
                }
            }

            if (isset($regionData['id'])) {
                // Update existing region
                $region = Region::find($regionData['id']);
                if (!$region) {
                    throw new Exception("Region not found for update");
                }
                $region->update($regionData);
                $region->refresh();
                return $this->enrichRegionData($region);
            } else {
                // Create new region
                $region = Region::create($regionData);
                return $this->enrichRegionData($region);
            }
        } catch (Exception $e) {
            Logger::error("Error saving region: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a new region
     */
    public function createRegion($data)
    {
        try {
            // Convert array fields to JSON for storage if needed
            if (isset($data['regional_traits']) && is_array($data['regional_traits'])) {
                $data['regional_traits'] = json_encode($data['regional_traits']);
            }

            $region = Region::create($data);
            return $this->enrichRegionData($region);
        } catch (Exception $e) {
            Logger::error("Error creating region: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update region's corruption level
     */
    public function updateCorruptionLevel($regionId, $newLevel)
    {
        try {
            $region = Region::find($regionId);
            if (!$region) {
                throw new Exception("Region not found for corruption level update");
            }

            $region->update(['corruption_level' => $newLevel]);
            return true;
        } catch (Exception $e) {
            Logger::error("Error updating corruption level: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update region's cultural influence
     */
    public function updateCulturalInfluence($regionId, $newInfluence)
    {
        try {
            $region = Region::find($regionId);
            if (!$region) {
                throw new Exception("Region not found for cultural influence update");
            }

            $region->update(['cultural_influence' => $newInfluence]);
            return true;
        } catch (Exception $e) {
            Logger::error("Error updating cultural influence: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get regions where betting opportunities are available
     */
    public function getRegionsForBetting($filters = [])
    {
        try {
            $query = Region::query();

    // Add filters for regions that are more likely to have interesting events
            $query->where(function ($q) {
                $q->where('corruption_level', '>', 30)
                  ->orWhere('cultural_influence', '>', 60)
                  ->orWhereIn('status', ['unstable', 'changing', 'contested']);
            });

            if (!empty($filters['minCorruption'])) {
                $query->where('corruption_level', '>=', $filters['minCorruption']);
            }

            if (!empty($filters['maxCorruption'])) {
                $query->where('corruption_level', '<=', $filters['maxCorruption']);
            }

            $limit = $filters['limit'] ?? 5;

            $regions = $query
                ->orderByRaw('(corruption_level + cultural_influence) DESC')
                ->take($limit)
                ->get();

            return $regions
                ->map(fn(Region $region) => $this->enrichRegionData($region))
                ->all();
        } catch (Exception $e) {
            Logger::error("Error fetching regions for betting: " . $e->getMessage());
            throw $e;
        }
    }

    private function enrichRegionData(Region $region): array
    {
        return array_merge(
            $region->toArray(),
            [
                'influenceActionCosts' => $region->getInfluenceCosts(),
                'influenceEffectiveness' => $region->getInfluenceEffectiveness()
            ]
        );
    }
}
