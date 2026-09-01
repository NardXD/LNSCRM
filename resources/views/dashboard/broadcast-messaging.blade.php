@extends('layouts.app')

@section('title', 'Broadcast Messaging')

@section('content')
    @if(session('status') === 'outlook-mail-connected')
        <div class="flash-alert flash-alert-success" role="alert">Microsoft 365 mailbox connected. You can now use it as an email sender.</div>
    @endif
    @if(session('error'))
        <div class="flash-alert flash-alert-error" role="alert">{{ session('error') }}</div>
    @endif

    <div class="bc-page" id="broadcastApp"
         data-api-base="{{ url('api/broadcast') }}"
         data-csrf="{{ csrf_token() }}"
         data-can-sms="{{ !empty($canSendSms) ? '1' : '0' }}"
         data-can-email="{{ !empty($canSendEmail) ? '1' : '0' }}"
         data-twilio="{{ !empty($twilioConnected) ? '1' : '0' }}"
         data-outlook="{{ !empty($outlookConfigured) ? '1' : '0' }}">

        <div class="bc-top">
            <div class="bc-top-main">
                <h1 class="bc-title">Broadcast Messaging</h1>
                <p class="bc-subtitle">Send bulk SMS and email, then track delivery</p>
            </div>
            <div class="bc-top-actions" id="bcTopActions">
                <button type="button" class="btn btn-primary btn-sm" id="btnNew" {{ (empty($canSendSms) && empty($canSendEmail)) ? 'disabled' : '' }}>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    New broadcast
                </button>
            </div>
        </div>

        <nav class="bc-context-nav" id="bcContextNav" hidden aria-label="Broadcast navigation">
            <button type="button" class="bc-context-back btn btn-secondary btn-sm" id="btnContextBack">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
                Broadcasts
            </button>
            <span class="bc-context-sep" aria-hidden="true">/</span>
            <span class="bc-context-title" id="bcContextTitle">New broadcast</span>
        </nav>

        <div id="viewList">
            <div class="bc-toolbar">
                <div class="bc-search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input type="search" id="listSearch" placeholder="Search broadcasts...">
                </div>
                <select id="listType" class="bc-select">
                    <option value="">All types</option>
                    <option value="sms">SMS</option>
                    <option value="email">Email</option>
                </select>
                <select id="listStatus" class="bc-select">
                    <option value="all">All statuses</option>
                    <option value="sending">Sending</option>
                    <option value="sent">Sent</option>
                    <option value="partial">Partial</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            <div class="bc-card">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Broadcast name</th>
                                <th>Type</th>
                                <th>Sender</th>
                                <th>Recipients</th>
                                <th>Status</th>
                                <th>Date created</th>
                                <th>Date sent</th>
                            </tr>
                        </thead>
                        <tbody id="listBody">
                            <tr><td colspan="7" class="bc-empty">Loading broadcasts…</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="bc-pager" id="listPager"></div>
            </div>
        </div>

        <div id="viewWizard" hidden>
            <div class="bc-stepper" role="tablist" aria-label="Broadcast steps">
                <button type="button" class="bc-step-item active" data-step="1" role="tab" aria-selected="true">
                    <span class="bc-step-num">1</span>
                    <span class="bc-step-label">Setup</span>
                </button>
                <button type="button" class="bc-step-item" data-step="2" role="tab" aria-selected="false">
                    <span class="bc-step-num">2</span>
                    <span class="bc-step-label">Recipients</span>
                </button>
                <button type="button" class="bc-step-item" data-step="3" role="tab" aria-selected="false">
                    <span class="bc-step-num">3</span>
                    <span class="bc-step-label">Compose</span>
                </button>
                <button type="button" class="bc-step-item" data-step="4" role="tab" aria-selected="false">
                    <span class="bc-step-num">4</span>
                    <span class="bc-step-label">Review</span>
                </button>
            </div>

            <div class="bc-card bc-wizard-card">
                <section class="bc-panel" data-panel="1">
                    <label class="bc-label">Broadcast name</label>
                    <input type="text" id="fName" class="bc-input" maxlength="160" placeholder="e.g. August storage promotion">

                    <label class="bc-label">Type</label>
                    <div class="bc-type-row">
                        <label class="bc-type-card" id="typeSmsCard">
                            <input type="radio" name="bcType" value="sms">
                            <strong>SMS</strong>
                            <span>Send via Twilio</span>
                        </label>
                        <label class="bc-type-card" id="typeEmailCard">
                            <input type="radio" name="bcType" value="email">
                            <strong>Email</strong>
                            <span>Send via Microsoft 365</span>
                        </label>
                    </div>
                    <p class="bc-hint" id="typeHint"></p>

                    <div id="smsSenderBlock">
                        <label class="bc-label">Twilio sender number</label>
                        <select id="fFromNumber" class="bc-input"></select>
                        <p class="bc-hint">This number is used as the SMS From value. Manage numbers in Phone System or Integrations.</p>
                    </div>

                    <div id="emailSenderBlock" hidden>
                        <div class="bc-sender-head">
                            <label class="bc-label">Microsoft 365 sender</label>
                            <div class="bc-sender-actions">
                                <a href="{{ route('inbox.connect.outlook', ['intent' => 'broadcast']) }}" class="btn btn-primary btn-sm" id="btnConnectM365">Sign in M365</a>
                                <a href="{{ route('inbox') }}" class="btn btn-secondary btn-sm" id="btnAddAccount">Shared mailboxes</a>
                            </div>
                        </div>
                        <select id="fInbox" class="bc-input"></select>
                        <p class="bc-hint">Choose a shared team mailbox or sign in with a Microsoft 365 account for broadcast-only sending. Direct accounts are not synced in Inbox.</p>
                    </div>
                </section>

                <section class="bc-panel" data-panel="2" hidden>
                    <div class="bc-recip-layout">
                        <div>
                            <div class="bc-recip-tools">
                                <input type="search" id="recipSearch" class="bc-input" placeholder="Search leads, clients, and contacts…">
                                <select id="recipSource" class="bc-select">
                                    <option value="all">All sources</option>
                                    <option value="leads">Leads</option>
                                    <option value="clients">Clients</option>
                                    <option value="contacts">Contacts</option>
                                </select>
                            </div>
                            <div class="bc-recip-actions">
                                <button type="button" class="btn btn-secondary btn-sm" id="btnSelectAllRecipients">Select all</button>
                                <button type="button" class="btn btn-secondary btn-sm" id="btnDeselectAllRecipients">Deselect all</button>
                            </div>
                            <div class="bc-recip-results" id="recipResults">
                                <div class="bc-empty">Search to find people with a phone number or email address.</div>
                            </div>
                            <div class="bc-pager" id="recipPager"></div>
                            <label class="bc-label">Or paste addresses (one per line)</label>
                            <textarea id="recipPaste" class="bc-input" rows="3" placeholder="+15551234567 or name@example.com"></textarea>
                            <button type="button" class="btn btn-secondary btn-sm" id="btnPaste">Add pasted</button>
                        </div>
                        <div class="bc-selected">
                            <div class="bc-selected-head">
                                <strong>Selected</strong>
                                <span id="selectedCount">0</span>
                            </div>
                            <p class="bc-hint" id="recipientLimitHint"></p>
                            <div id="selectedList" class="bc-selected-list">
                                <div class="bc-empty">No recipients yet.</div>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" id="btnClearSelected">Clear all</button>
                        </div>
                    </div>
                </section>

                <section class="bc-panel" data-panel="3" hidden>
                    <div id="emailSubjectBlock" hidden>
                        <label class="bc-label">Subject</label>
                        <input type="text" id="fSubject" class="bc-input" maxlength="500" placeholder="Email subject">
                    </div>
                    <div id="smsBodyBlock">
                        <label class="bc-label">SMS message</label>
                        <textarea id="fBody" class="bc-input" rows="7" placeholder="Write your message…"></textarea>
                        <div class="bc-char" id="charCount"></div>
                    </div>
                    <div id="emailBodyBlock" hidden>
                        <label class="bc-label">Email body</label>
                        <div class="bc-email-tools">
                            <button type="button" class="bc-tool-btn" id="btnEmailAttach" title="Attach files">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                Attach
                            </button>
                            <button type="button" class="bc-tool-btn" id="btnEmailImage" title="Insert image">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                                Image
                            </button>
                            <input type="file" id="emailAttachInput" multiple hidden>
                            <input type="file" id="emailImageInput" accept="image/*" hidden>
                        </div>
                        <div class="bc-attach-chips" id="emailAttachChips"></div>
                        <div class="bc-html-editor" id="emailHtmlEditor" data-html-editor="broadcast">
                            <div class="bc-html-toolbar">
                                <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
                                <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
                                <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
                                <button type="button" data-cmd="insertUnorderedList" title="Bullet list">• List</button>
                                <button type="button" data-cmd="createLink" title="Link">Link</button>
                                <button type="button" data-cmd="removeFormat" title="Clear formatting">Clear</button>
                                <span class="bc-html-toolbar-spacer"></span>
                                <button type="button" class="is-active" data-html-mode="visual">Visual</button>
                                <button type="button" data-html-mode="source">HTML</button>
                            </div>
                            <div id="fEmailVisual" class="bc-html-visual" contenteditable="true" data-placeholder="Write your email… Use Visual for formatting or switch to HTML to paste templates." role="textbox" aria-multiline="true"></div>
                            <textarea id="fEmailSource" class="bc-input bc-html-source" rows="10" hidden placeholder="<p>Hi,</p><p>Your message here…</p>"></textarea>
                        </div>
                        <div class="bc-char" id="emailCharCount"></div>
                        <p class="bc-hint">Attach files (up to 5, 3 MB each) or insert images inline. Switch to HTML mode to paste full templates.</p>
                    </div>
                </section>

                <section class="bc-panel" data-panel="4" hidden>
                    <div class="bc-review" id="reviewSummary"></div>
                    <h3 class="bc-review-title">Recipient list</h3>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr><th>Name</th><th>Address</th><th>Source</th></tr>
                            </thead>
                            <tbody id="reviewRecipients"></tbody>
                        </table>
                    </div>
                    <div class="bc-pager" id="reviewPager"></div>
                </section>

                <div class="bc-wizard-actions">
                    <button type="button" class="btn btn-secondary btn-sm" id="btnCancel">Cancel</button>
                    <div class="bc-wizard-nav">
                        <button type="button" class="btn btn-secondary btn-sm" id="btnBack" hidden>Back</button>
                        <button type="button" class="btn btn-primary btn-sm" id="btnNext">Continue</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="viewDetail" hidden>
            <div class="bc-detail-head">
                <div>
                    <h2 id="detailName" class="bc-detail-title"></h2>
                    <p id="detailMeta" class="page-subtitle"></p>
                </div>
                <div id="detailStatus"></div>
            </div>
            <div class="bc-stats" id="detailStats"></div>
            <div class="bc-card">
                <div class="bc-detail-body">
                    <div>
                        <h3 class="bc-review-title">Message</h3>
                        <div id="detailMessage" class="bc-message-preview"></div>
                        <div id="detailAttachments" class="bc-detail-attachments" hidden></div>
                    </div>
                    <div>
                        <div class="bc-detail-results-head">
                            <h3 class="bc-review-title">Results</h3>
                            <div class="bc-detail-actions" id="detailActions" hidden>
                                <button type="button" class="btn btn-secondary btn-sm" id="btnRetryFailed" hidden>Retry failed</button>
                                <button type="button" class="btn btn-primary btn-sm" id="btnToggleAddRecipients">Add recipients</button>
                            </div>
                        </div>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Address</th>
                                        <th>Status</th>
                                        <th>Error</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="detailRecipients"></tbody>
                            </table>
                        </div>
                        <div class="bc-pager" id="resultsPager"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bc-modal-overlay" id="addRecipientsModal" hidden>
            <div class="bc-modal" role="dialog" aria-modal="true" aria-labelledby="addRecipientsTitle">
                <div class="bc-modal-header">
                    <h3 class="bc-modal-title" id="addRecipientsTitle">Add recipients</h3>
                    <button type="button" class="bc-modal-close" id="btnCloseAddRecipients" aria-label="Close">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <div class="bc-modal-body">
                    <div class="bc-recip-layout">
                        <div>
                            <div class="bc-recip-tools">
                                <input type="search" id="detailRecipSearch" class="bc-input" placeholder="Search leads, clients, and contacts…">
                                <select id="detailRecipSource" class="bc-select">
                                    <option value="all">All sources</option>
                                    <option value="leads">Leads</option>
                                    <option value="clients">Clients</option>
                                    <option value="contacts">Contacts</option>
                                </select>
                            </div>
                            <div class="bc-recip-actions">
                                <button type="button" class="btn btn-secondary btn-sm" id="btnSelectAllDetailRecipients">Select all</button>
                                <button type="button" class="btn btn-secondary btn-sm" id="btnDeselectAllDetailRecipients">Deselect all</button>
                            </div>
                            <div class="bc-recip-results" id="detailRecipResults">
                                <div class="bc-empty">Search to find people with a phone number or email address.</div>
                            </div>
                            <div class="bc-pager" id="detailRecipPager"></div>
                            <label class="bc-label">Or paste addresses (one per line)</label>
                            <textarea id="detailRecipPaste" class="bc-input" rows="3" placeholder="+15551234567 or name@example.com"></textarea>
                            <button type="button" class="btn btn-secondary btn-sm" id="btnDetailPaste">Add pasted</button>
                        </div>
                        <div class="bc-selected">
                            <div class="bc-selected-head">
                                <strong>Selected</strong>
                                <span id="detailSelectedCount">0</span>
                            </div>
                            <div id="detailSelectedList" class="bc-selected-list">
                                <div class="bc-empty">No recipients yet.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bc-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" id="btnCancelAddRecipients">Cancel</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnSendDetailRecipients">Send to selected</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .bc-page {
        max-width: 1120px;
        font-size: 0.8125rem;
    }

    /* Compact page header */
    .bc-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.75rem;
        margin-bottom: 0.85rem;
    }
    .bc-title {
        font-size: 1.125rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 0.15rem;
        line-height: 1.3;
    }
    .bc-subtitle {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin: 0;
    }
    .bc-top-actions { flex-shrink: 0; display: flex; align-items: center; margin: 0.25rem 0; }

    /* Context breadcrumb nav */
    .bc-context-nav {
        display: flex;
        align-items: center;
        gap: 0.35rem;
        margin-bottom: 0.75rem;
        padding: 0.35rem 0;
        flex-wrap: wrap;
    }
    .bc-context-nav[hidden] { display: none; }
    .bc-context-back {
        text-decoration: none;
    }
    .bc-context-back svg { width: 14px; height: 14px; }
    .bc-context-sep { color: var(--text-muted); font-size: 0.75rem; }
    .bc-context-title {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-secondary);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 320px;
    }

    /* Scoped compact buttons */
    .bc-page .btn,
    .bc-page a.btn {
        padding: 0.35rem 0.7rem;
        margin: 0.4rem 0;
        font-size: 0.75rem;
        border-radius: 6px;
        gap: 0.3rem;
        font-weight: 500;
        line-height: 1.35;
        text-decoration: none;
    }
    .bc-page a.btn:hover,
    .bc-page a.btn:focus,
    .bc-page a.btn:active {
        text-decoration: none;
    }
    .bc-page .btn svg { width: 14px; height: 14px; }
    .bc-page .btn-sm,
    .bc-page a.btn-sm {
        padding: 0.3rem 0.6rem;
        margin: 0.35rem 0;
    }

    /* List toolbar */
    .bc-toolbar {
        display: flex;
        gap: 0.45rem;
        margin-bottom: 0.6rem;
        flex-wrap: wrap;
        align-items: center;
    }
    .bc-search { position: relative; flex: 1; min-width: 180px; }
    .bc-search svg {
        position: absolute; left: 0.55rem; top: 50%; transform: translateY(-50%);
        width: 13px; height: 13px; color: var(--text-muted); pointer-events: none;
    }
    .bc-search input, .bc-select, .bc-input {
        width: 100%;
        padding: 0.4rem 0.6rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.75rem;
        font-family: inherit;
        background: #fff;
        color: var(--text-primary);
        line-height: 1.35;
    }
    .bc-search input { padding-left: 1.85rem; }
    .bc-select { width: auto; min-width: 110px; padding-right: 1.5rem; }

    /* Cards & tables */
    .bc-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
    }
    .table-container { overflow-x: auto; }
    .bc-page .data-table { width: 100%; border-collapse: collapse; }
    .bc-page .data-table th {
        padding: 0.45rem 0.65rem;
        text-align: left;
        font-size: 0.625rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid var(--border);
        background: var(--bg-primary);
        white-space: nowrap;
    }
    .bc-page .data-table td {
        padding: 0.5rem 0.65rem;
        font-size: 0.75rem;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
    }
    .bc-page .data-table tbody tr { cursor: pointer; }
    .bc-page .data-table tbody tr:hover { background: var(--bg-primary); }
    .bc-empty {
        text-align: center;
        color: var(--text-secondary);
        padding: 1.25rem 0.75rem !important;
        cursor: default;
        font-size: 0.75rem;
    }

    /* Badges */
    .bc-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1rem 0.45rem;
        border-radius: 999px;
        font-size: 0.625rem;
        font-weight: 600;
        line-height: 1.4;
    }
    .bc-badge.sms { background: #eef2ff; color: #4338ca; }
    .bc-badge.email { background: #eff6ff; color: #1d4ed8; }
    .bc-badge.sending { background: #fff7ed; color: #c2410c; }
    .bc-badge.sent, .bc-badge.delivered { background: #ecfdf5; color: #047857; }
    .bc-badge.partial { background: #fef3c7; color: #b45309; }
    .bc-badge.failed, .bc-badge.undelivered { background: #fef2f2; color: #b91c1c; }
    .bc-badge.pending { background: #f3f4f6; color: #4b5563; }

    /* Pagination */
    .bc-pager { padding: 0.45rem 0.65rem; }
    .bc-pager:empty { display: none; }
    .bc-pager-inner {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    .bc-pager-info { font-size: 0.6875rem; color: var(--text-secondary); }
    .bc-pager-controls { display: flex; gap: 0.2rem; flex-wrap: wrap; align-items: center; }
    .bc-page-btn {
        min-width: 1.6rem;
        border: 1px solid var(--border);
        background: #fff;
        border-radius: 5px;
        padding: 0.2rem 0.45rem;
        margin: 0.25rem 0;
        font-size: 0.6875rem;
        cursor: pointer;
        font-family: inherit;
        color: var(--text-primary);
        line-height: 1.3;
        text-decoration: none;
    }
    .bc-page-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
    .bc-page-btn.is-active { background: var(--accent-light); border-color: transparent; color: var(--accent); font-weight: 600; }
    .bc-page-btn:disabled { opacity: 0.45; cursor: default; }

    /* Wizard stepper */
    .bc-stepper {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0;
        margin-bottom: 0.65rem;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
    }
    .bc-step-item {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        padding: 0.45rem 0.35rem;
        margin: 0.25rem 0;
        border: none;
        border-right: 1px solid var(--border);
        background: var(--bg-primary);
        color: var(--text-secondary);
        font-size: 0.6875rem;
        font-weight: 500;
        font-family: inherit;
        cursor: pointer;
        transition: background 0.12s, color 0.12s;
        text-decoration: none;
    }
    .bc-step-item:last-child { border-right: none; }
    .bc-step-item:hover:not(.active) { background: #fff; color: var(--text-primary); }
    .bc-step-item.active {
        background: #fff;
        color: var(--accent);
        font-weight: 600;
        box-shadow: inset 0 -2px 0 var(--accent);
    }
    .bc-step-item.done { color: var(--text-primary); }
    .bc-step-item.done .bc-step-num {
        background: #ecfdf5;
        color: #047857;
        border-color: #a7f3d0;
    }
    .bc-step-num {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.15rem;
        height: 1.15rem;
        border-radius: 999px;
        border: 1px solid var(--border);
        background: #fff;
        font-size: 0.625rem;
        font-weight: 700;
        flex-shrink: 0;
    }
    .bc-step-item.active .bc-step-num {
        background: var(--accent);
        border-color: var(--accent);
        color: #fff;
    }
    .bc-step-label { white-space: nowrap; }

    /* Wizard form */
    .bc-wizard-card { padding: 0.85rem 1rem; }
    .bc-label {
        display: block;
        font-size: 0.6875rem;
        font-weight: 600;
        margin: 0.65rem 0 0.25rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .bc-label:first-child { margin-top: 0; }
    .bc-hint {
        font-size: 0.6875rem;
        color: var(--text-muted);
        margin-top: 0.3rem;
        line-height: 1.4;
    }
    .bc-type-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
    .bc-type-card {
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 0.55rem 0.65rem;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
    }
    .bc-type-card strong { font-size: 0.75rem; }
    .bc-type-card:has(input:checked) { border-color: var(--accent); background: var(--accent-light); }
    .bc-type-card input { accent-color: var(--accent); }
    .bc-type-card span { font-size: 0.6875rem; color: var(--text-secondary); }
    .bc-sender-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-top: 0.65rem;
    }
    .bc-sender-head .bc-label { margin: 0; }
    .bc-sender-actions { display: flex; gap: 0.35rem; flex-wrap: wrap; align-items: center; margin: 0.25rem 0 0.5rem; }

    /* Recipients */
    .bc-recip-layout { display: grid; grid-template-columns: 1.35fr 0.85fr; gap: 0.65rem; }
    .bc-recip-tools { display: flex; gap: 0.35rem; margin-bottom: 0.45rem; }
    .bc-recip-actions { display: flex; gap: 0.35rem; margin: 0.35rem 0 0.5rem; flex-wrap: wrap; align-items: center; }
    .bc-recip-results {
        border: 1px solid var(--border);
        border-radius: 6px;
        max-height: 240px;
        overflow: auto;
        margin-bottom: 0.6rem;
    }
    .bc-recip-row {
        display: flex;
        gap: 0.5rem;
        align-items: flex-start;
        padding: 0.45rem 0.55rem;
        border-bottom: 1px solid var(--border);
        cursor: pointer;
        font-size: 0.75rem;
    }
    .bc-recip-row:hover { background: var(--bg-primary); }
    .bc-recip-row strong { font-size: 0.75rem; font-weight: 600; }
    .bc-recip-row small { display: block; color: var(--text-secondary); font-size: 0.6875rem; }
    .bc-recip-row input[type="checkbox"] { margin-top: 0.15rem; flex-shrink: 0; }
    .bc-selected {
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 0.55rem;
        background: var(--bg-primary);
    }
    .bc-selected-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.45rem;
        font-size: 0.75rem;
    }
    .bc-selected-head strong { font-size: 0.75rem; }
    .bc-selected-head span {
        font-size: 0.6875rem;
        color: var(--text-secondary);
        font-weight: 600;
    }
    .bc-selected-list { max-height: 280px; overflow: auto; margin-bottom: 0.45rem; }
    .bc-chip {
        display: flex;
        justify-content: space-between;
        gap: 0.35rem;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 5px;
        padding: 0.3rem 0.45rem;
        margin-bottom: 0.25rem;
        font-size: 0.6875rem;
    }
    .bc-chip button { border: 0; background: none; color: #b91c1c; cursor: pointer; font-size: 0.6875rem; }
    .bc-char { font-size: 0.625rem; color: var(--text-muted); margin-top: 0.25rem; text-align: right; }

    /* Wizard footer */
    .bc-wizard-actions {
        display: flex;
        justify-content: space-between;
        gap: 0.5rem;
        margin-top: 0.85rem;
        padding: 0.65rem 0 0.25rem;
        border-top: 1px solid var(--border);
        align-items: center;
    }
    .bc-wizard-nav { display: flex; gap: 0.35rem; align-items: center; }

    /* Review */
    .bc-review {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 0.45rem;
        margin-bottom: 0.75rem;
    }
    .bc-review-item {
        background: var(--bg-primary);
        border-radius: 6px;
        padding: 0.5rem 0.55rem;
        border: 1px solid var(--border);
    }
    .bc-review-item span {
        display: block;
        font-size: 0.625rem;
        color: var(--text-secondary);
        margin-bottom: 0.15rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .bc-review-item strong { font-size: 0.75rem; font-weight: 600; word-break: break-word; }
    .bc-review-title { font-size: 0.75rem; font-weight: 600; margin: 0 0 0.45rem; }

    /* Detail view */
    .bc-detail-head {
        display: flex;
        justify-content: space-between;
        gap: 0.65rem;
        align-items: flex-start;
        margin-bottom: 0.6rem;
    }
    .bc-detail-title { font-size: 0.95rem; font-weight: 700; margin-bottom: 0.15rem; line-height: 1.3; }
    .bc-detail-head .page-subtitle { font-size: 0.6875rem; }
    .bc-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0.45rem;
        margin-bottom: 0.6rem;
    }
    .bc-stat {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 0.45rem 0.55rem;
    }
    .bc-stat span { display: block; font-size: 0.625rem; color: var(--text-secondary); }
    .bc-stat strong { font-size: 0.95rem; font-weight: 700; }
    .bc-detail-body {
        display: grid;
        grid-template-columns: 0.85fr 1.35fr;
        gap: 0.75rem;
        padding: 0.75rem;
    }
    .bc-message-preview {
        white-space: pre-wrap;
        background: var(--bg-primary);
        border-radius: 6px;
        padding: 0.55rem 0.65rem;
        font-size: 0.75rem;
        min-height: 80px;
        border: 1px solid var(--border);
        line-height: 1.45;
    }
    .bc-message-preview.is-html { white-space: normal; }

    /* Email editor */
    .bc-html-editor {
        display: grid;
        gap: 0.3rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 0.4rem;
        background: var(--bg-primary);
    }
    .bc-html-toolbar { display: flex; flex-wrap: wrap; gap: 0.2rem; align-items: center; }
    .bc-html-toolbar button {
        border: 1px solid var(--border);
        background: #fff;
        border-radius: 4px;
        padding: 0.15rem 0.35rem;
        font-size: 0.625rem;
        font-weight: 600;
        cursor: pointer;
        color: var(--text-primary);
        font-family: inherit;
        line-height: 1.3;
    }
    .bc-html-toolbar button:hover, .bc-html-toolbar button.is-active {
        border-color: var(--accent);
        color: var(--accent);
        background: var(--accent-light);
    }
    .bc-html-toolbar-spacer { flex: 1; }
    .bc-html-visual {
        min-height: 140px;
        max-height: 280px;
        overflow: auto;
        border: 1px solid var(--border);
        border-radius: 5px;
        padding: 0.45rem 0.55rem;
        background: #fff;
        font-size: 0.75rem;
        line-height: 1.45;
    }
    .bc-html-visual:empty:before { content: attr(data-placeholder); color: var(--text-muted); font-size: 0.6875rem; }
    .bc-html-source {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 0.6875rem;
        min-height: 140px;
        max-height: 280px;
        overflow: auto;
        resize: vertical;
    }
    .bc-email-tools { display: flex; gap: 0.35rem; margin-bottom: 0.35rem; flex-wrap: wrap; }
    .bc-tool-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        border: 1px solid var(--border);
        background: #fff;
        border-radius: 5px;
        padding: 0.2rem 0.45rem;
        margin: 0.35rem 0;
        font-size: 0.6875rem;
        font-weight: 600;
        cursor: pointer;
        color: var(--text-primary);
        font-family: inherit;
        text-decoration: none;
    }
    .bc-tool-btn svg { width: 12px; height: 12px; }
    .bc-tool-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-light); }
    .bc-attach-chips { display: flex; flex-wrap: wrap; gap: 0.25rem; margin-bottom: 0.35rem; }
    .bc-attach-chips:empty { display: none; }
    .bc-attach-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        max-width: 100%;
        border: 1px solid var(--border);
        background: #fff;
        border-radius: 999px;
        padding: 0.1rem 0.4rem;
        font-size: 0.625rem;
    }
    .bc-attach-chip span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 140px; }
    .bc-attach-chip button { border: none; background: transparent; color: #b91c1c; cursor: pointer; font-size: 0.8rem; }
    .bc-detail-attachments { display: flex; flex-wrap: wrap; gap: 0.25rem; margin-top: 0.45rem; }
    .bc-detail-attach {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        border: 1px solid var(--border);
        background: #fff;
        border-radius: 5px;
        padding: 0.2rem 0.4rem;
        font-size: 0.625rem;
    }
    .bc-detail-results-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.45rem;
        flex-wrap: wrap;
    }
    .bc-detail-results-head .bc-review-title { margin: 0; }
    .bc-detail-actions { display: flex; gap: 0.35rem; flex-wrap: wrap; align-items: center; margin: 0.25rem 0; }

    /* Modal */
    .bc-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.4);
        z-index: 1200;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.75rem;
    }
    .bc-modal-overlay[hidden] { display: none; }
    .bc-modal {
        width: min(880px, 100%);
        max-height: calc(100vh - 1.5rem);
        overflow: auto;
        background: #fff;
        border-radius: 8px;
        border: 1px solid var(--border);
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.14);
    }
    .bc-modal-header, .bc-modal-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 0.85rem;
    }
    .bc-modal-header { border-bottom: 1px solid var(--border); }
    .bc-modal-footer { border-top: 1px solid var(--border); justify-content: flex-end; }
    .bc-modal-title { margin: 0; font-size: 0.8125rem; font-weight: 600; }
    .bc-modal-close {
        border: 0;
        background: none;
        width: 26px;
        height: 26px;
        border-radius: 5px;
        cursor: pointer;
        color: var(--text-secondary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .bc-modal-close svg { width: 15px; height: 15px; }
    .bc-modal-close:hover { background: var(--bg-primary); color: var(--text-primary); }
    .bc-modal-body { padding: 0.65rem 0.85rem; }
    .bc-row-action {
        border: none;
        background: none;
        color: var(--accent);
        cursor: pointer;
        font-size: 0.6875rem;
        font-weight: 600;
        padding: 0;
        font-family: inherit;
    }
    .bc-row-action:hover { text-decoration: underline; }
    .bc-html-visual img { max-width: 100%; height: auto; }
    .bc-type-card.is-disabled { opacity: 0.45; pointer-events: none; }

    /* Flash alerts in page */
    .bc-page > .flash-alert {
        padding: 0.45rem 0.65rem;
        font-size: 0.75rem;
        margin-bottom: 0.6rem;
        border-radius: 6px;
    }

    @media (max-width: 900px) {
        .bc-recip-layout, .bc-detail-body, .bc-stats, .bc-type-row { grid-template-columns: 1fr; }
        .bc-stepper { grid-template-columns: repeat(2, 1fr); }
        .bc-step-item:nth-child(2) { border-right: none; }
        .bc-step-item:nth-child(1), .bc-step-item:nth-child(2) { border-bottom: 1px solid var(--border); }
        .bc-pager-inner { justify-content: center; }
        .bc-top { flex-direction: column; align-items: stretch; }
        .bc-stats { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 520px) {
        .bc-step-label { display: none; }
        .bc-stepper { grid-template-columns: repeat(4, 1fr); }
        .bc-step-item { border-bottom: none !important; border-right: 1px solid var(--border); }
        .bc-step-item:last-child { border-right: none; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const root = document.getElementById('broadcastApp');
    const API = root.dataset.apiBase;
    const CSRF = root.dataset.csrf;
    const canSms = root.dataset.canSms === '1';
    const canEmail = root.dataset.canEmail === '1';
    const MAX_ATTACH_BYTES = 3 * 1024 * 1024;
    const MAX_ATTACH_COUNT = 5;

    function maxRecipients() {
        return Number(state.bootstrap?.max_recipients) || 10000;
    }

    function remainingRecipientSlots(selectedCount = state.selected.size) {
        return Math.max(0, maxRecipients() - selectedCount);
    }

    function recipientLimitMessage(count = state.selected.size) {
        const max = maxRecipients();
        return count > max ? `A broadcast can include at most ${max.toLocaleString()} recipients.` : null;
    }

    function canAddRecipients(count = 1, selectedCount = state.selected.size) {
        return selectedCount + count <= maxRecipients();
    }

    const state = {
        view: 'list',
        step: 1,
        page: 1,
        recipPage: 1,
        resultsPage: 1,
        detailRecipPage: 1,
        reviewPage: 1,
        bootstrap: null,
        selected: new Map(),
        current: null,
        poll: null,
        type: canSms ? 'sms' : 'email',
        emailAttachments: [],
        detailSelected: new Map(),
        lastRecipRows: [],
        lastDetailRecipRows: [],
        existingAddresses: new Set(),
    };

    const el = (id) => document.getElementById(id);

    function headers() {
        return { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' };
    }

    async function api(path, options = {}) {
        const res = await fetch(API + path, { credentials: 'same-origin', ...options, headers: { ...headers(), ...(options.headers || {}) } });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || 'Request failed');
        return data;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
    }

    function sanitizeHtml(html) {
        const wrap = document.createElement('div');
        wrap.innerHTML = String(html || '');
        wrap.querySelectorAll('script,iframe,object,embed,link,meta').forEach((node) => node.remove());
        wrap.querySelectorAll('*').forEach((node) => {
            [...node.attributes].forEach((attr) => {
                const name = attr.name.toLowerCase();
                const value = String(attr.value || '');
                if (name.startsWith('on') || (name === 'href' && /^\s*javascript:/i.test(value))) {
                    node.removeAttribute(attr.name);
                }
            });
        });
        return wrap.innerHTML;
    }

    function htmlToPlain(html) {
        const wrap = document.createElement('div');
        wrap.innerHTML = String(html || '');
        return (wrap.textContent || '').replace(/\u00a0/g, ' ').trim();
    }

    function getEmailEditor() {
        return {
            root: el('emailHtmlEditor'),
            visual: el('fEmailVisual'),
            source: el('fEmailSource'),
        };
    }

    function setEmailBody(html) {
        const ed = getEmailEditor();
        const clean = sanitizeHtml(html || '');
        if (ed.visual) ed.visual.innerHTML = clean;
        if (ed.source) ed.source.value = clean;
        updateCompose();
    }

    function getEmailBody() {
        const ed = getEmailEditor();
        if (!ed.source) return '';
        if (ed.source.hidden === false) {
            return sanitizeHtml(ed.source.value.trim());
        }
        return sanitizeHtml((ed.visual?.innerHTML || '').trim());
    }

    function setEmailEditorMode(mode) {
        const ed = getEmailEditor();
        if (!ed.root) return;
        const visualMode = mode !== 'source';
        if (visualMode) {
            if (ed.visual && ed.source) ed.visual.innerHTML = sanitizeHtml(ed.source.value);
            if (ed.visual) ed.visual.hidden = false;
            if (ed.source) ed.source.hidden = true;
        } else {
            if (ed.source && ed.visual) ed.source.value = sanitizeHtml(ed.visual.innerHTML);
            if (ed.visual) ed.visual.hidden = true;
            if (ed.source) ed.source.hidden = false;
        }
        ed.root.querySelectorAll('[data-html-mode]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.htmlMode === (visualMode ? 'visual' : 'source'));
        });
    }

    function getComposeBody() {
        return selectedType() === 'email' ? getEmailBody() : (el('fBody').value || '');
    }

    function clearComposeBody() {
        el('fBody').value = '';
        setEmailBody('');
        setEmailEditorMode('visual');
        state.emailAttachments = [];
        renderAttachChips();
    }

    function insertHtmlAtCaret(editor, html) {
        if (!editor) return;
        editor.focus();
        const clean = sanitizeHtml(html);
        try {
            document.execCommand('insertHTML', false, clean);
            return;
        } catch (_) {}
        editor.innerHTML = sanitizeHtml((editor.innerHTML || '') + clean);
    }

    function readFileAsAttachment(file) {
        return new Promise((resolve, reject) => {
            if (file.size > MAX_ATTACH_BYTES) {
                reject(new Error(`${file.name} is larger than 3 MB.`));
                return;
            }
            const reader = new FileReader();
            reader.onload = () => {
                const result = String(reader.result || '');
                const base64 = result.includes(',') ? result.split(',')[1] : result;
                resolve({
                    name: file.name,
                    contentType: file.type || 'application/octet-stream',
                    contentBytes: base64,
                    size: file.size,
                });
            };
            reader.onerror = () => reject(new Error(`Could not read ${file.name}`));
            reader.readAsDataURL(file);
        });
    }

    function renderAttachChips() {
        const chips = el('emailAttachChips');
        if (!chips) return;
        chips.innerHTML = state.emailAttachments.map((file, idx) => `
            <span class="bc-attach-chip">
                <span title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</span>
                <button type="button" data-remove-attach="${idx}" aria-label="Remove">×</button>
            </span>
        `).join('');
        chips.querySelectorAll('button[data-remove-attach]').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.emailAttachments.splice(Number(btn.dataset.removeAttach), 1);
                renderAttachChips();
            });
        });
    }

    async function addEmailAttachments(fileList) {
        const incoming = [...(fileList || [])];
        if (!incoming.length) return;
        if (state.emailAttachments.length + incoming.length > MAX_ATTACH_COUNT) {
            alert(`You can attach up to ${MAX_ATTACH_COUNT} files.`);
            return;
        }
        try {
            const files = await Promise.all(incoming.map(readFileAsAttachment));
            state.emailAttachments.push(...files);
            renderAttachChips();
        } catch (err) {
            alert(err.message || 'Could not attach file.');
        }
    }

    async function insertEmailImage(file) {
        if (!file) return;
        if (file.size > MAX_ATTACH_BYTES) {
            alert(`${file.name} is larger than 3 MB.`);
            return;
        }
        const ed = getEmailEditor();
        if (ed.source && !ed.source.hidden) {
            alert('Switch to Visual mode to insert images at the cursor, or paste an <img> tag in HTML mode.');
            return;
        }
        try {
            const attachment = await readFileAsAttachment(file);
            const imgHtml = `<img src="data:${attachment.contentType};base64,${attachment.contentBytes}" alt="${escapeHtml(file.name)}" style="max-width:100%;height:auto;">`;
            insertHtmlAtCaret(ed.visual, imgHtml);
            if (ed.source) ed.source.value = sanitizeHtml(ed.visual?.innerHTML || '');
            updateCompose();
        } catch (err) {
            alert(err.message || 'Could not insert image.');
        }
    }

    function prepareEmailSendPayload(body) {
        const attachments = state.emailAttachments.map((file) => ({
            name: file.name,
            contentType: file.contentType,
            contentBytes: file.contentBytes,
        }));
        let inlineCount = 0;
        const preparedBody = body.replace(
            /<img\b[^>]*\ssrc=(["'])data:image\/([^;]+);base64,([^"']+)\1[^>]*>/gi,
            (match, quote, ext, bytes) => {
                inlineCount += 1;
                const contentId = `bc-img-${inlineCount}-${Math.random().toString(36).slice(2, 8)}`;
                attachments.push({
                    name: `image-${inlineCount}.${ext}`,
                    contentType: `image/${ext}`,
                    contentBytes: bytes,
                    isInline: true,
                    contentId,
                });
                return match.replace(/src=(["'])data:image\/[^"']+\1/i, `src=${quote}cid:${contentId}${quote}`);
            }
        );
        return { body: preparedBody, attachments };
    }

    function embedInlineImages(html, attachments) {
        let result = html;
        (attachments || []).forEach((file) => {
            if (!file.isInline || !file.contentId || !file.contentBytes) return;
            const dataUri = `data:${file.contentType || 'image/png'};base64,${file.contentBytes}`;
            result = result.split(`cid:${file.contentId}`).join(dataUri);
        });
        return result;
    }

    function formatAttachmentSummary(attachments) {
        const files = (attachments || []).filter((a) => !a.isInline);
        const inline = (attachments || []).filter((a) => a.isInline);
        const parts = [];
        if (files.length) parts.push(`${files.length} file${files.length === 1 ? '' : 's'}`);
        if (inline.length) parts.push(`${inline.length} inline image${inline.length === 1 ? '' : 's'}`);
        return parts.join(', ') || '—';
    }

    function isRetryableStatus(status) {
        return status === 'failed' || status === 'undelivered';
    }

    function canManageCampaign(campaign) {
        if (!campaign?.can_send) return false;
        return campaign.status !== 'sending';
    }

    function clearDetailSelected() {
        state.detailSelected.clear();
        renderDetailSelected();
    }

    function renderPager(container, pagination, onPage) {
        if (!container) return;
        const pg = pagination || { current_page: 1, last_page: 1, per_page: 20, total: 0 };
        const total = Number(pg.total || 0);
        if (total <= 0) {
            container.innerHTML = '';
            return;
        }
        const current = Number(pg.current_page || 1);
        const last = Math.max(1, Number(pg.last_page || 1));
        const perPage = Number(pg.per_page || 20);
        const from = (current - 1) * perPage + 1;
        const to = Math.min(total, current * perPage);
        const start = Math.max(1, current - 2);
        const end = Math.min(last, start + 4);
        const pages = [];
        for (let i = start; i <= end; i += 1) pages.push(i);
        container.innerHTML = `<div class="bc-pager-inner">
            <span class="bc-pager-info">Showing ${from}–${to} of ${total}</span>
            <div class="bc-pager-controls">
                <button type="button" class="bc-page-btn" data-page="${current - 1}" ${current <= 1 ? 'disabled' : ''}>Previous</button>
                ${pages.map((page) => `<button type="button" class="bc-page-btn ${page === current ? 'is-active' : ''}" data-page="${page}">${page}</button>`).join('')}
                <button type="button" class="bc-page-btn" data-page="${current + 1}" ${current >= last ? 'disabled' : ''}>Next</button>
            </div>
        </div>`;
        container.querySelectorAll('button[data-page]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const next = Number(btn.dataset.page);
                if (!next || next === current) return;
                onPage(next);
            });
        });
    }

    function toggleRows(map, rows, selected, maxCount = null) {
        (rows || []).forEach((row) => {
            const key = recipientKey(row);
            if (selected) {
                if (maxCount !== null && map.size >= maxCount) return;
                map.set(key, row);
            } else {
                map.delete(key);
            }
        });
    }

    function renderDetailSelected() {
        const items = [...state.detailSelected.values()];
        const countEl = el('detailSelectedCount');
        if (countEl) countEl.textContent = String(items.length);
        const list = el('detailSelectedList');
        if (!list) return;
        list.innerHTML = items.length
            ? items.map((row) => `<div class="bc-chip"><div><strong>${escapeHtml(row.name || row.address)}</strong><small style="display:block;color:var(--text-secondary)">${escapeHtml(row.address)}</small></div><button type="button" data-detail-key="${escapeHtml(recipientKey(row))}">Remove</button></div>`).join('')
            : '<div class="bc-empty">No recipients yet.</div>';
        list.querySelectorAll('button[data-detail-key]').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.detailSelected.delete(btn.dataset.detailKey);
                renderDetailSelected();
                searchDetailRecipients();
            });
        });
    }

    async function searchDetailRecipients() {
        const campaign = state.current;
        const box = el('detailRecipResults');
        if (!box || !campaign) return;
        box.innerHTML = '<div class="bc-empty">Searching…</div>';
        try {
            const params = new URLSearchParams({
                channel: campaign.type,
                q: el('detailRecipSearch').value.trim(),
                source: el('detailRecipSource').value,
                page: String(state.detailRecipPage),
            });
            const data = await api('/recipients?' + params.toString());
            const rows = data.data || [];
            const available = rows.filter((row) => !state.existingAddresses.has(String(row.address || '').toLowerCase()));
            state.lastDetailRecipRows = available;
            if (!available.length) {
                box.innerHTML = '<div class="bc-empty">No new matching people found.</div>';
                renderPager(el('detailRecipPager'), data.pagination, (page) => {
                    state.detailRecipPage = page;
                    searchDetailRecipients();
                });
                return;
            }
            box.innerHTML = available.map((row) => {
                const key = recipientKey(row);
                const checked = state.detailSelected.has(key) ? 'checked' : '';
                return `<label class="bc-recip-row">
                    <input type="checkbox" data-detail-key="${escapeHtml(key)}" ${checked}>
                    <div>
                        <strong>${escapeHtml(row.name || row.address)}</strong>
                        <small>${escapeHtml(row.address)} · ${escapeHtml(row.meta || row.source)}</small>
                    </div>
                </label>`;
            }).join('');
            box.querySelectorAll('input[type="checkbox"]').forEach((input, index) => {
                input.addEventListener('change', () => {
                    const row = available[index];
                    const key = recipientKey(row);
                    if (input.checked) state.detailSelected.set(key, row);
                    else state.detailSelected.delete(key);
                    renderDetailSelected();
                });
            });
            renderPager(el('detailRecipPager'), data.pagination, (page) => {
                state.detailRecipPage = page;
                searchDetailRecipients();
            });
        } catch (err) {
            box.innerHTML = `<div class="bc-empty">${escapeHtml(err.message)}</div>`;
            renderPager(el('detailRecipPager'), null, () => {});
        }
    }

    function addDetailPasted() {
        const campaign = state.current;
        if (!campaign) return;
        const existing = state.existingAddresses;
        const lines = el('detailRecipPaste').value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
        lines.forEach((address) => {
            if (existing.has(address.toLowerCase())) return;
            const row = { source: 'manual', source_id: null, name: address, address, meta: 'Manual' };
            state.detailSelected.set(recipientKey(row), row);
        });
        el('detailRecipPaste').value = '';
        renderDetailSelected();
    }

    async function sendDetailRecipients() {
        const campaign = state.current;
        if (!campaign) return;
        const recipients = [...state.detailSelected.values()];
        if (!recipients.length) {
            alert('Select at least one new recipient.');
            return;
        }
        const max = maxRecipients();
        const existing = Number(campaign.recipient_count || 0);
        if (existing + recipients.length > max) {
            alert(`This broadcast can include at most ${max.toLocaleString()} recipients (${existing.toLocaleString()} already on it).`);
            return;
        }
        const btn = el('btnSendDetailRecipients');
        btn.disabled = true;
        btn.textContent = 'Sending…';
        try {
            await api(`/campaigns/${campaign.id}/recipients`, {
                method: 'POST',
                body: JSON.stringify({
                    recipients: recipients.map((row) => ({
                        source: row.source,
                        source_id: row.source_id,
                        name: row.name,
                        address: row.address,
                    })),
                }),
            });
            closeAddRecipientsModal();
            await refreshDetail(campaign.id);
            startDetailPoll(campaign.id);
            loadList();
        } catch (err) {
            alert(err.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Send to selected';
        }
    }

    async function retryFailedRecipients(recipientIds) {
        const campaign = state.current;
        if (!campaign) return;
        const count = recipientIds?.length || campaign.retryable_count || 0;
        if (!count) return;
        const label = recipientIds?.length === 1 ? 'Retry this failed recipient?' : `Retry ${count} failed recipient${count === 1 ? '' : 's'}?`;
        if (!confirm(label)) return;

        const btn = el('btnRetryFailed');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Retrying…';
        }
        try {
            const payload = recipientIds?.length ? { recipient_ids: recipientIds } : {};
            await api(`/campaigns/${campaign.id}/retry`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            await refreshDetail(campaign.id);
            startDetailPoll(campaign.id);
            loadList();
        } catch (err) {
            alert(err.message);
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = `Retry failed (${state.current?.retryable_count || 0})`;
            }
        }
    }

    function startDetailPoll(id) {
        if (state.poll) {
            clearInterval(state.poll);
            state.poll = null;
        }
        if (state.current?.status !== 'sending') return;
        state.poll = setInterval(async () => {
            try {
                await refreshDetail(id, { silent: true });
                if (state.current.status !== 'sending') {
                    clearInterval(state.poll);
                    state.poll = null;
                    loadList();
                }
            } catch (_) {}
        }, 2500);
    }

    function formatDate(value) {
        if (!value) return '—';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return '—';
        return d.toLocaleString();
    }

    function badge(kind, label) {
        return `<span class="bc-badge ${kind}">${escapeHtml(label)}</span>`;
    }

    function statusLabel(status) {
        return ({ sending: 'Sending', sent: 'Sent', partial: 'Partial', failed: 'Failed', delivered: 'Delivered', undelivered: 'Undelivered', pending: 'Pending' })[status] || status;
    }

    const STEP_LABELS = ['Setup', 'Recipients', 'Compose', 'Review'];

    function updateContextNav() {
        const nav = el('bcContextNav');
        const title = el('bcContextTitle');
        const topActions = el('bcTopActions');
        if (!nav || !title) return;

        if (state.view === 'list') {
            nav.hidden = true;
            if (topActions) topActions.hidden = false;
            return;
        }

        nav.hidden = false;
        if (topActions) topActions.hidden = true;

        if (state.view === 'wizard') {
            title.textContent = `New broadcast · ${STEP_LABELS[state.step - 1] || 'Setup'}`;
        } else if (state.view === 'detail') {
            title.textContent = state.current?.name || 'Broadcast details';
        }
    }

    function showView(name) {
        state.view = name;
        el('viewList').hidden = name !== 'list';
        el('viewWizard').hidden = name !== 'wizard';
        el('viewDetail').hidden = name !== 'detail';
        updateContextNav();
        if (name !== 'detail' && state.poll) {
            clearInterval(state.poll);
            state.poll = null;
        }
    }

    function recipientKey(row) {
        return `${row.source || 'manual'}:${row.source_id || ''}:${String(row.address || '').toLowerCase()}`;
    }

    function setStep(step) {
        state.step = step;
        document.querySelectorAll('.bc-step-item').forEach((btn) => {
            const n = Number(btn.dataset.step);
            btn.classList.toggle('active', n === step);
            btn.classList.toggle('done', n < step);
            btn.setAttribute('aria-selected', n === step ? 'true' : 'false');
        });
        document.querySelectorAll('.bc-panel').forEach((panel) => { panel.hidden = Number(panel.dataset.panel) !== step; });
        el('btnBack').hidden = step === 1;
        el('btnNext').textContent = step === 4 ? 'Send' : 'Continue';
        updateContextNav();
        if (step === 2) searchRecipients();
        if (step === 3) updateCompose();
        if (step === 4) renderReview();
    }

    function selectedType() {
        return document.querySelector('input[name="bcType"]:checked')?.value || state.type;
    }

    function senderTypeLabel(type) {
        if (type === 'broadcast') return 'Direct';
        if (type === 'shared') return 'Shared';
        return '';
    }

    function fillSenders() {
        const data = state.bootstrap || {};
        const smsSelect = el('fFromNumber');
        smsSelect.innerHTML = (data.sms_senders || []).map((n) =>
            `<option value="${escapeHtml(n.phone_number)}">${escapeHtml((n.friendly_name ? n.friendly_name + ' — ' : '') + n.phone_number)}${n.assigned ? ' (assigned)' : ''}</option>`
        ).join('') || '<option value="">No Twilio SMS numbers found</option>';

        const emailSelect = el('fInbox');
        const senders = data.email_senders || [];
        emailSelect.innerHTML = senders.map((inbox) => {
            const kind = senderTypeLabel(inbox.type);
            const prefix = kind ? `[${kind}] ` : '';
            const suffix = inbox.connected ? '' : ' (not connected)';
            return `<option value="${inbox.id}" ${inbox.connected ? '' : 'disabled'}>${escapeHtml(prefix + inbox.name)} — ${escapeHtml(inbox.email || 'No address')}${suffix}</option>`;
        }).join('') || '<option value="">No Microsoft 365 senders available</option>';

        const connectBtn = el('btnConnectM365');
        if (connectBtn) {
            const connectUrl = data.outlook_connect_url || connectBtn.getAttribute('href');
            connectBtn.href = connectUrl;
            connectBtn.hidden = !data.outlook_configured;
        }
    }

    function applyType() {
        const type = selectedType();
        state.type = type;
        el('smsSenderBlock').hidden = type !== 'sms';
        el('emailSenderBlock').hidden = type !== 'email';
        el('emailSubjectBlock').hidden = type !== 'email';
        el('smsBodyBlock').hidden = type !== 'sms';
        el('emailBodyBlock').hidden = type !== 'email';
        el('typeHint').textContent = type === 'sms'
            ? (state.bootstrap?.twilio_connected ? '' : 'Connect Twilio in Integrations before sending SMS broadcasts.')
            : (state.bootstrap?.outlook_configured ? '' : 'Add Microsoft OAuth credentials in Integrations before connecting a mailbox.');
        updateCompose();
        state.selected.clear();
        state.recipPage = 1;
        renderSelected();
        searchRecipients();
    }

    function updateCompose() {
        if (selectedType() === 'sms') {
            const body = el('fBody').value || '';
            el('charCount').textContent = `${body.length} / 1600 characters`;
        } else {
            const body = getEmailBody();
            el('emailCharCount').textContent = `${body.length} characters`;
        }
    }

    function renderSelected() {
        const items = [...state.selected.values()];
        const max = maxRecipients();
        el('selectedCount').textContent = `${items.length.toLocaleString()} / ${max.toLocaleString()}`;
        const hint = el('recipientLimitHint');
        if (hint) {
            hint.textContent = items.length >= max
                ? 'Recipient limit reached. Remove some before adding more.'
                : `Up to ${max.toLocaleString()} recipients per broadcast. Large sends continue in the background after you submit.`;
        }
        el('selectedList').innerHTML = items.length
            ? items.map((row) => `<div class="bc-chip"><div><strong>${escapeHtml(row.name || row.address)}</strong><small style="display:block;color:var(--text-secondary)">${escapeHtml(row.address)}</small></div><button type="button" data-key="${escapeHtml(recipientKey(row))}">Remove</button></div>`).join('')
            : '<div class="bc-empty">No recipients yet.</div>';
        el('selectedList').querySelectorAll('button[data-key]').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.selected.delete(btn.dataset.key);
                renderSelected();
            });
        });
    }

    async function searchRecipients() {
        const box = el('recipResults');
        box.innerHTML = '<div class="bc-empty">Searching…</div>';
        try {
            const params = new URLSearchParams({
                channel: selectedType(),
                q: el('recipSearch').value.trim(),
                source: el('recipSource').value,
                page: String(state.recipPage),
            });
            const data = await api('/recipients?' + params.toString());
            const rows = data.data || [];
            state.lastRecipRows = rows;
            if (!rows.length) {
                box.innerHTML = '<div class="bc-empty">No matching people with a valid address.</div>';
                renderPager(el('recipPager'), data.pagination, (page) => {
                    state.recipPage = page;
                    searchRecipients();
                });
                return;
            }
            box.innerHTML = rows.map((row) => {
                const key = recipientKey(row);
                const checked = state.selected.has(key) ? 'checked' : '';
                return `<label class="bc-recip-row">
                    <input type="checkbox" data-key="${escapeHtml(key)}" ${checked}>
                    <div>
                        <strong>${escapeHtml(row.name || row.address)}</strong>
                        <small>${escapeHtml(row.address)} · ${escapeHtml(row.meta || row.source)}</small>
                    </div>
                </label>`;
            }).join('');
            box.querySelectorAll('input[type="checkbox"]').forEach((input, index) => {
                input.addEventListener('change', () => {
                    const row = rows[index];
                    const key = recipientKey(row);
                    if (input.checked) {
                        if (!canAddRecipients()) {
                            input.checked = false;
                            alert(recipientLimitMessage(maxRecipients() + 1));
                            return;
                        }
                        state.selected.set(key, row);
                    } else {
                        state.selected.delete(key);
                    }
                    renderSelected();
                });
            });
            renderPager(el('recipPager'), data.pagination, (page) => {
                state.recipPage = page;
                searchRecipients();
            });
        } catch (err) {
            box.innerHTML = `<div class="bc-empty">${escapeHtml(err.message)}</div>`;
            renderPager(el('recipPager'), null, () => {});
        }
    }

    function addPasted() {
        const lines = el('recipPaste').value.split(/\r?\n/).map((line) => line.trim()).filter(Boolean);
        if (!lines.length) return;
        const slots = remainingRecipientSlots();
        if (slots <= 0) {
            alert(recipientLimitMessage(maxRecipients() + 1));
            return;
        }
        let added = 0;
        lines.forEach((address) => {
            if (added >= slots) return;
            const row = { source: 'manual', source_id: null, name: address, address, meta: 'Manual' };
            const key = recipientKey(row);
            if (state.selected.has(key)) return;
            state.selected.set(key, row);
            added += 1;
        });
        if (lines.length > added) {
            alert(`Added ${added.toLocaleString()} address${added === 1 ? '' : 'es'}. ${recipientLimitMessage(maxRecipients() + 1)}`);
        }
        el('recipPaste').value = '';
        renderSelected();
    }

    function renderReview() {
        const type = selectedType();
        const sender = type === 'sms'
            ? (el('fFromNumber').selectedOptions[0]?.textContent || el('fFromNumber').value)
            : (el('fInbox').selectedOptions[0]?.textContent || 'Microsoft 365 mailbox');
        const recipients = [...state.selected.values()];
        const perPage = 20;
        const lastPage = Math.max(1, Math.ceil(recipients.length / perPage) || 1);
        if (state.reviewPage > lastPage) state.reviewPage = lastPage;
        const pageRows = recipients.slice((state.reviewPage - 1) * perPage, state.reviewPage * perPage);
        el('reviewSummary').innerHTML = [
            ['Name', el('fName').value.trim()],
            ['Type', type.toUpperCase()],
            ['Sender', sender],
            ['Recipients', String(recipients.length)],
            ...(type === 'email' ? [
                ['Subject', el('fSubject').value.trim()],
                ['Attachments', formatAttachmentSummary([
                    ...state.emailAttachments.map((f) => ({ name: f.name, isInline: false })),
                    ...(getEmailBody().match(/data:image\/[^;]+;base64,/g) || []).map((_, idx) => ({ isInline: true, name: `image-${idx + 1}` })),
                ])],
            ] : []),
        ].map(([label, value]) => `<div class="bc-review-item"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value || '—')}</strong></div>`).join('');
        el('reviewRecipients').innerHTML = pageRows.length
            ? pageRows.map((row) => `<tr><td>${escapeHtml(row.name || '—')}</td><td>${escapeHtml(row.address)}</td><td>${escapeHtml(row.meta || row.source)}</td></tr>`).join('')
            : '<tr><td colspan="3" class="bc-empty">No recipients selected.</td></tr>';
        renderPager(el('reviewPager'), {
            current_page: state.reviewPage,
            last_page: lastPage,
            per_page: perPage,
            total: recipients.length,
        }, (page) => {
            state.reviewPage = page;
            renderReview();
        });
    }

    function validateStep(step) {
        if (step === 1) {
            if (!el('fName').value.trim()) return 'Enter a broadcast name.';
            if (selectedType() === 'sms') {
                if (!canSms) return 'You do not have permission to send SMS broadcasts.';
                if (!el('fFromNumber').value) return 'Select a Twilio phone number.';
            } else {
                if (!canEmail) return 'You do not have permission to send email broadcasts.';
                if (!el('fInbox').value) return 'Select a Microsoft 365 sender or sign in with Microsoft 365.';
            }
        }
        if (step === 2) {
            if (state.selected.size === 0) return 'Select at least one recipient.';
            const limitError = recipientLimitMessage();
            if (limitError) return limitError;
        }
        if (step === 3) {
            if (selectedType() === 'email' && !el('fSubject').value.trim()) return 'Enter an email subject.';
            const body = getComposeBody();
            const plain = selectedType() === 'email' ? htmlToPlain(body) : body.trim();
            if (!plain) return 'Compose a message.';
            if (selectedType() === 'sms' && body.length > 1600) return 'SMS messages can be at most 1600 characters.';
        }
        return null;
    }

    async function sendBroadcast() {
        const error = validateStep(4) || validateStep(1) || validateStep(2) || validateStep(3);
        if (error) { alert(error); return; }
        el('btnNext').disabled = true;
        el('btnNext').textContent = 'Sending…';
        try {
            const type = selectedType();
            const rawBody = getComposeBody();
            const emailPayload = type === 'email' ? prepareEmailSendPayload(rawBody) : { body: rawBody, attachments: [] };
            const payload = {
                name: el('fName').value.trim(),
                type,
                from_number: el('fFromNumber').value || null,
                shared_inbox_id: el('fInbox').value ? Number(el('fInbox').value) : null,
                subject: el('fSubject').value.trim(),
                body: emailPayload.body,
                attachments: emailPayload.attachments,
                recipients: [...state.selected.values()].map((row) => ({
                    source: row.source,
                    source_id: row.source_id,
                    name: row.name,
                    address: row.address,
                })),
            };
            const data = await api('/campaigns', { method: 'POST', body: JSON.stringify(payload) });
            state.emailAttachments = [];
            renderAttachChips();
            await openDetail(data.data.id);
        } catch (err) {
            alert(err.message);
        } finally {
            el('btnNext').disabled = false;
            el('btnNext').textContent = 'Send';
        }
    }

    async function loadList() {
        const params = new URLSearchParams({
            q: el('listSearch').value.trim(),
            type: el('listType').value,
            status: el('listStatus').value,
            page: String(state.page),
        });
        const data = await api('/campaigns?' + params.toString());
        const rows = data.data || [];
        el('listBody').innerHTML = rows.length
            ? rows.map((row) => `<tr data-id="${row.id}">
                <td><strong>${escapeHtml(row.name)}</strong></td>
                <td>${badge(row.type, row.type === 'sms' ? 'SMS' : 'Email')}</td>
                <td>${escapeHtml(row.sender || '—')}</td>
                <td>${row.recipient_count}</td>
                <td>${badge(row.status, statusLabel(row.status))}</td>
                <td>${escapeHtml(formatDate(row.created_at))}</td>
                <td>${escapeHtml(formatDate(row.sent_at))}</td>
            </tr>`).join('')
            : '<tr><td colspan="7" class="bc-empty">No broadcasts yet. Create one to send bulk SMS or email.</td></tr>';
        el('listBody').querySelectorAll('tr[data-id]').forEach((row) => {
            row.addEventListener('click', () => openDetail(Number(row.dataset.id)));
        });
        const pg = data.pagination || { current_page: 1, last_page: 1, per_page: 20, total: rows.length };
        renderPager(el('listPager'), pg, (page) => {
            state.page = page;
            loadList();
        });
    }

    function renderDetail(campaign) {
        el('detailName').textContent = campaign.name;
        el('detailMeta').textContent = `${campaign.type === 'sms' ? 'SMS' : 'Email'} · ${campaign.sender || 'No sender'} · Created ${formatDate(campaign.created_at)}`;
        el('detailStatus').innerHTML = badge(campaign.status, statusLabel(campaign.status));
        el('detailStats').innerHTML = [
            ['Recipients', campaign.recipient_count],
            ['Sent', campaign.sent_count],
            [campaign.type === 'sms' ? 'Delivered' : 'Successful', campaign.delivered_count],
            ['Failed', campaign.failed_count],
        ].map(([label, value]) => `<div class="bc-stat"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('');
        const preview = el('detailMessage');
        if (campaign.type === 'email') {
            const subjectLine = campaign.subject ? `<p><strong>Subject:</strong> ${escapeHtml(campaign.subject)}</p>` : '';
            let bodyHtml = String(campaign.body || '').includes('<')
                ? sanitizeHtml(campaign.body)
                : escapeHtml(campaign.body || '').replace(/\r?\n/g, '<br>');
            bodyHtml = embedInlineImages(bodyHtml, campaign.attachments || []);
            preview.classList.add('is-html');
            preview.innerHTML = subjectLine + bodyHtml;
            const fileAttachments = (campaign.attachments || []).filter((a) => !a.isInline);
            const attachRow = el('detailAttachments');
            if (attachRow) {
                attachRow.innerHTML = fileAttachments.length
                    ? fileAttachments.map((file) => `<span class="bc-detail-attach">${escapeHtml(file.name)}${file.size ? ` · ${Math.max(1, Math.round(file.size / 1024))} KB` : ''}</span>`).join('')
                    : '';
                attachRow.hidden = fileAttachments.length === 0;
            }
        } else {
            preview.classList.remove('is-html');
            preview.textContent = campaign.body || '';
            const attachRow = el('detailAttachments');
            if (attachRow) {
                attachRow.innerHTML = '';
                attachRow.hidden = true;
            }
        }
        const recipients = campaign.recipients || [];
        state.existingAddresses = new Set(campaign.recipient_addresses || recipients.map((r) => String(r.address || '').toLowerCase()));
        const manageable = canManageCampaign(campaign);
        const actions = el('detailActions');
        const retryBtn = el('btnRetryFailed');
        const addBtn = el('btnToggleAddRecipients');
        if (actions) actions.hidden = !manageable;
        if (retryBtn) {
            const retryCount = campaign.retryable_count || recipients.filter((r) => isRetryableStatus(r.status)).length;
            retryBtn.hidden = retryCount === 0;
            retryBtn.textContent = `Retry failed (${retryCount})`;
            retryBtn.disabled = false;
        }
        if (addBtn) addBtn.hidden = !manageable;
        if (el('detailRecipPaste')) {
            el('detailRecipPaste').placeholder = campaign.type === 'sms'
                ? '+15551234567'
                : 'name@example.com';
        }
        el('detailRecipients').innerHTML = recipients.length
            ? recipients.map((row) => {
                const retryCell = manageable && isRetryableStatus(row.status)
                    ? `<td><button type="button" class="bc-row-action" data-retry-id="${row.id}">Retry</button></td>`
                    : '<td></td>';
                return `<tr>
                <td>${escapeHtml(row.name || '—')}</td>
                <td>${escapeHtml(row.address)}</td>
                <td>${badge(row.status, statusLabel(row.status))}</td>
                <td>${escapeHtml(row.error_message || '—')}</td>
                ${retryCell}
            </tr>`;
            }).join('')
            : '<tr><td colspan="5" class="bc-empty">No recipient results yet.</td></tr>';
        el('detailRecipients').querySelectorAll('[data-retry-id]').forEach((btn) => {
            btn.addEventListener('click', () => retryFailedRecipients([Number(btn.dataset.retryId)]));
        });
        renderPager(el('resultsPager'), campaign.recipients_pagination, (page) => {
            state.resultsPage = page;
            refreshDetail(campaign.id);
        });
    }

    async function refreshDetail(id, options = {}) {
        const params = new URLSearchParams({
            page: String(state.resultsPage || 1),
            per_page: '20',
        });
        const data = await api('/campaigns/' + id + '?' + params.toString());
        state.current = data.data;
        const pg = state.current?.recipients_pagination;
        if (pg && Number(pg.last_page) >= 1 && state.resultsPage > Number(pg.last_page)) {
            state.resultsPage = Number(pg.last_page);
            return refreshDetail(id, options);
        }
        if (!options.silent || state.view === 'detail') {
            renderDetail(state.current);
        }
        return state.current;
    }

    function closeAddRecipientsModal() {
        const modal = el('addRecipientsModal');
        if (modal) modal.hidden = true;
        clearDetailSelected();
        state.lastDetailRecipRows = [];
        state.detailRecipPage = 1;
        if (el('detailRecipSearch')) el('detailRecipSearch').value = '';
        if (el('detailRecipPaste')) el('detailRecipPaste').value = '';
        document.body.style.overflow = '';
        if (el('detailRecipSource')) el('detailRecipSource').value = 'all';
        const results = el('detailRecipResults');
        if (results) results.innerHTML = '<div class="bc-empty">Search to find people with a phone number or email address.</div>';
        renderPager(el('detailRecipPager'), null, () => {});
    }

    function openAddRecipientsModal() {
        if (!state.current || !canManageCampaign(state.current)) return;
        const modal = el('addRecipientsModal');
        if (!modal) return;
        clearDetailSelected();
        state.detailRecipPage = 1;
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        searchDetailRecipients();
    }

    async function openDetail(id) {
        state.resultsPage = 1;
        await refreshDetail(id);
        closeAddRecipientsModal();
        showView('detail');
        startDetailPoll(id);
    }

    function resetWizard() {
        el('fName').value = '';
        el('fSubject').value = '';
        clearComposeBody();
        el('recipSearch').value = '';
        el('recipPaste').value = '';
        state.selected.clear();
        renderSelected();
        const smsRadio = document.querySelector('input[name="bcType"][value="sms"]');
        const emailRadio = document.querySelector('input[name="bcType"][value="email"]');
        if (canSms && smsRadio) smsRadio.checked = true;
        else if (emailRadio) emailRadio.checked = true;
        applyType();
        setStep(1);
    }

    async function boot() {
        if (!canSms) el('typeSmsCard').classList.add('is-disabled');
        if (!canEmail) el('typeEmailCard').classList.add('is-disabled');
        const data = await api('/bootstrap');
        state.bootstrap = data.data;
        fillSenders();
        resetWizard();
        showView('list');
        await loadList();
    }

    el('btnNew').addEventListener('click', () => { resetWizard(); showView('wizard'); });
    el('btnCancel').addEventListener('click', () => showView('list'));
    el('btnContextBack')?.addEventListener('click', () => {
        if (state.view === 'detail') loadList();
        showView('list');
    });
    el('btnBack').addEventListener('click', () => setStep(Math.max(1, state.step - 1)));
    el('btnNext').addEventListener('click', () => {
        if (state.step === 4) { sendBroadcast(); return; }
        const error = validateStep(state.step);
        if (error) { alert(error); return; }
        setStep(state.step + 1);
    });
    document.querySelectorAll('.bc-step-item').forEach((btn) => {
        btn.addEventListener('click', () => {
            const next = Number(btn.dataset.step);
            if (next > state.step) {
                const error = validateStep(state.step);
                if (error) { alert(error); return; }
            }
            setStep(next);
        });
    });
    document.querySelectorAll('input[name="bcType"]').forEach((input) => input.addEventListener('change', applyType));
    el('fBody').addEventListener('input', updateCompose);
    el('emailHtmlEditor')?.addEventListener('click', (e) => {
        const modeBtn = e.target.closest('[data-html-mode]');
        if (modeBtn) {
            e.preventDefault();
            setEmailEditorMode(modeBtn.dataset.htmlMode);
            return;
        }
        const cmdBtn = e.target.closest('[data-cmd]');
        if (!cmdBtn) return;
        e.preventDefault();
        const ed = getEmailEditor();
        if (ed.source && !ed.source.hidden) {
            alert('Switch to Visual mode to use formatting buttons, or edit HTML directly in HTML mode.');
            return;
        }
        ed.visual?.focus();
        const cmd = cmdBtn.dataset.cmd;
        if (cmd === 'createLink') {
            const url = prompt('Link URL', 'https://');
            if (url) document.execCommand('createLink', false, url);
        } else {
            document.execCommand(cmd, false, null);
        }
        if (ed.source) ed.source.value = sanitizeHtml(ed.visual?.innerHTML || '');
        updateCompose();
    });
    el('emailHtmlEditor')?.addEventListener('input', () => {
        const ed = getEmailEditor();
        if (ed.source?.hidden !== false && ed.visual && ed.source) {
            ed.source.value = sanitizeHtml(ed.visual.innerHTML);
        }
        updateCompose();
    });
    el('fEmailSource')?.addEventListener('input', updateCompose);
    el('btnEmailAttach')?.addEventListener('click', () => el('emailAttachInput')?.click());
    el('btnEmailImage')?.addEventListener('click', () => el('emailImageInput')?.click());
    el('emailAttachInput')?.addEventListener('change', (e) => {
        addEmailAttachments(e.target.files);
        e.target.value = '';
    });
    el('emailImageInput')?.addEventListener('change', async (e) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        await insertEmailImage(file);
    });
    el('recipSearch').addEventListener('input', () => {
        state.recipPage = 1;
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(searchRecipients, 250);
    });
    el('recipSource').addEventListener('change', () => { state.recipPage = 1; searchRecipients(); });
    el('btnPaste').addEventListener('click', addPasted);
    el('btnClearSelected').addEventListener('click', () => { state.selected.clear(); renderSelected(); searchRecipients(); });
    el('btnSelectAllRecipients')?.addEventListener('click', () => {
        const before = state.selected.size;
        toggleRows(state.selected, state.lastRecipRows, true, maxRecipients());
        if (state.selected.size >= maxRecipients() && before < state.lastRecipRows.length) {
            alert(recipientLimitMessage(maxRecipients() + 1));
        }
        renderSelected();
        searchRecipients();
    });
    el('btnDeselectAllRecipients')?.addEventListener('click', () => {
        toggleRows(state.selected, state.lastRecipRows, false);
        renderSelected();
        searchRecipients();
    });
    el('btnRetryFailed')?.addEventListener('click', () => retryFailedRecipients());
    el('btnToggleAddRecipients')?.addEventListener('click', openAddRecipientsModal);
    el('btnCloseAddRecipients')?.addEventListener('click', closeAddRecipientsModal);
    el('btnCancelAddRecipients')?.addEventListener('click', closeAddRecipientsModal);
    el('addRecipientsModal')?.addEventListener('click', (e) => {
        if (e.target === el('addRecipientsModal')) closeAddRecipientsModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && el('addRecipientsModal') && !el('addRecipientsModal').hidden) {
            closeAddRecipientsModal();
        }
    });
    el('btnSelectAllDetailRecipients')?.addEventListener('click', () => {
        const slots = Math.max(0, maxRecipients() - Number(state.current?.recipient_count || 0));
        const before = state.detailSelected.size;
        toggleRows(state.detailSelected, state.lastDetailRecipRows, true, slots);
        if (state.detailSelected.size >= slots && before < state.lastDetailRecipRows.length) {
            alert(`This broadcast can include at most ${maxRecipients().toLocaleString()} recipients in total.`);
        }
        renderDetailSelected();
        searchDetailRecipients();
    });
    el('btnDeselectAllDetailRecipients')?.addEventListener('click', () => {
        toggleRows(state.detailSelected, state.lastDetailRecipRows, false);
        renderDetailSelected();
        searchDetailRecipients();
    });
    el('btnDetailPaste')?.addEventListener('click', addDetailPasted);
    el('btnSendDetailRecipients')?.addEventListener('click', sendDetailRecipients);
    el('detailRecipSearch')?.addEventListener('input', () => {
        state.detailRecipPage = 1;
        clearTimeout(state.detailSearchTimer);
        state.detailSearchTimer = setTimeout(searchDetailRecipients, 250);
    });
    el('detailRecipSource')?.addEventListener('change', () => {
        state.detailRecipPage = 1;
        searchDetailRecipients();
    });
    ['listSearch', 'listType', 'listStatus'].forEach((id) => {
        el(id).addEventListener('change', () => { state.page = 1; loadList(); });
        el(id).addEventListener('input', () => { state.page = 1; clearTimeout(state.listTimer); state.listTimer = setTimeout(loadList, 250); });
    });

    boot().catch((err) => {
        el('listBody').innerHTML = `<tr><td colspan="7" class="bc-empty">${escapeHtml(err.message)}</td></tr>`;
    });
})();
</script>
@endpush
