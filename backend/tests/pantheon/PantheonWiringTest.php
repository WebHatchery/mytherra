<?php

declare(strict_types=1);

namespace Tests\Pantheon;

use PHPUnit\Framework\TestCase;

final class PantheonWiringTest extends TestCase
{
    public function testPantheonServiceAppliesAutonomousDivinePressure(): void
    {
        $service = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/PantheonService.php');
        $gameLoop = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/GameLoopService.php');
        $mythology = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/MythologyService.php');
        $eraLegacy = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/EraLegacyService.php');

        self::assertStringContainsString('class PantheonService', $service);
        self::assertStringContainsString("'pantheon'", $service);
        self::assertStringContainsString("'state'", $service);
        self::assertStringContainsString('advanceWorld', $service);
        self::assertStringContainsString('pressureForDeity', $service);
        self::assertStringContainsString('applyProsperity', $service);
        self::assertStringContainsString('applyStrife', $service);
        self::assertStringContainsString('applySecrets', $service);
        self::assertStringContainsString('applyEntropy', $service);
        self::assertStringContainsString('pantheon_intervention', $service);
        self::assertStringContainsString('counterplay', $service);
        self::assertStringContainsString('COUNTERPLAY_COSTS', $service);
        self::assertStringContainsString('pantheon_counterplay', $service);
        self::assertStringContainsString('bettingHooks', $service);
        self::assertStringContainsString('politics', $service);
        self::assertStringContainsString('spendDivineFavor', $service);
        self::assertStringContainsString('PantheonService', $gameLoop);
        self::assertStringContainsString("'pantheon' =>", $gameLoop);
        self::assertStringContainsString('pantheonService->advanceWorld($tickYear, 1)', $gameLoop);
        self::assertStringContainsString('pantheon interventions', $gameLoop);
        self::assertStringContainsString('pantheon_intervention', $mythology);
        self::assertStringContainsString('pantheon_counterplay', $mythology);
        self::assertStringContainsString('pantheon_intervention', $eraLegacy);
        self::assertStringContainsString('pantheon_counterplay', $eraLegacy);
        self::assertStringContainsString('divine_intervention', $eraLegacy);
    }

    public function testPantheonRoutesStatusExportDashboardAndFrontendAreWired(): void
    {
        $routes = (string)file_get_contents(dirname(__DIR__, 2) . '/src/routes/router.php');
        $container = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Utils/ContainerConfig.php');
        $status = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/StatusActions.php');
        $export = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/ExportService.php');
        $dashboard = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/pages/Dashboard.tsx');
        $lastTick = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/components/DashboardLastTickPanel.tsx');
        $api = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/api/apiService.ts');
        $page = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/pages/PantheonPage.tsx');
        $entity = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/entities/pantheon.ts');

        self::assertStringContainsString("PantheonController::class, 'getPantheon'", $routes);
        self::assertStringContainsString("PantheonController::class, 'counterplay'", $routes);
        self::assertStringContainsString('PantheonService::class', $container);
        self::assertStringContainsString('PantheonActions::class', $container);
        self::assertStringContainsString('PantheonController::class', $container);
        self::assertStringContainsString("'pantheon' => \$this->pantheonService->status()", $status);
        self::assertStringContainsString("'pantheon' => \$pantheon", $export);
        self::assertStringContainsString("'pantheon' => 'exportPantheon'", $export);
        self::assertStringContainsString('DashboardPantheonPanel', $dashboard);
        self::assertStringContainsString('Pantheon Interventions', $lastTick);
        self::assertStringContainsString('PantheonStatusResponse', $api);
        self::assertStringContainsString('PantheonTickSummary', $api);
        self::assertStringContainsString('getPantheon', $api);
        self::assertStringContainsString('counterplayPantheon', $api);
        self::assertStringContainsString('normalizePantheonStatusResponse', $api);
        self::assertStringContainsString('normalizePantheonCounterplayResponse', $api);
        self::assertStringContainsString('AI Pantheon', $page);
        self::assertStringContainsString('Pantheon Pressure', $page);
        self::assertStringContainsString('Divine Actors', $page);
        self::assertStringContainsString('Player Counterplay', $page);
        self::assertStringContainsString('Appease', $page);
        self::assertStringContainsString('Challenge', $page);
        self::assertStringContainsString('Recent Interventions', $page);
        self::assertStringContainsString('Pantheon Betting Hooks', $page);
        self::assertStringContainsString('PantheonPoliticsStatus', $entity);
        self::assertStringContainsString('PantheonBettingHook', $entity);
        self::assertStringContainsString('PantheonIntervention', $entity);
        self::assertStringContainsString('PantheonCounterplayStatus', $entity);
    }

    public function testPantheonInterventionBettingIsWired(): void
    {
        $divineBet = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Models/DivineBet.php');
        $bettingActions = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/BettingActions.php');
        $gameLoop = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/GameLoopService.php');
        $odds = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/OddsCalculationService.php');
        $migration = (string)file_get_contents(dirname(__DIR__, 2) . '/migrations/004_add_pantheon_intervention_bet_type.php');
        $initSql = (string)file_get_contents(dirname(__DIR__, 2) . '/migrations/001_database_init_prod.sql');
        $api = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/api/apiService.ts');
        $page = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/pages/PantheonPage.tsx');
        $divineBetEntity = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/entities/divineBet.ts');

        self::assertStringContainsString('pantheon_intervention', $divineBet);
        self::assertStringContainsString('PantheonService', $bettingActions);
        self::assertStringContainsString('pantheon_intervention', $bettingActions);
        self::assertStringContainsString('bettingHooks', $bettingActions);
        self::assertStringContainsString("case 'pantheon_intervention'", $gameLoop);
        self::assertStringContainsString('recentPantheonInterventionForRegion', $gameLoop);
        self::assertStringContainsString('pantheonInterventionOddsModifier', $gameLoop);
        self::assertStringContainsString('calculatePantheonInterventionModifier', $odds);
        self::assertStringContainsString('pantheon_intervention', $migration);
        self::assertStringContainsString('pantheon_intervention', $initSql);
        self::assertStringContainsString('PantheonBettingHook', $api);
        self::assertStringContainsString('normalizePantheonBettingHook', $api);
        self::assertStringContainsString('Pantheon Betting Hooks', $page);
        self::assertStringContainsString('pantheon_intervention', $divineBetEntity);
    }
}
