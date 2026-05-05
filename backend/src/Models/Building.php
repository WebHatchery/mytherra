<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class Building extends Model
{
    protected $table = 'buildings';

    protected $fillable = [
        'id',
        'settlement_id',
        'type',
        'name',
        'condition',
        'status',
        'level',
        'specialProperties'
      ];

    protected $casts = [
        'specialProperties' => 'array',
        'condition' => 'integer',
        'level' => 'integer'
      ];

    protected $keyType = 'string';

    public $incrementing = false;

    // Legacy constants kept for backward compatibility - use database methods instead
    // @deprecated Use BuildingType::getTypeCodes() and BuildingStatus::getStatusCodes()

    // Database-driven static methods
    public static function getValidTypes()
    {
        return BuildingType::getTypeCodes();
    }

    public static function getValidStatuses()
    {
          return BuildingStatus::getStatusCodes();
    }

    public static function getAllTypeDetails()
    {
          return BuildingType::getActiveTypes();
    }

    public static function getAllStatusDetails()
    {
          return BuildingStatus::getActiveStatuses();
    }

    public static function getValidSpecialProperties()
    {
          return BuildingSpecialProperty::getPropertyCodes();
    }

    // Relationships
    public function settlement()
    {
        return $this->belongsTo(Settlement::class, 'settlementId', 'id');
    }

    public function buildingType()
    {
          return $this->belongsTo(BuildingType::class, 'type', 'code');
    }

    public function buildingStatus()
    {
          return $this->belongsTo(BuildingStatus::class, 'status', 'code');
    }

    // Validation methods (database-driven)
    public function validateType($type)
    {
        return in_array($type, self::getValidTypes());
    }

    public function validateStatus($status)
    {
          return in_array($status, self::getValidStatuses());
    }

    public function validateCondition($condition)
    {
          return is_numeric($condition) && $condition >= 0 && $condition <= 100;
    }

    public function validateSpecialProperties($properties)
    {
        if (!is_array($properties)) {
            return false;
        }

          $validProperties = BuildingSpecialProperty::getPropertyCodes();
        foreach ($properties as $property) {
            if (!in_array($property, $validProperties)) {
                return false;
            }
        }

          return true;
    }

    // Database table creation
    public static function createTable()
    {
        if (!Schema::schema()->hasTable('buildings')) {
            Schema::schema()->create('buildings', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('settlement_id');
                $table->string('type');

    // Changed from enum to string for flexibility
                $table->string('name');
                $table->integer('condition')->default(100);
                $table->string('status')->default('active');

    // Changed from enum to string
                $table->integer('level')->default(1);
                $table->json('specialProperties')->nullable();
                $table->timestamps();

    // Foreign key constraint
                $table->foreign('settlement_id')->references('id')->on('settlements')->onDelete('cascade');

    // Indexes for performance
                $table->index('settlement_id');
                $table->index('type');
                $table->index('status');
                $table->index('condition');
                $table->index('level');
            });
        }
    }

    // Instance helper methods (database-driven)
    public function getTypeDescription()
    {
        $buildingType = BuildingType::getByCode($this->type);
        return $buildingType ? $buildingType->description : 'Unknown building type';
    }

    public function getTypeDetails()
    {
          $buildingType = BuildingType::getByCode($this->type);
          return $buildingType ? $buildingType->getTypeDetails() : [];
    }

    public function getStatusDescription()
    {
          $buildingStatus = BuildingStatus::getByCode($this->status);
          return $buildingStatus ? $buildingStatus->description : 'Unknown status';
    }

    public function getStatusDetails()
    {
          $buildingStatus = BuildingStatus::getByCode($this->status);
          return $buildingStatus ? $buildingStatus->getStatusDetails() : [];
    }

    // Condition methods (database-driven)
    public function getConditionLevel()
    {
        $conditionDetails = BuildingConditionLevel::getConditionDetails($this->condition);
        return $conditionDetails['name'];
    }

    public function getConditionDetails()
    {
          return BuildingConditionLevel::getConditionDetails($this->condition);
    }

    public function getConditionColorCode()
    {
          $conditionDetails = BuildingConditionLevel::getConditionDetails($this->condition);
          return $conditionDetails['color_code'];
    }

    public function getMaintenanceMultiplier()
    {
          $conditionDetails = BuildingConditionLevel::getConditionDetails($this->condition);
          return $conditionDetails['maintenance_multiplier'];
    }

    public function getProductivityMultiplier()
    {
          $conditionDetails = BuildingConditionLevel::getConditionDetails($this->condition);
          return $conditionDetails['productivity_multiplier'];
    }

    // Business logic methods (database-driven)
    public function isOperational()
    {
        $buildingStatus = BuildingStatus::getByCode($this->status);
        if (!$buildingStatus) {
            return false;
        }

        $modifiers = $buildingStatus->productivity_modifiers ?? [];
        return isset($modifiers['is_operational']) ? $modifiers['is_operational'] : false;
    }

    public function hasSpecialProperty($property)
    {
          return in_array($property, $this->specialProperties ?? []);
    }

    public function getSpecialPropertyDetails($property)
    {
          $specialProperty = BuildingSpecialProperty::getByCode($property);
          return $specialProperty ? $specialProperty->toArray() : null;
    }

    public function getAllSpecialPropertyDetails()
    {
          $details = [];
        foreach ($this->specialProperties ?? [] as $property) {
            $propertyDetails = $this->getSpecialPropertyDetails($property);
            if ($propertyDetails) {
                $details[] = $propertyDetails;
            }
        }
          return $details;
    }

    // Category methods (database-driven)
    public function isMagical()
    {
        // Check if building type is magical or has magical special properties
        $buildingType = BuildingType::getByCode($this->type);
        $isMagicalType = $buildingType && ($buildingType->category === 'magical');

        $hasMagicalProperty = false;
        foreach ($this->specialProperties ?? [] as $property) {
            $propDetails = BuildingSpecialProperty::getByCode($property);
            if ($propDetails && $propDetails->category === 'magical') {
                $hasMagicalProperty = true;
                break;
            }
        }

        return $isMagicalType || $hasMagicalProperty;
    }

    public function isReligious()
    {
          // Check if building type is religious or has religious special properties
          $buildingType = BuildingType::getByCode($this->type);
          $isReligiousType = $buildingType && ($buildingType->category === 'religious');

          $hasReligiousProperty = false;
        foreach ($this->specialProperties ?? [] as $property) {
            $propDetails = BuildingSpecialProperty::getByCode($property);
            if ($propDetails && $propDetails->category === 'religious') {
                $hasReligiousProperty = true;
                break;
            }
        }

          return $isReligiousType || $hasReligiousProperty;
    }

    public function isMilitary()
    {
          // Check if building type is military or has defensive special properties
          $buildingType = BuildingType::getByCode($this->type);
          $isMilitaryType = $buildingType && ($buildingType->category === 'military');

          $hasMilitaryProperty = false;
        foreach ($this->specialProperties ?? [] as $property) {
            $propDetails = BuildingSpecialProperty::getByCode($property);
            if ($propDetails && in_array($propDetails->category, ['defensive', 'military'])) {
                $hasMilitaryProperty = true;
                break;
            }
        }

          return $isMilitaryType || $hasMilitaryProperty;
    }

    public function isCommercial()
    {
          // Check if building type is commercial or has economic special properties
          $buildingType = BuildingType::getByCode($this->type);
          $isCommercialType = $buildingType && ($buildingType->category === 'commercial');

          $hasCommercialProperty = false;
        foreach ($this->specialProperties ?? [] as $property) {
            $propDetails = BuildingSpecialProperty::getByCode($property);
            if ($propDetails && $propDetails->category === 'economic') {
                $hasCommercialProperty = true;
                break;
            }
        }

          return $isCommercialType || $hasCommercialProperty;
    }

    // Calculate building value (fully database-driven)
    public function calculateValue()
    {
        // Get base value from building type
        $buildingType = BuildingType::getByCode($this->type);
        $baseValue = $buildingType ? ($buildingType->base_cost ?? 500) : 500;

    // Apply condition multiplier
        $conditionMultiplier = $this->condition / 100;

    // Get status multiplier from database
        $buildingStatus = BuildingStatus::getByCode($this->status);
        $statusModifiers = $buildingStatus ? ($buildingStatus->productivity_modifiers ?? []) : [];
        $statusMultiplier = $statusModifiers['value_multiplier'] ?? 1.0;

    // Apply condition level multiplier
        $conditionDetails = BuildingConditionLevel::getConditionDetails($this->condition);
        $conditionValueMultiplier = $conditionDetails['productivity_multiplier'] ?? 1.0;

    // Apply special property modifiers
        $specialPropertyMultiplier = 1.0;
        foreach ($this->specialProperties ?? [] as $property) {
            $propDetails = BuildingSpecialProperty::getByCode($property);
            if ($propDetails && isset($propDetails->effects['value_multiplier'])) {
                $specialPropertyMultiplier *= (1 + $propDetails->effects['value_multiplier']);
            }
        }

        return (int) round($baseValue * $conditionMultiplier * $statusMultiplier * $conditionValueMultiplier * $specialPropertyMultiplier);
    }

    // Production and efficiency methods (database-driven)
    public function getProductionEfficiency()
    {
        $baseEfficiency = 1.0;

    // Apply condition modifier
        $conditionDetails = BuildingConditionLevel::getConditionDetails($this->condition);
        $conditionModifier = $conditionDetails['productivity_multiplier'] ?? 1.0;

    // Apply status modifier
        $buildingStatus = BuildingStatus::getByCode($this->status);
        $statusModifiers = $buildingStatus ? ($buildingStatus->productivity_modifiers ?? []) : [];
        $statusModifier = $statusModifiers['productivity_multiplier'] ?? 1.0;

    // Apply special property modifiers
        $specialModifier = 1.0;
        foreach ($this->specialProperties ?? [] as $property) {
            $propDetails = BuildingSpecialProperty::getByCode($property);
            if ($propDetails && isset($propDetails->effects['productivity_bonus'])) {
                $specialModifier += $propDetails->effects['productivity_bonus'];
            }
            if ($propDetails && isset($propDetails->effects['productivity_penalty'])) {
                $specialModifier += $propDetails->effects['productivity_penalty'];

    // Note: penalty is negative
            }
        }

        return $baseEfficiency * $conditionModifier * $statusModifier * $specialModifier;
    }

    public function getMaintenanceCost()
    {
          // Get base maintenance from building type
          $buildingType = BuildingType::getByCode($this->type);
          $baseMaintenance = $buildingType ? ($buildingType->maintenance_cost ?? 10) : 10;

    // Apply condition multiplier
          $conditionDetails = BuildingConditionLevel::getConditionDetails($this->condition);
          $conditionMultiplier = $conditionDetails['maintenance_multiplier'] ?? 1.0;

    // Apply status modifier
          $buildingStatus = BuildingStatus::getByCode($this->status);
          $statusModifiers = $buildingStatus ? ($buildingStatus->maintenance_modifiers ?? []) : [];
          $statusMultiplier = $statusModifiers['maintenance_multiplier'] ?? 1.0;

    // Apply special property modifiers
          $specialMultiplier = 1.0;
        foreach ($this->specialProperties ?? [] as $property) {
            $propDetails = BuildingSpecialProperty::getByCode($property);
            if ($propDetails && isset($propDetails->effects['maintenance_cost'])) {
                $specialMultiplier += $propDetails->effects['maintenance_cost'];
            }
            if ($propDetails && isset($propDetails->effects['maintenance_penalty'])) {
                $specialMultiplier += $propDetails->effects['maintenance_penalty'];
            }
        }

          return (int) round($baseMaintenance * $conditionMultiplier * $statusMultiplier * $specialMultiplier);
    }
}
