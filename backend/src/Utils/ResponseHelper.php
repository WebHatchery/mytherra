<?php

declare(strict_types=1);

namespace App\Utils;

class ResponseHelper
{
    public static function success($data = null, int $status = 200): array
    {
        $response = [
            'success' => true,
            'timestamp' => date('c')
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return $response;
    }

    public static function error(string $message, string $code = 'ERROR', $details = null, int $status = 400): array
    {
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code,
                'timestamp' => date('c')
            ]
        ];

        if ($details !== null) {
            $response['error']['details'] = $details;
        }

        return $response;
    }

    public static function validationError(array $errors): array
    {
        return self::error(
            'Validation failed',
            'VALIDATION_ERROR',
            $errors,
            400
        );
    }

    public static function notFound(string $resource = 'Resource'): array
    {
        return self::error(
            "{$resource} not found",
            'NOT_FOUND',
            null,
            404
        );
    }
}
