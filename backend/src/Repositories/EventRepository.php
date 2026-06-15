<?php

declare(strict_types=1);

namespace App\Repositories;

use Exception;
use App\Utils\Logger;
use App\Models\GameEvent;

class EventRepository
{
    private const ERA_LENGTH_YEARS = 100;

    public function createEvent(array $eventData): GameEvent
    {
        try {
            $event = new GameEvent(array_merge(
                [
                    'id' => 'event-' . bin2hex(random_bytes(8)),
                    'timestamp' => date('c'),
                    'status' => 'completed',
                    'related_region_ids' => [],
                    'related_hero_ids' => [],
                    'related_settlement_ids' => [],
                    'related_landmark_ids' => [],
                    'related_resource_ids' => []
                ],
                $eventData
            ));
            $event->save();

            return $event;
        } catch (Exception $e) {
            Logger::error("Error creating event", [
                'eventData' => $eventData,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

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

            if (!empty($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            $regionId = $filters['regionId'] ?? $filters['region_id'] ?? null;
            if (!empty($regionId)) {
                $query->where(function ($inner) use ($regionId) {
                    $inner->where('region_id', $regionId)
                        ->orWhereJsonContains('related_region_ids', $regionId);
                });
            }

            $heroId = $filters['heroId'] ?? $filters['hero_id'] ?? null;
            if (!empty($heroId)) {
                $query->whereJsonContains('related_hero_ids', $heroId);
            }

            $settlementId = $filters['settlementId'] ?? $filters['settlement_id'] ?? null;
            if (!empty($settlementId)) {
                $query->whereJsonContains('related_settlement_ids', $settlementId);
            }

            $landmarkId = $filters['landmarkId'] ?? $filters['landmark_id'] ?? null;
            if (!empty($landmarkId)) {
                $query->whereJsonContains('related_landmark_ids', $landmarkId);
            }

            $resourceId = $filters['resourceId'] ?? $filters['resource_id'] ?? null;
            if (!empty($resourceId)) {
                $query->whereJsonContains('related_resource_ids', $resourceId);
            }

            $era = $filters['era'] ?? null;
            if ($era !== null && $era !== '' && is_numeric($era)) {
                $eraNumber = max(1, (int)$era);
                $startYear = (($eraNumber - 1) * self::ERA_LENGTH_YEARS) + 1;
                $endYear = $eraNumber * self::ERA_LENGTH_YEARS;
                $query->whereBetween('year', [$startYear, $endYear]);
            }

            $query
                ->orderByRaw('COALESCE(year, 0) DESC')
                ->orderByDesc('timestamp')
                ->orderByDesc('created_at');

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
