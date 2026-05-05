<?php

namespace App\Repositories;

use Exception;
use App\Utils\Logger;
use App\Repositories\DatabaseService;

class BuildingRepository extends BaseRepository
{
    protected string $table = 'buildings';
    public function __construct(DatabaseService $db)
    {
        parent::__construct($db);
    }

    /**
     * Fetch building by ID
     */
    public function getBuildingById($id)
    {
        return $this->getById($id);
    }

    /**
     * Get all buildings for a settlement
     */
    public function getSettlementBuildings($settlementId)
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE settlement_id = :settlement_id";
            return $this->executeQuery($sql, [':settlement_id' => $settlementId])->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            Logger::error("Error fetching buildings for settlement: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all buildings with optional filtering
     */
    public function getAllBuildings($filters = [], $limit = 20, $offset = 0)
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE 1=1";
            $params = [];

    // Define filter mappings
            $filterMapping = [
                'type' => 'type',
                'status' => 'status',
                'settlementId' => 'settlement_id',
                'level' => 'level'
            ];

            $this->applyFilters($sql, $params, $filters, $filterMapping);

            $sql .= " ORDER BY level DESC, type ASC";
            $this->addPagination($sql, $params, $limit, $offset);

            $stmt = $this->executeQuery($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            Logger::error("Error fetching buildings: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Save or update a building
     */
    public function saveBuilding($buildingData)
    {
        try {
            $requiredFields = ['name', 'type', 'settlement_id', 'level', 'status'];

    // Add timestamps
            $buildingData['created_at'] = $buildingData['created_at'] ?? date('Y-m-d H:i:s');
            $buildingData['updated_at'] = date('Y-m-d H:i:s');

            return $this->saveEntity($buildingData, $requiredFields);
        } catch (Exception $e) {
            Logger::error("Error saving building: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update building level
     */
    public function upgradeBuilding($buildingId, $newLevel, $year)
    {
        try {
            $sql = "UPDATE {$this->table} SET 
                level = :level,
                last_upgrade_year = :last_upgrade_year,
                updated_at = NOW()
                WHERE id = :id";

            return $this->executeQuery($sql, [
                ':id' => $buildingId,
                ':level' => $newLevel,
                ':last_upgrade_year' => $year
            ]);
        } catch (Exception $e) {
            Logger::error("Error upgrading building: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update building status
     */
    public function updateStatus($buildingId, $newStatus)
    {
        try {
            $sql = "UPDATE {$this->table} SET 
                status = :status,
                updated_at = NOW()
                WHERE id = :id";

            return $this->executeQuery($sql, [
                ':id' => $buildingId,
                ':status' => $newStatus
            ]);
        } catch (Exception $e) {
            Logger::error("Error updating building status: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get building counts by type for a settlement
     */
    public function getBuildingCountsByType($settlementId)
    {
        try {
            $sql = "SELECT type, COUNT(*) as count, AVG(level) as avg_level 
                    FROM {$this->table} 
                    WHERE settlement_id = :settlement_id 
                    GROUP BY type";

            $stmt = $this->executeQuery($sql, [':settlement_id' => $settlementId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            Logger::error("Error getting building counts: " . $e->getMessage());
            throw $e;
        }
    }
}
