// Global Infobip RTC softphone for inbound/outbound browser calls
import { createInfobipRtc } from 'infobip-rtc';

(function () {
    'use strict';

    if (typeof window === 'undefined') {
        return;
    }

    window.createInfobipRtc = createInfobipRtc;
    window.globalInfobipRtc = window.globalInfobipRtc || null;
    window.globalActiveCall = window.globalActiveCall || null;
    window.isCallAnswered = window.isCallAnswered || false;
    window.__infobipActiveCall = window.__infobipActiveCall || null;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function callerLabel(call) {
        try {
            const source = typeof call?.source === 'function' ? call.source() : call?.source;
            return source?.identifier || source?.displayName || call?.from || 'Unknown';
        } catch {
            return 'Unknown';
        }
    }

    function stopGlobalRingSound() {
        if (window.__infobipRingAudio) {
            try {
                window.__infobipRingAudio.pause();
                window.__infobipRingAudio.currentTime = 0;
            } catch (_) {}
            window.__infobipRingAudio = null;
        }
    }

    function startGlobalRingSound() {
        stopGlobalRingSound();
        try {
            const audio = new Audio('/sounds/ringtone.mp3');
            audio.loop = true;
            audio.play().catch(() => {});
            window.__infobipRingAudio = audio;
        } catch (_) {}
    }

    function clearDurationTimer() {
        if (window.callDurationInterval) {
            clearInterval(window.callDurationInterval);
            window.callDurationInterval = null;
        }
        window.callStartTime = null;
    }

    function startDurationTimer() {
        const callDuration = document.getElementById('callDuration');
        window.callStartTime = Date.now();
        clearDurationTimer();
        window.callStartTime = Date.now();

        const update = () => {
            if (!window.callStartTime || !callDuration) return;
            const elapsed = Math.floor((Date.now() - window.callStartTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            callDuration.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        };

        update();
        window.callDurationInterval = setInterval(update, 1000);
    }

    function hideIncoming() {
        const notification = document.getElementById('inboundCallNotification');
        if (notification) notification.style.display = 'none';
        stopGlobalRingSound();
    }

    function hideOngoing() {
        const ongoing = document.getElementById('ongoingCallNotification');
        if (ongoing) ongoing.style.display = 'none';
        clearDurationTimer();
    }

    function showOngoing(label) {
        const ongoingNotification = document.getElementById('ongoingCallNotification');
        const ongoingNumber = document.getElementById('ongoingCallNumber');
        if (ongoingNotification && ongoingNumber) {
            ongoingNumber.textContent = label;
            ongoingNotification.style.display = 'block';
            startDurationTimer();
        }
    }

    function wireCallLifecycle(call) {
        const hangupEvents = ['hangup', 'error', 'disconnected'];
        hangupEvents.forEach((eventName) => {
            try {
                call.on?.(eventName, () => {
                    window.globalActiveCall = null;
                    window.__infobipActiveCall = null;
                    window.isCallAnswered = false;
                    hideIncoming();
                    hideOngoing();
                });
            } catch (_) {}
        });
    }

    window.handleIncomingCall = function (call) {
        const label = callerLabel(call);
        window.globalActiveCall = call;
        window.__infobipActiveCall = call;
        window.isCallAnswered = false;
        wireCallLifecycle(call);

        const notification = document.getElementById('inboundCallNotification');
        const numberElement = document.getElementById('incomingCallNumber');
        if (notification && numberElement) {
            numberElement.textContent = label;
            notification.style.display = 'block';
            startGlobalRingSound();
        } else {
            // Fallback when global call UI is not mounted
            console.info('Incoming Infobip call from', label);
        }
    };

    window.answerIncomingCall = async function () {
        const activeCall = window.globalActiveCall;
        if (!activeCall) return;

        try {
            await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch (_) {}

        window.isCallAnswered = true;
        hideIncoming();

        try {
            activeCall.accept?.();
        } catch (e) {
            console.error('Failed to accept Infobip call', e);
            return;
        }

        showOngoing(callerLabel(activeCall));
        wireCallLifecycle(activeCall);
    };

    window.declineIncomingCall = function () {
        const activeCall = window.globalActiveCall;
        if (!activeCall) return;
        try {
            activeCall.decline?.() || activeCall.reject?.() || activeCall.hangup?.();
        } catch (_) {}
        window.globalActiveCall = null;
        window.__infobipActiveCall = null;
        window.isCallAnswered = false;
        hideIncoming();
        hideOngoing();
    };

    window.hangupGlobalCall = function () {
        const activeCall = window.globalActiveCall || window.__infobipActiveCall;
        if (activeCall) {
            try {
                activeCall.hangup?.();
            } catch (_) {}
        }
        window.globalActiveCall = null;
        window.__infobipActiveCall = null;
        window.isCallAnswered = false;
        hideIncoming();
        hideOngoing();
    };

    async function fetchToken() {
        const response = await fetch('/twilio/capability-token', {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
        });
        const data = await response.json();
        if (!response.ok || !data.success || !data.token) {
            throw new Error(data.message || 'Failed to fetch Infobip WebRTC token');
        }
        return data;
    }

    async function initInfobipRtc(force = false) {
        if (window.globalInfobipRtc && !force) {
            return window.globalInfobipRtc;
        }

        const data = await fetchToken();
        const rtc = createInfobipRtc(data.token, { debug: false });

        rtc.on('connected', () => {
            console.info('Infobip RTC connected', data.identity || '');
        });

        rtc.on('disconnected', () => {
            console.warn('Infobip RTC disconnected');
        });

        rtc.on('incoming-webrtc-call', (event) => {
            const call = event?.incomingCall || event;
            window.handleIncomingCall(call);
        });

        rtc.on('incoming-application-call', (event) => {
            const call = event?.incomingCall || event;
            window.handleIncomingCall(call);
        });

        await rtc.connect();
        window.globalInfobipRtc = rtc;
        window.globalTwilioDevice = rtc; // legacy alias used by older phone panel scripts
        return rtc;
    }

    window.initGlobalTwilioDevice = initInfobipRtc;
    window.initGlobalInfobipRtc = initInfobipRtc;

    window.placeInfobipPhoneCall = async function (phoneNumber) {
        const rtc = await initInfobipRtc();
        const call = rtc.callPhone(phoneNumber, { from: undefined });
        window.globalActiveCall = call;
        window.__infobipActiveCall = call;
        window.isCallAnswered = true;
        showOngoing(phoneNumber);
        wireCallLifecycle(call);
        return call;
    };

    document.addEventListener('DOMContentLoaded', () => {
        initInfobipRtc().catch((err) => {
            console.warn('Infobip RTC not initialized:', err?.message || err);
        });
    });
})();
