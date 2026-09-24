# Inbox queues on SiteGround (no Redis / Horizon)

Inbox background work uses the **database** queue driver and named queues. Horizon is not required.

## Queues

| Queue | Work |
|-------|------|
| `inbox-mail` | Scheduled sends, snooze reopens |
| `inbox-notify` | Thread / assignee notifications |
| `default` | Outlook mail sync jobs, inbound lead rules |

## Required cron jobs (Site Tools → Cron Jobs)

Run **both** every minute:

```bash
cd /home/USER/www/YOUR_SITE && php artisan schedule:run >> /dev/null 2>&1
```

```bash
cd /home/USER/www/YOUR_SITE && php artisan queue:work database --queue=inbox-mail,inbox-notify,default --stop-when-empty --max-time=55 --tries=3 --timeout=300 >> /dev/null 2>&1
```

Replace the path with your real app root (the folder that contains `artisan`).

Without the `queue:work` cron, scheduled sync/send jobs stay in the `jobs` table and mail will not update in the background.

## `.env`

```env
QUEUE_CONNECTION=database
CACHE_STORE=database
```

`CACHE_STORE=database` (or redis/memcached if you have it) is needed for unique/overlapping job locks.

## Optional inline fallback

If the queue worker is unavailable temporarily:

```bash
php artisan inbox:sync-mail --sync
php artisan inbox:process-scheduled-replies --sync
php artisan inbox:process-reopens --sync
```

## Local development

`composer dev` already listens on `inbox-mail,inbox-notify,default`.

## UI polling

When `/inbox` is open and the browser tab is focused, the conversation list and sidebar counts refresh about every 45 seconds so queued sync appears without a manual refresh.
