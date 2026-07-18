<?php

declare(strict_types=1);

namespace Tests\Era;

use PHPUnit\Framework\TestCase;

final class EraComparisonWiringTest extends TestCase
{
    private string $serviceSource;

    protected function setUp(): void
    {
        $servicePath = dirname(__DIR__, 2) . '/src/Services/EraComparisonService.php';
        $serviceSource = file_get_contents($servicePath);
        self::assertIsString($serviceSource);
        $this->serviceSource = $serviceSource;
    }

    public function testEraComparisonServiceBuildsSnapshotsAndDeltas(): void
    {
        self::assertStringContainsString('class EraComparisonService', $this->serviceSource);

        foreach (
            [
            'worldSnapshot',
            'compareSnapshots',
            'latestComparison',
            "'transition_history'",
            'Region::all()',
            'Settlement::all()',
            'Hero::all()',
            'Landmark::all()',
            'ResourceNode::all()',
            'DivineBet::all()',
            ] as $expected
        ) {
            self::assertStringContainsString($expected, $this->serviceSource);
        }
    }

    public function testStatusExportAndTransitionExposeEraComparison(): void
    {
        $statusActions = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/StatusActions.php');
        $transition = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/EraTransitionService.php');
        $export = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/ExportService.php');

        self::assertStringContainsString('EraComparisonService', $statusActions);
        self::assertStringContainsString("'eraComparison' => \$this->eraComparisonService->current", $statusActions);
        self::assertStringContainsString('EraComparisonService', $transition);
        self::assertStringContainsString('$beforeSnapshot = $this->eraComparisonService->worldSnapshot', $transition);
        self::assertStringContainsString('$afterSnapshot = $this->eraComparisonService->worldSnapshot', $transition);
        self::assertStringContainsString("'transitionDelta' => \$transitionDelta", $transition);
        self::assertStringContainsString("'comparisonSummary' => \$this->eraComparisonService->comparisonSummary", $transition);
        self::assertStringContainsString("'eraComparison' => \$eraComparison", $export);
    }
}
