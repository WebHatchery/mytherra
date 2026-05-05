<?php

namespace App\Models;

use App\InitData\ResourceNodeTypeData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class ResourceNodeType extends Model
{
    protected $table = 'resource_node_types';

    protected $fillable = [
        'name',
        'code',
        'description',
        'base_output',
        'extraction_difficulty',
        'renewal_rate',
        'properties',
        'resource_category',
        'is_active'
    ];

    protected $casts = [
        'base_output' => 'integer',
        'extraction_difficulty' => 'integer',
        'renewal_rate' => 'integer',
        'properties' => 'array',
        'is_active' => 'boolean'
    ];

    public $timestamps = true;

    /**
     * Get all active resource node types
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
            'base_output' => $this->base_output,
            'extraction_difficulty' => $this->extraction_difficulty,
            'renewal_rate' => $this->renewal_rate,
            'properties' => $this->properties ?? [],
            'resource_category' => $this->resource_category
        ];
    }

    /**
     * Check if type is renewable
     */
    public function isRenewable()
    {
        return $this->renewal_rate > 0;
    }

    /**
     * Check if type is magical
     */
    public function isMagical()
    {
        $properties = $this->properties ?? [];
        return in_array('magical', $properties);
    }

    /**
     * Create database table
     */
    public static function createTable()
    {
        if (!Schema::schema()->hasTable('resource_node_types')) {
            Schema::schema()->create('resource_node_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description');
                $table->integer('base_output')->default(50);
                $table->integer('extraction_difficulty')->default(50);
                $table->integer('renewal_rate')->default(0);
                $table->json('properties')->nullable();
                $table->string('resource_category');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

    // Indexes
                $table->index('code');
                $table->index('is_active');
                $table->index('resource_category');
            });

    // Seed default data
            self::seedDefaultData();
        }
    }

    /**
     * Seed default resource node types
     */
    public static function seedDefaultData()
    {
        foreach (ResourceNodeTypeData::getData() as $typeData) {
            // Set is_active to true if not set
            if (!isset($typeData['is_active'])) {
                $typeData['is_active'] = true;
            }
            self::create($typeData);
        }
    }

    /**
     * Relationship to resource nodes
     */
    public function resourceNodes()
    {
        return $this->hasMany(ResourceNode::class, 'type', 'code');
    }
}
