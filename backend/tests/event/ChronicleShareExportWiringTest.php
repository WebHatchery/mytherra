<?php

declare(strict_types=1);

namespace Tests\Event;

use PHPUnit\Framework\TestCase;

final class ChronicleShareExportWiringTest extends TestCase
{
    public function testChronicleShareExportIsCuratedAndAuthenticated(): void
    {
        $routes = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Routes/router.php');
        $controller = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Controllers/ExportController.php');
        $service = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/ExportService.php');
        $dashboard = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/pages/Dashboard.tsx');
        $app = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/App.tsx');
        $sharePage = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/pages/ChronicleSharePage.tsx');
        $replayPanel = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/components/DashboardChronicleReplayPanel.tsx');
        $api = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/api/apiService.ts');

        self::assertStringContainsString(
            "get(\$api . '/export/chronicle-share'",
            $routes
        );
        self::assertStringContainsString(
            "get(\$api . '/export/chronicle-replay'",
            $routes
        );
        self::assertStringContainsString(
            "post(\$api . '/export/chronicle-share/public'",
            $routes
        );
        self::assertStringContainsString(
            "get(\$api . '/export/chronicle-share/public'",
            $routes
        );
        self::assertStringContainsString(
            "delete(\$api . '/export/chronicle-share/public/{shareId}'",
            $routes
        );
        self::assertStringContainsString(
            "get(\$api . '/public/chronicle-share/{shareId}'",
            $routes
        );
        self::assertMatchesRegularExpression(
            "/export\\/chronicle-share'.*\\\$auth\\);/",
            $routes
        );
        self::assertMatchesRegularExpression(
            "/export\\/chronicle-share\\/public'.*\\\$auth\\);/",
            $routes
        );
        self::assertMatchesRegularExpression(
            "/export\\/chronicle-replay'.*\\\$auth\\);/",
            $routes
        );
        self::assertMatchesRegularExpression(
            "/public\\/chronicle-share\\/\\{shareId\\}'\\s*,\\s*\\[ExportController::class, 'getPublicChronicleShare'\\]\\);/",
            $routes
        );
        self::assertStringContainsString('exportChronicleShare', $controller);
        self::assertStringContainsString('exportChronicleReplay', $controller);
        self::assertStringContainsString('publishChronicleShare', $controller);
        self::assertStringContainsString('listPublicChronicleShares', $controller);
        self::assertStringContainsString('revokePublicChronicleShare', $controller);
        self::assertStringContainsString('authUser($request)', $controller);
        self::assertStringContainsString('getPublicChronicleShare', $controller);
        self::assertStringContainsString('mytherra-chronicle-share.json', $controller);
        self::assertStringContainsString('chronicleReplay', $controller);
        self::assertStringContainsString("'chronicle' => 'Curated share package", $controller);

        foreach (
            [
            'packageType',
            'chronicle_share',
            'chronicle_replay',
            'exportChronicleReplay',
            'replayBeatSummary',
            'shareText',
            'entitySpotlight',
            'bettingHighlights',
            'timelineUrl',
            'topEventTypes',
            'supportsScrub',
            'CHRONICLE_MAJOR_TYPES',
            'publishChronicleShare',
            'getPublicChronicleShare',
            'listPublicChronicleShares',
            'revokePublicChronicleShare',
            'public_chronicle_shares',
            'PUBLIC_SHARE_RETENTION_DAYS',
            'shareGovernance',
            'sharePolicySummary',
            'visibilityStatus',
            'expiresAt',
            'revokedAt',
            'revokedBy',
            'policySummary',
            'GameConfigService::getInstance()',
            'shareUrl',
            'createdBy',
            'canRevoke',
            'isAdminUser',
            ] as $expected
        ) {
            self::assertStringContainsString($expected, $service);
        }

        self::assertStringContainsString('ChronicleSharePackage', $api);
        self::assertStringContainsString('ChronicleShareGovernance', $api);
        self::assertStringContainsString('PublishedChronicleShareResponse', $api);
        self::assertStringContainsString('publishChronicleShare', $api);
        self::assertStringContainsString('getPublicChronicleShare', $api);
        self::assertStringContainsString('getChronicleShareManagement', $api);
        self::assertStringContainsString('revokeChronicleShare', $api);
        self::assertStringContainsString('ChronicleShareManagementResponse', $api);
        self::assertStringContainsString('ChronicleShareRevokeResponse', $api);
        self::assertStringContainsString('export/chronicle-share/public', $api);
        self::assertStringContainsString('public/chronicle-share', $api);
        self::assertStringContainsString('ChronicleReplayResponse', $api);
        self::assertStringContainsString('getChronicleReplay', $api);
        self::assertStringContainsString('publishChronicleShare({ limit: 40 })', $dashboard);
        self::assertStringContainsString('getChronicleShareManagement', $dashboard);
        self::assertStringContainsString('revokeChronicleShare', $dashboard);
        self::assertStringContainsString('Create Share Page', $dashboard);
        self::assertStringContainsString('Chronicle Share Links', $dashboard);
        self::assertStringContainsString('shareStatusLabel', $dashboard);
        self::assertStringContainsString('Active public link', $dashboard);
        self::assertStringContainsString('Policy:', $dashboard);
        self::assertStringContainsString('Revoke', $dashboard);
        self::assertStringContainsString('/chronicle-share/:shareId', $app);
        self::assertStringContainsString('ChronicleSharePage', $app);
        self::assertStringContainsString('Shared Chronicle', $sharePage);
        self::assertStringContainsString('getPublicChronicleShare', $sharePage);
        self::assertStringContainsString('Share Policy', $sharePage);
        self::assertStringContainsString('Public Chronicle Replay', $sharePage);
        self::assertStringContainsString('setActiveReplayIndex', $sharePage);
        self::assertStringContainsString('Running Context', $sharePage);
        self::assertStringContainsString('Replay Themes', $sharePage);
        self::assertStringContainsString('Betting Highlights', $sharePage);
        self::assertStringContainsString('Timeline', $sharePage);
        self::assertStringContainsString('DashboardChronicleReplayPanel', $dashboard);
        self::assertStringContainsString('getChronicleReplay({ limit: 16 })', $dashboard);
        self::assertStringContainsString('Chronicle Replay', $replayPanel);
        self::assertStringContainsString('setActiveIndex', $replayPanel);
        self::assertStringContainsString('Open event', $replayPanel);
    }
}
