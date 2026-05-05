<?php

namespace App\Repositories;

use Exception;
use App\Utils\Logger;
use App\Models\Settlement;

class SettlementRepository
{
    /**
     * Fetch settlement by ID
     */
    public function getById($id)
    {
        try {
            $settlement = Settlement::find($id);
            return $settlement ? $settlement->toArray() : false;
        } catch (Exception $e) {
            Logger::error("Error fetching settlement by ID: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch settlement by ID
     */
    public function getSettlementById($id)
    {
        return $this->getById($id);
    }

    /**
     * Fetch multiple settlements by array of IDs
     */
    public function getSettlementsByIds(array $ids)
    {
        try {
            return Settlement::whereIn('id', $ids)->get()->map->toArray()->all();
        } catch (Exception $e) {
            Logger::error("Error fetching settlements by IDs: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all settlements with optional filtering
     */
    public function getAllSettlements($filters = [], $limit = 20, $offset = 0)
    {
        try {
            $query = Settlement::query();

    // Apply filters using Eloquent
            if (!empty($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['region_id'])) {
                $query->where('region_id', $filters['region_id']);
            }

            if (!empty($filters['minPopulation'])) {
                $query->where('population', '>=', $filters['minPopulation']);
            }

            if (!empty($filters['maxPopulation'])) {
                $query->where('population', '<=', $filters['maxPopulation']);
            }

            if (!empty($filters['minProsperity'])) {
                $query->where('prosperity', '>=', $filters['minProsperity']);
            }

            if (!empty($filters['maxProsperity'])) {
                $query->where('prosperity', '<=', $filters['maxProsperity']);
            }

            if (!empty($filters['hasPort'])) {
                $query->where('has_port', $filters['hasPort']);
            }

            if (!empty($filters['tradeRoutes'])) {
                $query->where('trade_routes', '>=', $filters['tradeRoutes']);
            }

    // Apply ordering and pagination
            $settlements = $query
                ->orderBy('population', 'desc')
                ->orderBy('prosperity', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            return $settlements->map->toArray()->all();
        } catch (Exception $e) {
            Logger::error("Error fetching settlements: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Save or update a settlement
     */
    public function saveSettlement($settlementData)
    {
        try {
            $requiredFields = ['name', 'type', 'region_id', 'population', 'status'];
            foreach ($requiredFields as $field) {
                if (empty($settlementData[$field])) {
                    throw new Exception("Required field '$field' is missing");
                }
            }

            if (isset($settlementData['id'])) {
                // Update existing settlement
                $settlement = Settlement::find($settlementData['id']);
                if (!$settlement) {
                    throw new Exception("Settlement not found for update");
                }
                $settlement->update($settlementData);
                return $settlement->toArray();
            } else {
                // Create new settlement
                $settlement = Settlement::create($settlementData);
                return $settlement->toArray();
            }
        } catch (Exception $e) {
            Logger::error("Error saving settlement: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get settlements suitable for betting opportunities
     */
    public function getSettlementsForBetting($filters = [])
    {
        try {
            $query = Settlement::query();

    // Add filters for settlements that are more likely to have interesting events
            $query->where(function ($q) {
                $q->where('prosperity', '>=', 50)
                  ->orWhereIn('status', ['growing', 'declining', 'transforming'])
                  ->orWhere('cultural_influence', '>=', 60);
            });

            if (!empty($filters['minProsperity'])) {
                $query->where('prosperity', '>=', $filters['minProsperity']);
            }

            if (!empty($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            if (!empty($filters['regionId'])) {
                $query->where('region_id', $filters['regionId']);
            }

            $limit = $filters['limit'] ?? 5;

    // Order by prosperity and population to find most notable settlements
            $settlements = $query
                ->orderBy('prosperity', 'desc')
                ->orderBy('population', 'desc')
                ->take($limit)
                ->get();

            return $settlements->map->toArray()->all();
        } catch (Exception $e) {
            Logger::error("Error fetching settlements for betting: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update settlement's population
     */
    public function updatePopulation($settlementId, $newPopulation)
    {
        try {
            if ($newPopulation < 0) {
                throw new Exception("Population cannot be negative");
            }

            $settlement = Settlement::find($settlementId);
            if (!$settlement) {
                throw new Exception("Settlement not found for population update");
            }

            $settlement->update(['population' => $newPopulation]);
            return true;
        } catch (Exception $e) {
            Logger::error("Error updating settlement population: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update settlement's prosperity
     */
    public function updateProsperity($settlementId, $newProsperity)
    {
        try {
            if ($newProsperity < 0 || $newProsperity > 100) {
                throw new Exception("Prosperity must be between 0 and 100");
            }

            $settlement = Settlement::find($settlementId);
            if (!$settlement) {
                throw new Exception("Settlement not found for prosperity update");
            }

            $settlement->update(['prosperity' => $newProsperity]);
            return true;
        } catch (Exception $e) {
            Logger::error("Error updating settlement prosperity: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update settlement's status
     */
    public function updateStatus($settlementId, $newStatus)
    {
        try {
            $settlement = Settlement::find($settlementId);
            if (!$settlement) {
                throw new Exception("Settlement not found for status update");
            }

            $settlement->update(['status' => $newStatus]);
            return true;
        } catch (Exception $e) {
            Logger::error("Error updating settlement status: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get settlements by region
     */
    public function getSettlementsByRegion($regionId, $limit = 10)
    {
        try {
            $settlements = Settlement::where('region_id', $regionId)
                ->orderBy('population', 'desc')
                ->take($limit)
                ->get();

            return $settlements->map->toArray()->all();
        } catch (Exception $e) {
            Logger::error("Error fetching settlements by region: " . $e->getMessage());
            throw $e;
        }
    }
}
