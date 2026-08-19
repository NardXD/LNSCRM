{{-- Classic script: register one Twilio Device on every CRM page so inbound calls ring outside /twilio/call. --}}
<script>
(function () {
    var hangupUrl = @json(route('twilio.hangup'));
    var presenceUrl = @json(route('twilio.agent-presence'));
    var endedUrl = @json(route('twilio.agent-ended-call'));
    var answeredUrl = @json(route('twilio.agent-answered-call'));

    function csrfHeaders() {
        return {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
        };
    }

    function readBanner() {
        return typeof window.__lnscrmReadCallBanner === 'function' ? window.__lnscrmReadCallBanner() : null;
    }

    function writeBanner(state) {
        if (typeof window.__lnscrmWriteCallBanner === 'function') {
            window.__lnscrmWriteCallBanner(state);
        }
    }

    function applyBanner(state) {
        if (typeof window.__lnscrmApplyCallBanner === 'function') {
            window.__lnscrmApplyCallBanner(state);
        }
    }

    function persistRinging(from, callSid, extra) {
        var current = readBanner() || {};
        extra = extra || {};
        writeBanner({
            status: 'ringing',
            from: from || current.from || 'Unknown',
            callSid: callSid || current.callSid || null,
            connectToken: extra.connectToken || current.connectToken || null,
            startedAt: null,
        });
        applyBanner(readBanner());
    }

    function persistAnswered(from, callSid, extra) {
        var current = readBanner() || {};
        extra = extra || {};
        writeBanner({
            status: 'answered',
            from: from || current.from || 'Unknown',
            callSid: callSid || current.callSid || null,
            connectToken: extra.connectToken || (typeof extra.connectToken === 'string' ? extra.connectToken : current.connectToken) || null,
            startedAt: current.status === 'answered' && current.startedAt ? current.startedAt : Date.now(),
        });
        applyBanner(readBanner());
        startDurationTimer();
    }

    function clearBanner() {
        writeBanner(null);
        applyBanner(null);
        stopDurationTimer();
        window.isCallAnswered = false;
    }

    function startDurationTimer() {
        var state = readBanner();
        if (!state || state.status !== 'answered' || !state.startedAt) return;
        window.callStartTime = Number(state.startedAt);
        if (window.callDurationInterval) clearInterval(window.callDurationInterval);
        window.callDurationInterval = setInterval(function () {
            var durationEl = document.getElementById('callDuration');
            if (!window.callStartTime || !durationEl) return;
            var elapsed = Math.max(0, Math.floor((Date.now() - window.callStartTime) / 1000));
            durationEl.textContent = String(Math.floor(elapsed / 60)).padStart(2, '0')
                + ':' + String(elapsed % 60).padStart(2, '0');
        }, 1000);
    }

    function stopDurationTimer() {
        if (window.callDurationInterval) {
            clearInterval(window.callDurationInterval);
            window.callDurationInterval = null;
        }
        window.callStartTime = null;
    }

    function callerFromCall(call) {
        return (call && call.parameters && (call.parameters.From || call.parameters.Caller))
            || (call && call.from)
            || 'Unknown';
    }

    function parentSidFromCall(call) {
        if (call && call.customParameters && typeof call.customParameters.get === 'function') {
            var custom = call.customParameters.get('parent_call_sid');
            if (custom) return custom;
        }
        if (call && call.parameters) {
            return call.parameters.parent_call_sid || call.parameters.ParentCallSid || call.parameters.CallSid || null;
        }
        return callSidFromCall(call);
    }

    function connectTokenFromCall(call) {
        try {
            return (call && call.connectToken) || null;
        } catch (e) {
            return null;
        }
    }

    function markAgentEnded(callSid) {
        if (!callSid) return;
        fetch(endedUrl, {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            keepalive: true,
            body: JSON.stringify({ call_sid: callSid }),
        }).catch(function () {});
    }

    function notifyCallAnswered(call, callSid) {
        if (call && call.__lnscrmLeadAssigned) return;
        if (!callSid) return;
        if (call) call.__lnscrmLeadAssigned = true;
        fetch(answeredUrl, {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            keepalive: true,
            body: JSON.stringify({ call_sid: callSid }),
        }).catch(function () {});
    }

    function autoReconnectAnsweredCall(call) {
        var from = callerFromCall(call);
        var sid = parentSidFromCall(call);
        window.globalActiveCall = call;
        window.__twilioActiveCall = call;
        window.isCallAnswered = true;
        persistAnswered(from, sid, { connectToken: connectTokenFromCall(call) });
        notifyCallAnswered(call, sid);
        var accept = function () {
            try {
                call.accept();
            } catch (e) {
                console.error('Failed to re-answer call after refresh', e);
            }
        };
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
                stream.getTracks().forEach(function (track) { track.stop(); });
                accept();
            }).catch(function () {
                accept();
            });
        } else {
            accept();
        }
    }

    function withinRestoreGrace() {
        return window.__lnscrmBannerRestoreAt && (Date.now() - window.__lnscrmBannerRestoreAt) < 8000;
    }

    function attachCallPersistence(call) {
        if (!call || call.__lnscrmBannerBound) return;
        call.__lnscrmBannerBound = true;

        var from = callerFromCall(call);
        var sid = parentSidFromCall(call);
        var token = connectTokenFromCall(call);
        var liveStatus = typeof call.status === 'function' ? String(call.status()) : '';
        var restored = readBanner();
        if (liveStatus === 'open' || liveStatus === 'connecting' || (restored && restored.status === 'answered')) {
            persistAnswered(from, sid, { connectToken: token });
        } else {
            persistRinging(from, sid, { connectToken: token });
        }

        if (typeof call.accept === 'function') {
            var origAccept = call.accept.bind(call);
            call.accept = function () {
                var acceptedSid = parentSidFromCall(call) || sid;
                persistAnswered(from, acceptedSid, { connectToken: connectTokenFromCall(call) || token });
                notifyCallAnswered(call, acceptedSid);
                return origAccept.apply(this, arguments);
            };
        }

        if (typeof call.on === 'function') {
            call.on('accept', function () {
                var acceptedSid = parentSidFromCall(call) || sid;
                persistAnswered(from, acceptedSid, { connectToken: connectTokenFromCall(call) || token });
                notifyCallAnswered(call, acceptedSid);
            });
            call.on('cancel', function () {
                if (window.isCallAnswered || (readBanner() && readBanner().status === 'answered')) {
                    return;
                }
                if (withinRestoreGrace()) {
                    applyBanner(readBanner());
                    return;
                }
                clearBanner();
            });
            call.on('disconnect', function () {
                if (withinRestoreGrace() && readBanner() && readBanner().status === 'answered') {
                    applyBanner(readBanner());
                    return;
                }
                clearBanner();
            });
            call.on('reject', function () {
                clearBanner();
            });
        }
    }

    function showIncomingBanner(callerNumber) {
        persistRinging(callerNumber, null);
    }

    function hangupViaApi(callSid) {
        if (!callSid) return Promise.resolve();
        return fetch(hangupUrl, {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            keepalive: true,
            body: JSON.stringify({ call_sid: callSid }),
        }).catch(function () {});
    }

    function resolveParentSid(call) {
        var state = readBanner() || {};
        return state.callSid || parentSidFromCall(call) || null;
    }

    function endEntireCall() {
        var liveCall = window.globalActiveCall || window.__twilioActiveCall;
        var sid = resolveParentSid(liveCall);
        markAgentEnded(sid);
        hangupViaApi(sid);
        try {
            if (liveCall && typeof liveCall.disconnect === 'function') {
                liveCall.disconnect();
            }
        } catch (e) {}
        try {
            if (window.globalTwilioDevice && typeof window.globalTwilioDevice.disconnectAll === 'function') {
                window.globalTwilioDevice.disconnectAll();
            }
        } catch (e) {}
        window.globalActiveCall = null;
        window.__twilioActiveCall = null;
        window.isCallAnswered = false;
        clearBanner();
    }

    window.hangupOngoingCall = endEntireCall;
    window.endEntireCall = endEntireCall;
    window.addEventListener('load', function () {
        window.hangupOngoingCall = endEntireCall;
    });
    setTimeout(function () { window.hangupOngoingCall = endEntireCall; }, 500);
    setTimeout(function () { window.hangupOngoingCall = endEntireCall; }, 2000);

    document.addEventListener('click', function (event) {
        var decline = event.target.closest('#declineCallBtn');
        var hangup = event.target.closest('#hangupCallBtn');
        if (decline) {
            clearBanner();
            return;
        }
        if (hangup) {
            event.preventDefault();
            event.stopPropagation();
            endEntireCall();
        }
    }, true);

    function getDeviceClass() {
        return window.TwilioVoiceSDK?.Device || window.Twilio?.Device || null;
    }

    function waitForDeviceClass(timeoutMs) {
        return new Promise(function (resolve) {
            var existing = getDeviceClass();
            if (existing) {
                resolve(existing);
                return;
            }
            var started = Date.now();
            var timer = setInterval(function () {
                var Device = getDeviceClass();
                if (Device) {
                    clearInterval(timer);
                    resolve(Device);
                } else if (Date.now() - started >= timeoutMs) {
                    clearInterval(timer);
                    resolve(null);
                }
            }, 50);
        });
    }

    if (typeof window.handleIncomingCall !== 'function') {
        window.handleIncomingCall = function (call) {
            var callerNumber = callerFromCall(call);
            window.globalActiveCall = call;
            window.__twilioActiveCall = call;
            window.isCallAnswered = false;
            showIncomingBanner(callerNumber);
            attachCallPersistence(call);
        };
    }

    function tryReconnectWithConnectToken(device) {
        var state = readBanner();
        if (!device || !state || state.status !== 'answered' || !state.connectToken) {
            return;
        }
        if (window.globalActiveCall) {
            return;
        }
        if (typeof device.connect !== 'function') {
            return;
        }
        device.connect({ connectToken: state.connectToken }).then(function (call) {
            window.globalActiveCall = call;
            window.__twilioActiveCall = call;
            window.isCallAnswered = true;
            attachCallPersistence(call);
            persistAnswered(state.from, state.callSid, { connectToken: state.connectToken });
        }).catch(function () {
            // Incoming re-dial will auto-accept instead.
        });
    }

    function bindIncoming(device) {
        if (!device || window.__lnscrmIncomingBound) {
            return;
        }
        window.__lnscrmIncomingBound = true;
        device.on('incoming', function (call) {
            window.globalActiveCall = call;
            window.__twilioActiveCall = call;
            var state = readBanner();
            if (state && state.status === 'answered') {
                autoReconnectAnsweredCall(call);
                attachCallPersistence(call);
                return;
            }
            attachCallPersistence(call);
            if (typeof window.handleIncomingCall === 'function') {
                window.handleIncomingCall(call);
            } else {
                showIncomingBanner(callerFromCall(call));
            }
        });
        device.on('tokenWillExpire', function () {
            window.ensureLnscrmTwilioDevice(true);
        });
    }

    window.ensureLnscrmTwilioDevice = function (refreshToken) {
        if (window.globalTwilioDevice && !refreshToken) {
            bindIncoming(window.globalTwilioDevice);
            return Promise.resolve(window.globalTwilioDevice);
        }
        if (window.__lnscrmTwilioInitPromise && !refreshToken) {
            return window.__lnscrmTwilioInitPromise;
        }

        window.__lnscrmTwilioInitPromise = (async function () {
            try {
                var Device = await waitForDeviceClass(20000);
                if (!Device) {
                    return null;
                }

                if (window.globalTwilioDevice && !refreshToken) {
                    bindIncoming(window.globalTwilioDevice);
                    return window.globalTwilioDevice;
                }

                var csrf = document.querySelector('meta[name="csrf-token"]');
                var response = await fetch('{{ route('twilio.capability-token') }}', {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '',
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                });
                if (!response.ok) {
                    return window.globalTwilioDevice || null;
                }

                var data = await response.json();
                if (!data || !data.success || !data.token) {
                    return window.globalTwilioDevice || null;
                }

                if (window.globalTwilioDevice) {
                    if (refreshToken && typeof window.globalTwilioDevice.updateToken === 'function') {
                        window.globalTwilioDevice.updateToken(data.token);
                    }
                    bindIncoming(window.globalTwilioDevice);
                    return window.globalTwilioDevice;
                }

                var device = new Device(data.token, {
                    logLevel: 'error',
                    codecPreferences: ['opus', 'pcmu'],
                });
                window.globalTwilioDevice = device;
                bindIncoming(device);
                window.dispatchEvent(new CustomEvent('lnscrm:twilio-device-ready', {
                    detail: { device: device },
                }));
                device.on('registered', function () {
                    tryReconnectWithConnectToken(device);
                });
                device.register();
                return device;
            } catch (error) {
                console.error('Failed to register Twilio Device for inbound calls', error);
                window.__lnscrmTwilioInitPromise = null;
                return window.globalTwilioDevice || null;
            }
        })();

        return window.__lnscrmTwilioInitPromise;
    };

    var restored = readBanner();
    if (restored) {
        window.__lnscrmBannerRestoreAt = Date.now();
        applyBanner(restored);
        if (restored.status === 'answered') {
            startDurationTimer();
        }
    }

    fetch(presenceUrl, {
        method: 'GET',
        headers: csrfHeaders(),
        credentials: 'same-origin',
    }).then(function (response) {
        return response.ok ? response.json() : null;
    }).then(function (payload) {
        var me = payload && payload.data && payload.data.me;
        if (!me) return;
        var existing = readBanner();
        if (existing && existing.status === 'answered') {
            if (me.current_call_sid && !existing.callSid) {
                persistAnswered(existing.from, me.current_call_sid);
            }
            return;
        }
        if (me.status !== 'busy' || !me.current_call_sid) {
            if (!window.globalActiveCall && !window.__twilioActiveCall && (!existing || existing.status !== 'answered')) {
                setTimeout(function () {
                    if (!window.globalActiveCall && !window.__twilioActiveCall) {
                        var latest = readBanner();
                        if (!latest || latest.status !== 'answered') {
                            clearBanner();
                        }
                    }
                }, 12000);
            }
            return;
        }
        persistRinging(
            me.current_from_number || (existing && existing.from) || 'Unknown',
            me.current_call_sid || (existing && existing.callSid) || null
        );
    }).catch(function () {});

    window.ensureLnscrmTwilioDevice();
})();
</script>
