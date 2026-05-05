<?php

namespace App\Models;

use PDO;
use App\Repositories\DatabaseService;

class BetConfig
{
    private static $db;

    // Initialize database connection
    private static function init()
    {
        if (!self::$db) {
            self::$db = DatabaseService::getInstance();
        }
    }

    // Create the betting configuration tables (alias for createTables to match other models)
    public static function createTable()
    {
        return self::createTables();
    }

    // Create the betting configuration tables
    public static function createTables()
    {
        self::init();

        try {
            // Create bet_types table
            self::$db->query("
                CREATE TABLE IF NOT EXISTS bet_types (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(50) UNIQUE NOT NULL,
                    description TEXT NOT NULL,
                    base_odds DECIMAL(4,2) NOT NULL,
                    min_timeframe INT NOT NULL DEFAULT 1,
                    max_timeframe INT NOT NULL DEFAULT 50,
                    resolve_conditions TEXT NOT NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");

    // Create confidence_levels table
            self::$db->query("
                CREATE TABLE IF NOT EXISTS confidence_levels (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(50) UNIQUE NOT NULL,
                    description TEXT NOT NULL,
                    odds_modifier DECIMAL(4,2) NOT NULL,
                    stake_multiplier DECIMAL(4,2) NOT NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");

    // Create timeframe_modifiers table
            self::$db->query("
                CREATE TABLE IF NOT EXISTS timeframe_modifiers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    max_timeframe INT NOT NULL,
                    modifier DECIMAL(4,2) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_max_timeframe (max_timeframe)
                )
            ");

    // Create betting_system_config table for other constants
            self::$db->query("
                CREATE TABLE IF NOT EXISTS betting_system_config (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(50) UNIQUE NOT NULL,
                    value DECIMAL(10,2) NOT NULL,
                    description TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");

            return true;
        } catch (\PDOException $e) {
            error_log("Error creating betting config tables: " . $e->getMessage());
            throw $e;
        }
    }

    // Get all bet types
    public static function getBetTypes()
    {
        self::init();
        return self::$db->query("SELECT * FROM bet_types WHERE is_active = TRUE")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all confidence levels
    public static function getConfidenceLevels()
    {
        self::init();
        return self::$db->query("SELECT * FROM confidence_levels WHERE is_active = TRUE")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get timeframe modifier for a specific timeframe
    public static function getTimeframeModifier($timeframe)
    {
        self::init();
        $stmt = self::$db->prepare("
            SELECT modifier 
            FROM timeframe_modifiers 
            WHERE max_timeframe >= :timeframe 
            ORDER BY max_timeframe ASC 
            LIMIT 1
        ");
        $stmt->execute(['timeframe' => $timeframe]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['modifier'] : 1.0;
    }

    // Get system config value
    public static function getSystemConfig($code)
    {
        self::init();
        $stmt = self::$db->prepare("SELECT value FROM betting_system_config WHERE code = :code");
        $stmt->execute(['code' => $code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['value'] : null;
    }
}
