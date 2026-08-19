// Global Twilio Device Initialization for Inbound Calls
// This script initializes the Twilio device globally so incoming calls can be received anywhere

// FORCE EXECUTION - this MUST run
(function() {
    'use strict';
    console.log('🚀🚀🚀 twilio-global.js LOADED 🚀🚀🚀');
    // console.log('📍 Current page:', typeof window !== 'undefined' ? window.location.pathname : 'N/A');
    // console.log('⏰ Load time:', new Date().toISOString());
    // console.log('✅ File is executing!');
    
    // Immediately define handleIncomingCall to ensure it exists
    if (typeof window !== 'undefined') {
        window.handleIncomingCall = window.handleIncomingCall || function(call) {
            console.log('🔔 handleIncomingCall called (early fallback)');
            const callerNumber = call.parameters?.From || call.parameters?.Caller || call.from || 'Unknown';
            window.globalActiveCall = call;
            const notification = document.getElementById('inboundCallNotification');
            const numberElement = document.getElementById('incomingCallNumber');
            if (notification && numberElement) {
                numberElement.textContent = callerNumber;
                notification.style.display = 'block';
            } else {
                alert('Incoming call from: ' + callerNumber);
            }
        };
        // console.log('✅ window.handleIncomingCall defined (early):', typeof window.handleIncomingCall);
    }
})();

// Define functions immediately so they're available globally
if (typeof window !== 'undefined') {
    // Answer incoming call - define immediately
    window.answerIncomingCall = async function() {
        const activeCall = window.globalActiveCall;
        if (!activeCall) {
            console.error('No active call to answer');
            return;
        }
        try {
            await navigator.mediaDevices.getUserMedia({ audio: true });
            
            // Mark call as answered BEFORE accept() so cancel event doesn't hide ongoing notification
            window.isCallAnswered = true;
            
            activeCall.accept();
            
            console.log('Call answered');
            // Keep heartbeat alive while on a queued call so we return to available after hangup.
            if (callQueueAvailable) {
                sendCallQueueHeartbeat();
            }
            
            // Hide incoming notification
            const incomingNotification = document.getElementById('inboundCallNotification');
            if (incomingNotification) incomingNotification.style.display = 'none';
            
            // Stop ring sound
            if (typeof stopGlobalRingSound === 'function') stopGlobalRingSound();
            
            // Show ongoing call notification
            const ongoingNotification = document.getElementById('ongoingCallNotification');
            const ongoingNumber = document.getElementById('ongoingCallNumber');
            const callDuration = document.getElementById('callDuration');
            
            if (ongoingNotification && ongoingNumber) {
                const callerNumber = activeCall.parameters?.From || activeCall.parameters?.Caller || activeCall.from || 'Unknown';
                ongoingNumber.textContent = callerNumber;
                if (callDuration) callDuration.textContent = '00:00';
                ongoingNotification.style.display = 'block';
                
                // Start call duration timer
                window.callStartTime = Date.now();
                if (window.callDurationInterval) clearInterval(window.callDurationInterval);
                
                function updateDuration() {
                    if (!window.callStartTime || !callDuration) return;
                    const elapsed = Math.floor((Date.now() - window.callStartTime) / 1000);
                    const minutes = Math.floor(elapsed / 60);
                    const seconds = elapsed % 60;
                    callDuration.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                }
                
                updateDuration();
                window.callDurationInterval = setInterval(updateDuration, 1000);
            }
            
            // Handle disconnect event
            activeCall.on('disconnect', () => {
                window.globalActiveCall = null;
                window.__twilioActiveCall = null;
                window.isCallAnswered = false;
                
                // Hide ongoing notification
                const ongoingNotif = document.getElementById('ongoingCallNotification');
                if (ongoingNotif) ongoingNotif.style.display = 'none';
                
                // Clear timer
                if (window.callDurationInterval) {
                    clearInterval(window.callDurationInterval);
                    window.callDurationInterval = null;
                }
                window.callStartTime = null;

                // Re-assert availability after call ends (server also releases busy via webhook).
                if (callQueueAvailable) {
                    window.setCallQueueAvailable(true).catch(() => {});
                }
                
                console.log('Call disconnected');
            });
        } catch (error) {
            console.error('Error answering call:', error);
            if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
                alert('Microphone permission is required to answer calls. Please allow microphone access.');
            } else {
                alert('Failed to answer call: ' + error.message);
            }
        }
    };
    
    // Hangup ongoing call
    window.hangupOngoingCall = function() {
        const call = window.globalActiveCall || window.__twilioActiveCall;
        if (!call) {
            console.error('No active call to hangup');
            // Still hide the notification in case it's stuck
            const notification = document.getElementById('ongoingCallNotification');
            if (notification) notification.style.display = 'none';
            window.isCallAnswered = false;
            return;
        }
        try {
            if (typeof call.disconnect === 'function') {
                call.disconnect();
            }
            const notification = document.getElementById('ongoingCallNotification');
            if (notification) notification.style.display = 'none';
            
            // Clear timer
            if (window.callDurationInterval) {
                clearInterval(window.callDurationInterval);
                window.callDurationInterval = null;
            }
            window.callStartTime = null;
            window.isCallAnswered = false;
            
            window.globalActiveCall = null;
            window.__twilioActiveCall = null;
            console.log('Call hung up');
        } catch (error) {
            console.error('Error hanging up call:', error);
            const notification = document.getElementById('ongoingCallNotification');
            if (notification) notification.style.display = 'none';
            window.isCallAnswered = false;
            window.globalActiveCall = null;
            window.__twilioActiveCall = null;
        }
    };
    
    // Decline incoming call - define immediately
    window.declineIncomingCall = function() {
        const activeCall = window.globalActiveCall;
        if (!activeCall) {
            console.error('No active call to decline');
            return;
        }
        try {
            activeCall.reject();
            console.log('Call declined');
            const notification = document.getElementById('inboundCallNotification');
            if (notification) notification.style.display = 'none';
            if (typeof stopGlobalRingSound === 'function') stopGlobalRingSound();
            window.globalActiveCall = null;
        } catch (error) {
            console.error('Error declining call:', error);
            const notification = document.getElementById('inboundCallNotification');
            if (notification) notification.style.display = 'none';
            if (typeof stopGlobalRingSound === 'function') stopGlobalRingSound();
            window.globalActiveCall = null;
        }
    };
}

// Define handleIncomingCall IMMEDIATELY after the fallback functions
// console.log('🔧 Defining window.handleIncomingCall (early definition)...');
window.handleIncomingCall = function(call) {
    // console.log('🔔🔔🔔 handleIncomingCall EXECUTED (early version) 🔔🔔🔔');
    // console.log('📍 Current page:', window.location.pathname);
    // console.log('📞 Call object:', call);
    
    // Store the call globally
    if (typeof window !== 'undefined') {
        window.globalActiveCall = call;
        window.__twilioActiveCall = call;
    }
    
    // Get caller information
    const callerNumber = call.parameters?.From || call.parameters?.Caller || call.from || 'Unknown';
    console.log('📞 Caller number:', callerNumber);
    
    // Show notification
    const notification = document.getElementById('inboundCallNotification');
    const numberElement = document.getElementById('incomingCallNumber');
    
    if (notification && numberElement) {
        numberElement.textContent = callerNumber;
        notification.style.display = 'block';
        notification.style.visibility = 'visible';
        notification.style.opacity = '1';
        // console.log('✅✅✅ NOTIFICATION SHOWN (early version) ✅✅✅');
    } else {
        console.error('❌ Notification elements not found');
        alert('Incoming call from: ' + callerNumber);
    }
};

let globalTwilioDevice = null;
let globalTwilioInitPromise = null;
let globalActiveCall = null;
let globalRingAudioContext = null;
let globalRingOscillator = null;
let globalRingGainNode = null;
let globalRingInterval = null;
let isGlobalRinging = false;
let callDurationInterval = null;
let callStartTime = null;
let callQueueHeartbeatTimer = null;
let callQueueAvailable = false;
const CALL_QUEUE_STORAGE_KEY = 'lnscrm.callQueueAvailable';

function csrfHeaders() {
    return {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    };
}

function persistCallQueueAvailable(on) {
    try {
        localStorage.setItem(CALL_QUEUE_STORAGE_KEY, on ? '1' : '0');
    } catch (error) {
        // Ignore private-mode / storage failures.
    }
}

function readPersistedCallQueueAvailable() {
    try {
        return localStorage.getItem(CALL_QUEUE_STORAGE_KEY) === '1';
    } catch (error) {
        return false;
    }
}

function applyCallQueuePresenceUi(isOn, status) {
    const on = !!isOn;
    const statusText = status === 'busy' ? 'On call' : (on ? 'Available' : 'Offline');
    document.querySelectorAll('#agentAvailableToggle').forEach((el) => {
        el.checked = on;
    });
    const headerToggle = document.getElementById('headerAgentAvailableToggle');
    if (headerToggle) {
        if (headerToggle.hasAttribute('aria-checked') || headerToggle.getAttribute('role') === 'switch') {
            headerToggle.setAttribute('aria-checked', on ? 'true' : 'false');
        } else {
            headerToggle.checked = on;
        }
    }
    document.querySelectorAll('#agentAvailableLabel, #headerAgentAvailableLabel').forEach((el) => {
        el.textContent = statusText;
    });
    const subtitle = document.getElementById('agentQueueSubtitle');
    if (subtitle) {
        subtitle.textContent = isOn
            ? 'You are in the inbound round-robin queue — calls ring on any CRM page'
            : 'Turn on to receive round-robin inbound calls on any CRM page';
    }
}

async function sendCallQueueHeartbeat() {
    if (!callQueueAvailable) return;
    try {
        await fetch('/twilio/agent-presence/heartbeat', {
            method: 'POST',
            headers: csrfHeaders(),
            keepalive: true,
        });
    } catch (error) {
        console.warn('Call queue heartbeat failed', error);
    }
}

function startCallQueueHeartbeat() {
    callQueueAvailable = true;
    persistCallQueueAvailable(true);
    if (callQueueHeartbeatTimer) {
        clearInterval(callQueueHeartbeatTimer);
    }
    sendCallQueueHeartbeat();
    callQueueHeartbeatTimer = setInterval(sendCallQueueHeartbeat, 20000);
}

function stopCallQueueHeartbeat() {
    callQueueAvailable = false;
    persistCallQueueAvailable(false);
    if (callQueueHeartbeatTimer) {
        clearInterval(callQueueHeartbeatTimer);
        callQueueHeartbeatTimer = null;
    }
}

window.syncCallQueuePresence = function (isAvailable, status) {
    const on = !!isAvailable;
    applyCallQueuePresenceUi(on, status || (on ? 'available' : 'offline'));
    if (on) {
        startCallQueueHeartbeat();
        initializeGlobalTwilioDevice();
    } else {
        stopCallQueueHeartbeat();
    }
};

window.setCallQueueAvailable = async function (available, options = {}) {
    try {
        if (available && options.requestMic && navigator.mediaDevices?.getUserMedia) {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                stream.getTracks().forEach((track) => track.stop());
            } catch (error) {
                console.warn('Microphone permission not granted yet; calls can still be answered after allowing access.', error);
            }
        }

        const response = await fetch('/twilio/agent-presence', {
            method: 'POST',
            headers: csrfHeaders(),
            body: JSON.stringify({ status: available ? 'available' : 'offline' }),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to update availability');
        }
        const status = data?.data?.me?.status || (available ? 'available' : 'offline');
        window.syncCallQueuePresence(available, status);
        if (typeof window.updateHeaderQueueMeta === 'function' && data.data) {
            window.updateHeaderQueueMeta(data.data);
        }
        window.dispatchEvent(new CustomEvent('lnscrm:call-queue-changed', { detail: data }));
        return data;
    } catch (error) {
        console.error('Failed to set call queue availability', error);
        throw error;
    }
};

async function bootstrapCallQueuePresence() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (!csrfToken) return;

    const persistedOn = readPersistedCallQueueAvailable();
    if (persistedOn) {
        window.syncCallQueuePresence(true, 'available');
    }

    try {
        const response = await fetch('/twilio/agent-presence', {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': csrfToken.getAttribute('content') || '',
                'Accept': 'application/json',
            },
        });
        if (!response.ok) {
            if (persistedOn) {
                await window.setCallQueueAvailable(true).catch(() => {});
            }
            return;
        }
        const data = await response.json();
        const status = data?.data?.me?.status;
        const serverOn = status === 'available' || status === 'busy';

        if (persistedOn && !serverOn) {
            await window.setCallQueueAvailable(true).catch(() => {});
            return;
        }

        window.syncCallQueuePresence(serverOn, status);
    } catch (error) {
        if (persistedOn) {
            await window.setCallQueueAvailable(true).catch(() => {});
        }
    }
}

function bindHeaderAgentQueueToggle() {
    const toggle = document.getElementById('headerAgentAvailableToggle');
    if (!toggle || toggle.dataset.bound === '1') return;
    toggle.dataset.bound = '1';
    const eventName = toggle.getAttribute('role') === 'switch' ? 'click' : 'change';
    toggle.addEventListener(eventName, async (event) => {
        if (toggle.dataset.busy === '1') return;
        const on = eventName === 'click'
            ? toggle.getAttribute('aria-checked') !== 'true'
            : !!event.target.checked;
        applyCallQueuePresenceUi(on, on ? 'available' : 'offline');
        toggle.dataset.busy = '1';
        try {
            await window.setCallQueueAvailable(on);
        } catch (error) {
            applyCallQueuePresenceUi(!on, !on ? 'offline' : 'available');
            alert(error.message || 'Failed to update availability');
        } finally {
            toggle.dataset.busy = '0';
        }
    });
}

// Initialize Twilio Device globally (one instance per page so inbound calls work everywhere)
async function initializeGlobalTwilioDevice() {
    if (globalTwilioDevice) {
        return globalTwilioDevice;
    }
    if (window.globalTwilioDevice) {
        globalTwilioDevice = window.globalTwilioDevice;
        return globalTwilioDevice;
    }
    if (globalTwilioInitPromise) {
        return globalTwilioInitPromise;
    }

    globalTwilioInitPromise = (async () => {
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (!csrfToken) {
            console.error('❌ Global Twilio: No CSRF token found, skipping initialization');
            return null;
        }

        try {
            const response = await fetch('/twilio/capability-token', {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken.getAttribute('content') || '',
                    'Accept': 'application/json',
                }
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                window.__twilioDeviceInitError = errorData.message
                    || `Browser calling unavailable (HTTP ${response.status}).`;
                return null;
            }

            const data = await response.json();
            if (!data.success || !data.token) {
                window.__twilioDeviceInitError = data.message
                    || 'Browser calling is not configured.';
                return null;
            }
            window.__twilioDeviceInitError = null;

            const waitForSdk = typeof window.whenTwilioVoiceSdkReady === 'function'
                ? window.whenTwilioVoiceSdkReady(20000)
                : new Promise((resolve, reject) => {
                    const started = Date.now();
                    const timer = setInterval(() => {
                        const Device = window.TwilioVoiceSDK?.Device || window.Twilio?.Device || null;
                        if (Device) {
                            clearInterval(timer);
                            resolve(Device);
                        } else if (Date.now() - started >= 20000) {
                            clearInterval(timer);
                            reject(new Error('Twilio Voice SDK failed to load'));
                        }
                    }, 50);
                });

            const Device = await waitForSdk;
            setupGlobalTwilioDevice(data.token, Device);
            return globalTwilioDevice;
        } catch (error) {
            console.error('Error initializing global Twilio Device:', error);
            window.__twilioDeviceInitError = error.message || 'Browser calling failed to start.';
            return null;
        } finally {
            if (!globalTwilioDevice) {
                globalTwilioInitPromise = null;
            }
        }
    })();

    return globalTwilioInitPromise;
}

window.ensureGlobalTwilioDevice = initializeGlobalTwilioDevice;
window.whenGlobalTwilioDeviceReady = async function () {
    const device = await initializeGlobalTwilioDevice();
    return device || window.globalTwilioDevice || globalTwilioDevice || null;
};

function setupGlobalTwilioDevice(token, DeviceClass) {
    try {
        const Device = DeviceClass || window.TwilioVoiceSDK?.Device || window.Twilio?.Device;
        
        if (!Device) {
            throw new Error('Twilio Voice SDK not loaded');
        }

        // Create device with Voice SDK 2.x API
        globalTwilioDevice = new Device(token, {
            logLevel: 'info',
            codecPreferences: ['opus', 'pcmu']
        });
        window.globalTwilioDevice = globalTwilioDevice;
        window.dispatchEvent(new CustomEvent('lnscrm:twilio-device-ready', {
            detail: { device: globalTwilioDevice },
        }));

        // Device registered
        globalTwilioDevice.on('registered', () => {
            console.log('✅ Global Twilio Device registered - ready for incoming calls');
            // console.log('Device identity:', globalTwilioDevice.identity);
            // console.log('Device state:', globalTwilioDevice.state);
            // console.log('Current page:', window.location.pathname);
            
            // Verify notification element exists
            const notification = document.getElementById('inboundCallNotification');
            const ongoingNotification = document.getElementById('ongoingCallNotification');
            const header = document.querySelector('header');
            
            // console.log('🔍 Notification check:', {
            //     'inbound-notification': !!notification,
            //     'ongoing-notification': !!ongoingNotification,
            //     'header-exists': !!header,
            //     'page-path': window.location.pathname
            // });
            
            // if (notification) {
            //     console.log('✅ Inbound call notification element found');
            // } else {
            //     console.error('❌ Inbound call notification element NOT found!');
            //     console.error('Header HTML:', header ? header.innerHTML.substring(0, 200) : 'No header found');
            // }
            
            // if (ongoingNotification) {
            //     console.log('✅ Ongoing call notification element found');
            // } else {
            //     console.error('❌ Ongoing call notification element NOT found!');
            // }
        });

        globalTwilioDevice.on('error', (error) => {
            console.error('Global Twilio Device error:', error);
        });

        // Handle incoming calls - PRIMARY handler for all pages
        globalTwilioDevice.on('incoming', (call) => {
            // console.log('🔔🔔🔔 INCOMING CALL RECEIVED GLOBALLY 🔔🔔🔔');
            // console.log('📍 Current page:', window.location.pathname);
            // console.log('📞 Call object:', call);
            // console.log('📋 Call parameters:', call.parameters);
            // console.log('📞 Call from:', call.from);
            // console.log('🆔 Device identity:', globalTwilioDevice.identity);
            
            // Check if notification elements exist BEFORE handling
            const notification = document.getElementById('inboundCallNotification');
            const numberElement = document.getElementById('incomingCallNumber');
            
            // console.log('🔍 Pre-handle check:', {
            //     'notification-exists': !!notification,
            //     'number-element-exists': !!numberElement,
            //     'header-exists': !!document.querySelector('header'),
            //     'window-handleIncomingCall': typeof window.handleIncomingCall
            // });
            
            // Always handle via global handler - this works on ALL pages
            if (typeof window.handleIncomingCall === 'function') {
                // console.log('✅ Calling window.handleIncomingCall...');
                window.handleIncomingCall(call);
            } else {
                console.error('❌ window.handleIncomingCall is not a function!');
                // Fallback: handle directly
                handleIncomingCall(call);
            }
        });

        // Also listen for tokenWillExpire to refresh token
        globalTwilioDevice.on('tokenWillExpire', async () => {
            // console.log('Token will expire, refreshing...');
            try {
                const response = await fetch('/twilio/capability-token', {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'Accept': 'application/json',
                    }
                });
                const data = await response.json();
                if (data.success && data.token) {
                    globalTwilioDevice.updateToken(data.token);
                    // console.log('Token refreshed successfully');
                }
            } catch (error) {
                console.error('Error refreshing token:', error);
            }
        });

        // Register the device
        globalTwilioDevice.register();
        
    } catch (error) {
        console.error('Error setting up global Twilio Device:', error);
    }
}

// Make handleIncomingCall globally accessible - works on ALL pages (FULL VERSION - overwrites early version)
// console.log('🔧 Defining window.handleIncomingCall (full version - overwrites early)...');
window.handleIncomingCall = function(call) {
    // console.log('🔔🔔🔔 handleIncomingCall EXECUTED 🔔🔔🔔');
    // console.log('📍 Current page:', window.location.pathname);
    // console.log('📞 Call object:', call);
    
    // Store the call globally
    globalActiveCall = call;
    if (typeof window !== 'undefined') {
        window.globalActiveCall = call;
        // Also store in a way that's accessible from inline handlers
        window.__twilioActiveCall = call;
    }
    
    // Get caller information - for inbound calls, From will be the external caller's number
    const callerNumber = call.parameters?.From || call.parameters?.Caller || call.from || 'Unknown';
    
    // console.log('📞 Caller number:', callerNumber);
    // console.log('📋 Call parameters:', call.parameters);
    
    // Show notification - this should work on ANY page with the header
    // console.log('🔔 About to show notification on page:', window.location.pathname);
    
    // Force show notification with detailed logging
    const notification = document.getElementById('inboundCallNotification');
    const numberElement = document.getElementById('incomingCallNumber');
    
    // console.log('🔍 Direct element check in handleIncomingCall:', {
    //     'notification': notification,
    //     'numberElement': numberElement,
    //     'notification-display': notification ? window.getComputedStyle(notification).display : 'N/A',
    //     'notification-exists': !!notification,
    //     'numberElement-exists': !!numberElement
    // });
    
    if (notification && numberElement) {
        numberElement.textContent = callerNumber;
        notification.style.display = 'block';
        notification.style.visibility = 'visible';
        notification.style.opacity = '1';
        // console.log('✅✅✅ NOTIFICATION FORCED TO SHOW ✅✅✅');
        // console.log('Notification style after:', window.getComputedStyle(notification).display);
    } else {
        console.error('❌❌❌ CANNOT SHOW NOTIFICATION - ELEMENTS MISSING ❌❌❌');
        console.error('Trying to find header...');
        const header = document.querySelector('header');
        console.error('Header found:', !!header);
        if (header) {
            console.error('Header HTML (first 500 chars):', header.innerHTML.substring(0, 500));
        }
    }
    
    // Also call the function
    showIncomingCallNotification(callerNumber);
    
    // Play ring sound
    playGlobalRingSound();
    
    // Set up call event handlers
    call.on('cancel', () => {
        console.log('Incoming call canceled');
        hideIncomingCallNotification();
        // Only hide ongoing notification if call was NOT answered
        // When call is answered, Twilio fires 'cancel' but the call is still active
        if (!window.isCallAnswered) {
            hideOngoingCallNotification();
            stopCallDurationTimer();
            globalActiveCall = null;
            if (typeof window !== 'undefined') {
                window.globalActiveCall = null;
            }
        }
        stopGlobalRingSound();
    });
    
    call.on('disconnect', () => {
        console.log('Incoming call disconnected');
        hideIncomingCallNotification();
        hideOngoingCallNotification();
        stopGlobalRingSound();
        stopCallDurationTimer();
        window.isCallAnswered = false;
        globalActiveCall = null;
        if (typeof window !== 'undefined') {
            window.globalActiveCall = null;
        }
    });
    
    call.on('reject', () => {
        console.log('Incoming call rejected');
        hideIncomingCallNotification();
        hideOngoingCallNotification();
        stopGlobalRingSound();
        stopCallDurationTimer();
        window.isCallAnswered = false;
        globalActiveCall = null;
        if (typeof window !== 'undefined') {
            window.globalActiveCall = null;
        }
    });
};

function showIncomingCallNotification(callerNumber) {
    // console.log('🔔 showIncomingCallNotification FUNCTION CALLED');
    // console.log('📞 Caller number:', callerNumber);
    // console.log('📍 Current page:', window.location.pathname);
    
    // Try multiple ways to find the elements
    let notification = document.getElementById('inboundCallNotification');
    let numberElement = document.getElementById('incomingCallNumber');
    
    // If not found, try querySelector
    if (!notification) {
        notification = document.querySelector('#inboundCallNotification');
    }
    if (!numberElement) {
        numberElement = document.querySelector('#incomingCallNumber');
    }
    
    // console.log('🔍 Element search results:', {
    //     'notification-by-id': !!document.getElementById('inboundCallNotification'),
    //     'notification-by-query': !!document.querySelector('#inboundCallNotification'),
    //     'notification-found': !!notification,
    //     'numberElement-found': !!numberElement,
    //     'header-exists': !!document.querySelector('header'),
    //     'all-elements-with-id': Array.from(document.querySelectorAll('[id]')).map(el => el.id)
    // });
    
    if (notification && numberElement) {
        numberElement.textContent = callerNumber;
        
        // Force show with multiple methods
        notification.style.display = 'block';
        notification.style.visibility = 'visible';
        notification.style.opacity = '1';
        notification.removeAttribute('hidden');
        notification.classList.remove('hidden');
        
        // Verify it's actually visible
        const computedStyle = window.getComputedStyle(notification);
        // console.log('✅ Notification set to show');
        // console.log('Computed display:', computedStyle.display);
        // console.log('Computed visibility:', computedStyle.visibility);
        // console.log('Computed opacity:', computedStyle.opacity);
        
        if (computedStyle.display === 'none') {
            console.error('⚠️ WARNING: Notification display is still "none" after setting!');
            // Force with !important via style attribute
            notification.setAttribute('style', 'display: block !important; visibility: visible !important; opacity: 1 !important;');
        }
        
        // console.log('✅✅✅ NOTIFICATION SHOULD BE VISIBLE NOW ✅✅✅');
    } else {
        // console.error('❌❌❌ CRITICAL: Notification elements not found!');
        console.error('Page:', window.location.pathname);
        console.error('All notification-related elements:', document.querySelectorAll('[id*="call"], [id*="Call"], [id*="notification"], [id*="Notification"]'));
        
        // Try to create a temporary alert as fallback
        alert('Incoming call from: ' + callerNumber + '\n\n(Notification element not found on this page)');
    }
}

function hideIncomingCallNotification() {
    const notification = document.getElementById('inboundCallNotification');
    if (notification) {
        notification.style.display = 'none';
    }
}

function showOngoingCallNotification(call) {
    // console.log('showOngoingCallNotification called', call);
    
    const notification = document.getElementById('ongoingCallNotification');
    const numberElement = document.getElementById('ongoingCallNumber');
    const durationElement = document.getElementById('callDuration');
    
    // console.log('Ongoing notification elements:', {
    //     notification: !!notification,
    //     numberElement: !!numberElement,
    //     durationElement: !!durationElement
    // });
    
    if (notification && numberElement) {
        // Get caller information
        const callerNumber = call.parameters?.From || call.parameters?.Caller || call.from || 'Unknown';
        numberElement.textContent = callerNumber;
        
        // Set initial duration
        if (durationElement) {
            durationElement.textContent = '00:00';
        }
        
        // Show notification
        notification.style.display = 'block';
        
        // Start call duration timer
        callStartTime = Date.now();
        if (typeof window !== 'undefined') {
            window.callStartTime = callStartTime;
        }
        startCallDurationTimer();
        
        // console.log('✅ Ongoing call notification shown');
    } else {
        console.error('❌ Ongoing call notification elements not found!', {
            notification: !!notification,
            numberElement: !!numberElement
        });
    }
}

function hideOngoingCallNotification() {
    const notification = document.getElementById('ongoingCallNotification');
    if (notification) {
        notification.style.display = 'none';
    }
    stopCallDurationTimer();
}

function startCallDurationTimer() {
    // Clear any existing timer
    stopCallDurationTimer();
    
    // Update immediately
    updateCallDuration();
    
    // Update every second
    callDurationInterval = setInterval(() => {
        updateCallDuration();
    }, 1000);
    
    // Also store in window for inline script access
    if (typeof window !== 'undefined') {
        window.callDurationInterval = callDurationInterval;
    }
}

function stopCallDurationTimer() {
    if (callDurationInterval) {
        clearInterval(callDurationInterval);
        callDurationInterval = null;
    }
    if (typeof window !== 'undefined' && window.callDurationInterval) {
        clearInterval(window.callDurationInterval);
        window.callDurationInterval = null;
    }
    callStartTime = null;
    if (typeof window !== 'undefined') {
        window.callStartTime = null;
    }
}

function updateCallDuration() {
    // Check both local and window callStartTime
    const startTime = callStartTime || (typeof window !== 'undefined' ? window.callStartTime : null);
    if (!startTime) return;
    
    const durationElement = document.getElementById('callDuration');
    if (!durationElement) return;
    
    const elapsed = Math.floor((Date.now() - startTime) / 1000); // seconds
    const minutes = Math.floor(elapsed / 60);
    const seconds = elapsed % 60;
    
    const formatted = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    durationElement.textContent = formatted;
}

// Hangup ongoing call
window.hangupOngoingCall = function() {
    const activeCall = globalActiveCall || (typeof window !== 'undefined' ? window.globalActiveCall : null);
    
    if (!activeCall) {
        console.error('No active call to hangup');
        window.isCallAnswered = false;
        return;
    }
    
    try {
        if (typeof activeCall.disconnect === 'function') {
            activeCall.disconnect();
            console.log('Call hung up');
        } else {
            console.warn('Call object does not have disconnect method');
        }
        
        hideOngoingCallNotification();
        window.isCallAnswered = false;
        globalActiveCall = null;
        if (typeof window !== 'undefined') {
            window.globalActiveCall = null;
            window.__twilioActiveCall = null;
        }
    } catch (error) {
        console.error('Error hanging up call:', error);
        hideOngoingCallNotification();
        window.isCallAnswered = false;
        globalActiveCall = null;
        if (typeof window !== 'undefined') {
            window.globalActiveCall = null;
            window.__twilioActiveCall = null;
        }
    }
};

// Answer incoming call - update the function to use full implementation
window.answerIncomingCall = async function() {
    // Check both globalActiveCall and window.globalActiveCall
    const activeCall = globalActiveCall || (typeof window !== 'undefined' ? window.globalActiveCall : null);
    
    if (!activeCall) {
        console.error('No active call to answer');
        return;
    }

    try {
        // Request microphone permission
        await navigator.mediaDevices.getUserMedia({ audio: true });
        
        // Mark call as answered BEFORE accept() so cancel event doesn't hide ongoing notification
        window.isCallAnswered = true;
        
        // Answer the call
        activeCall.accept();
        
        console.log('Call answered');
        if (callQueueAvailable) {
            sendCallQueueHeartbeat();
        }
        hideIncomingCallNotification();
        stopGlobalRingSound();
        
        // Update global references
        globalActiveCall = activeCall;
        if (typeof window !== 'undefined') {
            window.globalActiveCall = activeCall;
            window.__twilioActiveCall = activeCall;
        }
        
        // Show ongoing call notification
        console.log('Showing ongoing call notification...');
        showOngoingCallNotification(activeCall);
        
        // Set up call disconnect handler
        activeCall.on('disconnect', () => {
            console.log('Call ended');
            hideOngoingCallNotification();
            stopCallDurationTimer();
            window.isCallAnswered = false;
            globalActiveCall = null;
            if (typeof window !== 'undefined') {
                window.globalActiveCall = null;
                window.__twilioActiveCall = null;
            }
            if (callQueueAvailable) {
                window.setCallQueueAvailable(true).catch(() => {});
            }
        });
        
    } catch (error) {
        console.error('Error answering call:', error);
        if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
            alert('Microphone permission is required to answer calls. Please allow microphone access.');
        } else {
            alert('Failed to answer call: ' + error.message);
        }
    }
};

// Decline incoming call - update the function to use full implementation
window.declineIncomingCall = function() {
    // Check both globalActiveCall and window.globalActiveCall
    const activeCall = globalActiveCall || (typeof window !== 'undefined' ? window.globalActiveCall : null);
    
    if (!activeCall) {
        console.error('No active call to decline');
        return;
    }

    try {
        // Disconnect/end the call - try disconnect first, then reject as fallback
        if (typeof activeCall.disconnect === 'function') {
            activeCall.disconnect();
            console.log('Call declined and disconnected');
        } else if (typeof activeCall.reject === 'function') {
            activeCall.reject();
            console.log('Call declined (rejected)');
        } else {
            console.warn('Call object does not have disconnect or reject method');
        }
        
        hideIncomingCallNotification();
        hideOngoingCallNotification();
        stopGlobalRingSound();
        globalActiveCall = null;
        if (typeof window !== 'undefined') {
            window.globalActiveCall = null;
            window.__twilioActiveCall = null;
        }
    } catch (error) {
        console.error('Error declining call:', error);
        hideIncomingCallNotification();
        hideOngoingCallNotification();
        stopGlobalRingSound();
        globalActiveCall = null;
        if (typeof window !== 'undefined') {
            window.globalActiveCall = null;
            window.__twilioActiveCall = null;
        }
    }
};

// Ring sound functions
function playGlobalRingSound() {
    stopGlobalRingSound();
    
    if (isGlobalRinging) return;
    isGlobalRinging = true;
    
    try {
        globalRingAudioContext = globalRingAudioContext || new (window.AudioContext || window.webkitAudioContext)();
        
        if (globalRingAudioContext.state === 'suspended') {
            globalRingAudioContext.resume();
        }
        
        function playRingTone() {
            if (!isGlobalRinging) return;
            
            // First ring
            globalRingOscillator = globalRingAudioContext.createOscillator();
            globalRingGainNode = globalRingAudioContext.createGain();
            
            globalRingOscillator.connect(globalRingGainNode);
            globalRingGainNode.connect(globalRingAudioContext.destination);
            
            globalRingOscillator.frequency.value = 440;
            globalRingOscillator.type = 'sine';
            
            const now = globalRingAudioContext.currentTime;
            globalRingGainNode.gain.setValueAtTime(0, now);
            globalRingGainNode.gain.linearRampToValueAtTime(0.5, now + 0.05);
            globalRingGainNode.gain.setValueAtTime(0.5, now + 0.35);
            globalRingGainNode.gain.linearRampToValueAtTime(0, now + 0.4);
            
            globalRingOscillator.start(now);
            globalRingOscillator.stop(now + 0.4);
            
            // Second ring after pause
            setTimeout(() => {
                if (!isGlobalRinging) return;
                
                const osc2 = globalRingAudioContext.createOscillator();
                const gain2 = globalRingAudioContext.createGain();
                
                osc2.connect(gain2);
                gain2.connect(globalRingAudioContext.destination);
                
                osc2.frequency.value = 440;
                osc2.type = 'sine';
                
                const now2 = globalRingAudioContext.currentTime;
                gain2.gain.setValueAtTime(0, now2);
                gain2.gain.linearRampToValueAtTime(0.5, now2 + 0.05);
                gain2.gain.setValueAtTime(0.5, now2 + 0.35);
                gain2.gain.linearRampToValueAtTime(0, now2 + 0.4);
                
                osc2.start(now2);
                osc2.stop(now2 + 0.4);
            }, 600);
        }
        
        playRingTone();
        
        globalRingInterval = setInterval(() => {
            if (isGlobalRinging) {
                playRingTone();
            } else {
                stopGlobalRingSound();
            }
        }, 4000);
        
    } catch (e) {
        console.error('Could not play ring sound:', e);
        isGlobalRinging = false;
    }
}

function stopGlobalRingSound() {
    isGlobalRinging = false;
    
    if (globalRingInterval) {
        clearInterval(globalRingInterval);
        globalRingInterval = null;
    }
    
    if (globalRingOscillator) {
        try {
            globalRingOscillator.stop();
        } catch (e) {
            // Oscillator might already be stopped
        }
        globalRingOscillator = null;
    }
    
    if (globalRingGainNode) {
        try {
            globalRingGainNode.disconnect();
        } catch (e) {
            // Already disconnected
        }
        globalRingGainNode = null;
    }
}

// Test function to manually test notification (for debugging)
window.testIncomingCallNotification = function() {
    console.log('🧪 TEST: Testing notification display...');
    const notification = document.getElementById('inboundCallNotification');
    const numberElement = document.getElementById('incomingCallNumber');
    
    if (notification && numberElement) {
        numberElement.textContent = '+1234567890';
        notification.style.display = 'block';
        notification.style.visibility = 'visible';
        notification.style.opacity = '1';
        console.log('✅ TEST: Notification should be visible now');
        alert('Test notification shown! Check the header.');
    } else {
        console.error('❌ TEST: Notification elements not found!');
        alert('ERROR: Notification elements not found on this page!');
    }
};

// Verify functions are defined
console.log('✅ twilio-global.js: Functions defined:', {
    'handleIncomingCall': typeof window.handleIncomingCall,
    'answerIncomingCall': typeof window.answerIncomingCall,
    'declineIncomingCall': typeof window.declineIncomingCall,
    'hangupOngoingCall': typeof window.hangupOngoingCall,
    'testIncomingCallNotification': typeof window.testIncomingCallNotification
});

function startGlobalCallListener() {
    bindHeaderAgentQueueToggle();
    bootstrapCallQueuePresence();
    initializeGlobalTwilioDevice();
    document.addEventListener('click', () => {
        if (globalRingAudioContext?.state === 'suspended') {
            globalRingAudioContext.resume().catch(() => {});
        }
    }, { passive: true });
}

// Initialize when DOM is ready - ensure it runs on ALL pages
console.log('🔧 Setting up initialization handlers...');
console.log('📄 Document readyState:', document.readyState);

if (document.readyState === 'loading') {
    console.log('⏳ Document still loading, waiting for DOMContentLoaded...');
    document.addEventListener('DOMContentLoaded', function() {
        console.log('📄✅ DOMContentLoaded fired - initializing global Twilio device');
        console.log('📍 Page:', window.location.pathname);
        try {
            startGlobalCallListener();
        } catch (error) {
            console.error('❌ Error in DOMContentLoaded handler:', error);
        }
    });
} else {
    // DOM already loaded, initialize immediately
    console.log('📄✅ DOM already loaded - initializing global Twilio device immediately');
    console.log('📍 Page:', window.location.pathname);
    try {
        startGlobalCallListener();
    } catch (error) {
        console.error('❌ Error in immediate initialization:', error);
    }
}

document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && callQueueAvailable) {
        sendCallQueueHeartbeat();
        initializeGlobalTwilioDevice();
    }
});

window.addEventListener('pagehide', () => {
    if (!callQueueAvailable) return;
    try {
        fetch('/twilio/agent-presence/heartbeat', {
            method: 'POST',
            headers: csrfHeaders(),
            keepalive: true,
        });
    } catch (error) {
        // Ignore navigation teardown errors.
    }
});

window.addEventListener('storage', (event) => {
    if (event.key !== CALL_QUEUE_STORAGE_KEY) return;
    const on = event.newValue === '1';
    window.syncCallQueuePresence(on, on ? 'available' : 'offline');
});

// Also try to initialize after a short delay to ensure everything is loaded
setTimeout(() => {
    console.log('⏰ 1s timeout check - globalTwilioDevice exists?', !!globalTwilioDevice);
    if (!globalTwilioDevice) {
        console.log('⏰ Global Twilio: Retrying initialization after 1s delay...');
        try {
            initializeGlobalTwilioDevice();
        } catch (error) {
            console.error('❌ Error in 1s retry:', error);
        }
    } else {
        console.log('✅ Global Twilio device already initialized');
    }
}, 1000);

// One more retry after 3 seconds
setTimeout(() => {
    console.log('⏰ 3s timeout check - globalTwilioDevice exists?', !!globalTwilioDevice);
    if (!globalTwilioDevice) {
        console.log('⏰ Global Twilio: Final retry after 3s delay...');
        try {
            initializeGlobalTwilioDevice();
        } catch (error) {
            console.error('❌ Error in 3s retry:', error);
        }
    } else {
        console.log('✅ Global Twilio device already initialized');
    }
}, 3000);

// Log that script execution completed
console.log('✅ twilio-global.js script execution completed');
console.log('📍 Final page:', window.location.pathname);
