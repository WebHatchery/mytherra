<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\MythologyService;

class MythologyActions
{
    public function __construct(private MythologyService $mythologyService)
    {
    }

    public function fetchMythologyStatus(): array
    {
        return $this->mythologyService->status();
    }

    public function promoteMyth(array $payload): array
    {
        return $this->mythologyService->promote($payload);
    }
}
