<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class DivineBet extends Model
{
    protected $table = 'divine_bets';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'player_id',
        'bet_type',
        'target_id',
        'description',
        'timeframe',
        'confidence',
        'divine_favor_stake',
        'potential_payout',
        'current_odds',
        'status',
        'placed_year',
        'resolved_year',
        'resolution_notes'
    ];

    protected $casts = [
        'timeframe' => 'integer',
        'divine_favor_stake' => 'integer',
        'potential_payout' => 'integer',
        'current_odds' => 'float',
        'placed_year' => 'integer',
        'resolved_year' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Bet types based on Node.js backend
    public const BET_TYPES = [
        'settlement_growth',
        'landmark_discovery',
        'cultural_shift',
        'hero_settlement_bond',
        'hero_location_visit',
        'settlement_transformation',
        'corruption_spread',
        // New enhanced bet types
        'hero_level_milestone',
        'hero_death',
        'region_danger_change',
        'war_outcome',
        'prosperity_threshold'
    ];

    // Confidence levels based on Node.js backend
    public const CONFIDENCE_LEVELS = [
        'long_shot',
        'possible',
        'likely',
        'near_certain'
    ];

    // Bet statuses based on Node.js backend
    public const STATUSES = [
        'active',
        'won',
        'lost',
        'expired'
    ];

    // Bet type configurations with base odds and descriptions
    public const BET_TYPE_CONFIGS = [
        'settlement_growth' => [
            'description' => 'A bet on whether a settlement will grow in size',
            'base_odds' => 2.5,
            'resolve_conditions' => 'Settlement population increases by at least 20% within timeframe'
        ],
        'landmark_discovery' => [
            'description' => 'A bet on whether a new landmark will be discovered',
            'base_odds' => 3.2,
            'resolve_conditions' => 'New landmark is discovered within the specified region within timeframe'
        ],
        'cultural_shift' => [
            'description' => 'A bet on cultural changes within a settlement or region',
            'base_odds' => 4.0,
            'resolve_conditions' => 'Cultural traits of the target change significantly within timeframe'
        ],
        'hero_settlement_bond' => [
            'description' => 'A bet on whether a hero will form a bond with a settlement',
            'base_odds' => 2.8,
            'resolve_conditions' => 'Hero becomes associated with the specified settlement within timeframe'
        ],
        'hero_location_visit' => [
            'description' => 'A bet on whether a hero will visit a specific location',
            'base_odds' => 2.0,
            'resolve_conditions' => 'Hero visits the specified location within timeframe'
        ],
        'settlement_transformation' => [
            'description' => 'A bet on a major transformation of a settlement',
            'base_odds' => 5.0,
            'resolve_conditions' => 'Settlement changes type or undergoes significant transformation within timeframe'
        ],
        'corruption_spread' => [
            'description' => 'A bet on whether corruption will spread to an area',
            'base_odds' => 3.5,
            'resolve_conditions' => 'Corruption level increases significantly in target area within timeframe'
        ],
        // New enhanced bet types
        'hero_level_milestone' => [
            'description' => 'A bet on a hero reaching a specific level',
            'base_odds' => 2.2,
            'resolve_conditions' => 'Hero reaches the specified level within timeframe'
        ],
        'hero_death' => [
            'description' => 'A bet on whether a hero will die',
            'base_odds' => 4.5,
            'resolve_conditions' => 'Hero dies within the specified timeframe'
        ],
        'region_danger_change' => [
            'description' => 'A bet on a region\'s danger level changing',
            'base_odds' => 2.8,
            'resolve_conditions' => 'Region danger level changes by at least 2 levels within timeframe'
        ],
        'war_outcome' => [
            'description' => 'A bet on the outcome of a conflict between regions',
            'base_odds' => 3.0,
            'resolve_conditions' => 'Specified region wins or loses the conflict within timeframe'
        ],
        'prosperity_threshold' => [
            'description' => 'A bet on a settlement reaching a prosperity threshold',
            'base_odds' => 2.4,
            'resolve_conditions' => 'Settlement prosperity reaches or exceeds target value within timeframe'
        ]
    ];

    // Confidence configurations with odds modifiers and stake multipliers
    public const CONFIDENCE_CONFIGS = [
        'long_shot' => [
            'description' => 'Very unlikely to happen',
            'odds_modifier' => 2.0,
            'stake_multiplier' => 0.5
        ],
        'possible' => [
            'description' => 'Could reasonably happen',
            'odds_modifier' => 1.0,
            'stake_multiplier' => 1.0
        ],
        'likely' => [
            'description' => 'More likely to happen than not',
            'odds_modifier' => 0.7,
            'stake_multiplier' => 1.5
        ],
        'near_certain' => [
            'description' => 'Almost guaranteed to happen',
            'odds_modifier' => 0.4,
            'stake_multiplier' => 2.0
        ]
    ];

    // Status configurations
    public const STATUS_CONFIGS = [
        'active' => [
            'description' => 'Bet is currently active and awaiting resolution',
            'is_active' => true
        ],
        'won' => [
            'description' => 'Bet has been resolved as a win',
            'is_active' => false
        ],
        'lost' => [
            'description' => 'Bet has been resolved as a loss',
            'is_active' => false
        ],
        'expired' => [
            'description' => 'Bet has expired without resolution',
            'is_active' => false
        ]
    ];

    /**
     * Validate bet type
     */
    public static function validateBetType(string $betType): bool
    {
        return in_array($betType, self::BET_TYPES);
    }

    /**
     * Validate confidence level
     */
    public static function validateConfidence(string $confidence): bool
    {
        return in_array($confidence, self::CONFIDENCE_LEVELS);
    }

    /**
     * Validate bet status
     */
    public static function validateStatus(string $status): bool
    {
        return in_array($status, self::STATUSES);
    }

    /**
     * Validate timeframe (1-50 years)
     */
    public static function validateTimeframe(int $timeframe): bool
    {
        return $timeframe >= 1 && $timeframe <= 50;
    }

    /**
     * Validate divine favor stake (1-1000)
     */
    public static function validateStake(int $stake): bool
    {
        return $stake >= 1 && $stake <= 1000;
    }

    /**
     * Validate current odds (minimum 1.1)
     */
    public static function validateOdds(float $odds): bool
    {
        return $odds >= 1.1;
    }

    /**
     * Get base odds for a specific bet type
     */
    public static function getBaseOddsForType(string $betType): float
    {
        return self::BET_TYPE_CONFIGS[$betType]['base_odds'] ?? 2.0;
    }

    /**
     * Get confidence modifier for calculations
     */
    public static function getConfidenceModifier(string $confidence): float
    {
        return self::CONFIDENCE_CONFIGS[$confidence]['odds_modifier'] ?? 1.0;
    }

    /**
     * Get stake multiplier for payout calculations
     */
    public static function getStakeMultiplier(string $confidence): float
    {
        return self::CONFIDENCE_CONFIGS[$confidence]['stake_multiplier'] ?? 1.0;
    }

    /**
     * Calculate potential payout based on stake, odds, and confidence
     */
    public function calculatePotentialPayout(): int
    {
        $stakeMultiplier = self::getStakeMultiplier($this->confidence);
        return (int) round($this->divine_favor_stake * $this->current_odds * $stakeMultiplier);
    }

    /**
     * Check if bet is active
     */
    public function isActive(): bool
    {
        $statusConfig = self::STATUS_CONFIGS[$this->status] ?? self::STATUS_CONFIGS['active'];
        return $statusConfig['is_active'];
    }

    /**
     * Check if bet has expired based on current year
     */
    public function hasExpired(int $currentYear): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $betAge = $currentYear - $this->placed_year;
        return $betAge >= $this->timeframe;
    }

    /**
     * Get bet age in years
     */
    public function getBetAge(int $currentYear): int
    {
        return $currentYear - $this->placed_year;
    }

    /**
     * Get years remaining until expiration
     */
    public function getYearsRemaining(int $currentYear): int
    {
        return max(0, $this->timeframe - $this->getBetAge($currentYear));
    }

    /**
     * Get risk level based on confidence and odds
     */
    public function getRiskLevel(): string
    {
        if ($this->confidence === 'long_shot' || $this->current_odds >= 5.0) {
            return 'high';
        }
        if ($this->confidence === 'near_certain' || $this->current_odds <= 1.5) {
            return 'low';
        }
        if ($this->confidence === 'likely' || $this->current_odds <= 2.5) {
            return 'medium';
        }
        return 'moderate';
    }

    /**
     * Get expected return on investment percentage
     */
    public function getExpectedROI(): float
    {
        $winProbability = 1.0 / $this->current_odds;
        $potentialReturn = $this->potential_payout - $this->divine_favor_stake;
        $expectedReturn = ($winProbability * $potentialReturn) - ((1 - $winProbability) * $this->divine_favor_stake);

        return ($expectedReturn / $this->divine_favor_stake) * 100;
    }

    /**
     * Resolve bet with outcome and notes
     */
    public function resolve(string $outcome, int $resolvedYear, ?string $notes = null): bool
    {
        if (!in_array($outcome, ['won', 'lost', 'expired'])) {
            throw new InvalidArgumentException("Invalid bet outcome: {$outcome}");
        }

        $this->status = $outcome;
        $this->resolved_year = $resolvedYear;
        $this->resolution_notes = $notes;

        return $this->save();
    }

    /**
     * Update current odds and recalculate potential payout
     */
    public function updateOdds(float $newOdds): bool
    {
        if (!self::validateOdds($newOdds)) {
            throw new InvalidArgumentException("Invalid odds value: {$newOdds}");
        }

    // Apply a max change of 20% to avoid wild fluctuations
        $maxChange = $this->current_odds * 0.2;
        $this->current_odds = max(
            min($newOdds, $this->current_odds + $maxChange),
            $this->current_odds - $maxChange
        );

    // Ensure minimum odds of 1.1
        $this->current_odds = max($this->current_odds, 1.1);

    // Recalculate potential payout
        $this->potential_payout = $this->calculatePotentialPayout();

        return $this->save();
    }

    /**
     * Create database table for divine bets
     */
    public static function createTable()
    {
        $db = \App\Repositories\DatabaseService::getInstance();
        $sql = "
            CREATE TABLE IF NOT EXISTS divine_bets (
                id VARCHAR(255) PRIMARY KEY,
                player_id VARCHAR(255) NOT NULL,
                bet_type ENUM('" . implode("','", self::BET_TYPES) . "') NOT NULL,
                target_id VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                timeframe INT NOT NULL CHECK (timeframe >= 1 AND timeframe <= 50),
                confidence ENUM('" . implode("','", self::CONFIDENCE_LEVELS) . "') NOT NULL,
                divine_favor_stake INT NOT NULL CHECK (divine_favor_stake >= 1 AND divine_favor_stake <= 1000),
                potential_payout INT NOT NULL,
                current_odds DECIMAL(10,2) NOT NULL CHECK (current_odds >= 1.1),
                status ENUM('" . implode("','", self::STATUSES) . "') NOT NULL DEFAULT 'active',
                placed_year INT NOT NULL,
                resolved_year INT NULL,
                resolution_notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_divine_bets_player_id (player_id),
                INDEX idx_divine_bets_bet_type (bet_type),
                INDEX idx_divine_bets_target_id (target_id),
                INDEX idx_divine_bets_status (status),
                INDEX idx_divine_bets_confidence (confidence),
                INDEX idx_divine_bets_placed_year (placed_year),
                INDEX idx_divine_bets_timeframe (timeframe)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";

        $db->query($sql);
    }

    /**
     * Validate model data before saving
     */
    public function save(array $options = [])
    {
        // Validate bet type
        if (!self::validateBetType($this->bet_type)) {
            throw new InvalidArgumentException("Invalid bet type: {$this->bet_type}");
        }

    // Validate confidence
        if (!self::validateConfidence($this->confidence)) {
            throw new InvalidArgumentException("Invalid confidence level: {$this->confidence}");
        }

    // Validate status
        if (!self::validateStatus($this->status)) {
            throw new InvalidArgumentException("Invalid status: {$this->status}");
        }

    // Validate timeframe
        if (!self::validateTimeframe($this->timeframe)) {
            throw new InvalidArgumentException("Invalid timeframe: {$this->timeframe}. Must be between 1 and 50 years.");
        }

    // Validate stake
        if (!self::validateStake($this->divine_favor_stake)) {
            throw new InvalidArgumentException("Invalid stake: {$this->divine_favor_stake}. Must be between 1 and 1000.");
        }

    // Validate odds
        if (!self::validateOdds($this->current_odds)) {
            throw new InvalidArgumentException("Invalid odds: {$this->current_odds}. Must be at least 1.1.");
        }

        return parent::save($options);
    }
}
