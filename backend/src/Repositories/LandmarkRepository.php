<?php

declare(strict_types=1);

namespace App\Repositories;

use Exception;
use PDO;
use App\Utils\Logger;
use App\Repositories\DatabaseService;

class LandmarkRepository extends BaseRepository
{
    protected string $table = 'landmarks';

    public function __construct(DatabaseService $db)
    {
        parent::__construct($db);
    }

    /**
     * Fetch landmark by ID
     */
    public function getLandmarkById($id)
    {
        $sql = "SELECT * FROM landmarks WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch multiple landmarks by array of IDs
     */
    public function getLandmarksByIds(array $ids)
    {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "SELECT * FROM landmarks WHERE id IN ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all landmarks with optional filtering
     */
    public function getAllLandmarks($filters = [], $limit = 20, $offset = 0)
    {
        $sql = "SELECT * FROM landmarks WHERE 1=1";
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

        if (isset($filters['discoveredYear'])) {
            if ($filters['discoveredYear'] === null) {
                $sql .= " AND discovered_year IS NULL";
            } else {
                $sql .= " AND discovered_year = :discovered_year";
                $params[':discovered_year'] = $filters['discoveredYear'];
            }
        }

        if (!empty($filters['minDangerLevel'])) {
            $sql .= " AND danger_level >= :min_danger_level";
            $params[':min_danger_level'] = $filters['minDangerLevel'];
        }

        if (!empty($filters['minMagicLevel'])) {
            $sql .= " AND magic_level >= :min_magic_level";
            $params[':min_magic_level'] = $filters['minMagicLevel'];
        }

    // Add ordering and pagination
        $sql .= " ORDER BY magic_level DESC, danger_level DESC";
        $sql .= " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Save or update a landmark
     */
    public function saveLandmark($landmarkData)
    {
        if (empty($landmarkData['id'])) {
            // Insert new landmark
            $sql = "INSERT INTO landmarks (
                id, name, type, region_id, description,
                danger_level, magic_level, status, discovered_year,
                last_visited_year, discovery_notes, created_at, updated_at
            ) VALUES (
                :id, :name, :type, :region_id, :description,
                :danger_level, :magic_level, :status, :discovered_year,
                :last_visited_year, :discovery_notes, :created_at, :updated_at
            )";
        } else {
            // Update existing landmark
            $sql = "UPDATE landmarks SET 
                name = :name,
                type = :type,
                region_id = :region_id,
                description = :description,
                danger_level = :danger_level,
                magic_level = :magic_level,
                status = :status,
                discovered_year = :discovered_year,
                last_visited_year = :last_visited_year,
                discovery_notes = :discovery_notes,
                updated_at = :updated_at
                WHERE id = :id";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($landmarkData);
    }

    /**
     * Get landmarks suitable for betting opportunities
     */
    public function getLandmarksForBetting($filters = [])
    {
        $sql = "SELECT * FROM landmarks WHERE 1=1";
        $params = [];

    // Focus on undiscovered or recently discovered landmarks
        $sql .= " AND (
            discovered_year IS NULL OR
            magic_level >= 50 OR
            danger_level >= 50 OR
            status IN ('mysterious', 'corrupted', 'unstable')
        )";

        if (!empty($filters['regionId'])) {
            $sql .= " AND region_id = :region_id";
            $params[':region_id'] = $filters['regionId'];
        }

        if (isset($filters['discovered'])) {
            if ($filters['discovered']) {
                $sql .= " AND discovered_year IS NOT NULL";
            } else {
                $sql .= " AND discovered_year IS NULL";
            }
        }

    // Order by potential for interesting discoveries
        $sql .= " ORDER BY (magic_level + danger_level) DESC";
        $sql .= " LIMIT :limit";
        $params[':limit'] = $filters['limit'] ?? 5;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark a landmark as discovered
     */
    public function markDiscovered($landmarkId, $year, $discoveryNotes = null)
    {
        $sql = "UPDATE landmarks SET 
            discovered_year = :discovered_year,
            discovery_notes = :discovery_notes,
            updated_at = NOW()
            WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $landmarkId,
            ':discovered_year' => $year,
            ':discovery_notes' => $discoveryNotes
        ]);
    }

    /**
     * Update landmark visit record
     */
    public function recordVisit($landmarkId, $year)
    {
        $sql = "UPDATE landmarks SET 
            last_visited_year = :last_visited_year,
            updated_at = NOW()
            WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $landmarkId,
            ':last_visited_year' => $year
        ]);
    }

    /**
     * Update landmark's corruption status
     */
    public function updateCorruptionStatus($landmarkId, $newStatus, $notes = null)
    {
        $sql = "UPDATE landmarks SET 
            status = :status,
            discovery_notes = CONCAT(COALESCE(discovery_notes, ''), :notes),
            updated_at = NOW()
            WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $landmarkId,
            ':status' => $newStatus,
            ':notes' => $notes ? "\n" . $notes : null
        ]);
    }
}
