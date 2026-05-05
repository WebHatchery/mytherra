<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class SettlementNamePool extends Model
{
    protected $table = 'settlement_name_pools';

    protected $fillable = [
        'type',
        'value'
    ];    public static function createTable()
    {
        if (!Schema::schema()->hasTable('settlement_name_pools')) {
            Schema::schema()->create('settlement_name_pools', function (Blueprint $table) {
                $table->id();
                $table->string('type');
                $table->string('value');
                $table->timestamps();

                $table->foreign('type')->references('code')->on('settlement_name_types');
                $table->unique(['type', 'value']);
                $table->index('type');
            });
        }
    }

    // Relationship with type
    public function nameType()
    {
        return $this->belongsTo(SettlementNameType::class, 'type', 'code');
    }

    // Get valid types from lookup table
    public static function getValidTypes()
    {
        return SettlementNameType::getTypeCodes();
    }

    // Validate type before saving
    public function save(array $options = [])
    {
        if (!in_array($this->type, self::getValidTypes())) {
            throw new InvalidArgumentException("Invalid settlement name type: {$this->type}");
        }
        return parent::save($options);
    }
}
