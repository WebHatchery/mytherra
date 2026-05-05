<?php

namespace App\Models;

use Exception;
use App\Utils\Logger;
use App\Repositories\DatabaseService;

class LocationInteractionConfig
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
            // Create region_interaction_types table
            self::$db->exec("
                CREATE TABLE IF NOT EXISTS region_interaction_types (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(50) UNIQUE NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    description TEXT,
                    base_duration INT NOT NULL,
                    base_success_chance DECIMAL(4,2) NOT NULL,
                    influence_cost INT NOT NULL DEFAULT 0,
                    cooldown_hours INT NOT NULL DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");

    // Create location_type_interactions table (specific interactions for each location type)
            self::$db->exec("
                CREATE TABLE IF NOT EXISTS location_type_interactions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    location_type VARCHAR(50) NOT NULL,
                    interaction_code VARCHAR(50) NOT NULL,
                    success_modifier DECIMAL(4,2) NOT NULL DEFAULT 1.0,
                    duration_modifier DECIMAL(4,2) NOT NULL DEFAULT 1.0,
                    min_hero_level INT NOT NULL DEFAULT 1,
                    required_trait VARCHAR(50),
                    trait_bonus DECIMAL(4,2) DEFAULT 0.1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_location_interaction (location_type, interaction_code)
                )
            ");

    // Create region_status_modifiers table
            self::$db->exec("
                CREATE TABLE IF NOT EXISTS region_status_modifiers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    status_code VARCHAR(50) NOT NULL,
                    interaction_code VARCHAR(50) NOT NULL,
                    success_modifier DECIMAL(4,2) NOT NULL DEFAULT 1.0,
                    duration_modifier DECIMAL(4,2) NOT NULL DEFAULT 1.0,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_status_interaction (status_code, interaction_code)
                )
            ");

            return true;
        } catch (\PDOException $e) {
            Logger::error("Error creating location interaction configuration tables: " . $e->getMessage());
            throw $e;
        }
    }

    public static function seedData()
    {
        self::init();
        try {
            // Seed interaction types
            $interactionTypes = [
                [
                    'code' => 'explore',
                    'name' => 'Explore Area',
                    'description' => 'Search the area for resources and discoveries',
                    'base_duration' => 4,
                    'base_success_chance' => 0.7,
                    'influence_cost' => 0,
                    'cooldown_hours' => 1
                ],
                [
                    'code' => 'investigate_rumors',
                    'name' => 'Investigate Rumors',
                    'description' => 'Look into local stories and legends',
                    'base_duration' => 6,
                    'base_success_chance' => 0.6,
                    'influence_cost' => 5,
                    'cooldown_hours' => 24
                ],
                [
                    'code' => 'establish_camp',
                    'name' => 'Establish Camp',
                    'description' => 'Set up a temporary base in the area',
                    'base_duration' => 8,
                    'base_success_chance' => 0.8,
                    'influence_cost' => 10,
                    'cooldown_hours' => 48
                ],
                [
                    'code' => 'gather_resources',
                    'name' => 'Gather Resources',
                    'description' => 'Collect local resources and materials',
                    'base_duration' => 3,
                    'base_success_chance' => 0.9,
                    'influence_cost' => 0,
                    'cooldown_hours' => 2
                ],
                [
                    'code' => 'study_magic',
                    'name' => 'Study Magic',
                    'description' => 'Research magical properties of the area',
                    'base_duration' => 12,
                    'base_success_chance' => 0.5,
                    'influence_cost' => 15,
                    'cooldown_hours' => 72
                ]
            ];

            $stmt = self::$db->prepare("
                INSERT INTO region_interaction_types 
                (code, name, description, base_duration, base_success_chance, influence_cost, cooldown_hours)
                VALUES 
                (:code, :name, :description, :base_duration, :base_success_chance, :influence_cost, :cooldown_hours)
            ");

            foreach ($interactionTypes as $type) {
                $stmt->execute($type);
            }

    // Seed location type interactions
            $locationInteractions = [
                // Wilderness interactions
                [
                    'location_type' => 'wilderness',
                    'interaction_code' => 'explore',
                    'success_modifier' => 0.8,
                    'duration_modifier' => 1.2,
                    'min_hero_level' => 1,
                    'required_trait' => 'adventurous',
                    'trait_bonus' => 0.2
                ],
                [
                    'location_type' => 'wilderness',
                    'interaction_code' => 'gather_resources',
                    'success_modifier' => 1.2,
                    'duration_modifier' => 0.8,
                    'min_hero_level' => 1,
                    'required_trait' => 'resourceful',
                    'trait_bonus' => 0.15
                ],

                // Settlement interactions
                [
                    'location_type' => 'settlement',
                    'interaction_code' => 'investigate_rumors',
                    'success_modifier' => 1.3,
                    'duration_modifier' => 0.7,
                    'min_hero_level' => 2,
                    'required_trait' => 'charismatic',
                    'trait_bonus' => 0.25
                ],
                [
                    'location_type' => 'settlement',
                    'interaction_code' => 'establish_camp',
                    'success_modifier' => 1.5,
                    'duration_modifier' => 0.6,
                    'min_hero_level' => 3,
                    'required_trait' => 'diplomatic',
                    'trait_bonus' => 0.2
                ],

                // Magical location interactions
                [
                    'location_type' => 'magical',
                    'interaction_code' => 'study_magic',
                    'success_modifier' => 1.4,
                    'duration_modifier' => 0.9,
                    'min_hero_level' => 5,
                    'required_trait' => 'mystical',
                    'trait_bonus' => 0.3
                ],
                [
                    'location_type' => 'magical',
                    'interaction_code' => 'explore',
                    'success_modifier' => 0.7,
                    'duration_modifier' => 1.3,
                    'min_hero_level' => 3,
                    'required_trait' => 'cautious',
                    'trait_bonus' => 0.2
                ]
            ];

            $stmt = self::$db->prepare("
                INSERT INTO location_type_interactions 
                (location_type, interaction_code, success_modifier, duration_modifier, 
                min_hero_level, required_trait, trait_bonus)
                VALUES 
                (:location_type, :interaction_code, :success_modifier, :duration_modifier,
                :min_hero_level, :required_trait, :trait_bonus)
            ");

            foreach ($locationInteractions as $interaction) {
                $stmt->execute($interaction);
            }

    // Seed region status modifiers
            $statusModifiers = [
                [
                    'status_code' => 'peaceful',
                    'interaction_code' => 'explore',
                    'success_modifier' => 1.2,
                    'duration_modifier' => 0.8,
                    'description' => 'Peaceful regions are easier to explore'
                ],
                [
                    'status_code' => 'chaotic',
                    'interaction_code' => 'establish_camp',
                    'success_modifier' => 0.7,
                    'duration_modifier' => 1.3,
                    'description' => 'Chaotic regions make camping difficult'
                ],
                [
                    'status_code' => 'blessed',
                    'interaction_code' => 'study_magic',
                    'success_modifier' => 1.4,
                    'duration_modifier' => 0.7,
                    'description' => 'Blessed regions enhance magical study'
                ],
                [
                    'status_code' => 'corrupted',
                    'interaction_code' => 'gather_resources',
                    'success_modifier' => 0.6,
                    'duration_modifier' => 1.4,
                    'description' => 'Corrupted regions yield fewer resources'
                ]
            ];

            $stmt = self::$db->prepare("
                INSERT INTO region_status_modifiers 
                (status_code, interaction_code, success_modifier, duration_modifier, description)
                VALUES 
                (:status_code, :interaction_code, :success_modifier, :duration_modifier, :description)
            ");

            foreach ($statusModifiers as $modifier) {
                $stmt->execute($modifier);
            }

            return true;
        } catch (\PDOException $e) {
            Logger::error("Error seeding location interaction configuration data: " . $e->getMessage());
            throw $e;
        }
    }

    public static function getInteractionTypes()
    {
        self::init();
        $stmt = self::$db->query("SELECT * FROM region_interaction_types WHERE 1");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getLocationTypeInteractions($locationType)
    {
        self::init();
        $stmt = self::$db->prepare("
            SELECT * FROM location_type_interactions 
            WHERE location_type = :location_type
        ");
        $stmt->execute(['location_type' => $locationType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getRegionStatusModifiers($statusCode = null)
    {
        self::init();
        if ($statusCode) {
            $stmt = self::$db->prepare("
                SELECT * FROM region_status_modifiers 
                WHERE status_code = :status_code
            ");
            $stmt->execute(['status_code' => $statusCode]);
        } else {
            $stmt = self::$db->query("SELECT * FROM region_status_modifiers WHERE 1");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
