<?php

declare(strict_types=1);

namespace Tests\Landmark;

use PHPUnit\Framework\TestCase;

final class LandmarkForecastingBettingWiringTest extends TestCase
{
    public function testLandmarkForecastingUsesVisibleRiskSignals(): void
    {
        $betting = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/BettingActions.php');
        $odds = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/OddsCalculationService.php');
        $loop = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/GameLoopService.php');
        $smoke = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/test/smoke.test.ts');

        self::assertStringContainsString('landmarkCorruptionCandidates', $betting);
        self::assertStringContainsString('landmarkCorruptionRisk', $betting);
        self::assertStringContainsString('Landmark Corruption:', $betting);
        self::assertStringContainsString("'corruption_spread'", $betting);
        self::assertStringContainsString('Corruption risk', $betting);
        self::assertStringContainsString('Landmark status', $betting);
        self::assertStringContainsString('calculateCorruptionSpreadModifier', $odds);
        self::assertStringContainsString('calculateLandmarkCorruptionModifier', $odds);
        self::assertStringContainsString("target['type'] === 'landmark'", $loop);
        self::assertStringContainsString("'corruption_spread'", $smoke);
    }
}
