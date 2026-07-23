<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicon')
    <title>Smart Workspace CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-soft: #eef2ff;
            --success-bg: #ecfdf5;
            --success-border: #34d399;
            --success-text: #065f46;
            --error-bg: #fef2f2;
            --error-border: #f87171;
            --error-text: #991b1b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: radial-gradient(circle at top left, #eef2ff 0%, #f8fafc 35%, #f8fafc 100%);
            color: var(--text);
            min-height: 100vh;
        }

        .wrapper {
            max-width: 1180px;
            margin: 0 auto;
            padding: 2rem 1.25rem;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2.25rem;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.01em;
        }

        .brand-mark {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.75rem;
            background: var(--primary);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 800;
        }

        .topbar-link {
            font-size: 0.9rem;
            color: var(--muted);
            text-decoration: none;
            transition: color 0.15s;
        }

        .topbar-link:hover {
            color: var(--text);
        }

        .content {
            display: grid;
            grid-template-columns: 1.2fr 0.9fr;
            gap: 2rem;
            align-items: start;
        }

        .hero {
            padding-top: 1rem;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: var(--primary-soft);
            color: var(--primary);
            border-radius: 999px;
            padding: 0.45rem 0.85rem;
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        h1 {
            font-size: clamp(2rem, 4vw, 3rem);
            line-height: 1.15;
            letter-spacing: -0.03em;
            margin-bottom: 0.95rem;
        }

        .hero-text {
            color: var(--muted);
            font-size: 1.02rem;
            line-height: 1.7;
            max-width: 40rem;
            margin-bottom: 1.35rem;
        }

        .feature-list {
            list-style: none;
            display: grid;
            gap: 0.7rem;
        }

        .feature-list li {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            color: #334155;
            font-size: 0.94rem;
        }

        .feature-list li::before {
            content: "✓";
            color: var(--primary);
            font-weight: 800;
        }

        .login-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.4rem;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
        }

        .login-title {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .login-subtitle {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 1.15rem;
        }

        .alert {
            border-radius: 0.65rem;
            padding: 0.75rem 0.85rem;
            font-size: 0.87rem;
            margin-bottom: 0.95rem;
        }

        .alert-success {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
        }

        .alert-error {
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
        }

        .form-group {
            margin-bottom: 0.85rem;
        }

        .form-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
            color: #334155;
        }

        .form-input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 0.55rem;
            padding: 0.65rem 0.75rem;
            font-size: 0.92rem;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 0.3rem 0 1rem;
            gap: 0.5rem;
        }

        .remember {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--muted);
            font-size: 0.84rem;
            cursor: pointer;
        }

        .forgot-link {
            font-size: 0.84rem;
            color: var(--primary);
            text-decoration: none;
        }

        .forgot-link:hover {
            color: var(--primary-hover);
        }

        .btn {
            width: 100%;
            border: none;
            border-radius: 0.6rem;
            padding: 0.68rem 0.95rem;
            font-size: 0.93rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin: 0.95rem 0;
            color: #94a3b8;
            font-size: 0.78rem;
        }

        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .btn-secondary {
            border: 1px solid var(--border);
            color: var(--text);
            background: #fff;
        }

        .btn-secondary:hover {
            background: #f8fafc;
        }

        .hint {
            margin-top: 0.8rem;
            color: var(--muted);
            font-size: 0.78rem;
            text-align: center;
        }

        .context-sections {
            margin-top: 2.5rem;
            display: grid;
            gap: 1.5rem;
        }

        .section-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.25rem;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.35rem;
            letter-spacing: -0.01em;
        }

        .section-subtitle {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .screenshots-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.9rem;
        }

        .screenshot {
            border: 1px solid #dbe4f0;
            border-radius: 0.85rem;
            background: #f8fbff;
            overflow: hidden;
        }

        .shot-bar {
            height: 2rem;
            border-bottom: 1px solid #dbe4f0;
            background: #eef2ff;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0 0.7rem;
        }

        .shot-dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 999px;
            background: #c7d2fe;
        }

        .shot-body {
            padding: 0.7rem;
            display: grid;
            gap: 0.5rem;
        }

        .shot-headline {
            height: 0.65rem;
            border-radius: 0.4rem;
            background: linear-gradient(90deg, #818cf8, #a5b4fc);
            width: 70%;
        }

        .shot-row {
            height: 0.55rem;
            border-radius: 0.35rem;
            background: #dbeafe;
        }

        .shot-row.small {
            width: 55%;
        }

        .shot-panel {
            margin-top: 0.25rem;
            border-radius: 0.5rem;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            height: 3.8rem;
        }

        .highlight-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.9rem;
        }

        .highlight-item {
            border: 1px solid var(--border);
            border-radius: 0.8rem;
            padding: 0.9rem;
            background: #fcfdff;
        }

        .highlight-item h3 {
            font-size: 0.92rem;
            margin-bottom: 0.35rem;
        }

        .highlight-item p {
            color: var(--muted);
            font-size: 0.84rem;
            line-height: 1.55;
        }

        @media (max-width: 940px) {
            .content {
                grid-template-columns: 1fr;
            }

            .hero {
                order: 2;
            }

            .login-card {
                order: 1;
            }

            .screenshots-grid,
            .highlight-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <header class="topbar">
            <div class="brand">
                <span class="brand-mark">CRM</span>
            </div>
            <a href="{{ route('register') }}" class="topbar-link">Start free trial</a>
        </header>

        <main class="content">
            <section class="hero">
                <span class="badge">All-in-one workspace CRM</span>
                <h1>Grow faster with a CRM built for teams that execute.</h1>
                <p class="hero-text">
                    Manage leads, projects, payroll, communication, and operations in one secure platform.
                    Launch your account in minutes and keep your whole team aligned.
                </p>

                <ul class="feature-list">
                    <li>Centralized dashboard for operations, billing, and team performance</li>
                    <li>Role-based access controls for full admin visibility</li>
                    <li>Built-in hiring assistant, communication tools, and integrations</li>
                    <li>Fast onboarding with a 14-day trial company setup</li>
                </ul>
            </section>

            <section class="login-card">
                <h2 class="login-title">Welcome back</h2>
                <p class="login-subtitle">Sign in to your workspace account.</p>

                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-error">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('login.submit') }}">
                    @csrf

                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input"
                            placeholder="you@example.com"
                            value="{{ old('email', session('registered_email')) }}"
                            required
                            autofocus
                        >
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Enter password"
                            required
                        >
                    </div>

                    <div class="row">
                        <label class="remember">
                            <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}>
                            Remember me
                        </label>
                        <a href="{{ route('password.request') }}" class="forgot-link">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn btn-primary">Sign in</button>
                </form>

                <div class="divider">or</div>

                <a href="{{ route('register') }}" class="btn btn-secondary">Create Trial Account</a>
                <p class="hint">A new company and full-access admin account are created automatically.</p>
            </section>
        </main>

        <section class="context-sections">
            <article class="section-card">
                <h2 class="section-title">Product Screenshots</h2>
                <p class="section-subtitle">Quick visual preview of the core workspace modules.</p>

                <div class="screenshots-grid">
                    <div class="screenshot">
                        <div class="shot-bar">
                            <span class="shot-dot"></span>
                            <span class="shot-dot"></span>
                            <span class="shot-dot"></span>
                        </div>
                        <div class="shot-body">
                            <div class="shot-headline"></div>
                            <div class="shot-row"></div>
                            <div class="shot-row small"></div>
                            <div class="shot-panel"></div>
                        </div>
                    </div>

                    <div class="screenshot">
                        <div class="shot-bar">
                            <span class="shot-dot"></span>
                            <span class="shot-dot"></span>
                            <span class="shot-dot"></span>
                        </div>
                        <div class="shot-body">
                            <div class="shot-headline"></div>
                            <div class="shot-row"></div>
                            <div class="shot-row"></div>
                            <div class="shot-panel"></div>
                        </div>
                    </div>

                    <div class="screenshot">
                        <div class="shot-bar">
                            <span class="shot-dot"></span>
                            <span class="shot-dot"></span>
                            <span class="shot-dot"></span>
                        </div>
                        <div class="shot-body">
                            <div class="shot-headline"></div>
                            <div class="shot-row small"></div>
                            <div class="shot-row"></div>
                            <div class="shot-panel"></div>
                        </div>
                    </div>
                </div>
            </article>

            <article class="section-card">
                <h2 class="section-title">Highlighted Features</h2>
                <p class="section-subtitle">Built to support your full operations from one workspace.</p>

                <div class="highlight-grid">
                    <div class="highlight-item">
                        <h3>Lead & Client Pipeline</h3>
                        <p>Track prospects from first touch to conversion with visibility across your team.</p>
                    </div>
                    <div class="highlight-item">
                        <h3>Team & Access Control</h3>
                        <p>Create roles, assign permissions, and keep each department focused on what matters.</p>
                    </div>
                    <div class="highlight-item">
                        <h3>Payroll & Time Tracking</h3>
                        <p>Manage attendance, work hours, and payroll workflows in one consistent process.</p>
                    </div>
                    <div class="highlight-item">
                        <h3>Built-in Messaging</h3>
                        <p>Keep communication inside the CRM to reduce tool switching and speed up execution.</p>
                    </div>
                    <div class="highlight-item">
                        <h3>AI Hiring Assistant</h3>
                        <p>Generate job descriptions and manage hiring steps with conversational AI support.</p>
                    </div>
                    <div class="highlight-item">
                        <h3>Integrations Ready</h3>
                        <p>Connect external tools and channels while keeping your core data centralized.</p>
                    </div>
                </div>
            </article>
        </section>
    </div>
</body>
</html>
