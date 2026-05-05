<?php

namespace App\Actions;

use App\Models\ResourceNode;
use App\Repositories\ResourceNodeRepository;
use App\Core\Exceptions\ResourceNotFoundException;
use App\Helpers\Logger;

/**
 * Handles resource node operations
 */
class ResourceNodeActions
{
    public function __construct(
        private ResourceNodeRepository $resourceNodeRepository
    ) {
    }

    /**
     * Fetch all resource nodes with optional filtering
     *
     * @param array $filters Optional filters for the query
     * @return array Array of resource node data
     * @throws \RuntimeException if database operation fails
     */
    public function fetchAllResourceNodes(array $filters = []): array
    {
        try {
            $query = ResourceNode::query();

    // Apply filters
            if (!empty($filters['regionId'])) {
                $query->where('regionId', $filters['regionId']);
            }

            if (!empty($filters['settlementId'])) {
                $query->where('settlementId', $filters['settlementId']);
            }

            if (!empty($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['minOutput'])) {
                $query->where('output', '>=', $filters['minOutput']);
            }

            if (!empty($filters['maxOutput'])) {
                $query->where('output', '<=', $filters['maxOutput']);
            }

    // Apply pagination
            $limit = $filters['limit'] ?? 20;
            $offset = $filters['offset'] ?? 0;

            $resourceNodes = $query->skip($offset)->take($limit)->get();

            return $resourceNodes->map(fn($node) => $node->toArray())->all();
        } catch (\Exception $error) {
            Logger::error('Error fetching resource nodes', [
                'filters' => $filters,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch resource nodes from database', 0, $error);
        }
    }

    /**
     * Fetch a resource node by ID
     *
     * @param string $nodeId The ID of the resource node to fetch
     * @return array Resource node data
     * @throws ResourceNotFoundException if resource node not found
     * @throws \RuntimeException if database operation fails
     */
    public function fetchResourceNodeById(string $nodeId): array
    {
        try {
            $node = $this->resourceNodeRepository->getById($nodeId);

            if (!$node) {
                Logger::info("Resource node not found", ['nodeId' => $nodeId]);
                throw new ResourceNotFoundException("Resource node not found: {$nodeId}");
            }

            if (!($node instanceof ResourceNode)) {
                throw new \RuntimeException("Invalid resource node data returned from repository");
            }

            return $node->toArray();
        } catch (ResourceNotFoundException $error) {
            throw $error;
        } catch (\Exception $error) {
            Logger::error('Error fetching resource node', [
                'nodeId' => $nodeId,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch resource node from database', 0, $error);
        }
    }

    /**
     * Create a new resource node
     *
     * @param array $nodeData The resource node data
     * @return array The created resource node data
     * @throws \RuntimeException if validation fails or database operation fails
     */
    public function createResourceNode(array $nodeData): array
    {
        try {
            $node = new ResourceNode($nodeData);

    // Validate the resource node
            $errors = $node->validate();
            if (!empty($errors)) {
                throw new \RuntimeException('Validation failed: ' . implode(', ', $errors));
            }

            $node->save();

            Logger::info("Successfully created resource node", ['id' => $node->id]);
            return $node->toArray();
        } catch (\Exception $error) {
            Logger::error('Error creating resource node', [
                'data' => $nodeData,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to create resource node', 0, $error);
        }
    }

    /**
     * Update a resource node
     *
     * @param string $nodeId The ID of the resource node to update
     * @param array $updateData The update data
     * @return array The updated resource node data
     * @throws ResourceNotFoundException if resource node not found
     * @throws \RuntimeException if validation fails or database operation fails
     */
    public function updateResourceNode(string $nodeId, array $updateData): array
    {
        try {
            $node = $this->resourceNodeRepository->getById($nodeId);

            if (!$node) {
                Logger::info("Resource node not found", ['nodeId' => $nodeId]);
                throw new ResourceNotFoundException("Resource node not found: {$nodeId}");
            }

            if (!($node instanceof ResourceNode)) {
                throw new \RuntimeException("Invalid resource node data returned from repository");
            }

            $node->fill($updateData);

    // Validate the resource node
            $errors = $node->validate();
            if (!empty($errors)) {
                throw new \RuntimeException('Validation failed: ' . implode(', ', $errors));
            }

            $node->save();

            Logger::info("Successfully updated resource node", ['id' => $nodeId]);
            return $node->toArray();
        } catch (ResourceNotFoundException $error) {
            throw $error;
        } catch (\Exception $error) {
            Logger::error('Error updating resource node', [
                'nodeId' => $nodeId,
                'data' => $updateData,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to update resource node', 0, $error);
        }
    }

    /**
     * Delete a resource node
     *
     * @param string $nodeId The ID of the resource node to delete
     * @return bool True if deleted successfully
     * @throws ResourceNotFoundException if resource node not found
     * @throws \RuntimeException if database operation fails
     */
    public function deleteResourceNode(string $nodeId): bool
    {
        try {
            $node = $this->resourceNodeRepository->getById($nodeId);

            if (!$node) {
                Logger::info("Resource node not found", ['nodeId' => $nodeId]);
                throw new ResourceNotFoundException("Resource node not found: {$nodeId}");
            }

            if (!($node instanceof ResourceNode)) {
                throw new \RuntimeException("Invalid resource node data returned from repository");
            }

            $node->delete();

            Logger::info("Successfully deleted resource node", ['id' => $nodeId]);
            return true;
        } catch (ResourceNotFoundException $error) {
            throw $error;
        } catch (\Exception $error) {
            Logger::error('Error deleting resource node', [
                'nodeId' => $nodeId,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to delete resource node', 0, $error);
        }
    }
}
