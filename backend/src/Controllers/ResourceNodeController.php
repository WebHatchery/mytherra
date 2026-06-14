<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Actions\ResourceNodeActions;
use App\Traits\ApiResponseTrait;
use App\Helpers\Logger;

class ResourceNodeController
{
    use ApiResponseTrait;

    public function __construct(
        private ResourceNodeActions $resourceNodeActions
    ) {
    }

    public function getAllResourceNodes(Request $request, Response $response): Response
    {
        Logger::debug("GET /api/resource-nodes endpoint called");

        $queryParams = $request->getQueryParams();
        $filters = [
            'regionId' => $queryParams['regionId'] ?? null,
            'settlementId' => $queryParams['settlementId'] ?? null,
            'type' => $queryParams['type'] ?? null,
            'status' => $queryParams['status'] ?? null,
            'minOutput' => isset($queryParams['minOutput']) ? (int)$queryParams['minOutput'] : null,
            'maxOutput' => isset($queryParams['maxOutput']) ? (int)$queryParams['maxOutput'] : null,
            'limit' => isset($queryParams['limit']) ? min((int)$queryParams['limit'], 100) : 20,
            'offset' => isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0
        ];

        return $this->handleApiAction(
            $response,
            fn() => $this->resourceNodeActions->fetchAllResourceNodes($filters),
            'fetching resource nodes',
            'No resource nodes found'
        );
    }

    public function getResourceNodeById(Request $request, Response $response, array $args): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->resourceNodeActions->fetchResourceNodeById($args['id']),
            'fetching resource node',
            'Resource node not found'
        );
    }

    public function createResourceNode(Request $request, Response $response): Response
    {
        $body = json_decode((string)$request->getBody(), true);
        if (!is_array($body)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Invalid JSON input',
                'error_code' => 'VALIDATION_ERROR'
            ], 400);
        }

        return $this->handleApiAction(
            $response,
            fn() => $this->resourceNodeActions->createResourceNode($body),
            'creating resource node',
            null,
            201
        );
    }

    public function updateResourceNode(Request $request, Response $response, array $args): Response
    {
        $body = json_decode((string)$request->getBody(), true);
        if (!is_array($body)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Invalid JSON input',
                'error_code' => 'VALIDATION_ERROR'
            ], 400);
        }

        return $this->handleApiAction(
            $response,
            fn() => $this->resourceNodeActions->updateResourceNode($args['id'], $body),
            'updating resource node',
            'Resource node not found'
        );
    }

    public function deleteResourceNode(Request $request, Response $response, array $args): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => ['deleted' => $this->resourceNodeActions->deleteResourceNode($args['id'])],
            'deleting resource node',
            'Resource node not found'
        );
    }
}
