<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Exception;
use Mytherra\Utils\Logger;

class BettingConfigRepository extends BaseRepository
{
    protected string $table = 'bet_type_configs';

    public function __construct(DatabaseService $db)
    {
        parent::__construct($db);
    }

    /**
     * Get bet type configuration
     */
    public function getBetTypeConfig($betType)
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE code = :code";
            return $this->executeQuery($sql, [':code' => $betType])->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            Logger::error("Error fetching bet type config: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all bet type configurations
     */
    public function getAllBetTypeConfigs()
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE is_active = true";
            return $this->executeQuery($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            Logger::error("Error fetching bet type configs: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get confidence configuration
     */
    public function getConfidenceConfig($confidence)
    {
        try {
            $sql = "SELECT * FROM bet_confidence_configs WHERE code = :code";
            return $this->executeQuery($sql, [':code' => $confidence])->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            Logger::error("Error fetching confidence config: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all confidence configurations
     */
    public function getAllConfidenceConfigs()
    {
        try {
            $sql = "SELECT * FROM bet_confidence_configs WHERE is_active = true";
            return $this->executeQuery($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            Logger::error("Error fetching confidence configs: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get timeframe modifiers configuration
     */
    public function getTimeframeModifiers()
    {
        try {
            $sql = "SELECT * FROM bet_timeframe_modifiers ORDER BY max_timeframe ASC";
            return $this->executeQuery($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            Logger::error("Error fetching timeframe modifiers: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get target type modifiers
     */
    public function getTargetTypeModifiers($targetType, $betType)
    {
        try {
            $sql = "SELECT * FROM bet_target_modifiers WHERE target_type = :targetType AND bet_type = :betType";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'targetType' => $targetType,
                'betType' => $betType
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            Logger::error("Error fetching target type modifiers: " . $e->getMessage());
            throw $e;
        }
    }
}
