<?php

declare(strict_types=1);

namespace Tests\Civilization;

use PHPUnit\Framework\TestCase;

final class CivilizationBehaviorWiringTest extends TestCase
{
    public function testCivilizationServiceScoresAndAppliesRegionalAgendas(): void
    {
        $service = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/CivilizationBehaviorService.php');
        $gameLoop = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/GameLoopService.php');

        self::assertStringContainsString('class CivilizationBehaviorService', $service);
        self::assertStringContainsString("'civilization'", $service);
        self::assertStringContainsString("'behavior_decisions'", $service);
        self::assertStringContainsString('regionAgendas', $service);
        self::assertStringContainsString('advanceWorld', $service);
        self::assertStringContainsString('scoreExpansion', $service);
        self::assertStringContainsString('scoreDefense', $service);
        self::assertStringContainsString('scoreTrade', $service);
        self::assertStringContainsString('scoreRivalry', $service);
        self::assertStringContainsString('scoreResearch', $service);
        self::assertStringContainsString('scoreRecovery', $service);
        self::assertStringContainsString('civilization_behavior', $service);
        self::assertStringContainsString('applyExpansion', $service);
        self::assertStringContainsString('applyDefense', $service);
        self::assertStringContainsString('applyTrade', $service);
        self::assertStringContainsString('applyRivalry', $service);
        self::assertStringContainsString('applyResearch', $service);
        self::assertStringContainsString('applyRecovery', $service);
        self::assertStringContainsString('CivilizationBehaviorService', $gameLoop);
        self::assertStringContainsString("'civilization' =>", $gameLoop);
        self::assertStringContainsString('advanceWorld($tickYear, 1)', $gameLoop);
    }

    public function testCivilizationRoutesStatusExportDashboardAndFrontendAreWired(): void
    {
        $routes = (string)file_get_contents(dirname(__DIR__, 2) . '/src/routes/router.php');
        $container = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Utils/ContainerConfig.php');
        $status = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/StatusActions.php');
        $export = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/ExportService.php');
        $dashboard = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/pages/Dashboard.tsx');
        $api = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/api/apiService.ts');
        $page = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/pages/CivilizationPage.tsx');

        self::assertStringContainsString("CivilizationController::class, 'getCivilization'", $routes);
        self::assertStringContainsString("CivilizationController::class, 'advanceCivilization'", $routes);
        self::assertStringContainsString('CivilizationBehaviorService::class', $container);
        self::assertStringContainsString("'civilization' => \$this->civilizationBehaviorService->status()", $status);
        self::assertStringContainsString("'civilization' => \$civilization", $export);
        self::assertStringContainsString("'civilization' => 'exportCivilization'", $export);
        self::assertStringContainsString('DashboardCivilizationPanel', $dashboard);
        self::assertStringContainsString('CivilizationStatusResponse', $api);
        self::assertStringContainsString('getCivilization', $api);
        self::assertStringContainsString('advanceCivilization', $api);
        self::assertStringContainsString('Civilization', $page);
        self::assertStringContainsString('Advance Civic Agenda', $page);
        self::assertStringContainsString('Regional Agendas', $page);
        self::assertStringContainsString('Recent Decisions', $page);
    }
}
