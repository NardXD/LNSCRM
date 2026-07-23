<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicon')
    <title>Create Account - CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #f3f4f6;
            --bg-card: #ffffff;
            --accent: #5f61e6;
            --accent-hover: #4f51d6;
            --accent-light: #f0f0ff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --border: #e5e7eb;
            --success: #10b981;
            --gold: #0ea5e9;
            --gold-dark: #0284c7;
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
            padding: 2rem 1rem;
        }

        .container {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
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

        .card-narrow {
            max-width: 480px;
            margin: 0 auto;
        }

        /* Progress Steps */
        .progress-bar {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .progress-step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 100px;
            font-size: 0.8125rem;
            color: var(--text-muted);
        }

        .progress-step.active {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }

        .progress-step.completed {
            background: var(--accent-light);
            border-color: var(--accent);
            color: var(--accent);
        }

        .progress-step-number {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: currentColor;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6875rem;
            font-weight: 600;
        }

        .progress-step.active .progress-step-number {
            background: white;
            color: var(--accent);
        }

        .progress-step.completed .progress-step-number {
            background: var(--accent);
        }

        /* Step Content */
        .step-content {
            display: none;
        }

        .step-content.active {
            display: block;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(10px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Page Header */
        .page-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            font-size: 0.9375rem;
            color: var(--text-secondary);
        }

        /* Plan Tabs */
        .plan-tabs {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .plan-tab {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border: 1px solid var(--border);
            border-radius: 100px;
            background: var(--bg-card);
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.15s;
        }

        .plan-tab:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .plan-tab.active {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }

        .plan-tab-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
        }

        /* Billing Toggle */
        .billing-toggle {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 1.5rem;
        }

        .toggle-wrapper {
            display: flex;
            align-items: center;
            background: var(--text-primary);
            border-radius: 8px;
            padding: 0.25rem;
        }

        .toggle-btn {
            padding: 0.5rem 1rem;
            border: none;
            background: transparent;
            border-radius: 6px;
            font-size: 0.8125rem;
            font-weight: 500;
            color: white;
            cursor: pointer;
            transition: all 0.15s;
        }

        .toggle-btn.active {
            background: var(--accent);
        }

        .toggle-badge {
            background: #f97316;
            color: white;
            font-size: 0.625rem;
            font-weight: 600;
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
            margin-left: 0.375rem;
        }

        /* Plans Grid */
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        @media (max-width: 900px) {
            .plans-grid {
                grid-template-columns: 1fr;
                max-width: 400px;
                margin: 0 auto;
            }
        }

        /* Plan Card */
        .plan-card {
            background: var(--bg-card);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            position: relative;
            cursor: pointer;
            transition: all 0.2s;
        }

        .plan-card:hover {
            border-color: #d1d5db;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .plan-card.selected {
            border-color: var(--accent);
        }

        .plan-card input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        /* Popular Badge */
        .popular-badge {
            position: absolute;
            top: -1px;
            right: 1.5rem;
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
            font-size: 0.6875rem;
            font-weight: 600;
            padding: 0.375rem 0.75rem;
            border-radius: 0 0 8px 8px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .popular-badge::before {
            content: '★ ';
        }

        /* Plan Header */
        .plan-name {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
            text-align: center;
        }

        .plan-price {
            text-align: center;
            margin-bottom: 0.25rem;
        }

        .plan-price-amount {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--gold-dark);
        }

        .plan-price-period {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .plan-tagline {
            text-align: center;
            font-size: 0.8125rem;
            color: var(--text-secondary);
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1rem;
        }

        /* Credits Section */
        .credits-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
        }

        .credits-list {
            margin-bottom: 1rem;
        }

        .credit-item {
            display: flex;
            justify-content: space-between;
            font-size: 0.8125rem;
            padding: 0.25rem 0;
        }

        .credit-label {
            color: var(--text-secondary);
        }

        .credit-value {
            color: var(--gold-dark);
            font-weight: 500;
        }

        /* Features Section */
        .features-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
        }

        .features-list {
            margin-bottom: 1.5rem;
        }

        .feature-item {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            font-size: 0.8125rem;
            color: var(--text-secondary);
            padding: 0.25rem 0;
        }

        .feature-item svg {
            width: 16px;
            height: 16px;
            color: var(--gold);
            flex-shrink: 0;
            margin-top: 1px;
        }

        /* Select Button */
        .plan-select-btn {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--accent);
            border-radius: 8px;
            background: transparent;
            color: var(--accent);
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
        }

        .plan-select-btn:hover {
            background: var(--accent);
            color: white;
        }

        .plan-card.selected .plan-select-btn {
            background: var(--accent);
            color: white;
        }

        /* Form Styles */
        .step-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.375rem;
        }

        .step-subtitle {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

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

        /* Promo Code */
        .promo-box {
            background: var(--accent-light);
            border: 1px dashed var(--accent);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }

        .promo-box .icon {
            width: 48px;
            height: 48px;
            background: var(--bg-card);
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .promo-box .icon svg {
            width: 24px;
            height: 24px;
            color: var(--accent);
        }

        .promo-input-group {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .promo-input-group .form-input {
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .btn-apply {
            padding: 0.625rem 1rem;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.8125rem;
            font-weight: 500;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.15s;
        }

        .btn-apply:hover {
            background: var(--accent-hover);
        }

        .skip-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: var(--text-muted);
            font-size: 0.8125rem;
            text-decoration: none;
        }

        .skip-link:hover {
            color: var(--text-secondary);
        }

        /* Payment */
        .payment-methods {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }

        .payment-method {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            background: var(--bg-card);
            cursor: pointer;
            text-align: center;
            transition: all 0.15s;
        }

        .payment-method:hover {
            border-color: #d1d5db;
        }

        .payment-method.selected {
            border-color: var(--accent);
            background: var(--accent-light);
        }

        .payment-method input {
            display: none;
        }

        .payment-method span {
            font-size: 0.8125rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .card-icons {
            display: flex;
            gap: 0.375rem;
            justify-content: center;
            margin-bottom: 0.375rem;
        }

        .order-summary {
            background: #f9fafb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.25rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .summary-row:last-child {
            margin-bottom: 0;
            padding-top: 0.5rem;
            border-top: 1px solid var(--border);
            font-weight: 600;
        }

        .summary-label {
            color: var(--text-secondary);
        }

        .summary-value {
            color: var(--text-primary);
        }

        /* Success */
        .success-content {
            text-align: center;
            padding: 2rem 0;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: #ecfdf5;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .success-icon svg {
            width: 40px;
            height: 40px;
            color: var(--success);
        }

        .success-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .success-message {
            font-size: 0.9375rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
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

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background: var(--bg-primary);
        }

        .btn-group {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .btn-group .btn {
            flex: 1;
        }

        /* Back link */
        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            margin-top: 1.5rem;
            color: var(--text-secondary);
            font-size: 0.8125rem;
            text-decoration: none;
        }

        .back-link:hover {
            color: var(--accent);
        }

        .back-link svg {
            width: 16px;
            height: 16px;
        }

        /* Responsive */
        @media (max-width: 640px) {
            .card {
                padding: 1.5rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column-reverse;
            }

            .progress-bar {
                gap: 0.375rem;
            }

            .progress-step {
                padding: 0.375rem 0.75rem;
                font-size: 0.75rem;
            }

            .progress-step-label {
                display: none;
            }

            .plan-tabs {
                flex-direction: column;
                align-items: stretch;
            }

            .billing-toggle {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-step active" data-step="1">
                <span class="progress-step-number">1</span>
                <span class="progress-step-label">Plan</span>
            </div>
            <div class="progress-step" data-step="2">
                <span class="progress-step-number">2</span>
                <span class="progress-step-label">Info</span>
            </div>
            <div class="progress-step" data-step="3">
                <span class="progress-step-number">3</span>
                <span class="progress-step-label">Promo</span>
            </div>
            <div class="progress-step" data-step="4">
                <span class="progress-step-number">4</span>
                <span class="progress-step-label">Payment</span>
            </div>
            <div class="progress-step" data-step="5">
                <span class="progress-step-number">5</span>
                <span class="progress-step-label">Done</span>
            </div>
        </div>

        <form id="registerForm" method="POST" action="{{ route('register.submit') }}">
            @csrf

            <!-- Step 1: Choose Plan -->
            <div class="step-content active" data-step="1">
                <div class="page-header">
                    <h1 class="page-title">Choose Your Plan</h1>
                    <p class="page-subtitle">Select the perfect plan for your needs</p>
                </div>

                <!-- Plan Type Tabs -->
                <div class="plan-tabs">
                    <button type="button" class="plan-tab active">
                        <span class="plan-tab-dot"></span>
                        Membership Plans
                    </button>
                    <button type="button" class="plan-tab">
                        <span class="plan-tab-dot"></span>
                        Skiptrace Bundle Plans
                    </button>
                    <button type="button" class="plan-tab">
                        <span class="plan-tab-dot"></span>
                        Leadmax Package Plans
                    </button>
                </div>

                <!-- Billing Toggle -->
                <div class="billing-toggle">
                    <div class="toggle-wrapper">
                        <button type="button" class="toggle-btn active" data-billing="monthly">Monthly</button>
                        <button type="button" class="toggle-btn" data-billing="annual">Annual<span class="toggle-badge">SAVE 10%</span></button>
                    </div>
                </div>

                <!-- Plans Grid -->
                <div class="plans-grid">
                    <!-- FREE Plan -->
                    <label class="plan-card selected">
                        <input type="radio" name="plan" value="free" checked>
                        <div class="plan-name">Free</div>
                        <div class="plan-price">
                            <span class="plan-price-amount">$0</span>
                            <span class="plan-price-period">per month</span>
                        </div>
                        <div class="plan-tagline">Free Plan</div>

                        <div class="credits-title">Credits Included:</div>
                        <div class="credits-list">
                            <div class="credit-item">
                                <span class="credit-label">Property Detail:</span>
                                <span class="credit-value">1</span>
                            </div>
                            <div class="credit-item">
                                <span class="credit-label">Property Search:</span>
                                <span class="credit-value">100</span>
                            </div>
                            <div class="credit-item">
                                <span class="credit-label">Property Storage:</span>
                                <span class="credit-value">50,000</span>
                            </div>
                        </div>

                        <div class="features-title">Key Features:</div>
                        <div class="features-list">
                            <div class="feature-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                7¢ Skip Tracing / record
                            </div>
                            <div class="feature-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                2¢ Property Records / record
                            </div>
                            <div class="feature-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                1 User
                            </div>
                            <div class="feature-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                Basic Direct Mailing System
                            </div>
                            <div class="feature-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                Custom Filters Access
                            </div>
                        </div>

                        <button type="button" class="plan-select-btn" onclick="selectPlan(this)">
                            → Select Plan
                        </button>
                    </label>

                    <!-- GOLD Plan -->
                    <label class="plan-card">
                        <input type="radio" name="plan" value="gold">
                        <span class="popular-badge">Most Popular</span>
                        <div class="plan-name">Gold</div>
                        <div class="plan-price">
                            <span class="plan-price-amount">$149</span>
                            <span class="plan-price-period">per month</span>
                        </div>
                        <div class="plan-tagline">Perfect for growing real estate businesses</div>

                        <div class="credits-title">Credits Included:</div>
                        <div class="credits-list">
                            <div class="credit-item">
                                <span class="credit-label">Record Exports:</span>
                                <span class="credit-value">30,000</span>
                            </div>
                            <div class="credit-item">
                                <span class="credit-label">Property Detail:</span>
                                <span class="credit-value">200</span>
                            </div>
                            <div class="credit-item">
                                <span class="credit-label">Property Search:</span>
                                <span class="credit-value">10,000</span>
                            </div>
                            <div class="credit-item">
                                <span class="credit-label">Property Storage:</span>
                                <span class="credit-value">Unlimited</span>
                            </div>
                        </div>

                        <div class="features-title">Key Features:</div>
                        <div class="features-list">
                            <div class="feature-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                10k Property Records / month
                            </div>
                            <div class="feature-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                6¢ Skip Tracing / record
                            </div>
                            <div class="feature-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                Unlimited Property List Storage
                            </div>
                            <div class="feature-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                10 Users
                            </div>
                            <div class="feature-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                Direct Mail
                            </div>
                            <div class="feature-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                Marketing Sequence
                            </div>
                        </div>

                        <button type="button" class="plan-select-btn" onclick="selectPlan(this)">
                            → Select Plan
                        </button>
                    </label>

                    <!-- PLATINUM Plan -->
                    <label class="plan-card">
                        <input type="radio" name="plan" value="platinum">
                        <div class="plan-name">Platinum</div>
                        <div class="plan-price">
                            <span class="plan-price-amount">$399</span>
                            <span class="plan-price-period">per month</span>
                        </div>
                        <div class="plan-tagline">Ultimate solution for serious real estate professionals</div>

                        <div class="credits-title">Credits Included:</div>
                        <div class="credits-list">
                            <div class="credit-item">
                                <span class="credit-label">Record Exports:</span>
                                <span class="credit-value">75,000</span>
                            </div>
                            <div class="credit-item">
                                <span class="credit-label">Property Detail:</span>
                                <span class="credit-value">400</span>
                            </div>
                            <div class="credit-item">
                                <span class="credit-label">Property Search:</span>
                                <span class="credit-value">25,000</span>
                            </div>
                            <div class="credit-item">
                                <span class="credit-label">Property Storage:</span>
                                <span class="credit-value">Unlimited</span>
                            </div>
                        </div>

                        <div class="features-title">Key Features:</div>
                        <div class="features-list">
                            <div class="feature-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                25k Property Records / month
                            </div>
                            <div class="feature-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                5¢ Skip Tracing / record
                            </div>
                            <div class="feature-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                Unlimited Property List Storage
                            </div>
                            <div class="feature-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                40 Users
                            </div>
                            <div class="feature-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                Direct Mail
                            </div>
                            <div class="feature-item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                Dialer API Integration
                            </div>
                        </div>

                        <button type="button" class="plan-select-btn" onclick="selectPlan(this)">
                            → Select Plan
                        </button>
                    </label>
                </div>

                <div class="btn-group" style="max-width: 300px; margin: 2rem auto 0;">
                    <button type="button" class="btn btn-primary" onclick="nextStep()">Continue</button>
                </div>
            </div>

            <!-- Step 2: User Info -->
            <div class="step-content" data-step="2">
                <div class="card card-narrow">
                    <h2 class="step-title">Your information</h2>
                    <p class="step-subtitle">Enter your account details</p>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name" class="form-label">First name</label>
                            <input type="text" id="first_name" name="first_name" class="form-input" placeholder="John" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name" class="form-label">Last name</label>
                            <input type="text" id="last_name" name="last_name" class="form-input" placeholder="Doe" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" name="email" class="form-input" placeholder="john@example.com" required>
                    </div>

                    <div class="form-group">
                        <label for="company" class="form-label">Company name</label>
                        <input type="text" id="company" name="company" class="form-input" placeholder="Acme Inc.">
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Min. 8 characters" required>
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation" class="form-label">Confirm password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-input" placeholder="Confirm password" required>
                    </div>

                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary" onclick="prevStep()">Back</button>
                        <button type="button" class="btn btn-primary" onclick="nextStep()">Continue</button>
                    </div>
                </div>
            </div>

            <!-- Step 3: Promotion -->
            <div class="step-content" data-step="3">
                <div class="card card-narrow">
                    <h2 class="step-title">Have a promo code?</h2>
                    <p class="step-subtitle">Enter your code for a discount</p>

                    <div class="promo-box">
                        <div class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21.5 12H16c-.7 2-2 3-4 3s-3.3-1-4-3H2.5"/>
                                <path d="M5.5 5.1L2 12v6c0 1.1.9 2 2 2h16a2 2 0 002-2v-6l-3.4-6.9A2 2 0 0016.8 4H7.2a2 2 0 00-1.8 1.1z"/>
                            </svg>
                        </div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">Enter your promotional code below</div>
                        <div class="promo-input-group">
                            <input type="text" name="promo_code" class="form-input" placeholder="PROMO2024">
                            <button type="button" class="btn-apply">Apply</button>
                        </div>
                    </div>

                    <a href="#" class="skip-link" onclick="nextStep(); return false;">Skip this step</a>

                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary" onclick="prevStep()">Back</button>
                        <button type="button" class="btn btn-primary" onclick="nextStep()">Continue</button>
                    </div>
                </div>
            </div>

            <!-- Step 4: Payment -->
            <div class="step-content" data-step="4">
                <div class="card card-narrow">
                    <h2 class="step-title">Payment details</h2>
                    <p class="step-subtitle">Complete your subscription</p>

                    <div class="payment-methods">
                        <label class="payment-method selected">
                            <input type="radio" name="payment_method" value="card" checked>
                            <div class="card-icons">
                                <svg width="32" height="20" viewBox="0 0 32 20"><rect fill="#1A1F71" width="32" height="20" rx="2"/><text x="16" y="13" text-anchor="middle" fill="white" font-size="8" font-weight="bold">VISA</text></svg>
                                <svg width="32" height="20" viewBox="0 0 32 20"><rect fill="#EB001B" width="32" height="20" rx="2"/><circle cx="12" cy="10" r="6" fill="#EB001B"/><circle cx="20" cy="10" r="6" fill="#F79E1B"/><path d="M16 5.5a6 6 0 000 9" fill="#FF5F00"/></svg>
                            </div>
                            <span>Card</span>
                        </label>
                        <label class="payment-method">
                            <input type="radio" name="payment_method" value="paypal">
                            <div class="card-icons">
                                <svg width="32" height="20" viewBox="0 0 32 20"><rect fill="#003087" width="32" height="20" rx="2"/><text x="16" y="13" text-anchor="middle" fill="white" font-size="6" font-weight="bold">PayPal</text></svg>
                            </div>
                            <span>PayPal</span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label for="card_number" class="form-label">Card number</label>
                        <input type="text" id="card_number" name="card_number" class="form-input" placeholder="1234 5678 9012 3456">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="expiry" class="form-label">Expiry date</label>
                            <input type="text" id="expiry" name="expiry" class="form-input" placeholder="MM/YY">
                        </div>
                        <div class="form-group">
                            <label for="cvv" class="form-label">CVV</label>
                            <input type="text" id="cvv" name="cvv" class="form-input" placeholder="123">
                        </div>
                    </div>

                    <div class="order-summary">
                        <div class="summary-row">
                            <span class="summary-label">Plan</span>
                            <span class="summary-value" id="summaryPlan">Free</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Discount</span>
                            <span class="summary-value">$0.00</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Total</span>
                            <span class="summary-value" id="summaryTotal">$0.00/month</span>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary" onclick="prevStep()">Back</button>
                        <button type="button" class="btn btn-primary" onclick="nextStep()">Complete</button>
                    </div>
                </div>
            </div>

            <!-- Step 5: Success -->
            <div class="step-content" data-step="5">
                <div class="card card-narrow">
                    <div class="success-content">
                        <div class="success-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </div>
                        <h2 class="success-title">You're all set!</h2>
                        <p class="success-message">Your account has been created successfully.<br>Welcome to CRM Portal!</p>
                        <a href="{{ route('dashboard') }}" class="btn btn-primary">Go to Dashboard</a>
                    </div>
                </div>
            </div>
        </form>

        <a href="{{ route('login') }}" class="back-link" id="backToLogin">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m15 18-6-6 6-6"/>
            </svg>
            Already have an account? Sign in
        </a>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = 5;

        const planPrices = {
            'free': { name: 'Free', price: '$0.00/month' },
            'gold': { name: 'Gold', price: '$149.00/month' },
            'platinum': { name: 'Platinum', price: '$399.00/month' }
        };

        function updateProgress() {
            const steps = document.querySelectorAll('.progress-step');

            steps.forEach((step, index) => {
                const stepNum = index + 1;
                const numEl = step.querySelector('.progress-step-number');
                
                step.classList.remove('active', 'completed');
                
                if (stepNum < currentStep) {
                    step.classList.add('completed');
                    numEl.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="12" height="12"><polyline points="20 6 9 17 4 12"/></svg>';
                } else if (stepNum === currentStep) {
                    step.classList.add('active');
                    numEl.textContent = stepNum;
                } else {
                    numEl.textContent = stepNum;
                }
            });

            // Update step content
            document.querySelectorAll('.step-content').forEach(content => {
                content.classList.remove('active');
                if (parseInt(content.dataset.step) === currentStep) {
                    content.classList.add('active');
                }
            });

            // Hide back to login on success
            const backLink = document.getElementById('backToLogin');
            backLink.style.display = currentStep === 5 ? 'none' : 'flex';
        }

        function nextStep() {
            if (currentStep < totalSteps) {
                // If on step 4, submit the form instead of moving to step 5
                if (currentStep === 4) {
                    document.getElementById('registerForm').submit();
                    return;
                }
                currentStep++;
                updateProgress();
                updateSummary();
                window.scrollTo(0, 0);
            }
        }

        function prevStep() {
            if (currentStep > 1) {
                currentStep--;
                updateProgress();
                window.scrollTo(0, 0);
            }
        }

        function updateSummary() {
            const selectedPlan = document.querySelector('input[name="plan"]:checked').value;
            const plan = planPrices[selectedPlan];
            document.getElementById('summaryPlan').textContent = plan.name;
            document.getElementById('summaryTotal').textContent = plan.price;
        }

        function selectPlan(btn) {
            const card = btn.closest('.plan-card');
            const radio = card.querySelector('input[type="radio"]');
            
            document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            radio.checked = true;
        }

        // Plan card selection
        document.querySelectorAll('.plan-card').forEach(card => {
            card.addEventListener('click', (e) => {
                if (e.target.closest('.plan-select-btn')) return;
                document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                card.querySelector('input[type="radio"]').checked = true;
            });
        });

        // Plan tabs
        document.querySelectorAll('.plan-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.plan-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
            });
        });

        // Billing toggle
        document.querySelectorAll('.toggle-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });

        // Payment method selection
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', () => {
                document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
                method.classList.add('selected');
            });
        });

        // Initialize
        updateProgress();
    </script>
</body>
</html>

