<?php

namespace App\Models;

use Exception;
use App\Utils\Logger;
use App\Repositories\DatabaseService;
use App\InitData\HeroAlignmentConfigData;

class HeroAlignmentConfig
{
    private static $db = null;

    private static function init()
    {
        if (self::$db === null) {
            self::$db = DatabaseService::getInstance();
        }
    }

    public static function createTable()
    {
        self::init();
        try {
            // Create alignment_traits table
            self::$db->exec("
                CREATE TABLE IF NOT EXISTS alignment_traits (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(50) UNIQUE NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    description TEXT,
                    base_influence DECIMAL(4,2) DEFAULT 0,
                    category ENUM('personality', 'morality', 'motivation') NOT NULL,
                    opposing_trait_code VARCHAR(50),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_category (category),
                    INDEX idx_opposing (opposing_trait_code)
                )
            ");

    // Create alignment_modifiers table
            self::$db->exec("
                CREATE TABLE IF NOT EXISTS alignment_modifiers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    trigger_type VARCHAR(50) NOT NULL,
                    trigger_condition VARCHAR(50) NOT NULL,
                    trait_code VARCHAR(50) NOT NULL,
                    modifier_value DECIMAL(4,2) NOT NULL,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_trigger (trigger_type, trigger_condition),
                    INDEX idx_trait (trait_code)
                )
            ");

    // Create alignment_event_responses table
            self::$db->exec("
                CREATE TABLE IF NOT EXISTS alignment_event_responses (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_type VARCHAR(50) NOT NULL,
                    required_trait_code VARCHAR(50),
                    response_type VARCHAR(50) NOT NULL,
                    probability DECIMAL(4,2) NOT NULL,
                    influence_modifier DECIMAL(4,2) DEFAULT 1.0,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_event_trait (event_type, required_trait_code)
                )
            ");

            return true;
        } catch (\PDOException $e) {
            Logger::error("Error creating alignment configuration tables: " . $e->getMessage());
            throw $e;
        }
    }

    public static function seedData()
    {
        self::init();
        try {
            $data = HeroAlignmentConfigData::getData();

    // Seed alignment traits
            $stmt = self::$db->prepare("
                INSERT INTO alignment_traits 
                (code, name, description, base_influence, category, opposing_trait_code)
                VALUES 
                (:code, :name, :description, :base_influence, :category, :opposing_trait_code)
            ");

            foreach ($data['traits'] as $trait) {
                $stmt->execute($trait);
            }

    // Seed alignment modifiers
            $stmt = self::$db->prepare("
                INSERT INTO alignment_modifiers 
                (trigger_type, trigger_condition, trait_code, modifier_value, description)
                VALUES 
                (:trigger_type, :trigger_condition, :trait_code, :modifier_value, :description)
            ");

            foreach ($data['modifiers'] as $modifier) {
                $stmt->execute($modifier);
            }

    // Seed event responses
            $stmt = self::$db->prepare("
                INSERT INTO alignment_event_responses 
                (event_type, required_trait_code, response_type, probability, influence_modifier, description)
                VALUES 
                (:event_type, :required_trait_code, :response_type, :probability, :influence_modifier, :description)
            ");

            foreach ($data['event_responses'] as $response) {
                $stmt->execute($response);
            }

            return true;
        } catch (\PDOException $e) {
            Logger::error("Error seeding alignment configuration data: " . $e->getMessage());
            throw $e;
        }
    }

    public static function getTraits()
    {
        self::init();
        $stmt = self::$db->query("SELECT * FROM alignment_traits WHERE 1");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getModifiers($triggerType = null, $triggerCondition = null)
    {
        self::init();
        $sql = "SELECT * FROM alignment_modifiers WHERE 1";
        $params = [];

        if ($triggerType) {
            $sql .= " AND trigger_type = :trigger_type";
            $params[':trigger_type'] = $triggerType;
        }

        if ($triggerCondition) {
            $sql .= " AND trigger_condition = :trigger_condition";
            $params[':trigger_condition'] = $triggerCondition;
        }

        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getEventResponses($eventType = null, $traitCode = null)
    {
        self::init();
        $sql = "SELECT * FROM alignment_event_responses WHERE 1";
        $params = [];

        if ($eventType) {
            $sql .= " AND event_type = :event_type";
            $params[':event_type'] = $eventType;
        }

        if ($traitCode) {
            $sql .= " AND required_trait_code = :trait_code";
            $params[':trait_code'] = $traitCode;
        }

        $stmt = self::$db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
