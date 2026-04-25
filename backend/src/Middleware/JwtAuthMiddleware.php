<?php

namespace App\Middleware;

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

    public function __invoke(Request $request, Response $response, array $args)
    {
        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader)) {
            return $this->unauthorizedResponse($response, 'Missing Authorization header');
        }

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->unauthorizedResponse($response, 'Invalid Authorization header format');
        }

        $token = trim((string) $matches[1]);
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if (empty($secret)) {
             return $this->unauthorizedResponse($response, 'Server configuration error');
        }

        JWT::$leeway = 31536000;

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            $decodedArray = (array) $decoded;
            $roles = $decodedArray['roles'] ?? ['user'];
            $primaryRole = is_array($roles) ? ($roles[0] ?? 'user') : $roles;
            $authType = $decodedArray['auth_type'] ?? 'frontpage';
            $isGuest = !empty($decodedArray['is_guest']) || $authType === 'guest';
            $authUser = [
                'user_id' => $decodedArray['sub'] ?? $decodedArray['user_id'] ?? null,
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
            return $this->unauthorizedResponse($response, 'User creation failed: ' . $e->getMessage());
        }
    }

    private function unauthorizedResponse(Response $response, string $message = 'Unauthorized'): Response
    {
        $portalBaseUrl = rtrim((string) ($_ENV['AUTH_PORTAL_BASE_URL'] ?? ''), '/');
        $loginUrl = $_ENV['WEB_HATCHERY_LOGIN_URL'] ?? ($portalBaseUrl !== '' ? $portalBaseUrl . '/login' : '');

        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHORIZED',
            'login_url' => $loginUrl
        ]));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
