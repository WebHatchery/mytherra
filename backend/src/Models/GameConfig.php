<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class GameConfig extends Model
{
    protected $table = 'game_configs';

    protected $fillable = [
        'id',
        'category',
        'key',
        'value',
        'data_type',
        'description'
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('game_configs')) {
            Schema::schema()->create('game_configs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('category', 100);
                $table->string('key', 100);
                $table->text('value');
                $table->enum('data_type', ['number', 'string', 'boolean', 'array']);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['category', 'key']);
                $table->index(['category']);
            });
        }
    }
}
