<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class AdminAuthMiddleware
{
    public function __invoke(Request $request, Response $response, array $args): Request|Response
    {
        $authUser = $request->getAttribute('auth_user');

        if (!is_array($authUser)) {
            return $this->forbiddenResponse($response, 'User not authenticated');
        }

        if (!empty($authUser['is_guest'])) {
            return $this->forbiddenResponse($response, 'Guest sessions cannot perform admin actions');
        }

        $roles = $authUser['roles'] ?? [];
        if (!is_array($roles)) {
            $roles = [(string) $roles];
        }

        $role = (string) ($authUser['role'] ?? 'user');
        if ($role !== 'admin' && !in_array('admin', $roles, true)) {
            return $this->forbiddenResponse($response, 'Admin access required');
        }

        return $request;
    }

    private function forbiddenResponse(Response $response, string $message): Response
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => 'FORBIDDEN'
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(403);
    }
}
