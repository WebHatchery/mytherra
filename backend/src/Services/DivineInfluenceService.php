<?php

declare(strict_types=1);

namespace App\Services;

use App\Utils\Logger;
use App\Models\Hero;
use App\Models\Region;
use App\Models\Settlement;
use App\Models\Landmark;
use App\Models\Player;
use App\Models\GameState;
use App\Models\InfluenceHistory;
use App\Models\GameEvent;

class DivineInfluenceService
{
    public function __construct()
    {
        // All database operations now use Eloquent ORM
    }

    /**
     * Calculate target resistance to divine influence
     */
    public function calculateTargetResistance($targetId, $targetType, $influenceType)
    {
        try {
            $baseResistance = 0;

            switch ($targetType) {
                case 'hero':
                    // Heroes have varying resistance based on their power level and role
                    $hero = Hero::where('id', $targetId)->first(['level', 'role']);

                    if ($hero) {
                        $baseResistance = $hero->level * 5;
                        if ($hero->role === 'mystic' || $hero->role === 'priest') {
                            $baseResistance += 20;
                        }
                    }
                    break;

                case 'region':
                    // Regions resist based on their magic affinity and chaos levels
                    $region = Region::where('id', $targetId)->first(['magic_affinity', 'chaos']);

                    if ($region) {
                        $baseResistance = ($region->magic_affinity + $region->chaos) / 2;
                    }
                    break;

                case 'settlement':
                    // Settlements resist based on population and prosperity
                    $settlement = Settlement::where('id', $targetId)->first(['population', 'prosperity']);

                    if ($settlement) {
                        $baseResistance = min(75, sqrt($settlement->population / 100) + $settlement->prosperity / 2);
                    }
                    break;

                case 'landmark':
                    // Landmarks resist based on their magic and danger levels
                    $landmark = Landmark::where('id', $targetId)->first(['magic_level', 'danger_level']);

                    if ($landmark) {
                        $baseResistance = max($landmark->magic_level, $landmark->danger_level);
                    }
                    break;
            }

    // Adjust resistance based on influence type
            switch ($influenceType) {
                case 'bless':
                    $baseResistance *= 0.8;

    // Blessings are easier to apply
                    break;
                case 'curse':
                    $baseResistance *= 1.5;

    // Curses are harder to apply
                    break;
                case 'guide':
                    $baseResistance *= 1.2;

    // Guidance is moderately difficult
                    break;
            }

            return min(95, max(5, $baseResistance));
        } catch (\Exception $e) {
            Logger::error("Error calculating target resistance: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Apply divine influence effect to target
     *
     * @param string $targetId ID of the target entity
     * @param string $targetType Type of target (hero, region, settlement, landmark)
     * @param string $influenceType Type of influence (bless, curse, guide, etc.)
     * @param string $strength Strength of the influence (subtle, moderate, significant)
     * @param string $description Description of the influence action
     * @return array Result of influence application
     */
    public function applyInfluence(
        string $targetId,
        string $targetType,
        string $influenceType,
        string $strength,
        string $description
    ): array {
        try {
            // Get target entity
            $target = match ($targetType) {
                'hero' => Hero::find($targetId),
                'region' => Region::find($targetId),
                'settlement' => Settlement::find($targetId),
                'landmark' => Landmark::find($targetId),
                default => null
            };

            if (!$target) {
                return [
                    'success' => false,
                    'message' => "Target {$targetId} of type {$targetType} not found"
                ];
            }

    // Calculate cost and effectiveness
            $cost = $this->calculateInfluenceCost($targetId, $targetType, $influenceType, $strength);
            $modifiers = $this->calculateModifiers($influenceType, $targetType, $targetId);

    // Check if player has enough divine favor
            $player = Player::getSinglePlayer();
            if ($player->getDivineFavor() < $cost['cost']) {
                return [
                    'success' => false,
                    'cost' => $cost['cost'],
                    'message' => "Insufficient divine favor"
                ];
            }

    // Apply effects to target
            $effects = match ($targetType) {
                'hero' => $this->applyHeroInfluence($target, $influenceType, $strength),
                'region' => $this->applyRegionInfluence($target, $influenceType, $strength),
                'settlement' => $this->applySettlementInfluence($target, $influenceType, $strength),
                'landmark' => $this->applyLandmarkInfluence($target, $influenceType, $strength),
                default => throw new \Exception("Invalid target type: {$targetType}")
            };

    // Spend divine favor
            if (!$player->spendDivineFavor($cost['cost'])) {
                return [
                    'success' => false,
                    'cost' => $cost['cost'],
                    'message' => "Failed to spend divine favor"
                ];
            }

    // Record influence history
            $this->recordInfluenceHistory(
                $targetId,
                $targetType,
                $influenceType,
                $strength,
                $description,
                $effects
            );

    // Return success response with effects
            return [
                'success' => true,
                'cost' => $cost['cost'],
                'effects' => [
                    'prosperityChange' => $modifiers['prosperityEffect'],
                    'heroAttractionChange' => $modifiers['heroAttractionModifier'],
                    'eventProbabilityChange' => $modifiers['eventProbabilityModifier'],
                    'otherEffects' => $effects
                ],
                'message' => "Divine influence successfully applied",
                'targetName' => $target->name
            ];
        } catch (\Exception $e) {
            Logger::error("Error applying divine influence: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Error: " . $e->getMessage()
            ];
        }
    }

    private function getTargetType($target)
    {
        if (strpos(get_class($target), 'Hero') !== false) {
            return 'hero';
        }
        if (strpos(get_class($target), 'Region') !== false) {
            return 'region';
        }
        if (strpos(get_class($target), 'Settlement') !== false) {
            return 'settlement';
        }
        if (strpos(get_class($target), 'Landmark') !== false) {
            return 'landmark';
        }
        return null;
    }

    private function applyHeroInfluence($hero, $influenceType, $strength)
    {
        $effects = [];
        switch ($influenceType) {
            case 'empower':
                $hero->power_level += ceil($strength / 20);
                $effects[] = "Power level increased";
                break;
            case 'guide':
                $hero->guidance_level = min(100, ($hero->guidance_level ?? 0) + ceil($strength / 10));
                $effects[] = "Guidance level increased";
                break;
            case 'inspire':
                $hero->inspiration = min(100, ($hero->inspiration ?? 0) + ceil($strength / 5));
                $effects[] = "Inspiration increased";
                break;
        }
        $hero->save();
        return $effects;
    }

    private function applyRegionInfluence($region, $influenceType, $strength)
    {
        $effects = [];
        switch ($influenceType) {
            case 'bless':
                $region->prosperity = min(100, $region->prosperity + ceil($strength / 10));
                $effects[] = "Prosperity increased";
                break;
            case 'curse':
                $region->chaos = min(100, $region->chaos + ceil($strength / 7));
                $effects[] = "Chaos increased";
                break;
            case 'guide_research':
                $region->magic_affinity = min(100, $region->magic_affinity + ceil($strength / 12));
                $effects[] = "Magic affinity increased";
                break;
        }
        $region->save();
        return $effects;
    }

    private function applySettlementInfluence($settlement, $influenceType, $strength)
    {
        $effects = [];
        switch ($influenceType) {
            case 'bless':
                $settlement->prosperity = min(100, $settlement->prosperity + ceil($strength / 10));
                $effects[] = "Prosperity increased";
                break;
            case 'protect':
                $settlement->defensibility = min(100, $settlement->defensibility + ceil($strength / 15));
                $effects[] = "Defensibility increased";
                break;
            case 'develop':
                $settlement->development_level = min(100, ($settlement->development_level ?? 0) + ceil($strength / 20));
                $effects[] = "Development level increased";
                break;
        }
        $settlement->save();
        return $effects;
    }

    private function applyLandmarkInfluence($landmark, $influenceType, $strength)
    {
        $effects = [];
        switch ($influenceType) {
            case 'bless':
                $landmark->magic_level = min(100, $landmark->magic_level + ceil($strength / 10));
                $effects[] = "Magic level increased";
                break;
            case 'purify':
                $landmark->danger_level = max(0, $landmark->danger_level - ceil($strength / 15));
                $effects[] = "Danger level decreased";
                break;
            case 'enhance':
                $landmark->power_level = min(100, ($landmark->power_level ?? 0) + ceil($strength / 12));
                $effects[] = "Power level increased";
                break;
        }
        $landmark->save();
        return $effects;
    }

    private function recordInfluenceEvent($targetId, $targetType, $influenceType, $strength, $description, $effects)
    {
        try {
            GameEvent::create([
                'id' => 'event-' . bin2hex(random_bytes(8)),
                'title' => 'Divine Influence Applied',
                'description' => $description,
                'type' => 'divine_influence',
                'status' => 'completed',
                'region_id' => $targetType === 'region' ? $targetId : null,
                'timestamp' => date('c'),
                'year' => $this->getCurrentGameYear()
            ]);
        } catch (\Exception $e) {
            Logger::error("Error recording influence event: " . $e->getMessage());

    // Don't throw - this is a non-critical operation
        }
    }

    private function getCurrentGameYear()
    {
        try {
            $gameState = GameState::getCurrent();
            return $gameState ? $gameState->current_year : 1;
        } catch (\Exception $e) {
            Logger::error("Error getting game year: " . $e->getMessage());
            return 1;
        }
    }

    private function calculateDiminishingReturns(string $targetId): float
    {
        // TODO: Implement history-based reduction
        // For now, return full effectiveness
        return 1.0;
    }

    public function calculateDivinePointRecovery(string $playerId, int $activeBets = 0): int
    {
        $baseRecovery = 10;

    // Base points per year
        $pointsPerBet = 2;

    // Additional points for each active bet

        return $baseRecovery + ($activeBets * $pointsPerBet);
    }

    private function recordInfluenceHistory(string $targetId, string $targetType, string $influenceType, string $strength, string $description, array $effects)
    {
        try {
            InfluenceHistory::create([
                'target_id' => $targetId,
                'target_type' => $targetType,
                'influence_type' => $influenceType,
                'strength' => $strength,
                'description' => $description,
                'effects' => $effects,
                'game_year' => $this->getCurrentGameYear()
            ]);
        } catch (\Exception $e) {
            Logger::error("Error recording influence history: " . $e->getMessage());

    // Non-critical operation, don't throw
        }
    }

    private function calculateModifiers(string $influenceType, string $targetType, string $targetId): array
    {
        try {
            // Get base values
            $base = match ($influenceType) {
                'environmental' => [
                    'prosperityEffect' => 5,
                    'heroAttractionModifier' => 0.1,
                    'eventProbabilityModifier' => 0.05
                ],
                'inspirational' => [
                    'prosperityEffect' => 8,
                    'heroAttractionModifier' => 0.2,
                    'eventProbabilityModifier' => 0.1
                ],
                'coincidental' => [
                    'prosperityEffect' => 12,
                    'heroAttractionModifier' => 0.3,
                    'eventProbabilityModifier' => 0.15
                ],
                'direct' => [
                    'prosperityEffect' => 20,
                    'heroAttractionModifier' => 0.5,
                    'eventProbabilityModifier' => 0.25
                ],
                default => throw new \Exception("Invalid influence type: {$influenceType}")
            };

    // Add target-specific modifiers
            $targetResistance = $this->calculateTargetResistance($targetId, $targetType, $influenceType);
            $resistanceModifier = 1 - ($targetResistance / 100);
            $diminishingReturns = $this->calculateDiminishingReturns($targetId);

    // Calculate resonance bonus
            $resonanceBonus = 1.0;
            if ($targetType === 'region') {
                $divineResonance = Region::where('id', $targetId)->value('divine_resonance');

                if ($divineResonance !== null) {
                    // Formula: 1 + (resonance - 50) * 0.01
                    $resonanceBonus += ($divineResonance - 50) * 0.01;
                }
            }

    // Apply all modifiers
            $finalModifier = $resistanceModifier * $diminishingReturns * $resonanceBonus;

            return [
                'prosperityEffect' => round($base['prosperityEffect'] * $finalModifier),
                'heroAttractionModifier' => round($base['heroAttractionModifier'] * $finalModifier * 100) / 100,
                'eventProbabilityModifier' => round($base['eventProbabilityModifier'] * $finalModifier * 100) / 100
            ];
        } catch (\Exception $e) {
            Logger::error("Error calculating modifiers: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate the cost of applying divine influence
     *
     * @param string $targetId ID of the target entity
     * @param string $targetType Type of target (hero, region, settlement, landmark)
     * @param string $influenceType Type of influence (bless, curse, guide, etc.)
     * @param string $strength Strength of the influence (subtle, moderate, significant)
     * @return array Cost calculation result
     */
    public function calculateInfluenceCost(string $targetId, string $targetType, string $influenceType, string $strength): array
    {
        try {
            // Base costs for different influence types
            $baseCosts = [
                'environmental' => 5,
                'inspirational' => 10,
                'coincidental' => 15,
                'direct' => 25
            ];

    // Strength multipliers
            $strengthMultipliers = [
                'subtle' => 1.0,
                'minor' => 1.5,
                'moderate' => 2.5,
                'significant' => 4.0
            ];

            if (!isset($baseCosts[$influenceType])) {
                throw new \Exception("Invalid influence type: {$influenceType}");
            }

            if (!isset($strengthMultipliers[$strength])) {
                throw new \Exception("Invalid strength level: {$strength}");
            }

    // Calculate base cost with strength multiplier
            $cost = round($baseCosts[$influenceType] * $strengthMultipliers[$strength]);

    // Additional cost for certain target types
            if ($targetType === 'region') {
                $cost = round($cost * 1.5);

    // Regions are more expensive to influence
            }

    // Ensure minimum cost of 1
            $cost = max(1, $cost);

    // Calculate effectiveness estimate
            $modifiers = $this->calculateModifiers($influenceType, $targetType, $targetId);

    // Get target name using Eloquent
            $targetName = match ($targetType) {
                'hero' => Hero::where('id', $targetId)->value('name'),
                'settlement' => Settlement::where('id', $targetId)->value('name'),
                'region' => Region::where('id', $targetId)->value('name'),
                'landmark' => Landmark::where('id', $targetId)->value('name'),
                default => throw new \Exception("Invalid target type: {$targetType}")
            } ?? "Unknown {$targetType}";

            return [
                'cost' => $cost,
                'effectivenessEstimate' => $modifiers,
                'targetName' => $targetName
            ];
        } catch (\Exception $e) {
            Logger::error("Error calculating influence cost: " . $e->getMessage());
            throw $e;
        }
    }
}
