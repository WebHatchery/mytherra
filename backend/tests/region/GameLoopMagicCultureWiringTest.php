<?php

declare(strict_types=1);

namespace Tests\Region;

use PHPUnit\Framework\TestCase;

final class GameLoopMagicCultureWiringTest extends TestCase
{
    private string $serviceSource;

    protected function setUp(): void
    {
        $servicePath = dirname(__DIR__, 2) . '/src/Services/GameLoopService.php';
        $serviceSource = file_get_contents($servicePath);
        self::assertIsString($serviceSource);
        $this->serviceSource = $serviceSource;
    }

    public function testRegionTicksUseMagicCulturePressureFromWorldEntities(): void
    {
        self::assertStringContainsString(
            'private function magicCulturePressureForRegion(Region $region): array',
            $this->serviceSource
        );
        self::assertStringContainsString('$magicCulturePressure = $this->magicCulturePressureForRegion($region);', $this->serviceSource);
        self::assertStringContainsString("Hero::where('region_id', \$region->id)", $this->serviceSource);
        self::assertStringContainsString("Landmark::where('region_id', \$region->id)", $this->serviceSource);
        self::assertStringContainsString("ResourceNode::where('region_id', \$region->id)", $this->serviceSource);
        self::assertStringContainsString("Settlement::where('region_id', \$region->id)", $this->serviceSource);
        self::assertStringContainsString('interRegionCulturePressureForRegion', $this->serviceSource);
        self::assertStringContainsString('trade_routes', $this->serviceSource);
        self::assertStringContainsString('Inter-region culture pressure:', $this->serviceSource);
    }

    public function testRegionSummariesExposeMagicCultureAndTraits(): void
    {
        self::assertStringContainsString("\$region->magic_affinity = \$this->clamp(\$oldMagicAffinity + \$magicCulturePressure['magicDelta']);", $this->serviceSource);
        self::assertStringContainsString("\$region->cultural_influence = \$magicCulturePressure['culturalInfluence'];", $this->serviceSource);
        self::assertStringContainsString('$this->regionalTraitsAfterMagicCulturePressure', $this->serviceSource);
        self::assertStringContainsString("'magicAffinity' => \$oldMagicAffinity", $this->serviceSource);
        self::assertStringContainsString("'culturalInfluence' => \$oldCulturalInfluence", $this->serviceSource);
        self::assertStringContainsString("'regionalTraits' => \$oldRegionalTraits", $this->serviceSource);
        self::assertStringContainsString('Magic/culture pressure:', $this->serviceSource);
    }

    public function testRegionEventsLinkMagicCulturePressureEntities(): void
    {
        self::assertStringContainsString('$this->regionEventTitle(', $this->serviceSource);
        self::assertStringContainsString('Regional Culture Shift', $this->serviceSource);
        self::assertStringContainsString('Regional Magic Surge', $this->serviceSource);
        self::assertStringContainsString('$magicCulturePressure[\'heroIds\']', $this->serviceSource);
        self::assertStringContainsString('$magicCulturePressure[\'settlementIds\']', $this->serviceSource);
        self::assertStringContainsString('$magicCulturePressure[\'landmarkIds\']', $this->serviceSource);
        self::assertStringContainsString('$magicCulturePressure[\'resourceIds\']', $this->serviceSource);
        self::assertStringContainsString('$magicCulturePressure[\'relatedRegionIds\']', $this->serviceSource);
        self::assertStringContainsString('$this->ids(array_merge(', $this->serviceSource);
    }
}
