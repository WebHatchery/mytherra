<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use App\Utils\Logger;
use App\Models\LocationInteractionConfig;

class HeroLocationService
{
    private $interactionTypes;
    private $locationInteractions;
    private $statusModifiers;

    public function __construct()
    {
        // Load configurations from database
        $this->interactionTypes = LocationInteractionConfig::getInteractionTypes();
        $this->statusModifiers = LocationInteractionConfig::getRegionStatusModifiers();
    }

    /**
     * Start a new interaction between a hero and a location
     */
    public function startInteraction($hero, $location, $interactionCode)
    {
        try {
            // Validate interaction type exists
            $interactionType = $this->getInteractionType($interactionCode);
            if (!$interactionType) {
                throw new Exception("Invalid interaction type: {$interactionCode}");
            }

    // Get location-specific modifiers
            $locationInteractions = LocationInteractionConfig::getLocationTypeInteractions($location['type']);
            $locationModifier = $this->getLocationModifier($locationInteractions, $interactionCode);

    // Get status-based modifiers
            $statusModifier = $this->getStatusModifier($location['status'], $interactionCode);

    // Calculate success chance
            $successChance = $this->calculateSuccessChance(
                $hero,
                $interactionType,
                $locationModifier,
                $statusModifier
            );

    // Calculate duration
            $duration = $this->calculateDuration(
                $interactionType['base_duration'],
                $locationModifier,
                $statusModifier
            );

    // Check requirements
            $this->validateRequirements($hero, $locationModifier, $interactionType);

            return [
                'success_chance' => $successChance,
                'duration' => $duration,
                'influence_cost' => $interactionType['influence_cost'],
                'cooldown' => $interactionType['cooldown_hours']
            ];
        } catch (Exception $e) {
            Logger::error("Error starting interaction: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Complete an interaction and determine results
     */
    public function completeInteraction($hero, $location, $interaction)
    {
        try {
            $success = $this->rollForSuccess($interaction['success_chance']);

            if ($success) {
                return $this->generateSuccessResults($hero, $location, $interaction);
            } else {
                return $this->generateFailureResults($hero, $location, $interaction);
            }
        } catch (Exception $e) {
            Logger::error("Error completing interaction: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if a hero can perform an interaction
     */
    public function canPerformInteraction($hero, $location, $interactionCode)
    {
        try {
            $interactionType = $this->getInteractionType($interactionCode);
            if (!$interactionType) {
                return [
                    'allowed' => false,
                    'reason' => 'Invalid interaction type'
                ];
            }

    // Get location-specific modifiers
            $locationInteractions = LocationInteractionConfig::getLocationTypeInteractions($location['type']);
            $locationModifier = $this->getLocationModifier($locationInteractions, $interactionCode);

    // Check level requirement
            if ($hero['level'] < ($locationModifier['min_hero_level'] ?? 1)) {
                return [
                    'allowed' => false,
                    'reason' => 'Hero level too low'
                ];
            }

    // Check cooldown
            if ($this->isOnCooldown($hero, $interactionCode)) {
                return [
                    'allowed' => false,
                    'reason' => 'Interaction on cooldown'
                ];
            }

    // Check influence cost
            if (($hero['divine_favor'] ?? 0) < $interactionType['influence_cost']) {
                return [
                    'allowed' => false,
                    'reason' => 'Insufficient divine favor'
                ];
            }

            return [
                'allowed' => true,
                'reason' => null
            ];
        } catch (Exception $e) {
            Logger::error("Error checking interaction possibility: " . $e->getMessage());
            return [
                'allowed' => false,
                'reason' => 'Internal error'
            ];
        }
    }

    private function calculateSuccessChance($hero, $interactionType, $locationModifier, $statusModifier)
    {
        $baseChance = $interactionType['base_success_chance'];

    // Apply location modifier
        $baseChance *= ($locationModifier['success_modifier'] ?? 1.0);

    // Apply status modifier
        $baseChance *= ($statusModifier['success_modifier'] ?? 1.0);

    // Apply hero level bonus (1% per level)
        $baseChance *= (1 + ($hero['level'] * 0.01));

    // Apply trait bonus if hero has required trait
        if (
            isset($locationModifier['required_trait']) &&
            isset($hero['traits'][$locationModifier['required_trait']])
        ) {
            $baseChance *= (1 + $locationModifier['trait_bonus']);
        }

        return min(0.95, max(0.05, $baseChance));
    }

    private function calculateDuration($baseDuration, $locationModifier, $statusModifier)
    {
        $duration = $baseDuration;

    // Apply location modifier
        $duration *= ($locationModifier['duration_modifier'] ?? 1.0);

    // Apply status modifier
        $duration *= ($statusModifier['duration_modifier'] ?? 1.0);

        return max(1, round($duration));
    }

    private function validateRequirements($hero, $locationModifier, $interactionType)
    {
        // Check level requirement
        if ($hero['level'] < ($locationModifier['min_hero_level'] ?? 1)) {
            throw new Exception("Hero level too low for this interaction");
        }

    // Check influence cost
        if (($hero['divine_favor'] ?? 0) < $interactionType['influence_cost']) {
            throw new Exception("Insufficient divine favor");
        }

    // Check cooldown
        if ($this->isOnCooldown($hero, $interactionType['code'])) {
            throw new Exception("This interaction is still on cooldown");
        }
    }

    private function getInteractionType($code)
    {
        foreach ($this->interactionTypes as $type) {
            if ($type['code'] === $code) {
                return $type;
            }
        }
        return null;
    }

    private function getLocationModifier($locationInteractions, $interactionCode)
    {
        foreach ($locationInteractions as $interaction) {
            if ($interaction['interaction_code'] === $interactionCode) {
                return $interaction;
            }
        }
        return [];
    }

    private function getStatusModifier($statusCode, $interactionCode)
    {
        foreach ($this->statusModifiers as $modifier) {
            if (
                $modifier['status_code'] === $statusCode &&
                $modifier['interaction_code'] === $interactionCode
            ) {
                return $modifier;
            }
        }
        return [];
    }

    private function isOnCooldown($hero, $interactionCode)
    {
        if (!isset($hero['last_interactions'][$interactionCode])) {
            return false;
        }

        $lastInteraction = $hero['last_interactions'][$interactionCode];
        $interactionType = $this->getInteractionType($interactionCode);
        $cooldownHours = $interactionType['cooldown_hours'];

        $cooldownEnds = strtotime($lastInteraction) + ($cooldownHours * 3600);
        return time() < $cooldownEnds;
    }

    private function rollForSuccess($chance)
    {
        return mt_rand() / mt_getrandmax() <= $chance;
    }

    private function generateSuccessResults($hero, $location, $interaction)
    {
        $results = [
            'success' => true,
            'rewards' => [],
            'messages' => []
        ];

        switch ($interaction['code']) {
            case 'explore':
                $results['rewards'] = $this->generateExplorationRewards($location);
                break;

            case 'gather_resources':
                $results['rewards'] = $this->generateResourceRewards($location);
                break;

            case 'investigate_rumors':
                $results['rewards'] = $this->generateRumorResults($location);
                break;

            case 'establish_camp':
                $results['rewards'] = $this->generateCampResults($location);
                break;

            case 'study_magic':
                $results['rewards'] = $this->generateMagicStudyResults($location);
                break;
        }

        return $results;
    }

    private function generateFailureResults($hero, $location, $interaction)
    {
        return [
            'success' => false,
            'consequences' => $this->generateFailureConsequences($interaction['code']),
            'messages' => ['The interaction was unsuccessful.']
        ];
    }

    private function generateExplorationRewards($location)
    {
        // Implementation would vary based on location type and status
        return [
            'discovery_chance' => 0.2,
            'resource_points' => mt_rand(1, 5),
            'experience' => mt_rand(10, 30)
        ];
    }

    private function generateResourceRewards($location)
    {
        return [
            'resources' => [
                'common' => mt_rand(3, 8),
                'uncommon' => mt_rand(0, 3),
                'rare' => mt_rand(0, 1)
            ],
            'experience' => mt_rand(5, 15)
        ];
    }

    private function generateRumorResults($location)
    {
        return [
            'new_locations_revealed' => mt_rand(0, 2),
            'quest_hints' => mt_rand(0, 1),
            'experience' => mt_rand(15, 25)
        ];
    }

    private function generateCampResults($location)
    {
        return [
            'rest_bonus' => mt_rand(10, 20),
            'preparation_bonus' => mt_rand(5, 15),
            'experience' => mt_rand(20, 40)
        ];
    }

    private function generateMagicStudyResults($location)
    {
        return [
            'magic_knowledge' => mt_rand(1, 5),
            'magic_influence' => mt_rand(5, 15),
            'experience' => mt_rand(25, 50)
        ];
    }

    private function generateFailureConsequences($interactionCode)
    {
        $consequences = [
            'explore' => ['Lost some supplies', 'Slightly tired'],
            'gather_resources' => ['Wasted time', 'Minor injury'],
            'investigate_rumors' => ['False information', 'Time wasted'],
            'establish_camp' => ['Resources wasted', 'Poor location chosen'],
            'study_magic' => ['Magical backlash', 'Knowledge confused']
        ];

        return $consequences[$interactionCode] ?? ['Generic failure'];
    }
}
