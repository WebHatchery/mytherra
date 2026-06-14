<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Hero;
use App\Models\Region;
use App\Models\Settlement;
use App\Models\DivineBet;
use App\Models\GameEvent;
use App\Models\GameState;
use Illuminate\Database\Capsule\Manager as DB;

class StatisticsService
{
    /**
     * Get high-level game summary
     */
    public function getGameSummary(): array
    {
        $gameState = GameState::getCurrent();

        return [
            'currentEra' => $gameState->era ?? 1,
            'currentYear' => $gameState->current_year ?? 1,
            'totalHeroes' => Hero::count(),
            'livingHeroes' => Hero::where('is_alive', true)->count(),
            'totalRegions' => Region::count(),
            'totalSettlements' => Settlement::count(),
            'activeBets' => DivineBet::where('status', 'active')->count()
        ];
    }

    /**
     * Get detailed hero statistics
     */
    public function getHeroStatistics(): array
    {
        $heroes = Hero::all();

        $byRole = $heroes->groupBy('role')->map->count();
        $byStatus = $heroes->groupBy('status')->map->count();

    // Level distribution (buckets)
        $levelDistribution = [
            '1-10' => $heroes->whereBetween('level', [1, 10])->count(),
            '11-25' => $heroes->whereBetween('level', [11, 25])->count(),
            '26-50' => $heroes->whereBetween('level', [26, 50])->count(),
            '50+' => $heroes->where('level', '>', 50)->count(),
        ];

        return [
            'roleDistribution' => $byRole,
            'statusDistribution' => $byStatus,
            'levelDistribution' => $levelDistribution,
            'averageLevel' => round($heroes->avg('level') ?? 0, 1),
            'topHeroes' => Hero::orderBy('level', 'desc')->take(5)->get(['id', 'name', 'level', 'role'])
        ];
    }

    /**
     * Get detailed region statistics
     */
    public function getRegionStatistics(): array
    {
        $regions = Region::all();

        return [
            'averageProsperity' => round($regions->avg('prosperity') ?? 0, 1),
            'averageChaos' => round($regions->avg('chaos') ?? 0, 1),
            'averageMagicAffinity' => round($regions->avg('magic_affinity') ?? 0, 1),
            'totalPopulation' => $regions->sum('population_total'),
            'mostDangerous' => Region::orderBy('danger_level', 'desc')->take(5)->get(['id', 'name', 'danger_level']),
            'mostProsperous' => Region::orderBy('prosperity', 'desc')->take(5)->get(['id', 'name', 'prosperity']),
            'statusDistribution' => $regions->groupBy('status')->map->count()
        ];
    }

    /**
     * Get financial/betting statistics
     */
    public function getFinancialStatistics(): array
    {
        $bets = DivineBet::all();

        return [
            'totalBetsPlaced' => $bets->count(),
            'totalInfluenceWagered' => $bets->sum('amount'),
            'betsWon' => $bets->where('status', 'won')->count(),
            'betsLost' => $bets->where('status', 'lost')->count(),
            'activeBets' => $bets->where('status', 'active')->count(),
            'payoutRatio' => $bets->where('status', 'won')->count() > 0
                ? round($bets->where('status', 'won')->sum('payout') / max(1, $bets->where('status', 'won')->sum('amount')), 2)
                : 0
        ];
    }
}
