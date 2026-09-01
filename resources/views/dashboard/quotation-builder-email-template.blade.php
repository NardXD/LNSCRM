@extends('layouts.app')

@section('title', 'Quote Email Template')

@section('content')
    <div class="ld-page">
        <div class="ld-top">
            <div class="ld-top-main">
                <h1 class="ld-title">Quote Email Template</h1>
                <p class="ld-subtitle">Customize the subject and HTML body used when you click <strong>Email quote</strong> on a storage quote.</p>
            </div>
        </div>

        <div id="email-template-page" class="ld-settings-layout">
            <div class="ld-settings-card">
                <div class="ld-settings-card-header">
                    <div class="ld-settings-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    </div>
                    <div class="ld-settings-heading">
                        <h2>Email content</h2>
                        <p>Write the subject and HTML body for quote emails. Use placeholders from the panel on the right — they are replaced with lead and quote details when sending.</p>
                    </div>
                </div>

                <form id="emailTemplateForm">
                    @csrf
                    <div class="ld-form-group">
                        <label class="ld-form-label" for="template-subject">Email subject</label>
                        <input type="text" id="template-subject" name="subject" class="ld-form-input" maxlength="500" placeholder="Your storage quote from @{{facility}}" required>
                    </div>

                    <div class="ld-form-group">
                        <label class="ld-form-label" for="template-body">Email body (HTML)</label>
                        <textarea id="template-body" name="body" class="ld-form-textarea" placeholder="<p>Hi @{{customer_name}},</p>" required></textarea>
                        <p class="ld-form-help">You can use HTML such as <code>&lt;p&gt;</code>, <code>&lt;strong&gt;</code>, and <code>&lt;table&gt;</code>. The quote PDF is always attached.</p>
                    </div>

                    <div class="ld-form-actions">
                        <button type="submit" class="btn btn-primary btn-sm" id="save-template-btn">Save template</button>
                        <button type="button" class="btn btn-secondary btn-sm" id="reset-template-btn">Reset to default</button>
                    </div>
                </form>

                <div id="template-alert" class="ld-flash ld-inline-alert" style="display: none;" role="alert"></div>

                <section class="ld-preview-section" aria-labelledby="email-preview-heading">
                    <div class="ld-preview-header">
                        <div>
                            <h3 id="email-preview-heading">Preview</h3>
                            <p>Sample lead and quote data — updates as you edit the subject and body.</p>
                        </div>
                        <span class="ld-preview-badge">Sample data</span>
                    </div>

                    <div class="ld-email-preview">
                        <div class="ld-email-preview-meta">
                            <div class="ld-email-preview-row">
                                <span class="ld-email-preview-label">Subject</span>
                                <span class="ld-email-preview-value" id="preview-subject">—</span>
                            </div>
                            <div class="ld-email-preview-row">
                                <span class="ld-email-preview-label">Attach</span>
                                <span class="ld-email-preview-attachment">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                                    </svg>
                                    storage-quote.pdf
                                </span>
                            </div>
                        </div>
                        <div class="ld-email-preview-body">
                            <iframe id="preview-body-frame" title="Email body preview" sandbox="allow-same-origin"></iframe>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="ld-settings-aside">
                <h3 class="ld-aside-title">Placeholders</h3>
                <p class="ld-aside-text">Click to insert at the cursor in the subject or body field.</p>
                <ul class="ld-placeholder-list">
                    @foreach($placeholders as $placeholder)
                        @php
                            $placeholderToken = '{' . '{' . $placeholder['key'] . '}' . '}';
                        @endphp
                        <li class="ld-placeholder-item">
                            <button type="button" class="ld-placeholder-btn" data-placeholder="{{ $placeholderToken }}">
                                <code>{{ $placeholderToken }}</code>
                            </button>
                            <span class="ld-placeholder-desc">{{ $placeholder['description'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </aside>
        </div>
    </div>
@endsection

@push('styles')
    @include('partials.leads-page-base-styles')
@endpush

@push('scripts')
    @php
        $emailTemplateConfig = [
            'apiUrl' => route('api.quotation-builder.email-template.get'),
            'storeUrl' => route('api.quotation-builder.email-template.store'),
            'resetUrl' => route('api.quotation-builder.email-template.reset'),
            'previewContext' => $previewContext,
        ];
    @endphp
    <script type="application/json" id="email-template-config">@json($emailTemplateConfig)</script>
    <script src="{{ asset('js/quotation-builder-email-template.js') }}?v={{ filemtime(public_path('js/quotation-builder-email-template.js')) }}"></script>
@endpush
