<?php

namespace App\Commands;

use Illuminate\Console\Command;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use App\Jobs\GameTickJob;

class GameLoopWorker extends Command
{
    protected $signature = 'game:loop {--queue=game-loop}';

    protected $description = 'Start the game loop worker';

    private Worker $worker;

    public function __construct(Worker $worker)
    {
        parent::__construct();
        $this->worker = $worker;
    }

    public function handle()
    {
        // Dispatch initial game tick
        dispatch(new GameTickJob());

    // Start processing the queue
        $this->processQueue();
    }

    private function processQueue()
    {
        $connection = config('queue.default');
        $queue = $this->option('queue');

        $options = new WorkerOptions(
            max_tries: 3,
            backoff: 60,
            memory: 128,
            timeout: 60,
            sleep: 3,
            maxJobs: 0,
            force: false,
            stopWhenEmpty: false,
            rest: 0
        );

        while (true) {
            try {
                $this->worker->daemon(
                    $connection,
                    $queue,
                    $options
                );
            } catch (\Exception $e) {
                $this->error('Queue worker error: ' . $e->getMessage());
                sleep(10);

    // Wait before retrying
            }
        }
    }
}
