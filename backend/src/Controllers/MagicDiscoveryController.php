<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Actions\MagicDiscoveryActions;
use App\Core\Request;
use App\Core\Response;
use App\Traits\ApiResponseTrait;

class MagicDiscoveryController
{
    use ApiResponseTrait;

    public function __construct(private MagicDiscoveryActions $magicDiscoveryActions)
    {
    }

    public function getMagic(Request $request, Response $response): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->magicDiscoveryActions->fetchMagicStatus(),
            'fetching magic discovery'
        );
    }

    public function researchMagic(Request $request, Response $response): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->magicDiscoveryActions->researchMagic($request->getParsedBody() ?? []),
            'researching magic discovery'
        );
    }
}
