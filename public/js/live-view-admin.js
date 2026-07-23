/**
 * Admin-side live screen viewer.
 */
window.LiveViewAdmin = (function () {
    const signaling = window.LiveViewSignaling;
    const audio = window.LiveViewAudio;
    const chat = window.LiveViewChat;
    let adminPeerConnection = null;
    let currentSession = null;
    let currentWorkerId = null;
    let offerApplied = false;
    let trackReceived = false;
    let trackTimeout = null;
    let watchGeneration = 0;
    let onStatusChange = () => {};
    let onError = () => {};
    let onStreamStarted = () => {};

    let onAudioStateChange = () => {};
    let onChatMessage = () => {};
    let onReplaceChatMessage = () => {};
    let onRemoveChatMessage = () => {};
    let onChatSendingChange = () => {};
    let chatEnabled = true;
    const processedSignalIds = new Set();

    function configure(options = {}) {
        onStatusChange = options.onStatusChange || onStatusChange;
        onError = options.onError || onError;
        onStreamStarted = options.onStreamStarted || onStreamStarted;
        onAudioStateChange = options.onAudioStateChange || onAudioStateChange;
        onChatMessage = options.onChatMessage || onChatMessage;
        onReplaceChatMessage = options.onReplaceChatMessage || onReplaceChatMessage;
        onRemoveChatMessage = options.onRemoveChatMessage || onRemoveChatMessage;
        onChatSendingChange = options.onChatSendingChange || onChatSendingChange;
        chatEnabled = options.enableChat !== false;

        audio.configure({
            onAudioEnded: () => onAudioStateChange('ended'),
            onAudioError: (message) => {
                onAudioStateChange('error', message);
                onError(message);
            },
        });

        chat.configure({
            onMessage: (message) => onChatMessage(message),
            onReplaceMessage: (tempId, message) => onReplaceChatMessage(tempId, message),
            onRemoveMessage: (tempId) => onRemoveChatMessage(tempId),
            onSendingChange: (sending) => onChatSendingChange(sending),
        });
    }

    function resetTrackState() {
        offerApplied = false;
        trackReceived = false;
        processedSignalIds.clear();
        if (trackTimeout) {
            clearTimeout(trackTimeout);
            trackTimeout = null;
        }
    }

    function isWatchCurrent(generation) {
        return generation === watchGeneration;
    }

    function markStreamStarted() {
        if (trackReceived) {
            return;
        }

        trackReceived = true;
        if (trackTimeout) {
            clearTimeout(trackTimeout);
            trackTimeout = null;
        }
        setStatus('connected', 'Streaming live');
        onStreamStarted();
    }

    function setStatus(status, detail = '') {
        onStatusChange(status, detail);
    }

    async function requestLiveViewFromWorker(workerId) {
        const data = await signaling.api(`${signaling.getApiBase()}/sessions`, {
            method: 'POST',
            body: JSON.stringify({ worker_id: workerId }),
        });

        return {
            session: data.session,
            turnConfigured: data.turn_configured !== false,
        };
    }

    async function endSession(session, reason = 'admin_closed', failureReason = null) {
        if (!session?.id) {
            return;
        }

        try {
            await signaling.api(`${signaling.getApiBase()}/sessions/${session.id}/end`, {
                method: 'POST',
                body: JSON.stringify({
                    reason,
                    failure_reason: failureReason,
                }),
            });
        } catch (error) {
            console.warn('Failed to end live view session', error);
        }
    }

    function detachPeerConnection(pc) {
        if (!pc) {
            return;
        }

        if (adminPeerConnection === pc) {
            adminPeerConnection = null;
        }

        try {
            pc.onicecandidate = null;
            pc.ontrack = null;
            pc.onconnectionstatechange = null;
            pc.oniceconnectionstatechange = null;
            pc.close();
        } catch (error) {
            console.warn('Failed to close peer connection', error);
        }
    }

    async function stopWatching(reason = 'admin_closed', failureReason = null) {
        watchGeneration += 1;

        const pc = adminPeerConnection;
        const session = currentSession;
        const workerId = currentWorkerId;

        signaling.unsubscribeUserSignals();

        if (adminPeerConnection === pc) {
            adminPeerConnection = null;
        }
        if (currentSession === session) {
            currentSession = null;
        }
        if (currentWorkerId === workerId) {
            currentWorkerId = null;
        }

        resetTrackState();
        detachPeerConnection(pc);
        audio.stopLocalAudio();
        chat.clearSession();

        await endSession(session, reason, failureReason);
    }

    async function handleSignal(signal, generation, pc) {
        if (!isWatchCurrent(generation) || pc !== adminPeerConnection) {
            return;
        }

        if (currentSession?.id && signal.session_id && Number(signal.session_id) !== Number(currentSession.id)) {
            return;
        }

        if (signal?.id && processedSignalIds.has(signal.id)) {
            return;
        }

        const workerId = signal.from_user_id;
        const payload = signal.payload || {};

        if (signal.signal_type === 'live-view-end') {
            if (signal?.id) {
                processedSignalIds.add(signal.id);
            }

            setStatus('ended', 'Live view ended.');
            await stopWatching('worker_ended');
            return;
        }

        audio.handleSignal(signal, pc);

        if (signal.signal_type === 'live-view-audio-decline') {
            if (signal?.id) {
                processedSignalIds.add(signal.id);
            }

            onAudioStateChange('declined');
            return;
        }

        chat.handleSignal(signal);

        if (signal.signal_type === 'offer' && payload.offer && payload.renegotiation) {
            return;
        }

        if (signal.signal_type === 'answer' && payload.answer && payload.renegotiation) {
            return;
        }

        if (signal.signal_type === 'offer' && payload.offer) {
            if (offerApplied || pc.signalingState !== 'stable') {
                return;
            }

            if (!currentSession?.id) {
                return;
            }

            try {
                await pc.setRemoteDescription(
                    signaling.deserializeSessionDescription(payload.offer)
                );
            } catch (error) {
                console.error('Failed to apply live view offer', error, payload.offer);
                throw error;
            }

            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            await signaling.waitForIceGathering(pc);

            if (!isWatchCurrent(generation) || pc !== adminPeerConnection) {
                return;
            }

            await signaling.sendSignal(
                workerId,
                'answer',
                { answer: signaling.serializeSessionDescription(pc.localDescription) },
                currentSession.id
            );

            await signaling.flushPendingIceCandidates(pc);

            if (signal?.id) {
                processedSignalIds.add(signal.id);
            }

            offerApplied = true;
            setStatus('connecting', 'Establishing video stream…');
            return;
        }

        if (signal.signal_type === 'ice-candidate') {
            await signaling.applyRemoteIceCandidate(pc, payload);

            if (signal?.id) {
                processedSignalIds.add(signal.id);
            }
        }
    }

    async function subscribeToSignals(generation, pc, sessionId) {
        signaling.unsubscribeUserSignals();

        const onSignal = async (signal) => {
            try {
                await handleSignal(signal, generation, pc);
            } catch (error) {
                console.warn('Admin live view signal failed', error);
                if (error?.name === 'OperationError' && !offerApplied) {
                    const message = 'Live view connection failed. Close and click Watch Live again.';
                    setStatus('failed', message);
                    onError(message);
                }
            }
        };

        await signaling.subscribeUserSignals(onSignal, sessionId, { skipCatchUp: true });

        return onSignal;
    }

    async function startWatchingWorkerOnce(workerId, videoElement) {
        await stopWatching('replaced');

        const generation = watchGeneration;
        currentWorkerId = workerId;
        setStatus('connecting', 'Connecting to employee screen…');

        const pc = await signaling.createPeerConnection();
        adminPeerConnection = pc;

        pc.ontrack = (event) => {
            if (!isWatchCurrent(generation) || pc !== adminPeerConnection) {
                return;
            }

            if (event.track?.kind === 'audio') {
                audio.handleIncomingTrack(event);
                onAudioStateChange('active');
                return;
            }

            signaling.attachRemoteTrack(videoElement, event, () => {
                markStreamStarted();
            });
        };

        let session;
        let turnConfigured = true;

        try {
            const sessionResult = await requestLiveViewFromWorker(workerId);
            session = sessionResult.session;
            turnConfigured = sessionResult.turnConfigured !== false;
            currentSession = session;
        } catch (error) {
            detachPeerConnection(pc);
            resetTrackState();
            throw error;
        }

        signaling.wireIceCandidate(pc, workerId, session.id);

        const onSignal = await subscribeToSignals(generation, pc, session.id);

        const pending = await signaling.pullSignals(session.id);
        for (const signal of pending) {
            await onSignal(signal);
        }

        try {
            if (!turnConfigured) {
                setStatus('connecting', 'Connecting… (TURN not configured — cross-network viewing may fail)');
            }

            if (!isWatchCurrent(generation) || pc !== adminPeerConnection) {
                detachPeerConnection(pc);
                return { turnConfigured };
            }

            if (chatEnabled) {
                chat.setSession(session.id);
                chat.startRealtime();
            }

            trackTimeout = setTimeout(() => {
                if (!isWatchCurrent(generation) || pc !== adminPeerConnection) {
                    return;
                }
                if (!trackReceived && offerApplied) {
                    const message = 'No video received yet. Close the modal and click Watch Live again.';
                    setStatus('failed', message);
                    onError(message);
                }
            }, 12000);

            pc.oniceconnectionstatechange = () => {
                if (!isWatchCurrent(generation) || pc !== adminPeerConnection) {
                    return;
                }

                const iceState = pc.iceConnectionState;
                if (iceState === 'checking' && offerApplied && !trackReceived) {
                    setStatus('connecting', 'Establishing peer connection…');
                }
                if ((iceState === 'connected' || iceState === 'completed') && offerApplied && !trackReceived) {
                    markStreamStarted();
                }
                if (iceState === 'failed') {
                    const message = 'Live view connection failed. Close and click Watch Live again.';
                    setStatus('failed', message);
                    onError(message);
                    stopWatching('connection_failed', message);
                }
            };

            pc.onconnectionstatechange = () => {
                if (!isWatchCurrent(generation) || pc !== adminPeerConnection) {
                    return;
                }

                const state = pc.connectionState;
                if (state === 'failed' || state === 'disconnected') {
                    const message = 'Live view unavailable. Latest recording clip will still be saved.';
                    setStatus('failed', message);
                    onError(message);
                    stopWatching('connection_failed', message);
                }
            };
        } catch (error) {
            if (isWatchCurrent(generation) && adminPeerConnection === pc) {
                const message = error.message || 'Live view unavailable. Latest recording clip will still be saved.';
                setStatus('failed', message);
                onError(message);
                detachPeerConnection(pc);
                resetTrackState();
            } else {
                detachPeerConnection(pc);
            }
            throw error;
        }

        return { turnConfigured: true };
    }

    async function startWatchingWorker(workerId, videoElement, attempt = 1) {
        const maxAttempts = 3;

        try {
            return await startWatchingWorkerOnce(workerId, videoElement);
        } catch (error) {
            if (attempt >= maxAttempts) {
                throw error;
            }

            setStatus('connecting', `Retrying connection (attempt ${attempt + 1} of ${maxAttempts})…`);
            await new Promise((resolve) => setTimeout(resolve, 600));

            return startWatchingWorker(workerId, videoElement, attempt + 1);
        }
    }

    async function startAudioChat(adminName) {
        if (!adminPeerConnection || !currentSession?.id || !currentWorkerId) {
            throw new Error('Live view must be connected before starting audio chat.');
        }

        if (!offerApplied) {
            throw new Error('Wait for the live video connection before starting audio chat.');
        }

        await audio.startAsAdmin(adminPeerConnection, currentWorkerId, currentSession.id, adminName);
        onAudioStateChange('active');
    }

    async function endAudioChat() {
        if (!currentSession?.id || !currentWorkerId) {
            audio.stopLocalAudio();
            onAudioStateChange('ended');
            return;
        }

        await audio.endAudio(currentWorkerId, currentSession.id);
        onAudioStateChange('ended');
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

    function getCurrentSession() {
        return currentSession;
    }

    function isChatSending() {
        return chat.isSending();
    }

    function isReadyForAudio() {
        return !!(adminPeerConnection && currentSession?.id && offerApplied);
    }

    return {
        configure,
        startWatchingWorker,
        stopWatching,
        startAudioChat,
        endAudioChat,
        sendChatMessage,
        loadChatMessages,
        syncChatMessages,
        getCurrentSession,
        isChatSending,
        isReadyForAudio,
    };
})();
