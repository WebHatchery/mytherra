<?php

declare(strict_types=1);

namespace Tests\Era;

use PHPUnit\Framework\TestCase;

final class EraPressureWiringTest extends TestCase
{
    private string $serviceSource;

    protected function setUp(): void
    {
        $servicePath = dirname(__DIR__, 2) . '/src/Services/EraPressureService.php';
        $serviceSource = file_get_contents($servicePath);
        self::assertIsString($serviceSource);
        $this->serviceSource = $serviceSource;
    }

    public function testEraPressureServiceDefinesRoadmapTriggersFromWorldState(): void
    {
        self::assertStringContainsString('class EraPressureService', $this->serviceSource);
        foreach (['cataclysm', 'collapse', 'conquest', 'magical_rupture', 'divine_war'] as $triggerCode) {
            self::assertStringContainsString("'{$triggerCode}'", $this->serviceSource);
        }

        foreach (['Region::all()', 'Settlement::all()', 'Hero::all()', 'Landmark::all()', 'ResourceNode::all()'] as $source) {
            self::assertStringContainsString($source, $this->serviceSource);
        }

        self::assertStringContainsString('InfluenceHistory::where', $this->serviceSource);
        self::assertStringContainsString("DivineBet::where('status', 'active')", $this->serviceSource);
        self::assertStringContainsString('Player::getSinglePlayer()', $this->serviceSource);
    }

    public function testStatusTickStatisticsAndExportExposeEraPressure(): void
    {
        $statusActions = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/StatusActions.php');
        $gameLoop = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/GameLoopService.php');
        $statistics = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/StatisticsService.php');
        $export = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/ExportService.php');

        self::assertStringContainsString("\$eraPressure = \$this->eraPressureService->evaluate", $statusActions);
        self::assertStringContainsString("'eraPressure' => \$eraPressure", $statusActions);
        self::assertStringContainsString("'eraPressure' => null", $gameLoop);
        self::assertStringContainsString("\$result['eraPressure'] = \$this->eraPressureService->evaluate", $gameLoop);
        self::assertStringContainsString('recordEraPressureEventIfNeeded', $gameLoop);
        self::assertStringContainsString("'era_pressure'", $gameLoop);
        self::assertStringContainsString('EraPressureService::currentEraForYear', $statistics);
        self::assertStringContainsString("'eraPressure' => \$eraPressure", $export);
    }
}
