<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicon')
    <title>Admin Login - CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #fafafa;
            --bg-card: #ffffff;
            --accent: #5f61e6;
            --accent-hover: #4f51d6;
            --accent-light: #f0f0ff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --border: #e5e7eb;
            --border-focus: #5f61e6;
            --admin-badge: #dc2626;
            --admin-badge-bg: #fee2e2;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        /* Logo */
        .logo-section {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--accent) 0%, #7c3aed 100%);
            border-radius: 14px;
            margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(95, 97, 230, 0.3);
        }

        .logo svg {
            width: 28px;
            height: 28px;
            color: white;
        }

        .logo-title {
            font-size: 1.375rem;
            font-weight: 600;
            color: var(--text-primary);
            letter-spacing: -0.01em;
            margin-bottom: 0.25rem;
        }

        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            background: var(--admin-badge-bg);
            color: var(--admin-badge);
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.625rem;
            border-radius: 6px;
            margin-top: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .admin-badge svg {
            width: 14px;
            height: 14px;
        }

        .logo-subtitle {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        /* Form */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            color: var(--text-primary);
            font-size: 0.8125rem;
            font-weight: 500;
            margin-bottom: 0.375rem;
        }

        .input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 0.625rem 0.75rem;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 0.875rem;
            font-family: inherit;
            transition: border-color 0.15s, box-shadow 0.15s;
            outline: none;
        }

        .form-input::placeholder {
            color: var(--text-muted);
        }

        .form-input:hover {
            border-color: #d1d5db;
        }

        .form-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
        }

        /* Password toggle */
        .password-toggle {
            position: absolute;
            right: 0.625rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.15s;
        }

        .password-toggle:hover {
            color: var(--text-secondary);
        }

        .password-toggle svg {
            width: 18px;
            height: 18px;
        }

        /* Remember & Forgot */
        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
            margin-top: 0.25rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .checkbox-input {
            width: 16px;
            height: 16px;
            accent-color: var(--accent);
            cursor: pointer;
        }

        .remember-label {
            color: var(--text-secondary);
            font-size: 0.8125rem;
            user-select: none;
        }

        .forgot-link {
            color: var(--accent);
            font-size: 0.8125rem;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.15s;
        }

        .forgot-link:hover {
            color: var(--accent-hover);
        }

        /* Buttons */
        .btn {
            width: 100%;
            padding: 0.625rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent) 0%, #7c3aed 100%);
            border: none;
            color: white;
            box-shadow: 0 2px 4px rgba(95, 97, 230, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--accent-hover) 0%, #6d28d9 100%);
            box-shadow: 0 4px 8px rgba(95, 97, 230, 0.3);
            transform: translateY(-1px);
        }

        .btn-primary:active {
            transform: translateY(0) scale(0.98);
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-primary);
            margin-top: 0.75rem;
        }

        .btn-secondary:hover {
            background: var(--bg-primary);
            border-color: #d1d5db;
        }

        /* Error Messages */
        .alert {
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 1rem 0;
        }

        .divider-line {
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .divider-text {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        /* Back to regular login */
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .back-link a {
            color: var(--text-secondary);
            font-size: 0.8125rem;
            text-decoration: none;
            transition: color 0.15s;
        }

        .back-link a:hover {
            color: var(--accent);
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 1.5rem;
                border-radius: 12px;
            }

            .form-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
        }

        /* Focus visible */
        .btn:focus-visible,
        .forgot-link:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Logo Section -->
            <div class="logo-section">
                <div class="logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                        <path d="M2 17l10 5 10-5"/>
                        <path d="M2 12l10 5 10-5"/>
                    </svg>
                </div>
                <h1 class="logo-title">Admin Portal</h1>
                <div class="admin-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <path d="M9 12l2 2 4-4"/>
                    </svg>
                    Admin Access
                </div>
                <p class="logo-subtitle">Sign in to admin dashboard</p>
            </div>

            <!-- Error Messages -->
            @if ($errors->any())
                <div class="alert alert-error">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Login Form -->
            <form method="POST" action="{{ route('admin.login.submit') }}">
                @csrf
                
                <!-- Email Field -->
                <div class="form-group">
                    <label for="email" class="form-label">Admin Email</label>
                    <div class="input-wrapper">
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input" 
                            placeholder="admin@example.com"
                            value="{{ old('email') }}"
                            required 
                            autofocus
                            autocomplete="email"
                        >
                    </div>
                </div>

                <!-- Password Field -->
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="Enter password"
                            required
                            autocomplete="current-password"
                            style="padding-right: 2.5rem;"
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <svg id="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Remember Me & Forgot Password -->
                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" class="checkbox-input" {{ old('remember') ? 'checked' : '' }}>
                        <span class="remember-label">Remember me</span>
                    </label>
                    <a href="{{ route('password.request') }}" class="forgot-link">Forgot password?</a>
                </div>

                <!-- Login Button -->
                <button type="submit" class="btn btn-primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                        <polyline points="10 17 15 12 10 7"/>
                        <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    Sign in to Admin
                </button>
            </form>

            <!-- Back to Regular Login -->
            <div class="back-link">
                <a href="{{ route('login') }}">← Back to regular login</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = `
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                `;
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = `
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                `;
            }
        }
    </script>
</body>
</html>

