<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\TemporalOmenService;

class TemporalOmenActions
{
    public function __construct(private TemporalOmenService $temporalOmenService)
    {
    }

    public function fetchOmenStatus(): array
    {
        return $this->temporalOmenService->status();
    }

    public function readOmen(array $payload): array
    {
        return $this->temporalOmenService->read($payload);
    }
}
