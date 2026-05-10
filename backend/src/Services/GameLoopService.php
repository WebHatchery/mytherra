<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Region;
use App\Models\Hero;
use App\Models\Settlement;
use App\Actions\RegionActions;
use App\Actions\HeroActions;
use App\Actions\BettingActions;
use App\Actions\SettlementActions;

class GameLoopService
{
    private RegionActions $regionActions;
    private HeroActions $heroActions;
    private BettingActions $bettingActions;
    private SettlementActions $settlementActions;
    private SettlementEvolutionService $settlementEvolution;

    public function __construct()
    {
        $this->regionActions = new RegionActions();
        $this->heroActions = new HeroActions();
        $this->bettingActions = new BettingActions();
        $this->settlementActions = new SettlementActions();
        $this->settlementEvolution = new SettlementEvolutionService();
    }

    public function processRegions(int $currentYear)
    {
        $regions = Region::all();
        foreach ($regions as $region) {
            try {
                $this->regionActions->processRegionTick($region, $currentYear);
            } catch (\Exception $e) {
                \Log::error("Error processing region {$region->id}: " . $e->getMessage());
            }
        }
    }

    public function processHeroes(int $currentYear)
    {
        $heroes = Hero::where('is_alive', true)->get();
        foreach ($heroes as $hero) {
            try {
                $this->heroActions->processHeroTick($hero, $currentYear);
            } catch (\Exception $e) {
                \Log::error("Error processing hero {$hero->id}: " . $e->getMessage());
            }
        }
    }

    public function processSettlements(int $currentYear)
    {
        $settlements = Settlement::all();
        foreach ($settlements as $settlement) {
            try {
                $this->settlementEvolution->processSettlementEvolution($settlement, $currentYear);
            } catch (\Exception $e) {
                \Log::error("Error processing settlement {$settlement->id}: " . $e->getMessage());
            }
        }
    }

    public function processExpiredBets(int $currentYear)
    {
        try {
            $this->bettingActions->processExpiredBets();
        } catch (\Exception $e) {
            \Log::error("Error processing expired bets: " . $e->getMessage());
        }
    }
}
