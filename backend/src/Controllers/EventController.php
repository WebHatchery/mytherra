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
        $limit = isset($queryParams['limit']) ? min(max((int)$queryParams['limit'], 1), 100) : 20;
        $page = isset($queryParams['page']) ? max((int)$queryParams['page'], 1) : 1;
        $offset = isset($queryParams['offset']) ? max((int)$queryParams['offset'], 0) : (($page - 1) * $limit);

        $filters = [
            'type' => $queryParams['type'] ?? null,
            'status' => $queryParams['status'] ?? null,
            'regionId' => $queryParams['regionId'] ?? $queryParams['region_id'] ?? null,
            'heroId' => $queryParams['heroId'] ?? $queryParams['hero_id'] ?? null,
            'settlementId' => $queryParams['settlementId'] ?? $queryParams['settlement_id'] ?? null,
            'landmarkId' => $queryParams['landmarkId'] ?? $queryParams['landmark_id'] ?? null,
            'resourceId' => $queryParams['resourceId'] ?? $queryParams['resource_id'] ?? null,
            'era' => $queryParams['era'] ?? null,
            'limit' => $limit,
            'offset' => $offset
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
