<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\GameState;
use App\Models\Player;
use App\Repositories\DatabaseService;
use App\Scripts\EnvironmentManager;
use App\Services\GameLoopService;

$options = parseHealthOptions($argv);

try {
    ob_start();
    (new EnvironmentManager())->loadEnvironment();
    ob_end_clean();

    DatabaseService::getInstance();

    $loopService = new GameLoopService();
    $runtime = $loopService->getRuntimeStatus();
    $gameState = GameState::getCurrent();
    $player = Player::getSinglePlayer();
    $generatedAt = new DateTimeImmutable('now');
    $issues = evaluateGameLoopIssues($runtime, $generatedAt, $options);
    $status = healthStatusFromIssues($issues);
    $exitCode = exitCodeForStatus($status);

    echo json_encode([
        'success' => $status !== 'critical',
        'status' => $status,
        'generatedAt' => $generatedAt->format(DATE_ATOM),
        'thresholds' => [
            'maxTickAgeMinutes' => $options['maxAgeMinutes'],
            'maxQueuedJobs' => $options['maxQueuedJobs'],
        ],
        'game' => [
            'currentYear' => (int)$gameState->current_year,
            'divineFavor' => (int)$player->divine_favor,
        ],
        'simulation' => [
            'enabled' => (bool)($runtime['enabled'] ?? false),
            'lastTickAt' => $runtime['lastTickAt'] ?? null,
            'lastTickAgeSeconds' => lastTickAgeSeconds($runtime['lastTickAt'] ?? null, $generatedAt),
            'queue' => $runtime['queue'] ?? [],
        ],
        'issues' => $issues,
    ], JSON_PRETTY_PRINT) . PHP_EOL;

    exit($exitCode);
} catch (Throwable $error) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    fwrite(STDERR, json_encode([
        'success' => false,
        'status' => 'critical',
        'generatedAt' => date(DATE_ATOM),
        'issues' => [[
            'severity' => 'critical',
            'code' => 'health_check_failed',
            'message' => $error->getMessage(),
        ]],
    ], JSON_PRETTY_PRINT) . PHP_EOL);

    exit(2);
}

/**
 * @param array<int, string> $argv
 * @return array{maxAgeMinutes:int,maxQueuedJobs:int}
 */
function parseHealthOptions(array $argv): array
{
    $options = [
        'maxAgeMinutes' => 5,
        'maxQueuedJobs' => 10,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--max-age-minutes=')) {
            $options['maxAgeMinutes'] = max(1, (int)substr($arg, strlen('--max-age-minutes=')));
        }
        if (str_starts_with($arg, '--max-queued-jobs=')) {
            $options['maxQueuedJobs'] = max(0, (int)substr($arg, strlen('--max-queued-jobs=')));
        }
    }

    return $options;
}

/**
 * @param array<string, mixed> $runtime
 * @param array{maxAgeMinutes:int,maxQueuedJobs:int} $options
 * @return array<int, array{severity:string,code:string,message:string}>
 */
function evaluateGameLoopIssues(array $runtime, DateTimeImmutable $now, array $options): array
{
    $issues = [];
    $enabled = (bool)($runtime['enabled'] ?? false);
    $queue = is_array($runtime['queue'] ?? null) ? $runtime['queue'] : [];
    $lastTick = is_array($runtime['lastTickResult'] ?? null) ? $runtime['lastTickResult'] : [];

    if (!$enabled) {
        $issues[] = [
            'severity' => 'warning',
            'code' => 'simulation_paused',
            'message' => 'Simulation scheduling is paused.',
        ];
    }

    if (!($queue['available'] ?? false)) {
        $issues[] = [
            'severity' => 'warning',
            'code' => 'queue_unavailable',
            'message' => 'Queue tables are unavailable; automated worker state cannot be verified.',
        ];
    } else {
        $failedJobs = (int)($queue['failedJobs'] ?? 0);
        $queuedJobs = (int)($queue['jobs'] ?? 0);

        if ($failedJobs > 0) {
            $issues[] = [
                'severity' => 'critical',
                'code' => 'failed_jobs_present',
                'message' => "{$failedJobs} failed queue job(s) need review.",
            ];
        }

        if ($queuedJobs > $options['maxQueuedJobs']) {
            $issues[] = [
                'severity' => 'warning',
                'code' => 'queue_backlog',
                'message' => "{$queuedJobs} queued job(s) exceed the configured threshold of {$options['maxQueuedJobs']}.",
            ];
        }
    }

    $lastTickAt = $runtime['lastTickAt'] ?? null;
    $lastTickAge = lastTickAgeSeconds(is_string($lastTickAt) ? $lastTickAt : null, $now);
    if ($enabled && $lastTickAge === null) {
        $issues[] = [
            'severity' => 'critical',
            'code' => 'missing_last_tick',
            'message' => 'No completed tick has been recorded while simulation is enabled.',
        ];
    } elseif ($enabled && $lastTickAge > ($options['maxAgeMinutes'] * 60)) {
        $issues[] = [
            'severity' => 'critical',
            'code' => 'stale_last_tick',
            'message' => "Last completed tick is {$lastTickAge} seconds old.",
        ];
    }

    foreach (tickErrors($lastTick) as $error) {
        $issues[] = [
            'severity' => 'critical',
            'code' => 'last_tick_error',
            'message' => $error,
        ];
    }

    return $issues;
}

function lastTickAgeSeconds(?string $lastTickAt, DateTimeImmutable $now): ?int
{
    if (!$lastTickAt) {
        return null;
    }

    try {
        $completedAt = new DateTimeImmutable($lastTickAt);
        return max(0, $now->getTimestamp() - $completedAt->getTimestamp());
    } catch (Throwable) {
        return null;
    }
}

/**
 * @param array<string, mixed> $tick
 * @return array<int, string>
 */
function tickErrors(array $tick): array
{
    $messages = [];
    foreach ($tick['errors'] ?? [] as $error) {
        $messages[] = is_string($error) ? $error : json_encode($error);
    }

    foreach (['regions', 'settlements', 'resources', 'heroes', 'bets'] as $section) {
        if (!is_array($tick[$section] ?? null)) {
            continue;
        }

        foreach (($tick[$section]['errors'] ?? []) as $error) {
            if (is_array($error)) {
                $messages[] = (string)($error['message'] ?? json_encode($error));
            } else {
                $messages[] = (string)$error;
            }
        }
    }

    return array_values(array_filter($messages));
}

/**
 * @param array<int, array{severity:string,code:string,message:string}> $issues
 */
function healthStatusFromIssues(array $issues): string
{
    foreach ($issues as $issue) {
        if ($issue['severity'] === 'critical') {
            return 'critical';
        }
    }

    return $issues === [] ? 'healthy' : 'warning';
}

function exitCodeForStatus(string $status): int
{
    return match ($status) {
        'healthy' => 0,
        'warning' => 1,
        default => 2,
    };
}
