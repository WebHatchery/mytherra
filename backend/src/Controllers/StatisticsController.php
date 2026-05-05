<?php

namespace App\Controllers;

use App\Services\StatisticsService;
use App\Core\Response;
use App\Core\Request;

class StatisticsController
{
    private StatisticsService $statisticsService;

    public function __construct(StatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Get general game summary
     */
    public function getSummary(Request $request, Response $response): Response
    {
        $data = $this->statisticsService->getGameSummary();
        return $this->jsonResponse($response, $data);
    }

    /**
     * Get hero statistics
     */
    public function getHeroStats(Request $request, Response $response): Response
    {
        $data = $this->statisticsService->getHeroStatistics();
        return $this->jsonResponse($response, $data);
    }

    /**
     * Get region statistics
     */
    public function getRegionStats(Request $request, Response $response): Response
    {
        $data = $this->statisticsService->getRegionStatistics();
        return $this->jsonResponse($response, $data);
    }

    /**
     * Get financial statistics
     */
    public function getFinancialStats(Request $request, Response $response): Response
    {
        $data = $this->statisticsService->getFinancialStatistics();
        return $this->jsonResponse($response, $data);
    }

    /**
     * Helper to return JSON response
     */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $data
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
