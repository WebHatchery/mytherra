<?php

namespace App\Actions;

use App\Models\Settlement;
use App\Models\Region;
use App\Repositories\SettlementRepository;
use App\Utils\Logger;
use App\Core\Exceptions\ResourceNotFoundException;

/**
 * Handles settlement-related business logic
 */
class SettlementActions
{
    private SettlementRepository $repository;

    public function __construct(SettlementRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Fetch all settlements with optional filtering
     *
     * @param array $filters Optional filters (regionId, settlementType, minPopulation, maxPopulation, limit, offset)
     * @return array List of settlements
     * @throws \RuntimeException if database operation fails
     */
    public function fetchAllSettlements(array $filters = []): array
    {
        try {
            $query = Settlement::query();

            if (!empty($filters['regionId'])) {
                $query->where('region_id', $filters['regionId']);
            }

            if (!empty($filters['settlementType'])) {
                $query->where('type', $filters['settlementType']);
            }

            if (isset($filters['minPopulation'])) {
                $query->where('population', '>=', $filters['minPopulation']);
            }

            if (isset($filters['maxPopulation'])) {
                $query->where('population', '<=', $filters['maxPopulation']);
            }

            $limit = $filters['limit'] ?? 20;
            $offset = $filters['offset'] ?? 0;

            return $query
                ->orderBy('population', 'DESC')
                ->skip($offset)
                ->take($limit)
                ->get()
                ->toArray();
        } catch (\Exception $error) {
            Logger::error('Error fetching settlements', [
                'filters' => $filters,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch settlements from database', 0, $error);
        }
    }

    /**
     * Fetch settlement by ID with relationships
     *
     * @param string $settlementId Settlement identifier
     * @return array Settlement data
     * @throws ResourceNotFoundException if settlement not found
     * @throws \RuntimeException if database operation fails
     */
    public function fetchSettlementById(string $settlementId): array
    {
        try {
            $settlement = Settlement::find($settlementId);

            if (!$settlement) {
                Logger::info("Settlement not found", ['settlementId' => $settlementId]);
                throw new ResourceNotFoundException("Settlement not found: {$settlementId}");
            }

            return $settlement->toArray();
        } catch (ResourceNotFoundException $error) {
            throw $error;
        } catch (\Exception $error) {
            Logger::error('Error fetching settlement', [
                'settlementId' => $settlementId,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch settlement from database', 0, $error);
        }
    }

    /**
     * Get settlements by region ID
     *
     * @param string $regionId Region identifier
     * @return array List of settlements in the region
     * @throws \RuntimeException if database operation fails
     */
    public function fetchSettlementsByRegionId(string $regionId): array
    {
        try {
            return Settlement::where('region_id', $regionId)
                ->orderBy('population', 'DESC')
                ->get()
                ->toArray();
        } catch (\Exception $error) {
            Logger::error('Error fetching settlements for region', [
                'regionId' => $regionId,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch settlements for region', 0, $error);
        }
    }

    /**
     * Get settlement statistics for a region
     *
     * @param string $regionId Region identifier
     * @return array Settlement statistics
     * @throws \RuntimeException if database operation fails
     */
    public function getSettlementStatistics(string $regionId): array
    {
        try {
            $settlements = Settlement::where('region_id', $regionId)->get();

            return [
                'total_settlements' => $settlements->count(),
                'total_population' => $settlements->sum('population'),
                'average_prosperity' => $settlements->avg('prosperity'),
                'settlement_types' => [
                    'city' => $settlements->where('type', 'city')->count(),
                    'town' => $settlements->where('type', 'town')->count(),
                    'village' => $settlements->where('type', 'village')->count(),
                    'hamlet' => $settlements->where('type', 'hamlet')->count()
                ],
                'settlement_statuses' => [
                    'thriving' => $settlements->where('status', 'thriving')->count(),
                    'stable' => $settlements->where('status', 'stable')->count(),
                    'declining' => $settlements->where('status', 'declining')->count(),
                    'abandoned' => $settlements->where('status', 'abandoned')->count(),
                    'ruined' => $settlements->where('status', 'ruined')->count()
                ]
            ];
        } catch (\Exception $error) {
            Logger::error('Error calculating settlement statistics', [
                'regionId' => $regionId,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to calculate settlement statistics', 0, $error);
        }
    }

    /**
     * Create a new settlement
     *
     * @param array $settlementData Settlement creation data
     * @return array Created settlement data
     * @throws \RuntimeException if database operation fails
     */
    public function createSettlement(array $settlementData): array
    {
        try {
            return $this->repository->createSettlement($settlementData);
        } catch (\Exception $error) {
            Logger::error('Error creating settlement', [
                'data' => $settlementData,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to create settlement', 0, $error);
        }
    }

    /**
     * Update settlement by ID
     *
     * @param string $settlementId Settlement identifier
     * @param array $updateData Update data
     * @return array Updated settlement data
     * @throws \RuntimeException if settlement not found or database operation fails
     */
    public function updateSettlement(string $settlementId, array $updateData): array
    {
        try {
            $settlement = Settlement::find($settlementId);
            if (!$settlement) {
                throw new \RuntimeException('Settlement not found');
            }

            foreach ($updateData as $key => $value) {
                if (property_exists($settlement, $key)) {
                    $settlement->$key = $value;
                }
            }

            $settlement->save();
            return $settlement->toArray();
        } catch (\RuntimeException $error) {
            Logger::error('Error updating settlement', [
                'settlementId' => $settlementId,
                'updateData' => $updateData,
                'error' => $error->getMessage()
            ]);
            throw $error;
        } catch (\Exception $error) {
            Logger::error('Database error updating settlement', [
                'settlementId' => $settlementId,
                'updateData' => $updateData,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to update settlement', 0, $error);
        }
    }
}
