<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Actions\ArtifactActions;
use App\Core\Request;
use App\Core\Response;
use App\Traits\ApiResponseTrait;

class ArtifactController
{
    use ApiResponseTrait;

    public function __construct(private ArtifactActions $artifactActions)
    {
    }

    public function getArtifacts(Request $request, Response $response): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->artifactActions->fetchArtifactStatus(),
            'fetching artifacts'
        );
    }

    public function createArtifact(Request $request, Response $response): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->artifactActions->createArtifact($request->getParsedBody() ?? []),
            'creating artifact'
        );
    }

    public function empowerArtifact(Request $request, Response $response, array $args): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->artifactActions->empowerArtifact($args['id']),
            'empowering artifact'
        );
    }

    public function transferArtifact(Request $request, Response $response, array $args): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->artifactActions->transferArtifact($args['id'], $request->getParsedBody() ?? []),
            'transferring artifact'
        );
    }

    public function stabilizeArtifact(Request $request, Response $response, array $args): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->artifactActions->stabilizeArtifact($args['id']),
            'stabilizing artifact'
        );
    }
}
