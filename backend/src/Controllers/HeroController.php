<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Request;
use App\Actions\HeroActions;
use App\Traits\ApiResponseTrait;

class HeroController
{
    use ApiResponseTrait;

    public function __construct(
        private HeroActions $heroActions
    ) {
    }

    /**
     * Get all heroes
     */
    public function getAllHeroes(Request $request, Response $response): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->heroActions->fetchAllHeroes([]),
            'fetching heroes',
            'Hero not found'
        );
    }

    /**
     * Get hero by ID
     */
    public function getHeroById(Request $request, Response $response, array $args): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->heroActions->fetchHeroById($args['id']),
            'fetching hero',
            'Hero not found'
        );
    }

    public function getChampionStatus(Request $request, Response $response): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->heroActions->fetchChampionStatus(),
            'fetching champions'
        );
    }

    public function designateChampion(Request $request, Response $response, array $args): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->heroActions->designateChampion($args['id']),
            'designating champion'
        );
    }

    public function cultivateChampion(Request $request, Response $response, array $args): Response
    {
        $body = $request->getParsedBody() ?? [];
        $focus = is_string($body['focus'] ?? null) ? $body['focus'] : 'quest';

        return $this->handleApiAction(
            $response,
            fn() => $this->heroActions->cultivateChampion($args['id'], $focus),
            'cultivating champion'
        );
    }
}
