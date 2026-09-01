<style>
    .qb-settings-page {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        max-width: 1100px;
    }

    .qb-settings-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 300px;
        gap: 1.5rem;
        align-items: start;
    }

    .qb-settings-card,
    .qb-settings-aside {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem 1.75rem;
    }

    .qb-settings-card-header {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 1.5rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid var(--border);
    }

    .qb-settings-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: var(--accent-light);
    }

    .qb-settings-icon svg {
        width: 24px;
        height: 24px;
        color: var(--accent);
    }

    .qb-settings-icon.m365 {
        background: #eff6ff;
    }

    .qb-settings-icon.m365 svg {
        color: #2563eb;
    }

    .qb-settings-heading h2 {
        margin: 0 0 0.25rem;
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .qb-settings-heading p {
        margin: 0;
        font-size: 0.875rem;
        color: var(--text-secondary);
        line-height: 1.5;
    }

    .qb-flash {
        padding: 0.875rem 1rem;
        border-radius: 10px;
        font-size: 0.875rem;
        border: 1px solid transparent;
    }

    .qb-flash.success {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #065f46;
    }

    .qb-flash.error {
        background: #fef2f2;
        border-color: #fecaca;
        color: #991b1b;
    }

    .qb-flash.info {
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #1e40af;
    }

    .qb-status-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
    }

    .qb-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0.85rem;
        border-radius: 999px;
        font-size: 0.8125rem;
        font-weight: 600;
        border: 1px solid transparent;
    }

    .qb-status-pill::before {
        content: '';
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: currentColor;
        opacity: 0.85;
    }

    .qb-status-pill[data-status="connected"] {
        background: #ecfdf5;
        color: #047857;
        border-color: #a7f3d0;
    }

    .qb-status-pill[data-status="disconnected"] {
        background: var(--bg-primary);
        color: var(--text-secondary);
        border-color: var(--border);
    }

    .qb-status-pill[data-status="loading"] {
        background: #eff6ff;
        color: #1d4ed8;
        border-color: #bfdbfe;
    }

    .qb-connected-box {
        display: none;
        padding: 1rem 1.125rem;
        border-radius: 10px;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        margin-bottom: 1.25rem;
    }

    .qb-connected-box.visible {
        display: block;
    }

    .qb-connected-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-muted);
        margin-bottom: 0.35rem;
    }

    .qb-connected-email {
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--text-primary);
        word-break: break-word;
    }

    .qb-connected-name {
        margin-top: 0.25rem;
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    .qb-settings-form {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .qb-form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .qb-form-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .qb-form-input,
    .qb-form-textarea {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.9375rem;
        background: var(--bg-primary);
        color: var(--text-primary);
        transition: border-color 0.15s, box-shadow 0.15s;
    }

    .qb-form-textarea {
        min-height: 320px;
        resize: vertical;
        line-height: 1.55;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.8125rem;
    }

    .qb-form-input:focus,
    .qb-form-textarea:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .qb-form-help {
        margin: 0;
        font-size: 0.8125rem;
        color: var(--text-secondary);
        line-height: 1.5;
    }

    .qb-form-help code {
        padding: 0.1rem 0.35rem;
        border-radius: 4px;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        font-size: 0.75rem;
    }

    .qb-form-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        padding-top: 1.25rem;
        margin-top: 0.25rem;
        border-top: 1px solid var(--border);
    }

    .qb-btn-primary,
    .qb-btn-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        cursor: pointer;
        border: 1px solid transparent;
        transition: background 0.15s, border-color 0.15s, color 0.15s;
    }

    .qb-btn-primary {
        background: var(--accent);
        border-color: var(--accent);
        color: #fff;
    }

    .qb-btn-primary:hover {
        filter: brightness(0.95);
        color: #fff;
    }

    .qb-btn-secondary {
        background: var(--bg-card);
        border-color: var(--border);
        color: var(--text-primary);
    }

    .qb-btn-secondary:hover {
        background: var(--bg-primary);
    }

    .qb-btn-primary:disabled,
    .qb-btn-secondary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .qb-aside-title {
        margin: 0 0 0.35rem;
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .qb-aside-text {
        margin: 0 0 1rem;
        font-size: 0.8125rem;
        color: var(--text-secondary);
        line-height: 1.5;
    }

    .qb-steps {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 0.875rem;
    }

    .qb-steps li {
        display: grid;
        grid-template-columns: 1.5rem 1fr;
        gap: 0.75rem;
        align-items: start;
        font-size: 0.8125rem;
        color: var(--text-secondary);
        line-height: 1.45;
    }

    .qb-step-num {
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 999px;
        background: var(--accent-light);
        color: var(--accent);
        font-size: 0.75rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .qb-placeholder-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 0.625rem;
    }

    .qb-placeholder-item {
        display: grid;
        gap: 0.25rem;
    }

    .qb-placeholder-btn {
        display: inline-flex;
        align-items: center;
        justify-content: flex-start;
        width: 100%;
        padding: 0.5rem 0.625rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg-primary);
        cursor: pointer;
        transition: border-color 0.15s, background 0.15s;
    }

    .qb-placeholder-btn:hover {
        border-color: var(--accent);
        background: var(--accent-light);
    }

    .qb-placeholder-btn code {
        font-size: 0.75rem;
        color: var(--accent);
        background: transparent;
        padding: 0;
    }

    .qb-placeholder-desc {
        font-size: 0.75rem;
        color: var(--text-muted);
        padding-left: 0.125rem;
    }

    .qb-inline-alert {
        margin-top: 1rem;
    }

    .qb-preview-section {
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border);
    }

    .qb-preview-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .qb-preview-header h3 {
        margin: 0 0 0.25rem;
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .qb-preview-header p {
        margin: 0;
        font-size: 0.8125rem;
        color: var(--text-secondary);
        line-height: 1.45;
    }

    .qb-preview-badge {
        flex-shrink: 0;
        padding: 0.25rem 0.625rem;
        border-radius: 999px;
        font-size: 0.6875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        color: var(--text-muted);
    }

    .qb-email-preview {
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
        background: var(--bg-primary);
    }

    .qb-email-preview-meta {
        padding: 0.875rem 1rem;
        border-bottom: 1px solid var(--border);
        background: var(--bg-card);
        display: grid;
        gap: 0.5rem;
    }

    .qb-email-preview-row {
        display: grid;
        grid-template-columns: 4.5rem 1fr;
        gap: 0.75rem;
        align-items: baseline;
        font-size: 0.8125rem;
    }

    .qb-email-preview-label {
        color: var(--text-muted);
        font-weight: 500;
    }

    .qb-email-preview-value {
        color: var(--text-primary);
        word-break: break-word;
    }

    .qb-email-preview-attachment {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        font-size: 0.75rem;
        color: var(--text-secondary);
    }

    .qb-email-preview-body {
        padding: 1.25rem 1rem;
        min-height: 180px;
        background: #fff;
        color: #1f2937;
        font-size: 0.9375rem;
        line-height: 1.6;
    }

    .qb-email-preview-body iframe {
        display: block;
        width: 100%;
        min-height: 220px;
        border: 0;
        background: #fff;
    }

    @media (max-width: 900px) {
        .qb-settings-layout {
            grid-template-columns: 1fr;
        }
    }
</style>
