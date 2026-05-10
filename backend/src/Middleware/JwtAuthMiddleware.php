<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Environment;
use App\Core\Request;
use App\Core\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\User;

class JwtAuthMiddleware
{
    public function __construct()
    {
    }

    public function __invoke(Request $request, Response $response, array $args): Request|Response
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader)) {
            return $this->unauthorizedResponse($response, 'Missing Authorization header');
        }

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->unauthorizedResponse($response, 'Invalid Authorization header format');
        }

        $token = trim((string) $matches[1]);
        JWT::$leeway = 60;

        try {
            $secret = Environment::required('JWT_SECRET');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            $decodedArray = (array) $decoded;
            $roles = $this->normalizeRoles($decodedArray['roles'] ?? ['user']);
            $primaryRole = $roles[0] ?? 'user';
            $authType = $decodedArray['auth_type'] ?? 'frontpage';
            $isGuest = !empty($decodedArray['is_guest']) || $authType === 'guest';
            $userId = $decodedArray['sub'] ?? $decodedArray['user_id'] ?? null;
            $authUser = [
                'id' => $userId,
                'user_id' => $userId,
                'email' => $decodedArray['email'] ?? null,
                'username' => $decodedArray['username'] ?? null,
                'display_name' => $decodedArray['display_name'] ?? ($decodedArray['username'] ?? null),
                'role' => $isGuest ? 'guest' : $primaryRole,
                'roles' => $isGuest ? ['guest'] : $roles,
                'exp' => $decodedArray['exp'] ?? null,
                'iat' => $decodedArray['iat'] ?? null,
                'auth_type' => $isGuest ? 'guest' : 'frontpage',
                'is_guest' => $isGuest,
            ];
        } catch (\Exception $e) {
            error_log('JwtAuthMiddleware: token decode failed - ' . $e->getMessage());
            return $this->unauthorizedResponse($response, 'Invalid token');
        }

        if (!$authUser || !$authUser['user_id']) {
            return $this->unauthorizedResponse($response, 'Invalid token user data');
        }

        try {
            $localUser = User::createOrUpdateFromAuthData($authUser);
            return $request
                ->withAttribute('auth_user', $authUser)
                ->withAttribute('user', $localUser);
        } catch (\Exception $e) {
            error_log('JwtAuthMiddleware: local user sync failed - ' . $e->getMessage());
            return $this->unauthorizedResponse($response, 'User synchronization failed');
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeRoles(mixed $roles): array
    {
        if (is_string($roles)) {
            return [$roles];
        }

        if (!is_array($roles)) {
            return ['user'];
        }

        $normalized = [];
        foreach ($roles as $role) {
            if (is_string($role) && $role !== '') {
                $normalized[] = $role;
            }
        }

        return $normalized === [] ? ['user'] : array_values(array_unique($normalized));
    }

    private function unauthorizedResponse(Response $response, string $message = 'Unauthorized'): Response
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHORIZED',
            'login_url' => Environment::required('WEB_HATCHERY_LOGIN_URL')
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
