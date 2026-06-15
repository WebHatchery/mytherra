<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Request;
use App\Actions\StatusActions;
use App\Traits\ApiResponseTrait;
use App\Helpers\Logger;

class StatusController
{
    use ApiResponseTrait;

    public function __construct(
        private StatusActions $statusActions
    ) {
    }

    /**
     * Get game status
     */
    public function getGameStatus(Request $request, Response $response): Response
    {
        Logger::debug("GET /api/status endpoint called");

        return $this->handleApiAction(
            $response,
            fn() => $this->statusActions->fetchGameStatus(),
            'fetching game status',
            'Failed to fetch game status'
        );
    }

    /**
     * Get API status
     */
    public function getApiStatus(Request $request, Response $response): Response
    {
        $apiStatus = [
            'status' => 'API is operational',
            'time' => date('c'),
            'endpoints' => [
                '/regions',
                '/regions/:id',
                '/regions/:id/process',
                '/heroes',
                '/heroes/:id',
                '/settlements',
                '/settlements/:id',
                '/buildings',
                '/buildings/:id',                '/landmarks',
                '/landmarks/:id',
                '/landmarks/:id/discover',
                '/bets',
                '/bets/summary',
                '/bets/:id',
                '/speculation-events',
                '/betting-odds',
                '/admin/process-expired-bets',
                '/events',
                '/events/:id',
                '/influence/region/:id',
                '/influence/hero/:id',
                '/divine-influence/calculate-cost',
                '/divine-influence/apply',
                '/status',
                '/site-status'
            ]
        ];

        return $this->jsonResponse($response, $apiStatus);
    }

    /**
     * Get combined status
     */
    public function getStatus(Request $request, Response $response): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => [
                'game' => $this->statusActions->fetchGameStatus(),
                'api' => [
                    'status' => 'API is operational',
                    'time' => date('c'),
                    'version' => $this->statusActions->fetchVersionConfig()
                ]
            ],
            'fetching status',
            'Failed to fetch status'
        );
    }

    public function runGameTick(Request $request, Response $response): Response
    {
        Logger::debug("POST /api/admin/game-loop/tick endpoint called");

        $body = json_decode((string)$request->getBody(), true) ?: [];
        $advanceYear = array_key_exists('advanceYear', $body) ? (bool)$body['advanceYear'] : true;

        return $this->handleApiAction(
            $response,
            fn() => $this->statusActions->runGameTick($advanceYear),
            'running game tick',
            'Failed to run game tick'
        );
    }

    public function runEraTransition(Request $request, Response $response): Response
    {
        Logger::debug("POST /api/admin/era/transition endpoint called");

        $body = json_decode((string)$request->getBody(), true) ?: [];
        $force = array_key_exists('force', $body) ? (bool)$body['force'] : false;

        return $this->handleApiAction(
            $response,
            fn() => $this->statusActions->runEraTransition($force),
            'running era transition',
            'Failed to run era transition'
        );
    }

    public function startGameLoop(Request $request, Response $response): Response
    {
        Logger::debug("POST /api/admin/game-loop/start endpoint called");

        return $this->handleApiAction(
            $response,
            fn() => $this->statusActions->startGameLoop(),
            'starting game loop',
            'Failed to start game loop'
        );
    }

    public function stopGameLoop(Request $request, Response $response): Response
    {
        Logger::debug("POST /api/admin/game-loop/stop endpoint called");

        return $this->handleApiAction(
            $response,
            fn() => $this->statusActions->stopGameLoop(),
            'stopping game loop',
            'Failed to stop game loop'
        );
    }
}
