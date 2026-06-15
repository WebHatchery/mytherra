<?php

declare(strict_types=1);

namespace Tests\Omen;

use PHPUnit\Framework\TestCase;

final class TemporalOmenWiringTest extends TestCase
{
    public function testTemporalOmenServiceProvidesNonMutatingForecasts(): void
    {
        $service = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/TemporalOmenService.php');

        self::assertStringContainsString('class TemporalOmenService', $service);
        self::assertStringContainsString("'omens'", $service);
        self::assertStringContainsString("'temporal_history'", $service);
        self::assertStringContainsString('near', $service);
        self::assertStringContainsString('generation', $service);
        self::assertStringContainsString('era', $service);
        self::assertStringContainsString('worldOmen', $service);
        self::assertStringContainsString('regionOmen', $service);
        self::assertStringContainsString('heroOmen', $service);
        self::assertStringContainsString('advanceWorld', $service);
        self::assertStringContainsString('resolveOmenFollowUp', $service);
        self::assertStringContainsString('time_omen', $service);
        self::assertStringContainsString('time_omen_followup', $service);
        self::assertStringContainsString('does not rewind, branch, or mutate world state', $service);
        self::assertStringContainsString('EraPressureService', $service);
    }

    public function testTemporalOmenRoutesStatusExportAndFrontendAreWired(): void
    {
        $routes = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Routes/router.php');
        $container = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Utils/ContainerConfig.php');
        $status = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/StatusActions.php');
        $export = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/ExportService.php');
        $api = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/api/apiService.ts');
        $page = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/pages/TemporalOmensPage.tsx');

        self::assertStringContainsString("TemporalOmenController::class, 'getOmens'", $routes);
        self::assertStringContainsString("TemporalOmenController::class, 'readOmen'", $routes);
        self::assertStringContainsString('TemporalOmenService::class', $container);
        self::assertStringContainsString("'temporalOmens' => \$this->temporalOmenService->status()", $status);
        self::assertStringContainsString("'temporalOmens' => \$temporalOmens", $export);
        self::assertStringContainsString("'omens' => 'exportTemporalOmens'", $export);
        self::assertStringContainsString('TemporalOmenStatusResponse', $api);
        self::assertStringContainsString('getTemporalOmens', $api);
        self::assertStringContainsString('readTemporalOmen', $api);
        self::assertStringContainsString('Temporal Omens', $page);
        self::assertStringContainsString('Read Omen', $page);
    }
}
