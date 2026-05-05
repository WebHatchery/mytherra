<?php

namespace App\Services;

use App\Models\GameConfig;
use Ramsey\Uuid\Uuid;

/**
 * Game Configuration Service
 * Manages retrieving and caching game configuration values
 */
class GameConfigService
{
    private static $instance = null;
    private static $cache = [];
    private const CACHE_PREFIX = 'game_config:';

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get a configuration value by category and key
     */
    public function getConfig(string $category, string $key, $defaultValue = null)
    {
        $cacheKey = self::CACHE_PREFIX . "{$category}.{$key}";

    // Try to get from cache first
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

    // Not in cache, get from database
        try {
            $config = GameConfig::where('category', $category)
                ->where('key', $key)
                ->first();

            if ($config) {
                $value = $this->getTypedValue($config);
                self::$cache[$cacheKey] = $value;
                return $value;
            }
        } catch (\Exception $error) {
            error_log("Error fetching config {$category}.{$key}: " . $error->getMessage());
        }

    // Return default value if provided
        if ($defaultValue !== null) {
            self::$cache[$cacheKey] = $defaultValue;
            return $defaultValue;
        }

        throw new \Exception("Configuration not found: {$category}.{$key}");
    }

    /**
     * Get a configuration value directly with a single key
     * Format: "category.key"
     */
    public function getValue(string $configKey, $defaultValue = null)
    {
        $parts = explode('.', $configKey);
        if (count($parts) !== 2) {
            throw new \Exception("Invalid config key format: {$configKey}. Expected format: \"category.key\"");
        }

        return $this->getConfig($parts[0], $parts[1], $defaultValue);
    }

    /**
     * Get all configurations for a category
     */
    public function getCategoryConfig(string $category): array
    {
        try {
            $configs = GameConfig::where('category', $category)->get();

            $result = [];
            foreach ($configs as $config) {
                $key = $config->key;
                $value = $this->getTypedValue($config);
                $result[$key] = $value;

    // Update cache
                $cacheKey = self::CACHE_PREFIX . "{$category}.{$key}";
                self::$cache[$cacheKey] = $value;
            }

            return $result;
        } catch (\Exception $error) {
            error_log("Error fetching category config {$category}: " . $error->getMessage());
            return [];
        }
    }

    /**
     * Set a configuration value
     */
    public function setConfig(string $category, string $key, $value, string $dataType, ?string $description = null): void
    {
        try {
            // Convert value to string for storage
            $stringValue = $this->convertValueToString($value, $dataType);

            GameConfig::updateOrCreate(
                [
                    'category' => $category,
                    'key' => $key
                ],
                [
                    'id' => Uuid::uuid4()->toString(),
                    'value' => $stringValue,
                    'data_type' => $dataType,
                    'description' => $description
                ]
            );

    // Update cache
            $cacheKey = self::CACHE_PREFIX . "{$category}.{$key}";
            self::$cache[$cacheKey] = $value;
        } catch (\Exception $error) {
            error_log("Error setting config {$category}.{$key}: " . $error->getMessage());
            throw $error;
        }
    }

    /**
     * Clear the configuration cache
     */
    public function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Refresh cache from database
     */
    public function refreshCache(): void
    {
        try {
            $this->clearCache();

            $allConfigs = GameConfig::all();
            foreach ($allConfigs as $config) {
                $cacheKey = self::CACHE_PREFIX . "{$config->category}.{$config->key}";
                $value = $this->getTypedValue($config);
                self::$cache[$cacheKey] = $value;
            }
        } catch (\Exception $error) {
            error_log('Error refreshing config cache: ' . $error->getMessage());
        }
    }

    /**
     * Convert a value from string to its proper type based on data_type
     */
    private function getTypedValue(GameConfig $config)
    {
        switch ($config->data_type) {
            case 'number':
                return is_numeric($config->value) ? (float)$config->value : 0;
            case 'boolean':
                return filter_var($config->value, FILTER_VALIDATE_BOOLEAN);
            case 'array':
                return json_decode($config->value, true) ?? [];
            default:
                return $config->value;
        }
    }

    /**
     * Convert a value to string for storage
     */
    private function convertValueToString($value, string $dataType): string
    {
        switch ($dataType) {
            case 'array':
                return json_encode($value);
            case 'boolean':
            case 'number':
                return (string)$value;
            default:
                return $value;
        }
    }
}
