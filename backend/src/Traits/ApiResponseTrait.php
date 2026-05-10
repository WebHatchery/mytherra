<?php

declare(strict_types=1);

namespace App\Traits;

use App\Core\Response;
use App\Core\Exceptions\ResourceNotFoundException;

trait ApiResponseTrait
{
    /**
     * Send a JSON response
     */
    protected function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * Handle API action with consistent error handling
     *
     * @param Response $response The Response object
     * @param callable $action The action to execute
     * @param string $errorContext Context for general error messages
     * @param string|null $notFoundMessage Custom message for ResourceNotFoundException
     * @param int $successStatus HTTP status code for successful response
     */
    protected function handleApiAction(
        Response $response,
        callable $action,
        string $errorContext,
        ?string $notFoundMessage = null,
        int $successStatus = 200
    ): Response {        try {
            $result = $action();
            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $result
            ], $successStatus);
    } catch (\InvalidArgumentException $error) {
        // Return 400 for validation errors
        return $this->jsonResponse($response, [
            'success' => false,
            'message' => $error->getMessage(),
            'error_code' => 'VALIDATION_ERROR'
        ], 400);
    } catch (\Exception $error) {
        // Return 500 only for actual system errors
        error_log("Error {$errorContext}: " . $error->getMessage());
        return $this->jsonResponse($response, [
            'success' => false,
            'message' => "An error occurred while {$errorContext}"
        ], 500);
    }
    }
}
