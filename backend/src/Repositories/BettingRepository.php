<?php

declare(strict_types=1);

namespace App\Repositories;

use Exception;
use App\Utils\Logger;
use App\Models\DivineBet;
use App\Models\Settlement;
use App\Models\Hero;
use App\Models\Region;
use App\Models\Landmark;
use App\Models\ResourceNode;

class BettingRepository
{
    /**
     * Insert a new divine bet into the database
     */
    public function createDivineBet($betData)
    {
        try {
            // Generate ID if not provided
            if (!isset($betData['id'])) {
                $betData['id'] = 'bet-' . uniqid();
            }

            $bet = DivineBet::create($betData);
            return $bet->id;
        } catch (Exception $e) {
            Logger::error("Error creating divine bet: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch divine bet by ID
     */
    public function fetchDivineBetById($id)
    {
        try {
            $bet = DivineBet::find($id);

            if (!$bet) {
                return null;
            }

            return $this->transformBetData($bet->toArray());
        } catch (Exception $e) {
            Logger::error("Error fetching bet {$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch all divine bets with filtering
     */
    public function fetchAllDivineBets($filters = [], $limit = 20, $offset = 0)
    {
        try {
            $query = DivineBet::query();

    // Apply filters
            if (!empty($filters['playerId'])) {
                $query->where('player_id', $filters['playerId']);
            }

            if (!empty($filters['betType'])) {
                $query->where('bet_type', $filters['betType']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['targetId'])) {
                $query->where('target_id', $filters['targetId']);
            }

            if (!empty($filters['confidence'])) {
                $query->where('confidence', $filters['confidence']);
            }

            if (!empty($filters['timeframe'])) {
                $query->where('timeframe', $filters['timeframe']);
            }

            if (!empty($filters['minStake'])) {
                $query->where('divine_favor_stake', '>=', $filters['minStake']);
            }

            if (!empty($filters['maxStake'])) {
                $query->where('divine_favor_stake', '<=', $filters['maxStake']);
            }

    // Add ordering and pagination
            $bets = $query
                ->orderBy('created_at', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();

            return $this->transformBetDataArray($bets->toArray());
        } catch (Exception $e) {
            Logger::error("Error fetching divine bets: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if a target entity exists
     */
    public function validateTargetEntity($targetId)
    {
        try {
            // Check in settlements
            if (Settlement::find($targetId)) {
                return true;
            }

    // Check in heroes
            if (Hero::find($targetId)) {
                return true;
            }

    // Check in regions
            if (Region::find($targetId)) {
                return true;
            }

    // Check in landmarks
            if (Landmark::find($targetId)) {
                return true;
            }

    // Check in resource nodes
            if (ResourceNode::find($targetId)) {
                return true;
            }

            return false;
        } catch (Exception $e) {
            Logger::error("Error validating target entity: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get sample targets for betting
     */
    public function getSampleTargets()
    {
        try {
            $targets = [];

    // Get sample settlements
            $settlements = Settlement::take(3)->get(['id', 'name']);
            foreach ($settlements as $settlement) {
                $targets['settlement'][] = [
                    'id' => $settlement->id,
                    'name' => $settlement->name,
                    'type' => 'settlement'
                ];
            }

    // Get sample heroes
            $heroes = Hero::take(3)->get(['id', 'name']);
            foreach ($heroes as $hero) {
                $targets['hero'][] = [
                    'id' => $hero->id,
                    'name' => $hero->name,
                    'type' => 'hero'
                ];
            }

    // Get sample regions
            $regions = Region::take(3)->get(['id', 'name']);
            foreach ($regions as $region) {
                $targets['region'][] = [
                    'id' => $region->id,
                    'name' => $region->name,
                    'type' => 'region'
                ];
            }

    // Get sample landmarks
            $landmarks = Landmark::take(3)->get(['id', 'name']);
            foreach ($landmarks as $landmark) {
                $targets['landmark'][] = [
                    'id' => $landmark->id,
                    'name' => $landmark->name,
                    'type' => 'landmark'
                ];
            }

    // Get sample resource nodes
            $resources = ResourceNode::take(3)->get(['id', 'name']);
            foreach ($resources as $resource) {
                $targets['resource'][] = [
                    'id' => $resource->id,
                    'name' => $resource->name,
                    'type' => 'resource'
                ];
            }

            return $targets;
        } catch (Exception $e) {
            Logger::error("Error getting sample targets: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get sample regions for speculation events
     */
    public function getSampleRegions($limit = 5)
    {
        try {
            return Region::take($limit)->get(['id'])->toArray();
        } catch (Exception $e) {
            Logger::error("Error getting sample regions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Alias for createDivineBet to match BettingActions interface
     */
    public function createBet($betData)
    {
        return $this->createDivineBet($betData);
    }

    /**
     * Alias for fetchDivineBetById to match BettingActions interface
     */
    public function getBetById($id)
    {
        return $this->fetchDivineBetById($id);
    }

    /**
     * Alias for fetchAllDivineBets to match BettingActions interface
     */
    public function getAllBets($filters = [])
    {
        $limit = $filters['limit'] ?? 20;
        $offset = $filters['offset'] ?? 0;
        unset($filters['limit'], $filters['offset']);

        return $this->fetchAllDivineBets($filters, $limit, $offset);
    }

    public function getBetSummary(array $filters = []): array
    {
        $limit = isset($filters['recentLimit']) ? max(1, min((int)$filters['recentLimit'], 10)) : 5;
        unset($filters['recentLimit'], $filters['limit'], $filters['offset'], $filters['status']);

        try {
            $baseQuery = $this->applySummaryFilters(DivineBet::query(), $filters);
            $statusCounts = (clone $baseQuery)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();
            $activeQuery = (clone $baseQuery)->where('status', 'active');
            $resolvedQuery = (clone $baseQuery)->whereIn('status', ['won', 'lost', 'expired']);
            $wonQuery = (clone $baseQuery)->where('status', 'won');
            $resolvedCount = (int)(clone $resolvedQuery)->count();
            $wonCount = (int)($statusCounts['won'] ?? 0);
            $resolvedStake = (int)(clone $resolvedQuery)->sum('divine_favor_stake');
            $wonPayout = (int)(clone $wonQuery)->sum('potential_payout');
            $activeStake = (int)(clone $activeQuery)->sum('divine_favor_stake');
            $activePotential = (int)(clone $activeQuery)->sum('potential_payout');
            $netResolvedFavor = $wonPayout - $resolvedStake;

            $topBetTypes = (clone $baseQuery)
                ->selectRaw('bet_type, COUNT(*) as total, SUM(divine_favor_stake) as stake')
                ->groupBy('bet_type')
                ->orderByDesc('total')
                ->limit(4)
                ->get()
                ->map(fn($row) => [
                    'betType' => $row->bet_type,
                    'total' => (int)$row->total,
                    'stake' => (int)$row->stake,
                ])
                ->values()
                ->all();

            return [
                'generatedAt' => date('c'),
                'total' => (int)(clone $baseQuery)->count(),
                'active' => (int)($statusCounts['active'] ?? 0),
                'won' => $wonCount,
                'lost' => (int)($statusCounts['lost'] ?? 0),
                'expired' => (int)($statusCounts['expired'] ?? 0),
                'activeStake' => $activeStake,
                'activePotentialPayout' => $activePotential,
                'resolvedStake' => $resolvedStake,
                'wonPayout' => $wonPayout,
                'netResolvedFavor' => $netResolvedFavor,
                'winRatePercent' => $resolvedCount > 0 ? round(($wonCount / $resolvedCount) * 100, 1) : null,
                'averageOdds' => round((float)((clone $baseQuery)->avg('current_odds') ?? 0), 2),
                'topBetTypes' => $topBetTypes,
                'recentActive' => (clone $activeQuery)
                    ->orderByDesc('placed_year')
                    ->orderByDesc('created_at')
                    ->limit($limit)
                    ->get()
                    ->map(fn(DivineBet $bet) => $this->transformBetData($bet->toArray()))
                    ->values()
                    ->all(),
                'recentResolved' => (clone $resolvedQuery)
                    ->orderByDesc('resolved_year')
                    ->orderByDesc('updated_at')
                    ->limit($limit)
                    ->get()
                    ->map(fn(DivineBet $bet) => $this->transformBetData($bet->toArray()))
                    ->values()
                    ->all(),
                'summary' => $this->buildBetSummaryText($activeStake, $activePotential, $resolvedCount, $netResolvedFavor),
            ];
        } catch (Exception $e) {
            Logger::error("Error summarizing divine bets: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get speculation events for betting opportunities
     */
    public function getSpeculationEvents($filters = [])
    {
        // For now, return a mock response since this would need complex game logic
        // In a real implementation, this would generate events based on current game state
        return [
            [
                'id' => 'event-001',
                'title' => 'The Rise of a New Settlement',
                'description' => 'A small hamlet shows signs of unprecedented growth. Will it become a thriving city?',
                'type' => 'settlement_growth',
                'status' => 'active',
                'regionId' => 'region-001',
                'targetId' => 'settlement-001',
                'timeframe' => ['minimum' => 2, 'maximum' => 8],
                'bettingOptions' => [
                    [
                        'id' => 'option-001',
                        'description' => 'Settlement becomes a major trade hub',
                        'currentOdds' => 2.5,
                        'minimumStake' => 15,
                        'potentialPayout' => 37
                    ]
                ]
            ]
        ];
    }

    /**
     * Process expired bets and update their status
     */
    public function processExpiredBets($currentYear)
    {
        try {
            // Find expired bets
            $expiredBets = DivineBet::where('status', 'active')
                ->whereRaw('(placed_year + timeframe) <= ?', [$currentYear])
                ->get();

            $processedCount = 0;
            foreach ($expiredBets as $bet) {
                $bet->update([
                    'status' => 'expired',
                    'resolved_year' => $currentYear,
                    'resolution_notes' => 'Bet expired without resolution'
                ]);
                $processedCount++;
            }

            return [
                'processed' => $processedCount,
                'expired_bets' => $expiredBets->toArray()
            ];
        } catch (Exception $e) {
            Logger::error("Error processing expired bets: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Transform database row to API format (snake_case to camelCase)
     */
    private function transformBetData($bet)
    {
        if (!$bet) {
            return null;
        }

        try {
            $transformed = [
                'id' => $bet['id'],
                'playerId' => $bet['player_id'],
                'betType' => $bet['bet_type'],
                'targetId' => $bet['target_id'],
                'description' => $bet['description'],
                'timeframe' => (int)$bet['timeframe'],
                'confidence' => $bet['confidence'],
                'divineFavorStake' => (int)$bet['divine_favor_stake'],
                'potentialPayout' => (int)$bet['potential_payout'],
                'currentOdds' => (float)$bet['current_odds'],
                'status' => $bet['status'],
                'placedYear' => (int)$bet['placed_year'],
                'resolvedYear' => $bet['resolved_year'] ? (int)$bet['resolved_year'] : null,
                'resolutionNotes' => $bet['resolution_notes'],
                'createdAt' => $bet['created_at'],
                'updatedAt' => $bet['updated_at']
            ];

            $payoutProfile = DivineBet::calculatePayoutProfile(
                $transformed['divineFavorStake'],
                $transformed['currentOdds'],
                $transformed['confidence']
            );
            $storedPayout = (int)$transformed['potentialPayout'];
            if ($storedPayout > 0 && $storedPayout !== $payoutProfile['grossPayout']) {
                $payoutProfile['grossPayout'] = $storedPayout;
                $payoutProfile['grossMultiplier'] = round(
                    $storedPayout / max(1, $transformed['divineFavorStake']),
                    2
                );
                $payoutProfile['netProfit'] = max(0, $storedPayout - $transformed['divineFavorStake']);
                $payoutProfile['summary'] = "Stake {$transformed['divineFavorStake']} for {$storedPayout} favor " .
                    "({$payoutProfile['grossMultiplier']}x gross, {$payoutProfile['netProfit']} net).";
            }
            $transformed['payoutProfile'] = $payoutProfile;

            return $transformed;
        } catch (Exception $e) {
            Logger::error("Error in transformBetData: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Transform array of database rows to API format
     */
    private function transformBetDataArray($bets)
    {
        if (!$bets) {
            return [];
        }

        return array_map([$this, 'transformBetData'], $bets);
    }

    private function applySummaryFilters($query, array $filters)
    {
        if (!empty($filters['playerId'])) {
            $query->where('player_id', $filters['playerId']);
        }

        if (!empty($filters['betType'])) {
            $query->where('bet_type', $filters['betType']);
        }

        if (!empty($filters['targetId'])) {
            $query->where('target_id', $filters['targetId']);
        }

        if (!empty($filters['confidence'])) {
            $query->where('confidence', $filters['confidence']);
        }

        return $query;
    }

    private function buildBetSummaryText(
        int $activeStake,
        int $activePotential,
        int $resolvedCount,
        int $netResolvedFavor
    ): string {
        $activeText = $activeStake > 0
            ? "{$activeStake} favor at risk for up to {$activePotential} favor"
            : 'No active favor at risk';
        $resolvedText = $resolvedCount > 0
            ? "resolved net {$netResolvedFavor} favor"
            : 'no resolved history yet';

        return "{$activeText}; {$resolvedText}.";
    }
}
