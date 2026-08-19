@extends('layouts.app')

@section('title', 'Twilio Call')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Phone System</h1>
        <p class="page-subtitle">Make calls using Twilio</p>
    </div>

    @include('twilio.partials.setup-tips')

    <div class="twilio-call-container">
        <div class="call-cards-row">
            <div class="call-card">
                <div class="call-card-header">
                    <h2 class="call-card-title">
                        Make a Call
                        @if(isset($twilioNumber) && $twilioNumber)
                            <span class="twilio-number-display">{{ $twilioNumber }}</span>
                        @endif
                    </h2>
                </div>
                <div class="call-card-body">
                    @if(isset($integrationError) && $integrationError)
                        <div class="twilio-error-alert">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            <span>{{ $integrationError }}</span>
                        </div>
                    @elseif(!isset($twilioNumber) || !$twilioNumber)
                        <div class="twilio-error-alert">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="8" x2="12" y2="12"/>
                                <line x1="12" y1="16" x2="12.01" y2="16"/>
                            </svg>
                            <span>A phone system number is required. Assign one in User Management or Phone System → Numbers to make outbound calls.</span>
                        </div>
                    @endif
                    <div class="form-group">
                        <label for="phoneNumber" class="form-label">Phone Number</label>
                        <div class="phone-input-group">
                            <input type="text" id="phoneNumber" class="form-input"  value="+" @if(!isset($hasIntegration) || !$hasIntegration || !isset($twilioNumber) || !$twilioNumber) disabled @endif>
                            <button type="button" class="btn-primary" id="makeCallBtn" onclick="makeCall()" @if(!isset($hasIntegration) || !$hasIntegration || !isset($twilioNumber) || !$twilioNumber) disabled @endif>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                </svg>
                                Make Call
                            </button>
                            <button type="button" class="btn-danger" id="hangupBtn" onclick="hangup()" style="display: none;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/>
                                    <line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                                Hang Up
                            </button>
                        </div>
                        <small class="form-hint">Enter phone number in E.164 format (e.g., +1234567890)</small>
                    </div>

                    <!-- Dial Pad (also sends DTMF during active calls) -->
                    <div class="dialpad-container">
                        <div class="dialpad">
                            @php
                                $isDisabled = !isset($hasIntegration) || !$hasIntegration || !isset($twilioNumber) || !$twilioNumber;
                            @endphp
                            <button type="button" class="dialpad-key" onclick="dialNumber('1', event)" @if($isDisabled) disabled @endif>
                                <span class="dialpad-number">1</span>
                            </button>
                            <button type="button" class="dialpad-key" onclick="dialNumber('2', event)" @if($isDisabled) disabled @endif>
                                <span class="dialpad-number">2</span>
                            </button>
                            <button type="button" class="dialpad-key" onclick="dialNumber('3', event)" @if($isDisabled) disabled @endif>
                                <span class="dialpad-number">3</span>
                            </button>
                            <button type="button" class="dialpad-key" onclick="dialNumber('4', event)" @if($isDisabled) disabled @endif>
                                <span class="dialpad-number">4</span>
                            </button>
                            <button type="button" class="dialpad-key" onclick="dialNumber('5', event)" @if($isDisabled) disabled @endif>
                                <span class="dialpad-number">5</span>
                            </button>
                            <button type="button" class="dialpad-key" onclick="dialNumber('6', event)" @if($isDisabled) disabled @endif>
                                <span class="dialpad-number">6</span>
                            </button>
                            <button type="button" class="dialpad-key" onclick="dialNumber('7', event)" @if($isDisabled) disabled @endif>
                                <span class="dialpad-number">7</span>
                            </button>
                            <button type="button" class="dialpad-key" onclick="dialNumber('8', event)" @if($isDisabled) disabled @endif>
                                <span class="dialpad-number">8</span>
                            </button>
                            <button type="button" class="dialpad-key" onclick="dialNumber('9', event)" @if($isDisabled) disabled @endif>
                                <span class="dialpad-number">9</span>
                            </button>
                            <button type="button" class="dialpad-key" onclick="dialNumber('*', event)" @if($isDisabled) disabled @endif>
                                <span class="dialpad-number">*</span>
                            </button>
                            <button type="button" class="dialpad-key" onclick="dialNumber('0', event)" @if($isDisabled) disabled @endif>
                                <span class="dialpad-number">0</span>
                            </button>
                            <button type="button" class="dialpad-key" onclick="dialNumber('#', event)" @if($isDisabled) disabled @endif>
                                <span class="dialpad-number">#</span>
                            </button>
                        </div>
                        <button type="button" class="btn-delete" onclick="deleteLastDigit()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"/>
                                <line x1="18" y1="9" x2="12" y2="15"/>
                                <line x1="12" y1="9" x2="18" y2="15"/>
                            </svg>
                        </button>
                        <small class="form-hint">During a call, keys send DTMF (e.g. "Press 1 for sales")</small>
                    </div>
                </div>
            </div>

            <!-- Phone system panel (tabs: Live, History, Contacts, Numbers) -->
            @include('twilio.partials.phone-panel')

            <div class="call-card phone-lead-card">
                <div class="call-card-header">
                    <h2 class="call-card-title">Lead</h2>
                </div>
                @include('partials.contact-history-panel', ['panelId' => 'phoneContactHistory'])
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .twilio-call-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        max-width: 1400px;
    }

    .call-cards-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(280px, 320px);
        gap: 1.5rem;
    }

    .call-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }

    .call-card {
        display: flex;
        flex-direction: column;
    }

    .call-card-header {
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border);
    }

    .call-card-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .twilio-number-display {
        font-size: 0.875rem;
        font-weight: 400;
        color: var(--text-secondary);
        padding: 0.25rem 0.75rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 6px;
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', 'source-code-pro', monospace;
    }

    .twilio-error-alert {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 1rem;
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 8px;
        color: #991b1b;
        font-size: 0.875rem;
        margin-bottom: 1rem;
    }

    .twilio-error-alert svg {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
        margin-top: 0.125rem;
    }

    .twilio-error-alert span {
        flex: 1;
        line-height: 1.5;
    }

    .call-card-body {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        flex: 1;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .phone-input-group {
        display: flex;
        gap: 0.75rem;
        align-items: stretch;
    }

    .form-input {
        flex: 1;
        padding: 0.75rem 1rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        transition: all 0.15s;
    }

    .form-input:disabled {
        background: var(--bg-primary);
        opacity: 0.6;
        cursor: not-allowed;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .phone-input-group .btn-primary {
        white-space: nowrap;
        padding: 0.75rem 1.5rem;
        flex-shrink: 0;
    }

    .form-hint {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    /* Dial Pad Styles */
    .dialpad-container {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        align-items: center;
    }

    .dialpad {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
        width: 100%;
        max-width: 300px;
    }

    .dialpad-key {
        aspect-ratio: 1;
        min-height: 60px;
        background: var(--bg-card);
        border: 2px solid var(--border);
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.15s;
        user-select: none;
        -webkit-tap-highlight-color: transparent;
    }

    .dialpad-key:hover {
        background: var(--bg-primary);
        border-color: var(--accent);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .dialpad-key:active {
        transform: translateY(0);
        background: var(--accent-light);
        border-color: var(--accent);
    }

    .dialpad-key:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    .dialpad-key:disabled:hover {
        background: var(--bg-card);
        transform: none;
        border-color: var(--border);
        box-shadow: none;
    }

    .dialpad-number {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text-primary);
        line-height: 1;
    }

    .dialpad-letters {
        font-size: 0.625rem;
        color: var(--text-muted);
        margin-top: 0.25rem;
        font-weight: 500;
        letter-spacing: 0.05em;
    }

    .btn-delete {
        width: 100%;
        max-width: 300px;
        padding: 0.75rem;
        background: var(--bg-primary);
        border: 2px solid var(--border);
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s;
    }

    .btn-delete:hover {
        background: #fee2e2;
        border-color: #dc2626;
    }

    .btn-delete:active {
        background: #fecaca;
    }

    .btn-delete svg {
        width: 20px;
        height: 20px;
        color: var(--text-secondary);
    }

    .btn-delete:hover svg {
        color: #dc2626;
    }

    .btn-primary, .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
        border: none;
        text-decoration: none;
    }

    .btn-primary {
        background: var(--accent);
        color: white;
    }

    .btn-primary:hover:not(:disabled) {
        background: var(--accent-hover);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }

    .btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .btn-secondary {
        background: var(--bg-primary);
        color: var(--text-primary);
        border: 1px solid var(--border);
    }

    .btn-secondary:hover {
        background: var(--border);
    }

    .btn-primary svg, .btn-secondary svg, .btn-danger svg {
        width: 18px;
        height: 18px;
    }

    .btn-danger {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
        border: none;
        background: #dc2626;
        color: white;
    }

    .btn-danger:hover {
        background: #b91c1c;
    }

    .call-status {
        padding: 1rem;
        border-radius: 8px;
        background: var(--bg-primary);
        border: 1px solid var(--border);
    }

    .call-status.success {
        background: #d1fae5;
        border-color: #059669;
    }

    .call-status.error {
        background: #fee2e2;
        border-color: #dc2626;
    }

    .call-status.info {
        background: #dbeafe;
        border-color: #2563eb;
    }

    .status-message {
        font-size: 0.875rem;
        color: var(--text-primary);
    }

    .info-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 1rem;
    }

    .info-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .info-list li {
        font-size: 0.875rem;
        color: var(--text-secondary);
        padding-left: 1.5rem;
        position: relative;
    }

    .info-list li::before {
        content: '•';
        position: absolute;
        left: 0;
        color: var(--accent);
        font-weight: bold;
    }


    /* Call Log Styles */
    .call-log-area {
        background: var(--bg-primary);
        border-radius: 8px;
        padding: 1rem;
        max-height: 400px;
        overflow-y: auto;
        min-height: 300px;
        flex: 1;
    }

    .call-log-entry {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        margin-bottom: 0.5rem;
        background: var(--bg-card);
        border-radius: 6px;
        font-size: 0.875rem;
        color: var(--text-primary);
        border-left: 3px solid var(--border);
        animation: fadeIn 0.3s ease-in;
    }

    .call-log-entry.calling {
        border-left-color: #2563eb;
    }

    .call-log-entry.answered {
        border-left-color: #059669;
    }

    .call-log-entry.ended {
        border-left-color: #dc2626;
    }

    .call-log-entry.error {
        border-left-color: #dc2626;
        color: #dc2626;
    }

    .call-log-icon {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
    }

    .call-log-icon.phone-icon {
        color: #2563eb;
    }

    .call-log-icon.check-icon {
        color: #059669;
    }

    .call-log-icon.error-icon {
        color: #dc2626;
    }

    .log-timestamp {
        margin-left: auto;
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .btn-clear-log {
        padding: 0.5rem 1rem;
        background: var(--bg-primary);
        color: var(--text-secondary);
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.75rem;
        cursor: pointer;
        transition: all 0.15s;
    }

    .btn-clear-log:hover {
        background: var(--border);
        color: var(--text-primary);
    }

    .call-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .phone-lead-card {
        min-width: 0;
        padding: 0;
        overflow: hidden;
    }
    .phone-lead-card .call-card-header {
        padding: 1.5rem 1.5rem 1rem;
        margin-bottom: 0;
    }
    .phone-lead-card .chp-panel,
    .phone-lead-card .chp-panel[hidden],
    .phone-lead-card .chp-panel.chp-visible,
    .phone-lead-card .chp-panel:not([hidden]) {
        display: flex !important;
        width: 100%;
        max-width: none;
        min-width: 0;
        height: auto;
        min-height: 280px;
        border: 0;
        background: transparent;
    }
    .phone-lead-card .chp-header { display: none; }

    @media (max-width: 1200px) {
        .call-cards-row {
            grid-template-columns: 1fr 1fr;
        }
        .phone-lead-card {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 1024px) {
        .call-cards-row {
            grid-template-columns: 1fr;
        }
        .phone-lead-card {
            grid-column: auto;
        }
    }

    @media (max-width: 768px) {
        .phone-input-group {
            flex-direction: column;
        }

        .phone-input-group .btn-primary {
            width: 100%;
        }

        .btn-primary, .btn-secondary, .btn-danger {
            width: 100%;
            justify-content: center;
        }

        .call-log-area {
            min-height: 200px;
        }

        .dialpad {
            max-width: 100%;
            gap: 0.5rem;
        }

        .dialpad-key {
            min-height: 50px;
        }

        .dialpad-number {
            font-size: 1.25rem;
        }

        .dialpad-letters {
            font-size: 0.5rem;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    let logEntryId = 2;
    let currentCallSid = null;
    
    // Check integration status on page load
    const hasIntegration = @json(isset($hasIntegration) && $hasIntegration);
    const integrationError = @json(isset($integrationError) ? $integrationError : null);
    
    // Show error in logs if no integration
    @if(isset($integrationError) && $integrationError)
        document.addEventListener('DOMContentLoaded', function() {
            addLogEntry('Error: {{ $integrationError }}', 'error', 'error-icon');
        });
    @endif

    // Audio context for ring sound (persistent)
    let ringAudioContext = null;
    let ringOscillator = null;
    let ringGainNode = null;
    let ringInterval = null;
    let isRinging = false;

    

    // Audio functions for call sounds
    function playRingSound() {
        // Stop any existing ring sound first
        stopRingSound();
        
        if (isRinging) return; // Already ringing
        
        isRinging = true;
        
        try {
            // Create or resume audio context
            ringAudioContext = ringAudioContext || new (window.AudioContext || window.webkitAudioContext)();
            
            // Resume audio context if suspended (browser autoplay policy)
            if (ringAudioContext.state === 'suspended') {
                ringAudioContext.resume();
            }
            
            // Create a realistic phone ring pattern (ring-ring-pause pattern)
            function playRingTone() {
                if (!isRinging) return;
                
                // First ring (400ms)
                ringOscillator = ringAudioContext.createOscillator();
                ringGainNode = ringAudioContext.createGain();
                
                ringOscillator.connect(ringGainNode);
                ringGainNode.connect(ringAudioContext.destination);
                
                // Use a more phone-like frequency (combination of tones)
                ringOscillator.frequency.value = 440; // A4 note
                ringOscillator.type = 'sine';
                
                // Volume envelope: quick attack, sustain, quick release
                const now = ringAudioContext.currentTime;
                ringGainNode.gain.setValueAtTime(0, now);
                ringGainNode.gain.linearRampToValueAtTime(0.5, now + 0.05); // Quick attack
                ringGainNode.gain.setValueAtTime(0.5, now + 0.35); // Sustain
                ringGainNode.gain.linearRampToValueAtTime(0, now + 0.4); // Quick release
                
                ringOscillator.start(now);
                ringOscillator.stop(now + 0.4);
                
                // Second ring after 200ms pause (400ms)
                setTimeout(() => {
                    if (!isRinging) return;
                    
                    const osc2 = ringAudioContext.createOscillator();
                    const gain2 = ringAudioContext.createGain();
                    
                    osc2.connect(gain2);
                    gain2.connect(ringAudioContext.destination);
                    
                    osc2.frequency.value = 440;
                    osc2.type = 'sine';
                    
                    const now2 = ringAudioContext.currentTime;
                    gain2.gain.setValueAtTime(0, now2);
                    gain2.gain.linearRampToValueAtTime(0.5, now2 + 0.05);
                    gain2.gain.setValueAtTime(0.5, now2 + 0.35);
                    gain2.gain.linearRampToValueAtTime(0, now2 + 0.4);
                    
                    osc2.start(now2);
                    osc2.stop(now2 + 0.4);
                }, 600); // 200ms pause + 400ms first ring
            }
            
            // Play the ring pattern immediately
            playRingTone();
            
            // Repeat the ring pattern every 4 seconds (ring-ring-pause-pause pattern)
            ringInterval = setInterval(() => {
                if (isRinging) {
                    playRingTone();
                } else {
                    stopRingSound();
                }
            }, 4000);
            
        } catch (e) {
            console.error('Could not play ring sound:', e);
            isRinging = false;
        }
    }
    
    function stopRingSound() {
        isRinging = false;
        
        if (ringInterval) {
            clearInterval(ringInterval);
            ringInterval = null;
        }
        
        if (ringOscillator) {
            try {
                ringOscillator.stop();
            } catch (e) {
                // Oscillator might already be stopped
            }
            ringOscillator = null;
        }
        
        if (ringGainNode) {
            try {
                ringGainNode.disconnect();
            } catch (e) {
                // Already disconnected
            }
            ringGainNode = null;
        }
    }

    function playHangupSound() {
        // Play hangup sound when call ends using Web Audio API
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 400; // Lower tone for hangup
            oscillator.type = 'sine';
            gainNode.gain.setValueAtTime(0.2, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.3);
        } catch (e) {
            console.log('Could not play hangup sound:', e);
        }
    }

    function addLogEntry(message, type = 'info', icon = 'check-icon') {
        const logArea = document.getElementById('callLogArea');
        const timestamp = new Date().toLocaleTimeString();
        const entryId = 'log-entry-' + logEntryId++;
        
        const iconSvg = {
            'check-icon': '<svg class="call-log-icon check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
            'phone-icon': '<svg class="call-log-icon phone-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
            'error-icon': '<svg class="call-log-icon error-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        };

        const entryClass = type === 'calling' ? 'calling' : 
                          type === 'answered' ? 'answered' : 
                          type === 'ended' ? 'ended' : 
                          type === 'error' ? 'error' : '';

        const logEntry = document.createElement('div');
        logEntry.className = `call-log-entry ${entryClass}`;
        logEntry.id = entryId;
        logEntry.innerHTML = `
            ${iconSvg[icon] || iconSvg['check-icon']}
            <span>${message}</span>
            <span class="log-timestamp">${timestamp}</span>
        `;
        
        logArea.appendChild(logEntry);
        logArea.scrollTop = logArea.scrollHeight;
        
        // Show clear log button if there are entries
        const clearBtn = document.getElementById('clearLogBtn');
        if (clearBtn) {
            clearBtn.style.display = 'block';
        }
    }

    function clearLog() {
        const logArea = document.getElementById('callLogArea');
        logArea.innerHTML = `
            <div class="call-log-entry">
                <svg class="call-log-icon check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                <span>Twilio device ready</span>
                <span class="log-timestamp">${new Date().toLocaleTimeString()}</span>
            </div>
            <div class="call-log-entry">
                <svg class="call-log-icon check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                <span>Twilio device registered</span>
                <span class="log-timestamp">${new Date().toLocaleTimeString()}</span>
            </div>
        `;
        logEntryId = 2;
        currentCallSid = null;
        document.getElementById('clearLogBtn').style.display = 'none';
    }

    function dialNumber(number, evt) {
        const clickedButton = (evt && evt.target) ? (evt.target.closest('.dialpad-key') || evt.target) : null;
        const doVisualFeedback = () => {
            if (clickedButton && clickedButton.classList.contains('dialpad-key')) {
                clickedButton.style.transform = 'scale(0.95)';
                setTimeout(() => { clickedButton.style.transform = ''; }, 100);
            }
        };
        // During active call: send DTMF instead of appending to phone input
        if (activeConnection || currentCallSid) {
            sendDtmfDigit(number);
            doVisualFeedback();
            return;
        }
        const phoneInput = document.getElementById('phoneNumber');
        const currentValue = phoneInput.value;
        phoneInput.value = currentValue + number;
        phoneInput.focus();
        doVisualFeedback();
    }

    function deleteLastDigit() {
        const phoneInput = document.getElementById('phoneNumber');
        const currentValue = phoneInput.value;
        if (currentValue.length > 0) {
            phoneInput.value = currentValue.slice(0, -1);
        }
        phoneInput.focus();
    }

    function sendDtmfDigit(digit) {
        // Browser call: use Voice SDK sendDigits
        if (activeConnection && typeof activeConnection.sendDigits === 'function') {
            try {
                activeConnection.sendDigits(digit);
                addLogEntry('Sent key: ' + digit, 'info', 'check-icon');
            } catch (e) {
                addLogEntry('Error sending key: ' + e.message, 'error', 'error-icon');
            }
            return;
        }
        // API call: use backend to play DTMF via TwiML
        if (currentCallSid) {
            fetch('{{ route("twilio.send-digits") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ call_sid: currentCallSid, digits: digit })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    addLogEntry('Sent key: ' + digit, 'info', 'check-icon');
                } else {
                    addLogEntry('Error: ' + (data.message || 'Failed to send key'), 'error', 'error-icon');
                }
            })
            .catch(error => {
                addLogEntry('Error sending key: ' + error.message, 'error', 'error-icon');
            });
        } else {
            addLogEntry('No active call', 'error', 'error-icon');
        }
    }

    function makeCall() {
        // Check if integration is available
        if (!hasIntegration) {
            const errorMsg = integrationError || 'Twilio integration not configured. Please configure your Twilio credentials in the Integrations page.';
            addLogEntry('Error: ' + errorMsg, 'error', 'error-icon');
            return;
        }

        const phoneInput = document.getElementById('phoneNumber');
        if (phoneInput && phoneInput.disabled) {
            addLogEntry('Error: Call function is disabled. Please configure your Twilio integration.', 'error', 'error-icon');
            return;
        }

        const phoneNumber = phoneInput.value;
        
        if (!phoneNumber) {
            addLogEntry('Error: Please enter a phone number', 'error', 'error-icon');
            return;
        }

        // Validate E.164 format (simplified)
        if (!phoneNumber.startsWith('+')) {
            addLogEntry('Error: Phone number must start with + (E.164 format)', 'error', 'error-icon');
            return;
        }

        addLogEntry(`Calling ${phoneNumber}...`, 'calling', 'phone-icon');

        // Make AJAX call to the test-call route
        const url = new URL('{{ route("twilio.test-call") }}', window.location.origin);
        url.searchParams.append('phone', phoneNumber);
        
        fetch(url.toString(), {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        })
        .then(response => {
            // Check if response is ok
            if (!response.ok) {
                return response.text().then(text => {
                    try {
                        const jsonData = JSON.parse(text);
                        throw new Error(jsonData.message || `HTTP ${response.status}: ${response.statusText}`);
                    } catch (e) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}. ${text.substring(0, 100)}`);
                    }
                });
            }
            
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                // If not JSON, try to parse as text and see what we got
                return response.text().then(text => {
                    console.error('Non-JSON response received:', text);
                    throw new Error('Server returned non-JSON response. Check server logs for details.');
                });
            }
        })
        .then(data => {
            console.log('Call response:', data);
            
            if (data.success === false) {
                throw new Error(data.message || 'Call failed');
            }
            
            if (data.call_sid) {
                currentCallSid = data.call_sid;
                addLogEntry('Call initiated successfully - SID: ' + data.call_sid.substring(0, 20) + '...', 'calling', 'phone-icon');
                addLogEntry('Initial status: ' + (data.status || 'queued'), 'calling', 'phone-icon');
                
                // Show hangup button and DTMF keys
                document.getElementById('hangupBtn').style.display = 'inline-flex';
                
                // Start polling for real status updates from Twilio
                startStatusPolling(data.call_sid);
            } else {
                // If no call_sid, there might be an issue
                console.error('Response missing call_sid:', data);
                addLogEntry('Warning: Call initiated but no Call SID returned. Response: ' + JSON.stringify(data), 'error', 'error-icon');
                addLogEntry('Check server logs and Twilio configuration', 'error', 'error-icon');
            }
        })
        .catch(error => {
            console.error('Call error:', error);
            addLogEntry('Error: ' + error.message, 'error', 'error-icon');
            addLogEntry('Please check: 1) Twilio credentials in .env, 2) Server logs, 3) Browser console', 'error', 'error-icon');
        });
    }

    function hangup() {
        if (!currentCallSid) {
            addLogEntry('No active call to hangup', 'error', 'error-icon');
            return;
        }

        addLogEntry('Hanging up...', 'ended', 'phone-icon');

        fetch('{{ route("twilio.hangup") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                call_sid: currentCallSid
            })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => {
                    throw new Error(data.message || 'Failed to hangup call');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                addLogEntry('Call ended', 'ended', 'check-icon');
                
                // Stop status polling
                if (statusPollInterval) {
                    clearInterval(statusPollInterval);
                    statusPollInterval = null;
                }
                
                // Stop ring sound if still playing
                stopRingSound();
                
                // Play hangup sound
                playHangupSound();
                
                // Reset call state
                currentCallSid = null;
                lastKnownStatus = null;
                document.getElementById('hangupBtn').style.display = 'none';
            } else {
                addLogEntry('Error: ' + (data.message || 'Failed to hangup'), 'error', 'error-icon');
            }
        })
        .catch(error => {
            console.error('Hangup error:', error);
            addLogEntry('Error hanging up: ' + error.message, 'error', 'error-icon');
        });
    }

    let statusPollInterval = null;
    let lastKnownStatus = null;

    function startStatusPolling(callSid) {
        // Clear any existing polling
        if (statusPollInterval) {
            clearInterval(statusPollInterval);
        }
        
        // Poll every 2 seconds for status updates
        statusPollInterval = setInterval(() => {
            fetchCallStatus(callSid);
        }, 2000);
        
        // Stop polling after 5 minutes
        setTimeout(() => {
            if (statusPollInterval) {
                clearInterval(statusPollInterval);
                statusPollInterval = null;
            }
        }, 300000); // 5 minutes
    }

    function fetchCallStatus(callSid) {
        if (!callSid) return;
        
        fetch(`{{ route('twilio.call-status') }}?call_sid=${callSid}`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.status) {
                const status = data.status.toLowerCase();
                
                // Only log if status changed
                if (status !== lastKnownStatus) {
                    lastKnownStatus = status;
                    
                    const statusMap = {
                        'queued': { message: 'Call queued', type: 'calling', icon: 'phone-icon' },
                        'initiated': { message: 'Call initiated', type: 'calling', icon: 'phone-icon' },
                        'ringing': { message: 'Call ringing', type: 'calling', icon: 'phone-icon' },
                        'answered': { message: 'Call answered', type: 'answered', icon: 'check-icon' },
                        'completed': { message: 'Call ended', type: 'ended', icon: 'check-icon' },
                        'busy': { message: 'Line busy', type: 'ended', icon: 'error-icon' },
                        'no-answer': { message: 'No answer', type: 'ended', icon: 'error-icon' },
                        'failed': { message: 'Call failed', type: 'error', icon: 'error-icon' },
                        'canceled': { message: 'Call canceled', type: 'ended', icon: 'error-icon' },
                    };
                    
                    const statusInfo = statusMap[status] || { 
                        message: `Call status: ${status}`, 
                        type: 'calling', 
                        icon: 'phone-icon' 
                    };
                    
                    addLogEntry(statusInfo.message, statusInfo.type, statusInfo.icon);
                    
                    // Play ring sound when call is ringing
                    if (status === 'ringing') {
                        playRingSound();
                    } else {
                        // Stop ring sound when status changes away from ringing
                        stopRingSound();
                    }
                    
                    // Play hangup sound when call ends
                    if (['completed', 'failed', 'canceled', 'busy', 'no-answer'].includes(status)) {
                        stopRingSound(); // Make sure ring stops
                        playHangupSound();
                    }
                    
                    // If call is completed, failed, or canceled, stop polling and hide hangup button
                    if (['completed', 'failed', 'canceled', 'busy', 'no-answer'].includes(status)) {
                        if (statusPollInterval) {
                            clearInterval(statusPollInterval);
                            statusPollInterval = null;
                        }
                        document.getElementById('hangupBtn').style.display = 'none';
                        currentCallSid = null;
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error fetching call status:', error);
        });
    }


    // Twilio Device for browser-based calling
    let twilioDevice = null;
    let activeConnection = null;
    let useBrowserCalling = false; // Toggle between API calls and browser calls

    function getTwilioDeviceClass() {
        if (typeof window.getTwilioDeviceClass === 'function' && window.getTwilioDeviceClass !== getTwilioDeviceClass) {
            return window.getTwilioDeviceClass();
        }

        return window.TwilioVoiceSDK?.Device || window.Twilio?.Device || null;
    }

    function waitForTwilioDevice(timeoutMs = 20000) {
        if (typeof window.whenTwilioVoiceSdkReady === 'function') {
            return window.whenTwilioVoiceSdkReady(timeoutMs).catch(() => null);
        }

        return new Promise((resolve) => {
            const existing = getTwilioDeviceClass();
            if (existing) {
                resolve(existing);
                return;
            }

            const started = Date.now();
            const timer = setInterval(() => {
                const Device = getTwilioDeviceClass();
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

    function adoptTwilioDevice(device) {
        if (!device || twilioDevice) {
            return false;
        }

        twilioDevice = device;
        window.globalTwilioDevice = device;
        useBrowserCalling = true;
        addLogEntry('Browser calling ready — inbound calls also ring on every CRM page while you are available', 'info', 'check-icon');
        return true;
    }

    function setupTwilioDevice(token, DeviceClass) {
        try {
            if (window.globalTwilioDevice) {
                adoptTwilioDevice(window.globalTwilioDevice);
                return;
            }

            const Device = DeviceClass || getTwilioDeviceClass();
            if (!Device) {
                throw new Error('Twilio Voice SDK 2.x not loaded');
            }

            twilioDevice = new Device(token, {
                logLevel: 'info',
                codecPreferences: ['opus', 'pcmu']
            });
            window.globalTwilioDevice = twilioDevice;

            twilioDevice.on('registered', () => {
                console.log('Twilio Device registered');
                useBrowserCalling = true;
                addLogEntry('Browser calling ready — inbound calls also ring on every CRM page while you are available', 'info', 'check-icon');
            });

            twilioDevice.on('error', (error) => {
                console.error('Twilio Device error:', error);
                const errorMsg = error.message || error.toString() || 'Unknown error';
                addLogEntry('Browser calling error: ' + errorMsg, 'error', 'error-icon');
            });

            twilioDevice.on('incoming', (call) => {
                addLogEntry('Incoming call from browser - use header notification to answer', 'calling', 'phone-icon');
                if (typeof window.handleIncomingCall === 'function') {
                    window.handleIncomingCall(call);
                }
            });

            twilioDevice.register();
            useBrowserCalling = true;
        } catch (error) {
            console.error('Error setting up Twilio Device:', error);
            addLogEntry('Failed to setup browser calling: ' + error.message, 'error', 'error-icon');
            useBrowserCalling = false;
        }
    }

    async function initializeTwilioDevice() {
        try {
            if (typeof window.ensureLnscrmTwilioDevice === 'function') {
                const device = await window.ensureLnscrmTwilioDevice();
                if (device) {
                    adoptTwilioDevice(device);
                    return;
                }
            }

            if (window.globalTwilioDevice) {
                adoptTwilioDevice(window.globalTwilioDevice);
                return;
            }

            const response = await fetch('{{ route("twilio.capability-token") }}', {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                }
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                const errorMessage = errorData.message || `HTTP ${response.status}`;

                if (errorMessage.includes('API Key') || errorMessage.includes('App SID')) {
                    addLogEntry('Browser calling is not configured (App SID / API Key missing). Outbound will ring the phone, then connect back to this page — add App SID, API Key, and API Secret under Integrations for in-browser audio.', 'info', 'check-icon');
                } else {
                    addLogEntry('Browser calling unavailable: ' + errorMessage, 'info', 'check-icon');
                }
                useBrowserCalling = false;
                return;
            }

            const data = await response.json();
            if (!data.success) {
                addLogEntry('Browser calling not available: ' + (data.message || 'Configuration missing'), 'info', 'check-icon');
                useBrowserCalling = false;
                return;
            }

            if (window.globalTwilioDevice) {
                adoptTwilioDevice(window.globalTwilioDevice);
                return;
            }

            const Device = await waitForTwilioDevice();
            if (!Device) {
                addLogEntry('Browser calling SDK not available. Using API-based calling.', 'info', 'check-icon');
                useBrowserCalling = false;
                return;
            }

            setupTwilioDevice(data.token, Device);
        } catch (error) {
            console.error('Error initializing Twilio Device:', error);
            addLogEntry('Browser calling not available. Using API-based calling.', 'info', 'check-icon');
            useBrowserCalling = false;
        }
    }

    window.addEventListener('lnscrm:twilio-device-ready', (event) => {
        adoptTwilioDevice(event.detail?.device || window.globalTwilioDevice);
    });

    // Make call using browser (Voice SDK 2.x)
    async function makeBrowserCall(phoneNumber) {
        twilioDevice = twilioDevice || window.globalTwilioDevice;
        if (!twilioDevice || !useBrowserCalling) {
            // Fallback to API call if browser calling not available
            return makeCall();
        }

        try {
            // Request microphone permission
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            stream.getTracks().forEach(track => track.stop()); // Stop immediately, Twilio will request it

            addLogEntry('Calling ' + phoneNumber + ' from browser...', 'calling', 'phone-icon');
            
            // Make the call using Voice SDK 2.x API
            const params = {
                To: phoneNumber,
                phone: phoneNumber,
                user_id: '{{ auth()->id() }}'
            };
            
            activeConnection = await twilioDevice.connect({ params });
            
            // Voice SDK 2.x uses 'call' object, not 'connection'
            if (activeConnection) {
                currentCallSid = activeConnection.parameters?.CallSid || activeConnection.sid;
                document.getElementById('hangupBtn').style.display = 'inline-flex';
                
                // Handle call events
                activeConnection.on('ringing', () => {
                    addLogEntry('Phone ringing...', 'calling', 'phone-icon');
                });

                activeConnection.on('accept', () => {
                    currentCallSid = activeConnection.parameters?.CallSid || currentCallSid;
                    addLogEntry('Call connected — you can talk from this page', 'answered', 'check-icon');
                });

                activeConnection.on('disconnect', () => {
                    activeConnection = null;
                    addLogEntry('Browser call ended', 'ended', 'check-icon');
                    document.getElementById('hangupBtn').style.display = 'none';
                });
                
                activeConnection.on('error', (error) => {
                    console.error('Call error:', error);
                    addLogEntry('Call error: ' + (error.message || error), 'error', 'error-icon');
                });
                
                addLogEntry('Browser call initiated', 'calling', 'phone-icon');
            }
        } catch (error) {
            console.error('Error making browser call:', error);
            if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
                addLogEntry('Microphone permission denied. Please allow microphone access.', 'error', 'error-icon');
                alert('Microphone permission is required for browser-based calling. Please allow microphone access and try again.');
            } else {
                addLogEntry('Browser call failed: ' + error.message, 'error', 'error-icon');
                // Fallback to API call
                return makeCall();
            }
        }
    }

    // Update makeCall function to support browser calling
    const originalMakeCall = window.makeCall;
    window.makeCall = function() {
        const phoneNumber = document.getElementById('phoneNumber').value.trim();
        
        if (!phoneNumber || phoneNumber === '+') {
            addLogEntry('Please enter a phone number', 'error', 'error-icon');
            return;
        }

        // Check if browser calling is available and preferred
        if (useBrowserCalling && twilioDevice) {
            makeBrowserCall(phoneNumber);
        } else {
            // Use API-based calling
            if (originalMakeCall) {
                originalMakeCall();
            }
        }
    };

    // Update hangup function to support browser calls
    const originalHangup = window.hangup;
    window.hangup = function() {
        if (activeConnection && twilioDevice) {
            // Browser call - disconnect directly (Voice SDK 2.x)
            try {
                activeConnection.disconnect();
                activeConnection = null;
                addLogEntry('Browser call ended', 'ended', 'check-icon');
                document.getElementById('hangupBtn').style.display = 'none';
                playHangupSound();
            } catch (error) {
                console.error('Error disconnecting call:', error);
                activeConnection = null;
                document.getElementById('hangupBtn').style.display = 'none';
                playHangupSound();
            }
        } else if (originalHangup) {
            // API call - use original hangup
            originalHangup();
        }
    };

    // Initialize - Log device ready on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Device ready and registered logs are already in the HTML
        // This ensures they show on page load
        
        // Try to initialize browser calling
        initializeTwilioDevice();
    });
</script>
@include('twilio.partials.phone-panel-scripts')
@endpush

