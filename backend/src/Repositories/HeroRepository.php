<?php

declare(strict_types=1);

namespace App\Repositories;

use Exception;
use App\Utils\Logger;
use App\Models\Hero;
use App\Models\HeroSettlementInteraction;

class HeroRepository
{
    /**
     * Override parent getById to return Hero model instance
     */
    public function getById($id): ?Hero
    {
        try {
            return Hero::find($id);
        } catch (Exception $e) {
            Logger::error("Error fetching hero by ID: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch hero by ID
     */
    public function getHeroById($id): ?Hero
    {
        try {
            return Hero::find($id);
        } catch (Exception $e) {
            Logger::error("Error fetching hero by ID: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch multiple heroes by array of IDs
     */
    public function getHeroesByIds(array $ids): array
    {
        try {
            return Hero::whereIn('id', $ids)->get()->all();
        } catch (Exception $e) {
            Logger::error("Error fetching heroes by IDs: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all heroes with optional filtering
     */
    public function getAllHeroes($filters = [], $limit = 20, $offset = 0): array
    {
        try {
            $query = Hero::query();

            if (!empty($filters['role'])) {
                $query->where('role', $filters['role']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['isAlive'])) {
                $query->where('is_alive', $filters['isAlive']);
            }

            if (!empty($filters['regionId'])) {
                $query->where('region_id', $filters['regionId']);
            }

            if (!empty($filters['minLevel'])) {
                $query->where('level', '>=', $filters['minLevel']);
            }

    // Add ordering and pagination
            $heroes = $query
                ->orderBy('level', 'desc')
                ->orderBy('name', 'asc')
                ->skip($offset)
                ->take($limit)
                ->get();

            return $heroes->all();
        } catch (Exception $e) {
            Logger::error("Error fetching all heroes: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Save or update a hero
     */
    public function saveHero($heroData): Hero
    {
        try {
            if ($heroData instanceof Hero) {
                // Already a Hero model, just save it
                $heroData->save();
                return $heroData;
            }

    // Convert array data to Hero model
            if (isset($heroData['id']) && !empty($heroData['id'])) {
                // Update existing hero
                $hero = Hero::find($heroData['id']);
                if (!$hero) {
                    throw new Exception("Hero not found for update");
                }
                $hero->update($heroData);
                return $hero;
            } else {
                // Create new hero
                return Hero::create($heroData);
            }
        } catch (Exception $e) {
            Logger::error("Error saving hero: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get heroes suitable for betting opportunities
     */
    public function getHeroesForBetting($filters = []): array
    {
        try {
            $query = Hero::where('is_alive', 1);

    // Add filters for heroes that are more likely to have interesting events
            $query->where(function ($q) {
                $q->where('level', '>=', 3)
                  ->orWhereIn('status', ['active', 'questing', 'exploring'])
                  ->orWhereIn('role', ['warrior', 'prophet', 'agent of change']);
            });

            if (!empty($filters['minLevel'])) {
                $query->where('level', '>=', $filters['minLevel']);
            }

            if (!empty($filters['role'])) {
                $query->where('role', $filters['role']);
            }

            if (!empty($filters['regionId'])) {
                $query->where('region_id', $filters['regionId']);
            }

            $limit = $filters['limit'] ?? 5;

    // Order by level and recent activity
            $heroes = $query
                ->orderBy('level', 'desc')
                ->orderBy('updated_at', 'desc')
                ->take($limit)
                ->get();

            return $heroes->all();
        } catch (Exception $e) {
            Logger::error("Error fetching heroes for betting: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update hero's level
     */
    public function updateLevel($heroId, $newLevel): ?Hero
    {
        try {
            $hero = Hero::find($heroId);
            if (!$hero) {
                throw new Exception("Hero not found for level update");
            }

            $hero->update(['level' => $newLevel]);
            return $hero;
        } catch (Exception $e) {
            Logger::error("Error updating hero level: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update hero's status
     */
    public function updateStatus($heroId, $newStatus): ?Hero
    {
        try {
            $hero = Hero::find($heroId);
            if (!$hero) {
                throw new Exception("Hero not found for status update");
            }

            $hero->update(['status' => $newStatus]);
            return $hero;
        } catch (Exception $e) {
            Logger::error("Error updating hero status: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all hero settlement interactions for a hero
     */
    public function getHeroSettlementInteractions($heroId)
    {
        try {
            return HeroSettlementInteraction::where('hero_id', $heroId)
                ->orderBy('interaction_date', 'desc')
                ->get()
                ->map->toArray()
                ->all();
        } catch (Exception $e) {
            Logger::error("Error fetching hero settlement interactions: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Record a new hero settlement interaction
     */
    public function recordSettlementInteraction($heroId, $settlementId, $interactionType)
    {
        try {
            HeroSettlementInteraction::create([
                'hero_id' => $heroId,
                'settlement_id' => $settlementId,
                'interaction_type' => $interactionType,
                'interaction_date' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            Logger::error("Error recording settlement interaction: " . $e->getMessage());
            throw $e;
        }
    }
}
