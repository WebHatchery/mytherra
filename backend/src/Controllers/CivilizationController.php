<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Actions\CivilizationActions;
use App\Core\Request;
use App\Core\Response;
use App\Traits\ApiResponseTrait;

class CivilizationController
{
    use ApiResponseTrait;

    public function __construct(private CivilizationActions $civilizationActions)
    {
    }

    public function getCivilization(Request $request, Response $response): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->civilizationActions->fetchCivilizationStatus(),
            'fetching civilization behavior'
        );
    }

    public function advanceCivilization(Request $request, Response $response): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->civilizationActions->advanceCivilization($request->getParsedBody() ?? []),
            'advancing civilization behavior'
        );
    }
}
