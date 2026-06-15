<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Actions\TemporalOmenActions;
use App\Core\Request;
use App\Core\Response;
use App\Traits\ApiResponseTrait;

class TemporalOmenController
{
    use ApiResponseTrait;

    public function __construct(private TemporalOmenActions $temporalOmenActions)
    {
    }

    public function getOmens(Request $request, Response $response): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->temporalOmenActions->fetchOmenStatus(),
            'fetching temporal omens'
        );
    }

    public function readOmen(Request $request, Response $response): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->temporalOmenActions->readOmen($request->getParsedBody() ?? []),
            'reading temporal omen'
        );
    }
}
