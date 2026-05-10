<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Request;
use App\Actions\LandmarkActions;
use App\Traits\ApiResponseTrait;
use App\Helpers\Logger;

class LandmarkController
{
    use ApiResponseTrait;

    public function __construct(
        private LandmarkActions $landmarkActions
    ) {
    }

    /**
     * Get all landmarks
     */
    public function getAllLandmarks(Request $request, Response $response): Response
    {
        Logger::debug("GET /api/landmarks endpoint called");

        $queryParams = $request->getQueryParams();
        $filters = [
            'type' => $queryParams['type'] ?? null,
            'status' => $queryParams['status'] ?? null,
            'regionId' => $queryParams['regionId'] ?? null,
            'discoveredBy' => $queryParams['discoveredBy'] ?? null,
            'limit' => isset($queryParams['limit']) ? min((int)$queryParams['limit'], 100) : 20,
            'offset' => isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0
        ];

        return $this->handleApiAction(
            $response,
            fn() => $this->landmarkActions->fetchAllLandmarks($filters),
            'fetching landmarks',
            'No landmarks found with the specified criteria'
        );
    }

    /**
     * Get landmark by ID
     */
    public function getLandmarkById(Request $request, Response $response, array $args): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->landmarkActions->fetchLandmarkById($args['id']),
            'fetching landmark',
            'Landmark not found'
        );
    }

    /**
     * Create a new landmark
     */
    public function createLandmark(Request $request, Response $response): Response
    {
        Logger::debug("POST /api/landmarks endpoint called");

        try {
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

            $landmark = $this->landmarkActions->createLandmark($body);

            $responseData = [
                'success' => true,
                'data' => $landmark->toArray()
            ];

            $response->getBody()->write(json_encode($responseData));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(201);
        } catch (\Exception $error) {
            Logger::error('Error creating landmark: ' . $error->getMessage());

            $errorResponse = [
                'success' => false,
                'error' => [
                    'message' => $error->getMessage(),
                    'code' => 'LANDMARK_CREATION_ERROR'
                ]
            ];

            $response->getBody()->write(json_encode($errorResponse));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
    }

    /**
     * Update a landmark
     */
    public function updateLandmark(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        Logger::debug("PUT /api/landmarks/{$id} endpoint called");

        try {
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

            $landmark = $this->landmarkActions->updateLandmark($id, $body);

            $responseData = [
                'success' => true,
                'data' => $landmark->toArray()
            ];

            $response->getBody()->write(json_encode($responseData));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        } catch (\Exception $error) {
            Logger::error('Error updating landmark: ' . $error->getMessage());

            $errorResponse = [
                'success' => false,
                'error' => [
                    'message' => $error->getMessage(),
                    'code' => 'LANDMARK_UPDATE_ERROR'
                ]
            ];

            $response->getBody()->write(json_encode($errorResponse));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
    }

    /**
     * Delete a landmark
     */
    public function deleteLandmark(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        Logger::debug("DELETE /api/landmarks/{$id} endpoint called");

        try {
            // Initialize repositories needed for this endpoint
            $landmarkRepo = new \App\Repositories\LandmarkRepository($this->db);
            $landmarkActions = new LandmarkActions($landmarkRepo);

            $success = $landmarkActions->deleteLandmark($id);

            $responseData = [
                'success' => true,
                'message' => "Landmark with ID '{$id}' has been deleted."
            ];

            $response->getBody()->write(json_encode($responseData));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        } catch (\Exception $error) {
            Logger::error('Error deleting landmark: ' . $error->getMessage());

            $errorResponse = [
                'success' => false,
                'error' => [
                    'message' => $error->getMessage(),
                    'code' => 'LANDMARK_DELETE_ERROR'
                ]
            ];

            $response->getBody()->write(json_encode($errorResponse));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
    }

    /**
     * Discover a landmark
     */
    public function discoverLandmark(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        Logger::debug("POST /api/landmarks/{$id}/discover endpoint called");

        try {
            $body = json_decode($request->getBody(), true);
            $discoveredYear = $body['discoveredYear'] ?? date('Y');

            $landmark = $this->landmarkActions->discoverLandmark($id, $discoveredYear);

            $responseData = [
                'success' => true,
                'data' => $landmark->toArray(),
                'message' => "Landmark '{$landmark->name}' has been discovered."
            ];

            $response->getBody()->write(json_encode($responseData));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        } catch (\Exception $error) {
            Logger::error('Error discovering landmark: ' . $error->getMessage());

            $errorResponse = [
                'success' => false,
                'error' => [
                    'message' => $error->getMessage(),
                    'code' => 'LANDMARK_DISCOVERY_ERROR'
                ]
            ];

            $response->getBody()->write(json_encode($errorResponse));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }
    }
}
