<?php

declare(strict_types=1);

namespace App\Utils;

class Utils
{
    private static bool $isTestMode;

    public static function isTestMode(): bool
    {
        if (!isset(self::$isTestMode)) {
            self::$isTestMode = defined('TEST_MODE') && TEST_MODE === true;
        }
        return self::$isTestMode;
    }

    public static function debugLog(string $message): void
    {
        if (!self::isTestMode()) {
            error_log("[DEBUG] " . $message);
        }
    }
}
