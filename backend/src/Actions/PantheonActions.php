<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\PantheonService;

class PantheonActions
{
    public function __construct(private PantheonService $pantheonService)
    {
    }

    public function fetchPantheonStatus(): array
    {
        return $this->pantheonService->status();
    }

    public function counterplay(string $deityId, array $payload): array
    {
        return $this->pantheonService->counterplay($deityId, $payload);
    }
}
