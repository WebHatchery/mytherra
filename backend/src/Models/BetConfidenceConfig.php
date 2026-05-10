<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Capsule\Manager as Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class BetConfidenceConfig extends Model
{
    protected $table = 'bet_confidence_configs';

    protected $fillable = [
        'code',
        'description',
        'odds_modifier',
        'stake_multiplier',
        'is_active',
    ];

    protected $casts = [
        'odds_modifier' => 'float',
        'stake_multiplier' => 'float',
        'is_active' => 'boolean',
    ];

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('bet_confidence_configs')) {
            Schema::schema()->create('bet_confidence_configs', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('description');
                $table->decimal('odds_modifier', 4, 2);
                $table->decimal('stake_multiplier', 4, 2);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('code');
                $table->index('is_active');
            });
        }
    }
}
