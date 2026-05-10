<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class SettlementTypeConfig extends Model
{
    protected $table = 'settlement_type_configs';

    protected $fillable = [
        'code',
        'description',
        'min_population',
        'max_population',
        'min_buildings',
        'max_buildings',
        'base_defensibility',
        'evolution_threshold'
    ];

    protected $casts = [
        'min_population' => 'integer',
        'max_population' => 'integer',
        'min_buildings' => 'integer',
        'max_buildings' => 'integer',
        'base_defensibility' => 'integer',
        'evolution_threshold' => 'integer'
    ];

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('settlement_type_configs')) {
            Schema::schema()->create('settlement_type_configs', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('description');
                $table->integer('min_population');
                $table->integer('max_population');
                $table->integer('min_buildings');
                $table->integer('max_buildings');
                $table->integer('base_defensibility');
                $table->integer('evolution_threshold')->nullable();
                $table->timestamps();

                $table->index('code');
            });
        }
    }
}
