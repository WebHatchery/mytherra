<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Environment;
use App\Core\Response;
use App\Core\Request;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthController
{
    public function getLoginInfo(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'login_url' => Environment::required('WEB_HATCHERY_LOGIN_URL'),
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getCurrentUser(Request $request, Response $response): Response
    {
        $authUser = $request->getAttribute('auth_user');
        $localUser = $request->getAttribute('user');

        if (!$authUser || !$localUser) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'User not authenticated']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'user' => $this->serializeUser($localUser, $authUser)
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function createGuestSession(Request $request, Response $response): Response
    {
        $jwtSecret = Environment::required('JWT_SECRET');

        $guestExternalId = 'guest_' . bin2hex(random_bytes(16));
        $guestUser = User::createOrUpdateFromAuthData([
            'user_id' => $guestExternalId,
            'username' => 'guest_' . substr(bin2hex(random_bytes(4)), 0, 8),
            'display_name' => 'Guest Oracle',
            'auth_type' => 'guest',
            'is_guest' => true,
            'role' => 'guest',
            'email' => '',
        ]);

        $now = time();
        $claims = [
            'iat' => $now,
            'nbf' => $now - 5,
            'exp' => $now + (60 * 60 * 24 * 365),
            'jti' => bin2hex(random_bytes(16)),
            'sub' => $guestExternalId,
            'user_id' => $guestExternalId,
            'username' => $guestUser->auth_username ?: $guestUser->username,
            'display_name' => $guestUser->display_name ?: 'Guest Oracle',
            'email' => '',
            'role' => 'guest',
            'roles' => ['guest'],
            'auth_type' => 'guest',
            'is_guest' => true,
        ];

        $token = JWT::encode($claims, $jwtSecret, 'HS256');
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Guest session created',
            'data' => [
                'token' => $token,
                'user' => $this->serializeUser($guestUser, [
                    'role' => 'guest',
                    'roles' => ['guest'],
                    'is_guest' => true,
                    'auth_type' => 'guest',
                    'user_id' => $guestExternalId,
                ])
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function linkGuestAccount(Request $request, Response $response): Response
    {
        $authUser = $request->getAttribute('auth_user');
        $localUser = $request->getAttribute('user');
        if (!$authUser || !$localUser) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'User not authenticated']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }

        if (($authUser['role'] ?? 'user') === 'admin') {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Guest accounts cannot be linked to admin accounts']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        if (!empty($authUser['is_guest'])) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Linking requires a signed-in non-guest account']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $payload = json_decode((string) $request->getBody(), true) ?: [];
        $guestToken = trim((string) ($payload['guest_token'] ?? ''));
        if ($guestToken === '') {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Invalid guest_token']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $decodedGuest = JWT::decode($guestToken, new Key(Environment::required('JWT_SECRET'), 'HS256'));
            $guestClaims = (array) $decodedGuest;
        } catch (\Throwable $exception) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Invalid guest token']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $guestUserId = (string) ($guestClaims['sub'] ?? $guestClaims['user_id'] ?? '');
        if ($guestUserId === '' || !str_starts_with($guestUserId, 'guest_') || empty($guestClaims['is_guest'])) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Guest token is not a guest session']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $guestUser = User::where('auth0_id', $guestUserId)->first();
        if (!$guestUser) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Guest account not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $movedByTable = [
            'divine_bets' => 0,
            'divine_influence_actions' => 0,
            'users' => 0,
        ];

        \Illuminate\Database\Capsule\Manager::connection()->transaction(function () use ($guestUser, $localUser, $guestUserId, &$movedByTable) {
            $movedByTable['divine_bets'] = \Illuminate\Database\Capsule\Manager::table('divine_bets')
                ->where('player_id', (string) $guestUser->id)
                ->update(['player_id' => (string) $localUser->id]);

            if (\Illuminate\Database\Capsule\Manager::schema()->hasTable('divine_influence_actions')) {
                $movedByTable['divine_influence_actions'] = \Illuminate\Database\Capsule\Manager::table('divine_influence_actions')
                    ->where('player_id', (string) $guestUser->id)
                    ->update(['player_id' => (string) $localUser->id]);
            }

            $localUser->divine_influence = (int) $localUser->divine_influence + (int) $guestUser->divine_influence;
            $localUser->divine_favor = (int) $localUser->divine_favor + (int) $guestUser->divine_favor;
            $localUser->experience = (int) $localUser->experience + (int) $guestUser->experience;
            $localUser->level = max((int) $localUser->level, (int) $guestUser->level);
            $localUser->betting_stats = array_merge($guestUser->betting_stats ?? [], $localUser->betting_stats ?? []);
            $localUser->game_preferences = array_merge($guestUser->game_preferences ?? [], $localUser->game_preferences ?? []);
            $localUser->save();

            $movedByTable['users'] = $guestUser->delete() ? 1 : 0;
        });

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Guest account data linked successfully',
            'data' => [
                'guest_user_id' => $guestUserId,
                'linked_to_user_id' => (string) $localUser->id,
                'moved_rows_by_table' => $movedByTable,
                'total_moved_rows' => array_sum($movedByTable),
            ]
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function updatePreferences(Request $request, Response $response): Response
    {
        $localUser = $request->getAttribute('user');
        if (!$localUser) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'User not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $body = json_decode((string) $request->getBody(), true);
        $preferences = $body['preferences'] ?? [];
        if (!is_array($preferences)) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Invalid preferences format']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $localUser->updateGamePreferences($preferences);
        $response->getBody()->write(json_encode(['success' => true, 'message' => 'Preferences updated successfully', 'data' => ['preferences' => $localUser->game_preferences]]));
        return $response->withHeader('Content-Type', 'application/json');
    }
    private function serializeUser(User $user, array $authUser): array
    {
        $isGuest = (bool) ($authUser['is_guest'] ?? false) || (($authUser['auth_type'] ?? 'frontpage') === 'guest');
        return [
            'id' => $user->id,
            'auth_user_id' => $user->auth_user_id,
            'email' => $user->auth_email,
            'username' => $user->auth_username,
            'display_name' => $user->display_name,
            'divine_influence' => $user->divine_influence,
            'divine_favor' => $user->divine_favor,
            'betting_stats' => $user->betting_stats ?? [],
            'game_preferences' => $user->game_preferences ?? [],
            'role' => $isGuest ? 'guest' : ($authUser['role'] ?? 'user'),
            'roles' => $authUser['roles'] ?? ($isGuest ? ['guest'] : ['user']),
            'is_active' => $user->is_active ?? true,
            'is_guest' => $isGuest,
            'auth_type' => $isGuest ? 'guest' : 'frontpage',
            'guest_user_id' => $isGuest ? ($authUser['user_id'] ?? null) : null,
        ];
    }
}
