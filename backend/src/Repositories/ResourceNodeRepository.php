<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Exception;
use App\Utils\Logger;

class ResourceNodeRepository extends BaseRepository
{
    protected string $table = 'resource_nodes';

    public function __construct(DatabaseService $db)
    {
        parent::__construct($db);
    }

    /**
     * Fetch resource node by ID
     */
    public function getResourceNodeById($id)
    {
        $sql = "SELECT * FROM resource_nodes WHERE id = :id";
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all resource nodes with optional filtering
     */
    public function getAllResourceNodes($filters = [], $limit = 20, $offset = 0)
    {
        $sql = "SELECT * FROM resource_nodes WHERE 1=1";
        $params = [];

        if (!empty($filters['type'])) {
            $sql .= " AND type = :type";
            $params[':type'] = $filters['type'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['regionId'])) {
            $sql .= " AND region_id = :region_id";
            $params[':region_id'] = $filters['regionId'];
        }

        if (!empty($filters['minQuantity'])) {
            $sql .= " AND quantity >= :min_quantity";
            $params[':min_quantity'] = $filters['minQuantity'];
        }

        if (!empty($filters['minQuality'])) {
            $sql .= " AND quality >= :min_quality";
            $params[':min_quality'] = $filters['minQuality'];
        }

    // Add ordering and pagination
        $sql .= " ORDER BY quality DESC, quantity DESC";
        $sql .= " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Save or update a resource node
     */
    public function saveResourceNode($nodeData)
    {
        if (empty($nodeData['id'])) {
            // Insert new resource node
            $sql = "INSERT INTO resource_nodes (
                id, name, type, region_id, settlement_id,
                quantity, quality, status, last_harvest_year,
                created_at, updated_at
            ) VALUES (
                :id, :name, :type, :region_id, :settlement_id,
                :quantity, :quality, :status, :last_harvest_year,
                :created_at, :updated_at
            )";
        } else {
            // Update existing resource node
            $sql = "UPDATE resource_nodes SET 
                name = :name,
                type = :type,
                region_id = :region_id,
                settlement_id = :settlement_id,
                quantity = :quantity,
                quality = :quality,
                status = :status,
                last_harvest_year = :last_harvest_year,
                updated_at = :updated_at
                WHERE id = :id";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($nodeData);
    }

    /**
     * Get resource nodes near a settlement
     */
    public function getSettlementResourceNodes($settlementId, $radius = 5)
    {
        $sql = "SELECT * FROM resource_nodes 
                WHERE settlement_id = :settlement_id 
                ORDER BY quality DESC, quantity DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':settlement_id' => $settlementId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update resource quantity after harvest
     */
    public function updateQuantity($nodeId, $newQuantity, $harvestYear)
    {
        $sql = "UPDATE resource_nodes SET 
            quantity = :quantity,
            last_harvest_year = :last_harvest_year,
            updated_at = NOW()
            WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $nodeId,
            ':quantity' => $newQuantity,
            ':last_harvest_year' => $harvestYear
        ]);
    }

    /**
     * Update resource node status
     */
    public function updateStatus($nodeId, $newStatus)
    {
        $sql = "UPDATE resource_nodes SET 
            status = :status,
            updated_at = NOW()
            WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $nodeId,
            ':status' => $newStatus
        ]);
    }

    /**
     * Get resource distribution in a region
     */
    public function getRegionResourceDistribution($regionId)
    {
        $sql = "SELECT type, COUNT(*) as count, 
                       AVG(quantity) as avg_quantity,
                       AVG(quality) as avg_quality
                FROM resource_nodes 
                WHERE region_id = :region_id 
                GROUP BY type";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':region_id' => $regionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
