<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Hero;
use App\Models\Region;
use App\Models\Settlement;
use App\Models\DivineBet;
use App\Models\GameEvent;
use App\Models\GameState;
use App\Models\Building;
use App\Models\Landmark;
use App\Models\ResourceNode;

class ExportService
{
    private function timestamp(): string
    {
        return (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
    }

    /**
     * Export full world snapshot
     */
    public function exportFullSnapshot(): array
    {
        $gameState = GameState::getCurrent();

        return [
            'exportedAt' => $this->timestamp(),
            'version' => '1.0',
            'gameState' => [
                'era' => $gameState->era ?? 1,
                'year' => $gameState->current_year ?? 1,
                'tick' => $gameState->tick ?? 0,
            ],
            'regions' => $this->exportRegions(),
            'heroes' => $this->exportHeroes(),
            'settlements' => $this->exportSettlements(),
            'buildings' => $this->exportBuildings(),
            'landmarks' => $this->exportLandmarks(),
            'resourceNodes' => $this->exportResourceNodes(),
            'divineBets' => $this->exportDivineBets(),
            'events' => $this->exportEvents()
        ];
    }

    /**
     * Export by specific type
     */
    public function exportByType(string $type): array
    {
        $methodMap = [
            'regions' => 'exportRegions',
            'heroes' => 'exportHeroes',
            'settlements' => 'exportSettlements',
            'buildings' => 'exportBuildings',
            'landmarks' => 'exportLandmarks',
            'resources' => 'exportResourceNodes',
            'bets' => 'exportDivineBets',
            'events' => 'exportEvents'
        ];

        if (!isset($methodMap[$type])) {
            throw new \InvalidArgumentException("Unknown export type: {$type}");
        }

        return [
            'exportedAt' => $this->timestamp(),
            'type' => $type,
            'data' => $this->{$methodMap[$type]}()
        ];
    }

    /**
     * Export all regions with relationships
     */
    private function exportRegions(): array
    {
        return Region::with(['settlements', 'landmarks', 'heroes'])
            ->get()
            ->map(function ($region) {
                return [
                    'id' => $region->id,
                    'name' => $region->name,
                    'color' => $region->color,
                    'prosperity' => $region->prosperity,
                    'chaos' => $region->chaos,
                    'magic_affinity' => $region->magic_affinity,
                    'status' => $region->status,
                    'danger_level' => $region->danger_level,
                    'population_total' => $region->population_total,
                    'climate_type' => $region->climate_type,
                    'cultural_influence' => $region->cultural_influence,
                    'divine_resonance' => $region->divine_resonance,
                    'regional_traits' => $region->regional_traits,
                    'settlement_count' => $region->settlements->count(),
                    'hero_count' => $region->heroes->count(),
                    'landmark_count' => $region->landmarks->count()
                ];
            })
            ->toArray();
    }

    /**
     * Export all heroes
     */
    private function exportHeroes(): array
    {
        return Hero::with('region')
            ->get()
            ->map(function ($hero) {
                return [
                    'id' => $hero->id,
                    'name' => $hero->name,
                    'region_id' => $hero->region_id,
                    'region_name' => $hero->region?->name,
                    'role' => $hero->role,
                    'level' => $hero->level,
                    'age' => $hero->age,
                    'is_alive' => $hero->is_alive,
                    'status' => $hero->status,
                    'alignment' => $hero->alignment,
                    'personality_traits' => $hero->personality_traits,
                    'feats' => $hero->feats,
                    'death_reason' => $hero->death_reason
                ];
            })
            ->toArray();
    }

    /**
     * Export all settlements
     */
    private function exportSettlements(): array
    {
        return Settlement::with(['buildings'])
            ->get()
            ->map(function ($settlement) {
                return [
                    'id' => $settlement->id,
                    'name' => $settlement->name,
                    'region_id' => $settlement->region_id,
                    'type' => $settlement->type,
                    'population' => $settlement->population,
                    'prosperity' => $settlement->prosperity,
                    'defensibility' => $settlement->defensibility,
                    'status' => $settlement->status,
                    'building_count' => $settlement->buildings->count()
                ];
            })
            ->toArray();
    }

    /**
     * Export all buildings
     */
    private function exportBuildings(): array
    {
        return Building::all()
            ->map(function ($building) {
                return [
                    'id' => $building->id,
                    'name' => $building->name,
                    'settlement_id' => $building->settlement_id,
                    'type' => $building->type,
                    'condition' => $building->condition,
                    'status' => $building->status
                ];
            })
            ->toArray();
    }

    /**
     * Export all landmarks
     */
    private function exportLandmarks(): array
    {
        return Landmark::all()
            ->map(function ($landmark) {
                return [
                    'id' => $landmark->id,
                    'name' => $landmark->name,
                    'region_id' => $landmark->region_id,
                    'type' => $landmark->type,
                    'status' => $landmark->status,
                    'discovered_year' => $landmark->discovered_year
                ];
            })
            ->toArray();
    }

    /**
     * Export all resource nodes
     */
    private function exportResourceNodes(): array
    {
        return ResourceNode::all()
            ->map(function ($node) {
                return [
                    'id' => $node->id,
                    'name' => $node->name,
                    'region_id' => $node->region_id,
                    'settlement_id' => $node->settlement_id,
                    'type' => $node->type,
                    'status' => $node->status,
                    'output' => $node->output,
                    'effective_output' => $node->getEffectiveOutput()
                ];
            })
            ->toArray();
    }

    /**
     * Export all divine bets
     */
    private function exportDivineBets(): array
    {
        return DivineBet::all()
            ->map(function ($bet) {
                return [
                    'id' => $bet->id,
                    'player_id' => $bet->player_id,
                    'bet_type' => $bet->bet_type,
                    'target_id' => $bet->target_id,
                    'description' => $bet->description,
                    'timeframe' => $bet->timeframe,
                    'confidence' => $bet->confidence,
                    'divine_favor_stake' => $bet->divine_favor_stake,
                    'potential_payout' => $bet->potential_payout,
                    'current_odds' => $bet->current_odds,
                    'status' => $bet->status,
                    'placed_year' => $bet->placed_year,
                    'resolved_year' => $bet->resolved_year,
                    'resolution_notes' => $bet->resolution_notes,
                    'placed_at' => $bet->created_at?->toIso8601String(),
                    'updated_at' => $bet->updated_at?->toIso8601String()
                ];
            })
            ->toArray();
    }

    /**
     * Export recent events (last 1000)
     */
    private function exportEvents(): array
    {
        return GameEvent::orderBy('created_at', 'desc')
            ->take(1000)
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'type' => $event->type,
                    'title' => $event->title,
                    'description' => $event->description,
                    'region_id' => $event->region_id,
                    'related_region_ids' => $event->related_region_ids,
                    'related_hero_ids' => $event->related_hero_ids,
                    'year' => $event->year,
                    'created_at' => $event->created_at?->toIso8601String()
                ];
            })
            ->toArray();
    }
}
