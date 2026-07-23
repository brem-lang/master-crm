# Running the queue worker in production

`routes/console.php` schedules `PullAllCompaniesLeadsJob` every 5 minutes, which
dispatches one `PullCompanyLeadsJob` per active company onto the `database`
queue (`QUEUE_CONNECTION=database` by default — see `.env.example`). Laravel's
scheduler only *enqueues* these jobs; something still has to consume the
queue, or dispatched jobs just sit in the `jobs` table forever.

## Systemd (plain VPS, no Forge)

`deploy/systemd/master-crm-queue-worker@.service` is a systemd **template**
unit (the `@` before `.service`) — one file that can run as one worker or
several concurrent ones, since each instance is independent.

First, edit the `User`, `Group`, `WorkingDirectory`, and `ExecStart` path
placeholders in the file to match the actual deploy user and path. Then:

```bash
sudo cp "deploy/systemd/master-crm-queue-worker@.service" /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now master-crm-queue-worker@1
```

### Running more than one worker (recommended once you have ~20 companies)

With a single worker, companies are processed one at a time from the queue —
a single slow or hanging child-CRM API call (each `PullCompanyLeadsJob` has a
300s timeout) can hold up every other company behind it until it finishes.
Running several workers lets companies sync in parallel instead. This is safe
to do without any code change — each `PullCompanyLeadsJob` is
`ShouldBeUnique` per company, so multiple workers can never double-process
the same company; they simply pick up different companies' jobs concurrently.

Enable as many instances as you want workers, e.g. 3:

```bash
sudo systemctl enable --now master-crm-queue-worker@1
sudo systemctl enable --now master-crm-queue-worker@2
sudo systemctl enable --now master-crm-queue-worker@3
```

Check they're all running: `systemctl status "master-crm-queue-worker@*"`.

## Either way

- The scheduler itself (`Schedule::job(...)->everyFiveMinutes()`) still needs
  its own cron entry in the deploy user's crontab:
  `* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`
- After deploying new code, restart the workers so they pick up the new code
  (`php artisan queue:restart` signals all running workers to finish their
  current job and exit; systemd's `Restart=always` immediately brings them
  back up running the freshly-deployed code) — a running worker otherwise
  keeps old code in memory indefinitely.
