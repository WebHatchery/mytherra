<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\EntityHistoryService;
use App\Traits\ApiResponseTrait;

class HistoryController
{
    use ApiResponseTrait;

    public function __construct(
        private EntityHistoryService $historyService
    ) {
    }

    public function getSummary(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $limit = isset($queryParams['limit']) ? (int)$queryParams['limit'] : 6;
        $eventsPerEntity = isset($queryParams['eventsPerEntity']) ? (int)$queryParams['eventsPerEntity'] : 3;
        $regionId = isset($queryParams['regionId']) ? (string)$queryParams['regionId'] : null;

        return $this->handleApiAction(
            $response,
            fn() => $this->historyService->getSummary($limit, $eventsPerEntity, $regionId),
            'fetching entity history summary',
            'Failed to fetch entity history summary'
        );
    }
}
