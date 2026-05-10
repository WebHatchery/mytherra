<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Request;
use App\Actions\EventActions;
use App\Traits\ApiResponseTrait;
use App\Helpers\Logger;

class EventController
{
    use ApiResponseTrait;

    public function __construct(
        private EventActions $eventActions
    ) {
    }

    /**
     * Get all events with optional filtering
     */
    public function getAllEvents(Request $request, Response $response): Response
    {
        Logger::debug("GET /api/events endpoint called");

        $queryParams = $request->getQueryParams();
        $filters = [
            'type' => $queryParams['type'] ?? null,
            'status' => $queryParams['status'] ?? null,
            'regionId' => $queryParams['regionId'] ?? null,
            'heroId' => $queryParams['heroId'] ?? null,
            'limit' => isset($queryParams['limit']) ? min((int)$queryParams['limit'], 100) : 20,
            'offset' => isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0
        ];

        return $this->handleApiAction(
            $response,
            fn() => $this->eventActions->fetchAllEvents($filters),
            'fetching events',
            'No events found with the specified criteria'
        );
    }

    /**
     * Get event by ID
     */
    public function getEventById(Request $request, Response $response, array $args): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->eventActions->fetchEventById($args['id']),
            'fetching event',
            'Event not found'
        );
    }
}
