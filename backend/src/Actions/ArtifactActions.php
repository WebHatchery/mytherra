<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\ArtifactService;

class ArtifactActions
{
    public function __construct(private ArtifactService $artifactService)
    {
    }

    public function fetchArtifactStatus(): array
    {
        return $this->artifactService->status();
    }

    public function createArtifact(array $payload): array
    {
        return $this->artifactService->create($payload);
    }

    public function empowerArtifact(string $artifactId): array
    {
        return $this->artifactService->empower($artifactId);
    }

    public function transferArtifact(string $artifactId, array $payload): array
    {
        $targetType = is_string($payload['targetType'] ?? null) ? $payload['targetType'] : 'unbound';
        $targetId = is_string($payload['targetId'] ?? null) ? $payload['targetId'] : null;

        return $this->artifactService->transfer($artifactId, $targetType, $targetId);
    }

    public function stabilizeArtifact(string $artifactId): array
    {
        return $this->artifactService->stabilize($artifactId);
    }
}
