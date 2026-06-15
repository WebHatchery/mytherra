<?php

namespace App\Scripts;

use App\Repositories\DatabaseService;
use App\Models\Region;
use App\Models\Hero;
use App\Models\GameEvent;
use App\Models\GameState;
use App\Models\Player;
use App\Models\Settlement;
use App\Models\Building;
use App\Models\DivineBet;
use App\InitData\ResourceNodeData;
use App\InitData\RegionData;
use App\InitData\HeroData;
use App\InitData\EventData;
use App\InitData\EvolutionParameterData;
use App\InitData\SettlementData;
use App\InitData\BettingConfigData;
use App\InitData\BetTargetModifierData;
use App\InitData\DivineBetData;
use App\InitData\BuildingData;

/**
 * Manages seeding of initial game data
 */
class GameDataSeeder
{
    private DatabaseService $db;

    public function __construct()
    {
        $this->db = DatabaseService::getInstance();
    }

    /**
     * Seed all initial game data
     */
    public function seedAllData(): void
    {
        echo "Initializing basic game data...\n";
        
        $this->seedEvolutionParameters();
        $this->seedBettingConfiguration();
        $this->seedGameEntities();
        $this->initializeGameState();
        $this->seedBuildings();
        $this->seedResourceNodes();
        $this->seedDivineBets();
        
        echo "✅ Basic game data initialized\n";
    }

    /**
     * Seed evolution parameters
     */
    private function seedEvolutionParameters(): void
    {
        echo "Seeding evolution parameters...\n";
        
        foreach (EvolutionParameterData::getData() as $paramData) {
            $param = new \App\Models\EvolutionParameter($paramData);
            $param->save();
            echo "Created evolution parameter: {$param->parameter}\n";
        }
        
        echo "Evolution parameters seeded.\n";
    }

    /**
     * Seed betting system configuration
     */
    private function seedBettingConfiguration(): void
    {
        echo "Seeding betting configuration data...\n";
        
        $this->seedBetTypes();
        $this->seedConfidenceLevels();
        $this->seedTimeframeModifiers();
        $this->seedSystemConfig();
        $this->seedBetTargetModifiers();
        
        echo "Betting configuration data seeded.\n";
    }

    /**
     * Seed bet types
     */
    private function seedBetTypes(): void
    {
        foreach (BettingConfigData::getBetTypes() as $data) {
            try {
                $stmt = $this->db->prepare("INSERT INTO bet_types 
                    (code, description, base_odds, min_timeframe, max_timeframe, resolve_conditions)
                    VALUES (:code, :description, :base_odds, :min_timeframe, :max_timeframe, :resolve_conditions)");
                $stmt->execute($data);
                echo "Created bet type: {$data['code']}\n";
            } catch (\Exception $e) {
                echo "Error creating bet type {$data['code']}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Seed confidence levels
     */
    private function seedConfidenceLevels(): void
    {
        foreach (BettingConfigData::getConfidenceLevels() as $data) {
            try {
                $stmt = $this->db->prepare("INSERT INTO confidence_levels 
                    (code, description, odds_modifier, stake_multiplier)
                    VALUES (:code, :description, :odds_modifier, :stake_multiplier)");
                $stmt->execute($data);
                echo "Created confidence level: {$data['code']}\n";
            } catch (\Exception $e) {
                echo "Error creating confidence level {$data['code']}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Seed timeframe modifiers
     */
    private function seedTimeframeModifiers(): void
    {
        foreach (BettingConfigData::getTimeframeModifiers() as $data) {
            try {
                $stmt = $this->db->prepare("INSERT INTO timeframe_modifiers 
                    (max_timeframe, modifier)
                    VALUES (:max_timeframe, :modifier)");
                $stmt->execute($data);
                echo "Created timeframe modifier for timeframe {$data['max_timeframe']}\n";
            } catch (\Exception $e) {
                echo "Error creating timeframe modifier {$data['max_timeframe']}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Seed betting system configuration
     */
    private function seedSystemConfig(): void
    {
        foreach (BettingConfigData::getSystemConfig() as $data) {
            try {
                $stmt = $this->db->prepare("INSERT INTO betting_system_config 
                    (code, value, description)
                    VALUES (:code, :value, :description)");
                $stmt->execute($data);
                echo "Created betting system config: {$data['code']}\n";
            } catch (\Exception $e) {
                echo "Error creating betting system config {$data['code']}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Seed bet target modifiers
     */
    private function seedBetTargetModifiers(): void
    {
        foreach (BetTargetModifierData::getData() as $data) {
            try {
                $stmt = $this->db->prepare("INSERT INTO bet_target_modifiers
                    (target_type, bet_type, condition_field, condition_value, comparison_operator,
                    modifier_value, modifier_type, description)
                    VALUES (:target_type, :bet_type, :condition_field, :condition_value, :comparison_operator,
                    :modifier_value, :modifier_type, :description)");
                $stmt->execute($data);
                echo "Created bet target modifier for {$data['target_type']} {$data['bet_type']}\n";
            } catch (\Exception $e) {
                echo "Error creating bet target modifier: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Seed main game entities (regions, settlements, heroes, events)
     */
    private function seedGameEntities(): void
    {
        $this->seedRegions();
        $this->seedSettlements();
        $this->seedHeroes();
        $this->seedEvents();
    }

    /**
     * Seed regions
     */
    private function seedRegions(): void
    {
        echo "Creating regions...\n";
        
        foreach (RegionData::getData() as $regionData) {
            try {
                $region = new Region($regionData);
                $region->save();
                echo "Created region: {$region->name}\n";
            } catch (\Exception $e) {
                echo "Error creating region {$regionData['id']}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Seed settlements
     */
    private function seedSettlements(): void
    {
        echo "Creating settlements...\n";
        
        foreach (SettlementData::getData() as $settlementData) {
            try {
                $settlement = new Settlement($settlementData);
                $settlement->save();
                echo "Created settlement: {$settlement->name}\n";
            } catch (\Exception $e) {
                echo "Error creating settlement {$settlementData['id']}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Seed heroes
     */
    private function seedHeroes(): void
    {
        echo "Creating heroes...\n";
        
        foreach (HeroData::getData() as $heroData) {
            try {
                $hero = new Hero($heroData);
                $hero->save();
                echo "Created hero: {$hero->name}\n";
            } catch (\Exception $e) {
                echo "Error creating hero {$heroData['id']}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Seed events
     */
    private function seedEvents(): void
    {
        echo "Creating events...\n";
        
        foreach (EventData::getData() as $eventData) {
            try {
                $event = new GameEvent($eventData);
                $event->save();
                echo "Created event: " . substr($event->description, 0, 50) . "...\n";
            } catch (\Exception $e) {
                echo "Error creating event {$eventData['id']}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Initialize core game state and player
     */
    private function initializeGameState(): void
    {
        echo "Initializing game state...\n";
        $gameState = new GameState([
            'singleton_id' => 'GAME_STATE',
            'current_year' => 1
        ]);
        $gameState->save();

        echo "Initializing player...\n";
        $player = new Player([
            'id' => 'SINGLE_PLAYER',
            'divine_favor' => 100
        ]);
        $player->save();
    }

    /**
     * Seed buildings
     */
    private function seedBuildings(): void
    {
        echo "Creating buildings...\n";
        
        foreach (BuildingData::getData() as $buildingData) {
            try {
                $building = new Building($buildingData);
                $building->save();
                echo "Created building: {$building->name} in settlement {$building->settlement_id}\n";
            } catch (\Exception $e) {
                echo "Error creating building {$buildingData['id']}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Seed resource nodes
     */
    private function seedResourceNodes(): void
    {
        echo "Creating resource nodes...\n";

        foreach (ResourceNodeData::getData() as $resourceData) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO resource_nodes
                        (id, region_id, settlement_id, type, name, output, status, created_at, updated_at)
                    VALUES
                        (:id, :region_id, :settlement_id, :type, :name, :output, :status, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        region_id = VALUES(region_id),
                        settlement_id = VALUES(settlement_id),
                        type = VALUES(type),
                        name = VALUES(name),
                        output = VALUES(output),
                        status = VALUES(status),
                        updated_at = NOW()
                ");
                $stmt->execute($resourceData);
                echo "Created resource node: {$resourceData['name']}\n";
            } catch (\Exception $e) {
                echo "Error creating resource node {$resourceData['id']}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Seed divine bets
     */
    private function seedDivineBets(): void
    {
        echo "Creating sample divine bets...\n";
        
        foreach (DivineBetData::getData() as $betData) {
            try {
                $bet = new DivineBet($betData);
                $bet->save();
                echo "Created divine bet: {$bet->id}\n";
            } catch (\Exception $e) {
                echo "Error creating divine bet {$betData['id']}: " . $e->getMessage() . "\n";
            }
        }
    }
}
