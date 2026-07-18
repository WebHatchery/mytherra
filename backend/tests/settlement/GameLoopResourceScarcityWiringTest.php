<?php

declare(strict_types=1);

namespace Tests\Settlement;

use PHPUnit\Framework\TestCase;

final class GameLoopResourceScarcityWiringTest extends TestCase
{
    private string $serviceSource;

    protected function setUp(): void
    {
        $servicePath = dirname(__DIR__, 2) . '/src/Services/GameLoopService.php';
        $serviceSource = file_get_contents($servicePath);
        self::assertIsString($serviceSource);
        $this->serviceSource = $serviceSource;
    }

    public function testResourceTicksClassifyScarcityThresholds(): void
    {
        self::assertStringContainsString(
            'private function resourceScarcityTier(int $output, string $status): string',
            $this->serviceSource
        );
        self::assertStringContainsString('$oldScarcityTier = $this->resourceScarcityTier', $this->serviceSource);
        self::assertStringContainsString("'scarcityTier' => \$scarcityTier", $this->serviceSource);
        self::assertStringContainsString('Resource Scarcity Critical', $this->serviceSource);
        self::assertStringContainsString('Resource Scarcity Relief', $this->serviceSource);
    }

    public function testResourceScarcityAffectsLinkedSettlementSurvival(): void
    {
        self::assertStringContainsString(
            'private function applyResourceScarcitySettlementImpact(',
            $this->serviceSource
        );
        self::assertStringContainsString('$settlement->prosperity = $this->clamp', $this->serviceSource);
        self::assertStringContainsString('$settlement->defensibility = $this->clamp', $this->serviceSource);
        self::assertStringContainsString('$this->settlementStatusFor(', $this->serviceSource);
        self::assertStringContainsString('settlementTraitsAfterResourceScarcity', $this->serviceSource);
        self::assertStringContainsString('Linked settlement survival metrics changed.', $this->serviceSource);
    }

    public function testResourceScarcityEventsExposeReadableLinks(): void
    {
        self::assertStringContainsString('private function resourceThresholdNotes(', $this->serviceSource);
        self::assertStringContainsString('$this->resourceRelatedHeroIds($node, (string)$node->status)', $this->serviceSource);
        self::assertStringContainsString("\$settlementImpact['summary']", $this->serviceSource);
        self::assertStringContainsString("\$this->sentenceFromNotes(\$thresholdNotes)", $this->serviceSource);
        self::assertStringContainsString("'scarcityTier' => \$oldScarcityTier", $this->serviceSource);
        self::assertStringContainsString("'scarcityTier' => \$outcome['scarcityTier']", $this->serviceSource);
    }
}
