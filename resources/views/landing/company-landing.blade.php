<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicon')
    <title>{{ $company->name }} — Staff workspace</title>
    <meta name="description" content="Internal workspace for {{ $company->name }} staff. Sign in to manage leads, inbox, quotes, and payroll.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --yellow: #ffe500;
            --yellow-deep: #f0d400;
            --purple: #2c1bb8;
            --purple-hot: #3d27e0;
            --ink: #100c28;
            --paper: #f4f1ea;
            --cream: #fbfaf6;
            --muted: #6a6580;
            --line: rgba(16, 12, 40, 0.1);
            --card: #ffffff;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Outfit', system-ui, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(900px 480px at 90% -10%, rgba(255, 229, 0, 0.28), transparent 55%),
                radial-gradient(700px 420px at -10% 10%, rgba(44, 27, 184, 0.12), transparent 50%),
                var(--cream);
            min-height: 100vh;
            line-height: 1.5;
        }
        img { max-width: 100%; display: block; }
        a { color: inherit; text-decoration: none; }

        .wrap {
            width: min(1120px, calc(100% - 2.5rem));
            margin: 0 auto;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.15rem 0 0.4rem;
        }
        .brand img {
            height: 54px;
            width: auto;
            border-radius: 12px;
            background: #fff;
        }
        .header-links {
            display: flex;
            align-items: center;
            gap: 0.7rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.92rem;
            padding: 0.72rem 1.15rem;
            border: 0;
            cursor: pointer;
            transition: transform 0.15s, background 0.15s, box-shadow 0.15s;
        }
        .btn:hover { transform: translateY(-1px); }
        .btn-yellow {
            background: var(--yellow);
            color: var(--ink);
            box-shadow: 0 10px 22px rgba(255, 229, 0, 0.32);
        }
        .btn-yellow:hover { background: var(--yellow-deep); }
        .btn-purple {
            background: var(--purple);
            color: #fff;
        }
        .btn-purple:hover { background: var(--purple-hot); }
        .btn-ghost {
            background: #fff;
            color: var(--ink);
            border: 1px solid var(--line);
        }

        .hero {
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 2.4rem;
            align-items: center;
            padding: 2.6rem 0 3.2rem;
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(44, 27, 184, 0.08);
            color: var(--purple);
            border-radius: 999px;
            padding: 0.35rem 0.75rem;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 1rem;
        }
        h1 {
            font-family: 'Syne', sans-serif;
            font-size: clamp(2.3rem, 5vw, 3.8rem);
            line-height: 0.98;
            letter-spacing: -0.04em;
            margin-bottom: 0.9rem;
        }
        h1 em {
            font-style: normal;
            color: var(--purple);
        }
        .lede {
            color: var(--muted);
            font-size: 1.05rem;
            max-width: 36rem;
            margin-bottom: 1.5rem;
        }
        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            margin-bottom: 1.4rem;
        }
        .checks {
            list-style: none;
            display: grid;
            gap: 0.55rem;
            color: #322c4a;
            font-size: 0.95rem;
        }
        .checks li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .checks li::before {
            content: "";
            width: 0.7rem;
            height: 0.7rem;
            border-radius: 999px;
            background: var(--yellow);
            box-shadow: inset 0 0 0 3px var(--purple);
            flex-shrink: 0;
        }

        .panel {
            background: var(--card);
            border-radius: 1.6rem;
            box-shadow: 0 28px 60px rgba(16, 12, 40, 0.12);
            overflow: hidden;
            border: 1px solid var(--line);
        }
        .panel-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            background: var(--ink);
            color: #fff;
        }
        .panel-dots { display: flex; gap: 0.35rem; }
        .panel-dots span {
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 999px;
            background: #ffe500;
            opacity: 0.85;
        }
        .panel-dots span:nth-child(2) { background: #8b7cff; }
        .panel-dots span:nth-child(3) { background: #fff; opacity: 0.35; }
        .panel-title { font-size: 0.82rem; font-weight: 600; letter-spacing: 0.02em; }
        .panel-body { padding: 1.1rem; display: grid; gap: 0.75rem; }
        .chip-row { display: flex; flex-wrap: wrap; gap: 0.45rem; }
        .chip {
            background: var(--paper);
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--ink);
        }
        .mock-card {
            border: 1px solid var(--line);
            border-radius: 1rem;
            padding: 0.9rem;
            display: grid;
            gap: 0.45rem;
        }
        .mock-card strong { font-size: 0.92rem; }
        .mock-card p { color: var(--muted); font-size: 0.82rem; }
        .mock-progress {
            height: 0.45rem;
            border-radius: 999px;
            background: #eeeaf6;
            overflow: hidden;
        }
        .mock-progress span {
            display: block;
            height: 100%;
            width: 68%;
            background: linear-gradient(90deg, var(--purple), #7c6bff);
        }
        .panel-photo {
            height: 168px;
            overflow: hidden;
        }
        .panel-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modules {
            padding: 0 0 4.2rem;
        }
        .section-head {
            margin-bottom: 1.4rem;
        }
        .kicker {
            color: var(--purple);
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-size: 0.74rem;
            margin-bottom: 0.45rem;
        }
        .section-head h2 {
            font-family: 'Syne', sans-serif;
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            letter-spacing: -0.03em;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }
        .mod {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 1.2rem;
            padding: 1.15rem;
            min-height: 150px;
        }
        .mod-icon {
            width: 2.3rem;
            height: 2.3rem;
            border-radius: 0.75rem;
            background: var(--yellow);
            color: var(--ink);
            display: grid;
            place-items: center;
            margin-bottom: 0.8rem;
        }
        .mod-icon svg { width: 1.15rem; height: 1.15rem; }
        .mod h3 { font-size: 1.02rem; margin-bottom: 0.35rem; }
        .mod p { color: var(--muted); font-size: 0.9rem; }

        .footer {
            border-top: 1px solid var(--line);
            padding: 1.3rem 0 1.8rem;
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            color: var(--muted);
            font-size: 0.82rem;
        }

        @media (max-width: 900px) {
            .hero, .grid { grid-template-columns: 1fr; }
            .header { flex-wrap: wrap; }
            .wrap { width: min(1120px, calc(100% - 1.5rem)); }
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .btn:hover { transform: none; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header class="header">
            <a href="{{ url('/') }}" class="brand" aria-label="{{ $company->name }} home">
                @if($company->logo)
                    <img src="{{ public_media_url($company->logo) }}" alt="{{ $company->name }}">
                @else
                    <img src="{{ asset('images/landing/logo.png') }}" alt="{{ $company->name }}">
                @endif
            </a>
            <div class="header-links">
                <a href="{{ url('/login') }}" class="btn btn-purple">Sign in</a>
            </div>
        </header>

        <main>
            <section class="hero">
                <div>
                    <p class="eyebrow">Internal workspace</p>
                    <h1>Manage your team.<br><em>Get to work.</em></h1>
                    <p class="lede">
                        Sign in to the {{ $company->name }} CRM — leads, inbox, quotes, and payroll in one place.
                        This portal is for staff only.
                    </p>
                    <div class="hero-actions">
                        <a href="{{ url('/login') }}" class="btn btn-purple">Sign in to workspace</a>
                    </div>
                    <ul class="checks">
                        <li>Inbox, leads, and quotation tools</li>
                        <li>Time tracking, payroll, and team access</li>
                        <li>Roles that keep each desk on the right records</li>
                    </ul>
                </div>

                <aside class="panel" aria-hidden="true">
                    <div class="panel-bar">
                        <div class="panel-dots"><span></span><span></span><span></span></div>
                        <div class="panel-title">{{ $company->name }} · Staff dashboard</div>
                    </div>
                    <div class="panel-photo">
                        <img src="{{ asset('images/landing/team.webp') }}" alt="">
                    </div>
                    <div class="panel-body">
                        <div class="chip-row">
                            <span class="chip">Inbox</span>
                            <span class="chip">Leads</span>
                            <span class="chip">Quotes</span>
                            <span class="chip">Payroll</span>
                        </div>
                        <div class="mock-card">
                            <strong>Today’s queue</strong>
                            <p>Follow-ups, inbound messages, and open quotations stay in one view after you sign in.</p>
                            <div class="mock-progress"><span></span></div>
                        </div>
                    </div>
                </aside>
            </section>

            <section class="modules">
                <div class="section-head">
                    <p class="kicker">Inside the workspace</p>
                    <h2>What you can run from here</h2>
                </div>
                <div class="grid">
                    <article class="mod">
                        <div class="mod-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <h3>Inbox & channels</h3>
                        <p>Reply across email, chat, and social from a single staff inbox.</p>
                    </article>
                    <article class="mod">
                        <div class="mod-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <h3>Leads & quotes</h3>
                        <p>Track prospects and build storage quotations without leaving the CRM.</p>
                    </article>
                    <article class="mod">
                        <div class="mod-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        </div>
                        <h3>Time & payroll</h3>
                        <p>Clock in, review hours, and keep payroll workflows with the rest of ops.</p>
                    </article>
                    <article class="mod">
                        <div class="mod-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <h3>Roles & access</h3>
                        <p>Admins control who sees billing and branch-level records.</p>
                    </article>
                    <article class="mod">
                        <div class="mod-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        </div>
                        <h3>Calendar & contracts</h3>
                        <p>Keep schedules, e-sign, and follow-through in the same workspace.</p>
                    </article>
                </div>
            </section>
        </main>

        <footer class="footer">
            <span>Staff access only · {{ $company->name }}</span>
            <a href="{{ url('/login') }}">Sign in</a>
        </footer>
    </div>
</body>
</html>
