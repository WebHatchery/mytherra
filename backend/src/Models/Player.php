<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class Player extends Model
{
    protected $table = 'players';

    protected $fillable = [
        'id',
        'divine_favor'
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'divine_favor' => 'integer'
    ];

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('players')) {
            Schema::schema()->create('players', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->integer('divine_favor')->default(100);
                $table->timestamps();
            });
        }
    }

    public static function getSinglePlayer()
    {
        return self::firstOrCreate(
            ['id' => 'SINGLE_PLAYER'],
            ['divine_favor' => 100]
        );
    }

    public function addDivineFavor(int $amount): bool
    {
        $this->divine_favor += $amount;
        return $this->save();
    }

    public function spendDivineFavor(int $amount): bool
    {
        if ($this->divine_favor < $amount) {
            return false;
        }
        $this->divine_favor -= $amount;
        return $this->save();
    }

    public function getDivineFavor(): int
    {
        return $this->divine_favor;
    }
}
