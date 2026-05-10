<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Core\Request;
use App\Core\Response;
use App\Middleware\AdminAuthMiddleware;
use PHPUnit\Framework\TestCase;

final class AdminAuthMiddlewareTest extends TestCase
{
    public function testAllowsAdminRoleFromAuthClaims(): void
    {
        $request = $this->requestWithAuthUser([
            'role' => 'admin',
            'roles' => ['admin'],
            'is_guest' => false,
        ]);

        $result = (new AdminAuthMiddleware())($request, new Response(), []);

        self::assertInstanceOf(Request::class, $result);
    }

    public function testRejectsNonAdminRole(): void
    {
        $request = $this->requestWithAuthUser([
            'role' => 'user',
            'roles' => ['user'],
            'is_guest' => false,
        ]);

        $result = (new AdminAuthMiddleware())($request, new Response(), []);

        self::assertInstanceOf(Response::class, $result);
        self::assertSame(403, $result->getStatusCode());
        self::assertStringContainsString('Admin access required', (string) $result->getBody());
    }

    public function testRejectsGuestSessions(): void
    {
        $request = $this->requestWithAuthUser([
            'role' => 'guest',
            'roles' => ['guest'],
            'is_guest' => true,
        ]);

        $result = (new AdminAuthMiddleware())($request, new Response(), []);

        self::assertInstanceOf(Response::class, $result);
        self::assertSame(403, $result->getStatusCode());
        self::assertStringContainsString('Guest sessions cannot perform admin actions', (string) $result->getBody());
    }

    /**
     * @param array<string, mixed> $authUser
     */
    private function requestWithAuthUser(array $authUser): Request
    {
        return (new Request('GET', '/'))->withAttribute('auth_user', $authUser);
    }
}
