<?php

namespace App\Models;

use App\InitData\RegionStatusData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class RegionStatus extends Model
{
    protected $table = 'region_statuses';

    protected $fillable = [
        'id',
        'name',
        'code',
        'description',
        'hero_spawn_modifier',
        'prosperity_modifier',
        'chaos_modifier',
        'is_active'
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'hero_spawn_modifier' => 'float',
        'prosperity_modifier' => 'float',
        'chaos_modifier' => 'float',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function regions()
    {
        return $this->hasMany(Region::class, 'status', 'code');
    }

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('region_statuses')) {
            Schema::schema()->create('region_statuses', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name', 100);
                $table->string('code', 50)->unique();
                $table->text('description')->nullable();
                $table->decimal('hero_spawn_modifier', 5, 2)->default(1.0);
                $table->decimal('prosperity_modifier', 5, 2)->default(1.0);
                $table->decimal('chaos_modifier', 5, 2)->default(1.0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active']);
                $table->index(['code']);
            });

            self::seedDefaultStatuses();
        }
    }

    public static function seedDefaultStatuses()
    {
        foreach (RegionStatusData::getData() as $status) {
            self::create($status);
        }
    }

    public static function getStatusCodes()
    {
        return self::where('is_active', true)->pluck('code')->toArray();
    }

    public static function getActiveStatuses()
    {
        return self::where('is_active', true)->orderBy('name')->get();
    }

    public static function getByCode($code)
    {
        return self::where('code', $code)->where('is_active', true)->first();
    }
}
