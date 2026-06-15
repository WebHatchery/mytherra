<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Actions\PantheonActions;
use App\Core\Request;
use App\Core\Response;
use App\Traits\ApiResponseTrait;

class PantheonController
{
    use ApiResponseTrait;

    public function __construct(private PantheonActions $pantheonActions)
    {
    }

    public function getPantheon(Request $request, Response $response): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->pantheonActions->fetchPantheonStatus(),
            'fetching pantheon pressure'
        );
    }

    public function counterplay(Request $request, Response $response, array $args): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->pantheonActions->counterplay(
                (string)$args['id'],
                $request->getParsedBody() ?? []
            ),
            'applying pantheon counterplay'
        );
    }
}
