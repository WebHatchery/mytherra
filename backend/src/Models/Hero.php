<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class Hero extends Model
{
    protected $table = 'heroes';

    protected $fillable = [
        'id',
        'name',
        'region_id',
        'role',
        'description',
        'feats',
        'influence_last_action',
        'level',
        'is_alive',
        'age',
        'death_reason',
        'personality_traits',
        'alignment',
        'status'
    ];

    protected $casts = [
        'feats' => 'array',
        'personality_traits' => 'array',
        'alignment' => 'array',
        'level' => 'integer',
        'age' => 'integer',
        'is_alive' => 'boolean'
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    // Validation constants
    public const ROLES = ['scholar', 'warrior', 'prophet', 'agent of change', 'undecided'];

    // For backward compatibility
    public const STATUSES = ['living', 'deceased', 'undead', 'ascended'];

    // Database-driven methods (replaces hardcoded arrays)
    public static function getValidRoles()
    {
        return HeroRole::getRoleCodes();
    }

    public static function getRoleDetails()
    {
        return HeroRole::getActiveRoles();
    }

    /**
     * Validate hero role
     */
    public function validateRole(): bool
    {
        return in_array($this->role, self::getValidRoles());
    }

    /**
     * Validate hero status
     */
    public function validateStatus(): bool
    {
        return in_array($this->status, self::STATUSES);
    }

    /**
     * Get role configuration
     */
    public function getRoleConfig(): array
    {
        $heroRole = HeroRole::where('code', $this->role)->first();

        if (!$heroRole) {
            // Fallback for unknown roles
            return [
                'name' => 'Unknown',
                'description' => 'Unknown role',
                'traits' => [],
                'preferredActions' => []
            ];
        }

        return [
            'name' => $heroRole->name,
            'description' => $heroRole->description,
            'traits' => $heroRole->personality_traits ?? [],
            'preferredActions' => $heroRole->preferred_actions ?? []
        ];
    }

    /**
     * Get alignment description
     */
    public function getAlignmentDescription(): string
    {
        if (!$this->alignment) {
            return 'Neutral';
        }

        $good = $this->alignment['good'] ?? 50;
        $chaotic = $this->alignment['chaotic'] ?? 50;

        $moral = $good > 50 ? 'Good' : ($good < 40 ? 'Evil' : 'Neutral');
        $order = $chaotic > 60 ? 'Chaotic' : ($chaotic < 40 ? 'Lawful' : 'Neutral');

        if ($moral === 'Neutral' && $order === 'Neutral') {
            return 'True Neutral';
        }

        return trim("$order $moral");
    }

    /**
     * Check if hero has specific personality trait
     */
    public function hasTrait(string $trait): bool
    {
        $traits = $this->personality_traits ?? [];
        return in_array($trait, $traits);
    }

    /**
     * Add personality trait
     */
    public function addTrait(string $trait): void
    {
        $traits = $this->personality_traits ?? [];
        if (!in_array($trait, $traits)) {
            $traits[] = $trait;
            $this->personality_traits = $traits;
        }
    }

    /**
     * Remove personality trait
     */
    public function removeTrait(string $trait): void
    {
        $traits = $this->personality_traits ?? [];
        $this->personality_traits = array_values(array_filter($traits, fn($t) => $t !== $trait));
    }

    /**
     * Add feat to hero
     */
    public function addFeat(string $feat): void
    {
        $feats = $this->feats ?? [];
        $feats[] = $feat;
        $this->feats = $feats;
    }

    /**
     * Get level category
     */
    public function getLevelCategory(): string
    {
        $level = $this->level ?? 1;
        if ($level >= 50) {
            return 'Legendary';
        }
        if ($level >= 25) {
            return 'Master';
        }
        if ($level >= 10) {
            return 'Experienced';
        }
        if ($level >= 5) {
            return 'Apprentice';
        }
        return 'Novice';
    }

    /**
     * Get age category
     */
    public function getAgeCategory(): string
    {
        $age = $this->age ?? 20;
        if ($age >= 70) {
            return 'Elder';
        }
        if ($age >= 50) {
            return 'Mature';
        }
        if ($age >= 30) {
            return 'Adult';
        }
        if ($age >= 18) {
            return 'Young Adult';
        }
        return 'Youth';
    }

    /**
     * Check if hero is milestone level (awards feats)
     */
    public function isMilestoneLevel(): bool
    {
        $level = $this->level ?? 1;
        if (in_array($level, [5, 10, 25])) {
            return true;
        }
        return $level >= 25 && $level % 25 === 0;
    }

    /**
     * Calculate level up chance (for tick processing)
     */
    public function calculateLevelUpChance(): float
    {
        $level = $this->level ?? 1;
        $baseLevelUpChance = 0.3;
        $levelUpDifficultyFactor = 0.95;

    // Accelerated leveling for low levels
        if ($level <= 15) {
            return $baseLevelUpChance * 4.0 * pow($levelUpDifficultyFactor, $level - 1);
        } elseif ($level <= 49) {
            return $baseLevelUpChance * 1.5 * pow($levelUpDifficultyFactor, $level - 1);
        } else {
            return $baseLevelUpChance * 0.3 * pow($levelUpDifficultyFactor, $level - 1);
        }
    }

    /**
     * Check if hero is alive
     */
    public function isAlive(): bool
    {
        return $this->is_alive ?? true;
    }

    /**
     * Check if hero is undead
     */
    public function isUndead(): bool
    {
        return $this->status === 'undead';
    }

    /**
     * Check if hero is ascended
     */
    public function isAscended(): bool
    {
        return $this->status === 'ascended';
    }

    /**
     * Get divine influence costs for this hero
     */
    public function getInfluenceCosts(): array
    {
        $baseCosts = [
            'guideHero' => 5,
            'empowerHero' => 10,
            'reviveHero' => 50,
            'forceNotableEvent' => 15
        ];

    // Calculate revive cost based on hero stats
        if (!$this->isAlive()) {
            $level = $this->level ?? 1;
            $age = $this->age ?? 20;
            $featCount = count($this->feats ?? []);

            $baseCosts['reviveHero'] = 50 + ($level * 5) + ($age * 2) + ($featCount * 3);
        }

        return $baseCosts;
    }

    /**
     * Update alignment values (ensures bounds)
     */
    public function updateAlignment(int $goodChange, int $chaoticChange, string $reason = ''): void
    {
        $alignment = $this->alignment ?? ['good' => 50, 'chaotic' => 50];

        $alignment['good'] = max(0, min(100, $alignment['good'] + $goodChange));
        $alignment['chaotic'] = max(0, min(100, $alignment['chaotic'] + $chaoticChange));

        if ($reason) {
            $alignment['lastChange'] = $reason;
        }

        $this->alignment = $alignment;
    }

    /**
     * Apply role change effects
     */
    public function changeRole(string $newRole, string $reason = ''): bool
    {
        if (!in_array($newRole, self::getValidRoles())) {
            return false;
        }

        $oldRole = $this->role;
        $this->role = $newRole;

    // Add feat for role change
        if ($oldRole === 'undecided') {
            $this->addFeat("Guided towards the path of a {$newRole}");
        } else {
            $this->addFeat("Changed from {$oldRole} to {$newRole}");
        }

    // Get role details from database for alignment changes
        $heroRole = HeroRole::where('code', $newRole)->first();
        if ($heroRole && $heroRole->alignment_modifiers) {
            $change = $heroRole->alignment_modifiers;
            $this->updateAlignment(
                $change['good'] ?? 0,
                $change['chaotic'] ?? 0,
                $reason ?: "Role change to {$newRole}"
            );
        }

        return true;
    }

    // Relationships

    /**
     * Get the region this hero belongs to
     */
    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    /**
     * Get the hero role details
     */
    public function heroRole()
    {
        return $this->belongsTo(HeroRole::class, 'role', 'code');
    }

    /**
     * Get settlement interactions for this hero
     */
    public function settlementInteractions()
    {
        return $this->hasMany(HeroSettlementInteraction::class, 'hero_id');
    }

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('heroes')) {
            Schema::schema()->create('heroes', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->string('region_id');
                $table->string('role');

    // Reference to hero_roles.code
                $table->text('description');
                $table->json('feats')->nullable();
                $table->string('influence_last_action')->nullable();
                $table->integer('level')->default(1);
                $table->boolean('is_alive')->default(true);
                $table->integer('age')->default(20);
                $table->string('death_reason')->nullable();
                $table->json('personality_traits')->nullable();
                $table->json('alignment')->nullable();
                $table->enum('status', ['living', 'deceased', 'undead', 'ascended'])->nullable();
                $table->timestamps();

    // Foreign key constraints
                $table->foreign('region_id')->references('id')->on('regions')->onDelete('cascade');
                $table->foreign('role')->references('code')->on('hero_roles')->onDelete('restrict');

    // Indexes for performance
                $table->index('region_id');
                $table->index('role');
                $table->index('is_alive');
                $table->index('status');
                $table->index('level');
            });
        }
    }
}
