<?php

declare(strict_types=1);

namespace App\Models;

use App\InitData\RegionClimateTypeData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class RegionClimateType extends Model
{
    protected $table = 'region_climate_types';

    protected $fillable = [
        'id',
        'name',
        'code',
        'description',
        'resource_modifier',
        'population_growth_modifier',
        'is_active'
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'resource_modifier' => 'float',
        'population_growth_modifier' => 'float',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function regions()
    {
        return $this->hasMany(Region::class, 'climate_type', 'code');
    }

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('region_climate_types')) {
            Schema::schema()->create('region_climate_types', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name', 100);
                $table->string('code', 50)->unique();
                $table->text('description')->nullable();
                $table->decimal('resource_modifier', 5, 2)->default(1.0);
                $table->decimal('population_growth_modifier', 5, 2)->default(1.0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active']);
                $table->index(['code']);
            });

            self::seedDefaultTypes();
        }
    }

    public static function seedDefaultTypes()
    {
        foreach (RegionClimateTypeData::getData() as $type) {
            self::create($type);
        }
    }

    public static function getTypeCodes()
    {
        return self::where('is_active', true)->pluck('code')->toArray();
    }

    public static function getActiveTypes()
    {
        return self::where('is_active', true)->orderBy('name')->get();
    }

    public static function getByCode($code)
    {
        return self::where('code', $code)->where('is_active', true)->first();
    }
}
