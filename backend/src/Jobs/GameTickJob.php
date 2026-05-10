<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\GameState;
use App\Models\Player;
use App\Services\GameLoopService;

class GameTickJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const DIVINE_FAVOR_PER_TICK = 10;

    public function __construct()
    {
        $this->onQueue('game-loop');
    }

    public function handle()
    {
        try {
            // Get or create game state
            $gameState = GameState::firstOrCreate(
                ['singleton_id' => 'GAME_STATE'],
                ['current_year' => 1]
            );

    // Process game tick
            $this->processGameTick($gameState);

    // Schedule next tick
            $this->release(60);

    // Release back to queue after 60 seconds
        } catch (\Exception $e) {
            \Log::error('Error in game tick processing: ' . $e->getMessage());
            $this->release(60);

    // Retry after 60 seconds even on error
        }
    }

    private function processGameTick(GameState $gameState)
    {
        $loopService = new GameLoopService();

    // 1. Process regions
        $loopService->processRegions($gameState->current_year);

    // 2. Process heroes
        $loopService->processHeroes($gameState->current_year);

    // 3. Process settlements
        $loopService->processSettlements($gameState->current_year);

    // 4. Process expired bets
        $loopService->processExpiredBets($gameState->current_year);

    // 5. Update divine favor
        $this->updateDivineFavor();

    // Log tick completion
        \Log::info('Game tick completed for year ' . $gameState->current_year);
    }

    private function updateDivineFavor()
    {
        $player = Player::firstOrCreate(
            ['id' => 'SINGLE_PLAYER'],
            ['divine_favor' => 100]
        );

        $player->divine_favor += self::DIVINE_FAVOR_PER_TICK;
        $player->save();
    }

    public function failed(\Throwable $exception)
    {
        \Log::error('GameTickJob failed: ' . $exception->getMessage());
    }
}
