<?php

declare(strict_types=1);

namespace App\Actions;

use App\Services\WeatherInfluenceService;

class WeatherActions
{
    public function __construct(private WeatherInfluenceService $weatherInfluenceService)
    {
    }

    public function fetchWeatherStatus(): array
    {
        return $this->weatherInfluenceService->status();
    }

    public function nudgeWeather(array $payload): array
    {
        return $this->weatherInfluenceService->nudge($payload);
    }
}
