<?php

declare(strict_types=1);

namespace App\Models;

use App\InitData\HeroDeathReasonData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class HeroDeathReason extends Model
{
    protected $table = 'hero_death_reasons';

    protected $fillable = [
        'code',
        'description',
        'is_active',
        'category',
        'severity'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('hero_death_reasons')) {
            Schema::schema()->create('hero_death_reasons', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->text('description');
                $table->string('category')->default('combat');

    // combat, natural, magical, tragic
                $table->integer('severity')->default(1);

    // 1-5, affects event impact
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('code');
                $table->index('is_active');
                $table->index(['category', 'severity']);
            });

    // Seed default death reasons
            self::seedDefaultReasons();
        }
    }

    public static function seedDefaultReasons()
    {
        foreach (HeroDeathReasonData::getData() as $reason) {
            self::create($reason);
        }
    }
}
