@extends('layouts.app')

@section('title', 'Broadcast Messaging')

@push('styles')
    @vite(['resources/css/pages/broadcast-messaging.css'])
@endpush

@push('scripts')
    @vite(['resources/js/pages/broadcast-messaging.js'])
@endpush

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
                        <tbody id="listBody" aria-busy="true">
                            @include('partials.skeleton-table-rows', ['rows' => 6, 'cols' => 7])
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
