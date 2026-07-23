@push('styles')
<style>
    .pnl-page .payroll-pnl-module {
        margin-top: 0;
    }

    .payroll-pnl-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.75rem 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid var(--border);
    }

    .payroll-pnl-toolbar-period {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.35rem 0.75rem;
    }

    .payroll-pnl-field-label {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        margin-right: -0.25rem;
    }

    .payroll-pnl-date-input {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .payroll-pnl-select--client {
        min-width: 10rem;
    }

    .payroll-pnl-select--sales-rep {
        min-width: 10rem;
    }

    .payroll-pnl-by-emp-filter-note {
        font-size: 0.8125rem;
        color: var(--accent, #2563eb);
        margin: 0 0 0.5rem 0;
        font-weight: 500;
    }

    .payroll-pnl-toolbar-trail {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-left: auto;
    }

    @media (max-width: 720px) {
        .payroll-pnl-toolbar-trail {
            margin-left: 0;
            width: 100%;
        }
    }

    .payroll-pnl-results[hidden] {
        display: none !important;
    }

    .payroll-pnl-module-inner {
        background: var(--bg-card, #fff);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 1.5rem 1.5rem 1.75rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    }

    .payroll-pnl-module-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .payroll-pnl-module-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
        letter-spacing: -0.02em;
    }

    .payroll-pnl-module-lead {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 0 0 0.75rem 0;
    }

    .payroll-pnl-module-section-label {
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .payroll-pnl-module-muted {
        font-weight: 400;
        color: var(--text-secondary);
        font-size: 0.8125rem;
    }

    .payroll-pnl-select {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-primary);
        color: var(--text-primary);
        cursor: pointer;
    }

    .payroll-pnl-expense-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        background: var(--accent, #2563eb);
        color: #fff;
        cursor: pointer;
    }

    .payroll-pnl-expense-btn:hover {
        filter: brightness(1.05);
    }

    .payroll-pnl-kpi-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.75rem;
    }

    .payroll-pnl-kpi-card {
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.1rem 1.15rem;
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }

    .payroll-pnl-kpi-card--emphasis {
        border-color: var(--accent, #2563eb);
        box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.12);
    }

    .payroll-pnl-kpi-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .payroll-pnl-kpi-label {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        line-height: 1.3;
    }

    .payroll-pnl-kpi-value {
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--text-primary);
        letter-spacing: -0.02em;
    }

    .payroll-pnl-kpi-sub {
        display: block;
        margin-top: 0.2rem;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #b45309;
        line-height: 1.35;
    }

    .payroll-pnl-kpi-sub[hidden] {
        display: none !important;
    }

    .payroll-pnl-cell-main {
        display: block;
        font-weight: 600;
    }

    .payroll-pnl-cell-sub {
        display: block;
        margin-top: 0.15rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #b45309;
        line-height: 1.35;
    }

    .payroll-pnl-paid-unpaid-cell {
        vertical-align: top;
    }

    .payroll-pnl-chart-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin: 0 0 0.75rem 0;
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    .payroll-pnl-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }

    .payroll-pnl-legend-swatch {
        width: 0.85rem;
        height: 0.85rem;
        border-radius: 3px;
        flex-shrink: 0;
    }

    .payroll-pnl-legend-swatch--paid {
        background: linear-gradient(180deg, #34d399 0%, #059669 100%);
    }

    .payroll-pnl-legend-swatch--unpaid {
        background: linear-gradient(180deg, #fcd34d 0%, #d97706 100%);
    }

    .payroll-pnl-kpi-hint {
        display: block;
        margin-top: 0.25rem;
        font-size: 0.75rem;
        color: var(--text-secondary);
        line-height: 1.35;
    }

    .payroll-pnl-kpi-icon {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 8px;
    }

    .payroll-pnl-kpi-icon--up { color: #059669; background: #d1fae5; }
    .payroll-pnl-kpi-icon--down { color: #dc2626; background: #fee2e2; }
    .payroll-pnl-kpi-icon--exp { color: #d97706; background: #fef3c7; }
    .payroll-pnl-kpi-icon--com { color: #7c3aed; background: #ede9fe; }
    .payroll-pnl-kpi-icon--net { color: #dc2626; background: #fee2e2; }

    .payroll-pnl-kpi-value.pnl-amt-pos { color: #059669; }
    .payroll-pnl-kpi-value.pnl-amt-neg { color: #dc2626; }

    .payroll-pnl-subheading {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 0.35rem 0;
    }

    .payroll-pnl-trend {
        margin-bottom: 1.75rem;
        width: 100%;
    }

    .payroll-pnl-trend-caption {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        margin: 0 0 1rem 0;
    }

    .payroll-pnl-trend-chart {
        display: flex;
        align-items: flex-end;
        justify-content: flex-start;
        gap: 0.65rem;
        min-height: 200px;
        padding: 1rem 0.5rem 0.25rem;
        border: 1px solid var(--border);
        border-radius: 12px;
        background: linear-gradient(to bottom, var(--bg-primary) 0%, var(--bg-card) 100%);
        overflow-x: auto;
        box-sizing: border-box;
    }

    .payroll-pnl-trend-chart--dense {
        width: 100%;
        max-width: 100%;
        justify-content: stretch;
        gap: 0.35rem;
        padding-left: 0.75rem;
        padding-right: 0.75rem;
        overflow-x: visible;
    }

    .payroll-pnl-trend-chart--dense .payroll-pnl-bar-col {
        flex: 1 1 0;
        min-width: 0;
        max-width: none;
    }

    .payroll-pnl-trend-chart--dense .payroll-pnl-bar {
        width: 100%;
        max-width: 100%;
    }

    .payroll-pnl-trend-chart--dense .payroll-pnl-bar-label {
        font-size: 0.625rem;
        max-width: 100%;
        white-space: normal;
        word-break: break-word;
    }

    .payroll-pnl-bar-col {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-end;
        min-width: 3.25rem;
        flex: 1;
        max-width: 5rem;
    }

    .payroll-pnl-bar-stack {
        display: flex;
        flex-direction: column-reverse;
        align-items: center;
        justify-content: flex-end;
        width: 100%;
        max-width: 2.75rem;
        min-height: 4px;
        gap: 1px;
    }

    .payroll-pnl-bar {
        width: 100%;
        max-width: 2.75rem;
        min-height: 4px;
        border-radius: 6px 6px 2px 2px;
        transition: height 0.2s ease;
    }

    .payroll-pnl-bar--pos { background: linear-gradient(180deg, #34d399 0%, #059669 100%); }
    .payroll-pnl-bar--neg { background: linear-gradient(180deg, #f87171 0%, #b91c1c 100%); }
    .payroll-pnl-bar--unpaid-pos { background: linear-gradient(180deg, #fcd34d 0%, #d97706 100%); }
    .payroll-pnl-bar--unpaid-neg { background: linear-gradient(180deg, #fdba74 0%, #c2410c 100%); }
    .payroll-pnl-bar--zero { background: #e5e7eb; }

    .payroll-pnl-bar-label {
        font-size: 0.6875rem;
        color: var(--text-secondary);
        margin-top: 0.5rem;
        text-align: center;
        line-height: 1.2;
        white-space: nowrap;
    }

    .payroll-pnl-breakdown { margin-bottom: 1.75rem; }

    .payroll-pnl-table-scroll {
        overflow-x: auto;
        border: 1px solid var(--border);
        border-radius: 10px;
    }

    .payroll-pnl-period-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
        min-width: 36rem;
    }

    .payroll-pnl-period-table thead th {
        text-align: left;
        font-weight: 600;
        color: var(--text-secondary);
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border);
        background: var(--bg-primary);
    }

    .payroll-pnl-period-table thead th.payroll-pnl-num {
        text-align: right;
    }

    .payroll-pnl-period-table tbody td,
    .payroll-pnl-period-table tbody th {
        padding: 0.7rem 1rem;
        border-bottom: 1px solid var(--border);
        color: var(--text-primary);
    }

    .payroll-pnl-period-table tbody th[scope="row"] {
        font-weight: 500;
        text-align: left;
    }

    .payroll-pnl-period-table tbody tr:last-child td,
    .payroll-pnl-period-table tbody tr:last-child th {
        border-bottom: none;
    }

    .payroll-pnl-period-table tfoot th,
    .payroll-pnl-period-table tfoot td {
        padding: 0.85rem 1rem;
        font-weight: 700;
        border-top: 2px solid var(--border);
        background: var(--bg-primary);
    }

    .payroll-pnl-num {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .payroll-pnl-amt-pos { color: #059669; font-weight: 600; }
    .payroll-pnl-amt-neg { color: #dc2626; font-weight: 600; }

    .payroll-pnl-client-group th {
        font-weight: 700;
        background: var(--bg-primary);
        border-top: 1px solid var(--border);
    }

    .payroll-pnl-client-col {
        text-align: left;
        width: 28%;
    }

    .payroll-pnl-employee-col {
        text-align: right;
        width: 22%;
    }

    .payroll-pnl-client-name {
        text-align: left;
    }

    .payroll-pnl-client-spacer {
        padding: 0;
        border-bottom: none;
        background: var(--bg-primary);
    }

    .payroll-pnl-client-employee .payroll-pnl-client-spacer {
        background: transparent;
    }

    .payroll-pnl-client-employee-name {
        font-weight: 500;
        text-align: right;
    }

    .payroll-pnl-empty {
        text-align: center;
        padding: 1.25rem 1rem;
        color: var(--text-secondary);
        font-style: italic;
    }

    .payroll-pnl-emp-table-wrap { margin-top: 0; }

    .payroll-pnl-emp-table {
        min-width: 38rem;
    }

    .payroll-pnl-dimension-grid {
        display: flex;
        flex-direction: column;
        gap: 1.75rem;
        margin-top: 1.75rem;
    }

    .payroll-pnl-dimension-pair {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1.35fr);
        gap: 1.75rem;
        padding-top: 1.75rem;
        border-top: 1px solid var(--border);
        align-items: start;
    }

    @media (max-width: 960px) {
        .payroll-pnl-dimension-pair {
            grid-template-columns: 1fr;
        }
    }

    .payroll-pnl-dimension {
        min-width: 0;
        width: 100%;
    }

    .payroll-pnl-dimension--sales-rep {
        padding-top: 0;
        border-top: none;
    }

    .payroll-pnl-dimension--expenses {
        padding-top: 0;
        border-top: none;
    }

    .payroll-pnl-dimension-chart {
        margin-bottom: 1rem;
        min-height: 180px;
    }

    .payroll-pnl-dimension-table-wrap {
        margin-top: 0;
    }

    .payroll-pnl-dimension .payroll-pnl-period-table {
        width: 100%;
        min-width: 0;
    }

    .payroll-pnl-dimension--sales-rep .payroll-pnl-period-table {
        max-width: none;
    }

    .payroll-pnl-expenses-table .payroll-pnl-expense-notes {
        max-width: 14rem;
        white-space: normal;
        word-break: break-word;
    }

    .payroll-pnl-expense-actions-col {
        width: 7rem;
        text-align: right;
    }

    .payroll-pnl-expense-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.375rem;
        flex-wrap: wrap;
    }

    .payroll-pnl-expense-action-btn {
        border: 1px solid var(--border);
        background: var(--bg-primary);
        color: var(--text-secondary);
        border-radius: 6px;
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        font-weight: 500;
        cursor: pointer;
        line-height: 1.2;
    }

    .payroll-pnl-expense-action-btn:hover {
        color: var(--text-primary);
        border-color: var(--text-secondary);
    }

    .payroll-pnl-expense-action-btn--danger:hover {
        color: #dc2626;
        border-color: #dc2626;
    }

    .payroll-pnl-dimension-chart .payroll-pnl-bar-label {
        font-size: 0.625rem;
        max-width: 4.5rem;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .payroll-pnl-emp-table thead th:first-child,
    .payroll-pnl-emp-table .payroll-pnl-emp-name {
        min-width: 7.5rem;
        max-width: 12rem;
        white-space: normal;
        word-break: break-word;
    }

    .payroll-pnl-emp-table .payroll-pnl-num {
        text-align: right;
        white-space: nowrap;
    }

    .pnl-margin-negative { color: #b91c1c; font-weight: 600; }

    .payroll-pnl-modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 2000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(15, 23, 42, 0.45);
    }

    .payroll-pnl-modal-overlay[hidden] {
        display: none !important;
    }

    .payroll-pnl-modal {
        width: 100%;
        max-width: 28rem;
        max-height: min(90vh, 40rem);
        overflow-y: auto;
        background: var(--bg-card, #fff);
        border-radius: 14px;
        border: 1px solid var(--border);
        box-shadow: 0 20px 50px rgba(0, 0, 0, 0.18);
    }

    .payroll-pnl-modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 1rem 1.15rem;
        border-bottom: 1px solid var(--border);
    }

    .payroll-pnl-modal-title {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .payroll-pnl-modal-close {
        border: none;
        background: transparent;
        font-size: 1.5rem;
        line-height: 1;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 0.15rem 0.35rem;
        border-radius: 6px;
    }

    .payroll-pnl-modal-close:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .payroll-pnl-modal-form {
        padding: 1rem 1.15rem;
        border-bottom: 1px solid var(--border);
    }

    .payroll-pnl-modal-field {
        margin-bottom: 0.9rem;
    }

    .payroll-pnl-modal-field label {
        display: block;
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--text-secondary);
        margin-bottom: 0.35rem;
    }

    .payroll-pnl-modal-input,
    .payroll-pnl-modal-textarea {
        width: 100%;
        padding: 0.5rem 0.65rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        font-family: inherit;
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .payroll-pnl-modal-textarea {
        resize: vertical;
        min-height: 4rem;
    }

    .payroll-pnl-modal-error {
        font-size: 0.8125rem;
        color: #b91c1c;
        margin: 0 0 0.5rem 0;
    }

    .payroll-pnl-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
        margin-top: 0.25rem;
    }

    .payroll-pnl-modal-btn {
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        border: none;
    }

    .payroll-pnl-modal-btn--ghost {
        background: transparent;
        color: var(--text-secondary);
        border: 1px solid var(--border);
    }

    .payroll-pnl-modal-btn--primary {
        background: var(--accent, #5f61e6);
        color: #fff;
    }

    .payroll-pnl-modal-list-wrap {
        padding: 0.85rem 1.15rem 1.1rem;
    }

    .payroll-pnl-modal-sub {
        margin: 0 0 0.5rem 0;
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--text-secondary);
    }

    .payroll-pnl-modal-list {
        list-style: none;
        margin: 0;
        padding: 0;
        max-height: 12rem;
        overflow-y: auto;
    }

    .payroll-pnl-modal-list li {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.55rem 0;
        border-bottom: 1px solid var(--border);
        font-size: 0.8125rem;
    }

    .payroll-pnl-modal-list li:last-child {
        border-bottom: none;
    }

    .payroll-pnl-modal-list-empty {
        list-style: none;
        padding: 0.35rem 0;
        color: var(--text-secondary);
        font-size: 0.8125rem;
    }

    .payroll-pnl-modal-list-meta {
        flex: 1;
        min-width: 0;
        color: var(--text-primary);
    }

    .payroll-pnl-modal-list-date {
        color: var(--text-secondary);
        font-size: 0.75rem;
    }

    .payroll-pnl-modal-list-remove {
        flex-shrink: 0;
        border: none;
        background: transparent;
        color: #b91c1c;
        font-size: 0.75rem;
        font-weight: 600;
        cursor: pointer;
        padding: 0.15rem 0.25rem;
    }
</style>
@endpush
