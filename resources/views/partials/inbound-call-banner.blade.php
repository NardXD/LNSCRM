{{-- Viewport overlay so incoming/ongoing call toasts are visible on every CRM page, not only /twilio/call. --}}
<div class="inbound-call-banner-layer" id="inboundCallBannerLayer" aria-live="polite">
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
                <div class="call-notification-lead" id="incomingCallLead"></div>
            </div>
            <div class="call-notification-actions">
                <button type="button" class="call-btn call-btn-answer" id="answerCallBtn" onclick="answerIncomingCall()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                </button>
                <button type="button" class="call-btn call-btn-decline" id="declineCallBtn" onclick="declineIncomingCall()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

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
                <div class="call-notification-lead" id="ongoingCallLead"></div>
                <div class="call-duration" id="callDuration">00:00</div>
            </div>
            <div class="call-notification-actions">
                <button type="button" class="call-btn call-btn-hangup" id="hangupCallBtn" onclick="hangupOngoingCall()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    window.__lnscrmCallBannerKey = 'lnscrm.activeCallBanner';

    window.__lnscrmReadCallBanner = function () {
        try {
            var state = JSON.parse(sessionStorage.getItem(window.__lnscrmCallBannerKey) || 'null');
            if (!state || (state.status !== 'ringing' && state.status !== 'answered')) {
                return null;
            }
            var ageMs = Date.now() - Number(state.updatedAt || 0);
            if (state.status === 'ringing' && ageMs > 15 * 60 * 1000) return null;
            if (state.status === 'answered' && ageMs > 6 * 60 * 60 * 1000) return null;
            return state;
        } catch (e) {
            return null;
        }
    };

    window.__lnscrmWriteCallBanner = function (state) {
        try {
            if (!state) {
                sessionStorage.removeItem(window.__lnscrmCallBannerKey);
                return;
            }
            state.updatedAt = Date.now();
            sessionStorage.setItem(window.__lnscrmCallBannerKey, JSON.stringify(state));
        } catch (e) {}
    };

    window.__lnscrmShowCallLead = function (phone) {
        var incomingLead = document.getElementById('incomingCallLead');
        var ongoingLead = document.getElementById('ongoingCallLead');
        var hide = function () {
            if (incomingLead) { incomingLead.textContent = ''; incomingLead.style.display = 'none'; }
            if (ongoingLead) { ongoingLead.textContent = ''; ongoingLead.style.display = 'none'; }
        };
        if (!phone || phone === 'Unknown') {
            hide();
            return;
        }
        var requestId = (window.__lnscrmCallLeadReq = (window.__lnscrmCallLeadReq || 0) + 1);
        fetch('/api/crm/contact-history?phone=' + encodeURIComponent(phone) + '&limit=5', {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        }).then(function (res) {
            return res.ok ? res.json() : null;
        }).then(function (data) {
            if (requestId !== window.__lnscrmCallLeadReq) return;
            var lead = data && data.contact && data.contact.lead;
            var assignee = lead && lead.assigned_user && lead.assigned_user.name;
            if (!lead || !assignee) {
                hide();
                return;
            }
            var names = (lead.labels || []).map(function (item) { return item && item.name; }).filter(Boolean);
            var label = (lead.name || 'Lead') + ' · Assigned to ' + assignee + (names.length ? ' · ' + names.join(', ') : '');
            [incomingLead, ongoingLead].forEach(function (el) {
                if (!el) return;
                el.textContent = label;
                el.style.display = 'block';
            });
        }).catch(function () {
            if (requestId !== window.__lnscrmCallLeadReq) return;
            hide();
        });
    };

    window.__lnscrmApplyCallBanner = function (state) {
        var incoming = document.getElementById('inboundCallNotification');
        var incomingNumber = document.getElementById('incomingCallNumber');
        var ongoing = document.getElementById('ongoingCallNotification');
        var ongoingNumber = document.getElementById('ongoingCallNumber');
        var durationEl = document.getElementById('callDuration');
        if (!state) {
            if (incoming) incoming.style.display = 'none';
            if (ongoing) ongoing.style.display = 'none';
            if (typeof window.__lnscrmShowCallLead === 'function') window.__lnscrmShowCallLead(null);
            return;
        }

        var from = state.from || 'Unknown';
        if (typeof window.__lnscrmShowCallLead === 'function') window.__lnscrmShowCallLead(from);
        if (state.status === 'answered') {
            window.isCallAnswered = true;
            if (incoming) incoming.style.display = 'none';
            if (ongoingNumber) ongoingNumber.textContent = from;
            if (ongoing) {
                ongoing.style.display = 'block';
                ongoing.style.visibility = 'visible';
                ongoing.style.opacity = '1';
            }
            if (state.startedAt) {
                window.callStartTime = Number(state.startedAt);
                if (durationEl) {
                    var elapsed = Math.max(0, Math.floor((Date.now() - window.callStartTime) / 1000));
                    durationEl.textContent = String(Math.floor(elapsed / 60)).padStart(2, '0')
                        + ':' + String(elapsed % 60).padStart(2, '0');
                }
            }
            return;
        }

        window.isCallAnswered = false;
        if (ongoing) ongoing.style.display = 'none';
        if (incomingNumber) incomingNumber.textContent = from;
        if (incoming) {
            incoming.style.display = 'block';
            incoming.style.visibility = 'visible';
            incoming.style.opacity = '1';
        }
    };

    var restored = window.__lnscrmReadCallBanner();
    if (restored) {
        window.__lnscrmBannerRestoreAt = Date.now();
        window.__lnscrmApplyCallBanner(restored);
    }
})();
</script>
