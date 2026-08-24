.ch-tpl-sidebar-btn {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    width: calc(100% - 1.5rem);
    margin: 0 0.75rem 0.5rem;
    padding: 0.5rem 0.65rem;
    border: 1px solid #e5e5ea;
    border-radius: 10px;
    background: #fff;
    color: var(--text-primary, #111827);
    font: inherit;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    flex-shrink: 0;
    text-align: left;
}
.ch-tpl-sidebar-btn svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    color: var(--text-secondary, #6b7280);
}
.ch-tpl-sidebar-btn:hover {
    border-color: var(--sms-accent, #0ea5e9);
    background: #f8fafc;
}
.fb-page-wrapper .ch-tpl-sidebar-btn:hover {
    border-color: var(--fb-accent, #1877f2);
}
.ch-tpl-sidebar-btn-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ch-tpl-sidebar-btn-count {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-secondary, #6b7280);
    background: #f3f4f6;
    border-radius: 999px;
    padding: 0.1rem 0.45rem;
    flex-shrink: 0;
}
.ch-tpl-sidebar-btn-count:empty { display: none; }

.ch-tpl-search-wrap {
    position: relative;
    flex: 1;
    min-width: 0;
}
.ch-tpl-search-icon {
    position: absolute;
    left: 0.65rem;
    top: 50%;
    transform: translateY(-50%);
    width: 15px;
    height: 15px;
    color: #9ca3af;
    pointer-events: none;
}
.ch-tpl-search,
.ch-tpl-picker-search {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 0.5rem 0.65rem;
    font: inherit;
    font-size: 0.84rem;
    background: #fff;
    color: var(--text-primary, #111827);
    outline: none;
    appearance: none;
    -webkit-appearance: none;
}
.ch-tpl-list-toolbar .ch-tpl-search {
    padding-left: 2.1rem;
}
.ch-tpl-search:focus,
.ch-tpl-picker-search:focus,
.ch-tpl-input:focus,
.ch-tpl-textarea:focus {
    border-color: var(--sms-accent, #0ea5e9);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.12);
}
.fb-page-wrapper .ch-tpl-search:focus,
.fb-page-wrapper .ch-tpl-picker-search:focus,
.fb-page-wrapper .ch-tpl-input:focus,
.fb-page-wrapper .ch-tpl-textarea:focus {
    border-color: var(--fb-accent, #1877f2);
    box-shadow: 0 0 0 3px rgba(24, 119, 242, 0.12);
}
.ch-tpl-search::-webkit-search-cancel-button { -webkit-appearance: none; }

.ch-tpl-list-toolbar {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}
.ch-tpl-list-toolbar .ch-tpl-btn { flex-shrink: 0; white-space: nowrap; }

.ch-tpl-list-shell {
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    background: #fafafa;
    overflow: hidden;
    min-height: 200px;
    max-height: min(52vh, 420px);
    display: flex;
    flex-direction: column;
}
.ch-tpl-list,
.ch-tpl-picker-list {
    overflow: auto;
}
.ch-tpl-list {
    flex: 1;
    display: flex;
    flex-direction: column;
}
.ch-tpl-picker-list {
    display: grid;
    gap: 0.2rem;
    max-height: 180px;
}

.ch-tpl-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.7rem 0.85rem;
    background: #fff;
    border-bottom: 1px solid #eef0f2;
}
.ch-tpl-row:last-child { border-bottom: 0; }
.ch-tpl-row:hover { background: #f9fafb; }
.ch-tpl-row-main {
    flex: 1;
    min-width: 0;
}
.ch-tpl-row-name {
    font-size: 0.86rem;
    font-weight: 600;
    color: var(--text-primary, #111827);
    margin-bottom: 0.15rem;
}
.ch-tpl-row-preview {
    font-size: 0.78rem;
    line-height: 1.4;
    color: var(--text-secondary, #6b7280);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.ch-tpl-row-actions {
    display: flex;
    align-items: center;
    gap: 0.15rem;
    flex-shrink: 0;
}
.ch-tpl-link-btn {
    border: 0;
    background: transparent;
    padding: 0.35rem 0.55rem;
    border-radius: 6px;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--sms-accent, #0ea5e9);
    cursor: pointer;
}
.fb-page-wrapper .ch-tpl-link-btn { color: var(--fb-accent, #1877f2); }
.ch-tpl-link-btn:hover { background: rgba(14, 165, 233, 0.08); }
.fb-page-wrapper .ch-tpl-link-btn:hover { background: rgba(24, 119, 242, 0.08); }
.ch-tpl-link-btn.muted { color: var(--text-secondary, #6b7280); }
.ch-tpl-link-btn.muted:hover { background: #f3f4f6; color: var(--text-primary, #111827); }

.ch-tpl-picker-item {
    display: block;
    width: 100%;
    border: 0;
    background: transparent;
    padding: 0.35rem 0.4rem;
    border-radius: 8px;
    cursor: pointer;
    text-align: left;
    font: inherit;
    font-size: 0.8rem;
    color: var(--text-primary, #111827);
}
.ch-tpl-picker-item:hover,
.ch-tpl-picker-item.is-active { background: #f3f4f6; }

.ch-tpl-empty {
    font-size: 0.84rem;
    color: var(--text-secondary, #6b7280);
    padding: 2.5rem 1rem;
    text-align: center;
    background: #fff;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ch-tpl-picker {
    position: absolute;
    bottom: calc(100% + 6px);
    left: 0;
    width: min(280px, 80vw);
    background: #fff;
    border: 1px solid #e5e5ea;
    border-radius: 10px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
    padding: 0.45rem;
    z-index: 30;
    gap: 0.35rem;
}
.ch-tpl-picker[hidden] { display: none !important; }
.ch-tpl-picker:not([hidden]) { display: grid; }
.ch-tpl-picker-wrap { position: relative; flex-shrink: 0; }

.ch-tpl-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.35);
    z-index: 120;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.ch-tpl-edit-backdrop { z-index: 130; }
.ch-tpl-modal-backdrop[hidden] { display: none !important; }
.ch-tpl-modal-backdrop:not([hidden]) { display: flex; }

.ch-tpl-modal {
    width: min(520px, 100%);
    background: #fff;
    border-radius: 14px;
    padding: 1.15rem 1.25rem 1.25rem;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.18);
    display: grid;
    gap: 0.85rem;
    max-height: min(90vh, 720px);
}
.ch-tpl-list-modal { width: min(560px, 100%); }
.ch-tpl-list-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    padding-bottom: 0.15rem;
}
.ch-tpl-list-head-text { min-width: 0; }
.ch-tpl-list-head h3 { margin: 0; font-size: 1.05rem; font-weight: 700; }
.ch-tpl-modal-help { margin: 0.25rem 0 0; font-size: 0.8rem; line-height: 1.45; color: var(--text-secondary, #6b7280); }
.ch-tpl-close-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--text-secondary, #6b7280);
    cursor: pointer;
    flex-shrink: 0;
    padding: 0;
}
.ch-tpl-close-btn svg { width: 18px; height: 18px; }
.ch-tpl-close-btn:hover { background: #f3f4f6; color: var(--text-primary, #111827); }

.ch-tpl-label { display: grid; gap: 0.3rem; font-size: 0.8rem; font-weight: 600; color: #374151; }
.ch-tpl-input,
.ch-tpl-textarea {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 0.5rem 0.65rem;
    font: inherit;
    font-size: 0.86rem;
    background: #fff;
    color: var(--text-primary, #111827);
    outline: none;
}
.ch-tpl-textarea { resize: vertical; min-height: 120px; }

.ch-tpl-modal-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.15rem;
    padding-top: 0.15rem;
}
.ch-tpl-modal-actions-right { display: flex; gap: 0.45rem; margin-left: auto; }
.ch-tpl-btn {
    border: 1px solid #e5e7eb;
    background: #fff;
    border-radius: 8px;
    padding: 0.45rem 0.8rem;
    font: inherit;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    color: var(--text-primary, #111827);
}
.ch-tpl-btn.primary { background: var(--sms-accent, #0ea5e9); border-color: var(--sms-accent, #0ea5e9); color: #fff; }
.fb-page-wrapper .ch-tpl-btn.primary { background: var(--fb-accent, #1877f2); border-color: var(--fb-accent, #1877f2); }
.ch-tpl-btn.ghost { background: #fff; }
.ch-tpl-btn-danger { color: #dc2626; border-color: #fecaca; }
.ch-tpl-btn-danger:hover { background: #fef2f2; }
.ch-tpl-btn:disabled { opacity: 0.6; cursor: not-allowed; }

.ch-tpl-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding-top: 0.15rem;
    flex-wrap: wrap;
}
.ch-tpl-pagination[hidden] { display: none !important; }
.ch-tpl-pagination-info {
    font-size: 0.78rem;
    color: var(--text-secondary, #6b7280);
}
.ch-tpl-pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-left: auto;
}
.ch-tpl-page-status {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--text-secondary, #6b7280);
    min-width: 5.5rem;
    text-align: center;
}
.ch-tpl-page-btn {
    border: 1px solid #e5e7eb;
    background: #fff;
    border-radius: 8px;
    padding: 0.35rem 0.65rem;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--text-primary, #111827);
    cursor: pointer;
}
.ch-tpl-page-btn:hover:not(:disabled) {
    border-color: var(--sms-accent, #0ea5e9);
    color: var(--sms-accent, #0ea5e9);
}
.fb-page-wrapper .ch-tpl-page-btn:hover:not(:disabled) {
    border-color: var(--fb-accent, #1877f2);
    color: var(--fb-accent, #1877f2);
}
.ch-tpl-page-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
