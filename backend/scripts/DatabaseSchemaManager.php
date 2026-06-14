<?php

namespace App\Scripts;

use App\Repositories\DatabaseService;

/**
 * Manages database schema creation and table dependencies
 */
class DatabaseSchemaManager
{
    private DatabaseService $db;

    public function __construct()
    {
        $this->db = DatabaseService::getInstance();
    }

    /**
     * Create the database if it doesn't exist and clear existing data
     */
    public function initializeDatabase(): void
    {
        echo "Creating database if it doesn't exist...\n";
        $this->db->createDatabaseIfNotExists();

        echo "Clearing existing database...\n";
        $this->db->clearDatabase(true); // true means DROP tables instead of TRUNCATE
    }

    /**
     * Create all database tables in the correct order
     */
    public function createTables(): void
    {
        echo "Creating database tables...\n";
        
        $this->loadModelFiles();
        $this->createLookupTables();
        $this->createEntityTables();
        
        echo "✅ Database structure initialized\n";
    }

    /**
     * Load all required model files
     */
    private function loadModelFiles(): void
    {
        // Load lookup/reference table models
        $lookupModels = [
            'HeroRole', 'HeroDeathReason', 'HeroEventMessage',
            'EvolutionParameter', 'SettlementEvolutionConfig', 'SettlementType',
            'SettlementStatus', 'SettlementTypeConfig', 'BuildingTypeConfig',
            'BuildingType', 'BuildingStatus', 'BuildingConditionLevel',
            'BuildingSpecialProperty', 'LandmarkType', 'LandmarkStatus',
            'ResourceNodeType', 'ResourceNodeStatus', 'RegionStatus',
            'RegionClimateType', 'RegionCulturalInfluence',
            'HeroSettlementInteractionType', 'BetConfig', 'BetTargetModifier'
        ];        foreach ($lookupModels as $model) {
            require_once __DIR__ . "/../src/models/{$model}.php";
        }

        // Load main entity models
        $entityModels = [
            'GameEvent', 'GameState', 'Player', 'InfluenceHistory',
            'Region', 'Hero', 'Settlement', 'Building', 'Landmark',
            'ResourceNode', 'DivineBet', 'HeroSettlementInteraction', 'QueueTables'
        ];        foreach ($entityModels as $model) {
            require_once __DIR__ . "/../src/models/{$model}.php";
        }
    }

    /**
     * Create lookup tables in correct dependency order
     */
    private function createLookupTables(): void
    {
        echo "Creating lookup tables...\n";
        
        $lookupTables = [
            // First create base types needed by other configurations
            'HeroRole',
            'HeroDeathReason', 
            'HeroEventMessage',
            'SettlementType',
            'SettlementStatus',
            
            // Region lookup tables (needed before Region table creation)
            'RegionStatus',
            'RegionClimateType', 
            'RegionCulturalInfluence',
            
            // Hero interaction lookup tables
            'HeroSettlementInteractionType',
            
            // Then create configs that depend on types
            'SettlementTypeConfig',
            'BuildingTypeConfig',
            'BuildingType',
            'BuildingStatus',
            'BuildingConditionLevel',
            'BuildingSpecialProperty',
            'LandmarkType',
            'LandmarkStatus',
            'ResourceNodeType',
            'ResourceNodeStatus',
            
            // Finally create evolution config which depends on types and configs
            'EvolutionParameter',
            'SettlementEvolutionConfig',
            
            // Betting system tables
            'BetConfig',
            'BetTargetModifier'
        ];

        foreach ($lookupTables as $model) {
            echo "Creating lookup table for {$model}...\n";
            $className = "\\App\\Models\\{$model}";
            $className::createTable();
        }
    }

    /**
     * Create main entity tables in correct dependency order
     */
    private function createEntityTables(): void
    {
        echo "Creating main entity tables...\n";
        
        $entityTables = [
            'GameState',    // Add GameState first as it's needed for initialization
            'Player',       // Add Player as it's also needed for initialization
            'InfluenceHistory', // Add InfluenceHistory for tracking divine influences
            'Region',       // Move Region before GameEvent since GameEvent references regions
            'Settlement',   // Move Settlement before Building since Building references settlements
            'Hero',         
            'GameEvent',    // Move GameEvent after Region since it references regions
            'Building',     // Move Building after Settlement since it references settlements
            'Landmark',
            'ResourceNode', 
            'DivineBet', 
            'HeroSettlementInteraction',
            'QueueTables'
        ];

        foreach ($entityTables as $model) {
            echo "Creating entity table for {$model}...\n";
            $className = "\\App\\Models\\{$model}";
            $className::createTable();
        }
    }
}
