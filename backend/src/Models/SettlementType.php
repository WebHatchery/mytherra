<?php

namespace App\Models;

use App\InitData\SettlementTypeData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class SettlementType extends Model
{
    protected $table = 'settlement_types';

    protected $fillable = [
        'id',
        'name',
        'code',
        'description',
        'min_population',
        'max_population',
        'is_active'
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'min_population' => 'integer',
        'max_population' => 'integer',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function settlements()
    {
        return $this->hasMany(Settlement::class, 'type', 'code');
    }

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('settlement_types')) {
            Schema::schema()->create('settlement_types', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name', 100);
                $table->string('code', 50)->unique();
                $table->text('description')->nullable();
                $table->integer('min_population')->default(0);
                $table->integer('max_population')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active']);
                $table->index(['code']);
            });

    // Seed default settlement types
            self::seedDefaultTypes();
        }
    }

    public static function seedDefaultTypes()
    {
        foreach (SettlementTypeData::getData() as $type) {
            self::create($type);
        }
    }

    // Helper methods
    public static function getActiveTypes()
    {
        return self::where('is_active', true)->orderBy('min_population')->get();
    }

    public static function getTypeCodes()
    {
        return self::where('is_active', true)->pluck('code')->toArray();
    }

    public function isValidForPopulation($population)
    {
        if ($population < $this->min_population) {
            return false;
        }

        if ($this->max_population && $population > $this->max_population) {
            return false;
        }

        return true;
    }
}
