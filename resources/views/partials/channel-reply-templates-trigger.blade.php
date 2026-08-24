@php
    $p = $prefix ?? 'channel';
    $label = $label ?? 'Templates';
@endphp
<button type="button" class="ch-tpl-sidebar-btn" id="{{ $p }}TemplatesBtn" title="Manage {{ $label }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="16" y1="13" x2="8" y2="13"/>
        <line x1="16" y1="17" x2="8" y2="17"/>
    </svg>
    <span class="ch-tpl-sidebar-btn-label">{{ $label }}</span>
    <span class="ch-tpl-sidebar-btn-count" id="{{ $p }}TemplateCount"></span>
</button>
