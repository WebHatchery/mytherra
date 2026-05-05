<?php

namespace App\Helpers;

use App\Utils\Utils;

class Logger
{
    public static function debug($message)
    {
        Utils::debugLog($message);
    }

    public static function error($message)
    {
        error_log("[ERROR] " . $message);
    }

    public static function warning($message, array $context = [])
    {
        $contextStr = !empty($context) ? " Context: " . json_encode($context) : "";
        error_log("[WARNING] " . $message . $contextStr);
    }

    public static function info($message, array $context = [])
    {
        $contextStr = !empty($context) ? " Context: " . json_encode($context) : "";
        error_log("[INFO] " . $message . $contextStr);
    }
}
