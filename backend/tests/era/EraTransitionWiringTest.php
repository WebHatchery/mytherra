<?php

declare(strict_types=1);

namespace Tests\Era;

use PHPUnit\Framework\TestCase;

final class EraTransitionWiringTest extends TestCase
{
    private string $serviceSource;

    protected function setUp(): void
    {
        $servicePath = dirname(__DIR__, 2) . '/src/Services/EraTransitionService.php';
        $serviceSource = file_get_contents($servicePath);
        self::assertIsString($serviceSource);
        $this->serviceSource = $serviceSource;
    }

    public function testEraTransitionServiceTransformsWorldAndStoresHistory(): void
    {
        self::assertStringContainsString('class EraTransitionService', $this->serviceSource);
        foreach (
            [
            'transformRegions',
            'transformSettlements',
            'transformHeroes',
            'transformLandmarks',
            'transformResources',
            'transformBets',
            'createDescendants',
            'recordDescendantEvent',
            "'era_transition'",
            "'era_descendant'",
            "'transition_history'",
            ] as $expected
        ) {
            self::assertStringContainsString($expected, $this->serviceSource);
        }

        foreach (
            [
            'Region::all()',
            'Settlement::all()',
            'Hero::all()',
            'Landmark::all()',
            'ResourceNode::all()',
            "DivineBet::where('status', 'active')",
            'GameEvent::create',
            'GameState::getCurrent()',
            ] as $source
        ) {
            self::assertStringContainsString($source, $this->serviceSource);
        }
    }

    public function testStatusTickExportAndAdminRouteExposeEraTransition(): void
    {
        $statusActions = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/StatusActions.php');
        $statusController = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Controllers/StatusController.php');
        $gameLoop = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/GameLoopService.php');
        $export = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/ExportService.php');
        $routes = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Routes/router.php');

        self::assertStringContainsString('EraTransitionService', $statusActions);
        self::assertStringContainsString("'eraTransition' => \$this->eraTransitionService->status", $statusActions);
        self::assertStringContainsString('runEraTransition', $statusActions);
        self::assertStringContainsString('runEraTransition', $statusController);
        self::assertStringContainsString("'eraTransition' => null", $gameLoop);
        self::assertStringContainsString("\$result['eraTransition'] = \$this->eraTransitionService->status", $gameLoop);
        self::assertStringContainsString("\$this->eraTransitionService->transition(false)", $gameLoop);
        self::assertStringContainsString("'eraTransition' => \$eraTransition", $export);
        self::assertStringContainsString("post(\$api . '/admin/era/transition'", $routes);
    }
}
