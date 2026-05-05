<?php

namespace App\Models;

use App\InitData\SettlementStatusData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class SettlementStatus extends Model
{
    protected $table = 'settlement_statuses';

    protected $fillable = [
        'id',
        'name',
        'code',
        'description',
        'prosperity_modifier',
        'growth_modifier',
        'is_active'
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'prosperity_modifier' => 'float',
        'growth_modifier' => 'float',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function settlements()
    {
        return $this->hasMany(Settlement::class, 'status', 'code');
    }

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('settlement_statuses')) {
            Schema::schema()->create('settlement_statuses', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name', 100);
                $table->string('code', 50)->unique();
                $table->text('description')->nullable();
                $table->decimal('prosperity_modifier', 5, 2)->default(1.0);
                $table->decimal('growth_modifier', 5, 2)->default(1.0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                  $table->index(['is_active']);
                $table->index(['code']);
            });

    // Seed default data
            self::seedDefaultStatuses();
        }
    }

    public static function seedDefaultStatuses()
    {
        foreach (SettlementStatusData::getData() as $status) {
            self::create($status);
        }
    }

    // Helper methods
    public static function getActiveStatuses()
    {
        return self::where('is_active', true)->orderBy('prosperity_modifier', 'desc')->get();
    }

    public static function getStatusCodes()
    {
        return self::where('is_active', true)->pluck('code')->toArray();
    }
}
