<?php

namespace App\Models;

use App\InitData\BuildingConditionLevelData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class BuildingConditionLevel extends Model
{
    protected $table = 'building_condition_levels';

    protected $fillable = [
        'name',
        'code',
        'description',
        'min_condition',
        'max_condition',
        'color_code',
        'maintenance_multiplier',
        'productivity_multiplier',
        'is_active'
    ];

    protected $casts = [
        'min_condition' => 'integer',
        'max_condition' => 'integer',
        'maintenance_multiplier' => 'float',
        'productivity_multiplier' => 'float',
        'is_active' => 'boolean'
    ];

    public $timestamps = true;

    /**
     * Get all active condition levels
     */
    public static function getActiveLevels()
    {
        return self::where('is_active', true)->orderBy('min_condition', 'desc')->get();
    }

    /**
     * Get condition level by condition value
     */
    public static function getByCondition($conditionValue)
    {
        return self::where('is_active', true)
                   ->where('min_condition', '<=', $conditionValue)
                   ->where('max_condition', '>=', $conditionValue)
                   ->first();
    }

    /**
     * Get condition level details for a specific value
     */
    public static function getConditionDetails($conditionValue)
    {
        $level = self::getByCondition($conditionValue);

        if (!$level) {
            return [
                'name' => 'Unknown',
                'description' => 'Unknown condition level',
                'color_code' => '#666666',
                'maintenance_multiplier' => 1.0,
                'productivity_multiplier' => 1.0
            ];
        }

        return [
            'name' => $level->name,
            'description' => $level->description,
            'color_code' => $level->color_code,
            'maintenance_multiplier' => $level->maintenance_multiplier,
            'productivity_multiplier' => $level->productivity_multiplier
        ];
    }

    /**
     * Create database table
     */
    public static function createTable()
    {
        if (!Schema::schema()->hasTable('building_condition_levels')) {
            Schema::schema()->create('building_condition_levels', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description');
                $table->integer('min_condition');
                $table->integer('max_condition');
                $table->string('color_code', 7)->default('#666666');
                $table->decimal('maintenance_multiplier', 3, 2)->default(1.0);
                $table->decimal('productivity_multiplier', 3, 2)->default(1.0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

    // Indexes
                $table->index('code');
                $table->index('is_active');
                $table->index(['min_condition', 'max_condition']);
            });

    // Seed default data
            self::seedDefaultData();
        }
    }

    /**
     * Seed default condition levels
     */
    public static function seedDefaultData()
    {
        foreach (BuildingConditionLevelData::getData() as $levelData) {
            // Set is_active to true if not set
            if (!isset($levelData['is_active'])) {
                $levelData['is_active'] = true;
            }
            self::create($levelData);
        }
    }

    /**
     * Relationship to buildings (calculated dynamically based on condition)
     */
    public function getBuildings()
    {
        return Building::whereBetween('condition', [$this->min_condition, $this->max_condition])->get();
    }
}
