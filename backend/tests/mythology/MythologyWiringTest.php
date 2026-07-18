<?php

declare(strict_types=1);

namespace Tests\Mythology;

use PHPUnit\Framework\TestCase;

final class MythologyWiringTest extends TestCase
{
    public function testMythologyServicePromotesEventsIntoDurableMyths(): void
    {
        $service = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/MythologyService.php');
        $gameLoop = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/GameLoopService.php');

        self::assertStringContainsString('class MythologyService', $service);
        self::assertStringContainsString("'mythology'", $service);
        self::assertStringContainsString("'myth_index'", $service);
        self::assertStringContainsString('mythCandidates', $service);
        self::assertStringContainsString('promote', $service);
        self::assertStringContainsString('advanceWorld', $service);
        self::assertStringContainsString('evolveMythIfReady', $service);
        self::assertStringContainsString('applyMythEffects', $service);
        self::assertStringContainsString('applyMythEchoEffects', $service);
        self::assertStringContainsString('myth_promoted', $service);
        self::assertStringContainsString('myth_echo', $service);
        self::assertStringContainsString('ECHO_COOLDOWN_YEARS', $service);
        self::assertStringContainsString('evolutionSummary', $service);
        self::assertStringContainsString('recentEchoes', $service);
        self::assertStringContainsString('autonomousEvolution', $service);
        self::assertStringContainsString('mythic_memory', $service);
        self::assertStringContainsString('myth_echo_', $service);
        self::assertStringContainsString('Mythic Reputation:', $service);
        self::assertStringContainsString('mythic_anchor', $service);
        self::assertStringContainsString('magic_discovery', $service);
        self::assertStringContainsString('divine_influence', $service);
        self::assertStringContainsString('artifact_created', $service);
        self::assertStringContainsString('weather_influence', $service);
        self::assertStringContainsString('EventRepository', $service);
        self::assertStringContainsString("'mythology' => ['processed' => 0", $gameLoop);
        self::assertStringContainsString('mythologyService->advanceWorld', $gameLoop);
        self::assertStringContainsString('myth echoes', $gameLoop);
        self::assertStringContainsString('mythic_tradition', $gameLoop);
        self::assertStringContainsString("str_starts_with((string)\$regionalTrait, 'myth_')", $gameLoop);
    }

    public function testMythologyRoutesStatusExportAndFrontendAreWired(): void
    {
        $routes = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Routes/router.php');
        $container = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Utils/ContainerConfig.php');
        $status = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/StatusActions.php');
        $export = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/ExportService.php');
        $api = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/api/apiService.ts');
        $page = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/pages/MythologyPage.tsx');
        $dashboardLastTick = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/components/DashboardLastTickPanel.tsx');

        self::assertStringContainsString("MythologyController::class, 'getMyths'", $routes);
        self::assertStringContainsString("MythologyController::class, 'promoteMyth'", $routes);
        self::assertStringContainsString('MythologyService::class', $container);
        self::assertStringContainsString("'mythology' => \$this->mythologyService->status()", $status);
        self::assertStringContainsString('$this->mythologyService', $status);
        self::assertStringContainsString("'mythology' => \$mythology", $export);
        self::assertStringContainsString("'myths' => 'exportMythology'", $export);
        self::assertStringContainsString("'myth_echo'", $export);
        self::assertStringContainsString('MythologyStatusResponse', $api);
        self::assertStringContainsString('MythEcho', $api);
        self::assertStringContainsString('GameTickMythologySummary', $api);
        self::assertStringContainsString('normalizeMythEcho', $api);
        self::assertStringContainsString('getMythology', $api);
        self::assertStringContainsString('promoteMyth', $api);
        self::assertStringContainsString('Mythology', $page);
        self::assertStringContainsString('Promote Myth', $page);
        self::assertStringContainsString('Candidate Legends', $page);
        self::assertStringContainsString('Recent Myth Echoes', $page);
        self::assertStringContainsString('Autonomous Evolution', $page);
        self::assertStringContainsString('Latest Myth Echo', $page);
        self::assertStringContainsString('Myth Echoes', $dashboardLastTick);
        self::assertStringContainsString('tick.mythology', $dashboardLastTick);
    }
}
