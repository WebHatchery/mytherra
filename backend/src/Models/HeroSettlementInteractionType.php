<?php

namespace App\Models;

use App\InitData\HeroSettlementInteractionTypeData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class HeroSettlementInteractionType extends Model
{
    protected $table = 'hero_settlement_interaction_types';

    protected $fillable = [
        'id',
        'name',
        'code',
        'description',
        'base_duration',
        'success_chance',
        'influence_cost',
        'cooldown_hours',
        'is_active'
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'base_duration' => 'integer',
        'success_chance' => 'float',
        'influence_cost' => 'integer',
        'cooldown_hours' => 'integer',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function interactions()
    {
        return $this->hasMany(HeroSettlementInteraction::class, 'interaction_type', 'code');
    }

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('hero_settlement_interaction_types')) {
            Schema::schema()->create('hero_settlement_interaction_types', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name', 100);
                $table->string('code', 50)->unique();
                $table->text('description')->nullable();
                $table->integer('base_duration')->default(1);
                $table->decimal('success_chance', 5, 2)->default(0.5);
                $table->integer('influence_cost')->default(0);
                $table->integer('cooldown_hours')->default(0);
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
        foreach (HeroSettlementInteractionTypeData::getData() as $type) {
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
