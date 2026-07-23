/**
 * Worker-side live screen viewing (reuses existing getDisplayMedia stream).
 */
window.LiveViewWorker = (function () {
    const signaling = window.LiveViewSignaling;
    const audio = window.LiveViewAudio;
    const chat = window.LiveViewChat;
    const livePeerConnections = {};
    const watcherNames = {};
    let heartbeatTimer = null;
    let isRunning = false;
    let activeWatchers = new Set();
    let getStream = () => null;
    let isClockedIn = () => false;
    let isRecordingSessionActive = () => false;
    let onWatchStarted = () => {};
    let onWatchEnded = () => {};

    let onAudioRequest = () => {};
    let onAudioStateChange = () => {};
    let onChatMessage = () => {};
    let onReplaceChatMessage = () => {};
    let onRemoveChatMessage = () => {};
    let onChatSendingChange = () => {};
    let activeSessionIds = {};
    const processedSignalIds = new Set();

    function configure(options = {}) {
        getStream = options.getStream || getStream;
        isClockedIn = options.isClockedIn || isClockedIn;
        isRecordingSessionActive = options.isRecordingSessionActive || isRecordingSessionActive;
        onWatchStarted = options.onWatchStarted || onWatchStarted;
        onWatchEnded = options.onWatchEnded || onWatchEnded;
        onAudioRequest = options.onAudioRequest || onAudioRequest;
        onAudioStateChange = options.onAudioStateChange || onAudioStateChange;
        onChatMessage = options.onChatMessage || onChatMessage;
        onReplaceChatMessage = options.onReplaceChatMessage || onReplaceChatMessage;
        onRemoveChatMessage = options.onRemoveChatMessage || onRemoveChatMessage;
        onChatSendingChange = options.onChatSendingChange || onChatSendingChange;

        audio.configure({
            onAudioRequest: (details) => onAudioRequest(details),
            onAudioEnded: () => onAudioStateChange('ended'),
            onAudioError: (message) => onAudioStateChange('error', message),
        });

        chat.configure({
            onMessage: (message) => onChatMessage(message),
            onReplaceMessage: (tempId, message) => onReplaceChatMessage(tempId, message),
            onRemoveMessage: (tempId) => onRemoveChatMessage(tempId),
            onSendingChange: (sending) => onChatSendingChange(sending),
        });
    }

    function hasLiveStream() {
        const stream = getStream();
        if (!stream) {
            return false;
        }

        return stream.getVideoTracks().some((track) => track.readyState === 'live');
    }

    function hasActiveSession() {
        return activeWatchers.size > 0 || Object.keys(livePeerConnections).length > 0;
    }

    function notifyWatchersChanged() {
        if (activeWatchers.size > 0) {
            onWatchStarted(Array.from(activeWatchers));
        } else {
            onWatchEnded();
        }
    }

    function viewerKey(viewerType, viewerId) {
        return `${viewerType || 'user'}:${viewerId}`;
    }

    async function sendSignalToAdmin(adminId, message, sessionId) {
        const type = message.type;
        const payload = { ...message };
        delete payload.type;
        await signaling.sendSignal(adminId, type, payload, sessionId);
    }

    async function createLiveConnection(adminId, sessionId, adminName, adminType = 'user') {
        const currentStream = getStream();
        if (!currentStream || !hasLiveStream()) {
            console.warn('No active screen stream available for live viewing');
            await clearHeartbeat();
            return;
        }

        const key = viewerKey(adminType, adminId);

        if (livePeerConnections[key]) {
            livePeerConnections[key].close();
            delete livePeerConnections[key];
        }

        const pc = await signaling.createPeerConnection();
        livePeerConnections[key] = pc;
        activeSessionIds[key] = sessionId;

        pc.ontrack = (event) => {
            if (event.track?.kind === 'audio') {
                audio.handleIncomingTrack(event);
                onAudioStateChange('active');
            }
        };

        const videoTracks = currentStream.getVideoTracks().filter((track) => track.readyState === 'live');
        if (videoTracks.length === 0) {
            console.warn('No live video tracks available for live viewing');
            await clearHeartbeat();
            return;
        }

        videoTracks.forEach((track) => {
            pc.addTrack(track, currentStream);
        });

        signaling.wireIceCandidate(pc, adminId, sessionId);

        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        await signaling.waitForIceGathering(pc);

        await sendSignalToAdmin(adminId, {
            type: 'offer',
            offer: signaling.serializeSessionDescription(pc.localDescription),
        }, sessionId);

        await signaling.sendSignal(adminId, 'live-view-ready', { session_id: sessionId }, sessionId);

        const watcherLabel = adminType === 'client'
            ? `Client - ${adminName || 'Client'}`
            : (adminName || 'Administrator');

        activeWatchers.add(watcherLabel);
        watcherNames[key] = watcherLabel;
        notifyWatchersChanged();

        chat.setSession(sessionId);
        chat.startRealtime();
    }

    async function handleSignal(signal) {
        if (signal?.id && processedSignalIds.has(signal.id)) {
            return;
        }

        const adminId = signal.from_user_id;
        const adminType = signal.from_type || 'user';
        const key = viewerKey(adminType, adminId);
        const sessionId = signal.session_id;
        const payload = signal.payload || {};

        if (signal.signal_type === 'live-view-request') {
            await createLiveConnection(adminId, sessionId || payload.session_id, payload.admin_name, adminType);

            if (signal?.id) {
                processedSignalIds.add(signal.id);
            }

            return;
        }

        chat.handleSignal(signal);

        if (signal.signal_type === 'live-view-end') {
            closeConnection(key);

            if (signal?.id) {
                processedSignalIds.add(signal.id);
            }

            return;
        }

        const pc = livePeerConnections[key];

        audio.handleSignal(signal, pc, {
            onRenegotiationPending: (details) => onAudioRequest(details),
        });

        if (signal.signal_type === 'live-view-audio-decline' || signal.signal_type === 'live-view-audio-end') {
            return;
        }

        if (signal.signal_type === 'offer' && payload.renegotiation) {
            return;
        }

        if (signal.signal_type === 'answer' && payload.renegotiation) {
            return;
        }

        if (!pc) {
            return;
        }

        if (signal.signal_type === 'answer' && payload.answer && !payload.renegotiation) {
            if (pc.signalingState !== 'have-local-offer') {
                return;
            }

            try {
                await pc.setRemoteDescription(
                    signaling.deserializeSessionDescription(payload.answer)
                );
                await signaling.flushPendingIceCandidates(pc);
            } catch (error) {
                console.error('Worker failed to apply live view answer', error);
                throw error;
            }

            if (signal?.id) {
                processedSignalIds.add(signal.id);
            }

            return;
        }

        if (signal.signal_type === 'ice-candidate') {
            await signaling.applyRemoteIceCandidate(pc, payload);

            if (signal?.id) {
                processedSignalIds.add(signal.id);
            }
        }
    }

    function closeConnection(key) {
        const pc = livePeerConnections[key];
        if (pc) {
            pc.close();
            delete livePeerConnections[key];
        }

        delete activeSessionIds[key];

        const name = watcherNames[key];
        if (name) {
            activeWatchers.delete(name);
            delete watcherNames[key];
        }

        if (Object.keys(livePeerConnections).length === 0) {
            chat.clearSession();
            audio.stopLocalAudio();
        }

        notifyWatchersChanged();
    }

    function closeAllConnections() {
        Object.keys(livePeerConnections).forEach((key) => {
            const pc = livePeerConnections[key];
            if (pc) {
                pc.close();
            }
            delete livePeerConnections[key];
        });
        Object.keys(watcherNames).forEach((key) => delete watcherNames[key]);
        Object.keys(activeSessionIds).forEach((key) => delete activeSessionIds[key]);
        activeWatchers.clear();
        chat.clearSession();
        audio.stopLocalAudio();
        notifyWatchersChanged();
    }

    async function enableAudioForAdmin(adminId) {
        const key = viewerKey('user', adminId);
        const pc = livePeerConnections[key];
        const sessionId = activeSessionIds[key];
        if (!pc || !sessionId) {
            throw new Error('No active live view connection.');
        }

        await audio.enableAsWorker(pc, adminId, sessionId);
        onAudioStateChange('active');
    }

    async function declineAudioForAdmin(adminId) {
        const sessionId = activeSessionIds[viewerKey('user', adminId)];
        if (sessionId) {
            await audio.declineAsWorker(adminId, sessionId);
        }
        onAudioStateChange('declined');
    }

    async function sendChatMessage(body) {
        return chat.sendMessage(body);
    }

    async function loadChatMessages() {
        return chat.loadMessages();
    }

    async function syncChatMessages() {
        return chat.syncMessages();
    }

    function isChatSending() {
        return chat.isSending();
    }

    async function start() {
        if (isRunning) {
            return;
        }

        isRunning = true;

        await signaling.loadIceConfig();
        await sendHeartbeat();
        await signaling.subscribeUserSignals(async (signal) => {
            if (!isRunning) {
                return;
            }

            await handleSignal(signal);
        }, null, { skipCatchUp: true });

        heartbeatTimer = setInterval(sendHeartbeat, signaling.getHeartbeatIntervalMs());
    }

    async function stop() {
        isRunning = false;
        processedSignalIds.clear();
        signaling.unsubscribeUserSignals();

        if (heartbeatTimer) {
            clearInterval(heartbeatTimer);
            heartbeatTimer = null;
        }

        closeAllConnections();
        await clearHeartbeat();
    }

    async function sendHeartbeat() {
        if (!isRunning || !isClockedIn() || !isRecordingSessionActive()) {
            return;
        }

        const streamActive = hasLiveStream();

        try {
            await signaling.api('/api/live-view/heartbeat', {
                method: 'POST',
                body: JSON.stringify({ stream_active: streamActive }),
            });

            if (!streamActive) {
                onWatchEnded();
            }
        } catch (error) {
            console.warn('Live view heartbeat failed', error);
        }
    }

    async function clearHeartbeat() {
        try {
            await signaling.api('/api/live-view/heartbeat/clear', { method: 'POST', body: '{}' });
        } catch (error) {
            console.warn('Failed to clear live view heartbeat', error);
        }
    }

    return {
        configure,
        start,
        stop,
        hasLiveStream,
        clearHeartbeat,
        enableAudioForAdmin,
        declineAudioForAdmin,
        sendChatMessage,
        loadChatMessages,
        syncChatMessages,
        isChatSending,
    };
})();
