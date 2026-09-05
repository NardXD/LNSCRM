<style>
    /* Base layout — matches /leads */
    .ld-page {
        max-width: 1200px;
        font-size: 0.8125rem;
    }

    .ld-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
        flex-wrap: wrap;
    }

    .ld-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 0.15rem;
        line-height: 1.3;
    }

    .ld-subtitle {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
        max-width: 52rem;
        line-height: 1.4;
    }

    .ld-top-actions,
    .leads-header-actions {
        display: flex;
        gap: 0.35rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .ld-page .btn,
    .ld-page a.btn {
        padding: 0.35rem 0.7rem;
        margin: 0.35rem 0;
        font-size: 0.75rem;
        border-radius: 6px;
        gap: 0.3rem;
        font-weight: 500;
        line-height: 1.35;
        text-decoration: none;
    }

    .ld-page a.btn:hover,
    .ld-page a.btn:focus,
    .ld-page a.btn:active {
        text-decoration: none;
    }

    .ld-page .btn svg {
        width: 14px;
        height: 14px;
    }

    .ld-page .btn-sm,
    .ld-page a.btn-sm {
        padding: 0.3rem 0.6rem;
        margin: 0.3rem 0;
        font-size: 0.6875rem;
    }

    .leads-toolbar {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
        margin-bottom: 0.6rem;
    }

    .leads-toolbar .leads-search {
        width: 100%;
    }

    .leads-toolbar-filters {
        display: flex;
        gap: 0.45rem;
        align-items: center;
        flex-wrap: nowrap;
        overflow-x: auto;
    }

    .leads-toolbar-filters .leads-label-filter {
        flex: 0 0 auto;
    }

    .leads-toolbar-filters .leads-source-filter,
    .leads-toolbar-filters .leads-assignee-filter,
    .leads-toolbar-filters .leads-thread-filter,
    .leads-toolbar-filters .leads-sort-filter {
        width: auto;
        flex: 0 0 auto;
    }

    .leads-toolbar-stack {
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
        margin-bottom: 0.6rem;
        width: 100%;
        max-width: 720px;
    }

    .leads-toolbar-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 0.45rem;
    }

    .leads-toolbar-row-3 {
        grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.9fr) minmax(0, 0.9fr);
    }

    .leads-search {
        flex: 1;
        min-width: 180px;
        width: 100%;
        padding: 0.4rem 0.6rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.75rem;
        background: var(--bg-card);
        color: var(--text-primary);
    }

    .leads-search:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 2px rgba(95, 97, 230, 0.12);
    }

    .leads-label-filter,
    .qb-label-filter {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.3rem;
        min-width: 0;
        padding: 0.25rem 0.4rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        background: var(--bg-card);
    }

    .leads-label-filter select,
    .qb-label-filter-select {
        border: none;
        background: transparent;
        font-size: 0.75rem;
        color: var(--text-primary);
        min-width: 120px;
        padding: 0.2rem 0.15rem;
        flex: 1;
    }

    .leads-source-filter,
    .leads-assignee-filter,
    .leads-thread-filter,
    .leads-sort-filter,
    .qb-assignee-filter {
        min-width: 0;
        width: 100%;
        padding: 0.4rem 0.55rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.75rem;
        background: var(--bg-card);
        color: var(--text-primary);
    }

    .lead-label-filter-chips,
    .qb-label-filter-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem;
    }

    .leads-tabs {
        display: flex;
        gap: 0.2rem;
        flex-wrap: wrap;
        align-items: center;
        margin: 0 0 0.5rem;
    }

    .leads-tab {
        border: 1px solid var(--border);
        background: var(--bg-card);
        color: var(--text-secondary);
        border-radius: 6px;
        padding: 0.3rem 0.55rem;
        font-size: 0.6875rem;
        font-weight: 600;
        cursor: pointer;
        margin: 0.2rem 0;
        font-family: inherit;
        line-height: 1.3;
    }

    .leads-tab span {
        margin-left: 0.15rem;
        opacity: 0.75;
        font-weight: 700;
    }

    .leads-tab.active {
        background: var(--accent);
        border-color: var(--accent);
        color: #fff;
    }

    .leads-followup-row {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        flex-wrap: wrap;
        margin: 0 0 0.6rem;
    }

    .leads-followup-chips {
        display: flex;
        gap: 0.25rem;
        flex-wrap: wrap;
        flex: 1;
    }

    .leads-followup-chip {
        border: 1px solid var(--border);
        background: var(--bg-card);
        color: var(--text-secondary);
        border-radius: 6px;
        padding: 0.28rem 0.55rem;
        font-size: 0.6875rem;
        font-weight: 600;
        cursor: pointer;
        margin: 0.2rem 0;
        font-family: inherit;
    }

    .leads-followup-chip span {
        margin-left: 0.15rem;
        opacity: 0.75;
    }

    .leads-followup-chip.active {
        background: var(--bg-primary);
        border-color: var(--accent);
        color: var(--accent);
    }

    .leads-card {
        position: relative;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
    }

    .leads-table,
    .ld-page .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
    }

    .leads-table th,
    .ld-page .data-table th {
        text-align: left;
        padding: 0.45rem 0.65rem;
        font-size: 0.625rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-secondary);
        border-bottom: 1px solid var(--border);
        background: var(--bg-primary);
        white-space: nowrap;
    }

    .leads-table td,
    .ld-page .data-table td {
        padding: 0.5rem 0.65rem;
        border-bottom: 1px solid var(--border);
        vertical-align: top;
    }

    .leads-table tbody tr,
    .ld-page .data-table tbody tr {
        cursor: default;
    }

    .leads-table tbody tr:hover,
    .ld-page .data-table tbody tr:hover {
        background: var(--bg-primary);
    }

    .leads-table tbody tr.is-clickable,
    .ld-page .data-table tbody tr.is-clickable {
        cursor: pointer;
    }

    .lead-name {
        font-weight: 600;
        font-size: 0.75rem;
        color: var(--text-primary);
    }

    .lead-badge {
        display: inline-block;
        font-size: 0.625rem;
        font-weight: 700;
        text-transform: uppercase;
        padding: 0.1rem 0.4rem;
        border-radius: 999px;
        background: #eef2ff;
        color: #4338ca;
    }

    .lead-badge.contacted { background: #e0f2fe; color: #0369a1; }
    .lead-badge.qualified { background: #dcfce7; color: #166534; }
    .lead-badge.converted { background: #d1fae5; color: #065f46; }
    .lead-badge.lost { background: #fee2e2; color: #991b1b; }
    .lead-badge.snoozed { background: #fef3c7; color: #92400e; }
    .lead-badge.archived { background: #e2e8f0; color: #475569; }
    .lead-badge.new { background: #eef2ff; color: #4338ca; }

    .lead-label-chip,
    .qb-label-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.1rem 0.4rem;
        border-radius: 999px;
        font-size: 0.625rem;
        font-weight: 700;
    }

    .inbox-pill {
        display: inline-flex; align-items: center; gap: 0.25rem;
        font-size: 0.68rem; font-weight: 600; padding: 0.1rem 0.4rem; border-radius: 999px;
        background: #f1f5f9; color: #334155;
    }

    .lead-label-chip button,
    .qb-label-chip button {
        background: none;
        border: none;
        color: inherit;
        cursor: pointer;
        font-size: 0.9rem;
        line-height: 1;
        padding: 0;
        opacity: 0.8;
    }

    .empty-state {
        text-align: center;
        color: var(--text-secondary);
        padding: 1.25rem 0.75rem !important;
        font-size: 0.75rem;
    }

    .leads-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.45rem 0.65rem;
        font-size: 0.6875rem;
        color: var(--text-secondary);
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .leads-pagination > div {
        display: flex;
        gap: 0.35rem;
        align-items: center;
    }

    .ld-loading {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--text-secondary);
        font-size: 0.75rem;
    }

    .ld-spinner,
    .leads-spinner {
        width: 1.2rem;
        height: 1.2rem;
        border: 2px solid var(--border);
        border-top-color: var(--accent);
        border-radius: 50%;
        animation: ld-spin 0.7s linear infinite;
        display: inline-block;
        flex-shrink: 0;
    }

    @keyframes ld-spin {
        to { transform: rotate(360deg); }
    }

    /* Settings pages — same visual language as /leads cards */
    .ld-settings-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 280px;
        gap: 0.75rem;
        align-items: start;
    }

    .ld-settings-card,
    .ld-settings-aside {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 0.85rem 1rem;
    }

    .ld-settings-card-header {
        display: flex;
        align-items: flex-start;
        gap: 0.65rem;
        margin-bottom: 0.85rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid var(--border);
    }

    .ld-settings-icon {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: var(--accent-light);
    }

    .ld-settings-icon svg {
        width: 16px;
        height: 16px;
        color: var(--accent);
    }

    .ld-settings-icon.m365 {
        background: #eff6ff;
    }

    .ld-settings-icon.m365 svg {
        color: #2563eb;
    }

    .ld-settings-heading h2 {
        margin: 0 0 0.2rem;
        font-size: 0.875rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .ld-settings-heading p {
        margin: 0;
        font-size: 0.75rem;
        color: var(--text-secondary);
        line-height: 1.45;
    }

    .ld-flash {
        padding: 0.55rem 0.75rem;
        border-radius: 6px;
        font-size: 0.75rem;
        border: 1px solid transparent;
        margin-bottom: 0.75rem;
    }

    .ld-flash.success {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #065f46;
    }

    .ld-flash.error {
        background: #fef2f2;
        border-color: #fecaca;
        color: #991b1b;
    }

    .ld-flash.info {
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #1e40af;
    }

    .ld-inline-alert {
        margin-top: 0.75rem;
    }

    .ld-form-group {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        margin-bottom: 0.75rem;
    }

    .ld-form-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .ld-form-input,
    .ld-form-textarea,
    .ld-form-select {
        width: 100%;
        padding: 0.4rem 0.6rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.75rem;
        background: var(--bg-card);
        color: var(--text-primary);
    }

    .ld-form-textarea {
        min-height: 220px;
        resize: vertical;
        line-height: 1.5;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.72rem;
    }

    .ld-form-input:focus,
    .ld-form-textarea:focus,
    .ld-form-select:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 2px rgba(95, 97, 230, 0.12);
    }

    .ld-form-help {
        margin: 0;
        font-size: 0.6875rem;
        color: var(--text-secondary);
        line-height: 1.45;
    }

    .ld-form-help code {
        padding: 0.08rem 0.3rem;
        border-radius: 4px;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        font-size: 0.65rem;
    }

    .ld-form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        padding-top: 0.75rem;
        margin-top: 0.25rem;
        border-top: 1px solid var(--border);
    }

    .ld-aside-title {
        margin: 0 0 0.25rem;
        font-size: 0.8125rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .ld-aside-text {
        margin: 0 0 0.75rem;
        font-size: 0.75rem;
        color: var(--text-secondary);
        line-height: 1.45;
    }

    .ld-steps {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 0.65rem;
    }

    .ld-steps li {
        display: grid;
        grid-template-columns: 1.25rem 1fr;
        gap: 0.55rem;
        align-items: start;
        font-size: 0.75rem;
        color: var(--text-secondary);
        line-height: 1.4;
    }

    .ld-step-num {
        width: 1.25rem;
        height: 1.25rem;
        border-radius: 999px;
        background: var(--accent-light);
        color: var(--accent);
        font-size: 0.6875rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .ld-placeholder-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 0.45rem;
    }

    .ld-placeholder-item {
        display: grid;
        gap: 0.15rem;
    }

    .ld-placeholder-btn {
        display: inline-flex;
        align-items: center;
        justify-content: flex-start;
        width: 100%;
        padding: 0.35rem 0.5rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        background: var(--bg-primary);
        cursor: pointer;
        transition: border-color 0.15s, background 0.15s;
    }

    .ld-placeholder-btn:hover {
        border-color: var(--accent);
        background: var(--accent-light);
    }

    .ld-placeholder-btn code {
        font-size: 0.6875rem;
        color: var(--accent);
        background: transparent;
        padding: 0;
    }

    .ld-placeholder-desc {
        font-size: 0.6875rem;
        color: var(--text-muted);
        padding-left: 0.1rem;
    }

    .ld-status-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-bottom: 0.75rem;
    }

    .ld-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.25rem 0.6rem;
        border-radius: 999px;
        font-size: 0.6875rem;
        font-weight: 600;
        border: 1px solid transparent;
    }

    .ld-status-pill::before {
        content: '';
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: currentColor;
        opacity: 0.85;
    }

    .ld-status-pill[data-status="connected"] {
        background: #ecfdf5;
        color: #047857;
        border-color: #a7f3d0;
    }

    .ld-status-pill[data-status="disconnected"] {
        background: var(--bg-primary);
        color: var(--text-secondary);
        border-color: var(--border);
    }

    .ld-status-pill[data-status="loading"] {
        background: #eff6ff;
        color: #1d4ed8;
        border-color: #bfdbfe;
    }

    .ld-connected-box {
        display: none;
        padding: 0.65rem 0.75rem;
        border-radius: 6px;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        margin-bottom: 0.75rem;
    }

    .ld-connected-box.visible {
        display: block;
    }

    .ld-connected-label {
        display: block;
        font-size: 0.625rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-muted);
        margin-bottom: 0.25rem;
    }

    .ld-connected-email {
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--text-primary);
        word-break: break-word;
    }

    .ld-connected-name {
        margin-top: 0.15rem;
        font-size: 0.75rem;
        color: var(--text-secondary);
    }

    .ld-preview-section {
        margin-top: 0.85rem;
        padding-top: 0.85rem;
        border-top: 1px solid var(--border);
    }

    .ld-preview-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.65rem;
    }

    .ld-preview-header h3 {
        margin: 0 0 0.15rem;
        font-size: 0.8125rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .ld-preview-header p {
        margin: 0;
        font-size: 0.6875rem;
        color: var(--text-secondary);
        line-height: 1.4;
    }

    .ld-preview-badge {
        flex-shrink: 0;
        padding: 0.15rem 0.45rem;
        border-radius: 999px;
        font-size: 0.625rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        color: var(--text-muted);
    }

    .ld-email-preview {
        border: 1px solid var(--border);
        border-radius: 6px;
        overflow: hidden;
        background: var(--bg-primary);
    }

    .ld-email-preview-meta {
        padding: 0.55rem 0.65rem;
        border-bottom: 1px solid var(--border);
        background: var(--bg-card);
        display: grid;
        gap: 0.35rem;
    }

    .ld-email-preview-row {
        display: grid;
        grid-template-columns: 4rem 1fr;
        gap: 0.5rem;
        align-items: baseline;
        font-size: 0.75rem;
    }

    .ld-email-preview-label {
        color: var(--text-muted);
        font-weight: 500;
    }

    .ld-email-preview-value {
        color: var(--text-primary);
        word-break: break-word;
    }

    .ld-email-preview-attachment {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.15rem 0.4rem;
        border-radius: 4px;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        font-size: 0.6875rem;
        color: var(--text-secondary);
    }

    .ld-email-preview-body {
        padding: 0.75rem;
        min-height: 140px;
        background: #fff;
    }

    .ld-email-preview-body iframe {
        display: block;
        width: 100%;
        min-height: 180px;
        border: 0;
        background: #fff;
    }

    @media (max-width: 900px) {
        .ld-settings-layout {
            grid-template-columns: 1fr;
        }

        .leads-toolbar-row,
        .leads-toolbar-row-3 {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {
        .ld-top {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>
