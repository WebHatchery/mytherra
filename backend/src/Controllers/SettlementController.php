<?php

namespace App\Controllers;

use App\Core\Response;
use App\Core\Request;
use App\Actions\SettlementActions;
use App\Actions\BuildingActions;
use App\Traits\ApiResponseTrait;
use App\Helpers\Logger;

class SettlementController
{
    use ApiResponseTrait;

    public function __construct(
        private SettlementActions $settlementActions,
        private BuildingActions $buildingActions
    ) {
    }

    /**
     * Get all settlements with optional filtering
     * Supports query parameters: regionId, settlementType, minPopulation, maxPopulation, limit, offset
     */
    public function getAllSettlements(Request $request, Response $response): Response
    {
        Logger::debug("GET /api/settlements endpoint called");

        $queryParams = $request->getQueryParams();
        $filters = [
            'regionId' => $queryParams['regionId'] ?? null,
            'settlementType' => $queryParams['settlementType'] ?? null,
            'minPopulation' => isset($queryParams['minPopulation']) ? (int)$queryParams['minPopulation'] : null,
            'maxPopulation' => isset($queryParams['maxPopulation']) ? (int)$queryParams['maxPopulation'] : null,
            'limit' => isset($queryParams['limit']) ? min((int)$queryParams['limit'], 100) : 20,
            'offset' => isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0
        ];

        return $this->handleApiAction(
            $response,
            fn() => $this->settlementActions->fetchAllSettlements($filters),
            'fetching settlements',
            'No settlements found with the specified criteria'
        );
    }

    /**
     * Get settlement by ID
     */
    public function getSettlementById(Request $request, Response $response, array $args): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->settlementActions->fetchSettlementById($args['id']),
            'fetching settlement',
            'Settlement not found'
        );
    }

    /**
     * Get buildings for a specific settlement
     */
    public function getSettlementBuildings(Request $request, Response $response, array $args): Response
    {
        Logger::debug("GET /api/settlements/{$args['id']}/buildings endpoint called");

        $queryParams = $request->getQueryParams();
        $filters = [
            'settlementId' => $args['id'],
            'type' => $queryParams['type'] ?? null,
            'status' => $queryParams['status'] ?? null,
            'minCondition' => isset($queryParams['minCondition']) ? (int)$queryParams['minCondition'] : null,
            'maxCondition' => isset($queryParams['maxCondition']) ? (int)$queryParams['maxCondition'] : null,
            'limit' => isset($queryParams['limit']) ? min((int)$queryParams['limit'], 100) : 20,
            'offset' => isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0
        ];

        return $this->handleApiAction(
            $response,
            fn() => $this->buildingActions->fetchAllBuildings($filters),
            'fetching settlement buildings',
            'No buildings found for this settlement'
        );
    }
}
