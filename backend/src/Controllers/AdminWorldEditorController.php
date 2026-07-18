<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Actions\AdminWorldEditorActions;
use App\Core\Request;
use App\Core\Response;
use App\Traits\ApiResponseTrait;

class AdminWorldEditorController
{
    use ApiResponseTrait;

    public function __construct(
        private AdminWorldEditorActions $worldEditorActions
    ) {
    }

    public function getStatus(Request $request, Response $response): Response
    {
        return $this->handleApiAction(
            $response,
            fn() => $this->worldEditorActions->status(),
            'fetching admin world editor'
        );
    }

    public function createEntity(Request $request, Response $response, array $args): Response
    {
        $body = json_decode((string)$request->getBody(), true);
        if (!is_array($body)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Invalid JSON input',
                'error_code' => 'VALIDATION_ERROR'
            ], 400);
        }

        return $this->handleApiAction(
            $response,
            fn() => $this->worldEditorActions->create((string)$args['entityType'], $body),
            'creating admin world editor entity',
            null,
            201
        );
    }

    public function updateEntity(Request $request, Response $response, array $args): Response
    {
        $body = json_decode((string)$request->getBody(), true);
        if (!is_array($body)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Invalid JSON input',
                'error_code' => 'VALIDATION_ERROR'
            ], 400);
        }

        return $this->handleApiAction(
            $response,
            fn() => $this->worldEditorActions->update(
                (string)$args['entityType'],
                (string)$args['id'],
                $body
            ),
            'updating admin world editor entity'
        );
    }

    public function previewEntity(Request $request, Response $response, array $args): Response
    {
        $body = json_decode((string)$request->getBody(), true);
        if (!is_array($body)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Invalid JSON input',
                'error_code' => 'VALIDATION_ERROR'
            ], 400);
        }

        return $this->handleApiAction(
            $response,
            fn() => $this->worldEditorActions->preview((string)$args['entityType'], $body),
            'previewing admin world editor entity'
        );
    }
}
