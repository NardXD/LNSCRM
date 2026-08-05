<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LNSCRM — {{ $data['display_name'] ?? 'Screen pop' }}</title>
    <style>
        :root {
            --bg: #f4f6f8;
            --card: #ffffff;
            --text: #1a2332;
            --muted: #5b6b7c;
            --accent: #0b5cab;
            --border: #d8dee6;
            --ok: #0f7b4c;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 1rem;
            line-height: 1.45;
        }
        .panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1.1rem 1.25rem;
            max-width: 520px;
        }
        h1 {
            margin: 0 0 0.25rem;
            font-size: 1.25rem;
            font-weight: 650;
        }
        .sub {
            color: var(--muted);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        .badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            background: #e8f1fb;
            color: var(--accent);
            margin-bottom: 0.75rem;
        }
        .badge.miss { background: #f1f3f5; color: var(--muted); }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.45rem 0;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
        }
        .row:last-child { border-bottom: 0; }
        .label { color: var(--muted); }
        .value { font-weight: 500; text-align: right; word-break: break-word; }
        a.cta {
            display: inline-block;
            margin-top: 1rem;
            background: var(--accent);
            color: #fff;
            text-decoration: none;
            padding: 0.55rem 0.9rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        a.cta:hover { filter: brightness(1.05); }
        .calls { margin-top: 1rem; }
        .calls h2 {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
            margin: 0 0 0.5rem;
        }
        .call {
            font-size: 0.8rem;
            padding: 0.4rem 0;
            border-bottom: 1px solid var(--border);
            color: var(--muted);
        }
        .brand {
            margin-top: 1.25rem;
            font-size: 0.75rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <div class="panel">
        @if (!empty($data['found']))
            <span class="badge">CRM match</span>
        @else
            <span class="badge miss">No CRM match</span>
        @endif

        <h1>{{ $data['display_name'] ?? 'Unknown caller' }}</h1>
        <p class="sub">{{ $data['phone'] ?? 'Waiting for an active Flex task…' }}</p>

        @if (!empty($data['client']))
            <div class="row"><span class="label">Client</span><span class="value">{{ $data['client']['name'] }}</span></div>
            @if (!empty($data['client']['contact_person']))
                <div class="row"><span class="label">Contact</span><span class="value">{{ $data['client']['contact_person'] }}</span></div>
            @endif
            @if (!empty($data['client']['email']))
                <div class="row"><span class="label">Email</span><span class="value">{{ $data['client']['email'] }}</span></div>
            @endif
            @if (!empty($data['client']['status']))
                <div class="row"><span class="label">Status</span><span class="value">{{ $data['client']['status'] }}</span></div>
            @endif
            @if (!empty($data['client']['crm_url']))
                <a class="cta" href="{{ $data['client']['crm_url'] }}" target="_blank" rel="noopener">Open in LNSCRM</a>
            @endif
        @elseif (!empty($data['phone_contact']))
            <div class="row"><span class="label">Contact</span><span class="value">{{ $data['phone_contact']['name'] }}</span></div>
            @if (!empty($data['phone_contact']['email']))
                <div class="row"><span class="label">Email</span><span class="value">{{ $data['phone_contact']['email'] }}</span></div>
            @endif
            @if (!empty($data['phone_contact']['notes']))
                <div class="row"><span class="label">Notes</span><span class="value">{{ $data['phone_contact']['notes'] }}</span></div>
            @endif
        @endif

        @if (!empty($data['recent_calls']))
            <div class="calls">
                <h2>Recent calls</h2>
                @foreach ($data['recent_calls'] as $call)
                    <div class="call">
                        {{ $call['direction'] ?? '—' }} · {{ $call['status'] ?? '—' }}
                        @if (!empty($call['started_at']))
                            · {{ \Illuminate\Support\Carbon::parse($call['started_at'])->toDayDateTimeString() }}
                        @endif
                        @if (!empty($call['duration']))
                            · {{ $call['duration'] }}s
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if (!empty($history['threads']))
            <div class="calls">
                <h2>All channels</h2>
                @foreach ($history['threads'] as $thread)
                    <div class="call">
                        <strong>{{ $thread['label'] ?? $thread['channel'] }}</strong>
                        — {{ $thread['title'] ?? '' }}
                        @if (!empty($thread['preview']))
                            <div>{{ \Illuminate\Support\Str::limit($thread['preview'], 80) }}</div>
                        @endif
                        @if (!empty($thread['deep_link']))
                            <div><a href="{{ $thread['deep_link'] }}" target="_blank" rel="noopener">Open</a></div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @php
            $historyUrl = url('/contact-history');
            $hq = [];
            if (!empty($data['phone'])) { $hq['phone'] = $data['phone']; }
            if (!empty($history['query']['email'])) { $hq['email'] = $history['query']['email']; }
            if ($hq !== []) { $historyUrl .= '?'.http_build_query($hq); }
        @endphp
        <a class="cta" href="{{ $historyUrl }}" target="_blank" rel="noopener" style="margin-top:0.75rem;">Full contact history</a>

        <p class="brand">{{ $company->name ?? 'LNSCRM' }} · Twilio Flex screen pop</p>
    </div>
</body>
</html>
