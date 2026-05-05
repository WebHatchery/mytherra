<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class Landmark extends Model
{
    protected $table = 'landmarks';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = true;

    protected $fillable = [
        'id',
        'region_id',
        'name',
        'type',
        'description',
        'status',
        'magic_level',
        'danger_level',
        'discovered_year',
        'last_visited_year',
        'associated_events',
        'traits'
    ];

    protected $casts = [
        'magic_level' => 'integer',
        'danger_level' => 'integer',
        'discovered_year' => 'integer',
        'last_visited_year' => 'integer',
        'associated_events' => 'array',
        'traits' => 'array'
    ];

    // Landmark types enum
    public const TYPE_TEMPLE = 'temple';
    public const TYPE_RUIN = 'ruin';
    public const TYPE_FOREST = 'forest';
    public const TYPE_MOUNTAIN = 'mountain';
    public const TYPE_RIVER = 'river';
    public const TYPE_MONUMENT = 'monument';
    public const TYPE_DUNGEON = 'dungeon';
    public const TYPE_TOWER = 'tower';
    public const TYPE_BATTLEFIELD = 'battlefield';
    public const TYPE_GROVE = 'grove';

    // Landmark statuses enum
    public const STATUS_PRISTINE = 'pristine';
    public const STATUS_WEATHERED = 'weathered';
    public const STATUS_CORRUPTED = 'corrupted';
    public const STATUS_BLESSED = 'blessed';
    public const STATUS_HAUNTED = 'haunted';
    public const STATUS_ACTIVE = 'active';

    // Landmark traits enum
    public const TRAIT_ANCIENT = 'ancient';
    public const TRAIT_DRAGON_LAIR = 'dragon_lair';
    public const TRAIT_PORTAL = 'portal';
    public const TRAIT_CURSED_GROUND = 'cursed_ground';
    public const TRAIT_HOLY_SITE = 'holy_site';
    public const TRAIT_HIDDEN = 'hidden';
    public const TRAIT_MAGICAL = 'magical';
    public const TRAIT_STRATEGIC = 'strategic';
    public const TRAIT_HISTORICAL = 'historical';
    public const TRAIT_ABUNDANT = 'abundant';    /**
     * Relationship with Region model
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

    /**
     * Relationship with LandmarkType model
     */
    public function landmarkType(): BelongsTo
    {
        return $this->belongsTo(LandmarkType::class, 'type', 'code');
    }

    /**
     * Relationship with LandmarkStatus model
     */
    public function landmarkStatus(): BelongsTo
    {
        return $this->belongsTo(LandmarkStatus::class, 'status', 'code');
    }

    // Database-driven methods (replaces hardcoded arrays)
    public static function getValidTypes()
    {
        return LandmarkType::getTypeCodes();
    }

    public static function getValidStatuses()
    {
        return LandmarkStatus::getStatusCodes();
    }

    public static function getTypeDetails()
    {
        return LandmarkType::getActiveTypes();
    }

    public static function getStatusDetails()
    {
        return LandmarkStatus::getActiveStatuses();
    }

    /**
     * Get all valid landmark traits
     */
    public static function getValidTraits()
    {
        return [
            self::TRAIT_ANCIENT,
            self::TRAIT_DRAGON_LAIR,
            self::TRAIT_PORTAL,
            self::TRAIT_CURSED_GROUND,
            self::TRAIT_HOLY_SITE,
            self::TRAIT_HIDDEN,
            self::TRAIT_MAGICAL,
            self::TRAIT_STRATEGIC,
            self::TRAIT_HISTORICAL,
            self::TRAIT_ABUNDANT
        ];
    }

    /**
     * Validation rules for landmark data
     */
    public static function getValidationRules()
    {
        return [
            'id' => 'required|string|max:255',
            'regionId' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'type' => 'required|in:' . implode(',', self::getValidTypes()),
            'description' => 'required|string',
            'status' => 'required|in:' . implode(',', self::getValidStatuses()),
            'magicLevel' => 'required|integer|min:0|max:100',
            'dangerLevel' => 'required|integer|min:0|max:100',
            'discoveredYear' => 'nullable|integer',
            'lastVisitedYear' => 'nullable|integer',
            'associatedEvents' => 'nullable|array',
            'traits' => 'nullable|array'
        ];
    }

    /**
     * Validate landmark data
     */
    public function validateLandmarkData()
    {
        $errors = [];

    // Validate type
        if (!in_array($this->type, self::getValidTypes())) {
            $errors[] = "Invalid landmark type: {$this->type}";
        }

    // Validate status
        if (!in_array($this->status, self::getValidStatuses())) {
            $errors[] = "Invalid landmark status: {$this->status}";
        }

    // Validate magic level
        if ($this->magicLevel < 0 || $this->magicLevel > 100) {
            $errors[] = "Magic level must be between 0 and 100";
        }

    // Validate danger level
        if ($this->dangerLevel < 0 || $this->dangerLevel > 100) {
            $errors[] = "Danger level must be between 0 and 100";
        }

    // Validate traits
        if ($this->traits) {
            $validTraits = self::getValidTraits();
            foreach ($this->traits as $trait) {
                if (!in_array($trait, $validTraits)) {
                    $errors[] = "Invalid landmark trait: {$trait}";
                }
            }
        }

        return $errors;
    }

    /**
     * Get magic level description
     */
    public function getMagicLevelDescription()
    {
        if ($this->magicLevel >= 80) {
            return 'Extremely Magical';
        }
        if ($this->magicLevel >= 60) {
            return 'Highly Magical';
        }
        if ($this->magicLevel >= 40) {
            return 'Moderately Magical';
        }
        if ($this->magicLevel >= 20) {
            return 'Slightly Magical';
        }
        return 'Mundane';
    }

    /**
     * Get danger level description
     */
    public function getDangerLevelDescription()
    {
        if ($this->dangerLevel >= 80) {
            return 'Extremely Dangerous';
        }
        if ($this->dangerLevel >= 60) {
            return 'Highly Dangerous';
        }
        if ($this->dangerLevel >= 40) {
            return 'Moderately Dangerous';
        }
        if ($this->dangerLevel >= 20) {
            return 'Slightly Dangerous';
        }
        return 'Safe';
    }

    /**
     * Check if landmark is magical
     */
    public function isMagical()
    {
        return $this->magicLevel > 20 || $this->hasTrait(self::TRAIT_MAGICAL);
    }

    /**
     * Check if landmark is dangerous
     */
    public function isDangerous()
    {
        return $this->dangerLevel > 40;
    }

    /**
     * Check if landmark has specific trait
     */
    public function hasTrait($trait)
    {
        return in_array($trait, $this->traits ?? []);
    }

    /**
     * Check if landmark is ancient
     */
    public function isAncient()
    {
        return $this->hasTrait(self::TRAIT_ANCIENT) ||
               $this->type === self::TYPE_RUIN ||
               $this->type === self::TYPE_MONUMENT;
    }

    /**
     * Check if landmark is sacred/religious
     */
    public function isSacred()
    {
        return $this->type === self::TYPE_TEMPLE ||
               $this->hasTrait(self::TRAIT_HOLY_SITE) ||
               $this->status === self::STATUS_BLESSED;
    }

    /**
     * Check if landmark is corrupted/evil
     */
    public function isCorrupted()
    {
        return $this->status === self::STATUS_CORRUPTED ||
               $this->hasTrait(self::TRAIT_CURSED_GROUND) ||
               $this->hasTrait(self::TRAIT_DRAGON_LAIR);
    }

    /**
     * Check if landmark is accessible
     */
    public function isAccessible()
    {
        return !$this->hasTrait(self::TRAIT_HIDDEN) &&
               $this->status !== self::STATUS_CORRUPTED;
    }

    /**
     * Get landmark influence based on type and traits
     */
    public function getInfluenceScore()
    {
        $baseScore = 10;

    // Type-based influence
        $typeInfluence = [
            self::TYPE_TEMPLE => 15,
            self::TYPE_TOWER => 12,
            self::TYPE_MONUMENT => 10,
            self::TYPE_DUNGEON => 8,
            self::TYPE_RUIN => 6,
            self::TYPE_FOREST => 5,
            self::TYPE_MOUNTAIN => 5,
            self::TYPE_RIVER => 4,
            self::TYPE_BATTLEFIELD => 7,
            self::TYPE_GROVE => 8
        ];

        $baseScore += $typeInfluence[$this->type] ?? 5;

    // Trait-based modifiers
        if ($this->hasTrait(self::TRAIT_ANCIENT)) {
            $baseScore += 5;
        }
        if ($this->hasTrait(self::TRAIT_MAGICAL)) {
            $baseScore += 4;
        }
        if ($this->hasTrait(self::TRAIT_HOLY_SITE)) {
            $baseScore += 6;
        }
        if ($this->hasTrait(self::TRAIT_STRATEGIC)) {
            $baseScore += 3;
        }
        if ($this->hasTrait(self::TRAIT_PORTAL)) {
            $baseScore += 8;
        }

    // Status modifiers
        if ($this->status === self::STATUS_BLESSED) {
            $baseScore += 5;
        }
        if ($this->status === self::STATUS_CORRUPTED) {
            $baseScore += 3;
        }
        if ($this->status === self::STATUS_HAUNTED) {
            $baseScore += 2;
        }

    // Magic and danger level influence
        $baseScore += intval($this->magicLevel / 20);
        $baseScore += intval($this->dangerLevel / 25);

        return max(1, $baseScore);
    }

    /**
     * Get default magic level for landmark type
     */
    public static function getDefaultMagicLevel($type)
    {
        $defaults = [
            self::TYPE_TEMPLE => 70,
            self::TYPE_RUIN => 50,
            self::TYPE_FOREST => 40,
            self::TYPE_MOUNTAIN => 30,
            self::TYPE_RIVER => 35,
            self::TYPE_MONUMENT => 45,
            self::TYPE_DUNGEON => 55,
            self::TYPE_TOWER => 60,
            self::TYPE_BATTLEFIELD => 40,
            self::TYPE_GROVE => 65
        ];

        return $defaults[$type] ?? 30;
    }

    /**
     * Get default danger level for landmark type
     */
    public static function getDefaultDangerLevel($type)
    {
        $defaults = [
            self::TYPE_TEMPLE => 30,
            self::TYPE_RUIN => 60,
            self::TYPE_FOREST => 35,
            self::TYPE_MOUNTAIN => 65,
            self::TYPE_RIVER => 25,
            self::TYPE_MONUMENT => 20,
            self::TYPE_DUNGEON => 80,
            self::TYPE_TOWER => 40,
            self::TYPE_BATTLEFIELD => 50,
            self::TYPE_GROVE => 30
        ];

        return $defaults[$type] ?? 40;
    }

    /**
     * Create database table
     */
    public static function createTable()
    {
        if (!Schema::schema()->hasTable('landmarks')) {
            Schema::schema()->create('landmarks', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('region_id');
                $table->string('name');
                $table->string('type');

    // Changed from enum to string for flexibility
                $table->text('description');
                $table->string('status')->default('pristine');

    // Changed from enum to string
                $table->integer('magic_level')->default(0);
                $table->integer('danger_level')->default(0);
                $table->integer('discovered_year')->nullable();
                $table->integer('last_visited_year')->nullable();
                $table->json('associated_events')->nullable();
                $table->json('traits')->nullable();
                $table->timestamps();

    // Foreign key constraints
                $table->foreign('region_id')->references('id')->on('regions')->onDelete('cascade');

    // Indexes for performance
                $table->index('region_id');
                $table->index('type');
                $table->index('status');
                $table->index('magic_level');
                $table->index('danger_level');
            });
        }
    }
}
