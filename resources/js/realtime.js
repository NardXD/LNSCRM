/**
 * Laravel Echo + Reverb bootstrap for dashboard realtime features.
 * Bundled via Vite (no CDN).
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.LogonRealtime = (function () {
    let echo = null;
    let initialized = false;

    /**
     * Resolve WebSocket endpoint from the current browser URL.
     * REVERB_SERVER_PORT (8080) is internal only — never use it in the browser.
     */
    function resolveConnection(config) {
        const pageSecure = window.location.protocol === 'https:';
        const useTls = config.scheme === 'https' || pageSecure;
        const host = config.useCustomHost && config.host
            ? config.host
            : window.location.hostname;

        let port = config.port != null ? Number(config.port) : null;
        if (!port || port === 8080) {
            if (useTls) {
                port = 443;
            } else if (window.location.port) {
                port = Number(window.location.port);
            } else {
                port = 80;
            }
        }

        return { host, port, useTls };
    }

    function init(config) {
        if (initialized || !config?.key) {
            return echo;
        }

        const { host, port, useTls } = resolveConnection(config || {});

        echo = new Echo({
            broadcaster: 'reverb',
            key: config.key,
            wsHost: host,
            wsPort: port,
            wssPort: port,
            forceTLS: useTls,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: config.authEndpoint,
            auth: {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    Accept: 'application/json',
                },
            },
        });

        initialized = true;
        window.dispatchEvent(new CustomEvent('crm:realtime-ready'));
        return echo;
    }

    function getEcho() {
        return echo;
    }

    function isReady() {
        return initialized && !!echo;
    }

    return {
        init,
        getEcho,
        isReady,
        resolveConnection,
    };
})();

if (window.__reverbConfig) {
    window.LogonRealtime.init(window.__reverbConfig);
}
