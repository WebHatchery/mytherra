<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\HeroRepository;
use App\Repositories\SettlementRepository;
use App\Repositories\HeroSettlementInteractionRepository;
use App\Models\Hero;
use App\Utils\Logger;

class HeroLifecycleService
{
    private $heroRepository;
    private $settlementRepository;
    private $interactionRepository;
    private const CHANCE_TO_MOVE = 0.3;
    private const BASE_LEVEL_UP_CHANCE = 0.4;
    private const LEVEL_UP_DIFFICULTY_FACTOR = 0.85;
    private const MAX_LEVELS_PER_YEAR = 3;
    private const LOW_LEVEL_THRESHOLD = 5;
    private const HIGH_LEVEL_THRESHOLD = 25;
    private const LOW_LEVEL_MULTIPLIER = 2.0;
    private const MID_LEVEL_MULTIPLIER = 1.5;
    private const HIGH_LEVEL_MULTIPLIER = 0.75;

    public function __construct(
        HeroRepository $heroRepository,
        SettlementRepository $settlementRepository,
        HeroSettlementInteractionRepository $interactionRepository
    ) {
        $this->heroRepository = $heroRepository;
        $this->settlementRepository = $settlementRepository;
        $this->interactionRepository = $interactionRepository;
    }

    /**
     * Process complete lifecycle for a hero including aging, leveling, movement, and mortality
     */
    public function processHeroLifecycle(string $heroId, int $currentYear): ?array
    {
        try {
            $hero = $this->heroRepository->getHeroById($heroId);
            if (!$hero || !$hero['is_alive']) {
                return null;
            }

            $changes = [];
            $events = [];
            $newFeats = [];

    // Age the hero
            $this->ageHero($hero, $changes);

    // Process leveling
            $levelingResult = $this->processHeroLeveling($hero, $currentYear);
            if ($levelingResult['leveledUp']) {
                $changes['level'] = $levelingResult['newLevel'];
                $events[] = $levelingResult['eventDescription'];
                $newFeats = array_merge($newFeats, $levelingResult['newFeats']);
            }

    // Process movement
            $movementResult = $this->processHeroMovement($hero, $currentYear);
            if ($movementResult['moved']) {
                $changes['region_id'] = $movementResult['newRegionId'];
                $events[] = $movementResult['eventDescription'];
                if (!empty($movementResult['newFeats'])) {
                    $newFeats = array_merge($newFeats, $movementResult['newFeats']);
                }
            }

    // Check mortality
            if ($this->shouldDieNaturally($hero) || $this->shouldDieFromDanger($hero)) {
                $deathReason = $this->generateDeathReason($hero);
                $this->processHeroDeath($hero, $currentYear, $deathReason);
                return [
                    'hero' => $hero,
                    'died' => true,
                    'deathReason' => $deathReason,
                    'events' => ["Hero died: $deathReason"]
                ];
            }

    // Apply changes and update feats
            if (!empty($newFeats)) {
                $currentFeats = json_decode($hero['feats'] ?? '[]', true);
                $changes['feats'] = json_encode(array_merge($currentFeats, $newFeats));
            }

            if (!empty($changes)) {
                $hero = $this->heroRepository->updateHero($heroId, $changes);
            }

            return [
                'hero' => $hero,
                'changes' => $changes,
                'events' => $events,
                'newFeats' => $newFeats,
                'died' => false
            ];
        } catch (\Exception $e) {
            Logger::error("Error processing hero lifecycle: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process hero leveling with accelerated leveling for low-level heroes
     */
    private function processHeroLeveling(array $hero, int $currentYear): array
    {
        $currentLevel = $hero['level'] ?? 1;
        $totalLevelsGained = 0;
        $newFeats = [];

    // Try multiple level ups per year for low-level heroes
        for ($attempt = 0; $attempt < self::MAX_LEVELS_PER_YEAR; $attempt++) {
            $levelUpChance = $this->calculateLevelUpChance($currentLevel);

            if (mt_rand() / mt_getrandmax() < $levelUpChance) {
                $currentLevel++;
                $totalLevelsGained++;

    // Check for milestone levels
                if ($this->isMilestoneLevel($currentLevel)) {
                    $newFeats[] = $this->generateLevelUpFeat($hero['name'], $currentLevel, $currentYear);
                }
            } else {
                break;

    // Stop attempting level-ups once we fail
            }
        }

        if ($totalLevelsGained > 0) {
            $eventDescription = $this->generateLevelUpDescription(
                $hero['name'],
                $currentLevel,
                $totalLevelsGained,
                $currentYear
            );

            return [
                'leveledUp' => true,
                'newLevel' => $currentLevel,
                'eventDescription' => $eventDescription,
                'newFeats' => $newFeats
            ];
        }

        return [
            'leveledUp' => false,
            'newFeats' => []
        ];
    }

    /**
     * Calculate level-up chance based on current level
     */
    private function calculateLevelUpChance(int $currentLevel): float
    {
        $chance = self::BASE_LEVEL_UP_CHANCE * pow(self::LEVEL_UP_DIFFICULTY_FACTOR, $currentLevel - 1);

    // Apply level-based multipliers
        if ($currentLevel <= self::LOW_LEVEL_THRESHOLD) {
            $chance *= self::LOW_LEVEL_MULTIPLIER;
        } elseif ($currentLevel < self::HIGH_LEVEL_THRESHOLD) {
            $chance *= self::MID_LEVEL_MULTIPLIER;
        } else {
            $chance *= self::HIGH_LEVEL_MULTIPLIER;
        }

        return min($chance, 1.0);
    }

    /**
     * Process hero movement between regions
     */
    private function processHeroMovement(array $hero, int $currentYear): array
    {
        $messageTemplate = null;
        if (mt_rand() / mt_getrandmax() >= self::CHANCE_TO_MOVE) {
            $messageTemplate = \App\Models\HeroEventMessage::getMessageTemplate('movement_stay', 'movement')
            ?? '{heroName} remained in their current region.';

            return [
            'moved' => false,
            'eventDescription' => strtr($messageTemplate, [
                '{heroName}' => $hero['name']
            ]),
                'newFeats' => []
            ];
        }

        $currentRegion = $this->settlementRepository->getRegionById($hero['region_id']);
        $availableRegions = $this->settlementRepository->getRegionsExcept($hero['region_id']);

        if (empty($availableRegions)) {
            $messageTemplate = \App\Models\HeroEventMessage::getMessageTemplate('movement_no_options', 'movement')
            ?? '{heroName} had nowhere else to travel.';

            return [
            'moved' => false,
            'eventDescription' => strtr($messageTemplate, [
                '{heroName}' => $hero['name']
            ]),
                'newFeats' => []
            ];
        }

        $targetRegion = $availableRegions[array_rand($availableRegions)];
        $messageTemplate = \App\Models\HeroEventMessage::getMessageTemplate('movement_travel', 'movement')
            ?? '{heroName} traveled from {fromRegion} to {toRegion}.';

        $eventDescription = strtr($messageTemplate, [
            '{heroName}' => $hero['name'],
            '{fromRegion}' => $currentRegion['name'],
            '{toRegion}' => $targetRegion['name']
        ]);

        return [
            'moved' => true,
            'newRegionId' => $targetRegion['id'],
            'eventDescription' => $eventDescription,
            'newFeats' => []
        ];
    }

    /**
     * Age the hero by one year
     */
    private function ageHero(array &$hero, array &$changes): void
    {
        $hero['age'] = ($hero['age'] ?? 20) + 1;
        $changes['age'] = $hero['age'];
    }

    /**
     * Check if hero should die of natural causes
     */
    private function shouldDieNaturally(array $hero): bool
    {
        $baseLifeExpectancy = 70;
        $powerBonus = ($hero['level'] ?? 1) * 2;
        $lifeExpectancy = $baseLifeExpectancy + $powerBonus;

        if (($hero['age'] ?? 20) > $lifeExpectancy) {
            return mt_rand(1, 100) <= 20;

    // 20% chance per year after life expectancy
        }

        return false;
    }

    /**
     * Check if hero should die from dangerous circumstances
     */
    private function shouldDieFromDanger(array $hero): bool
    {
        $baseDanger = 0.01;

    // 1% base chance
        $powerModifier = max(0.2, 1 - (($hero['level'] ?? 1) / 10));
        return mt_rand(1, 100) / 100 <= ($baseDanger * $powerModifier);
    }

    /**
     * Process hero death
     */
    private function processHeroDeath(array &$hero, int $currentYear, string $reason): void
    {
        $changes = [
            'is_alive' => false,
            'status' => 'deceased',
            'death_reason' => $reason,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $this->heroRepository->updateHero($hero['id'], $changes);
        $hero = array_merge($hero, $changes);
    }

    /**
     * Generate a death reason
     */
    private function generateDeathReason(array $hero): string
    {
        // Get active death reasons from the database
        $reasons = \App\Models\HeroDeathReason::where('is_active', true)->get();

        if ($reasons->isEmpty()) {
            return 'Died under mysterious circumstances';
        }

    // Select a random reason
        $reason = $reasons->random();
        return $reason->description;
    }

    /**
     * Check if a level is a milestone level
     */
    private function isMilestoneLevel(int $level): bool
    {
        if ($level === 5 || $level === 10 || $level === 25) {
            return true;
        }

        return $level >= 25 && $level % 25 === 0;
    }

    /**
     * Generate a level-up feat description
     */
    private function generateLevelUpFeat(string $heroName, int $level, int $currentYear): string
    {
        $template = \App\Models\HeroEventMessage::getMessageTemplate('level_up_feat', 'level')
            ?? 'Year {year}: Achieved the rank of level {level}, marking a significant milestone in their journey.';

        return strtr($template, [
            '{year}' => $currentYear,
            '{heroName}' => $heroName,
            '{level}' => $level
        ]);
    }

    /**
     * Generate a level-up event description
     */
    private function generateLevelUpDescription(string $heroName, int $newLevel, int $levelsGained, int $currentYear): string
    {
        $code = $levelsGained === 1 ? 'level_up_single' : 'level_up_multiple';
        $template = \App\Models\HeroEventMessage::getMessageTemplate($code, 'level')
            ?? ($levelsGained === 1
                ? 'Year {year}: {heroName} reached level {level}.'
                : 'Year {year}: {heroName} rapidly advanced {levelsGained} levels, reaching level {level}!');

        return strtr($template, [
            '{year}' => $currentYear,
            '{heroName}' => $heroName,
            '{level}' => $newLevel,
            '{levelsGained}' => $levelsGained
        ]);
    }
}
