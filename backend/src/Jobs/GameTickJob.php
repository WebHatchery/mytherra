<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\GameLoopService;
use App\Utils\Logger;

class GameTickJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue('game-loop');
    }

    public function handle()
    {
        $loopService = new GameLoopService();

        if (!$loopService->isEnabled()) {
            Logger::info('Game loop tick skipped because simulation is disabled.');
            return;
        }

        try {
            $loopService->processTick(true);

    // Schedule next tick
            $this->release(60);

    // Release back to queue after 60 seconds
        } catch (\Exception $e) {
            Logger::error('Error in game tick processing: ' . $e->getMessage());
            $this->release(60);

    // Retry after 60 seconds even on error
        }
    }

    public function failed(\Throwable $exception)
    {
        Logger::error('GameTickJob failed: ' . $exception->getMessage());
    }
}
