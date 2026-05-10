<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Capsule\Manager as Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class BetTypeConfig extends Model
{
    protected $table = 'bet_type_configs';

    protected $fillable = [
        'code',
        'description',
        'base_odds',
        'resolve_conditions',
        'is_active',
    ];

    protected $casts = [
        'base_odds' => 'float',
        'is_active' => 'boolean',
    ];

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('bet_type_configs')) {
            Schema::schema()->create('bet_type_configs', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('description');
                $table->decimal('base_odds', 4, 2);
                $table->string('resolve_conditions');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('code');
                $table->index('is_active');
            });
        }
    }
}
