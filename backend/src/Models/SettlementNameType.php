<?php

namespace App\Models;

use App\InitData\SettlementNameTypeData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class SettlementNameType extends Model
{
    protected $table = 'settlement_name_types';

    protected $fillable = [
        'id',
        'name',
        'code',
        'description',
        'is_active'
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'is_active' => 'boolean'
    ];

    // Relationships
    public function settlementNamePools()
    {
        return $this->hasMany(SettlementNamePool::class, 'type', 'code');
    }

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('settlement_name_types')) {
            Schema::schema()->create('settlement_name_types', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name', 100);
                $table->string('code', 50)->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active']);
                $table->index(['code']);
            });

    // Seed default settlement name types
            self::seedDefaultTypes();
        }
    }

    public static function seedDefaultTypes()
    {
        foreach (SettlementNameTypeData::getData() as $type) {
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
