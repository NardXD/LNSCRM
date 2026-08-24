@php
    $p = $prefix ?? 'channel';
    $label = $label ?? 'Templates';
    $help = $help ?? 'Reusable reply snippets for your team.';
@endphp
<div class="ch-tpl-modal-backdrop ch-tpl-list-backdrop" id="{{ $p }}TemplateListModal" hidden>
    <div class="ch-tpl-modal ch-tpl-list-modal" role="dialog" aria-modal="true" aria-labelledby="{{ $p }}TemplateListModalTitle">
        <div class="ch-tpl-list-head">
            <div class="ch-tpl-list-head-text">
                <h3 id="{{ $p }}TemplateListModalTitle">{{ $label }}</h3>
                <p class="ch-tpl-modal-help">{{ $help }}</p>
            </div>
            <button type="button" class="ch-tpl-close-btn" id="{{ $p }}TemplateListClose" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="ch-tpl-list-toolbar">
            <div class="ch-tpl-search-wrap">
                <svg class="ch-tpl-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" class="ch-tpl-search" id="{{ $p }}TemplateSearch" placeholder="Search templates…" autocomplete="off">
            </div>
            <button type="button" class="ch-tpl-btn primary" id="{{ $p }}NewTemplate" hidden>New template</button>
        </div>
        <div class="ch-tpl-list-shell">
            <div class="ch-tpl-list" id="{{ $p }}TemplateList"></div>
        </div>
        <div class="ch-tpl-pagination" id="{{ $p }}TemplatePagination" hidden>
            <span class="ch-tpl-pagination-info" id="{{ $p }}TemplatePaginationInfo"></span>
            <div class="ch-tpl-pagination-controls">
                <button type="button" class="ch-tpl-page-btn" id="{{ $p }}TemplatePrevPage" disabled>Previous</button>
                <span class="ch-tpl-page-status" id="{{ $p }}TemplatePageStatus"></span>
                <button type="button" class="ch-tpl-page-btn" id="{{ $p }}TemplateNextPage">Next</button>
            </div>
        </div>
    </div>
</div>
