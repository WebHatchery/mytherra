<?php

namespace App\Services;

use App\Models\DivineBet;
use App\Models\ComboBet;
use App\Models\GameState;
use Ramsey\Uuid\Uuid;

class ComboBetService
{
    /**
     * Create a combo bet from multiple individual bets
     * All linked bets must win for the combo to pay out
     *
     * @param array $betIds Array of existing bet IDs to combine
     * @param int $totalStake Total divine favor stake for the combo
     * @return array Combo bet details
     */
    public function createComboBet(array $betIds, int $totalStake): array
    {
        if (count($betIds) < 2) {
            throw new \InvalidArgumentException('Combo bets require at least 2 individual bets');
        }

        if (count($betIds) > 5) {
            throw new \InvalidArgumentException('Combo bets cannot combine more than 5 bets');
        }

    // Fetch all bets and validate they exist and are active
        $bets = DivineBet::whereIn('id', $betIds)->get();

        if ($bets->count() !== count($betIds)) {
            throw new \InvalidArgumentException('One or more bets not found');
        }

        foreach ($bets as $bet) {
            if (!$bet->isActive()) {
                throw new \InvalidArgumentException("Bet {$bet->id} is not active");
            }
        }

    // Calculate combined odds (multiply all individual odds)
        $combinedOdds = 1.0;
        foreach ($bets as $bet) {
            $combinedOdds *= $bet->current_odds;
        }

    // Apply combo bonus (5% increase per additional bet beyond 2)
        $comboBonus = 1.0 + (0.05 * (count($betIds) - 2));
        $finalOdds = round($combinedOdds * $comboBonus, 2);

    // Calculate potential payout
        $potentialPayout = (int) round($totalStake * $finalOdds);

        $gameState = GameState::first();

        $comboBetData = [
            'id' => Uuid::uuid4()->toString(),
            'bet_ids' => $betIds,
            'total_stake' => $totalStake,
            'combined_odds' => $finalOdds,
            'potential_payout' => $potentialPayout,
            'status' => 'active',
            'placed_year' => $gameState->year ?? 1,
            'individual_bet_count' => count($betIds),
            'created_at' => now()->toIso8601String()
        ];

    // Store combo bet (we'll create a simple model for this)
        // For now, return the data - can be persisted to a combo_bets table
        return $comboBetData;
    }

    /**
     * Resolve a combo bet by checking all linked bets
     *
     * @param array $comboBetData The combo bet configuration
     * @return array Resolution result
     */
    public function resolveComboBet(array $comboBetData): array
    {
        $betIds = $comboBetData['bet_ids'];
        $bets = DivineBet::whereIn('id', $betIds)->get();

        $allWon = true;
        $results = [];

        foreach ($bets as $bet) {
            $won = $bet->status === 'won';
            $results[] = [
                'bet_id' => $bet->id,
                'bet_type' => $bet->bet_type,
                'status' => $bet->status,
                'won' => $won
            ];

            if (!$won) {
                $allWon = false;
            }
        }

    // Check if any bets are still active
        $hasActiveBets = $bets->where('status', 'active')->count() > 0;

        if ($hasActiveBets) {
            return [
                'status' => 'pending',
                'message' => 'Some bets are still active',
                'individual_results' => $results,
                'payout' => 0
            ];
        }

        return [
            'status' => $allWon ? 'won' : 'lost',
            'message' => $allWon
                ? 'All bets won! Combo payout awarded!'
                : 'One or more bets lost. Combo bet failed.',
            'individual_results' => $results,
            'payout' => $allWon ? $comboBetData['potential_payout'] : 0
        ];
    }

    /**
     * Get combo odds preview without creating the bet
     */
    public function previewComboOdds(array $betIds): array
    {
        $bets = DivineBet::whereIn('id', $betIds)->get();

        if ($bets->count() !== count($betIds)) {
            throw new \InvalidArgumentException('One or more bets not found');
        }

        $combinedOdds = 1.0;
        $betDetails = [];

        foreach ($bets as $bet) {
            $combinedOdds *= $bet->current_odds;
            $betDetails[] = [
                'id' => $bet->id,
                'type' => $bet->bet_type,
                'odds' => $bet->current_odds,
                'description' => $bet->description
            ];
        }

        $comboBonus = 1.0 + (0.05 * (count($betIds) - 2));
        $finalOdds = round($combinedOdds * $comboBonus, 2);

        return [
            'individual_bets' => $betDetails,
            'combined_odds' => $combinedOdds,
            'combo_bonus' => $comboBonus,
            'final_odds' => $finalOdds,
            'risk_level' => $this->calculateComboRiskLevel($finalOdds, count($betIds))
        ];
    }

    /**
     * Calculate risk level for combo bet
     */
    private function calculateComboRiskLevel(float $odds, int $betCount): string
    {
        if ($odds >= 15.0 || $betCount >= 4) {
            return 'extreme';
        }
        if ($odds >= 8.0) {
            return 'very_high';
        }
        if ($odds >= 5.0) {
            return 'high';
        }
        if ($odds >= 3.0) {
            return 'moderate';
        }
        return 'low';
    }
}
