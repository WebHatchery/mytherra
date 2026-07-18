<?php

declare(strict_types=1);

namespace Tests\Region;

use PHPUnit\Framework\TestCase;

final class CultureForecastingBettingWiringTest extends TestCase
{
    public function testCultureShiftSpeculationUsesTradeRoutesAndForecastSignals(): void
    {
        $betting = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/BettingActions.php');
        $odds = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/OddsCalculationService.php');
        $loop = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/GameLoopService.php');

        self::assertStringContainsString("'cultural_shift'", $betting);
        self::assertStringContainsString('cultureShiftCandidates', $betting);
        self::assertStringContainsString('cultureShiftForecastForRegion', $betting);
        self::assertStringContainsString('connectedCultureRegions', $betting);
        self::assertStringContainsString('Trade routes', $betting);
        self::assertStringContainsString('Forecast culture', $betting);
        self::assertStringContainsString('calculateCultureShiftModifier', $odds);
        self::assertStringContainsString('trade_culture_exchange', $odds);
        self::assertStringContainsString('recentCultureShiftForRegion', $loop);
        self::assertStringContainsString('Regional Culture Shift', $loop);
        self::assertStringContainsString("'scholarly', 'mystical', 'martial', 'mercantile'", $loop);
    }

    public function testFrontendBettingSmokeCoversCultureShiftType(): void
    {
        $betEntity = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/entities/divineBet.ts');
        $smoke = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/test/smoke.test.ts');

        self::assertStringContainsString("'cultural_shift'", $betEntity);
        self::assertStringContainsString("'cultural_shift'", $smoke);
    }
}
