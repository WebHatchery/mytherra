<?php

declare(strict_types=1);

namespace App\Repositories;

use Exception;
use App\Utils\Logger;
use App\Models\GameEvent;

class EventRepository
{
    /**
     * Get event by ID
     */
    public function getById($id)
    {
        try {
            return GameEvent::find($id);
        } catch (Exception $e) {
            Logger::error("Error fetching event by ID", [
                'eventId' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get all events with optional filtering
     */
    public function getAllEvents(array $filters = []): array
    {
        try {
            $query = GameEvent::query();

    // Apply filters
            if (!empty($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['region_id'])) {
                $query->where('related_region_ids', 'like', '%' . $filters['region_id'] . '%');
            }

            if (!empty($filters['hero_id'])) {
                $query->where('related_hero_ids', 'like', '%' . $filters['hero_id'] . '%');
            }

    // Apply pagination if specified
            if (isset($filters['limit'])) {
                $query->take($filters['limit']);
                if (isset($filters['offset'])) {
                    $query->skip($filters['offset']);
                }
            }

            return $query->get()->all();
        } catch (Exception $e) {
            Logger::error("Error fetching events", [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to fetch events from database.');
        }
    }
}
