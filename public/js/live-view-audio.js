/**
 * Bidirectional audio chat over an existing live-view WebRTC peer connection.
 */
window.LiveViewAudio = (function () {
    const signaling = window.LiveViewSignaling;
    let localAudioStream = null;
    let remoteAudioElement = null;
    let audioActive = false;
    let pendingRenegotiationOffer = null;
    let pendingPeerId = null;
    let pendingSessionId = null;
    let onAudioRequest = () => {};
    let onAudioEnded = () => {};
    let onAudioError = () => {};

    function configure(options = {}) {
        onAudioRequest = options.onAudioRequest || onAudioRequest;
        onAudioEnded = options.onAudioEnded || onAudioEnded;
        onAudioError = options.onAudioError || onAudioError;
    }

    function ensureRemoteAudioElement() {
        if (remoteAudioElement) {
            return remoteAudioElement;
        }

        remoteAudioElement = document.createElement('audio');
        remoteAudioElement.autoplay = true;
        remoteAudioElement.playsInline = true;
        remoteAudioElement.style.display = 'none';
        document.body.appendChild(remoteAudioElement);
        return remoteAudioElement;
    }

    function attachRemoteAudio(stream) {
        const audio = ensureRemoteAudioElement();
        audio.srcObject = stream;
        audio.play().catch((error) => {
            console.warn('Remote audio play blocked', error);
        });
        audioActive = true;
    }

    async function requestMicrophone() {
        if (localAudioStream) {
            return localAudioStream;
        }

        try {
            localAudioStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                },
                video: false,
            });
            return localAudioStream;
        } catch (error) {
            const message = error?.name === 'NotAllowedError'
                ? 'Microphone access was denied. Please allow microphone access in your browser settings.'
                : (error.message || 'Unable to access microphone.');
            onAudioError(message);
            throw new Error(message);
        }
    }

    function addLocalAudioToPeer(pc) {
        if (!localAudioStream || !pc) {
            return;
        }

        const senders = pc.getSenders();
        localAudioStream.getAudioTracks().forEach((track) => {
            const existing = senders.find((sender) => sender.track?.id === track.id);
            if (!existing) {
                pc.addTrack(track, localAudioStream);
            }
        });
    }

    async function sendAudioOffer(pc, peerId, sessionId) {
        addLocalAudioToPeer(pc);
        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        await signaling.waitForIceGathering(pc);

        await signaling.sendSignal(
            peerId,
            'offer',
            {
                offer: signaling.serializeSessionDescription(pc.localDescription),
                renegotiation: true,
                audio: true,
            },
            sessionId
        );

        await signaling.flushPendingIceCandidates(pc);
    }

    async function applyAudioAnswer(pc, payload) {
        if (!payload?.answer) {
            return;
        }

        await pc.setRemoteDescription(
            signaling.deserializeSessionDescription(payload.answer)
        );
        await signaling.flushPendingIceCandidates(pc);
        audioActive = true;
    }

    async function applyRenegotiationOffer(pc, payload, peerId, sessionId) {
        if (!payload?.offer) {
            return;
        }

        await pc.setRemoteDescription(
            signaling.deserializeSessionDescription(payload.offer)
        );

        if (!localAudioStream) {
            pendingRenegotiationOffer = payload;
            pendingPeerId = peerId;
            pendingSessionId = sessionId;
            return false;
        }

        addLocalAudioToPeer(pc);
        const answer = await pc.createAnswer();
        await pc.setLocalDescription(answer);
        await signaling.waitForIceGathering(pc);

        await signaling.sendSignal(
            peerId,
            'answer',
            {
                answer: signaling.serializeSessionDescription(pc.localDescription),
                renegotiation: true,
                audio: true,
            },
            sessionId
        );

        await signaling.flushPendingIceCandidates(pc);
        audioActive = true;
        return true;
    }

    async function acceptPendingRenegotiation(pc) {
        if (!pendingRenegotiationOffer || !pendingPeerId || !pendingSessionId) {
            return false;
        }

        const payload = pendingRenegotiationOffer;
        const peerId = pendingPeerId;
        const sessionId = pendingSessionId;

        pendingRenegotiationOffer = null;
        pendingPeerId = null;
        pendingSessionId = null;

        await requestMicrophone();
        return applyRenegotiationOffer(pc, payload, peerId, sessionId);
    }

    async function startAsAdmin(pc, workerId, sessionId, adminName) {
        await requestMicrophone();

        await signaling.sendSignal(
            workerId,
            'live-view-audio-request',
            { admin_name: adminName, session_id: sessionId },
            sessionId
        );

        await sendAudioOffer(pc, workerId, sessionId);
        audioActive = true;
        return true;
    }

    async function enableAsWorker(pc, adminId, sessionId) {
        await requestMicrophone();

        if (pendingRenegotiationOffer) {
            await acceptPendingRenegotiation(pc);
            return true;
        }

        await sendAudioOffer(pc, adminId, sessionId);
        return true;
    }

    async function declineAsWorker(adminId, sessionId) {
        await signaling.sendSignal(
            adminId,
            'live-view-audio-decline',
            { session_id: sessionId },
            sessionId
        );
    }

    function handleIncomingTrack(event) {
        if (!event.track || event.track.kind !== 'audio') {
            return;
        }

        let stream = event.streams[0];
        if (!stream) {
            stream = new MediaStream([event.track]);
        }

        attachRemoteAudio(stream);
    }

    function handleSignal(signal, pc, callbacks = {}) {
        const payload = signal.payload || {};
        const peerId = signal.from_user_id;
        const sessionId = signal.session_id;

        if (signal.signal_type === 'live-view-audio-request') {
            onAudioRequest({
                adminName: payload.admin_name || 'Administrator',
                sessionId: payload.session_id || sessionId,
                peerId,
            });
            return;
        }

        if (signal.signal_type === 'live-view-audio-decline') {
            onAudioError('Employee declined audio chat.');
            return;
        }

        if (signal.signal_type === 'live-view-audio-end') {
            stopLocalAudio();
            onAudioEnded();
            return;
        }

        if (!pc) {
            return;
        }

        if (signal.signal_type === 'offer' && payload.renegotiation && payload.offer) {
            applyRenegotiationOffer(pc, payload, peerId, sessionId).then((answered) => {
                if (!answered && typeof callbacks.onRenegotiationPending === 'function') {
                    callbacks.onRenegotiationPending({
                        adminName: payload.admin_name || 'Administrator',
                        peerId,
                        sessionId,
                    });
                }
            }).catch((error) => {
                console.error('Audio renegotiation failed', error);
                onAudioError(error.message || 'Audio connection failed.');
            });
            return;
        }

        if (signal.signal_type === 'answer' && payload.renegotiation && payload.answer) {
            applyAudioAnswer(pc, payload).catch((error) => {
                console.error('Failed to apply audio answer', error);
                onAudioError(error.message || 'Audio connection failed.');
            });
        }
    }

    function stopLocalAudio() {
        if (localAudioStream) {
            localAudioStream.getTracks().forEach((track) => track.stop());
            localAudioStream = null;
        }

        if (remoteAudioElement) {
            remoteAudioElement.srcObject = null;
        }

        pendingRenegotiationOffer = null;
        pendingPeerId = null;
        pendingSessionId = null;
        audioActive = false;
    }

    async function endAudio(peerId, sessionId) {
        if (peerId && sessionId) {
            try {
                await signaling.sendSignal(
                    peerId,
                    'live-view-audio-end',
                    { session_id: sessionId },
                    sessionId
                );
            } catch (error) {
                console.warn('Failed to send audio end signal', error);
            }
        }

        stopLocalAudio();
        onAudioEnded();
    }

    function isAudioActive() {
        return audioActive;
    }

    function hasPendingAudioRequest() {
        return !!pendingRenegotiationOffer;
    }

    return {
        configure,
        requestMicrophone,
        startAsAdmin,
        enableAsWorker,
        declineAsWorker,
        acceptPendingRenegotiation,
        handleIncomingTrack,
        handleSignal,
        endAudio,
        stopLocalAudio,
        isAudioActive,
        hasPendingAudioRequest,
    };
})();
