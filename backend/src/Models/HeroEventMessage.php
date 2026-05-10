<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class HeroEventMessage extends Model
{
    protected $table = 'hero_event_messages';

    protected $fillable = [
        'code',
        'message_template',
        'category',
        'subcategory',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('hero_event_messages')) {
            Schema::schema()->create('hero_event_messages', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->text('message_template');
                $table->string('category');

    // level, movement, death, etc.
                $table->string('subcategory')->nullable();

    // For category-specific subdivisions
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('code');
                $table->index('is_active');
                $table->index(['category', 'subcategory']);
            });

    // Seed default messages
            self::seedDefaultMessages();
        }
    }

    public static function seedDefaultMessages()
    {
        $messages = [
            // Level up messages
            [
                'code' => 'level_up_single',
                'message_template' => 'Year {year}: {heroName} reached level {level}.',
                'category' => 'level',
                'subcategory' => 'single'
            ],
            [
                'code' => 'level_up_multiple',
                'message_template' => 'Year {year}: {heroName} rapidly advanced {levelsGained} levels, reaching level {level}!',
                'category' => 'level',
                'subcategory' => 'multiple'
            ],
            [
                'code' => 'level_up_feat',
                'message_template' => 'Year {year}: Achieved the rank of level {level}, marking a significant milestone in their journey.',
                'category' => 'level',
                'subcategory' => 'feat'
            ],

            // Movement messages
            [
                'code' => 'movement_stay',
                'message_template' => '{heroName} remained in their current region.',
                'category' => 'movement',
                'subcategory' => 'stay'
            ],
            [
                'code' => 'movement_no_options',
                'message_template' => '{heroName} had nowhere else to travel.',
                'category' => 'movement',
                'subcategory' => 'no_options'
            ],
            [
                'code' => 'movement_travel',
                'message_template' => '{heroName} traveled from {fromRegion} to {toRegion}.',
                'category' => 'movement',
                'subcategory' => 'travel'
            ]
        ];

        foreach ($messages as $message) {
            self::create($message);
        }
    }

    public static function getMessageTemplate(string $code, string $category = null): ?string
    {
        $query = self::where('code', $code)->where('is_active', true);
        if ($category) {
            $query->where('category', $category);
        }

        $message = $query->first();
        return $message ? $message->message_template : null;
    }
}
