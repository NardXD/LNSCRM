<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicon')
    <title>Forgot Password - CRM</title>
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
            --success: #10b981;
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

        .container {
            width: 100%;
            max-width: 380px;
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background: var(--accent-light);
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .icon-wrapper svg {
            width: 24px;
            height: 24px;
            color: var(--accent);
        }

        .title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.5;
        }

        /* Form */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            color: var(--text-primary);
            font-size: 0.8125rem;
            font-weight: 500;
            margin-bottom: 0.375rem;
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

        /* Button */
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
            border: none;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
        }

        .btn-primary:active {
            transform: scale(0.98);
        }

        /* Back link */
        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            margin-top: 1.25rem;
            color: var(--text-secondary);
            font-size: 0.8125rem;
            text-decoration: none;
            transition: color 0.15s;
        }

        .back-link:hover {
            color: var(--accent);
        }

        .back-link svg {
            width: 16px;
            height: 16px;
        }

        /* Success state */
        .success-icon {
            background: #ecfdf5;
        }

        .success-icon svg {
            color: var(--success);
        }

        /* Responsive */
        @media (max-width: 480px) {
            .card {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="icon-wrapper">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
                <h1 class="title">Forgot password?</h1>
                <p class="subtitle">No worries, we'll send you reset instructions.</p>
            </div>

            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                
                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input" 
                        placeholder="Enter your email"
                        value="{{ old('email') }}"
                        required 
                        autofocus
                    >
                </div>

                <button type="submit" class="btn btn-primary">Reset password</button>
            </form>

            <a href="{{ route('login') }}" class="back-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m15 18-6-6 6-6"/>
                </svg>
                Back to login
            </a>
        </div>
    </div>
</body>
</html>

