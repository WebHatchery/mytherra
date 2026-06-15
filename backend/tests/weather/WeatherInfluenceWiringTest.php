<?php

declare(strict_types=1);

namespace Tests\Weather;

use PHPUnit\Framework\TestCase;

final class WeatherInfluenceWiringTest extends TestCase
{
    public function testWeatherServiceModelsProbabilisticRegionalEffects(): void
    {
        $service = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/WeatherInfluenceService.php');

        self::assertStringContainsString('class WeatherInfluenceService', $service);
        self::assertStringContainsString("'weather'", $service);
        self::assertStringContainsString("'influence_history'", $service);
        self::assertStringContainsString('gentle_rains', $service);
        self::assertStringContainsString('drought', $service);
        self::assertStringContainsString('protective_winds', $service);
        self::assertStringContainsString('tempest', $service);
        self::assertStringContainsString('arcane_mist', $service);
        self::assertStringContainsString('applySettlementEffects', $service);
        self::assertStringContainsString('applyResourceEffects', $service);
        self::assertStringContainsString('resolveRiskOutcome', $service);
        self::assertStringContainsString('advanceWorld', $service);
        self::assertStringContainsString('resolveWeatherConsequence', $service);
        self::assertStringContainsString('weather_influence', $service);
        self::assertStringContainsString('weather_consequence', $service);
        self::assertStringContainsString('travelEffect', $service);
        self::assertStringContainsString('conflictEffect', $service);
        self::assertStringContainsString('crc32', $service);
    }

    public function testWeatherRoutesStatusExportAndFrontendAreWired(): void
    {
        $routes = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Routes/router.php');
        $container = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Utils/ContainerConfig.php');
        $status = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/StatusActions.php');
        $export = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/ExportService.php');
        $api = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/api/apiService.ts');
        $page = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/pages/WeatherPage.tsx');

        self::assertStringContainsString("WeatherController::class, 'getWeather'", $routes);
        self::assertStringContainsString("WeatherController::class, 'nudgeWeather'", $routes);
        self::assertStringContainsString('WeatherInfluenceService::class', $container);
        self::assertStringContainsString("'weather' => \$this->weatherInfluenceService->status()", $status);
        self::assertStringContainsString("'weather' => \$weather", $export);
        self::assertStringContainsString("'weather' => 'exportWeather'", $export);
        self::assertStringContainsString('WeatherStatusResponse', $api);
        self::assertStringContainsString('getWeatherStatus', $api);
        self::assertStringContainsString('nudgeWeather', $api);
        self::assertStringContainsString('Divine Weather', $page);
        self::assertStringContainsString('Weather Pattern', $page);
    }
}
