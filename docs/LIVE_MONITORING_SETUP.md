# Live Monitoring Setup Guide

This guide covers how to set up the **Employee Monitoring / Live Screen View** feature end to end: the real-time
transport (Laravel Reverb), WebRTC screen sharing between an employee's machine and an admin's browser, the
scheduled cleanup job, permissions, and the Windows desktop recorder client that employees install.

## 1. How it works

- Employees run the **ItsWorkPlace Recorder** desktop client (`clients/windows-recorder`) on Windows, which
  captures periodic screen recordings and uploads them to the server.
- While an employee is clocked in and has an active recording session, an admin with the right permission can
  open **Employee Monitoring** in the dashboard and start a **live view** — a WebRTC peer-to-peer screen share
  straight from the employee's browser tab/session to the admin's browser, plus an in-session chat and optional
  two-way audio.
- Signaling (SDP offers/answers, ICE candidates, chat, status) is delivered over **Laravel Reverb**
  (a self-hosted, Pusher-protocol-compatible WebSocket server) when configured. If Reverb is not configured, the
  app automatically falls back to REST polling — the feature keeps working, just less instantly.
- A scheduled artisan command cleans up stale sessions and expired signaling rows every 5 minutes.

Key source locations, for reference:

| Piece | Path |
|---|---|
| Admin dashboard page | `resources/views/dashboard/employee-monitoring.blade.php` |
| Monitoring controller | `app/Http/Controllers/EmployeeMonitoringController.php` |
| WebRTC signaling controller | `app/Http/Controllers/LiveViewController.php` |
| Core business logic | `app/Services/LiveViewService.php` |
| ICE server config builder | `app/Services/LiveViewIceConfigService.php` |
| Feature config | `config/live-view.php` |
| Broadcasting config | `config/broadcasting.php` |
| Cleanup command | `app/Console/Commands/CleanupLiveViewCommand.php` (`live-view:cleanup`, scheduled every 5 min in `routes/console.php`) |
| Frontend realtime bootstrap | `public/js/realtime.js`, `resources/views/partials/realtime-scripts.blade.php` |
| Frontend live-view JS | `public/js/live-view-*.js` |
| DB migrations | `database/migrations/2026_06_30_140000_create_live_view_tables.php`, `..._create_live_view_chat_messages_table.php` |
| Permission seeder | `database/seeders/AddLiveViewPermissionsSeeder.php` |
| Windows recorder client | `clients/windows-recorder/` |

## 2. Prerequisites

- PHP/Laravel app already deployed and running (this feature ships as part of the main app — no separate install).
- A process manager (Supervisor, systemd, etc.) able to run a long-lived process for Reverb.
- The Laravel **scheduler** running (`php artisan schedule:run` via cron every minute, or `php artisan schedule:work`
  in development) so the `live-view:cleanup` job actually executes.
- A queue worker running as usual for the app (`php artisan queue:work` / `queue:listen`) since broadcast events
  and other jobs go through the queue.
- Recommended: Redis as the cache store (`CACHE_STORE=redis`) once available — the heartbeat/live-available check
  is cache-backed and benefits from Redis under load. `database` cache works fine for smaller deployments.

## 3. Configure Laravel Reverb (real-time transport)

Reverb is optional in the sense that the app falls back to polling without it, but it's what makes live view feel
instant (sub-second) instead of polling-interval-delayed. To enable it:

1. Generate app credentials (or set them manually):
   ```bash
   php artisan reverb:install
   ```

2. Set these in `.env`:
   ```
   BROADCAST_CONNECTION=reverb
   CACHE_STORE=database        # or redis when available

   REVERB_APP_ID=logon
   REVERB_APP_KEY=your-key
   REVERB_APP_SECRET=your-secret

   REVERB_HOST=localhost        # server metadata only, does not control the browser URL
   REVERB_PORT=443              # public-facing port the browser connects to — do NOT use 8080 here
   REVERB_SCHEME=https

   REVERB_SERVER_HOST=0.0.0.0   # what the Reverb process itself binds to
   REVERB_SERVER_PORT=8080      # internal port, proxied by Nginx — never expose this directly

   REVERB_MAX_REQUEST_SIZE=65536
   REVERB_APP_MAX_MESSAGE_SIZE=65536

   VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
   VITE_REVERB_HOST="${REVERB_HOST}"
   VITE_REVERB_PORT="${REVERB_PORT}"
   VITE_REVERB_SCHEME="${REVERB_SCHEME}"
   ```

   Note: the browser's WebSocket host is derived from `window.location.hostname` (see `public/js/realtime.js`),
   which keeps this working correctly across per-company subdomains. Leave `REVERB_CLIENT_HOST` /
   `REVERB_CLIENT_PORT` / `REVERB_CLIENT_SCHEME` unset unless you have a specific reason to override it.

   Also note: WebRTC SDP offers/answers are sent via REST, not over the WebSocket, specifically to avoid
   "payload too large" errors on the signaling channel — only small control messages go over Reverb.

3. Run Reverb as a persistent service. Example Supervisor program:
   ```ini
   [program:reverb]
   command=php /path/to/artisan reverb:start
   directory=/path/to/app
   autostart=true
   autorestart=true
   user=www-data
   ```

4. Proxy WebSocket traffic to Reverb in Nginx (repeat per vhost / wildcard subdomain):
   ```nginx
   location /app/ {
       proxy_pass http://127.0.0.1:8080;
       proxy_http_version 1.1;
       proxy_set_header Upgrade $http_upgrade;
       proxy_set_header Connection "Upgrade";
       proxy_set_header Host $host;
       proxy_read_timeout 60s;
   }
   ```

5. After every deploy that touches config or code:
   ```bash
   composer install
   php artisan config:clear
   php artisan reverb:restart
   ```
   Then hard-refresh browsers so clients pick up the new realtime script.

If `BROADCAST_CONNECTION` isn't `reverb` or `REVERB_APP_KEY` is blank, the app silently disables the realtime
bootstrap (see `resources/views/partials/realtime-scripts.blade.php`) and everything continues to work purely
over REST polling.

## 4. Configure WebRTC STUN/TURN (`config/live-view.php`)

These control how the employee's and admin's browsers establish the peer-to-peer screen-share connection.

```
LIVE_VIEW_STUN_URLS=stun:stun.l.google.com:19302   # default, works for same-network / simple NAT cases
LIVE_VIEW_TURN_URLS=
LIVE_VIEW_TURN_USERNAME=
LIVE_VIEW_TURN_CREDENTIAL=
```

- **STUN alone is enough** for testing or when admin and employee are on networks that allow direct/NAT-punched
  connections.
- **TURN is required** once admins and employees are routinely on different networks (e.g., remote employees,
  corporate firewalls) — STUN can't relay media through strict NATs/firewalls, only TURN can. Use a hosted
  provider (Metered, Twilio Network Traversal Service, Xirsys) or self-host `coturn`, then set all three of
  `LIVE_VIEW_TURN_URLS`, `LIVE_VIEW_TURN_USERNAME`, `LIVE_VIEW_TURN_CREDENTIAL` — all three must be present or
  TURN is skipped entirely (`app/Services/LiveViewIceConfigService.php`).
- `LIVE_VIEW_TURN_URLS` accepts a comma-separated list if you have multiple TURN endpoints.

Other tunables (defaults are sensible for most deployments — only change if you understand the tradeoff):

| Env var | Default | Purpose |
|---|---|---|
| `LIVE_VIEW_HEARTBEAT_TTL` | 45s | How long a worker's "I'm live" cache entry lasts before expiring |
| `LIVE_VIEW_STALE_SESSION_MINUTES` | 10 | Sessions stuck in pending/connecting longer than this are auto-failed by the cleanup job |
| `LIVE_VIEW_SIGNAL_RETENTION_DAYS` | 7 | How long old `webrtc_signals` rows are kept before cleanup purges them |
| `LIVE_VIEW_ICE_GATHERING_TIMEOUT_MS` | 500 | Max wait for trickle-ICE host candidates before proceeding |
| `LIVE_VIEW_SIGNAL_POLL_CONNECT_INTERVAL_MS` | 200 | Polling cadence while a session is actively connecting (REST fallback) |
| `LIVE_VIEW_SIGNAL_POLL_ACTIVE_MS` | 1500 | Polling cadence once a live session is active |
| `LIVE_VIEW_SIGNAL_POLL_IDLE_MS` | 10000 | Polling cadence when no admin is watching, reduces server load |
| `LIVE_VIEW_HEARTBEAT_INTERVAL_MS` | 30000 | How often a worker's browser sends a heartbeat while eligible for live view |
| `LIVE_VIEW_EMPLOYEE_MONITORING_POLL_MS` | 30000 | How often the admin dashboard's employee list refreshes status |

## 5. Database and scheduled cleanup

Migrations for this feature (run normally via `php artisan migrate`):

- `live_view_sessions` — one row per live-view session (status: pending/connecting/active/ended/failed).
- `webrtc_signals` — SDP/ICE/chat signal payloads exchanged between admin and worker.
- `live_view_chat_messages` — in-session chat messages.

Make sure the scheduler is actually running in production (cron entry calling `php artisan schedule:run` every
minute is the standard approach) so `live-view:cleanup` executes every 5 minutes. Without it, stale sessions
never get marked `failed` and old signal rows accumulate.

## 6. Permissions

Two permissions gate this feature, both company-scoped:

- `view_employee_monitoring` — gates the `/employee-monitoring` page and its `api/employee-monitoring/*` routes
  (the dashboard, recordings list, sync health panel).
- `view_live_screen` — gates starting/viewing an actual live WebRTC session (`api/live-view/sessions`).

Run the seeder to provision `view_live_screen` for existing companies and attach it to the relevant roles
(Administrator, Company Admin, Manager, Admin):

```bash
php artisan db:seed --class=AddLiveViewPermissionsSeeder
```

`view_employee_monitoring` is provisioned via the broader sidebar/company permission seeders
(`SidebarPermissionsSeeder`, `app/Support/CompanyPermissionFactory.php`) — new companies get it automatically
through the normal company-provisioning flow.

## 7. Broadcast channel authorization

Defined in `routes/channels.php` — no action needed beyond the standard `/broadcasting/auth` endpoint being
reachable (it is, by default, as part of the app):

- `company.{companyId}.monitoring` — requires `view_employee_monitoring`; carries live status updates for the
  employee grid.
- `user.{userId}.live-view` — private per-user channel for WebRTC signals.
- `live-view-session.{sessionId}` — per-session channel for chat, access-checked against session participants.

## 8. Windows Recorder client (employee side)

The employee-side screen recorder lives in `clients/windows-recorder/` and ships as a Windows installer.

### Building the installer

```bash
cd clients/windows-recorder
npm install
npm run installer:prepare   # builds dist/windows-recorder.exe (headless sync) and the Electron UI
```

Then compile `installer.iss` with Inno Setup to produce
`installer-output/itsworkplace-recorder-setup.exe`, which installs:

- **ItsWorkPlace Recorder** — the Electron UI (login, hourly capture, recordings list, desktop icon).
- **ItsWorkPlace Recorder (sync queue only)** — a headless CLI entry point for uploading any queued recordings,
  useful wired up to Windows Task Scheduler.

### Configuring where it points

The client talks to your server via the `RECORDER_API_URL` environment variable (defaults to
`http://localhost:8000`) — set this to your production URL when packaging/distributing, e.g.:

```
RECORDER_API_URL=https://yourcompany.logon.online
```

`RECORDER_HEADLESS=1` suppresses the Windows notification popups for unattended/scheduled runs.

### Rollout

1. Build/sign the installer as above (point `RECORDER_API_URL` at your production domain before packaging).
2. Distribute `itsworkplace-recorder-setup.exe` to employee machines (e.g., via your existing software deployment
   tooling / Group Policy / manual install).
3. Employees log in through the Electron UI using their normal account credentials; the client then handles
   screen capture and upload sync automatically while they're clocked in.

## 9. Verifying the setup

1. **Reverb is live**: `php artisan reverb:start` (or check the Supervisor-managed process), and confirm the
   Nginx `/app/` location proxies to it without errors.
2. **Realtime bootstrap fires in-browser**: log in as an admin, open devtools console, confirm no errors from
   `realtime.js` and that a WebSocket connection to `/app/...` is established (Network tab, "WS" filter).
3. **Permissions**: as an Administrator, confirm `/employee-monitoring` loads and shows employees; confirm a
   non-permitted role gets denied.
4. **End-to-end live view**: have a test employee clock in and start a recording session on the recorder client,
   then from the admin dashboard start a live view and confirm the screen share connects. If it hangs on
   "connecting", suspect missing TURN configuration (different networks) — see Section 4.
5. **Cleanup job**: confirm `php artisan schedule:list` shows `live-view:cleanup` and that it's actually
   executing (check `storage/logs` or run `php artisan live-view:cleanup` manually to verify no errors).

## 10. Troubleshooting

| Symptom | Likely cause |
|---|---|
| Live view never leaves "pending"/"connecting" | Employee not clocked in, or no active recording session, or heartbeat expired (`LIVE_VIEW_HEARTBEAT_TTL`) |
| Connects fine on same network but not remotely | No TURN server configured — STUN alone can't traverse strict NATs/firewalls |
| No realtime script loaded in page source | `BROADCAST_CONNECTION` isn't `reverb`, or `REVERB_APP_KEY` is empty — check `.env` and `php artisan config:clear` |
| WebSocket connection fails in browser devtools | Nginx `/app/` proxy misconfigured, or `REVERB_PORT` set to the internal `8080` instead of the public port |
| "Payload too large" during signaling | Should not happen — SDP offers/answers go over REST by design; if seen, check for a reverse proxy stripping/mangling the API request |
| Stale sessions pile up as "pending" indefinitely | Laravel scheduler (`schedule:run`/`schedule:work`) isn't actually running in this environment |
| Recorder client can't reach server | `RECORDER_API_URL` not set/incorrect at packaging time, or a firewall blocking outbound HTTPS from the employee machine |
