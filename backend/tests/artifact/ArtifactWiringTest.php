<?php

declare(strict_types=1);

namespace Tests\Artifact;

use PHPUnit\Framework\TestCase;

final class ArtifactWiringTest extends TestCase
{
    public function testArtifactServiceStoresRiskyTraceableArtifacts(): void
    {
        $service = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/ArtifactService.php');

        self::assertStringContainsString('class ArtifactService', $service);
        self::assertStringContainsString("'artifacts'", $service);
        self::assertStringContainsString("'roster'", $service);
        self::assertStringContainsString('CREATION_COST', $service);
        self::assertStringContainsString('empower', $service);
        self::assertStringContainsString('transfer', $service);
        self::assertStringContainsString('stabilize', $service);
        self::assertStringContainsString('advanceWorld', $service);
        self::assertStringContainsString('resolveArtifactRisk', $service);
        self::assertStringContainsString('resolveWorldConsequence', $service);
        self::assertStringContainsString('artifact_created', $service);
        self::assertStringContainsString('artifact_empowered', $service);
        self::assertStringContainsString('artifact_transferred', $service);
        self::assertStringContainsString('artifact_corrupted', $service);
        self::assertStringContainsString('artifact_stolen', $service);
        self::assertStringContainsString('artifact_stabilized', $service);
        self::assertStringContainsString('artifact_consequence', $service);
        self::assertStringContainsString('EventRepository', $service);
    }

    public function testArtifactRoutesStatusExportAndFrontendAreWired(): void
    {
        $routes = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Routes/router.php');
        $status = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/StatusActions.php');
        $export = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/ExportService.php');
        $api = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/api/apiService.ts');
        $page = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/pages/ArtifactsPage.tsx');

        self::assertStringContainsString("ArtifactController::class, 'getArtifacts'", $routes);
        self::assertStringContainsString("ArtifactController::class, 'createArtifact'", $routes);
        self::assertStringContainsString("ArtifactController::class, 'empowerArtifact'", $routes);
        self::assertStringContainsString("ArtifactController::class, 'transferArtifact'", $routes);
        self::assertStringContainsString("ArtifactController::class, 'stabilizeArtifact'", $routes);
        self::assertStringContainsString("'artifacts' => \$this->artifactService->status()", $status);
        self::assertStringContainsString("'artifacts' => \$artifacts", $export);
        self::assertStringContainsString("'artifacts' => 'exportArtifacts'", $export);
        self::assertStringContainsString('ArtifactStatusResponse', $api);
        self::assertStringContainsString('createArtifact', $api);
        self::assertStringContainsString('empowerArtifact', $api);
        self::assertStringContainsString('transferArtifact', $api);
        self::assertStringContainsString('stabilizeArtifact', $api);
        self::assertStringContainsString('Divine Artifacts', $page);
    }
}
