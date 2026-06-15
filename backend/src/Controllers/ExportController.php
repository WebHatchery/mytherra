<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ExportService;
use App\Core\Response;
use App\Core\Request;

class ExportController
{
    private ExportService $exportService;

    public function __construct(ExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    /**
     * Export full world snapshot
     */
    public function exportFull(Request $request, Response $response): Response
    {
        $data = $this->exportService->exportFullSnapshot();

        return $this->downloadResponse($response, $data, 'mytherra-world-snapshot.json');
    }

    public function exportChronicleShare(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $data = $this->exportService->exportChronicleShare([
            'limit' => $queryParams['limit'] ?? null,
            'era' => $queryParams['era'] ?? null,
            'regionId' => $queryParams['regionId'] ?? $queryParams['region_id'] ?? null,
            'heroId' => $queryParams['heroId'] ?? $queryParams['hero_id'] ?? null,
            'settlementId' => $queryParams['settlementId'] ?? $queryParams['settlement_id'] ?? null,
            'landmarkId' => $queryParams['landmarkId'] ?? $queryParams['landmark_id'] ?? null,
            'resourceId' => $queryParams['resourceId'] ?? $queryParams['resource_id'] ?? null,
        ]);

        return $this->downloadResponse($response, $data, 'mytherra-chronicle-share.json');
    }

    /**
     * Export by specific type
     */
    public function exportByType(Request $request, Response $response, array $args): Response
    {
        $type = $args['type'] ?? '';

        try {
            $data = $this->exportService->exportByType($type);
            return $this->downloadResponse($response, $data, "mytherra-{$type}-export.json");
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get available export types
     */
    public function getExportTypes(Request $request, Response $response): Response
    {
        return $this->jsonResponse($response, [
            'success' => true,
            'types' => [
                'regions' => 'All regions with metadata',
                'heroes' => 'All heroes with stats',
                'settlements' => 'All settlements',
                'buildings' => 'All buildings',
                'landmarks' => 'All landmarks',
                'resources' => 'All resource nodes',
                'civilization' => 'Civilization agendas and recent decisions',
                'champions' => 'Mortal champion roster, outcomes, betting hooks, and legacy hooks',
                'pantheon' => 'AI pantheon pressure, relationships, and recent interventions',
                'chronicle' => 'Curated share package with highlights, linked entity spotlights, bets, and timeline cards',
                'bets' => 'All divine bets',
                'events' => 'Recent events (last 1000)'
            ]
        ]);
    }

    /**
     * Return JSON as downloadable file
     */
    private function downloadResponse(Response $response, array $data, string $filename): Response
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $response->getBody()->write($json);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->withStatus(200);
    }

    /**
     * Helper to return JSON response
     */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
