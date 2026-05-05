<?php

namespace App\Services;

use Exception;
use App\Utils\Logger;
use App\Models\HeroAlignmentConfig;

class HeroAlignmentService
{
    private $alignmentConfig;
    private $traits;
    private $modifiers;
    private $eventResponses;

    public function __construct()
    {
        // Load configuration from database
        $this->traits = HeroAlignmentConfig::getTraits();
        $this->modifiers = HeroAlignmentConfig::getModifiers();
        $this->eventResponses = HeroAlignmentConfig::getEventResponses();
    }

    /**
     * Update hero's alignment based on an event or action
     */
    public function processAlignmentChange($hero, $triggerType, $triggerCondition)
    {
        try {
            $modifiers = HeroAlignmentConfig::getModifiers($triggerType, $triggerCondition);

            foreach ($modifiers as $modifier) {
                $this->applyTraitModification(
                    $hero,
                    $modifier['trait_code'],
                    $modifier['modifier_value']
                );
            }

            return true;
        } catch (Exception $e) {
            Logger::error("Error processing alignment change: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Predict hero's response to an event based on their traits
     */
    public function predictEventResponse($hero, $eventType)
    {
        try {
            $responses = HeroAlignmentConfig::getEventResponses($eventType);
            $bestResponse = null;
            $highestProbability = 0;

            foreach ($responses as $response) {
                $probability = $this->calculateResponseProbability(
                    $hero,
                    $response['required_trait_code'],
                    $response['probability']
                );

                if ($probability > $highestProbability) {
                    $highestProbability = $probability;
                    $bestResponse = $response;
                }
            }

            return $bestResponse ? [
                'response_type' => $bestResponse['response_type'],
                'probability' => $highestProbability,
                'influence_modifier' => $bestResponse['influence_modifier']
            ] : null;
        } catch (Exception $e) {
            Logger::error("Error predicting event response: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Calculate trait influence on decisions
     */
    public function calculateTraitInfluence($hero, $traitCode)
    {
        foreach ($this->traits as $trait) {
            if ($trait['code'] === $traitCode) {
                $traitLevel = $hero['traits'][$traitCode] ?? 0;
                return $traitLevel * $trait['base_influence'];
            }
        }
        return 0;
    }

    /**
     * Apply a modification to a hero's trait
     */
    private function applyTraitModification($hero, $traitCode, $modifierValue)
    {
        try {
            $currentValue = $hero['traits'][$traitCode] ?? 0;
            $opposingTrait = $this->getOpposingTrait($traitCode);

    // Calculate new trait value
            $newValue = max(0, min(100, $currentValue + $modifierValue));
            $hero['traits'][$traitCode] = $newValue;

    // If trait has an opposite, reduce it proportionally
            if ($opposingTrait && isset($hero['traits'][$opposingTrait])) {
                $hero['traits'][$opposingTrait] = max(
                    0,
                    $hero['traits'][$opposingTrait] - ($modifierValue * 0.5)
                );
            }

            return $hero;
        } catch (Exception $e) {
            Logger::error("Error applying trait modification: " . $e->getMessage());
            return $hero;
        }
    }

    /**
     * Calculate probability of a specific response based on hero's traits
     */
    private function calculateResponseProbability($hero, $requiredTrait, $baseProbability)
    {
        $traitValue = $hero['traits'][$requiredTrait] ?? 0;
        $traitInfluence = $this->calculateTraitInfluence($hero, $requiredTrait);

    // Probability increases with trait level but has diminishing returns
        $modifier = 1 + (($traitValue / 100) * $traitInfluence);
        return min(1.0, $baseProbability * $modifier);
    }

    /**
     * Get the opposing trait for a given trait
     */
    private function getOpposingTrait($traitCode)
    {
        foreach ($this->traits as $trait) {
            if ($trait['code'] === $traitCode) {
                return $trait['opposing_trait_code'];
            }
        }
        return null;
    }

    /**
     * Get dominant trait in each category
     */
    public function getDominantTraits($hero)
    {
        $dominantTraits = [];
        $categories = ['personality', 'morality', 'motivation'];

        foreach ($categories as $category) {
            $highestValue = 0;
            $dominantTrait = null;

            foreach ($this->traits as $trait) {
                if ($trait['category'] === $category) {
                    $traitValue = $hero['traits'][$trait['code']] ?? 0;
                    if ($traitValue > $highestValue) {
                        $highestValue = $traitValue;
                        $dominantTrait = $trait['code'];
                    }
                }
            }

            if ($dominantTrait) {
                $dominantTraits[$category] = $dominantTrait;
            }
        }

        return $dominantTraits;
    }

    /**
     * Calculate overall alignment score (-100 to 100)
     */
    public function calculateAlignmentScore($hero)
    {
        $score = 0;
        $totalWeight = 0;

        foreach ($this->traits as $trait) {
            $traitValue = $hero['traits'][$trait['code']] ?? 0;
            $influence = $trait['base_influence'];

    // Positive traits contribute positively, negative traits negatively
            $isPositive = in_array($trait['code'], ['altruistic', 'honorable', 'cautious']);
            $contribution = $isPositive ? $traitValue : -$traitValue;

            $score += $contribution * $influence;
            $totalWeight += $influence;
        }

        return $totalWeight > 0 ?
            min(100, max(-100, $score / $totalWeight)) : 0;
    }
}
