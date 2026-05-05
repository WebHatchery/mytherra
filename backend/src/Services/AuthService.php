<?php

namespace App\Services;

use App\Core\Environment;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthService
{
    private string $jwtSecret;

    public function __construct()
    {
        $this->jwtSecret = Environment::required('JWT_SECRET');
    }

    public function validateToken(string $token): array
    {
        if (empty($this->jwtSecret)) {
            throw new \Exception('Server configuration error: JWT secret not set');
        }

        try {
            JWT::$leeway = 31536000;

            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            $decodedArray = (array) $decoded;

            $roles = $decodedArray['roles'] ?? ['user'];
            $primaryRole = is_array($roles) ? ($roles[0] ?? 'user') : $roles;
            $authType = $decodedArray['auth_type'] ?? 'frontpage';
            $isGuest = !empty($decodedArray['is_guest']) || $authType === 'guest';

            return [
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
            throw new \Exception('Invalid token: ' . $e->getMessage());
        }
    }

    public function syncUser(array $authUser)
    {
        if (!class_exists(User::class) || !method_exists(User::class, 'createOrUpdateFromAuthData')) {
            throw new \Exception('User model not found or incompatible');
        }

        return User::createOrUpdateFromAuthData($authUser);
    }
}
