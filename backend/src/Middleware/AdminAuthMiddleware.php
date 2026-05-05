<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class AdminAuthMiddleware
{
    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Request|Response
     */
    public function __invoke(Request $request, Response $response, array $args)
    {
        $user = $request->getAttribute('user');

        if (!$user) {
            return $this->forbiddenResponse($response, 'User not authenticated');
        }

    // Check if user has admin role
        // This depends on your User model structure. Assuming 'role' attribute or similar.
        // Mytherra User model might have getRole() or public property.
        // Let's assume standardized array access from JwtAuthMiddleware 'user' attribute usually is an array or object?
        // JwtAuthMiddleware sets 'user' as result of createOrUpdateLocalUser (likely an array or User model).

        $role = null;
        if (is_array($user)) {
            $role = $user['role'] ?? 'user';
        } elseif (is_object($user)) {
             $role = $user->role ?? 'user';
        }

        if ($role !== 'admin') {
            return $this->forbiddenResponse($response, 'Admin access required');
        }

    // Return request to continue
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
