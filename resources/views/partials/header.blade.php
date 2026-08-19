<!-- Header -->
<header class="header">
    <div class="header-left">
        <button class="mobile-menu-btn" onclick="toggleMobileSidebar()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
        <div class="search-box">
            <div class="search-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="m21 21-4.35-4.35"/>
                </svg>
            </div>
            <input type="text" class="search-input" placeholder="Search...">
        </div>
    </div>

    <div class="header-right">
        <!-- Inbound Call Notification -->
        <script>
            // DEBUG: Verify header script is loading
            // console.log('📋📋📋 HEADER SCRIPT LOADED 📋📋📋');
            // console.log('📍 Current page:', window.location.pathname);
            // console.log('⏰ Load time:', new Date().toISOString());
            
            // Ensure functions are available immediately (before scripts load)
            if (typeof window.answerIncomingCall === 'undefined') {
                window.answerIncomingCall = async function() {
                    const call = window.globalActiveCall || window.__twilioActiveCall;
                    if (!call) {
                        console.error('No active call to answer');
                        return;
                    }
                    try {
                        await navigator.mediaDevices.getUserMedia({ audio: true });
                        
                        // Mark call as answered BEFORE accept() so cancel event doesn't hide ongoing notification
                        window.isCallAnswered = true;
                        
                        call.accept();
                        
                        // Hide incoming notification
                        const incomingNotification = document.getElementById('inboundCallNotification');
                        if (incomingNotification) incomingNotification.style.display = 'none';
                        
                        // Show ongoing call notification
                        const ongoingNotification = document.getElementById('ongoingCallNotification');
                        const ongoingNumber = document.getElementById('ongoingCallNumber');
                        const callDuration = document.getElementById('callDuration');
                        
                        if (ongoingNotification && ongoingNumber) {
                            const callerNumber = call.parameters?.From || call.parameters?.Caller || call.from || 'Unknown';
                            ongoingNumber.textContent = callerNumber;
                            if (callDuration) callDuration.textContent = '00:00';
                            ongoingNotification.style.display = 'block';
                            
                            // Start call duration timer
                            if (!window.callStartTime) {
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
                        }
                        
                        console.log('Call answered');
                    } catch (error) {
                        console.error('Error answering call:', error);
                        alert('Failed to answer call: ' + error.message);
                    }
                };
            }
            
            if (typeof window.declineIncomingCall === 'undefined') {
                window.declineIncomingCall = function() {
                    const call = window.globalActiveCall || window.__twilioActiveCall;
                    if (!call) {
                        console.error('No active call to decline');
                        return;
                    }
                    try {
                        // Disconnect/end the call
                        if (call.disconnect) {
                            call.disconnect();
                        } else if (call.reject) {
                            call.reject();
                        }
                        const notification = document.getElementById('inboundCallNotification');
                        if (notification) notification.style.display = 'none';
                        window.globalActiveCall = null;
                        window.__twilioActiveCall = null;
                        console.log('Call declined and ended');
                    } catch (error) {
                        console.error('Error declining call:', error);
                        const notification = document.getElementById('inboundCallNotification');
                        if (notification) notification.style.display = 'none';
                        window.globalActiveCall = null;
                        window.__twilioActiveCall = null;
                    }
                };
            }
            
            // Hangup ongoing call function
            if (typeof window.hangupOngoingCall === 'undefined') {
                window.hangupOngoingCall = function() {
                    const call = window.globalActiveCall || window.__twilioActiveCall;
                    if (!call) {
                        console.error('No active call to hangup');
                        return;
                    }
                    try {
                        if (call.disconnect) {
                            call.disconnect();
                        }
                        const notification = document.getElementById('ongoingCallNotification');
                        if (notification) notification.style.display = 'none';
                        window.globalActiveCall = null;
                        window.__twilioActiveCall = null;
                        console.log('Call hung up');
                    } catch (error) {
                        console.error('Error hanging up call:', error);
                        const notification = document.getElementById('ongoingCallNotification');
                        if (notification) notification.style.display = 'none';
                        window.globalActiveCall = null;
                        window.__twilioActiveCall = null;
                    }
                };
            }
        </script>
        <div id="inboundCallNotification" class="inbound-call-notification" style="display: none;">
            <div class="call-notification-content">
                <div class="call-notification-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                </div>
                <div class="call-notification-info">
                    <div class="call-notification-title">Incoming Call</div>
                    <div class="call-notification-number" id="incomingCallNumber">Unknown</div>
                </div>
                <div class="call-notification-actions">
                    <button class="call-btn call-btn-answer" id="answerCallBtn" onclick="answerIncomingCall()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                    </button>
                    <button class="call-btn call-btn-decline" id="declineCallBtn" onclick="declineIncomingCall()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Ongoing Call Notification -->
        <div id="ongoingCallNotification" class="ongoing-call-notification" style="display: none;">
            <div class="call-notification-content">
                <div class="call-notification-icon ongoing">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                </div>
                <div class="call-notification-info">
                    <div class="call-notification-title">Ongoing Call</div>
                    <div class="call-notification-number" id="ongoingCallNumber">Unknown</div>
                    <div class="call-duration" id="callDuration">00:00</div>
                </div>
                <div class="call-notification-actions">
                    <button class="call-btn call-btn-hangup" id="hangupCallBtn" onclick="hangupOngoingCall()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        @if(auth()->user()?->hasPermission('view_phone_system'))
        @php
            $headerQueuePresence = \App\Models\CallAgentPresence::query()->where('user_id', auth()->id())->first();
            $headerQueueStatus = $headerQueuePresence?->status ?? 'offline';
            $headerQueueOn = in_array($headerQueueStatus, ['available', 'busy'], true);
            $headerQueueLabel = $headerQueueStatus === 'busy' ? 'On call' : ($headerQueueOn ? 'Available' : 'Offline');
        @endphp
        <div class="header-agent-queue" id="headerAgentQueue" title="Receive inbound calls on any CRM page while available">
            <button type="button"
                class="header-agent-queue-toggle"
                id="headerAgentAvailableToggle"
                role="switch"
                aria-checked="{{ $headerQueueOn ? 'true' : 'false' }}"
                aria-label="Available for inbound calls">
                <span class="agent-queue-toggle-ui"></span>
                <span class="agent-queue-toggle-label" id="headerAgentAvailableLabel">{{ $headerQueueLabel }}</span>
            </button>
        </div>
        <script>
            (function () {
                const toggle = document.getElementById('headerAgentAvailableToggle');
                if (!toggle) return;

                const storageKey = 'lnscrm.callQueueAvailable';
                const csrfHeaders = function () {
                    return {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    };
                };

                const persist = function (on) {
                    try { localStorage.setItem(storageKey, on ? '1' : '0'); } catch (e) {}
                };

                const readPersisted = function () {
                    try { return localStorage.getItem(storageKey) === '1'; } catch (e) { return false; }
                };

                const setUi = function (on, status) {
                    toggle.setAttribute('aria-checked', on ? 'true' : 'false');
                    const label = document.getElementById('headerAgentAvailableLabel');
                    if (label) {
                        label.textContent = status === 'busy' ? 'On call' : (on ? 'Available' : 'Offline');
                    }
                    const phoneToggle = document.getElementById('agentAvailableToggle');
                    if (phoneToggle) phoneToggle.checked = !!on;
                    const phoneLabel = document.getElementById('agentAvailableLabel');
                    if (phoneLabel) {
                        phoneLabel.textContent = status === 'busy' ? 'On call' : (on ? 'Available' : 'Offline');
                    }
                };

                const stopHeartbeat = function () {
                    if (window.__headerQueueHeartbeat) {
                        clearInterval(window.__headerQueueHeartbeat);
                        window.__headerQueueHeartbeat = null;
                    }
                };

                const startHeartbeat = function () {
                    stopHeartbeat();
                    const beat = function () {
                        fetch('/twilio/agent-presence/heartbeat', {
                            method: 'POST',
                            headers: csrfHeaders(),
                            keepalive: true,
                        }).catch(function () {});
                    };
                    beat();
                    window.__headerQueueHeartbeat = setInterval(beat, 20000);
                };

                const setAvailable = async function (on) {
                    if (typeof window.setCallQueueAvailable === 'function') {
                        await window.setCallQueueAvailable(on);
                        persist(on);
                        if (on) startHeartbeat();
                        else stopHeartbeat();
                        return;
                    }
                    const response = await fetch('/twilio/agent-presence', {
                        method: 'POST',
                        headers: csrfHeaders(),
                        body: JSON.stringify({ status: on ? 'available' : 'offline' }),
                    });
                    const data = await response.json().catch(function () { return {}; });
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Failed to update availability');
                    }
                    persist(on);
                    if (on) startHeartbeat();
                    else stopHeartbeat();
                };

                const restorePresence = async function () {
                    const persistedOn = readPersisted();
                    if (persistedOn || toggle.getAttribute('aria-checked') === 'true') {
                        setUi(true);
                        startHeartbeat();
                    }

                    try {
                        const response = await fetch('/twilio/agent-presence', {
                            method: 'GET',
                            headers: csrfHeaders(),
                        });
                        if (!response.ok) {
                            if (persistedOn) await setAvailable(true);
                            return;
                        }
                        const data = await response.json();
                        const status = data?.data?.me?.status;
                        const serverOn = status === 'available' || status === 'busy';

                        if (persistedOn && !serverOn) {
                            await setAvailable(true);
                            setUi(true, 'available');
                            return;
                        }

                        setUi(serverOn, status);
                        persist(serverOn);
                        if (serverOn) startHeartbeat();
                        else stopHeartbeat();
                    } catch (e) {
                        if (persistedOn) {
                            try { await setAvailable(true); } catch (err) {}
                        }
                    }
                };

                if (toggle.dataset.bound !== '1') {
                    toggle.dataset.bound = '1';
                    toggle.addEventListener('click', async function () {
                        if (toggle.dataset.busy === '1') return;
                        const on = toggle.getAttribute('aria-checked') !== 'true';
                        setUi(on);
                        persist(on);
                        toggle.dataset.busy = '1';
                        try {
                            await setAvailable(on);
                        } catch (error) {
                            setUi(!on);
                            persist(!on);
                            alert(error.message || 'Failed to update availability');
                        } finally {
                            toggle.dataset.busy = '0';
                        }
                    });
                }

                restorePresence();

                window.addEventListener('pagehide', function () {
                    if (toggle.getAttribute('aria-checked') !== 'true') return;
                    fetch('/twilio/agent-presence/heartbeat', {
                        method: 'POST',
                        headers: csrfHeaders(),
                        keepalive: true,
                    }).catch(function () {});
                });
            })();
        </script>
        @endif

        @if(auth()->user()?->hasPermission('view_messaging'))
        <a href="{{ route('messaging') }}" class="header-icon-btn" id="headerMessagingBtn" title="Messaging">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            <span class="notification-badge" id="headerMessagingBadge" style="display: none;"></span>
        </a>
        @endif

        <div class="header-notifications" id="headerNotifications">
            <button type="button" class="header-icon-btn" id="headerNotificationsBtn" title="Notifications" aria-expanded="false" aria-haspopup="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <span class="notification-badge" id="headerNotificationsBadge" style="display: none;"></span>
            </button>
            <div class="header-notifications-dropdown" id="headerNotificationsDropdown" hidden>
                <div class="header-notifications-head">
                    <strong>Notifications</strong>
                    <button type="button" class="header-notifications-markall" id="headerNotificationsMarkAll">Mark all read</button>
                </div>
                <div class="header-notifications-list" id="headerNotificationsList">
                    <div class="header-notifications-empty">No notifications yet</div>
                </div>
            </div>
        </div>

        <div class="user-menu" id="userMenu">
            @auth
                @php
                    $user = auth()->user();
                    $userName = $user->name ?? 'User';
                    $userEmail = $user->email ?? '';
                    $userPhoto = public_media_url($user->photo);
                    $userInitials = '';
                    if ($userName) {
                        $words = explode(' ', $userName);
                        $userInitials = '';
                        foreach ($words as $word) {
                            if ($word) {
                                $userInitials .= strtoupper($word[0]);
                            }
                        }
                        $userInitials = substr($userInitials, 0, 2);
                    }
                    $userRole = $user->role ? $user->role->name : ($user->isAdmin() ? 'Administrator' : 'Employee');
                @endphp
                <button class="user-trigger" onclick="toggleUserMenu()">
                    @if($userPhoto)
                        <div class="user-avatar" style="background-image: url('{{ $userPhoto }}'); background-size: cover; background-position: center; overflow: hidden;">
                        </div>
                    @else
                        <div class="user-avatar">{{ $userInitials }}</div>
                    @endif
                    <div class="user-info">
                    </div>
                    <svg class="user-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </button>

                <div class="dropdown-menu">
                    <div class="dropdown-header">
                        <div class="dropdown-header-name">{{ $userName }}</div>
                        <div class="dropdown-header-email">{{ $userEmail }}</div>
                    </div>
                <a href="#" class="dropdown-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Profile
                </a>
                <a href="#" class="dropdown-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M12 1v6m0 6v6M5.64 5.64l4.24 4.24m4.24 4.24l4.24 4.24M1 12h6m6 0h6M5.64 18.36l4.24-4.24m4.24-4.24l4.24-4.24"/>
                    </svg>
                    Settings
                </a>
                <a href="{{ route('billing-plan') }}" class="dropdown-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                    Billing Plan
                </a>
                <a href="{{ route('e-signature') }}" class="dropdown-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                    E-Signature
                </a>
                <div class="dropdown-divider"></div>
                <form method="POST" action="{{ route('logout') }}" style="margin: 0; width: 100%;">
                    @csrf
                    <button type="submit" class="dropdown-item" style="width: 100%; text-align: left; background: none; border: none; padding: 0.75rem 1rem; cursor: pointer; font-family: inherit;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        Logout
                    </button>
                </form>
                </div>
            @endauth
        </div>
    </div>
</header>

