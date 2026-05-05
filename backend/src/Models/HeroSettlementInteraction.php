<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class HeroSettlementInteraction extends Model
{
    protected $table = 'hero_settlement_interactions';

    protected $fillable = [
        'id',
        'hero_id',
        'settlement_id',
        'landmark_id',
        'action',
        'started_year',
        'duration',
        'success',
        'outcome_description',
        'interaction_type'
    ];

    protected $casts = [
        'started_year' => 'integer',
        'duration' => 'integer',
        'success' => 'boolean'
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Validate interaction type
     */
    public function validateInteractionType(): bool
    {
        return in_array($this->interaction_type, HeroSettlementInteractionType::getTypeCodes());
    }

    /**
     * Validate that either settlement_id or landmark_id is provided (but not both null)
     */
    public function validateTarget(): bool
    {
        return !empty($this->settlement_id) || !empty($this->landmark_id);
    }

    /**
     * Check if interaction is active (still ongoing)
     */
    public function isActive(int $currentYear): bool
    {
        $endYear = $this->started_year + $this->duration;
        return $currentYear < $endYear;
    }

    /**
     * Check if interaction has ended
     */
    public function hasEnded(int $currentYear): bool
    {
        $endYear = $this->started_year + $this->duration;
        return $currentYear >= $endYear;
    }

    /**
     * Get the end year of the interaction
     */
    public function getEndYear(): int
    {
        return $this->started_year + $this->duration;
    }

    /**
     * Get interaction type configuration
     */
    public function getInteractionTypeConfig(): array
    {
        $type = HeroSettlementInteractionType::getByCode($this->interaction_type);
        if (!$type) {
            return [
                'name' => 'Unknown',
                'description' => 'Interaction type not found',
                'typical_duration' => 1,
                'success_factors' => []
            ];
        }

        return [
            'name' => $type->name,
            'description' => $type->description,
            'typical_duration' => $type->base_duration,
            'success_factors' => [
                'base_chance' => $type->success_chance,
                'influence_cost' => $type->influence_cost,
                'cooldown_hours' => $type->cooldown_hours
            ]
        ];
    }

    /**
     * Get base success chance for the interaction
     */
    public function getBaseSuccessChance(): float
    {
        $type = HeroSettlementInteractionType::getByCode($this->interaction_type);
        return $type ? $type->success_chance : 0.5;
    }

    /**
     * Get influence cost for the interaction
     */
    public function getInfluenceCost(): int
    {
        $type = HeroSettlementInteractionType::getByCode($this->interaction_type);
        return $type ? $type->influence_cost : 0;
    }

    /**
     * Get cooldown hours for the interaction
     */
    public function getCooldownHours(): int
    {
        $type = HeroSettlementInteractionType::getByCode($this->interaction_type);
        return $type ? $type->cooldown_hours : 24;
    }

    // Relationships
    public function hero()
    {
        return $this->belongsTo(Hero::class, 'hero_id', 'id');
    }

    public function settlement()
    {
        return $this->belongsTo(Settlement::class, 'settlement_id', 'id');
    }

    public function landmark()
    {
        return $this->belongsTo(Landmark::class, 'landmark_id', 'id');
    }

    public function interactionType()
    {
        return $this->belongsTo(HeroSettlementInteractionType::class, 'interaction_type', 'code');
    }

    /**
     * Create database table
     */
    public static function createTable()
    {
        if (!Schema::schema()->hasTable('hero_settlement_interactions')) {
            Schema::schema()->create('hero_settlement_interactions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('hero_id');
                $table->string('settlement_id')->nullable();
                $table->string('landmark_id')->nullable();
                $table->string('action');
                $table->integer('started_year');
                $table->integer('duration')->default(1);
                $table->boolean('success')->nullable();
                $table->text('outcome_description')->nullable();
                $table->string('interaction_type');
                $table->timestamps();

                $table->foreign('hero_id')->references('id')->on('heroes')->onDelete('cascade');
                $table->foreign('settlement_id')->references('id')->on('settlements')->onDelete('set null');
                $table->foreign('landmark_id')->references('id')->on('landmarks')->onDelete('set null');
                $table->foreign('interaction_type')->references('code')->on('hero_settlement_interaction_types');

    // Add indexes
                $table->index('hero_id');
                $table->index('settlement_id');
                $table->index('landmark_id');
                $table->index('interaction_type');
                $table->index(['started_year', 'duration']);
            });
        }
    }

    /**
     * Validate model data before saving
     */
    public function save(array $options = [])
    {
        // Validate interaction type
        if (!$this->validateInteractionType()) {
            throw new \InvalidArgumentException("Invalid interaction type: {$this->interaction_type}");
        }

    // Validate target (settlement or landmark)
        if (!$this->validateTarget()) {
            throw new \InvalidArgumentException("Either settlement_id or landmark_id must be provided");
        }

        return parent::save($options);
    }
}
