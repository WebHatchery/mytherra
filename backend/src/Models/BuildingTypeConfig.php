<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class BuildingTypeConfig extends Model
{
    protected $table = 'building_type_configs';

    protected $fillable = [
        'category',
        'code',
        'name',
        'description',
        'base_cost',
        'maintenance',
        'prosperity_bonus',
        'defensibility_bonus'
    ];

    protected $casts = [
        'base_cost' => 'integer',
        'maintenance' => 'integer',
        'prosperity_bonus' => 'integer',
        'defensibility_bonus' => 'integer'
    ];

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('building_type_configs')) {
            Schema::schema()->create('building_type_configs', function (Blueprint $table) {
                $table->id();
                $table->string('category');
                $table->string('code')->unique();
                $table->string('name');
                $table->string('description');
                $table->integer('base_cost');
                $table->integer('maintenance');
                $table->integer('prosperity_bonus')->nullable();
                $table->integer('defensibility_bonus')->nullable();
                $table->timestamps();

                $table->index(['category', 'code']);
            });
        }
    }
}
