<?php

namespace App\Models;

use App\InitData\BuildingStatusData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class BuildingStatus extends Model
{
    protected $table = 'building_statuses';

    protected $fillable = [
        'id',
        'name',
        'code',
        'description',
        'productivity_modifier',
        'maintenance_modifier',
        'is_active'
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'productivity_modifier' => 'float',
        'maintenance_modifier' => 'float',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function buildings()
    {
        return $this->hasMany(Building::class, 'status', 'code');
    }

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('building_statuses')) {
            Schema::schema()->create('building_statuses', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name', 100);
                $table->string('code', 50)->unique();
                $table->text('description')->nullable();
                $table->decimal('productivity_modifier', 5, 2)->default(1.0);
                $table->decimal('maintenance_modifier', 5, 2)->default(1.0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active']);
                $table->index(['code']);
            });

    // Seed default building statuses
            self::seedDefaultStatuses();
        }
    }

    public static function seedDefaultStatuses()
    {
        foreach (BuildingStatusData::getData() as $statusData) {
            self::create($statusData);
        }
    }

    // Helper methods
    public static function getActiveStatuses()
    {
        return self::where('is_active', true)->orderBy('name')->get();
    }

    public static function getStatusCodes()
    {
        return self::where('is_active', true)->pluck('code')->toArray();
    }
}
