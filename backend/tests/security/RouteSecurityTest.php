<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

final class RouteSecurityTest extends TestCase
{
    private string $routeSource;

    protected function setUp(): void
    {
        $routePath = dirname(__DIR__, 2) . '/src/routes/router.php';
        $routeSource = file_get_contents($routePath);
        self::assertIsString($routeSource);
        $this->routeSource = $routeSource;
    }

    public function testLegacyLocalAuthRoutesAreNotRegistered(): void
    {
        self::assertStringNotContainsString('/auth/callback', $this->routeSource);
        self::assertStringNotContainsString('/auth/logout', $this->routeSource);
        self::assertStringNotContainsString('/auth/login-url', $this->routeSource);
        self::assertStringNotContainsString('/auth/register-url', $this->routeSource);
    }

    public function testAdminMiddlewareRunsAfterJwtMiddleware(): void
    {
        self::assertStringContainsString(
            '$admin = [JwtAuthMiddleware::class, AdminAuthMiddleware::class];',
            $this->routeSource
        );
    }

    public function testSharedWorldMutationRoutesRequireAdminMiddleware(): void
    {
        $adminOnlyRoutes = [
            "post(\$api . '/regions'",
            "post(\$api . '/regions/{id}/process'",
            "post(\$api . '/buildings'",
            "put(\$api . '/buildings/{id}'",
            "delete(\$api . '/buildings/{id}'",
            "post(\$api . '/landmarks'",
            "put(\$api . '/landmarks/{id}'",
            "delete(\$api . '/landmarks/{id}'",
            "post(\$api . '/landmarks/{id}/discover'",
            "post(\$api . '/admin/process-expired-bets'",
        ];

        foreach ($adminOnlyRoutes as $routePrefix) {
            self::assertMatchesRegularExpression(
                '/' . preg_quote('$router->' . $routePrefix, '/') . '.*\$admin\);/',
                $this->routeSource,
                $routePrefix . ' must use admin middleware'
            );
        }
    }
}
