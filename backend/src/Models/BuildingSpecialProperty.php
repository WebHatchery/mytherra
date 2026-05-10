<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class BuildingSpecialProperty extends Model
{
    protected $table = 'building_special_properties';

    protected $fillable = [
        'name',
        'code',
        'description',
        'effects',
        'rarity',
        'category',
        'is_active'
    ];

    protected $casts = [
        'effects' => 'array',
        'is_active' => 'boolean'
    ];

    public $timestamps = true;

    /**
     * Get all active special properties
     */
    public static function getActiveProperties()
    {
        return self::where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Get array of active property codes for validation
     */
    public static function getPropertyCodes()
    {
        return self::where('is_active', true)->pluck('code')->toArray();
    }

    /**
     * Get property by code
     */
    public static function getByCode($code)
    {
        return self::where('code', $code)->where('is_active', true)->first();
    }

    /**
     * Get properties by category
     */
    public static function getByCategory($category)
    {
        return self::where('category', $category)->where('is_active', true)->get();
    }

    /**
     * Create database table
     */
    public static function createTable()
    {
        if (!Schema::schema()->hasTable('building_special_properties')) {
            Schema::schema()->create('building_special_properties', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description');
                $table->json('effects')->nullable();
                $table->enum('rarity', ['common', 'uncommon', 'rare', 'legendary'])->default('common');
                $table->string('category');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

    // Indexes
                $table->index('code');
                $table->index('is_active');
                $table->index('category');
                $table->index('rarity');
            });

    // Seed default data
            self::seedDefaultData();
        }
    }

    /**
     * Seed default special properties
     */
    public static function seedDefaultData()
    {
        $properties = [
            [
                'name' => 'Magical',
                'code' => 'magical',
                'description' => 'Imbued with arcane energy that enhances its capabilities',
                'effects' => ['magic_production' => 0.2, 'mana_cost_reduction' => 0.1],
                'rarity' => 'uncommon',
                'category' => 'magical'
            ],
            [
                'name' => 'Ancient',
                'code' => 'ancient',
                'description' => 'Built in ages past with forgotten techniques and materials',
                'effects' => ['durability_bonus' => 0.3, 'cultural_value' => 0.5],
                'rarity' => 'rare',
                'category' => 'historical'
            ],
            [
                'name' => 'Cursed',
                'code' => 'cursed',
                'description' => 'Afflicted by dark magic that brings misfortune',
                'effects' => ['productivity_penalty' => -0.2, 'accident_chance' => 0.15],
                'rarity' => 'uncommon',
                'category' => 'supernatural'
            ],
            [
                'name' => 'Sacred',
                'code' => 'sacred',
                'description' => 'Blessed by divine forces and protected from evil',
                'effects' => ['divine_protection' => 0.3, 'undead_resistance' => 0.5],
                'rarity' => 'rare',
                'category' => 'religious'
            ],
            [
                'name' => 'Fortified',
                'code' => 'fortified',
                'description' => 'Reinforced with additional defensive measures',
                'effects' => ['defense_bonus' => 0.4, 'siege_resistance' => 0.3],
                'rarity' => 'common',
                'category' => 'defensive'
            ],
            [
                'name' => 'Hidden',
                'code' => 'hidden',
                'description' => 'Concealed from casual observation and detection',
                'effects' => ['stealth_bonus' => 0.6, 'discovery_difficulty' => 0.5],
                'rarity' => 'uncommon',
                'category' => 'stealth'
            ],
            [
                'name' => 'Luxurious',
                'code' => 'luxurious',
                'description' => 'Built with finest materials and exquisite craftsmanship',
                'effects' => ['comfort_bonus' => 0.4, 'social_status' => 0.3, 'maintenance_cost' => 0.2],
                'rarity' => 'uncommon',
                'category' => 'social'
            ],
            [
                'name' => 'Rundown',
                'code' => 'rundown',
                'description' => 'In poor condition due to neglect or age',
                'effects' => ['maintenance_penalty' => 0.3, 'productivity_penalty' => -0.15],
                'rarity' => 'common',
                'category' => 'condition'
            ],
            [
                'name' => 'Haunted',
                'code' => 'haunted',
                'description' => 'Inhabited by restless spirits or supernatural entities',
                'effects' => ['fear_factor' => 0.4, 'supernatural_activity' => 0.3, 'productivity_penalty' => -0.1],
                'rarity' => 'uncommon',
                'category' => 'supernatural'
            ],
            [
                'name' => 'Blessed',
                'code' => 'blessed',
                'description' => 'Favored by benevolent forces and divine grace',
                'effects' => ['productivity_bonus' => 0.2, 'disaster_resistance' => 0.3],
                'rarity' => 'rare',
                'category' => 'religious'
            ],
            [
                'name' => 'Technological',
                'code' => 'technological',
                'description' => 'Equipped with advanced mechanisms and innovations',
                'effects' => ['efficiency_bonus' => 0.25, 'research_bonus' => 0.2],
                'rarity' => 'uncommon',
                'category' => 'innovation'
            ],
            [
                'name' => 'Artistic',
                'code' => 'artistic',
                'description' => 'Decorated with beautiful artwork and creative elements',
                'effects' => ['cultural_value' => 0.3, 'inspiration_bonus' => 0.15],
                'rarity' => 'common',
                'category' => 'cultural'
            ],
            [
                'name' => 'Scholarly',
                'code' => 'scholarly',
                'description' => 'Designed to facilitate learning and knowledge preservation',
                'effects' => ['research_bonus' => 0.3, 'knowledge_storage' => 0.4],
                'rarity' => 'uncommon',
                'category' => 'educational'
            ],
            [
                'name' => 'Mysterious',
                'code' => 'mysterious',
                'description' => 'Shrouded in enigma with unknown origins or purposes',
                'effects' => ['discovery_bonus' => 0.2, 'random_events' => 0.1],
                'rarity' => 'rare',
                'category' => 'supernatural'
            ],
            [
                'name' => 'Dangerous',
                'code' => 'dangerous',
                'description' => 'Poses risks to those who work within or visit',
                'effects' => ['accident_chance' => 0.2, 'hazard_pay' => 0.3],
                'rarity' => 'common',
                'category' => 'hazardous'
            ],
            [
                'name' => 'Profitable',
                'code' => 'profitable',
                'description' => 'Generates additional income through various means',
                'effects' => ['income_bonus' => 0.25, 'trade_efficiency' => 0.2],
                'rarity' => 'uncommon',
                'category' => 'economic'
            ],
            [
                'name' => 'Strategic',
                'code' => 'strategic',
                'description' => 'Positioned in a tactically advantageous location',
                'effects' => ['tactical_bonus' => 0.3, 'control_radius' => 0.2],
                'rarity' => 'uncommon',
                'category' => 'military'
            ],
            [
                'name' => 'Cultural',
                'code' => 'cultural',
                'description' => 'Significant to local traditions and heritage',
                'effects' => ['cultural_influence' => 0.4, 'tourism_value' => 0.2],
                'rarity' => 'common',
                'category' => 'cultural'
            ],
            [
                'name' => 'Religious',
                'code' => 'religious',
                'description' => 'Dedicated to spiritual practices and divine worship',
                'effects' => ['divine_favor' => 0.2, 'pilgrimage_value' => 0.3],
                'rarity' => 'common',
                'category' => 'religious'
            ],
            [
                'name' => 'Defensive',
                'code' => 'defensive',
                'description' => 'Built primarily for protection and military defense',
                'effects' => ['defense_rating' => 0.5, 'garrison_capacity' => 0.3],
                'rarity' => 'common',
                'category' => 'defensive'
            ]
        ];

        foreach ($properties as $property) {
            self::create($property);
        }
    }

    /**
     * Relationship to buildings (many-to-many through special_properties JSON field)
     */
    public function getBuildings()
    {
        return Building::whereJsonContains('specialProperties', $this->code)->get();
    }
}
