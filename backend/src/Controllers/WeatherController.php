<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Actions\WeatherActions;
use App\Core\Request;
use App\Core\Response;
use App\Traits\ApiResponseTrait;

class WeatherController
{
    use ApiResponseTrait;

    public function __construct(private WeatherActions $weatherActions)
    {
    }

    public function getWeather(Request $request, Response $response): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->weatherActions->fetchWeatherStatus(),
            'fetching weather influences'
        );
    }

    public function nudgeWeather(Request $request, Response $response): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->weatherActions->nudgeWeather($request->getParsedBody() ?? []),
            'nudging weather'
        );
    }
}
