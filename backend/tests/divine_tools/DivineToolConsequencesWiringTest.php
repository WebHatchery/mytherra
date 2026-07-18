<?php

declare(strict_types=1);

namespace Tests\DivineTools;

use PHPUnit\Framework\TestCase;

final class DivineToolConsequencesWiringTest extends TestCase
{
    public function testDivineToolConsequencesAdvanceThroughGameLoop(): void
    {
        $gameLoop = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/GameLoopService.php');
        $artifacts = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/ArtifactService.php');
        $weather = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/WeatherInfluenceService.php');
        $omens = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/TemporalOmenService.php');
        $mythology = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/MythologyService.php');
        $eraLegacy = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/EraLegacyService.php');
        $export = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/ExportService.php');
        $dashboard = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/components/DashboardLastTickPanel.tsx');
        $api = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/api/apiService.ts');

        self::assertStringContainsString('processDivineToolConsequences', $gameLoop);
        self::assertStringContainsString("'divineTools'", $gameLoop);
        self::assertStringContainsString('artifactService->advanceWorld', $gameLoop);
        self::assertStringContainsString('weatherInfluenceService->advanceWorld', $gameLoop);
        self::assertStringContainsString('temporalOmenService->advanceWorld', $gameLoop);
        self::assertStringContainsString('divine tool consequences', $gameLoop);
        self::assertStringContainsString("'chains' => []", $gameLoop);

        self::assertStringContainsString('seedArtifactChain', $artifacts);
        self::assertStringContainsString('artifact_chain', $artifacts);
        self::assertStringContainsString('seedWeatherChain', $weather);
        self::assertStringContainsString('weather_chain', $weather);
        self::assertStringContainsString('seedOmenChain', $omens);
        self::assertStringContainsString('time_omen_chain', $omens);

        self::assertStringContainsString('artifact_consequence', $mythology);
        self::assertStringContainsString('weather_consequence', $mythology);
        self::assertStringContainsString('time_omen_followup', $mythology);
        self::assertStringContainsString('artifact_chain', $mythology);
        self::assertStringContainsString('weather_chain', $mythology);
        self::assertStringContainsString('time_omen_chain', $mythology);
        self::assertStringContainsString('artifact_consequence', $eraLegacy);
        self::assertStringContainsString('weather_consequence', $eraLegacy);
        self::assertStringContainsString('time_omen_followup', $eraLegacy);
        self::assertStringContainsString('artifact_chain', $eraLegacy);
        self::assertStringContainsString('weather_chain', $eraLegacy);
        self::assertStringContainsString('time_omen_chain', $eraLegacy);
        self::assertStringContainsString('carriedMythReason', $eraLegacy);
        self::assertStringContainsString('artifact_chain', $export);
        self::assertStringContainsString('weather_chain', $export);
        self::assertStringContainsString('time_omen_chain', $export);

        self::assertStringContainsString('GameTickDivineToolsSummary', $api);
        self::assertStringContainsString('divineTools?: GameTickDivineToolsSummary', $api);
        self::assertStringContainsString('chains?: GameTickDivineToolConsequence[]', $api);
        self::assertStringContainsString('Divine Tool Consequences', $dashboard);
        self::assertStringContainsString('divineToolChainCount', $dashboard);
        self::assertStringContainsString('tick.divineTools', $dashboard);
    }
}
