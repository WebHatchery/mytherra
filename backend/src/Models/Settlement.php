<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class Settlement extends Model
{
    protected $table = 'settlements';

    protected $fillable = [
        'id',
        'region_id',
        'name',
        'type',
        'population',
        'prosperity',
        'defensibility',
        'status',
        'specializations',
        'events',
        'founded_year',
        'last_event_year',
        'traits'
      ];

    protected $casts = [
        'specializations' => 'array',
        'events' => 'array',
        'traits' => 'array',
        'population' => 'integer',
        'prosperity' => 'integer',
        'defensibility' => 'integer',
        'founded_year' => 'integer',
        'last_event_year' => 'integer'
      ];

    protected $keyType = 'string';

    public $incrementing = false;

    // Database-driven methods for validation and reference
    public static function getValidTypes()
    {
        return SettlementType::getTypeCodes();
    }

    public static function getValidStatuses()
    {
          return SettlementStatus::getStatusCodes();
    }

    public static function getTypeDetails()
    {
          return SettlementType::getActiveTypes();
    }

    public static function getStatusDetails()
    {
          return SettlementStatus::getActiveStatuses();
    }

    // Relationships
    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

    public function buildings()
    {
          return $this->hasMany(Building::class, 'settlement_id', 'id');
    }

    public function resourceNodes()
    {
          return $this->hasMany(ResourceNode::class, 'settlement_id', 'id');
    }

    // Database-driven type and status relationships
    public function settlementType()
    {
        return $this->belongsTo(SettlementType::class, 'type', 'code');
    }

    public function settlementStatus()
    {
          return $this->belongsTo(SettlementStatus::class, 'status', 'code');
    }

    // Validation methods
    public function validateType($type)
    {
        return in_array($type, self::getValidTypes());
    }

    public function validateStatus($status)
    {
          return in_array($status, self::getValidStatuses());
    }

    public function validateProsperity($prosperity)
    {
          return is_numeric($prosperity) && $prosperity >= 0 && $prosperity <= 100;
    }

    public function validateDefensibility($defensibility)
    {
          return is_numeric($defensibility) && $defensibility >= 0 && $defensibility <= 100;
    }

    public function validatePopulation($population)
    {
          return is_numeric($population) && $population >= 0;
    }

    // Database table creation
    public static function createTable()
    {
        if (!Schema::schema()->hasTable('settlements')) {
            Schema::schema()->create('settlements', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('region_id');
                $table->string('name');
                $table->string('type');

    // Reference to settlement_types.code
                $table->integer('population')->default(0);
                $table->integer('prosperity')->default(50);
                $table->integer('defensibility')->default(25);
                $table->string('status')->default('stable');

    // Reference to settlement_statuses.code
                $table->json('specializations')->nullable();
                $table->json('events')->nullable();
                $table->integer('founded_year');
                $table->integer('last_event_year')->nullable();
                $table->json('traits')->nullable();
                $table->timestamps();

    // Foreign key constraints
                $table->foreign('region_id')->references('id')->on('regions')->onDelete('cascade');
                $table->foreign('type')->references('code')->on('settlement_types')->onDelete('restrict');
                $table->foreign('status')->references('code')->on('settlement_statuses')->onDelete('restrict');

    // Indexes for performance
                $table->index('region_id');
                $table->index('type');
                $table->index('status');
                $table->index('population');
                $table->index('prosperity');
            });
        }
    }

    // Helper methods for business logic
    public function getPopulationCategory()
    {
        $typeConfig = $this->settlementType;
        return match ($this->type) {
            'hamlet' => 'Small',
            'village' => 'Medium',
            'town' => 'Large',
            'city' => 'Very Large',
            default => 'Unknown'
        };
    }

    public function getProsperityLevel()
    {
        if ($this->prosperity >= 80) {
            return 'Very High';
        }
        if ($this->prosperity >= 60) {
            return 'High';
        }
        if ($this->prosperity >= 40) {
            return 'Medium';
        }
        if ($this->prosperity >= 20) {
            return 'Low';
        }
          return 'Very Low';
    }

    public function getDefensibilityLevel()
    {
        if ($this->defensibility >= 80) {
            return 'Very High';
        }
        if ($this->defensibility >= 60) {
            return 'High';
        }
        if ($this->defensibility >= 40) {
            return 'Medium';
        }
        if ($this->defensibility >= 20) {
            return 'Low';
        }
          return 'Very Low';
    }

    public function isProsperous()
    {
          return $this->prosperity >= 60;
    }

    public function isFortified()
    {
          return in_array('fortified', $this->traits ?? []);
    }

    public function hasSpecialization($specialization)
    {
          return in_array($specialization, $this->specializations ?? []);
    }

    public function hasTrait($trait)
    {
          return in_array($trait, $this->traits ?? []);
    }

    // Calculate influence costs (for future divine influence implementation)
    public function calculateInfluenceCost($action)
    {
        $baseCost = 10;

    // Larger settlements are harder to influence
        $populationMultiplier = 1 + ($this->population / 10000);

    // More prosperous settlements are more resistant
        $prosperityMultiplier = 1 + ($this->prosperity / 100);

    // Fortified settlements are harder to influence
        $defensibilityMultiplier = 1 + ($this->defensibility / 100);

        return (int) round($baseCost * $populationMultiplier * $prosperityMultiplier * $defensibilityMultiplier);
    }
}
