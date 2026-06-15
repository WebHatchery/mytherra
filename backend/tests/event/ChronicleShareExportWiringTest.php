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

        self::assertStringContainsString(
            "get(\$api . '/export/chronicle-share'",
            $routes
        );
        self::assertMatchesRegularExpression(
            "/export\\/chronicle-share'.*\\\$auth\\);/",
            $routes
        );
        self::assertStringContainsString('exportChronicleShare', $controller);
        self::assertStringContainsString('mytherra-chronicle-share.json', $controller);
        self::assertStringContainsString("'chronicle' => 'Curated share package", $controller);

        foreach ([
            'packageType',
            'chronicle_share',
            'shareText',
            'entitySpotlight',
            'bettingHighlights',
            'timelineUrl',
            'topEventTypes',
            'CHRONICLE_MAJOR_TYPES',
        ] as $expected) {
            self::assertStringContainsString($expected, $service);
        }
    }
}
