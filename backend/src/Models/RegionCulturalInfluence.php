<?php

declare(strict_types=1);

namespace App\Models;

use App\InitData\RegionCulturalInfluenceData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class RegionCulturalInfluence extends Model
{
    protected $table = 'region_cultural_influences';

    protected $fillable = [
        'id',
        'name',
        'code',
        'description',
        'hero_spawn_rate_modifier',
        'development_modifier',
        'stability_modifier',
        'is_active'
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'hero_spawn_rate_modifier' => 'float',
        'development_modifier' => 'float',
        'stability_modifier' => 'float',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function regions()
    {
        return $this->hasMany(Region::class, 'cultural_influence', 'code');
    }

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('region_cultural_influences')) {
            Schema::schema()->create('region_cultural_influences', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name', 100);
                $table->string('code', 50)->unique();
                $table->text('description')->nullable();
                $table->decimal('hero_spawn_rate_modifier', 5, 2)->default(1.0);
                $table->decimal('development_modifier', 5, 2)->default(1.0);
                $table->decimal('stability_modifier', 5, 2)->default(1.0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active']);
                $table->index(['code']);
            });

            self::seedDefaultInfluences();
        }
    }

    public static function seedDefaultInfluences()
    {
        foreach (RegionCulturalInfluenceData::getData() as $influence) {
            self::create($influence);
        }
    }

    public static function getInfluenceCodes()
    {
        return self::where('is_active', true)->pluck('code')->toArray();
    }

    public static function getActiveInfluences()
    {
        return self::where('is_active', true)->orderBy('name')->get();
    }

    public static function getByCode($code)
    {
        return self::where('code', $code)->where('is_active', true)->first();
    }
}
