<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Request;
use App\Actions\BuildingActions;
use App\Traits\ApiResponseTrait;
use App\Helpers\Logger;

class BuildingController
{
    use ApiResponseTrait;

    public function __construct(
        private BuildingActions $buildingActions
    ) {
    }

    /**
     * Get all buildings with optional filtering
     * Supports query parameters: settlementId, type, status, minCondition, maxCondition, limit, offset
     */
    public function getAllBuildings(Request $request, Response $response): Response
    {
        Logger::debug("GET /api/buildings endpoint called");

        $queryParams = $request->getQueryParams();
        $filters = [
            'type' => $queryParams['type'] ?? null,
            'status' => $queryParams['status'] ?? null,
            'settlementId' => $queryParams['settlementId'] ?? null,
            'level' => isset($queryParams['level']) ? (int)$queryParams['level'] : null,
            'limit' => isset($queryParams['limit']) ? min((int)$queryParams['limit'], 100) : 20,
            'offset' => isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0
        ];

        return $this->handleApiAction(
            $response,
            fn() => $this->buildingActions->fetchAllBuildings($filters),
            'fetching buildings',
            'No buildings found with the specified criteria'
        );
    }

    /**
     * Get building by ID
     */
    public function getBuildingById(Request $request, Response $response, array $args): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->buildingActions->fetchBuildingById($args['id']),
            'fetching building',
            'Building not found'
        );
    }

    /**
     * Create a new building
     */
    public function createBuilding(Request $request, Response $response): Response
    {
        Logger::debug("POST /api/buildings endpoint called");

        $body = json_decode($request->getBody(), true);

        if (!$body) {
            $errorResponse = [
                'success' => false,
                'error' => [
                    'message' => 'Invalid JSON input',
                    'code' => 'VALIDATION_ERROR'
                ]
            ];

            $response->getBody()->write(json_encode($errorResponse));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        return $this->handleApiAction(
            $response,
            fn() => $this->buildingActions->createBuilding($body),
            'creating building',
            'Failed to create building',
            201
        );
    }

    /**
     * Update a building
     */
    public function updateBuilding(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        Logger::debug("PUT /api/buildings/{$id} endpoint called");

        $body = json_decode($request->getBody(), true);

        if (!$body) {
            $errorResponse = [
                'success' => false,
                'error' => [
                    'message' => 'Invalid JSON input',
                    'code' => 'VALIDATION_ERROR'
                ]
            ];

            $response->getBody()->write(json_encode($errorResponse));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        return $this->handleApiAction(
            $response,
            fn() => $this->buildingActions->updateBuilding($id, $body),
            'updating building',
            'Building not found'
        );
    }

    /**
     * Delete a building
     */
    public function deleteBuilding(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        Logger::debug("DELETE /api/buildings/{$id} endpoint called");

        return $this->handleApiAction(
            $response,
            fn() => $this->buildingActions->deleteBuilding($id),
            'deleting building',
            'Building not found'
        );
    }
}
