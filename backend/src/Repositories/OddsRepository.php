<?php

namespace App\Repositories;

use PDO;
use Exception;
use App\Utils\Logger;

class OddsRepository extends BettingBaseRepository
{
    protected string $table = 'bet_odds';

    public function __construct(DatabaseService $db)
    {
        parent::__construct($db);
    }

    /**
     * Fetch target history for odds calculation
     */
    public function getTargetHistory($targetId, $betType)
    {
        try {
            $sql = "SELECT * FROM bet_history WHERE target_id = :targetId AND bet_type = :betType ORDER BY created_at DESC LIMIT 10";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'targetId' => $targetId,
                'betType' => $betType
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            Logger::error("Error fetching bet history: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch target statistics for odds calculation
     */
    public function getTargetStats($targetId, $type)
    {
        try {
            // Check if target_statistics table exists, if not return default stats
            $sql = "SHOW TABLES LIKE 'target_statistics'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            if (!$stmt->fetch()) {
                // Table doesn't exist, return default stats based on target type
                return $this->getDefaultTargetStats($targetId, $type);
            }

            $sql = "SELECT stats.* FROM target_statistics stats WHERE stats.target_id = :targetId AND stats.type = :type";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'targetId' => $targetId,
                'type' => $type
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: $this->getDefaultTargetStats($targetId, $type);
        } catch (Exception $e) {
            Logger::error("Error fetching target stats: " . $e->getMessage());

    // Return default stats on error
            return $this->getDefaultTargetStats($targetId, $type);
        }
    }

    /**
     * Get default target statistics when table doesn't exist
     */
    private function getDefaultTargetStats($targetId, $type)
    {
        $defaults = [
            'settlement' => [
                'prosperity' => 50,
                'population' => 1000,
                'growth_rate' => 0.02,
                'stability' => 0.7
            ],
            'hero' => [
                'level' => 5,
                'reputation' => 50,
                'activity_level' => 0.6,
                'success_rate' => 0.5
            ],
            'region' => [
                'prosperity' => 50,
                'chaos' => 20,
                'magic_affinity' => 30,
                'stability' => 0.6
            ],
            'landmark' => [
                'danger_level' => 40,
                'discovery_chance' => 0.3,
                'accessibility' => 0.5
            ]
        ];

        return $defaults[$type] ?? $defaults['settlement'];
    }

    /**
     * Save calculated odds for a target
     */
    public function saveCalculatedOdds($targetId, $betType, $odds)
    {
        try {
            $sql = "INSERT INTO bet_odds (target_id, bet_type, odds, calculated_at) 
                   VALUES (:targetId, :betType, :odds, NOW())
                   ON DUPLICATE KEY UPDATE odds = :odds, calculated_at = NOW()";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'targetId' => $targetId,
                'betType' => $betType,
                'odds' => $odds
            ]);
        } catch (Exception $e) {
            Logger::error("Error saving calculated odds: " . $e->getMessage());
            throw $e;
        }
    }
}
