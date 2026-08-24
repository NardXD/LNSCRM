@php
    $p = $prefix ?? 'channel';
    $label = $label ?? 'Templates';
    $help = $help ?? 'Reusable reply snippets for your team.';
@endphp
<div class="ch-tpl-modal-backdrop ch-tpl-edit-backdrop" id="{{ $p }}TemplateModal" hidden>
    <div class="ch-tpl-modal ch-tpl-edit-modal" role="dialog" aria-modal="true" aria-labelledby="{{ $p }}TemplateModalTitle">
        <div class="ch-tpl-list-head">
            <div class="ch-tpl-list-head-text">
                <h3 id="{{ $p }}TemplateModalTitle">New {{ $label }}</h3>
                <p class="ch-tpl-modal-help">{{ $help }}</p>
            </div>
            <button type="button" class="ch-tpl-close-btn" id="{{ $p }}TemplateClose" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <label class="ch-tpl-label">Name
            <input type="text" id="{{ $p }}TemplateName" class="ch-tpl-input" maxlength="160" placeholder="Follow-up">
        </label>
        <label class="ch-tpl-label">Message
            <textarea id="{{ $p }}TemplateBody" class="ch-tpl-textarea" rows="6" maxlength="{{ $bodyMax ?? 1600 }}" placeholder="Hi, thanks for reaching out…"></textarea>
        </label>
        <div class="ch-tpl-modal-actions">
            <button type="button" class="ch-tpl-btn ghost ch-tpl-btn-danger" id="{{ $p }}DeleteTemplate" hidden>Delete</button>
            <div class="ch-tpl-modal-actions-right">
                <button type="button" class="ch-tpl-btn ghost" id="{{ $p }}TemplateCancel">Cancel</button>
                <button type="button" class="ch-tpl-btn primary" id="{{ $p }}SaveTemplate">Create</button>
            </div>
        </div>
    </div>
</div>
