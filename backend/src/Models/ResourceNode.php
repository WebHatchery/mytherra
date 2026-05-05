<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;
use InvalidArgumentException;

class ResourceNode extends Model
{
    protected $table = 'resource_nodes';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'region_id',
        'settlement_id',
        'type',
        'name',
        'output',
        'status'
    ];

    protected $casts = [
        'output' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('resource_nodes')) {
            Schema::schema()->create('resource_nodes', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('region_id');
                $table->string('settlement_id')->nullable();
                $table->string('type');
                $table->string('name');
                $table->integer('output')->default(0);
                $table->string('status')->default('active');
                $table->timestamps();

                $table->foreign('region_id')->references('id')->on('regions')->onDelete('cascade');
                $table->foreign('settlement_id')->references('id')->on('settlements')->onDelete('set null');

    // Add indexes for better performance
                $table->index('region_id');
                $table->index('settlement_id');
                $table->index('type');
                $table->index('status');
            });
        }
    }

    // Database-driven methods for validation and reference
    public static function getValidTypes()
    {
        return ResourceNodeType::getTypeCodes();
    }

    public static function getValidStatuses()
    {
        return ResourceNodeStatus::getStatusCodes();
    }

    public static function getTypeDetails()
    {
        return ResourceNodeType::getActiveTypes();
    }

    public static function getStatusDetails()
    {
        return ResourceNodeStatus::getActiveStatuses();
    }

    /**
     * Get effective output considering status modifiers
     */
    public function getEffectiveOutput(): int
    {
        $statusConfig = ResourceNodeStatus::getByCode($this->status);
        if (!$statusConfig) {
            return 0;
        }

        return (int) round($this->output * $statusConfig->output_modifier);
    }

    /**
     * Check if resource node can be harvested
     */
    public function canHarvest(): bool
    {
        $statusConfig = ResourceNodeStatus::getByCode($this->status);
        if (!$statusConfig) {
            return false;
        }
        return $statusConfig->can_harvest;
    }

    /**
     * Get resource node productivity classification
     */
    public function getProductivityClass(): string
    {
        $effectiveOutput = $this->getEffectiveOutput();

        if ($effectiveOutput >= 80) {
            return 'excellent';
        }
        if ($effectiveOutput >= 60) {
            return 'good';
        }
        if ($effectiveOutput >= 40) {
            return 'average';
        }
        if ($effectiveOutput >= 20) {
            return 'poor';
        }
        return 'depleted';
    }

    /**
     * Check if resource node is magical
     */
    public function isMagical(): bool
    {
        $type = ResourceNodeType::getByCode($this->type);
        return $type && in_array('magical', $type->properties ?? []);
    }

    /**
     * Check if resource node is renewable
     */
    public function isRenewable(): bool
    {
        $type = ResourceNodeType::getByCode($this->type);
        return $type && $type->renewal_rate > 0;
    }

    /**
     * Get base output for a specific type
     */
    public static function getBaseOutputForType(string $type): int
    {
        $typeConfig = ResourceNodeType::getByCode($type);
        return $typeConfig ? $typeConfig->base_output : 50;
    }

    /**
     * Get common properties for a specific type
     */
    public static function getCommonPropertiesForType(string $type): array
    {
        $typeConfig = ResourceNodeType::getByCode($type);
        return $typeConfig ? ($typeConfig->properties ?? []) : [];
    }

    /**
     * Generate a name for the resource node based on type
     */
    public static function generateNameForType(string $type): string
    {
        $nameTemplates = [
            'mine' => [
                'Deep Shaft Mine', 'Iron Vein Mine', 'Copper Hollow Mine',
                'Silver Peak Mine', 'Gold Strike Mine', 'Crystal Cave Mine'
            ],
            'quarry' => [
                'Granite Quarry', 'Limestone Quarry', 'Marble Quarry',
                'Sandstone Pit', 'Slate Quarry', 'Basalt Quarry'
            ],
            'forest' => [
                'Timber Grove', 'Ancient Woods', 'Pine Forest',
                'Oak Stand', 'Birch Thicket', 'Cedar Woods'
            ],
            'farmland' => [
                'Fertile Fields', 'Golden Harvest Farm', 'Green Valley Farm',
                'Barley Fields', 'Wheat Meadows', 'Rich Soil Farm'
            ],
            'fishing' => [
                'Clear Waters', 'Fisher\'s Bend', 'Salmon Run',
                'Trout Stream', 'Deep Pool', 'Fisherman\'s Cove'
            ],
            'magical_spring' => [
                'Mystic Spring', 'Arcane Wellspring', 'Mana Fountain',
                'Crystal Spring', 'Ethereal Pool', 'Power Source'
            ]
        ];

        $typeConfig = ResourceNodeType::getByCode($type);
        if (!$typeConfig) {
            return 'Resource Site';
        }

        $templates = $nameTemplates[$type] ?? ['Resource Site'];
        return $templates[array_rand($templates)];
    }

    /**
     * Relationship with Region
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id', 'id');
    }

    /**
     * Relationship with Settlement (optional)
     */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class, 'settlement_id', 'id');
    }

    /**
     * Relationship with ResourceNodeType
     */
    public function resourceNodeType(): BelongsTo
    {
        return $this->belongsTo(ResourceNodeType::class, 'type', 'code');
    }

    /**
     * Relationship with ResourceNodeStatus
     */
    public function resourceNodeStatus(): BelongsTo
    {
        return $this->belongsTo(ResourceNodeStatus::class, 'status', 'code');
    }

    /**
     * Validate model data before saving
     */
    public function save(array $options = [])
    {
        // Validate type
        if (!in_array($this->type, ResourceNodeType::getTypeCodes())) {
            throw new InvalidArgumentException("Invalid resource node type: {$this->type}");
        }

    // Validate status
        if (!in_array($this->status, ResourceNodeStatus::getStatusCodes())) {
            throw new InvalidArgumentException("Invalid resource node status: {$this->status}");
        }

    // Validate output
        if ($this->output < 0 || $this->output > 100) {
            throw new InvalidArgumentException("Invalid output value: {$this->output}. Must be between 0 and 100.");
        }

        return parent::save($options);
    }
}
