<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class GameState extends Model
{
    public const SINGLETON_ID = 'GAME_STATE';

    protected $table = 'game_states';

    protected $fillable = [
        'singleton_id',
        'current_year'
    ];

    protected $primaryKey = 'singleton_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'current_year' => 'integer'
    ];

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('game_states')) {
            Schema::schema()->create('game_states', function (Blueprint $table) {
                $table->string('singleton_id')->primary();
                $table->integer('current_year')->default(1);
                $table->timestamps();
            });
        }
    }

    public static function getCurrent()
    {
        $state = self::find(self::SINGLETON_ID);
        if ($state) {
            return $state;
        }

        $legacyState = self::find('default');
        if ($legacyState) {
            $legacyState->singleton_id = self::SINGLETON_ID;
            $legacyState->save();
            return $legacyState;
        }

        return self::firstOrCreate(
            ['singleton_id' => self::SINGLETON_ID],
            ['current_year' => 1]
        );
    }
}
