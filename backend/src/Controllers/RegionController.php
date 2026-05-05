<?php

namespace App\Controllers;

use App\Core\Response;
use App\Core\Request;
use App\Actions\RegionActions;
use App\Traits\ApiResponseTrait;

class RegionController
{
    use ApiResponseTrait;

    public function __construct(
        private RegionActions $regionActions
    ) {
    }

    /**
     * Get all regions
     */
    public function getAllRegions(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $filters = [
            'type' => $queryParams['type'] ?? null,
            'status' => $queryParams['status'] ?? null,
            'limit' => isset($queryParams['limit']) ? min((int)$queryParams['limit'], 100) : 20,
            'offset' => isset($queryParams['offset']) ? (int)$queryParams['offset'] : 0
        ];

        return $this->handleApiAction(
            $response,
            fn() => $this->regionActions->fetchAllRegions($filters),
            'fetching regions',
            'Region not found'
        );
    }

    /**
     * Get region by ID
     */
    public function getRegionById(Request $request, Response $response, array $args): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->regionActions->fetchRegionById($args['id']),
            'fetching region',
            'Region not found'
        );
    }

    /**
     * Get landmarks for a specific region
     */
    public function getRegionLandmarks(Request $request, Response $response, array $args): Response
    {
        $regionId = $args['id'];

        return $this->handleApiAction(
            $response,
            fn() => $this->regionActions->getLandmarksByRegion($regionId),
            'fetching region landmarks',
            'No landmarks found in this region'
        );
    }
}
