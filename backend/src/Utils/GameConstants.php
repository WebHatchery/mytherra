<?php

declare(strict_types=1);

namespace App\Utils;

class GameConstants
{
    // Regional influence costs and modifiers
    public const INFLUENCE_COSTS = [
        'blessRegion' => 100,
        'curseRegion' => 150,
        'inspireHero' => 75
    ];

    // Time-related constants
    public const TIME_CONSTANTS = [
        'yearLengthMs' => 5 * 60 * 1000, // 5 minutes per game year
        'heroActionCooldownMs' => 30 * 1000 // 30 seconds between hero actions
    ];

    // Hero constants
    public const HERO_CONSTANTS = [
        'defaultAge' => 20,
        'leveling' => [
            'baseLevelUpChance' => 0.1,
            'levelUpDifficultyFactor' => 1.5
        ],
        'movement' => [
            'chanceToMove' => 0.05
        ],
        'ascension' => [
            'minPowerLevelForRegionCreation' => 10
        ]
    ];

    // Region constants
    public const REGION_CONSTANTS = [
        'defaultProsperity' => 50,
        'defaultChaos' => 50,
        'defaultMagicAffinity' => 50,
        'maxStatValue' => 100,
        'minStatValue' => 0
    ];

    // System constants
    public const SYSTEM_CONSTANTS = [
        'tickIntervalMs' => 60000, // 1 minute
        'divineFavorPerTick' => 10,
        'maxRegions' => 50,
        'maxHeroesPerRegion' => 10
    ];
}
