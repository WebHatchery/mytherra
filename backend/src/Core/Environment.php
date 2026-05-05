<?php

declare(strict_types=1);

namespace App\Core;

final class Environment
{
    public static function required(string $key): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if (!is_string($value) || trim($value) === '') {
            throw new \RuntimeException(sprintf('Required environment variable %s is not configured.', $key));
        }

        return $value;
    }

    public static function optional(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }
}
