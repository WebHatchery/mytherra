# Production Game Loop Runbook

Mytherra advances through the PHP tick runtime in `App\Services\GameLoopService`. Production can run it either as a cron/Task Scheduler cadence using `composer game:tick` or through the queue-backed `GameTickJob` path once a long-running Laravel-compatible worker is configured.

## Operator Commands

Run one production tick:

```bash
cd /path/to/mytherra/backend
composer game:tick
```

Check runtime health:

```bash
cd /path/to/mytherra/backend
composer game:health
```

Useful health thresholds:

```bash
composer game:health -- --max-age-minutes=5 --max-queued-jobs=10
```

Health exit codes:

- `0`: healthy.
- `1`: warning. The game can respond, but scheduling or queue visibility needs attention.
- `2`: critical. The last tick is stale, a tick error was recorded, failed jobs exist, or the health check itself could not run.

Pause and resume scheduling through the admin API:

```bash
curl -X POST https://example.com/mytherra/api/admin/game-loop/stop -H "Authorization: Bearer <admin-token>"
curl -X POST https://example.com/mytherra/api/admin/game-loop/start -H "Authorization: Bearer <admin-token>"
```

## Recommended Cron Cadence

Use cron or Windows Task Scheduler when a persistent queue worker is not available. Run once per minute:

```cron
* * * * * cd /var/www/mytherra/backend && /usr/bin/composer game:tick >> storage/logs/game-loop.log 2>&1
```

Pair it with a health check every minute or through the host monitoring system:

```cron
* * * * * cd /var/www/mytherra/backend && /usr/bin/composer game:health -- --max-age-minutes=5 >> storage/logs/game-loop-health.log 2>&1
```

Alert when `composer game:health` exits `2`. Treat exit `1` as a warning page or daily operational review unless the simulation is expected to be paused.

## Queue Worker Option

The queued path uses:

- `App\Jobs\GameTickJob`
- queue name `game-loop`
- database tables `jobs` and `failed_jobs`
- `/api/status` and `composer game:health` for queue visibility

If the production app uses a Laravel-compatible console bootstrap, supervise the worker with restart-on-failure behavior. Keep memory and runtime limits conservative because ticks are intended to be short:

```ini
[program:mytherra-game-loop]
directory=/var/www/mytherra/backend
command=/usr/bin/php artisan game:loop --queue=game-loop
autostart=true
autorestart=true
startsecs=10
stopwaitsecs=20
redirect_stderr=true
stdout_logfile=/var/www/mytherra/backend/storage/logs/game-loop-worker.log
```

Restart procedure:

1. Stop the worker or cron entry.
2. Run `composer game:health` and note the reported `lastTickAt`, queue counts, and failed-job count.
3. Run `composer game:tick` once manually.
4. Run `composer game:health -- --max-age-minutes=5`.
5. Restart the worker or cron entry only after the health check is no worse than `warning`.

## Dashboard Signals

The player-facing Dashboard shows:

- simulation enabled/paused state.
- last tick year range and completion time.
- queue availability, queued job count, and failed job count.
- recent region, settlement, resource, hero, and bet changes.
- tick errors surfaced from the latest stored result.

Use `/api/status` or `composer game:health` for automation. Use the Dashboard to confirm the latest tick is understandable to players.

## Alert Checklist

When `game:health` reports `critical`:

1. Check `issues[].code` in the JSON output.
2. If `stale_last_tick` or `missing_last_tick`, verify cron/worker scheduling and run `composer game:tick` manually.
3. If `failed_jobs_present`, inspect `failed_jobs.exception`, clear only understood failures, and restart the worker.
4. If `last_tick_error`, inspect `simulation.lastTickResult.errors` from `/api/status` and backend logs.
5. If `health_check_failed`, verify `.env`, database connectivity, Composer dependencies, and table initialization.

Do not clear failed jobs or restart the worker repeatedly without preserving the failure payload and backend log lines needed to debug the tick.
