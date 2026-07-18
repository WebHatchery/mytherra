<?php

declare(strict_types=1);

namespace Tests\Era;

use PHPUnit\Framework\TestCase;

final class EraLegacyWiringTest extends TestCase
{
    private string $serviceSource;

    protected function setUp(): void
    {
        $servicePath = dirname(__DIR__, 2) . '/src/Services/EraLegacyService.php';
        $serviceSource = file_get_contents($servicePath);
        self::assertIsString($serviceSource);
        $this->serviceSource = $serviceSource;
    }

    public function testEraLegacyServiceForecastsContinuityFromWorldState(): void
    {
        self::assertStringContainsString('class EraLegacyService', $this->serviceSource);
        foreach (
            [
            'heroLegacies',
            'bloodlineSeeds',
            'landmarkLegacies',
            'worldScars',
            'carriedMyths',
            'eraSpanningBets',
            ] as $legacyKey
        ) {
            self::assertStringContainsString("'{$legacyKey}'", $this->serviceSource);
        }

        foreach (
            [
            'Hero::all()',
            'Landmark::all()',
            'Region::all()',
            'ResourceNode::all()',
            'Settlement::all()',
            'GameEvent::orderBy',
            "DivineBet::where('status', 'active')",
            ] as $source
        ) {
            self::assertStringContainsString($source, $this->serviceSource);
        }
    }

    public function testStatusTickAndExportExposeEraLegacy(): void
    {
        $statusActions = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/StatusActions.php');
        $gameLoop = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/GameLoopService.php');
        $export = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/ExportService.php');

        self::assertStringContainsString('EraLegacyService', $statusActions);
        self::assertStringContainsString("\$eraLegacy = \$this->eraLegacyService->evaluate", $statusActions);
        self::assertStringContainsString("'eraLegacy' => \$eraLegacy", $statusActions);
        self::assertStringContainsString('private ?EraLegacyService $eraLegacyService = null', $gameLoop);
        self::assertStringContainsString("'eraLegacy' => null", $gameLoop);
        self::assertStringContainsString("\$result['eraLegacy'] = \$this->eraLegacyService->evaluate", $gameLoop);
        self::assertStringContainsString("'eraLegacy' => \$eraLegacy", $export);
    }
}
