{{-- Classic script: register one Twilio Device on every CRM page so inbound calls ring outside /twilio/call. --}}
<script>
(function () {
    var hangupUrl = @json(route('twilio.hangup'));
    var presenceUrl = @json(route('twilio.agent-presence'));

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

    function persistRinging(from, callSid) {
        var current = readBanner() || {};
        writeBanner({
            status: 'ringing',
            from: from || current.from || 'Unknown',
            callSid: callSid || current.callSid || null,
            startedAt: null,
        });
        applyBanner(readBanner());
    }

    function persistAnswered(from, callSid) {
        var current = readBanner() || {};
        writeBanner({
            status: 'answered',
            from: from || current.from || 'Unknown',
            callSid: callSid || current.callSid || null,
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

    function callSidFromCall(call) {
        return (call && call.parameters && call.parameters.CallSid)
            || (call && call.parameters && call.parameters.CallSid)
            || (call && call.sid)
            || null;
    }

    function withinRestoreGrace() {
        return window.__lnscrmBannerRestoreAt && (Date.now() - window.__lnscrmBannerRestoreAt) < 8000;
    }

    function attachCallPersistence(call) {
        if (!call || call.__lnscrmBannerBound) return;
        call.__lnscrmBannerBound = true;

        var from = callerFromCall(call);
        var sid = callSidFromCall(call);
        var restored = readBanner();
        if (restored && restored.status === 'answered') {
            persistAnswered(from, sid || restored.callSid);
        } else {
            persistRinging(from, sid);
        }

        if (typeof call.accept === 'function') {
            var origAccept = call.accept.bind(call);
            call.accept = function () {
                persistAnswered(from, callSidFromCall(call) || sid);
                return origAccept.apply(this, arguments);
            };
        }

        if (typeof call.on === 'function') {
            call.on('accept', function () {
                persistAnswered(from, callSidFromCall(call) || sid);
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
            body: JSON.stringify({ call_sid: callSid }),
        }).catch(function () {});
    }

    document.addEventListener('click', function (event) {
        var answer = event.target.closest('#answerCallBtn');
        var decline = event.target.closest('#declineCallBtn');
        var hangup = event.target.closest('#hangupCallBtn');
        if (answer) {
            var ringing = readBanner() || {};
            persistAnswered(ringing.from, ringing.callSid);
            return;
        }
        if (decline) {
            clearBanner();
            return;
        }
        if (hangup) {
            var state = readBanner();
            var liveCall = window.globalActiveCall || window.__twilioActiveCall;
            if ((!liveCall || typeof liveCall.disconnect !== 'function') && state && state.callSid) {
                hangupViaApi(state.callSid);
            }
            clearBanner();
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

    function bindIncoming(device) {
        if (!device || window.__lnscrmIncomingBound) {
            return;
        }
        window.__lnscrmIncomingBound = true;
        device.on('incoming', function (call) {
            window.globalActiveCall = call;
            window.__twilioActiveCall = call;
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

    setInterval(function () {
        var state = readBanner();
        if (state) applyBanner(state);
    }, 400);

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
        if (me.status !== 'busy') {
            return;
        }
        if (existing && existing.status === 'ringing') {
            persistRinging(existing.from || me.current_from_number, me.current_call_sid || existing.callSid);
            return;
        }
        if (me.current_call_sid || me.current_from_number) {
            persistAnswered(me.current_from_number || (existing && existing.from) || 'Unknown', me.current_call_sid);
        }
    }).catch(function () {});

    window.ensureLnscrmTwilioDevice();
})();
</script>
