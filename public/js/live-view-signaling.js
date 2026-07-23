/**
 * Shared WebRTC signaling helpers for live screen viewing.
 * Signaling is delivered via Laravel API polling (upgradeable to Reverb/Pusher later).
 */
window.LiveViewSignaling = (function () {
    const DEFAULT_ICE_SERVERS = [{ urls: 'stun:stun.l.google.com:19302' }];
    let apiBase = '/api/live-view';
    let iceConfigPromise = null;
    let iceGatheringTimeoutMs = 500;
    let signalPollActiveIntervalMs = 1500;
    let signalPollIdleIntervalMs = 10000;
    let heartbeatIntervalMs = 30000;
    let pollConnectIntervalMs = 200;
    let turnConfigured = false;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function base64EncodeUtf8(value) {
        const bytes = new TextEncoder().encode(value);
        let binary = '';
        bytes.forEach((byte) => {
            binary += String.fromCharCode(byte);
        });
        return btoa(binary);
    }

    function base64DecodeUtf8(value) {
        const binary = atob(value);
        const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
        return new TextDecoder().decode(bytes);
    }

    function normalizeSdp(value) {
        let sdp = value
            .replace(/\\r\\n/g, '\n')
            .replace(/\\n/g, '\n')
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n');

        // Some transports collapse SDP into a single line; repair before parsing.
        if (!sdp.includes('\n') && /(?:^| )[astmvoc]=/.test(sdp)) {
            sdp = sdp
                .replace(/(^|\s)(?=v=)/g, '$1\n')
                .replace(/(^|\s)(?=o=)/g, '$1\n')
                .replace(/(^|\s)(?=s=)/g, '$1\n')
                .replace(/(^|\s)(?=t=)/g, '$1\n')
                .replace(/(^|\s)(?=m=)/g, '$1\n')
                .replace(/(^|\s)(?=c=)/g, '$1\n')
                .replace(/(^|\s)(?=a=)/g, '$1\n');
        }

        sdp = sdp
            .split('\n')
            .map((line) => line.trimEnd())
            .filter((line, index, lines) => line.length > 0 || index < lines.length - 1)
            .join('\r\n');

        if (sdp && !sdp.endsWith('\r\n')) {
            sdp += '\r\n';
        }

        return sdp;
    }

    function serializeSessionDescription(description) {
        if (!description) {
            return null;
        }

        return {
            type: description.type,
            sdp: base64EncodeUtf8(description.sdp),
            encoding: 'base64',
        };
    }

    function deserializeSessionDescription(value) {
        if (!value || typeof value.type !== 'string' || typeof value.sdp !== 'string') {
            throw new Error('Invalid session description payload');
        }

        let sdp = value.sdp;
        if (value.encoding === 'base64') {
            sdp = base64DecodeUtf8(sdp);
        } else if (!value.encoding && !sdp.trimStart().startsWith('v=')) {
            try {
                sdp = base64DecodeUtf8(sdp);
            } catch (error) {
                // Keep legacy plain SDP payloads as-is.
            }
        }

        sdp = normalizeSdp(sdp);

        return new RTCSessionDescription({
            type: value.type,
            sdp,
        });
    }

    function serializeIceCandidate(candidate) {
        if (!candidate) {
            return null;
        }

        return typeof candidate.toJSON === 'function' ? candidate.toJSON() : candidate;
    }

    function deserializeIceCandidate(value) {
        if (!value) {
            return null;
        }

        const init = value.candidate && typeof value.candidate === 'object'
            ? value.candidate
            : value;

        if (!init.candidate) {
            return null;
        }

        return new RTCIceCandidate(init);
    }

    async function api(path, options = {}) {
        const response = await fetch(path, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                ...(options.headers || {}),
            },
            ...options,
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = data.message || data.error || `Request failed (${response.status})`;
            throw new Error(message);
        }

        return data;
    }

    async function pullSignals(sessionId = null, signalId = null) {
        const params = new URLSearchParams();
        if (sessionId) {
            params.set('session_id', String(sessionId));
        }
        if (signalId) {
            params.set('signal_id', String(signalId));
        }

        const query = params.toString();
        const data = await api(`${apiBase}/signals${query ? `?${query}` : ''}`);
        return data.signals || [];
    }

    async function pullSignalById(signalId, sessionId = null) {
        const signals = await pullSignals(sessionId, signalId);
        return signals[0] || null;
    }

    async function resolveRealtimeSignal(signal, sessionId = null) {
        if (!signal) {
            return null;
        }

        if (!signal.payload_deferred && signal.payload !== undefined) {
            return signal;
        }

        if (!signal.id) {
            return signal;
        }

        let full = await pullSignalById(signal.id, sessionId || signal.session_id || null);
        if (!full) {
            await new Promise((resolve) => setTimeout(resolve, 150));
            full = await pullSignalById(signal.id, sessionId || signal.session_id || null);
        }

        return full || signal;
    }

    async function sendSignal(toUserId, signalType, payload, sessionId = null) {
        return api(`${apiBase}/signals`, {
            method: 'POST',
            body: JSON.stringify({
                to_user_id: toUserId,
                session_id: sessionId,
                signal_type: signalType,
                payload,
            }),
        });
    }

    async function loadIceConfig(forceReload = false) {
        if (!iceConfigPromise || forceReload) {
            iceConfigPromise = api(`${apiBase}/ice-config`)
                .then((data) => {
                    iceGatheringTimeoutMs = data.ice_gathering_timeout_ms ?? 500;
                    signalPollActiveIntervalMs = data.signal_poll_active_interval_ms ?? 1500;
                    signalPollIdleIntervalMs = data.signal_poll_idle_interval_ms ?? 10000;
                    heartbeatIntervalMs = data.heartbeat_interval_ms ?? 30000;
                    pollConnectIntervalMs = data.signal_poll_connect_interval_ms ?? 200;
                    turnConfigured = !!data.turn_configured;
                    return {
                        iceServers: data.ice_servers?.length ? data.ice_servers : DEFAULT_ICE_SERVERS,
                        turnConfigured,
                    };
                })
                .catch((error) => {
                    console.warn('Failed to load live view ICE config', error);
                    return {
                        iceServers: DEFAULT_ICE_SERVERS,
                        turnConfigured: false,
                    };
                });
        }

        return iceConfigPromise;
    }

    async function createPeerConnection() {
        const config = await loadIceConfig();

        return new RTCPeerConnection({
            iceServers: config.iceServers,
            bundlePolicy: 'max-bundle',
            rtcpMuxPolicy: 'require',
            iceTransportPolicy: 'all',
        });
    }

    function isTurnConfigured() {
        return loadIceConfig().then((config) => config.turnConfigured);
    }

    function waitForIceGathering(pc, timeoutMs = null) {
        const waitMs = timeoutMs ?? (turnConfigured ? Math.max(iceGatheringTimeoutMs, 1200) : iceGatheringTimeoutMs);
        if (pc.iceGatheringState === 'complete') {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            let resolved = false;
            const startedAt = Date.now();

            const finish = () => {
                if (resolved) {
                    return;
                }
                resolved = true;
                clearTimeout(timeout);
                pc.removeEventListener('icegatheringstatechange', onStateChange);
                pc.removeEventListener('icecandidate', onCandidate);
                resolve();
            };

            const timeout = setTimeout(finish, waitMs);

            function onStateChange() {
                if (pc.iceGatheringState === 'complete') {
                    finish();
                }
            }

            function onCandidate(event) {
                if (!event.candidate) {
                    return;
                }

                const type = event.candidate.type;
                const elapsed = Date.now() - startedAt;

                if (type === 'relay' || type === 'srflx') {
                    finish();
                    return;
                }

                if (type === 'host' && !turnConfigured && elapsed >= 80) {
                    finish();
                }
            }

            pc.addEventListener('icegatheringstatechange', onStateChange);
            pc.addEventListener('icecandidate', onCandidate);
        });
    }

    /**
     * Adaptive signal poller — fast interval while connecting, slower when idle.
     */
    function createSignalPoller(pollFn, options = {}) {
        let timer = null;
        let stopped = false;
        let fastMode = options.startFast !== false;

        const getInterval = () => (fastMode ? pollConnectIntervalMs : signalPollActiveIntervalMs);

        async function tick() {
            if (stopped) {
                return;
            }

            try {
                await pollFn();
            } catch (error) {
                if (typeof options.onError === 'function') {
                    options.onError(error);
                }
            }

            if (!stopped) {
                timer = setTimeout(tick, getInterval());
            }
        }

        function start() {
            stopped = false;
            if (timer) {
                clearTimeout(timer);
            }
            tick();
        }

        function stop() {
            stopped = true;
            if (timer) {
                clearTimeout(timer);
                timer = null;
            }
        }

        function setFastMode(enabled) {
            fastMode = !!enabled;
        }

        function pollNow() {
            if (stopped) {
                return Promise.resolve();
            }

            if (timer) {
                clearTimeout(timer);
                timer = null;
            }

            return tick();
        }

        return { start, stop, setFastMode, pollNow };
    }

    function wireIceCandidate(pc, toUserId, sessionId) {
        if (!pc) {
            return;
        }

        pc.onicecandidate = (event) => {
            if (!event.candidate) {
                return;
            }

            sendSignal(
                toUserId,
                'ice-candidate',
                { candidate: serializeIceCandidate(event.candidate) },
                sessionId
            ).catch((error) => {
                console.warn('Failed to send ICE candidate', error);
            });
        };
    }

    const pendingIceCandidates = new WeakMap();

    function getPendingIceQueue(pc) {
        if (!pendingIceCandidates.has(pc)) {
            pendingIceCandidates.set(pc, []);
        }

        return pendingIceCandidates.get(pc);
    }

    async function addIceCandidateSafe(pc, candidate) {
        try {
            await pc.addIceCandidate(candidate);
        } catch (error) {
            console.warn('Failed to add ICE candidate', error);
        }
    }

    async function applyRemoteIceCandidate(pc, payload) {
        const candidate = deserializeIceCandidate(payload);
        if (!candidate) {
            return;
        }

        if (!pc.remoteDescription?.type) {
            getPendingIceQueue(pc).push(candidate);
            return;
        }

        await addIceCandidateSafe(pc, candidate);
    }

    async function flushPendingIceCandidates(pc) {
        if (!pc.remoteDescription?.type) {
            return;
        }

        const queue = getPendingIceQueue(pc);
        while (queue.length > 0) {
            const candidate = queue.shift();
            await addIceCandidateSafe(pc, candidate);
        }
    }

    function attachRemoteTrack(videoElement, event, onAttached) {
        if (!videoElement || !event.track) {
            return;
        }

        let stream = event.streams[0];
        if (!stream) {
            stream = videoElement.srcObject instanceof MediaStream
                ? videoElement.srcObject
                : new MediaStream();

            if (!stream.getTracks().includes(event.track)) {
                stream.addTrack(event.track);
            }
        }

        if (videoElement.srcObject !== stream) {
            videoElement.srcObject = stream;
        }

        videoElement.muted = true;
        videoElement.playsInline = true;

        const tryPlay = () => {
            const playPromise = videoElement.play();
            if (playPromise?.catch) {
                playPromise.catch((error) => {
                    console.warn('Live view video play blocked', error);
                });
            }
        };

        event.track.addEventListener('unmute', tryPlay);
        videoElement.addEventListener('loadedmetadata', tryPlay, { once: true });
        tryPlay();

        let attached = false;
        const notifyAttached = () => {
            if (attached) {
                return;
            }
            attached = true;
            if (typeof onAttached === 'function') {
                onAttached(stream, event.track);
            }
        };

        videoElement.addEventListener('playing', notifyAttached, { once: true });
        if (!videoElement.paused && videoElement.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA) {
            notifyAttached();
        }
    }

    let userSignalChannel = null;
    let userSignalHandler = null;
    let sessionChatChannel = null;
    let fallbackPollTimer = null;
    let connectionStateHandler = null;
    let boundConnection = null;

    function stopPollingFallback() {
        if (fallbackPollTimer) {
            clearTimeout(fallbackPollTimer);
            fallbackPollTimer = null;
        }
    }

    function startPollingFallback(handler, sessionId = null) {
        if (fallbackPollTimer) {
            return;
        }

        const poll = async () => {
            if (fallbackPollTimer === null) {
                return;
            }

            try {
                const signals = await pullSignals(sessionId);
                for (const signal of signals) {
                    await handler(signal);
                }
            } catch (error) {
                console.warn('Fallback live view signal poll failed', error);
            }

            if (fallbackPollTimer !== null) {
                fallbackPollTimer = setTimeout(poll, signalPollIdleIntervalMs);
            }
        };

        fallbackPollTimer = setTimeout(poll, 0);
    }

    function unbindConnectionState() {
        if (boundConnection && connectionStateHandler) {
            boundConnection.unbind('state_change', connectionStateHandler);
        }
        boundConnection = null;
        connectionStateHandler = null;
    }

    /**
     * An Echo instance existing only means init() ran — it says nothing about
     * whether the underlying Reverb socket is actually connected. Watch real
     * connection state so polling only runs while the push channel genuinely
     * isn't delivering, and stops the instant it is (or never starts at all).
     */
    function watchConnectionState(echo, handler, sessionId) {
        const connection = echo?.connector?.pusher?.connection;
        if (!connection) {
            startPollingFallback(handler, sessionId);
            return;
        }

        connectionStateHandler = ({ current }) => {
            if (current === 'connected') {
                stopPollingFallback();
            } else {
                startPollingFallback(handler, sessionId);
            }
        };
        connection.bind('state_change', connectionStateHandler);
        boundConnection = connection;

        if (connection.state !== 'connected') {
            startPollingFallback(handler, sessionId);
        }
    }

    async function subscribeUserSignals(handler, sessionId = null, options = {}) {
        userSignalHandler = handler;
        stopPollingFallback();
        unbindConnectionState();

        const echo = window.LogonRealtime?.getEcho?.();
        if (!echo || !window.__liveViewUserId) {
            if (!options.skipCatchUp) {
                const catchUp = await pullSignals(sessionId).catch(() => []);
                for (const signal of catchUp) {
                    await handler(signal);
                }
            }

            startPollingFallback(handler, sessionId);
            return null;
        }

        if (userSignalChannel) {
            echo.leave(`user.${window.__liveViewUserId}.live-view`);
            userSignalChannel = null;
        }

        userSignalChannel = echo.private(`user.${window.__liveViewUserId}.live-view`);
        userSignalChannel.listen('.signal.received', async (event) => {
            if (!userSignalHandler || !event?.signal) {
                return;
            }

            const signal = await resolveRealtimeSignal(event.signal, sessionId);
            if (signal) {
                await userSignalHandler(signal);
            }
        });

        watchConnectionState(echo, handler, sessionId);

        if (!options.skipCatchUp) {
            const catchUp = await pullSignals(sessionId).catch(() => []);
            for (const signal of catchUp) {
                await handler(signal);
            }
        }

        return userSignalChannel;
    }

    function unsubscribeUserSignals() {
        userSignalHandler = null;
        stopPollingFallback();
        unbindConnectionState();
        const echo = window.LogonRealtime?.getEcho?.();

        if (echo && window.__liveViewUserId) {
            echo.leave(`user.${window.__liveViewUserId}.live-view`);
        }

        userSignalChannel = null;
    }

    function subscribeSessionChat(sessionId, handler) {
        const echo = window.LogonRealtime?.getEcho?.();
        if (!echo || !sessionId) {
            return null;
        }

        if (sessionChatChannel) {
            echo.leave(sessionChatChannel.name);
            sessionChatChannel = null;
        }

        sessionChatChannel = echo.private(`live-view-session.${sessionId}`);
        sessionChatChannel.listen('.chat.message', (event) => {
            if (handler && event?.message) {
                handler(event.message);
            }
        });

        return sessionChatChannel;
    }

    function unsubscribeSessionChat(sessionId) {
        const echo = window.LogonRealtime?.getEcho?.();
        if (echo && sessionId) {
            echo.leave(`live-view-session.${sessionId}`);
        }
        sessionChatChannel = null;
    }

    return {
        DEFAULT_ICE_SERVERS,
        configureApiBase: (base) => { apiBase = base; },
        getApiBase: () => apiBase,
        api,
        pullSignals,
        pullSignalById,
        resolveRealtimeSignal,
        sendSignal,
        loadIceConfig,
        isTurnConfigured,
        createPeerConnection,
        waitForIceGathering,
        subscribeUserSignals,
        unsubscribeUserSignals,
        subscribeSessionChat,
        unsubscribeSessionChat,
        createSignalPoller,
        wireIceCandidate,
        applyRemoteIceCandidate,
        flushPendingIceCandidates,
        attachRemoteTrack,
        serializeSessionDescription,
        deserializeSessionDescription,
        serializeIceCandidate,
        deserializeIceCandidate,
        getSignalPollActiveIntervalMs: () => signalPollActiveIntervalMs,
        getSignalPollIdleIntervalMs: () => signalPollIdleIntervalMs,
        getHeartbeatIntervalMs: () => heartbeatIntervalMs,
    };
})();
