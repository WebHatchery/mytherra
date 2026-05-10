<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Schema;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'auth_user_id',
        'auth0_id',
        'auth_email',
        'email',
        'auth_username',
        'username',
        'display_name',
        'divine_influence',
        'divine_favor',
        'level',
        'experience',
        'character_class',
        'guild_id',
        'guild_rank',
        'betting_stats',
        'game_preferences',
        'is_active',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'auth_user_id' => 'integer',
        'divine_influence' => 'integer',
        'divine_favor' => 'integer',
        'level' => 'integer',
        'experience' => 'integer',
        'betting_stats' => 'json',
        'game_preferences' => 'json',
        'is_active' => 'boolean'
    ];

    public static function createTable()
    {
        if (!Schema::schema()->hasTable('users')) {
            Schema::schema()->create('users', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('auth_user_id')->nullable()->index();
                $table->string('auth0_id')->nullable()->index();
                $table->string('auth_email')->nullable();
                $table->string('email')->nullable();
                $table->string('auth_username')->nullable();
                $table->string('username')->nullable();
                $table->string('display_name')->nullable();
                $table->integer('divine_influence')->default(100);
                $table->integer('divine_favor')->default(100);
                $table->integer('level')->default(1);
                $table->integer('experience')->default(0);
                $table->string('character_class')->default('novice');
                $table->unsignedBigInteger('guild_id')->nullable();
                $table->string('guild_rank')->nullable();
                $table->json('betting_stats')->nullable();
                $table->json('game_preferences')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('auth_user_id', 'idx_auth_user_id');
                $table->index('auth0_id', 'idx_auth0_id');
            });
        }
    }

    public static function createOrUpdateFromAuthData(array $authData): self
    {
        $isGuest = (bool) ($authData['is_guest'] ?? false) || (($authData['auth_type'] ?? 'frontpage') === 'guest');
        $externalId = (string) ($authData['user_id'] ?? '');

        if ($isGuest) {
            $user = self::where('auth0_id', $externalId)->first();
            if (!$user) {
                $baseUsername = 'guest_' . substr(preg_replace('/[^a-zA-Z0-9]/', '', $externalId), 0, 10);
                $username = $baseUsername;
                $counter = 1;
                while (self::where('username', $username)->exists()) {
                    $counter++;
                    $username = $baseUsername . '_' . $counter;
                }

                $user = new self();
                $user->auth0_id = $externalId;
                $user->auth_username = $username;
                $user->username = $username;
                $user->display_name = 'Guest Oracle';
                $user->auth_email = '';
                $user->email = '';
                $user->divine_influence = 100;
                $user->divine_favor = 100;
                $user->betting_stats = [];
                $user->game_preferences = [];
            }

            $user->is_active = true;
            $user->save();
            return $user;
        }

        $user = null;
        if ($externalId !== '' && ctype_digit($externalId)) {
            $user = self::where('auth_user_id', (int) $externalId)->first();
        }
        if (!$user && $externalId !== '') {
            $user = self::where('auth0_id', $externalId)->first();
        }
        if (!$user && !empty($authData['email'])) {
            $user = self::where('auth_email', $authData['email'])->orWhere('email', $authData['email'])->first();
        }

        if (!$user) {
            $user = new self();
            if ($externalId !== '' && ctype_digit($externalId)) {
                $user->auth_user_id = (int) $externalId;
            }
            $user->divine_influence = 100;
            $user->divine_favor = 100;
            $user->betting_stats = [];
            $user->game_preferences = [];
        }

        if ($externalId !== '') {
            $user->auth0_id = $externalId;
        }
        if ($externalId !== '' && ctype_digit($externalId)) {
            $user->auth_user_id = (int) $externalId;
        }
        $user->auth_email = $authData['email'] ?? $user->auth_email;
        $user->auth_username = $authData['username'] ?? $user->auth_username ?? $authData['email'] ?? 'user_' . $externalId;
        $user->username = $user->username ?? $user->auth_username;
        $user->display_name = $authData['display_name'] ?? $authData['username'] ?? $user->display_name ?? $user->auth_username;
        $user->is_active = true;
        $user->save();

        return $user;
    }

    public function addDivineInfluence(int $amount): bool
    {
        $this->divine_influence += $amount;
        return $this->save();
    }

    public function spendDivineInfluence(int $amount): bool
    {
        if ($this->divine_influence < $amount) {
            return false;
        }
        $this->divine_influence -= $amount;
        return $this->save();
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

    public function getDivineInfluence(): int
    {
        return $this->divine_influence;
    }

    public function getDivineFavor(): int
    {
        return $this->divine_favor;
    }

    public function updateBettingStats(array $stats): bool
    {
        $currentStats = $this->betting_stats ?? [];
        $this->betting_stats = array_merge($currentStats, $stats);
        return $this->save();
    }

    public function updateGamePreferences(array $preferences): bool
    {
        $currentPreferences = $this->game_preferences ?? [];
        $this->game_preferences = array_merge($currentPreferences, $preferences);
        return $this->save();
    }

    public function addExperience(int $amount): bool
    {
        $this->experience += $amount;
        $newLevel = intval($this->experience / 1000) + 1;
        if ($newLevel > $this->level) {
            $this->level = $newLevel;
        }
        return $this->save();
    }

    public function getLevelProgress(): float
    {
        $currentLevelXP = ($this->level - 1) * 1000;
        $progressXP = $this->experience - $currentLevelXP;
        return ($progressXP / 1000) * 100;
    }

    public function joinGuild(int $guildId, string $rank = 'member'): bool
    {
        $this->guild_id = $guildId;
        $this->guild_rank = $rank;
        return $this->save();
    }

    public function leaveGuild(): bool
    {
        $this->guild_id = null;
        $this->guild_rank = null;
        return $this->save();
    }

    public function promoteInGuild(string $newRank): bool
    {
        if ($this->guild_id) {
            $this->guild_rank = $newRank;
            return $this->save();
        }
        return false;
    }

    public function getPowerLevel(): int
    {
        return ($this->level * 100) + $this->divine_influence + $this->divine_favor;
    }

    public function getCharacterRank(): string
    {
        $powerLevel = $this->getPowerLevel();
        if ($powerLevel >= 10000) {
            return 'Divine Champion';
        }
        if ($powerLevel >= 5000) {
            return 'Legendary Hero';
        }
        if ($powerLevel >= 2500) {
            return 'Epic Adventurer';
        }
        if ($powerLevel >= 1000) {
            return 'Veteran Explorer';
        }
        if ($powerLevel >= 500) {
            return 'Skilled Adventurer';
        }
        if ($powerLevel >= 200) {
            return 'Novice Explorer';
        }
        return 'Apprentice';
    }
}
