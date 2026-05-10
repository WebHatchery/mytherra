<?php

declare(strict_types=1);

namespace App\Repositories;

use Exception;
use App\Utils\Logger;
use App\Repositories\DatabaseService;

abstract class BaseRepository
{
    protected DatabaseService $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct(DatabaseService $db)
    {
        $this->db = $db;
    }

    /**
     * Get entity by ID
     */
    public function getById($id)
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
            $stmt = $this->db->getPdo()->prepare($sql);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch($this->db->getPdo()::FETCH_ASSOC);
        } catch (Exception $e) {
            Logger::error("Error fetching {$this->table} by ID: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get entities by array of IDs
     */
    public function getByIds(array $ids)
    {
        try {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} IN ($placeholders)";
            $stmt = $this->db->getPdo()->prepare($sql);
            $stmt->execute($ids);
            return $stmt->fetchAll($this->db->getPdo()::FETCH_ASSOC);
        } catch (Exception $e) {
            Logger::error("Error fetching {$this->table} by IDs: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Apply common filters to a query
     */
    protected function applyFilters(&$sql, &$params, $filters, $filterMapping)
    {
        foreach ($filters as $key => $value) {
            if (!empty($value) && isset($filterMapping[$key])) {
                $sql .= " AND {$filterMapping[$key]} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }
    }

    /**
     * Add pagination parameters to SQL
     */
    protected function addPagination(&$sql, &$params, $limit = null, $offset = null)
    {
        $limit = $limit !== null ? (int)$limit : null;
        $offset = $offset !== null ? (int)$offset : null;

        if ($limit !== null && $offset !== null) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        } elseif ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
        }
    }

    /**
     * Execute a query safely
     */
    protected function executeQuery($sql, $params = [])
    {
        try {
            $stmt = $this->db->getPdo()->prepare($sql);
            foreach ($params as $i => $param) {
                if (is_int($param)) {
                    $stmt->bindValue($i + 1, $param, $this->db->getPdo()::PARAM_INT);
                } else {
                    $stmt->bindValue($i + 1, $param, $this->db->getPdo()::PARAM_STR);
                }
            }
            $stmt->execute();
            return $stmt;
        } catch (Exception $e) {
            Logger::error("Error executing query on {$this->table}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Save or update an entity
     */
    protected function saveEntity($data, $requiredFields = [])
    {
        try {
            // Validate required fields
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    throw new Exception("Missing required field: {$field}");
                }
            }

            if (empty($data[$this->primaryKey])) {
                // Insert
                $fields = array_keys($data);
                $columns = implode(', ', $fields);
                $placeholders = ':' . implode(', :', $fields);
                $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
            } else {
                // Update
                $fields = array_keys($data);
                $updates = [];
                foreach ($fields as $field) {
                    if ($field !== $this->primaryKey) {
                        $updates[] = "{$field} = :{$field}";
                    }
                }
                $updateStr = implode(', ', $updates);
                $sql = "UPDATE {$this->table} SET {$updateStr} WHERE {$this->primaryKey} = :{$this->primaryKey}";
            }

            return $this->executeQuery($sql, $data);
        } catch (Exception $e) {
            Logger::error("Error saving entity to {$this->table}: " . $e->getMessage());
            throw $e;
        }
    }
}
