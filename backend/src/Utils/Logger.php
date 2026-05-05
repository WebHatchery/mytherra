<?php

namespace App\Utils;

class Logger
{
    /**
     * Log debug message
     */
    public static function debug($message, array $context = [])
    {
        self::log('DEBUG', $message, $context);
    }

    /**
     * Log error message
     */
    public static function error($message, array $context = [])
    {
        self::log('ERROR', $message, $context);
    }

    /**
     * Log warning message
     */
    public static function warning($message, array $context = [])
    {
        self::log('WARNING', $message, $context);
    }

    /**
     * Log info message
     */
    public static function info($message, array $context = [])
    {
        self::log('INFO', $message, $context);
    }

    /**
     * Format log message with context
     */
    private static function log(string $level, string $message, array $context = [])
    {
        $formattedMessage = self::formatMessage($message, $context);
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[{$timestamp}] [{$level}] {$formattedMessage}";

    // Log to system error log
        error_log($logLine);

    // Optional: could write to specific file here if configured
    }

    /**
     * Format log message with context
     */
    private static function formatMessage($message, array $context = []): string
    {
        if (empty($context)) {
            return $message;
        }

        $replace = [];
        foreach ($context as $key => $val) {
            if (is_string($val)) {
                $replace['{' . $key . '}'] = $val;
            } elseif (is_object($val) && method_exists($val, '__toString')) {
                $replace['{' . $key . '}'] = $val->__toString();
            } elseif (is_array($val)) {
                $replace['{' . $key . '}'] = json_encode($val);
            } else {
                $replace['{' . $key . '}'] = var_export($val, true);
            }
        }

        return strtr($message, $replace);
    }
}
