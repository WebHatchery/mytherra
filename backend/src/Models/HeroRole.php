<?php

declare(strict_types=1);

namespace App\Models;

use App\InitData\HeroRoleData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class HeroRole extends Model
{
    protected $table = 'hero_roles';

    protected $fillable = [
        'id',
        'name',
        'code',
        'description',
        'primary_attributes',
        'special_abilities',
        'starting_level_range',
        'is_active'
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'primary_attributes' => 'array',
        'special_abilities' => 'array',
        'starting_level_range' => 'array',
        'is_active' => 'boolean'
    ];

    // Relationships
    public function heroes()
    {
        return $this->hasMany(Hero::class, 'role', 'code');
    }

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('hero_roles')) {
            Schema::schema()->create('hero_roles', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name', 100);
                $table->string('code', 50)->unique();
                $table->text('description')->nullable();
                $table->json('primary_attributes')->nullable();
                $table->json('special_abilities')->nullable();
                $table->json('starting_level_range')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['is_active']);
                $table->index(['code']);
            });

    // Seed default hero roles
            self::seedDefaultRoles();
        }
    }

    public static function seedDefaultRoles()
    {
        foreach (HeroRoleData::getData() as $roleData) {
            self::create($roleData);
        }
    }

    // Helper methods
    public static function getActiveRoles()
    {
        return self::where('is_active', true)->orderBy('name')->get();
    }

    public static function getRoleCodes()
    {
        return self::where('is_active', true)->pluck('code')->toArray();
    }
}
