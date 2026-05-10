<?php

declare(strict_types=1);

namespace App\Models;

use App\InitData\ResourceNodeStatusData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class ResourceNodeStatus extends Model
{
    protected $table = 'resource_node_statuses';

    protected $fillable = [
        'name',
        'code',
        'description',
        'output_modifier',
        'extraction_difficulty_modifier',
        'can_harvest',
        'special_effects',
        'is_active'
    ];

    protected $casts = [
        'output_modifier' => 'float',
        'extraction_difficulty_modifier' => 'integer',
        'can_harvest' => 'boolean',
        'special_effects' => 'array',
        'is_active' => 'boolean'
    ];

    public $timestamps = true;

    /**
     * Get all active resource node statuses
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
            'output_modifier' => $this->output_modifier,
            'extraction_difficulty_modifier' => $this->extraction_difficulty_modifier,
            'can_harvest' => $this->can_harvest,
            'special_effects' => $this->special_effects ?? []
        ];
    }

    /**
     * Create database table
     */
    public static function createTable()
    {
        if (!Schema::schema()->hasTable('resource_node_statuses')) {
            Schema::schema()->create('resource_node_statuses', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description');
                $table->decimal('output_modifier', 4, 2)->default(1.0);
                $table->integer('extraction_difficulty_modifier')->default(0);
                $table->boolean('can_harvest')->default(true);
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
     * Seed default data
     */
    public static function seedDefaultData()
    {
        foreach (ResourceNodeStatusData::getData() as $statusData) {
            // Set is_active to true if not set
            if (!isset($statusData['is_active'])) {
                $statusData['is_active'] = true;
            }
            self::create($statusData);
        }
    }

    /**
     * Relationship to resource nodes
     */
    public function resourceNodes()
    {
        return $this->hasMany(ResourceNode::class, 'status', 'code');
    }
}
