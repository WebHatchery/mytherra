<?php

namespace App\Models;

use App\InitData\GameInitialConfigData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class GameInitialConfig extends Model
{
    protected $table = 'game_initial_configs';

    protected $fillable = [
        'id',
        'category',
        'subcategory',
        'key',
        'value',
        'data_type',
        'description',
        'is_active'
    ];

    protected $casts = [
        'value' => 'string',
        'is_active' => 'boolean'
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    // Initialize the model
    public static function createTable()
    {
        if (!Schema::schema()->hasTable('game_initial_configs')) {
            Schema::schema()->create('game_initial_configs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('category', 100);

    // e.g., 'hero', 'region', 'settlement'
                $table->string('subcategory', 100);

    // e.g., 'leveling', 'aging', 'evolution'
                $table->string('key', 100);

    // e.g., 'base_level_up_chance'
                $table->text('value');
                $table->enum('data_type', ['number', 'string', 'boolean', 'array']);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['category', 'subcategory', 'key']);
                $table->index(['category', 'subcategory']);
                $table->index(['is_active']);
            });
        }
    }

    // Default configuration data
    public static function seedDefaultConfig()
    {
        foreach (GameInitialConfigData::getData() as $config) {
            self::updateOrCreate(
                [
                    'category' => $config['category'],
                    'subcategory' => $config['subcategory'],
                    'key' => $config['key']
                ],
                [
                    'id' => $config['id'],
                    'value' => $config['value'],
                    'data_type' => $config['data_type'],
                    'description' => $config['description'],
                    'is_active' => true
                ]
            );
        }
    }

    // Get a single configuration value
    public static function getValue($category, $key, $subcategory = null)
    {
        $query = self::where([
            'category' => $category,
            'key' => $key,
            'is_active' => true
        ]);

        if ($subcategory) {
            $query->where('subcategory', $subcategory);
        }

        $config = $query->first();
        if (!$config) {
            return null;
        }

        return self::castValue($config->value, $config->data_type);
    }

    // Get all configurations in a category
    public static function getCategoryConfigs($category, $subcategory = null)
    {
        $query = self::where([
            'category' => $category,
            'is_active' => true
        ]);

        if ($subcategory) {
            $query->where('subcategory', $subcategory);
        }

        $configs = $query->get();
        return $configs->mapWithKeys(function ($config) {
            $key = $config->subcategory
                ? "{$config->subcategory}.{$config->key}"
                : $config->key;
            return [$key => self::castValue($config->value, $config->data_type)];
        });
    }

    // Helper method to cast values to their proper type
    private static function castValue($value, $type)
    {
        switch ($type) {
            case 'number':
                return is_numeric($value) ? floatval($value) : 0;
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'array':
                return json_decode($value, true) ?? [];
            default:
                return $value;
        }
    }
}
