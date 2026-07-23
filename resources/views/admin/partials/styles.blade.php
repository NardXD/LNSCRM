<style>
    /* Admin Sections Grid */
    .admin-sections-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 2rem;
        margin-top: 2rem;
    }

    .admin-section-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
    }

    .section-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        padding: 1.5rem;
        border-bottom: 1px solid var(--border);
        background: var(--bg-primary);
    }

    .section-card-title {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .section-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .section-icon svg {
        width: 24px;
        height: 24px;
    }

    .section-icon.blue {
        background: #dbeafe;
        color: #2563eb;
    }

    .section-icon.green {
        background: #d1fae5;
        color: #059669;
    }

    .section-icon.purple {
        background: #ede9fe;
        color: #7c3aed;
    }

    .section-icon.orange {
        background: #fed7aa;
        color: #ea580c;
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .section-subtitle {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .section-card-body {
        padding: 1.5rem;
    }

    /* Admin Subsections */
    .admin-subsection {
        margin-bottom: 2rem;
    }

    .admin-subsection:last-child {
        margin-bottom: 0;
    }

    .subsection-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 1rem;
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
        margin: 0;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .subsection-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    /* Plans Grid */
    .plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
    }

    .plan-card {
        background: var(--bg-primary);
        border: 2px solid var(--border);
        border-radius: 8px;
        padding: 1.5rem;
        transition: all 0.15s;
    }

    .plan-card:hover {
        border-color: var(--accent);
        transform: translateY(-2px);
    }

    .plan-card.featured {
        border-color: var(--accent);
        background: var(--accent-light);
    }

    .plan-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
    }

    .plan-name {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .plan-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
        background: var(--accent);
        color: white;
    }

    .plan-price {
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0.5rem 0;
    }

    .plan-price span {
        font-size: 1rem;
        color: var(--text-secondary);
        font-weight: 400;
    }

    .plan-features {
        list-style: none;
        padding: 0;
        margin: 1rem 0;
    }

    .plan-features li {
        padding: 0.5rem 0;
        font-size: 0.875rem;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .plan-features li svg {
        width: 16px;
        height: 16px;
        color: #059669;
        flex-shrink: 0;
    }

    .plan-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
        flex-wrap: wrap;
    }

    /* Admin Table */
    .table-container {
        overflow-x: auto;
        border: 1px solid var(--border);
        border-radius: 8px;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
    }

    .admin-table thead {
        background: var(--bg-primary);
    }

    .admin-table th {
        padding: 0.75rem 1rem;
        text-align: left;
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-secondary);
        border-bottom: 1px solid var(--border);
    }

    .admin-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--border);
        font-size: 0.875rem;
        color: var(--text-primary);
    }

    .admin-table tbody tr:hover {
        background: var(--bg-primary);
    }

    .admin-table tbody tr:last-child td {
        border-bottom: none;
    }

    .admin-table .btn-sm {
        margin: 0 0.25rem;
    }

    .empty-state {
        padding: 3rem 2rem;
        text-align: center;
        font-size: 0.9375rem;
        color: var(--text-secondary);
        background: var(--bg-primary);
        border-radius: 8px;
        margin: 0;
    }

    /* Status Badges */
    .status-badge {
        padding: 0.375rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
    }

    .status-badge.active {
        background: #d1fae5;
        color: #059669;
    }

    .status-badge.trial {
        background: #dbeafe;
        color: #2563eb;
    }

    .status-badge.expired {
        background: #fee2e2;
        color: #dc2626;
    }

    .status-badge.suspended {
        background: #fef3c7;
        color: #d97706;
    }

    /* Features Grid */
    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
    }

    .feature-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .feature-info {
        flex: 1;
    }

    .feature-name {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .feature-desc {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Toggle Switch */
    .toggle-switch {
        position: relative;
        width: 44px;
        height: 24px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #cbd5e1;
        transition: 0.3s;
        border-radius: 24px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: 0.3s;
        border-radius: 50%;
    }

    .toggle-switch input:checked + .toggle-slider {
        background-color: var(--accent);
    }

    .toggle-switch input:checked + .toggle-slider:before {
        transform: translateX(20px);
    }

    /* Payments List */
    .payments-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .payment-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 8px;
        border: 1px solid var(--border);
        gap: 1rem;
        flex-wrap: wrap;
    }

    .payment-info {
        flex: 1;
    }

    .payment-company {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .payment-details {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .payment-amount {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-right: 1rem;
    }

    /* Roles List */
    .roles-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .role-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .role-info {
        flex: 1;
    }

    .role-name {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .role-users {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Access Logs */
    .access-logs {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        max-height: 400px;
        overflow-y: auto;
    }

    .log-item {
        padding: 0.75rem;
        background: var(--bg-primary);
        border-radius: 8px;
        border-left: 3px solid var(--accent);
    }

    .log-text {
        font-size: 0.875rem;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .log-meta {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Settings List */
    .settings-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .setting-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 8px;
        border: 1px solid var(--border);
        gap: 1rem;
    }

    .setting-info {
        flex: 1;
    }

    .setting-name {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .setting-desc {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Health Metrics */
    .health-metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .health-metric {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 1rem;
        text-align: center;
    }

    .health-metric-value {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0.5rem 0;
    }

    .health-metric-label {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .health-metric.good .health-metric-value {
        color: #059669;
    }

    .health-metric.warning .health-metric-value {
        color: #d97706;
    }

    .health-metric.error .health-metric-value {
        color: #dc2626;
    }

    /* Support Actions Grid */
    .support-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
    }

    .support-action-card {
        background: var(--bg-primary);
        border: 2px solid var(--border);
        border-radius: 8px;
        padding: 1.5rem;
        cursor: pointer;
        transition: all 0.15s;
        text-align: left;
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        width: 100%;
    }

    .support-action-card:hover {
        border-color: var(--accent);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .support-action-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .support-action-icon svg {
        width: 24px;
        height: 24px;
    }

    .support-action-icon.blue {
        background: #dbeafe;
        color: #2563eb;
    }

    .support-action-icon.red {
        background: #fee2e2;
        color: #dc2626;
    }

    .support-action-icon.orange {
        background: #fed7aa;
        color: #ea580c;
    }

    .support-action-icon.purple {
        background: #ede9fe;
        color: #7c3aed;
    }

    .support-action-content {
        flex: 1;
    }

    .support-action-content h4 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 0.5rem 0;
    }

    .support-action-content p {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .support-badge {
        padding: 0.5rem 1rem;
        background: #d1fae5;
        color: #059669;
        border-radius: 100px;
        font-size: 0.875rem;
        font-weight: 500;
    }

    .badge-count {
        padding: 0.375rem 0.75rem;
        background: var(--accent-light);
        color: var(--accent);
        border-radius: 100px;
        font-size: 0.875rem;
        font-weight: 500;
    }

    /* Support Sessions */
    .support-sessions-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .support-session-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-left: 4px solid var(--accent);
        border-radius: 8px;
        padding: 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .support-session-info {
        flex: 1;
    }

    .support-session-company {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .support-session-details {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .support-session-time {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-left: 1rem;
    }

    .support-session-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        align-items: center;
    }

    /* Support Actions Log */
    .support-actions-log {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        max-height: 400px;
        overflow-y: auto;
    }

    .support-action-log-item {
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 8px;
        border-left: 4px solid var(--accent);
        display: flex;
        align-items: flex-start;
        gap: 1rem;
    }

    .support-action-log-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .support-action-log-icon.bypass {
        background: #fee2e2;
        color: #dc2626;
    }

    .support-action-log-icon.grant {
        background: #d1fae5;
        color: #059669;
    }

    .support-action-log-icon.emergency {
        background: #fed7aa;
        color: #ea580c;
    }

    .support-action-log-content {
        flex: 1;
    }

    .support-action-log-text {
        font-size: 0.875rem;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .support-action-log-meta {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Alert Warning */
    .alert-warning {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 1rem;
        background: #fef3c7;
        border: 1px solid #fcd34d;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }

    .alert-warning svg {
        width: 24px;
        height: 24px;
        color: #d97706;
        flex-shrink: 0;
    }

    .alert-warning div {
        flex: 1;
        font-size: 0.875rem;
        color: #92400e;
    }

    .btn-danger {
        background: #dc2626;
        color: white;
    }

    .btn-danger:hover {
        background: #b91c1c;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(220, 38, 38, 0.2);
    }

    .btn-danger:active {
        transform: translateY(0);
    }

    .review-actions-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 8px;
        border: 1px solid var(--border);
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    /* Support Tickets */
    .support-tickets-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        max-height: 500px;
        overflow-y: auto;
    }

    .support-ticket-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 1rem;
        cursor: pointer;
        transition: all 0.15s;
    }

    .support-ticket-card:hover {
        border-color: var(--accent);
        background: var(--accent-light);
    }

    .support-ticket-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }

    .support-ticket-id {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .support-ticket-status {
        padding: 0.25rem 0.5rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .support-ticket-status.open {
        background: #dbeafe;
        color: #2563eb;
    }

    .support-ticket-status.in-progress {
        background: #fef3c7;
        color: #d97706;
    }

    .support-ticket-status.resolved {
        background: #d1fae5;
        color: #059669;
    }

    .support-ticket-status.closed {
        background: #e5e7eb;
        color: #6b7280;
    }

    .support-ticket-subject {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .support-ticket-meta {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Buttons */
    .btn-primary, .btn-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
        border: none;
        white-space: nowrap;
        -webkit-tap-highlight-color: transparent;
        line-height: 1.5;
    }

    .btn-primary {
        background: var(--accent);
        color: white;
    }

    .btn-primary:hover {
        background: var(--accent-hover);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(95, 97, 230, 0.2);
    }

    .btn-primary:active {
        transform: translateY(0);
    }

    .btn-secondary {
        background: var(--bg-primary);
        color: var(--text-primary);
        border: 1px solid var(--border);
    }

    .btn-secondary:hover {
        background: var(--border);
        border-color: var(--accent);
        color: var(--accent);
    }

    .btn-primary svg, .btn-secondary svg {
        width: 18px;
        height: 18px;
        flex-shrink: 0;
    }

    .btn-sm {
        padding: 0.5rem 0.875rem;
        font-size: 0.8125rem;
        border-radius: 6px;
        gap: 0.375rem;
    }

    .btn-sm svg {
        width: 16px;
        height: 16px;
    }

    /* Form Controls */
    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        color: var(--text-primary);
        background: var(--bg-card);
        transition: all 0.15s;
        font-family: inherit;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-light);
    }

    .form-control::placeholder {
        color: var(--text-muted);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .filter-group {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .filter-select {
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .filter-select:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-light);
    }

    .search-box {
        position: relative;
        display: flex;
        align-items: center;
    }

    .search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        width: 18px;
        height: 18px;
        color: var(--text-secondary);
        pointer-events: none;
    }

    .search-input {
        padding: 0.625rem 0.75rem 0.625rem 2.5rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        width: 250px;
        background: var(--bg-card);
        color: var(--text-primary);
        transition: all 0.15s;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-light);
        width: 300px;
    }

    .link-text {
        font-size: 0.875rem;
        color: var(--accent);
        text-decoration: none;
        font-weight: 500;
    }

    .link-text:hover {
        color: var(--accent-hover);
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        align-items: center;
        justify-content: center;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: var(--bg-card);
        border-radius: 12px;
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .modal-content.modal-large {
        max-width: 900px;
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 0;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.15s;
        flex-shrink: 0;
    }

    .modal-close:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .modal-close svg {
        width: 20px;
        height: 20px;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        padding: 1.5rem;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
    }

    /* Company Module Modal Styles */
    .company-info-header {
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border);
    }

    .company-info-header h4 {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .company-module-subtitle {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .modules-selection-container {
        margin: 1.5rem 0;
    }

    .modules-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1rem;
        max-height: 400px;
        overflow-y: auto;
        padding: 0.5rem;
    }

    .module-card {
        background: var(--bg-primary);
        border: 2px solid var(--border);
        border-radius: 8px;
        padding: 1rem;
        cursor: pointer;
        transition: all 0.15s;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .module-card:hover {
        border-color: var(--accent);
        background: var(--accent-light);
    }

    .module-card.selected {
        border-color: var(--accent);
        background: var(--accent-light);
    }

    .module-checkbox {
        width: 20px;
        height: 20px;
        border: 2px solid var(--border);
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: all 0.15s;
    }

    .module-card.selected .module-checkbox {
        background: var(--accent);
        border-color: var(--accent);
    }

    .module-card.selected .module-checkbox::after {
        content: 'âœ“';
        color: white;
        font-size: 14px;
        font-weight: bold;
    }

    .module-info {
        flex: 1;
        min-width: 0;
    }

    .module-name {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .module-desc {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .module-actions-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 8px;
        border: 1px solid var(--border);
        margin-top: 1rem;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .module-count {
        font-size: 0.875rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    /* Responsive */
    @media (max-width: 768px) {
        /* Stats Grid */
        .stats-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .stat-card {
            padding: 1rem;
        }

        .stat-value {
            font-size: 1.5rem;
        }

        /* Admin Sections */
        .admin-sections-grid {
            gap: 1rem;
            margin-top: 1rem;
        }

        .section-card-header {
            padding: 1rem;
        }

        .section-card-body {
            padding: 1rem;
        }

        .section-card-title {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .section-icon {
            width: 40px;
            height: 40px;
        }

        .section-icon svg {
            width: 20px;
            height: 20px;
        }

        /* Grids */
        .plans-grid {
            grid-template-columns: 1fr;
        }

        .features-grid {
            grid-template-columns: 1fr;
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .admin-table {
            min-width: 600px;
        }

        .admin-table th,
        .admin-table td {
            padding: 0.75rem 0.5rem;
            font-size: 0.8125rem;
        }

        .health-metrics {
            grid-template-columns: 1fr;
        }

        .modules-grid {
            grid-template-columns: 1fr;
        }

        .module-actions-bar {
            flex-direction: column;
            gap: 0.75rem;
            align-items: stretch;
        }

        .support-actions-grid {
            grid-template-columns: 1fr;
        }

        .support-session-card {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .support-session-actions {
            width: 100%;
        }

        .support-session-actions .btn-sm {
            width: 100%;
            justify-content: center;
        }

        .review-actions-bar {
            flex-direction: column;
            gap: 0.75rem;
        }

        /* Section Headers */
        .section-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .header-actions {
            width: 100%;
            flex-direction: column;
        }

        .subsection-header {
            flex-direction: column;
            align-items: flex-start;
        }

        /* Buttons */
        .btn-primary,
        .btn-secondary {
            width: 100%;
            justify-content: center;
        }

        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
        }

        /* Forms */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-control {
            font-size: 16px; /* Prevents zoom on iOS */
        }

        .filter-group {
            flex-direction: column;
            width: 100%;
        }

        .filter-select {
            width: 100%;
        }

        .search-box {
            width: 100%;
        }

        .search-input {
            width: 100% !important;
        }

        /* Modals */
        .modal-content {
            width: 95%;
            max-width: 95%;
            margin: 1rem;
            max-height: 95vh;
        }

        .modal-header {
            padding: 1rem;
        }

        .modal-body {
            padding: 1rem;
        }

        .modal-footer {
            padding: 1rem;
            flex-direction: column;
        }

        .modal-footer .btn-primary,
        .modal-footer .btn-secondary {
            width: 100%;
        }

        .modal-actions {
            flex-direction: column;
        }

        .modal-actions .btn-primary,
        .modal-actions .btn-secondary {
            width: 100%;
        }

        /* Cards */
        .role-card {
            padding: 1rem;
        }

        .role-card-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .role-actions {
            width: 100%;
        }

        .role-actions .btn-sm {
            flex: 1;
        }

        .permission-card {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .feature-card {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .setting-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .payment-item {
            flex-direction: column;
            align-items: flex-start;
        }

        .payment-amount {
            margin-right: 0;
            margin-top: 0.5rem;
        }

        /* Support Cards */
        .support-action-card {
            flex-direction: column;
            text-align: left;
        }

        .support-action-icon {
            width: 40px;
            height: 40px;
        }

        .support-action-icon svg {
            width: 20px;
            height: 20px;
        }

        .support-action-log-item {
            flex-direction: column;
            gap: 0.75rem;
        }

        .support-action-log-icon {
            width: 32px;
            height: 32px;
        }

        /* Plan Cards */
        .plan-card {
            padding: 1rem;
        }

        .plan-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .plan-actions {
            width: 100%;
            flex-direction: column;
        }

        .plan-actions .btn-sm {
            width: 100%;
        }

        /* Module Cards */
        .module-card {
            padding: 0.75rem;
        }

        /* Company Info Header */
        .company-info-header {
            padding-bottom: 0.75rem;
            margin-bottom: 1rem;
        }

        .company-info-header h4 {
            font-size: 1.125rem;
        }

        /* Tabs */
        .management-tabs {
            flex-wrap: wrap;
            padding: 0.75rem;
        }

        .management-tabs .tab-btn {
            flex: 1;
            min-width: calc(50% - 0.25rem);
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
        }

        .tab-content {
            padding: 1rem;
        }

        /* Permissions Checklist */
        .permissions-checklist {
            max-height: 250px;
        }

        .permission-checkbox-item {
            padding: 0.5rem;
        }

        /* Alert */
        .alert-warning {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
    }

    /* Extra Small Devices */
    @media (max-width: 480px) {
        .stat-value {
            font-size: 1.25rem;
        }

        .section-title {
            font-size: 1.125rem;
        }

        .page-title {
            font-size: 1.5rem;
        }

        .page-subtitle {
            font-size: 0.875rem;
        }

        .modal-content {
            width: 100%;
            max-width: 100%;
            margin: 0;
            border-radius: 0;
            max-height: 100vh;
        }

        .management-tabs .tab-btn {
            min-width: 100%;
            font-size: 0.8125rem;
        }
    }
</style>
