<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Exception;
use App\Utils\Logger;

class InfluenceRepository extends BaseRepository
{
    protected string $table = 'influence_actions';

    public function __construct(DatabaseService $db)
    {
        parent::__construct($db);
    }

    /**
     * Record a divine influence action
     */
    public function recordInfluenceAction($actionData)
    {
        $sql = "INSERT INTO divine_influence_actions (
            id, player_id, target_id, target_type,
            action_type, influence_cost, effect_strength,
            resolution_notes, game_year, created_at
        ) VALUES (
            :id, :player_id, :target_id, :target_type,
            :action_type, :influence_cost, :effect_strength,
            :resolution_notes, :game_year, :created_at
        )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($actionData);
        return $actionData['id'];
    }

    /**
     * Get influence actions for a player
     */
    public function getPlayerInfluenceActions($playerId, $limit = 20, $offset = 0)
    {
        $sql = "SELECT * FROM divine_influence_actions 
                WHERE player_id = :player_id 
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':player_id' => $playerId,
            ':limit' => $limit,
            ':offset' => $offset
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get influence actions affecting a target
     */
    public function getTargetInfluenceActions($targetId, $targetType)
    {
        $sql = "SELECT * FROM divine_influence_actions 
                WHERE target_id = :target_id 
                AND target_type = :target_type 
                ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':target_id' => $targetId,
            ':target_type' => $targetType
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate total influence spent by a player
     */
    public function calculateTotalInfluenceSpent($playerId, $timeframe = null)
    {
        $sql = "SELECT SUM(influence_cost) as total_spent 
                FROM divine_influence_actions 
                WHERE player_id = :player_id";

        if ($timeframe) {
            $sql .= " AND created_at >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)";
        }

        $stmt = $this->db->prepare($sql);
        $params = [':player_id' => $playerId];
        if ($timeframe) {
            $params[':timeframe'] = $timeframe;
        }

        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total_spent'] ?? 0;
    }

    /**
     * Get recent influence effects on a target
     */
    public function getRecentInfluenceEffects($targetId, $targetType, $daysAgo = 30)
    {
        $sql = "SELECT * FROM divine_influence_actions 
                WHERE target_id = :target_id 
                AND target_type = :target_type 
                AND created_at >= DATE_SUB(NOW(), INTERVAL :days_ago DAY)
                ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':target_id' => $targetId,
            ':target_type' => $targetType,
            ':days_ago' => $daysAgo
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if a target can be influenced
     */
    public function canTargetBeInfluenced($targetId, $targetType, $actionType)
    {
        // Check for recent similar actions or cooldown periods
        $sql = "SELECT COUNT(*) as recent_actions 
                FROM divine_influence_actions 
                WHERE target_id = :target_id 
                AND target_type = :target_type 
                AND action_type = :action_type
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':target_id' => $targetId,
            ':target_type' => $targetType,
            ':action_type' => $actionType
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['recent_actions'] == 0;
    }

    /**
     * Calculate influence cost based on target and recent actions
     */
    public function calculateInfluenceCost($targetId, $targetType, $actionType)
    {
        // Get recent influence actions on the target
        $recentActions = $this->getRecentInfluenceEffects($targetId, $targetType, 7);

    // Base costs for different action types
        $baseCosts = [
            'guide' => 5,
            'empower' => 10,
            'bless' => 15,
            'corrupt' => 20,
            'revive' => 50
        ];

        $baseCost = $baseCosts[$actionType] ?? 10;

    // Increase cost based on recent actions
        $costMultiplier = 1 + (count($recentActions) * 0.2);

        return ceil($baseCost * $costMultiplier);
    }

    /**
     * Get top influencers for a time period
     */
    public function getTopInfluencers($timeframe = 30, $limit = 10)
    {
        $sql = "SELECT player_id, 
                       COUNT(*) as action_count,
                       SUM(influence_cost) as total_influence,
                       SUM(effect_strength) as total_effect
                FROM divine_influence_actions 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :timeframe DAY)
                GROUP BY player_id
                ORDER BY total_influence DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':timeframe' => $timeframe,
            ':limit' => $limit
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
