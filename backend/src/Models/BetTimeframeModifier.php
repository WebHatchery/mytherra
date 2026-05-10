<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Capsule\Manager as Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class BetTimeframeModifier extends Model
{
    protected $table = 'bet_timeframe_modifiers';

    protected $fillable = [
        'max_timeframe',
        'modifier',
    ];

    protected $casts = [
        'max_timeframe' => 'integer',
        'modifier' => 'float',
    ];

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('bet_timeframe_modifiers')) {
            Schema::schema()->create('bet_timeframe_modifiers', function (Blueprint $table) {
                $table->id();
                $table->integer('max_timeframe');
                $table->decimal('modifier', 4, 2);
                $table->timestamps();

                $table->index('max_timeframe');
            });
        }
    }
}
