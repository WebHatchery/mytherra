<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\CivilizationBehaviorService;

class CivilizationActions
{
    public function __construct(private CivilizationBehaviorService $civilizationBehaviorService)
    {
    }

    public function fetchCivilizationStatus(): array
    {
        return $this->civilizationBehaviorService->status();
    }

    public function advanceCivilization(array $payload): array
    {
        return $this->civilizationBehaviorService->advance($payload);
    }
}
