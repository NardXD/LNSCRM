<!-- Billing Management Section -->
<div class="admin-section-card" id="billing">
    <div class="section-card-header">
        <div class="section-card-title">
            <div class="section-icon blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
            </div>
            <div>
                <h2 class="section-title">Billing Management</h2>
                <p class="section-subtitle">Control subscriptions, plans, and payments</p>
            </div>
        </div>
    </div>

    <div class="section-card-body">
        <!-- Subscription Plans -->
        <div class="admin-subsection">
            <div class="subsection-header">
                <h3 class="subsection-title">Subscription Plans</h3>
                <button class="btn-sm btn-primary" onclick="openAddPlanModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Add Plan
                </button>
            </div>
            <div class="plans-grid" id="plansGrid">
                @foreach($plans ?? [] as $plan)
                    @php
                        $period = $plan->billing_cycle === 'yearly' ? 'year' : 'month';
                        $features = is_array($plan->features) ? $plan->features : [];
                    @endphp
                    <div class="plan-card {{ $plan->is_featured ? 'featured' : '' }}">
                        <div class="plan-header">
                            <h4 class="plan-name">{{ $plan->name }}</h4>
                            @if($plan->is_featured)
                                <span class="plan-badge">Popular</span>
                            @endif
                        </div>
                        <div class="plan-price">
                            ${{ number_format($plan->price, 0) }}<span>/{{ $period }}</span>
                        </div>
                        <ul class="plan-features">
                            @foreach($features as $feature)
                                <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>{{ $feature }}</li>
                            @endforeach
                        </ul>
                        <div class="plan-actions">
                            <button class="btn-sm btn-secondary" onclick="editPlan({{ $plan->id }})">Edit</button>
                            <button class="btn-sm btn-secondary" onclick="deletePlan({{ $plan->id }})">Delete</button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Company Billing Overview -->
        <div class="admin-subsection">
            <div class="subsection-header">
                <h3 class="subsection-title">Company Billing Overview</h3>
                <div class="filter-group">
                    <select class="filter-select" id="billingFilter">
                        <option value="all">All Companies</option>
                        <option value="active">Active</option>
                        <option value="trial">Trial</option>
                        <option value="expired">Expired</option>
                        <option value="suspended">Suspended</option>
                    </select>
                    <input type="text" class="search-input" placeholder="Search companies..." id="billingSearch">
                </div>
            </div>
            <div class="table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Billing Cycle</th>
                            <th>Amount</th>
                            <th>Next Billing</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="billingTableBody">
                        @foreach($companies ?? [] as $company)
                            <tr>
                                <td><strong>{{ $company['name'] }}</strong></td>
                                <td>{{ $company['plan'] ?? 'N/A' }}</td>
                                <td><span class="status-badge {{ $company['status'] }}">{{ ucfirst($company['status']) }}</span></td>
                                <td>{{ $company['billing_cycle'] ?? 'N/A' }}</td>
                                <td>${{ number_format($company['amount'], 0) }}</td>
                                <td>{{ $company['next_billing'] ?? '-' }}</td>
                                <td>
                                    <button class="btn-sm btn-secondary" onclick="manageBilling({{ $company['id'] }})">Manage</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment History -->
        <div class="admin-subsection">
            <div class="subsection-header">
                <h3 class="subsection-title">Recent Payments</h3>
                <a href="#" class="link-text">View All</a>
            </div>
            <div class="payments-list" id="paymentsList">
                @foreach($recentPayments ?? [] as $payment)
                    <div class="payment-item">
                        <div class="payment-info">
                            <div class="payment-company">{{ $payment['company'] }}</div>
                            <p class="payment-details">{{ $payment['date'] }} • {{ $payment['method'] }}</p>
                        </div>
                        <div class="payment-amount">${{ number_format($payment['amount'], 0) }}</div>
                        <span class="status-badge {{ $payment['status'] }}">{{ ucfirst($payment['status']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

