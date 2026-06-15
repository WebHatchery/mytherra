<?php

declare(strict_types=1);

namespace Tests\Magic;

use PHPUnit\Framework\TestCase;

final class MagicDiscoveryWiringTest extends TestCase
{
    public function testMagicDiscoveryServiceTracksResearchPathsAndDurableOutcomes(): void
    {
        $service = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/MagicDiscoveryService.php');

        self::assertStringContainsString('class MagicDiscoveryService', $service);
        self::assertStringContainsString("'magic'", $service);
        self::assertStringContainsString("'discovery_state'", $service);
        self::assertStringContainsString('ley_weaving', $service);
        self::assertStringContainsString('spirit_compacts', $service);
        self::assertStringContainsString('ruin_script', $service);
        self::assertStringContainsString('storm_rites', $service);
        self::assertStringContainsString('civic_enchantment', $service);
        self::assertStringContainsString('hidden', $service);
        self::assertStringContainsString('emerging', $service);
        self::assertStringContainsString('known', $service);
        self::assertStringContainsString('magic_research', $service);
        self::assertStringContainsString('magic_discovery', $service);
        self::assertStringContainsString('applyDurableDiscovery', $service);
        self::assertStringContainsString('bettingHooks', $service);
        self::assertStringContainsString('pathsForTarget', $service);
        self::assertStringContainsString("'betType' => 'magic_discovery'", $service);
        self::assertStringContainsString('EventRepository', $service);
    }

    public function testMagicDiscoveryRoutesStatusExportBettingResolutionAndFrontendAreWired(): void
    {
        $routes = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Routes/router.php');
        $container = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Utils/ContainerConfig.php');
        $status = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/StatusActions.php');
        $export = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/ExportService.php');
        $betting = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/BettingActions.php');
        $betModel = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Models/DivineBet.php');
        $loop = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/GameLoopService.php');
        $odds = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/OddsCalculationService.php');
        $api = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/api/apiService.ts');
        $betEntity = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/entities/divineBet.ts');
        $page = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/pages/MagicDiscoveryPage.tsx');

        self::assertStringContainsString("MagicDiscoveryController::class, 'getMagic'", $routes);
        self::assertStringContainsString("MagicDiscoveryController::class, 'researchMagic'", $routes);
        self::assertStringContainsString('MagicDiscoveryService::class', $container);
        self::assertStringContainsString("'magicDiscovery' => \$this->magicDiscoveryService->status()", $status);
        self::assertStringContainsString("'magicDiscovery' => \$magicDiscovery", $export);
        self::assertStringContainsString("'magic' => 'exportMagicDiscovery'", $export);
        self::assertStringContainsString('MagicDiscoveryService', $betting);
        self::assertStringContainsString('magic-discovery-', $betting);
        self::assertStringContainsString('magic_discovery', $betting);
        self::assertStringContainsString("'magic_discovery'", $betModel);
        self::assertStringContainsString("case 'magic_discovery'", $loop);
        self::assertStringContainsString('evaluateMagicDiscoveryBet', $loop);
        self::assertStringContainsString('calculateMagicDiscoveryModifier', $odds);
        self::assertStringContainsString('MagicDiscoveryStatusResponse', $api);
        self::assertStringContainsString('getMagicDiscovery', $api);
        self::assertStringContainsString('researchMagic', $api);
        self::assertStringContainsString("'magic_discovery'", $betEntity);
        self::assertStringContainsString('Magic Discovery', $page);
        self::assertStringContainsString('Research Magic', $page);
        self::assertStringContainsString('Betting Hooks', $page);
    }
}
