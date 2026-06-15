<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Actions\MythologyActions;
use App\Core\Request;
use App\Core\Response;
use App\Traits\ApiResponseTrait;

class MythologyController
{
    use ApiResponseTrait;

    public function __construct(private MythologyActions $mythologyActions)
    {
    }

    public function getMyths(Request $request, Response $response): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->mythologyActions->fetchMythologyStatus(),
            'fetching mythology'
        );
    }

    public function promoteMyth(Request $request, Response $response): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->mythologyActions->promoteMyth($request->getParsedBody() ?? []),
            'promoting mythology'
        );
    }
}
