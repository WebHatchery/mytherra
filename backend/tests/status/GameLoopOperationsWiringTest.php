<?php

declare(strict_types=1);

namespace Tests\Status;

use PHPUnit\Framework\TestCase;

final class GameLoopOperationsWiringTest extends TestCase
{
    public function testComposerExposesTickAndHealthCommands(): void
    {
        $composerPath = dirname(__DIR__, 2) . '/composer.json';
        $composer = json_decode((string)file_get_contents($composerPath), true);

        self::assertSame('php scripts/runGameTick.php', $composer['scripts']['game:tick'] ?? null);
        self::assertSame('php scripts/checkGameLoopHealth.php', $composer['scripts']['game:health'] ?? null);
    }

    public function testHealthScriptReportsMonitoringSemantics(): void
    {
        $scriptPath = dirname(__DIR__, 2) . '/scripts/checkGameLoopHealth.php';
        $source = file_get_contents($scriptPath);
        self::assertIsString($source);

        self::assertStringContainsString("'status' => \$status", $source);
        self::assertStringContainsString("'lastTickAgeSeconds'", $source);
        self::assertStringContainsString("'failed_jobs_present'", $source);
        self::assertStringContainsString("'stale_last_tick'", $source);
        self::assertStringContainsString("'last_tick_error'", $source);
        self::assertStringContainsString("'healthy' => 0", $source);
        self::assertStringContainsString("'warning' => 1", $source);
    }

    public function testProductionRunbookDocumentsWorkerCadenceAndAlerts(): void
    {
        $runbookPath = dirname(__DIR__, 2) . '/docs/production-game-loop.md';
        $source = file_get_contents($runbookPath);
        self::assertIsString($source);

        self::assertStringContainsString('composer game:tick', $source);
        self::assertStringContainsString('composer game:health', $source);
        self::assertStringContainsString('Health exit codes', $source);
        self::assertStringContainsString('Recommended Cron Cadence', $source);
        self::assertStringContainsString('Queue Worker Option', $source);
        self::assertStringContainsString('Alert Checklist', $source);
    }
}
