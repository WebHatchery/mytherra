<?php

declare(strict_types=1);

namespace Tests\Admin;

use PHPUnit\Framework\TestCase;

final class AdminWorldEditorWiringTest extends TestCase
{
    public function testAdminWorldEditorBackendIsAdminOnlyAndCoversPrimaryEntities(): void
    {
        $routes = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Routes/router.php');
        $container = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Utils/ContainerConfig.php');
        $service = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/AdminWorldEditorService.php');
        $controller = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Controllers/AdminWorldEditorController.php');
        $actions = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/AdminWorldEditorActions.php');

        self::assertStringContainsString('AdminWorldEditorController::class', $routes);
        self::assertStringContainsString("get(\$api . '/admin/world-editor'", $routes);
        self::assertStringContainsString("post(\$api . '/admin/world-editor/{entityType}'", $routes);
        self::assertStringContainsString("put(\$api . '/admin/world-editor/{entityType}/{id}'", $routes);
        self::assertMatchesRegularExpression(
            "/admin\\/world-editor'.*\\\$admin\\);/s",
            $routes
        );
        self::assertStringContainsString('AdminWorldEditorService::class', $container);
        self::assertStringContainsString('AdminWorldEditorActions::class', $container);
        self::assertStringContainsString('AdminWorldEditorController::class', $container);
        self::assertStringContainsString('handleApiAction', $controller);
        self::assertStringContainsString('AdminWorldEditorService', $actions);

        foreach (['regions', 'settlements', 'landmarks', 'resources', 'heroes'] as $entityType) {
            self::assertStringContainsString("'{$entityType}'", $service);
        }

        self::assertStringContainsString('recordEditEvent', $service);
        self::assertStringContainsString('admin_world_edit', $service);
        self::assertStringContainsString('requiredRegionId', $service);
        self::assertStringContainsString('must be between', $service);
    }
}
