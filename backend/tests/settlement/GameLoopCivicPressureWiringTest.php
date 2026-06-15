<?php

declare(strict_types=1);

namespace Tests\Settlement;

use PHPUnit\Framework\TestCase;

final class GameLoopCivicPressureWiringTest extends TestCase
{
    private string $serviceSource;

    protected function setUp(): void
    {
        $servicePath = dirname(__DIR__, 2) . '/src/Services/GameLoopService.php';
        $serviceSource = file_get_contents($servicePath);
        self::assertIsString($serviceSource);
        $this->serviceSource = $serviceSource;
    }

    public function testSettlementTicksUseCivicPressureFromHeroesAndLandmarks(): void
    {
        self::assertStringContainsString(
            'private function civicPressureForSettlement(Settlement $settlement): array',
            $this->serviceSource
        );
        self::assertStringContainsString("Hero::where('region_id', \$settlement->region_id)", $this->serviceSource);
        self::assertStringContainsString("Landmark::where('region_id', \$settlement->region_id)", $this->serviceSource);
        self::assertStringContainsString("+ \$civicPressure['growthModifier']", $this->serviceSource);
        self::assertStringContainsString("+ \$civicPressure['prosperityDelta']", $this->serviceSource);
        self::assertStringContainsString("+ \$civicPressure['defensibilityDelta']", $this->serviceSource);
    }

    public function testSettlementEventsExposeCivicPressureAndEntityLinks(): void
    {
        self::assertStringContainsString('Civic pressure:', $this->serviceSource);
        self::assertStringContainsString('$this->settlementSpecializationsAfterCivicPressure', $this->serviceSource);
        self::assertStringContainsString('$this->settlementTraitsAfterCivicPressure', $this->serviceSource);
        self::assertStringContainsString('{$civicPressure[\'summary\']}', $this->serviceSource);
        self::assertStringContainsString('$civicPressure[\'heroIds\']', $this->serviceSource);
        self::assertStringContainsString('$civicPressure[\'landmarkIds\']', $this->serviceSource);
    }
}
