<?php

declare(strict_types=1);

namespace App\Models;

use App\InitData\LandmarkStatusData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class LandmarkStatus extends Model
{
    protected $table = 'landmark_statuses';

    protected $fillable = [
        'name',
        'code',
        'description',
        'magic_modifier',
        'danger_modifier',
        'exploration_difficulty_modifier',
        'special_effects',
        'is_active'
    ];

    protected $casts = [
        'magic_modifier' => 'integer',
        'danger_modifier' => 'integer',
        'exploration_difficulty_modifier' => 'integer',
        'special_effects' => 'array',
        'is_active' => 'boolean'
    ];

    public $timestamps = true;

    /**
     * Get all active landmark statuses
     */
    public static function getActiveStatuses()
    {
        return self::where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Get array of active status codes for validation
     */
    public static function getStatusCodes()
    {
        return self::where('is_active', true)->pluck('code')->toArray();
    }

    /**
     * Get status by code
     */
    public static function getByCode($code)
    {
        return self::where('code', $code)->where('is_active', true)->first();
    }

    /**
     * Get status details for game mechanics
     */
    public function getStatusDetails()
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'magic_modifier' => $this->magic_modifier,
            'danger_modifier' => $this->danger_modifier,
            'exploration_difficulty_modifier' => $this->exploration_difficulty_modifier,
            'special_effects' => $this->special_effects ?? []
        ];
    }

    /**
     * Create database table
     */
    public static function createTable()
    {
        if (!Schema::schema()->hasTable('landmark_statuses')) {
            Schema::schema()->create('landmark_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description');
                $table->integer('magic_modifier')->default(0);
                $table->integer('danger_modifier')->default(0);
                $table->integer('exploration_difficulty_modifier')->default(0);
                $table->json('special_effects')->nullable();
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
     * Seed default landmark statuses
     */
    public static function seedDefaultData()
    {
        foreach (LandmarkStatusData::getData() as $statusData) {
            // Set is_active to true if not set
            if (!isset($statusData['is_active'])) {
                $statusData['is_active'] = true;
            }
            self::create($statusData);
        }
    }

    /**
     * Relationship to landmarks
     */
    public function landmarks()
    {
        return $this->hasMany(Landmark::class, 'status', 'code');
    }
}
