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
        $data = $this->exportService->exportChronicleShare($this->chronicleFilters($request));

        return $this->downloadResponse($response, $data, 'mytherra-chronicle-share.json');
    }

    public function exportChronicleReplay(Request $request, Response $response): Response
    {
        $data = $this->exportService->exportChronicleReplay($this->chronicleFilters($request));

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $data,
        ]);
    }

    public function publishChronicleShare(Request $request, Response $response): Response
    {
        $data = $this->exportService->publishChronicleShare(
            $this->chronicleFilters($request, true),
            $this->authUser($request)
        );

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $data,
        ], 201);
    }

    public function listPublicChronicleShares(Request $request, Response $response): Response
    {
        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $this->exportService->listPublicChronicleShares($this->authUser($request)),
        ]);
    }

    public function revokePublicChronicleShare(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $this->exportService->revokePublicChronicleShare(
                (string)($args['shareId'] ?? ''),
                $this->authUser($request)
            );

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $data,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'NOT_FOUND',
            ], 404);
        }
    }

    public function getPublicChronicleShare(Request $request, Response $response, array $args): Response
    {
        try {
            $data = $this->exportService->getPublicChronicleShare((string)($args['shareId'] ?? ''));

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $data,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'NOT_FOUND',
            ], 404);
        }
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
                'chronicleReplay' => 'Interactive chronicle playback frames built from event history',
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

    private function chronicleFilters(Request $request, bool $includeBody = false): array
    {
        $queryParams = $request->getQueryParams();
        $body = $includeBody ? ($request->getParsedBody() ?? []) : [];
        if (!is_array($body)) {
            $body = [];
        }

        return [
            'limit' => $body['limit'] ?? $queryParams['limit'] ?? null,
            'era' => $body['era'] ?? $queryParams['era'] ?? null,
            'regionId' => $body['regionId'] ?? $body['region_id'] ?? $queryParams['regionId'] ?? $queryParams['region_id'] ?? null,
            'heroId' => $body['heroId'] ?? $body['hero_id'] ?? $queryParams['heroId'] ?? $queryParams['hero_id'] ?? null,
            'settlementId' => $body['settlementId'] ?? $body['settlement_id'] ?? $queryParams['settlementId'] ?? $queryParams['settlement_id'] ?? null,
            'landmarkId' => $body['landmarkId'] ?? $body['landmark_id'] ?? $queryParams['landmarkId'] ?? $queryParams['landmark_id'] ?? null,
            'resourceId' => $body['resourceId'] ?? $body['resource_id'] ?? $queryParams['resourceId'] ?? $queryParams['resource_id'] ?? null,
        ];
    }

    private function authUser(Request $request): array
    {
        $authUser = $request->getAttribute('auth_user', []);

        return is_array($authUser) ? $authUser : [];
    }
}
