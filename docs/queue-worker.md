# Running the queue worker in production

`routes/console.php` schedules `PullAllCompaniesLeadsJob` every 5 minutes, which
dispatches one `PullCompanyLeadsJob` per active company onto the `database`
queue (`QUEUE_CONNECTION=database` by default — see `.env.example`). Laravel's
scheduler only *enqueues* these jobs; something still has to consume the
queue, or dispatched jobs just sit in the `jobs` table forever.

Two ways to run that consumer, pick whichever matches how this app is hosted:

## Option A — Laravel Forge

Forge → your site → **Queue** tab → **New Worker**:

- Connection: `database`
- Queue: `default`
- Max Tries: `3` (matches `PullCompanyLeadsJob::$tries`)
- Timeout: `300` or higher (matches `PullCompanyLeadsJob::$timeout`)

Forge supervises the worker itself (auto-restart on crash/deploy) — no file
in this repo is needed for that path.

## Option B — plain VPS (systemd)

Use `deploy/systemd/master-crm-queue-worker.service` as a template:

```bash
sudo cp deploy/systemd/master-crm-queue-worker.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now master-crm-queue-worker
```

Update the `User`, `ExecStart`, and `WorkingDirectory` paths in the unit file
to match the actual deploy user and path first.

## Either way

- The scheduler itself (`Schedule::job(...)->everyFiveMinutes()`) still needs
  its own cron entry: `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`
  — Forge's "Scheduler" toggle does this automatically; on a plain VPS it
  needs to be added to the deploy user's crontab.
- After deploying new code, restart the worker (`php artisan queue:restart`,
  or Forge's worker restart button) — running workers keep old code in memory
  until restarted.
