<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicon')
    <title>{{ $company->name }} - {{ config('app.name', 'CRM') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #ffffff;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --accent-light: #eff6ff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --border: #e5e7eb;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--bg); min-height: 100vh; color: var(--text-primary); }
        a { color: inherit; text-decoration: none; }
        .container { max-width: 1024px; margin: 0 auto; padding: 0 1.5rem; }
        /* Header */
        .header { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 0; }
        .logo-wrap { display: flex; align-items: center; gap: 0.5rem; }
        .logo { width: 40px; height: 40px; background: var(--accent); border-radius: 10px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .logo img { width: 100%; height: 100%; object-fit: contain; }
        .logo svg { width: 22px; height: 22px; color: white; }
        .logo-text { font-weight: 700; font-size: 1.25rem; color: var(--text-primary); }
        .sign-in { display: inline-flex; align-items: center; gap: 0.25rem; color: var(--accent); font-weight: 500; font-size: 0.9375rem; }
        .sign-in:hover { color: var(--accent-hover); }
        .sign-in svg { width: 16px; height: 16px; }
        /* Hero */
        .hero { padding: 4rem 0 5rem; text-align: center; }
        .badge { display: inline-block; background: var(--accent-light); color: var(--accent); padding: 0.375rem 0.75rem; border-radius: 9999px; font-size: 0.8125rem; font-weight: 500; margin-bottom: 1.25rem; }
        .hero h1 { font-size: clamp(2rem, 5vw, 3rem); font-weight: 700; line-height: 1.2; margin-bottom: 1rem; letter-spacing: -0.02em; }
        .hero h1 .highlight { color: var(--accent); }
        .hero p { font-size: 1.0625rem; color: var(--text-secondary); max-width: 36rem; margin: 0 auto 2rem; line-height: 1.6; }
        .hero-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 1rem 1.5rem; }
        .btn-cta { display: inline-flex; align-items: center; gap: 0.5rem; background: var(--accent); color: white; padding: 0.75rem 1.5rem; border-radius: 12px; font-weight: 600; font-size: 1rem; transition: background 0.15s; }
        .btn-cta:hover { background: var(--accent-hover); }
        .btn-cta svg { width: 20px; height: 20px; }
        .back-link { color: var(--text-muted); font-size: 0.875rem; }
        .back-link:hover { color: var(--text-secondary); }
        /* Social proof */
        .social-proof { padding: 2rem 0 4rem; }
        .profiles { display: flex; justify-content: center; gap: 2rem; flex-wrap: wrap; }
        .profile { text-align: center; }
        .profile-img { width: 64px; height: 64px; border-radius: 50%; background: var(--border); object-fit: cover; margin-bottom: 0.5rem; border: 2px solid transparent; }
        .profile.focus .profile-img { border-color: var(--accent); }
        .profile-name { font-weight: 600; font-size: 0.9375rem; }
        .profile-title { font-size: 0.8125rem; color: var(--text-muted); }
        /* Feature cards */
        .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1.25rem; max-width: 640px; margin: 0 auto 4rem; }
        .feature-card { background: var(--accent-light); border-radius: 12px; padding: 1.25rem; text-align: center; }
        .feature-icon { width: 32px; height: 32px; margin: 0 auto 0.75rem; color: var(--accent); }
        .feature-icon svg { width: 100%; height: 100%; }
        .feature-text { font-weight: 600; font-size: 0.9375rem; color: var(--text-primary); }
        /* Footer */
        .footer { padding: 2rem 0; text-align: center; border-top: 1px solid var(--border); }
        .footer-text { font-size: 0.8125rem; color: var(--text-muted); }
    </style>
</head>
<body>
    <header class="header container">
        <div class="logo-wrap">
            <div class="logo">
                @if($company->logo)
                    <img src="{{ public_media_url($company->logo) }}" alt="{{ $company->name }}">
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <path d="M2 17l10 5 10-5"/>
                    </svg>
                @endif
            </div>
            <span class="logo-text">{{ $company->name }}</span>
        </div>
        <a href="{{ url('/login') }}" class="sign-in">
            Sign in
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
        </a>
    </header>

    <main>
        <section class="hero">
            <h1>Manage Your Team.<br><span class="highlight">Simply.</span></h1>
            <p>Our AI assistant builds a professional job description through a simple conversation. No forms, no hassle.</p>
            <div class="hero-actions">
                <a href="{{ route('landing.start') }}" class="btn-cta">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    Get started
                </a>
                
            </div>
        </section>

        <section class="social-proof">
            <div class="profiles">
                <div class="profile">
                    <div class="profile-img" style="background: linear-gradient(135deg,#94a3b8,#64748b);"></div>
                    <div class="profile-name">Trusted</div>
                    <div class="profile-title">Teams worldwide</div>
                </div>
                <div class="profile focus">
                    <div class="profile-img" style="background: linear-gradient(135deg,#60a5fa,#3b82f6);"></div>
                    <div class="profile-name">{{ $company->name }}</div>
                    <div class="profile-title">Your organization</div>
                </div>
                <div class="profile">
                    <div class="profile-img" style="background: linear-gradient(135deg,#34d399,#10b981);"></div>
                    <div class="profile-name">Efficient</div>
                    <div class="profile-title">Workflow</div>
                </div>
            </div>
        </section>

        <section class="features container">
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M12 6v6l4 2"/>
                    </svg>
                </div>
                <div class="feature-text">5 Min Setup</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </div>
                <div class="feature-text">Secure & Reliable</div>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div class="feature-text">Team Collaboration</div>
            </div>
        </section>
    </main>

   
</body>
</html>
