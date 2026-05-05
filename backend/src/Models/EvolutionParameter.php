<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class EvolutionParameter extends Model
{
    protected $table = 'evolution_parameters';

    protected $fillable = [
        'parameter',
        'value',
        'description'
    ];

    protected $casts = [
        'value' => 'float'
    ];

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('evolution_parameters')) {
            Schema::schema()->create('evolution_parameters', function (Blueprint $table) {
                $table->id();
                $table->string('parameter')->unique();
                $table->decimal('value', 8, 4);
                $table->string('description');
                $table->timestamps();

                $table->index('parameter');
            });
        }
    }
}
