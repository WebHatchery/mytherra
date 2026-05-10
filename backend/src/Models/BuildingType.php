<?php

declare(strict_types=1);

namespace App\Models;

use App\InitData\BuildingTypeData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class BuildingType extends Model
{
    protected $table = 'building_types';

    protected $fillable = [
        'id',
        'name',
        'code',
        'description',
        'category',
        'base_cost',
        'maintenance_cost',
        'prosperity_bonus',
        'defensibility_bonus',
        'special_properties',
        'prerequisites',
        'is_active'
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'base_cost' => 'integer',
        'maintenance_cost' => 'integer',
        'prosperity_bonus' => 'integer',
        'defensibility_bonus' => 'integer',
        'special_properties' => 'array',
        'prerequisites' => 'array',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function buildings()
    {
        return $this->hasMany(Building::class, 'type', 'code');
    }

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('building_types')) {
            Schema::schema()->create('building_types', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name', 100);
                $table->string('code', 50)->unique();
                $table->text('description')->nullable();
                $table->string('category', 50);
                $table->integer('base_cost')->default(100);
                $table->integer('maintenance_cost')->default(10);
                $table->integer('prosperity_bonus')->default(0);
                $table->integer('defensibility_bonus')->default(0);
                $table->json('special_properties')->nullable();
                $table->json('prerequisites')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active']);
                $table->index(['code']);
                $table->index(['category']);
            });

    // Seed default building types
            self::seedDefaultTypes();
        }
    }

    public static function seedDefaultTypes()
    {
        foreach (BuildingTypeData::getData() as $typeData) {
            self::create($typeData);
        }
    }

    // Helper methods
    public static function getActiveTypes()
    {
        return self::where('is_active', true)->orderBy('category')->orderBy('name')->get();
    }

    public static function getTypeCodes()
    {
        return self::where('is_active', true)->pluck('code')->toArray();
    }

    public static function getByCategory($category)
    {
        return self::where('is_active', true)->where('category', $category)->get();
    }
}
