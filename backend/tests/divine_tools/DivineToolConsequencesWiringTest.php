<?php

declare(strict_types=1);

namespace Tests\DivineTools;

use PHPUnit\Framework\TestCase;

final class DivineToolConsequencesWiringTest extends TestCase
{
    public function testDivineToolConsequencesAdvanceThroughGameLoop(): void
    {
        $gameLoop = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/GameLoopService.php');
        $mythology = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/MythologyService.php');
        $eraLegacy = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/EraLegacyService.php');
        $dashboard = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/components/DashboardLastTickPanel.tsx');
        $api = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/api/apiService.ts');

        self::assertStringContainsString('processDivineToolConsequences', $gameLoop);
        self::assertStringContainsString("'divineTools'", $gameLoop);
        self::assertStringContainsString('artifactService->advanceWorld', $gameLoop);
        self::assertStringContainsString('weatherInfluenceService->advanceWorld', $gameLoop);
        self::assertStringContainsString('temporalOmenService->advanceWorld', $gameLoop);
        self::assertStringContainsString('divine tool consequences', $gameLoop);

        self::assertStringContainsString('artifact_consequence', $mythology);
        self::assertStringContainsString('weather_consequence', $mythology);
        self::assertStringContainsString('time_omen_followup', $mythology);
        self::assertStringContainsString('artifact_consequence', $eraLegacy);
        self::assertStringContainsString('weather_consequence', $eraLegacy);
        self::assertStringContainsString('time_omen_followup', $eraLegacy);
        self::assertStringContainsString('carriedMythReason', $eraLegacy);

        self::assertStringContainsString('GameTickDivineToolsSummary', $api);
        self::assertStringContainsString('divineTools?: GameTickDivineToolsSummary', $api);
        self::assertStringContainsString('Divine Tool Consequences', $dashboard);
        self::assertStringContainsString('tick.divineTools', $dashboard);
    }
}
