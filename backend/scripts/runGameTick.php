<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Scripts\EnvironmentManager;
use App\Repositories\DatabaseService;
use App\Services\GameLoopService;

$advanceYear = !in_array('--no-advance', $argv, true);

try {
    (new EnvironmentManager())->loadEnvironment();
    DatabaseService::getInstance();

    $result = (new GameLoopService())->processTick($advanceYear);
    echo json_encode([
        'success' => true,
        'data' => $result
    ], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, json_encode([
        'success' => false,
        'message' => $error->getMessage()
    ], JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}
