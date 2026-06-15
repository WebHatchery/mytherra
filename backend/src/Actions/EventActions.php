<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\GameEvent;
use App\Repositories\EventRepository;
use App\Core\Exceptions\ResourceNotFoundException;
use App\Helpers\Logger;

/**
 * Handles game event operations
 */
class EventActions
{
    public function __construct(
        private EventRepository $eventRepository
    ) {
    }

    /**
     * Fetch all events with optional filtering
     *
     * @param array $filters Optional filters for the query
     * @return array Array of event data
     * @throws \RuntimeException if database operation fails
     */
    public function fetchAllEvents(array $filters = []): array
    {
        try {
            $limit = $filters['limit'] ?? 10;
            $offset = $filters['offset'] ?? 0;

            $events = $this->eventRepository->getAllEvents($filters);

            return array_map(fn($event) => $event->toArray(), $events);
        } catch (\Exception $error) {
            Logger::error('Error fetching events', [
                'filters' => $filters,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch events from database', 0, $error);
        }
    }

    /**
     * Fetch an event by ID
     *
     * @param string $eventId The ID of the event to fetch
     * @return array Event data
     * @throws ResourceNotFoundException if event not found
     * @throws \RuntimeException if database operation fails
     */
    public function fetchEventById(string $eventId): array
    {
        try {
            $event = $this->eventRepository->getById($eventId);

            if (!$event) {
                Logger::info("Event not found", ['eventId' => $eventId]);
                throw new ResourceNotFoundException("Event not found: {$eventId}");
            }

            if (!($event instanceof GameEvent)) {
                throw new \RuntimeException("Invalid event data returned from repository");
            }

            return $event->toArray();
        } catch (ResourceNotFoundException $error) {
            throw $error;
        } catch (\Exception $error) {
            Logger::error('Error fetching event', [
                'eventId' => $eventId,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch event from database', 0, $error);
        }
    }

    /**
     * Count all events
     *
     * @return int Total number of events
     * @throws \RuntimeException if database operation fails
     */
    public function countAllEvents(): int
    {
        try {
            return $this->eventRepository->count();
        } catch (\Exception $error) {
            Logger::error('Error counting events', [
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to count events', 0, $error);
        }
    }

    /**
     * Create a new event
     *
     * @param array $eventData The event data
     * @return array The created event data
     * @throws \RuntimeException if validation fails or database operation fails
     */
    public function createEvent(array $eventData): array
    {
        try {
            $eventId = 'event-' . bin2hex(random_bytes(8));

            $event = new GameEvent(array_merge($eventData, [
                'id' => $eventId,
                'timestamp' => date('Y-m-d H:i:s'),
                'related_region_ids' => $eventData['related_region_ids'] ?? [],
                'related_hero_ids' => $eventData['related_hero_ids'] ?? [],
                'related_settlement_ids' => $eventData['related_settlement_ids'] ?? [],
                'related_landmark_ids' => $eventData['related_landmark_ids'] ?? [],
                'related_resource_ids' => $eventData['related_resource_ids'] ?? []
            ]));

            $event->save();

            Logger::info("Successfully created event", ['id' => $eventId]);
            return $event->toArray();
        } catch (\Exception $error) {
            Logger::error('Error creating event', [
                'data' => $eventData,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to create event', 0, $error);
        }
    }
}
