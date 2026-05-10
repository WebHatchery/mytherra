<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Exception;
use App\Utils\Logger;

abstract class BettingBaseRepository extends BaseRepository
{
    protected function validateBetData($betData)
    {
        $requiredFields = [
            'bet_id',
            'confidence_level',
            'timeframe',
            'target_type'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($betData[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        return true;
    }

    protected function calculateBetMultiplier($betData)
    {
        try {
            $baseMultiplier = 1.0;

    // Apply confidence level multiplier
            if (isset($betData['confidence_level'])) {
                $sql = "SELECT multiplier FROM bet_confidence_configs WHERE confidence_level = :level";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([':level' => $betData['confidence_level']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $baseMultiplier *= floatval($result['multiplier']);
                }
            }

    // Apply timeframe multiplier
            if (isset($betData['timeframe'])) {
                $sql = "SELECT multiplier FROM bet_timeframe_modifiers WHERE timeframe = :timeframe";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([':timeframe' => $betData['timeframe']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result) {
                    $baseMultiplier *= floatval($result['multiplier']);
                }
            }

            return $baseMultiplier;
        } catch (Exception $e) {
            Logger::error("Error calculating bet multiplier: " . $e->getMessage());
            throw $e;
        }
    }

    protected function resolveBet($betId, $outcome)
    {
        try {
            $sql = "UPDATE bets SET 
                    status = :status,
                    resolved_at = NOW(),
                    outcome = :outcome 
                    WHERE bet_id = :bet_id";

            $params = [
                ':status' => 'resolved',
                ':outcome' => $outcome,
                ':bet_id' => $betId
            ];

            return $this->executeQuery($sql, $params);
        } catch (Exception $e) {
            Logger::error("Error resolving bet: " . $e->getMessage());
            throw $e;
        }
    }
}
