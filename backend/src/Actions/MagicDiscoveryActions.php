<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\MagicDiscoveryService;

class MagicDiscoveryActions
{
    public function __construct(private MagicDiscoveryService $magicDiscoveryService)
    {
    }

    public function fetchMagicStatus(): array
    {
        return $this->magicDiscoveryService->status();
    }

    public function researchMagic(array $payload): array
    {
        return $this->magicDiscoveryService->research($payload);
    }
}
