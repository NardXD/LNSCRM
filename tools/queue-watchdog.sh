#!/bin/bash
# Keeps a long-running `queue:work` worker alive without root/Supervisor access.
# Schedule this via cron every 2-5 minutes (Site Tools -> Cron Jobs):
#   */5 * * * * /home/USER/www/YOUR_SITE/tools/queue-watchdog.sh >> /home/USER/www/YOUR_SITE/storage/logs/queue-watchdog.log 2>&1
#
# It does NOT start a new worker on every run — it only starts one if none is
# already running, so this is safe to run frequently.

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$APP_DIR"

QUEUES="messaging,inbox-mail,inbox-notify,default"
PATTERN="artisan queue:work.*${QUEUES}"

if pgrep -f "$PATTERN" > /dev/null 2>&1; then
    exit 0
fi

echo "$(date -Iseconds) starting queue worker"
nohup php artisan queue:work database \
    --queue="$QUEUES" \
    --sleep=1 \
    --tries=3 \
    --timeout=120 \
    --max-time=3600 \
    >> "$APP_DIR/storage/logs/queue-worker.log" 2>&1 &
disown
