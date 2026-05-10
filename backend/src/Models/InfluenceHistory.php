<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class InfluenceHistory extends Model
{
    protected $table = 'influence_history';

    protected $fillable = [
        'target_id',
        'target_type',
        'influence_type',
        'strength',
        'description',
        'effects',
        'game_year'
    ];

    protected $casts = [
        'effects' => 'array',
        'game_year' => 'integer'
    ];

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('influence_history')) {
            Schema::schema()->create('influence_history', function (Blueprint $table) {
                $table->id();
                $table->string('target_id');
                $table->string('target_type', 50);
                $table->string('influence_type', 50);
                $table->string('strength', 20);
                $table->text('description')->nullable();
                $table->json('effects')->nullable();
                $table->integer('game_year');
                $table->timestamps();

    // Indexes for performance
                $table->index(['target_type', 'target_id']);
                $table->index('game_year');
                $table->index('created_at');
            });
        }
    }

    // Relationships
    public function targetEntity()
    {
        return match ($this->target_type) {
            'hero' => $this->belongsTo(\App\Models\Hero::class, 'target_id'),
            'region' => $this->belongsTo(\App\Models\Region::class, 'target_id'),
            'settlement' => $this->belongsTo(\App\Models\Settlement::class, 'target_id'),
            'landmark' => $this->belongsTo(\App\Models\Landmark::class, 'target_id'),
            default => null
        };
    }

    // Helper methods
    public static function getInfluenceHistoryForTarget(string $targetType, string $targetId, int $limit = 10)
    {
        return self::where('target_type', $targetType)
                   ->where('target_id', $targetId)
                   ->orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get();
    }

    public static function getRecentInfluences(int $gameYear, int $limit = 50)
    {
        return self::where('game_year', '>=', $gameYear)
                   ->orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get();
    }
}
