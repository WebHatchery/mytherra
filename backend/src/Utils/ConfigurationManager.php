<?php

namespace App\Utils;

class ConfigurationManager
{
    /**
     * Initialize all game configurations
     */
    public static function initializeConfigurations()
    {
        // Initialize Laravel components
        LaravelBootstrap::initialize();

    // Create and seed the initial config table
        \App\Models\GameInitialConfig::createTable();
        \App\Models\GameInitialConfig::seedDefaultConfig();

    // Create the game config table
        \App\Models\GameConfig::createTable();

    // Initialize all configurations
        self::initializeFromDefaults();
    }

    /**
     * Initialize game configurations from default values
     */
    private static function initializeFromDefaults()
    {
        $initialConfigs = \App\Models\GameInitialConfig::where('is_active', true)->get();
        foreach ($initialConfigs as $config) {
            \App\Services\GameConfigService::getInstance()->setConfig(
                $config->category,
                $config->key,
                $config->value,
                $config->data_type,
                $config->description
            );
        }
    }
}
