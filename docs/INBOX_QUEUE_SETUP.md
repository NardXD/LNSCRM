# Inbox & messaging queues on SiteGround (no Redis / Horizon)

Inbox and outbound-messaging background work uses the **database** queue driver and named queues. Horizon is not required.

## Queues

| Queue | Work |
|-------|------|
| `messaging` | Outbound SMS/WhatsApp sends — the Twilio API call itself |
| `inbox-mail` | Reply/compose sends (both immediate and scheduled), snooze reopens, Outlook mail sync |
| `inbox-notify` | Thread / assignee notifications |
| `default` | Inbound lead rules and other non-urgent inbox work |

`messaging` is listed first and kept separate from the inbox queues so a burst of
SMS/WhatsApp sends can't delay mail sync, and vice versa.

Clicking "Send" on an inbox reply no longer waits on the Outlook (Graph API) call —
it creates a `ScheduledInboxReply` row (`is_immediate = true`, `send_at = now()`)
and dispatches `ProcessScheduledInboxReplyJob` onto `inbox-mail` immediately,
instead of waiting for the once-a-minute `dispatchDue()` sweep. This is the same
mechanism a real "schedule for later" reply already used, so a worker outage
means replies sit queued (not lost) until a worker or the cron fallback picks
them up — the same guarantee scheduled sends already had.

## Recommended (no root needed): a cron-driven watchdog

Most SiteGround plans — including Custom/Cloud — give SSH but not root, so
Supervisor (see below) isn't an option. `tools/queue-watchdog.sh` gets you most
of the same benefit without it: a cron job that checks every few minutes
whether a long-running worker is already alive, and starts one in the
background if not. It does **not** start a duplicate worker on every run, so
it's safe to schedule frequently.

In Site Tools → Cron Jobs, add:

```bash
*/3 * * * * /home/USER/www/YOUR_SITE/tools/queue-watchdog.sh >> /home/USER/www/YOUR_SITE/storage/logs/queue-watchdog.log 2>&1
```

Replace `USER`/`YOUR_SITE` with your real path, then make the script executable once via SSH:

```bash
chmod +x /home/USER/www/YOUR_SITE/tools/queue-watchdog.sh
```

Verify it's actually running after the first cron tick:

```bash
pgrep -fa "artisan queue:work"
tail -f storage/logs/queue-worker.log
```

The worker recycles itself every hour (`--max-time=3600`) to pick up deploys
and avoid memory creep; the watchdog cron notices it's gone and restarts it
within a few minutes. Worst case (the watchdog itself missing a beat, or the
process getting reaped by the host) — messages sit queued, not lost, and the
once-a-minute cron fallback below still drains them, just slower.

## If you do have root: Supervisor instead

A true process supervisor reacts to a crash immediately instead of within a
few minutes, so prefer this if your plan gives root access:

```ini
[program:lnscrm-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /home/USER/www/YOUR_SITE/artisan queue:work database --queue=messaging,inbox-mail,inbox-notify,default --sleep=1 --tries=3 --timeout=120 --max-time=3600
directory=/home/USER/www/YOUR_SITE
autostart=true
autorestart=true
numprocs=1
user=USER
redirect_stderr=true
stdout_logfile=/home/USER/www/YOUR_SITE/storage/logs/queue-worker.log
stopwaitsecs=90
```

Save as `/etc/supervisor/conf.d/lnscrm-queue.conf`, replace `USER`/`YOUR_SITE`, then:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start lnscrm-queue:*
```

Either way, you still need the `schedule:run` cron below — neither the
watchdog nor Supervisor replaces it, only the `queue:work` cron.

## Required cron jobs (Site Tools → Cron Jobs)

Always run this one every minute:

```bash
cd /home/USER/www/YOUR_SITE && php artisan schedule:run >> /dev/null 2>&1
```

As a fallback/safety net even if you're running the watchdog or Supervisor
above, also run this every minute — note it now includes `messaging`:

```bash
cd /home/USER/www/YOUR_SITE && php artisan queue:work database --queue=messaging,inbox-mail,inbox-notify,default --stop-when-empty --max-time=55 --tries=3 --timeout=120 >> /dev/null 2>&1
```

Replace the path with your real app root (the folder that contains `artisan`).

Without any queue worker at all, scheduled sync/send jobs — and now outbound
SMS/WhatsApp sends and inbox replies — stay queued in the `jobs` table and
won't go out.

## `.env`

```env
QUEUE_CONNECTION=database
CACHE_STORE=file
```

`CACHE_STORE=file` (or redis/memcached if you have it) is needed for unique/overlapping
job locks — avoid `database` here since it puts cache reads/writes on the same
MySQL connection as the rest of the app.

## Optional inline fallback

If the queue worker is unavailable temporarily:

```bash
php artisan inbox:sync-mail --sync
php artisan inbox:process-scheduled-replies --sync
php artisan inbox:process-reopens --sync
```

## Local development

`composer dev` already listens on `messaging,inbox-mail,inbox-notify,default`.

## UI polling

When `/inbox` is open and the browser tab is focused, the conversation list and sidebar counts refresh about every 45 seconds so queued sync appears without a manual refresh.
