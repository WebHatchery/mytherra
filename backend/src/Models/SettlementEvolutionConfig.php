<?php

declare(strict_types=1);

namespace App\Models;

use Exception;
use App\Utils\Logger;
use App\Repositories\DatabaseService;
use App\Models\EvolutionParameter;

class SettlementEvolutionConfig
{
    private static $dbService = null;

    private static function init()
    {
        if (self::$dbService === null) {
            self::$dbService = DatabaseService::getInstance();
        }
    }

    public static function createTable()
    {
        self::init();
        try {
            // Create evolution parameters table
            EvolutionParameter::createTable();

    // Create settlement evolution thresholds table
            self::$dbService->query("
                CREATE TABLE IF NOT EXISTS settlement_evolution_thresholds (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    settlement_type VARCHAR(50) NOT NULL,
                    next_type VARCHAR(50) NOT NULL,
                    min_population INT NOT NULL,
                    min_prosperity INT NOT NULL,
                    min_influence INT NOT NULL,
                    required_buildings TEXT,
                    evolution_time_days INT NOT NULL,
                    divine_favor_cost INT NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_type (settlement_type)
                )
            ");

    // Create prosperity factors table
            self::$dbService->query("
                CREATE TABLE IF NOT EXISTS settlement_prosperity_factors (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    factor_code VARCHAR(50) UNIQUE NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    description TEXT,
                    base_impact DECIMAL(4,2) NOT NULL,
                    impact_interval_hours INT NOT NULL,
                    stack_type ENUM('additive', 'multiplicative') DEFAULT 'additive',
                    max_stacks INT DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");

    // Create growth modifiers table
            self::$dbService->query("
                CREATE TABLE IF NOT EXISTS settlement_growth_modifiers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    modifier_type VARCHAR(50) NOT NULL,
                    condition_type VARCHAR(50) NOT NULL,
                    condition_value VARCHAR(50) NOT NULL,
                    population_modifier DECIMAL(4,2) DEFAULT 1.0,
                    prosperity_modifier DECIMAL(4,2) DEFAULT 1.0,
                    influence_modifier DECIMAL(4,2) DEFAULT 1.0,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_type_condition (modifier_type, condition_type)
                )
            ");

    // Create settlement specializations table
            self::$dbService->query("
                CREATE TABLE IF NOT EXISTS settlement_specializations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(50) UNIQUE NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    description TEXT,
                    base_prosperity_bonus DECIMAL(4,2) DEFAULT 0,
                    base_population_bonus DECIMAL(4,2) DEFAULT 0,
                    weight INT DEFAULT 10,
                    requirements JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");

    // Create settlement traits table
            self::$dbService->query("
                CREATE TABLE IF NOT EXISTS settlement_traits (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(50) UNIQUE NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    description TEXT,
                    base_defensibility_bonus DECIMAL(4,2) DEFAULT 0,
                    base_prosperity_bonus DECIMAL(4,2) DEFAULT 0,
                    weight INT DEFAULT 10,
                    biome_restrictions JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");

    // Create evolution types table
            self::$dbService->query("
                CREATE TABLE IF NOT EXISTS settlement_evolution_types (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(50) UNIQUE NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    description TEXT,
                    base_weight INT DEFAULT 10,
                    prosperity_threshold INT,
                    prosperity_modifier DECIMAL(4,2),
                    population_modifier DECIMAL(4,2),
                    defensibility_modifier DECIMAL(4,2),
                    regional_requirements JSON,
                    settlement_requirements JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");

            return true;
        } catch (Exception $e) {
            Logger::error("Error creating settlement evolution configuration tables: " . $e->getMessage());
            throw $e;
        }
    }

    public static function seedData()
    {
        self::init();
        try {
            $data = \App\InitData\SettlementEvolutionData::getData();

    // Seed evolution parameters
            EvolutionParameter::truncate();
            foreach ($data['parameters'] as $param) {
                EvolutionParameter::create($param);
            }

    // Seed evolution thresholds
            foreach ($data['thresholds'] as $threshold) {
                $stmt = self::$dbService->prepare("
                    INSERT INTO settlement_evolution_thresholds 
                    (settlement_type, next_type, min_population, min_prosperity, min_influence,
                    required_buildings, evolution_time_days, divine_favor_cost)
                    VALUES 
                    (:settlement_type, :next_type, :min_population, :min_prosperity, :min_influence,
                    :required_buildings, :evolution_time_days, :divine_favor_cost)
                ");
                $stmt->execute($threshold);
            }

    // Seed specializations
            self::$dbService->query("TRUNCATE TABLE settlement_specializations");
            foreach ($data['specializations'] as $spec) {
                $stmt = self::$dbService->prepare("
                    INSERT INTO settlement_specializations 
                    (code, name, description, base_prosperity_bonus, base_population_bonus, 
                    weight, requirements)
                    VALUES 
                    (:code, :name, :description, :base_prosperity_bonus, :base_population_bonus,
                    :weight, :requirements)
                ");
                $stmt->execute($spec);
            }

    // Seed traits
            self::$dbService->query("TRUNCATE TABLE settlement_traits");
            foreach ($data['traits'] as $trait) {
                $stmt = self::$dbService->prepare("
                    INSERT INTO settlement_traits 
                    (code, name, description, base_defensibility_bonus, base_prosperity_bonus,
                    weight, biome_restrictions)
                    VALUES 
                    (:code, :name, :description, :base_defensibility_bonus, :base_prosperity_bonus,
                    :weight, :biome_restrictions)
                ");
                $stmt->execute($trait);
            }

    // Seed evolution types
            self::$dbService->query("TRUNCATE TABLE settlement_evolution_types");
            foreach ($data['evolution_types'] as $type) {
                $stmt = self::$dbService->prepare("
                    INSERT INTO settlement_evolution_types 
                    (code, name, description, base_weight, prosperity_threshold,
                    prosperity_modifier, population_modifier, defensibility_modifier,
                    regional_requirements, settlement_requirements)
                    VALUES 
                    (:code, :name, :description, :base_weight, :prosperity_threshold,
                    :prosperity_modifier, :population_modifier, :defensibility_modifier,
                    :regional_requirements, :settlement_requirements)
                ");
                $stmt->execute($type);
            }

            return true;
        } catch (Exception $e) {
            Logger::error("Error seeding settlement evolution configuration data: " . $e->getMessage());
            throw $e;
        }
    }

    public static function getEvolutionThresholds($settlementType = null)
    {
        self::init();
        if ($settlementType) {
            $stmt = self::$dbService->prepare(
                "SELECT * FROM settlement_evolution_thresholds WHERE settlement_type = :type"
            );
            $stmt->execute(['type' => $settlementType]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        }

        $stmt = self::$dbService->prepare(
            "SELECT * FROM settlement_evolution_thresholds ORDER BY min_population ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getProsperityFactors()
    {
        self::init();
        $stmt = self::$dbService->prepare("SELECT * FROM settlement_prosperity_factors WHERE 1");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getGrowthModifiers($modifierType = null, $conditionType = null)
    {
        self::init();
        $sql = "SELECT * FROM settlement_growth_modifiers WHERE 1";
        $params = [];

        if ($modifierType) {
            $sql .= " AND modifier_type = :modifier_type";
            $params[':modifier_type'] = $modifierType;
        }

        if ($conditionType) {
            $sql .= " AND condition_type = :condition_type";
            $params[':condition_type'] = $conditionType;
        }

        $stmt = self::$dbService->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getSpecializations()
    {
        self::init();
        $stmt = self::$dbService->prepare("SELECT * FROM settlement_specializations ORDER BY weight DESC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getTraits()
    {
        self::init();
        $stmt = self::$dbService->prepare("SELECT * FROM settlement_traits ORDER BY weight DESC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function getEvolutionTypes()
    {
        self::init();
        $stmt = self::$dbService->prepare("SELECT * FROM settlement_evolution_types ORDER BY base_weight DESC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
