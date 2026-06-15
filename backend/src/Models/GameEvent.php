<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class GameEvent extends Model
{
    protected $table = 'game_events';

    protected $fillable = [
        'id',
        'title',
        'description',
        'type',
        'status',
        'region_id',
        'timestamp',
        'related_region_ids',
        'related_hero_ids',
        'related_settlement_ids',
        'related_landmark_ids',
        'related_resource_ids',
        'year'
      ];

    protected $casts = [
        'related_region_ids' => 'array',
        'related_hero_ids' => 'array',
        'related_settlement_ids' => 'array',
        'related_landmark_ids' => 'array',
        'related_resource_ids' => 'array',
        'year' => 'integer'
      ];

    protected $keyType = 'string';

    public $incrementing = false;    public static function createTable()
    {
        if (!Schema::schema()->hasTable('game_events')) {
            Schema::schema()->create('game_events', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('title');
                $table->text('description');
                $table->string('type')->default('general');
                $table->string('status')->default('active');
                $table->string('region_id')->nullable();
                $table->string('timestamp');
                $table->json('related_region_ids')->nullable();
                $table->json('related_hero_ids')->nullable();
                $table->json('related_settlement_ids')->nullable();
                $table->json('related_landmark_ids')->nullable();
                $table->json('related_resource_ids')->nullable();
                $table->integer('year')->nullable();
                $table->timestamps();

    // Foreign key constraints
                $table->foreign('region_id')->references('id')->on('regions')->onDelete('set null');

    // Indexes for performance
                $table->index('region_id');
                $table->index('type');
                $table->index('status');
                $table->index('year');
            });
        }
    }
}
