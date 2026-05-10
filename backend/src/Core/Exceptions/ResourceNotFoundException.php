<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

class ResourceNotFoundException extends \Exception
{
    public function __construct(string $message = "Resource not found", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
