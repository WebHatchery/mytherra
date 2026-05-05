<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class Region extends Model
{
    protected $table = 'regions';

    protected $fillable = [
        'id',
        'name',
        'color',
        'prosperity',
        'chaos',
        'magic_affinity',
        'status',
        'event_ids',
        'influence_last_action',
        'danger_level',
        'tags',
        'population_total',
        'regional_traits',
        'climate_type',
        'trade_routes',
        'cultural_influence',
        'divine_resonance'
    ];

    protected $casts = [
        'event_ids' => 'array',
        'tags' => 'array',
        'regional_traits' => 'array',
        'trade_routes' => 'array',
        'prosperity' => 'integer',
        'chaos' => 'integer',
        'magic_affinity' => 'integer',
        'danger_level' => 'integer',
        'population_total' => 'integer',
        'divine_resonance' => 'integer'
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    // Database-driven validation methods
    public function validateStatus(): bool
    {
        return in_array($this->status, RegionStatus::getStatusCodes());
    }

    public function validateClimateType(): bool
    {
        return in_array($this->climate_type, RegionClimateType::getTypeCodes());
    }

    public function validateCulturalInfluence(): bool
    {
        return in_array($this->cultural_influence, RegionCulturalInfluence::getInfluenceCodes());
    }

    // Get configuration from lookup tables
    public function getStatusConfig(): array
    {
        $status = RegionStatus::getByCode($this->status);
        if (!$status) {
            return [
                'name' => 'Unknown',
                'description' => 'Status not found',
                'modifiers' => ['heroSpawn' => 1.0, 'prosperity' => 1.0, 'chaos' => 1.0]
            ];
        }

        return [
            'name' => $status->name,
            'description' => $status->description,
            'modifiers' => [
                'heroSpawn' => $status->hero_spawn_modifier,
                'prosperity' => $status->prosperity_modifier,
                'chaos' => $status->chaos_modifier
            ]
        ];
    }

    /**
     * Calculate total population from settlements
     */
    public function calculateTotalPopulation(): int
    {
        return $this->settlements()->sum('population');
    }

    /**
     * Get settlement count by type
     */
    public function getSettlementCounts(): array
    {
        $settlements = $this->settlements;
        return [
            'cities' => $settlements->where('type', 'city')->count(),
            'towns' => $settlements->where('type', 'town')->count(),
            'villages' => $settlements->where('type', 'village')->count(),
            'hamlets' => $settlements->where('type', 'hamlet')->count(),
            'total' => $settlements->count()
        ];
    }

    /**
     * Check if region has specific trait
     */
    public function hasTrait(string $trait): bool
    {
        $traits = $this->regional_traits ?? [];
        return in_array($trait, $traits);
    }

    /**
     * Add regional trait
     */
    public function addTrait(string $trait): void
    {
        $traits = $this->regional_traits ?? [];
        if (!in_array($trait, $traits)) {
            $traits[] = $trait;
            $this->regional_traits = $traits;
        }
    }

    /**
     * Remove regional trait
     */
    public function removeTrait(string $trait): void
    {
        $traits = $this->regional_traits ?? [];
        $this->regional_traits = array_values(array_filter($traits, fn($t) => $t !== $trait));
    }

    /**
     * Get divine resonance effectiveness
     */
    public function getDivineResonanceEffectiveness(): string
    {
        $resonance = $this->divine_resonance ?? 50;
        if ($resonance >= 80) {
            return 'Very High';
        }
        if ($resonance >= 60) {
            return 'High';
        }
        if ($resonance >= 40) {
            return 'Moderate';
        }
        if ($resonance >= 20) {
            return 'Low';
        }
        return 'Very Low';
    }

    /**
     * Check if region is in crisis
     */
    public function isInCrisis(): bool
    {
        return $this->chaos >= 80 || $this->prosperity <= 20 || in_array($this->status, ['abandoned', 'warring']);
    }

    /**
     * Check if region is thriving
     */
    public function isThriving(): bool
    {
        return $this->prosperity >= 80 && $this->chaos <= 20 && $this->status === 'peaceful';
    }

    /**
     * Get danger level classification
     */
    public function getDangerLevelClassification(): string
    {
        $level = $this->danger_level ?? 5;
        if ($level >= 8) {
            return 'Extreme';
        }
        if ($level >= 6) {
            return 'High';
        }
        if ($level >= 4) {
            return 'Moderate';
        }
        if ($level >= 2) {
            return 'Low';
        }
        return 'Minimal';
    }

    // Relationships

    /**
     * Get the region status details
     */
    public function regionStatus()
    {
        return $this->belongsTo(RegionStatus::class, 'status', 'code');
    }

    /**
     * Get the climate type details
     */
    public function climateType()
    {
        return $this->belongsTo(RegionClimateType::class, 'climate_type', 'code');
    }

    /**
     * Get the cultural influence details
     */
    public function culturalInfluenceDetails()
    {
        return $this->belongsTo(RegionCulturalInfluence::class, 'cultural_influence', 'code');
    }

    /**
     * Get settlements in this region
     */
    public function settlements()
    {
        return $this->hasMany(Settlement::class, 'regionId', 'id');
    }

    /**
     * Get landmarks in this region
     */
    public function landmarks()
    {
        return $this->hasMany(Landmark::class, 'regionId', 'id');
    }

    /**
     * Get resource nodes in this region
     */
    public function resourceNodes()
    {
        return $this->hasMany(ResourceNode::class, 'region_id', 'id');
    }

    /**
     * Get heroes in this region
     */
    public function heroes()
    {
        return $this->hasMany(Hero::class, 'region_id');
    }

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('regions')) {
            Schema::schema()->create('regions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->string('color');
                $table->integer('prosperity')->default(50);
                $table->integer('chaos')->default(0);
                $table->integer('magic_affinity')->default(0);
                $table->string('status')->default('peaceful');
                $table->json('event_ids')->nullable();
                $table->string('influence_last_action')->nullable();
                $table->integer('danger_level')->default(5);
                $table->json('tags')->nullable();
                $table->integer('population_total')->default(0);
                $table->json('regional_traits')->nullable();
                $table->string('climate_type')->default('temperate');
                $table->json('trade_routes')->nullable();
                $table->string('cultural_influence')->default('pastoral');
                $table->integer('divine_resonance')->default(50);
                $table->timestamps();

    // Add foreign key constraints
                $table->foreign('status')->references('code')->on('region_statuses');
                $table->foreign('climate_type')->references('code')->on('region_climate_types');
                $table->foreign('cultural_influence')->references('code')->on('region_cultural_influences');

    // Add indexes for performance
                $table->index('status');
                $table->index('climate_type');
                $table->index('cultural_influence');
            });
        }
    }

    /**
     * Validate model data before saving
     */
    public function save(array $options = [])
    {
        // Validate status
        if (!$this->validateStatus()) {
            throw new \InvalidArgumentException("Invalid region status: {$this->status}");
        }

    // Validate climate type
        if (!$this->validateClimateType()) {
            throw new \InvalidArgumentException("Invalid climate type: {$this->climate_type}");
        }

    // Validate cultural influence
        if (!$this->validateCulturalInfluence()) {
            throw new \InvalidArgumentException("Invalid cultural influence: {$this->cultural_influence}");
        }

        return parent::save($options);
    }
}
