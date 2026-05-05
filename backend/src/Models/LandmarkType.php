<?php

namespace App\Models;

use App\InitData\LandmarkTypeData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class LandmarkType extends Model
{
    protected $table = 'landmark_types';

    protected $fillable = [
        'name',
        'code',
        'description',
        'base_magic_level',
        'base_danger_level',
        'discovery_difficulty',
        'exploration_rewards',
        'special_properties',
        'is_active'
    ];

    protected $casts = [
        'base_magic_level' => 'integer',
        'base_danger_level' => 'integer',
        'discovery_difficulty' => 'integer',
        'exploration_rewards' => 'array',
        'special_properties' => 'array',
        'is_active' => 'boolean'
    ];

    public $timestamps = true;

    /**
     * Get all active landmark types
     */
    public static function getActiveTypes()
    {
        return self::where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Get array of active type codes for validation
     */
    public static function getTypeCodes()
    {
        return self::where('is_active', true)->pluck('code')->toArray();
    }

    /**
     * Get type by code
     */
    public static function getByCode($code)
    {
        return self::where('code', $code)->where('is_active', true)->first();
    }

    /**
     * Get type details for game mechanics
     */
    public function getTypeDetails()
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'base_magic_level' => $this->base_magic_level,
            'base_danger_level' => $this->base_danger_level,
            'discovery_difficulty' => $this->discovery_difficulty,
            'exploration_rewards' => $this->exploration_rewards ?? [],
            'special_properties' => $this->special_properties ?? []
        ];
    }

    /**
     * Create database table
     */
    public static function createTable()
    {
        if (!Schema::schema()->hasTable('landmark_types')) {
            Schema::schema()->create('landmark_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description');
                $table->integer('base_magic_level')->default(0);
                $table->integer('base_danger_level')->default(0);
                $table->integer('discovery_difficulty')->default(50);
                $table->json('exploration_rewards')->nullable();
                $table->json('special_properties')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

    // Indexes
                $table->index('code');
                $table->index('is_active');
            });

    // Seed default data
            self::seedDefaultData();
        }
    }

    /**
     * Seed default landmark types
     */
    public static function seedDefaultData()
    {
        foreach (LandmarkTypeData::getData() as $typeData) {
            // Set is_active to true if not set
            if (!isset($typeData['is_active'])) {
                $typeData['is_active'] = true;
            }
            self::create($typeData);
        }
    }

    /**
     * Relationship to landmarks
     */
    public function landmarks()
    {
        return $this->hasMany(Landmark::class, 'type', 'code');
    }
}
