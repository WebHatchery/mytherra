<?php

declare(strict_types=1);

namespace Tests\Hero;

use PHPUnit\Framework\TestCase;

final class GameLoopHeroIdentityWiringTest extends TestCase
{
    private string $serviceSource;

    protected function setUp(): void
    {
        $servicePath = dirname(__DIR__, 2) . '/src/Services/GameLoopService.php';
        $serviceSource = file_get_contents($servicePath);
        self::assertIsString($serviceSource);
        $this->serviceSource = $serviceSource;
    }

    public function testHeroTicksUseRegionalPressureForIdentityChanges(): void
    {
        self::assertStringContainsString(
            'private function heroIdentityPressure(Hero $hero): array',
            $this->serviceSource
        );
        self::assertStringContainsString('$identityPressure = $this->heroIdentityPressure($hero);', $this->serviceSource);
        self::assertStringContainsString('$this->applyHeroIdentityPressure($hero, $identityPressure);', $this->serviceSource);
        self::assertStringContainsString('Hero identity pressure:', $this->serviceSource);
        self::assertStringContainsString("'goodDelta'", $this->serviceSource);
        self::assertStringContainsString("'chaoticDelta'", $this->serviceSource);
        self::assertStringContainsString("'traitSignals'", $this->serviceSource);
    }

    public function testHeroTicksRecordSettlementBondsFromResourceAndCrisisPressure(): void
    {
        self::assertStringContainsString('use App\Models\HeroSettlementInteraction;', $this->serviceSource);
        self::assertStringContainsString(
            'private function recordHeroSettlementInteraction(Hero $hero, array $identityPressure, int $currentYear): ?array',
            $this->serviceSource
        );
        self::assertStringContainsString('HeroSettlementInteraction::create([', $this->serviceSource);
        self::assertStringContainsString('heroResourceScarcityResponse', $this->serviceSource);
        self::assertStringContainsString('heroCrisisResponse', $this->serviceSource);
        self::assertStringContainsString('heroProsperityResponse', $this->serviceSource);
        self::assertStringContainsString("'settlementTraitSignals'", $this->serviceSource);
    }

    public function testHeroCivicBondEventsStayInspectable(): void
    {
        self::assertStringContainsString("'hero_civic_bond'", $this->serviceSource);
        self::assertStringContainsString("'Hero Civic Bond'", $this->serviceSource);
        self::assertStringContainsString("\$identityPressure['settlementIds']", $this->serviceSource);
        self::assertStringContainsString("\$identityPressure['resourceIds']", $this->serviceSource);
        self::assertStringContainsString('settlement bond {$settlementInteraction[\'targetName\']}', $this->serviceSource);
        self::assertStringContainsString('private function ids(array $values): array', $this->serviceSource);
    }
}
