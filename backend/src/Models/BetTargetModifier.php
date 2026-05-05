<?php

namespace App\Models;

use PDO;
use Exception;
use App\Utils\Logger;
use App\Repositories\DatabaseService;

class BetTargetModifier
{
    private static $db = null;    private static function init()
    {
        if (self::$db === null) {
            self::$db = DatabaseService::getInstance();
        }
    }

    public static function createTable()
    {
        self::init();
        try {            // Create bet_target_modifiers table for conditional modifiers
            self::$db->query("
                CREATE TABLE IF NOT EXISTS bet_target_modifiers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    target_type VARCHAR(50) NOT NULL,
                    bet_type VARCHAR(50) NOT NULL,
                    condition_field VARCHAR(50) NOT NULL,
                    condition_value DECIMAL(8,2) NOT NULL,
                    comparison_operator VARCHAR(10) NOT NULL,
                    modifier_value DECIMAL(4,2) NOT NULL,
                    modifier_type ENUM('multiply', 'add') DEFAULT 'multiply',
                    description TEXT,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_target_bet_type (target_type, bet_type),
                    INDEX idx_condition_field (condition_field)
                )
            ");
            return true;
        } catch (\PDOException $e) {
            Logger::error("Error creating bet_target_modifiers table: " . $e->getMessage());
            throw $e;
        }
    }

    public static function getTargetTypeModifiers($targetType, $betType)
    {
        self::init();
        try {
            $stmt = self::$db->prepare("
                SELECT * FROM bet_target_modifiers 
                WHERE target_type = :target_type 
                AND bet_type = :bet_type 
                AND is_active = TRUE
            ");

            $stmt->execute([
                'target_type' => $targetType,
                'bet_type' => $betType
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            Logger::error("Error fetching target type modifiers: " . $e->getMessage());
            throw $e;
        }
    }
}
