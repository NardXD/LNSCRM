@extends('layouts.app')

@section('title', 'Billing Plan')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Billing Plan</h1>
        <p class="page-subtitle">Manage your subscription and billing preferences</p>
    </div>

    <div class="billing-plan-container">
        <!-- Current Plan Section -->
        <div class="current-plan-section">
            <div class="current-plan-card">
                <div class="current-plan-header">
                    <div>
                        <div class="current-plan-badge">Current Plan</div>
                        <h2 class="current-plan-name">Gold Plan</h2>
                        <p class="current-plan-description">Perfect for growing real estate businesses</p>
                    </div>
                    <div class="current-plan-price">
                        <span class="price-amount">$149</span>
                        <span class="price-period">/month</span>
                    </div>
                </div>
                <div class="current-plan-details">
                    <div class="plan-detail-item">
                        <span class="detail-label">Billing Cycle</span>
                        <span class="detail-value">Monthly</span>
                    </div>
                    <div class="plan-detail-item">
                        <span class="detail-label">Next Billing Date</span>
                        <span class="detail-value">Jan 1, 2026</span>
                    </div>
                    <div class="plan-detail-item">
                        <span class="detail-label">Status</span>
                        <span class="detail-value"><span class="status-badge active">Active</span></span>
                    </div>
                </div>
                <div class="current-plan-actions">
                    <button class="btn-secondary" onclick="cancelSubscription()">Cancel Subscription</button>
                    <button class="btn-primary" onclick="changePlan()">Change Plan</button>
                </div>
            </div>

            <!-- Usage Statistics -->
            <div class="usage-stats-grid">
                <div class="usage-stat-card">
                    <div class="usage-stat-header">
                        <span class="usage-stat-label">Record Exports</span>
                        <span class="usage-stat-used">15,240 / 30,000</span>
                    </div>
                    <div class="usage-progress-bar">
                        <div class="usage-progress-fill" style="width: 50.8%"></div>
                    </div>
                    <div class="usage-stat-footer">51% used this month</div>
                </div>

                <div class="usage-stat-card">
                    <div class="usage-stat-header">
                        <span class="usage-stat-label">Property Detail</span>
                        <span class="usage-stat-used">120 / 200</span>
                    </div>
                    <div class="usage-progress-bar">
                        <div class="usage-progress-fill" style="width: 60%"></div>
                    </div>
                    <div class="usage-stat-footer">60% used this month</div>
                </div>

                <div class="usage-stat-card">
                    <div class="usage-stat-header">
                        <span class="usage-stat-label">Property Search</span>
                        <span class="usage-stat-used">6,500 / 10,000</span>
                    </div>
                    <div class="usage-progress-bar">
                        <div class="usage-progress-fill" style="width: 65%"></div>
                    </div>
                    <div class="usage-stat-footer">65% used this month</div>
                </div>
            </div>
        </div>

        <!-- Available Plans Section -->
        <div class="available-plans-section">
            <div class="section-header">
                <h2 class="section-title">Available Plans</h2>
                <div class="billing-toggle">
                    <div class="toggle-wrapper">
                        <button type="button" class="toggle-btn active" data-billing="monthly">Monthly</button>
                        <button type="button" class="toggle-btn" data-billing="annual">Annual<span class="toggle-badge">SAVE 10%</span></button>
                    </div>
                </div>
            </div>

            <!-- Plan Type Tabs -->
            <div class="plan-tabs">
                <button class="plan-tab active" data-tab="membership">Membership Plans</button>
                <button class="plan-tab" data-tab="skiptrace">Skiptrace Bundle Plans</button>
                <button class="plan-tab" data-tab="leadmax">Leadmax Package Plans</button>
            </div>

            <!-- Plans Grid -->
            <div class="plans-grid" id="plansGrid">
                <!-- Plans will be populated by JavaScript -->
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .billing-plan-container {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }

    /* Current Plan Section */
    .current-plan-section {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .current-plan-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 2rem;
    }

    .current-plan-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
        gap: 1rem;
    }

    .current-plan-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        background: var(--accent-light);
        color: var(--accent);
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .current-plan-name {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 0.5rem 0;
    }

    .current-plan-description {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .current-plan-price {
        text-align: right;
    }

    .price-amount {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .price-period {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .current-plan-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .plan-detail-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .detail-label {
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .detail-value {
        font-size: 0.875rem;
        color: var(--text-primary);
        font-weight: 500;
    }

    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-block;
    }

    .status-badge.active {
        background: #d1fae5;
        color: #059669;
    }

    .current-plan-actions {
        display: flex;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    /* Usage Stats */
    .usage-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
    }

    .usage-stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
    }

    .usage-stat-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
    }

    .usage-stat-label {
        font-size: 0.875rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .usage-stat-used {
        font-size: 0.875rem;
        color: var(--text-primary);
        font-weight: 600;
    }

    .usage-progress-bar {
        width: 100%;
        height: 8px;
        background: var(--bg-primary);
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 0.5rem;
    }

    .usage-progress-fill {
        height: 100%;
        background: var(--accent);
        border-radius: 4px;
        transition: width 0.3s;
    }

    .usage-stat-footer {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    /* Available Plans Section */
    .available-plans-section {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 2rem;
    }

    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    /* Billing Toggle */
    .billing-toggle {
        display: flex;
        justify-content: flex-end;
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
        color: white;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
        border-radius: 6px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        -webkit-tap-highlight-color: transparent;
    }

    .toggle-btn.active {
        background: white;
        color: var(--text-primary);
    }

    .toggle-badge {
        padding: 0.125rem 0.5rem;
        background: var(--accent);
        color: white;
        border-radius: 4px;
        font-size: 0.6875rem;
        font-weight: 600;
    }

    /* Plan Tabs */
    .plan-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 0.5rem;
    }

    .plan-tab {
        padding: 0.625rem 1.25rem;
        border: 1px solid var(--border);
        background: var(--bg-primary);
        border-radius: 100px;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        white-space: nowrap;
        -webkit-tap-highlight-color: transparent;
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

    /* Plans Grid */
    .plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
    }

    .plan-card {
        background: var(--bg-primary);
        border: 2px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
        cursor: pointer;
        transition: all 0.15s;
        position: relative;
    }

    .plan-card:hover {
        border-color: var(--accent);
        box-shadow: 0 4px 12px rgba(95, 97, 230, 0.1);
    }

    .plan-card.current {
        border-color: var(--accent);
        background: var(--accent-light);
    }

    .plan-card.popular {
        border-color: var(--accent);
    }

    .popular-badge {
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        padding: 0.25rem 0.75rem;
        background: var(--accent);
        color: white;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .plan-name {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .plan-price {
        display: flex;
        align-items: baseline;
        gap: 0.25rem;
        margin-bottom: 0.5rem;
    }

    .plan-price-amount {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .plan-price-period {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .plan-tagline {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin-bottom: 1.5rem;
    }

    .credits-title {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.75rem;
    }

    .credits-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
    }

    .credit-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 0.875rem;
        color: var(--text-primary);
    }

    .credit-label {
        color: var(--text-secondary);
    }

    .credit-value {
        font-weight: 600;
        color: var(--text-primary);
    }

    .features-title {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.75rem;
    }

    .features-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
    }

    .feature-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.875rem;
        color: var(--text-primary);
    }

    .feature-item svg {
        width: 18px;
        height: 18px;
        color: var(--accent);
        flex-shrink: 0;
    }

    .plan-actions {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .plan-select-btn {
        width: 100%;
        padding: 0.75rem 1.5rem;
        border: 1px solid var(--accent);
        background: var(--accent);
        color: white;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
        -webkit-tap-highlight-color: transparent;
    }

    .plan-select-btn:hover {
        background: var(--accent-hover);
        border-color: var(--accent-hover);
    }

    .plan-select-btn.current {
        background: var(--bg-primary);
        color: var(--accent);
        border-color: var(--border);
        cursor: default;
    }

    .plan-select-btn.current:hover {
        background: var(--bg-primary);
    }

    /* Buttons */
    .btn-primary, .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
        border: none;
        -webkit-tap-highlight-color: transparent;
    }

    .btn-primary {
        background: var(--accent);
        color: white;
    }

    .btn-primary:hover {
        background: var(--accent-hover);
    }

    .btn-secondary {
        background: var(--bg-primary);
        color: var(--text-primary);
        border: 1px solid var(--border);
    }

    .btn-secondary:hover {
        background: var(--border);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .current-plan-header {
            flex-direction: column;
        }

        .current-plan-price {
            text-align: left;
        }

        .current-plan-details {
            grid-template-columns: 1fr;
        }

        .current-plan-actions {
            flex-direction: column;
        }

        .current-plan-actions .btn-primary,
        .current-plan-actions .btn-secondary {
            width: 100%;
            justify-content: center;
        }

        .section-header {
            flex-direction: column;
            align-items: stretch;
        }

        .billing-toggle {
            justify-content: center;
            width: 100%;
        }

        .plans-grid {
            grid-template-columns: 1fr;
        }

        .usage-stats-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    // Plans Data
    const plansData = {
        membership: {
            monthly: [
                {
                    id: 'free',
                    name: 'Free',
                    price: 0,
                    tagline: 'Free Plan',
                    credits: [
                        { label: 'Property Detail', value: '1' },
                        { label: 'Property Search', value: '100' },
                        { label: 'Property Storage', value: '50,000' }
                    ],
                    features: [
                        '7¢ Skip Tracing / record',
                        '2¢ Property Records / record',
                        '1 User',
                        'Basic Direct Mailing System',
                        'Custom Filters Access'
                    ],
                    current: false
                },
                {
                    id: 'gold',
                    name: 'Gold',
                    price: 149,
                    tagline: 'Perfect for growing real estate businesses',
                    popular: true,
                    credits: [
                        { label: 'Record Exports', value: '30,000' },
                        { label: 'Property Detail', value: '200' },
                        { label: 'Property Search', value: '10,000' },
                        { label: 'Property Storage', value: 'Unlimited' }
                    ],
                    features: [
                        '10k Property Records / month',
                        '6¢ Skip Tracing / record',
                        'Unlimited Property List Storage',
                        '10 Users',
                        'Direct Mail',
                        'Marketing Sequence'
                    ],
                    current: true
                },
                {
                    id: 'platinum',
                    name: 'Platinum',
                    price: 399,
                    tagline: 'Ultimate solution for serious real estate professionals',
                    credits: [
                        { label: 'Record Exports', value: '75,000' },
                        { label: 'Property Detail', value: '400' },
                        { label: 'Property Search', value: '25,000' },
                        { label: 'Property Storage', value: 'Unlimited' }
                    ],
                    features: [
                        '25k Property Records / month',
                        '5¢ Skip Tracing / record',
                        'Unlimited Property List Storage',
                        '40 Users',
                        'Direct Mail',
                        'Dialer API Integration'
                    ],
                    current: false
                }
            ],
            annual: [
                {
                    id: 'free',
                    name: 'Free',
                    price: 0,
                    tagline: 'Free Plan',
                    credits: [
                        { label: 'Property Detail', value: '1' },
                        { label: 'Property Search', value: '100' },
                        { label: 'Property Storage', value: '50,000' }
                    ],
                    features: [
                        '7¢ Skip Tracing / record',
                        '2¢ Property Records / record',
                        '1 User',
                        'Basic Direct Mailing System',
                        'Custom Filters Access'
                    ],
                    current: false
                },
                {
                    id: 'gold',
                    name: 'Gold',
                    price: 134,
                    originalPrice: 149,
                    tagline: 'Perfect for growing real estate businesses',
                    popular: true,
                    credits: [
                        { label: 'Record Exports', value: '30,000' },
                        { label: 'Property Detail', value: '200' },
                        { label: 'Property Search', value: '10,000' },
                        { label: 'Property Storage', value: 'Unlimited' }
                    ],
                    features: [
                        '10k Property Records / month',
                        '6¢ Skip Tracing / record',
                        'Unlimited Property List Storage',
                        '10 Users',
                        'Direct Mail',
                        'Marketing Sequence'
                    ],
                    current: true
                },
                {
                    id: 'platinum',
                    name: 'Platinum',
                    price: 359,
                    originalPrice: 399,
                    tagline: 'Ultimate solution for serious real estate professionals',
                    credits: [
                        { label: 'Record Exports', value: '75,000' },
                        { label: 'Property Detail', value: '400' },
                        { label: 'Property Search', value: '25,000' },
                        { label: 'Property Storage', value: 'Unlimited' }
                    ],
                    features: [
                        '25k Property Records / month',
                        '5¢ Skip Tracing / record',
                        'Unlimited Property List Storage',
                        '40 Users',
                        'Direct Mail',
                        'Dialer API Integration'
                    ],
                    current: false
                }
            ]
        },
        skiptrace: {
            monthly: [
                { id: 'st-basic', name: 'Basic Bundle', price: 99, tagline: 'Essential skiptrace tools', credits: [], features: ['5,000 records/month', 'Basic skip tracing'], current: false },
                { id: 'st-pro', name: 'Pro Bundle', price: 199, tagline: 'Advanced skiptrace features', popular: true, credits: [], features: ['15,000 records/month', 'Advanced skip tracing', 'Priority support'], current: false }
            ],
            annual: [
                { id: 'st-basic', name: 'Basic Bundle', price: 89, originalPrice: 99, tagline: 'Essential skiptrace tools', credits: [], features: ['5,000 records/month', 'Basic skip tracing'], current: false },
                { id: 'st-pro', name: 'Pro Bundle', price: 179, originalPrice: 199, tagline: 'Advanced skiptrace features', popular: true, credits: [], features: ['15,000 records/month', 'Advanced skip tracing', 'Priority support'], current: false }
            ]
        },
        leadmax: {
            monthly: [
                { id: 'lm-starter', name: 'Starter Package', price: 79, tagline: 'Get started with lead generation', credits: [], features: ['1,000 leads/month', 'Basic filters'], current: false },
                { id: 'lm-business', name: 'Business Package', price: 249, tagline: 'Scale your lead generation', popular: true, credits: [], features: ['10,000 leads/month', 'Advanced filters', 'CRM integration'], current: false }
            ],
            annual: [
                { id: 'lm-starter', name: 'Starter Package', price: 71, originalPrice: 79, tagline: 'Get started with lead generation', credits: [], features: ['1,000 leads/month', 'Basic filters'], current: false },
                { id: 'lm-business', name: 'Business Package', price: 224, originalPrice: 249, tagline: 'Scale your lead generation', popular: true, credits: [], features: ['10,000 leads/month', 'Advanced filters', 'CRM integration'], current: false }
            ]
        }
    };

    let currentBilling = 'monthly';
    let currentTab = 'membership';

    // Render Plans
    function renderPlans() {
        const grid = document.getElementById('plansGrid');
        const plans = plansData[currentTab][currentBilling];

        grid.innerHTML = plans.map(plan => `
            <div class="plan-card ${plan.current ? 'current' : ''} ${plan.popular ? 'popular' : ''}">
                ${plan.popular ? '<span class="popular-badge">Most Popular</span>' : ''}
                <div class="plan-name">${plan.name}</div>
                <div class="plan-price">
                    <span class="plan-price-amount">$${plan.price}</span>
                    <span class="plan-price-period">/${currentBilling === 'monthly' ? 'month' : 'year'}</span>
                    ${plan.originalPrice ? `<span style="font-size: 0.75rem; color: var(--text-muted); text-decoration: line-through; margin-left: 0.5rem;">$${plan.originalPrice}</span>` : ''}
                </div>
                <div class="plan-tagline">${plan.tagline}</div>
                
                ${plan.credits && plan.credits.length > 0 ? `
                    <div class="credits-title">Credits Included:</div>
                    <div class="credits-list">
                        ${plan.credits.map(credit => `
                            <div class="credit-item">
                                <span class="credit-label">${credit.label}:</span>
                                <span class="credit-value">${credit.value}</span>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}

                <div class="features-title">Key Features:</div>
                <div class="features-list">
                    ${plan.features.map(feature => `
                        <div class="feature-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            ${feature}
                        </div>
                    `).join('')}
                </div>

                <div class="plan-actions">
                    <button class="plan-select-btn ${plan.current ? 'current' : ''}" onclick="selectPlan('${plan.id}')" ${plan.current ? 'disabled' : ''}>
                        ${plan.current ? 'Current Plan' : plan.price === 0 ? 'Select Plan' : 'Upgrade Now'}
                    </button>
                </div>
            </div>
        `).join('');
    }

    // Billing Toggle
    document.querySelectorAll('.toggle-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentBilling = this.dataset.billing;
            renderPlans();
        });
    });

    // Plan Tabs
    document.querySelectorAll('.plan-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.plan-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentTab = this.dataset.tab;
            renderPlans();
        });
    });

    // Functions
    function selectPlan(planId) {
        const plan = plansData[currentTab][currentBilling].find(p => p.id === planId);
        if (!plan) return;

        if (plan.price === 0) {
            alert('Switching to Free plan...');
        } else if (plan.current) {
            alert('This is your current plan.');
        } else {
            if (confirm(`Upgrade to ${plan.name} plan for $${plan.price}/${currentBilling === 'monthly' ? 'month' : 'year'}?`)) {
                alert('Redirecting to payment...');
            }
        }
    }

    function changePlan() {
        document.querySelector('.available-plans-section').scrollIntoView({ behavior: 'smooth' });
    }

    function cancelSubscription() {
        if (confirm('Are you sure you want to cancel your subscription? You will lose access to premium features at the end of your billing period.')) {
            alert('Subscription cancellation request submitted.');
        }
    }

    // Initialize
    renderPlans();
</script>
@endpush

