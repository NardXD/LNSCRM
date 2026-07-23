/**
 * Laravel Echo + Reverb bootstrap for dashboard realtime features.
 */
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
        if (initialized || !config?.key || typeof Echo === 'undefined' || typeof Pusher === 'undefined') {
            return echo;
        }

        window.Pusher = Pusher;

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
