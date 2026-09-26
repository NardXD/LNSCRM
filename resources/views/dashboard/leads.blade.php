@extends('layouts.app')

@section('title', 'Leads')

@php
    $leadFormOptions = $leadFormOptions ?? \App\Models\Lead::formOptions();
@endphp

@push('styles')
    @vite(['resources/css/pages/leads.css'])
@endpush

@push('scripts')
<script>
    window.__leadsConfig = {
        leadOptions: @json($leadFormOptions),
        storeganiseConnected: @json(!empty($storeganiseConnected)),
        canViewQuotationBuilder: @json(!empty($canViewQuotationBuilder)),
        leadQuoteUrlBase: @json(url('/quotation-builder/leads')),
        canManageLeadRules: @json(!empty($canManageLeadRules)),
    };
</script>
    @vite(['resources/js/pages/leads.js'])
@endpush

@section('content')
    <div class="ld-page-wrapper">
    <div class="ld-page">
    <div class="ld-top">
        <div class="ld-top-main">
            <h1 class="ld-title">Leads</h1>
            <p class="ld-subtitle">Phones, emails, and social names shared across Phone, Inbox, Viber, WhatsApp, Facebook, and SMS.</p>
        </div>
        <div class="leads-header-actions ld-top-actions">
            <button type="button" class="btn btn-secondary btn-sm" id="leadLabelsBtn">Labels</button>
            <button type="button" class="btn btn-secondary btn-sm" id="leadStatusesBtn">Statuses</button>
            <button type="button" class="btn btn-secondary btn-sm" id="leadRulesBtn">Rules</button>
            <button type="button" class="btn btn-primary btn-sm" id="newLeadBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New lead
            </button>
        </div>
    </div>

    <div class="leads-toolbar">
        <input type="search" id="leadSearch" class="leads-search" placeholder="Search name, phone, email, or label…">
        <div class="leads-toolbar-filters">
            <div class="leads-label-filter" id="leadLabelFilter">
                <div id="leadLabelFilterChips" class="lead-label-filter-chips"></div>
                <select id="leadLabelFilterSelect" aria-label="Filter by labels">
                    <option value="">Filter labels…</option>
                </select>
            </div>
            <select id="leadSourceFilter" class="leads-source-filter" aria-label="Filter by source">
                <option value="">All sources</option>
            </select>
            <select id="leadAssigneeFilter" class="leads-assignee-filter" aria-label="Filter by assignee">
                <option value="">All assignees</option>
                <option value="__none__">Unassigned</option>
            </select>
            <select id="leadThreadFilter" class="leads-thread-filter" aria-label="Filter by shared mailbox thread">
                <option value="">All leads</option>
                <option value="1">No shared mailbox thread</option>
            </select>
            <select id="leadSortFilter" class="leads-sort-filter" aria-label="Sort leads by">
                <option value="lead_age" selected>Sort: Lead Age</option>
                <option value="updated_at">Sort: Updated</option>
                <option value="thread_age">Sort: Thread Age</option>
            </select>
            <select id="leadSortDirFilter" class="leads-sort-filter" aria-label="Sort direction">
                <option value="asc" selected>Ascending</option>
                <option value="desc">Descending</option>
            </select>
        </div>
    </div>

    <div class="leads-tabs" role="tablist" id="leadStatusTabs">
        <button type="button" class="leads-tab active" data-status="all">All</button>
    </div>

    <div class="leads-card" id="leadsCard">
        <div class="leads-busy-overlay" id="leadsTableBusy" hidden>
            <span class="leads-spinner" aria-hidden="true"></span>
            <span>Updating table…</span>
        </div>
        <div class="table-container">
            <table class="data-table leads-table">
                <thead>
                    <tr>
                        <th>Lead</th>
                        <th>Lead Age</th>
                        <th>Phones</th>
                        <th>Emails</th>
                        <th>Labels</th>
                        <th>Assigned</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th>Thread Age</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="leadsTableBody" aria-busy="true">
                    @include('partials.skeleton-table-rows', ['rows' => 8, 'cols' => 10])
                </tbody>
            </table>
        </div>
        <div class="leads-pagination">
            <span id="leadsPageInfo">Showing 0 of 0</span>
            <div>
                <button type="button" class="btn btn-secondary btn-sm" id="leadsPrev" disabled>Previous</button>
                <button type="button" class="btn btn-secondary btn-sm" id="leadsNext" disabled>Next</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="leadModal">
        <div class="modal-content leads-modal">
            <div class="leads-busy-overlay" id="leadModalBusy" hidden>
                <span class="leads-spinner" aria-hidden="true"></span>
                <span id="leadModalBusyText">Saving…</span>
            </div>
            <div class="modal-header lead-modal-header">
                <div class="lead-modal-heading">
                    <h3 id="leadModalTitle">New Lead</h3>
                    <p id="leadModalAdded" class="lead-modal-added" hidden></p>
                    <div id="leadModalChannelLinks" class="lead-modal-channel-links" hidden aria-label="Messaging channels"></div>
                </div>
                <div class="lead-modal-header-actions">
                    @if (! empty($canViewQuotationBuilder))
                        <a id="leadModalQuoteBtn" class="btn btn-primary btn-sm lead-modal-quote-btn" href="#" hidden>Build Quote</a>
                    @endif
                    <button type="button" class="modal-close-btn lead-modal-close" id="closeLeadModal">&times;</button>
                </div>
                <div id="leadModalHeaderMeta" class="lead-modal-header-meta" hidden>
                    <div class="lead-modal-meta-block lead-modal-heading-labels" id="leadModalLabelsWrap" hidden>
                        <span class="lead-modal-meta-kicker">Labels</span>
                        <div id="leadModalLabels" class="lead-modal-labels"></div>
                    </div>
                    <div class="lead-modal-meta-block lead-modal-header-facility" id="leadModalStoreganiseWrap" hidden>
                        <span class="lead-modal-meta-kicker">Storeganise</span>
                        <span id="leadModalStoreganise" class="lead-modal-facility"></span>
                    </div>
                </div>
            </div>
            <div class="leads-modal-grid">
                <form id="leadForm" class="leads-form" novalidate>
                    <input type="hidden" id="leadId">
                    <div class="lead-form-tabs" role="tablist" aria-label="Lead form sections">
                        <button type="button" class="lead-form-tab active" role="tab" id="leadTabPrimary" data-lead-tab="primary" aria-selected="true" title="Primary lead info">Primary</button>
                        <button type="button" class="lead-form-tab" role="tab" id="leadTabAlternate" data-lead-tab="alternate" aria-selected="false" title="Alternate lead info">Alternate</button>
                        <button type="button" class="lead-form-tab" role="tab" id="leadTabSource" data-lead-tab="source" aria-selected="false" title="How did you hear about us?">Source</button>
                        <button type="button" class="lead-form-tab" role="tab" id="leadTabMatching" data-lead-tab="matching" aria-selected="false" title="Channel matching">Matching</button>
                        <button type="button" class="lead-form-tab" role="tab" id="leadTabNotes" data-lead-tab="notes" aria-selected="false" title="Labels and notes">Labels</button>
                    </div>

                    <section class="lead-form-panel active" data-lead-panel="primary" role="tabpanel" aria-labelledby="leadTabPrimary">
                        <div class="form-row form-row-3">
                            <div class="form-group">
                                <label for="leadTitle">Mr/Ms.</label>
                                <select id="leadTitle">
                                    <option value="">Select</option>
                                    @foreach ($leadFormOptions['titles'] as $title)
                                        <option value="{{ $title }}">{{ $title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="leadFirstName">First name *</label>
                                <input type="text" id="leadFirstName" required maxlength="255" placeholder="First name">
                            </div>
                            <div class="form-group">
                                <label for="leadLastName">Last name *</label>
                                <input type="text" id="leadLastName" required maxlength="255" placeholder="Last name">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="leadAddress">Address</label>
                            <input type="text" id="leadAddress" maxlength="500" placeholder="Street address">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="leadCity">City</label>
                                <input type="text" id="leadCity" maxlength="255" placeholder="City">
                            </div>
                            <div class="form-group">
                                <label for="leadPostal">Postal/Zip</label>
                                <input type="text" id="leadPostal" maxlength="20" placeholder="Postal or ZIP">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="leadCompany">Company</label>
                                <input type="text" id="leadCompany" maxlength="255" placeholder="Optional">
                            </div>
                            <div class="form-group">
                                <label for="leadDob">Date of birth</label>
                                <input type="date" id="leadDob">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="identity-label">
                                <label>Phone</label>
                                <button type="button" class="link-btn" id="addPrimaryPhoneBtn">+ Add phone</button>
                            </div>
                            <div id="primaryPhonesList" class="identity-list"></div>
                            <p class="form-hint">Used to match Phone, SMS, WhatsApp, and Viber.</p>
                        </div>
                        <div class="form-group">
                            <div class="identity-label">
                                <label>Email</label>
                                <button type="button" class="link-btn" id="addPrimaryEmailBtn">+ Add email</button>
                            </div>
                            <div id="primaryEmailsList" class="identity-list"></div>
                            <p class="form-hint">Used to match Inbox and email threads.</p>
                        </div>
                    </section>

                    <section class="lead-form-panel" data-lead-panel="alternate" role="tabpanel" aria-labelledby="leadTabAlternate" hidden>
                        <div class="form-row form-row-3">
                            <div class="form-group">
                                <label for="leadAltTitle">Mr/Ms.</label>
                                <select id="leadAltTitle">
                                    <option value="">Select</option>
                                    @foreach ($leadFormOptions['titles'] as $title)
                                        <option value="{{ $title }}">{{ $title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="leadAltFirstName">First name</label>
                                <input type="text" id="leadAltFirstName" maxlength="255" placeholder="First name">
                            </div>
                            <div class="form-group">
                                <label for="leadAltLastName">Last name</label>
                                <input type="text" id="leadAltLastName" maxlength="255" placeholder="Last name">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="leadAltAddress">Address</label>
                            <input type="text" id="leadAltAddress" maxlength="500" placeholder="Street address">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="leadAltCity">City</label>
                                <input type="text" id="leadAltCity" maxlength="255" placeholder="City">
                            </div>
                            <div class="form-group">
                                <label for="leadAltPostal">Postal/Zip</label>
                                <input type="text" id="leadAltPostal" maxlength="20" placeholder="Postal or ZIP">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="identity-label">
                                <label>Phone</label>
                                <button type="button" class="link-btn" id="addAltPhoneBtn">+ Add phone</button>
                            </div>
                            <div id="altPhonesList" class="identity-list"></div>
                        </div>
                        <div class="form-group">
                            <div class="identity-label">
                                <label>Email</label>
                                <button type="button" class="link-btn" id="addAltEmailBtn">+ Add email</button>
                            </div>
                            <div id="altEmailsList" class="identity-list"></div>
                        </div>
                    </section>

                    <section class="lead-form-panel" data-lead-panel="source" role="tabpanel" aria-labelledby="leadTabSource" hidden>
                        <div class="form-group">
                            <label for="leadSource">Source</label>
                            <select id="leadSource">
                                <option value="">Select one</option>
                                @foreach ($leadFormOptions['sources'] as $source)
                                    <option value="{{ $source }}">{{ $source }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="leadStatus">Status</label>
                                <select id="leadStatus"></select>
                            </div>
                            <div class="form-group">
                                <label for="leadAssignedTo">Assigned</label>
                                <select id="leadAssignedTo">
                                    <option value="">Unassigned</option>
                                </select>
                            </div>
                        </div>
                        <h4>Customer type</h4>
                        <div class="lead-radio-row">
                            @foreach ($leadFormOptions['customer_types'] as $value => $label)
                                <label class="lead-radio">
                                    <input type="radio" name="leadCustomerType" value="{{ $value }}">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="form-group lead-conditional" id="leadResidentialWrap" hidden>
                            <label for="leadResidentialType">Residential type</label>
                            <select id="leadResidentialType">
                                <option value="">Select type</option>
                                @foreach ($leadFormOptions['residential_types'] as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="lead-conditional" id="leadBusinessWrap" hidden>
                            <div class="form-group">
                                <label for="leadBusinessIndustry">Business industry</label>
                                <select id="leadBusinessIndustry">
                                    <option value="">Select industry</option>
                                    @foreach ($leadFormOptions['business_industries'] as $industry)
                                        <option value="{{ $industry }}">{{ $industry }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group" id="leadBusinessIndustryOtherWrap" hidden>
                                <label for="leadBusinessIndustryOther">Other industry</label>
                                <input type="text" id="leadBusinessIndustryOther" maxlength="255" placeholder="Enter industry">
                            </div>
                        </div>
                        <h4>Reason for storing</h4>
                        <div class="form-group">
                            <label for="leadStorageReason">Reason</label>
                            <select id="leadStorageReason">
                                <option value="">Select one</option>
                                @foreach ($leadFormOptions['storage_reasons'] as $reason)
                                    <option value="{{ $reason }}">{{ $reason }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" id="leadStorageReasonOtherWrap" hidden>
                            <label for="leadStorageReasonOther">Other reason</label>
                            <input type="text" id="leadStorageReasonOther" maxlength="255" placeholder="Enter reason">
                        </div>
                        <div class="lead-storeganise-block" id="leadStoreganiseBlock" hidden>
                            <h4>Storeganise</h4>
                            <p class="form-hint" style="margin-top:0">Push this lead to Storeganise as a customer user at the selected facility. A primary email is required.</p>
                            <div class="form-group">
                                <label for="leadStoreganiseSite">Facility</label>
                                <select id="leadStoreganiseSite">
                                    <option value="">Select a facility…</option>
                                </select>
                            </div>
                            <div class="lead-storeganise-actions">
                                <button type="button" class="btn btn-secondary btn-sm" id="syncLeadStoreganiseBtn" hidden>Push to Storeganise</button>
                            </div>
                        </div>
                    </section>

                    <section class="lead-form-panel" data-lead-panel="matching" role="tabpanel" aria-labelledby="leadTabMatching" hidden>
                        <p class="form-hint" style="margin-top:0">Primary and alternate phone numbers and emails are used to match conversations across Phone, Inbox, Viber, WhatsApp, Facebook, and SMS.</p>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="leadFacebook">Facebook name</label>
                                <input type="text" id="leadFacebook" maxlength="255" placeholder="Matches Messenger threads">
                            </div>
                            <div class="form-group">
                                <label for="leadInstagram">Instagram username</label>
                                <input type="text" id="leadInstagram" maxlength="255" placeholder="Matches Instagram DMs">
                            </div>
                        </div>
                        <h4>Shared inbox emails</h4>
                        <p class="form-hint">Attach a thread from a shared mailbox. It stays on this lead even if the sender address is different.</p>
                        <div id="leadInboxAttachedList" class="lead-inbox-list"></div>
                        <div class="form-group lead-inbox-search">
                            <label for="leadInboxSearch">Find a thread</label>
                            <input type="search" id="leadInboxSearch" maxlength="200" placeholder="Search subject or sender" autocomplete="off">
                        </div>
                        <div id="leadInboxResults" class="lead-inbox-list"></div>
                    </section>
                    <section class="lead-form-panel" data-lead-panel="notes" role="tabpanel" aria-labelledby="leadTabNotes" hidden>
                    <div id="leadExtras" hidden>
                        <div class="form-group">
                            <label>Labels</label>
                            <div id="leadLabelsList" class="lead-label-list"></div>
                            <div class="lead-label-add">
                                <select id="leadLabelSelect" aria-label="Add a label">
                                    <option value="">Select a label…</option>
                                </select>
                                <span class="leads-spinner" id="leadLabelBusy" hidden></span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <div id="leadNotesList" class="lead-notes-list"></div>
                            <textarea id="leadNoteInput" rows="3" maxlength="5000" placeholder="Add a note for the next agent…"></textarea>
                            <button type="button" class="btn btn-secondary btn-sm" id="addLeadNoteBtn" style="margin-top:0.4rem">Add note</button>
                        </div>
                    </div>
                    <p id="leadExtrasHint" class="chp-empty">Save this lead to add notes and labels.</p>
                    </section>
                    <p class="form-error" id="leadFormError" hidden></p>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" id="deleteLeadBtn" hidden>Delete</button>
                        <span style="flex:1"></span>
                        <button type="button" class="btn btn-secondary" id="cancelLeadBtn">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveLeadBtn">Save lead</button>
                    </div>
                </form>
                <div class="leads-history" id="leadHistoryPane">
                    <button type="button" class="lead-activity-trigger" id="leadActivityTrigger" disabled>
                        <div class="lead-activity-trigger-head">
                            <h4>Activity</h4>
                            <span id="leadActivityCount">View all updates</span>
                        </div>
                        <div id="leadActivityPreview"><p class="chp-empty">Save this lead to start an activity history.</p></div>
                    </button>
                    <h4 class="leads-history-sub">Contact history</h4>
                    <p class="chp-empty" id="leadHistoryEmpty">Save this lead to load Phone, Inbox, Viber, WhatsApp, Facebook, and SMS history.</p>
                    <div id="leadHistoryBody" hidden></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="leadMessageModal">
        <div class="modal-content leads-message-modal">
            <div class="modal-header">
                <h3 id="leadMessageModalTitle">Send follow-up</h3>
                <button type="button" class="modal-close-btn" id="closeLeadMessageModal">&times;</button>
            </div>
            <div class="leads-message-body">
                <p class="leads-rules-help" id="leadMessageHelp">Pick a channel this lead can use, then a saved template. Mail can send to the lead’s email even without an existing thread.</p>
                <p class="form-error" id="leadMessageError" hidden></p>
                <div class="form-group">
                    <label>Channel</label>
                    <div class="lead-message-channels" id="leadMessageChannels"></div>
                </div>
                <div class="form-group" id="leadMessageToWrap" hidden>
                    <label>To</label>
                    <div class="lead-message-to" id="leadMessageTo"></div>
                    <p class="leads-rules-help" id="leadMessageToHelp">All of this lead’s emails are selected. Uncheck any you don’t want to send to.</p>
                </div>
                <div class="form-group" id="leadMessageMailboxWrap" hidden>
                    <label for="leadMessageMailbox">Send from</label>
                    <select id="leadMessageMailbox">
                        <option value="">Choose a mailbox…</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="leadMessageTemplate">Template</label>
                    <select id="leadMessageTemplate">
                        <option value="">Custom message</option>
                    </select>
                </div>
                <div class="form-group" id="leadMessageSubjectWrap" hidden>
                    <label for="leadMessageSubject">Subject</label>
                    <input type="text" id="leadMessageSubject" maxlength="255">
                </div>
                <div class="form-group" id="leadMessagePlainWrap">
                    <label for="leadMessageBody">Message</label>
                    <textarea id="leadMessageBody" rows="6" placeholder="Hi @{{first_name}}, …"></textarea>
                </div>
                <div class="form-group" id="leadMessageHtmlWrap" hidden>
                    <label>Message</label>
                    <div class="leads-html-editor" id="leadMessageHtmlEditor" data-html-editor="follow-up">
                        <div class="leads-html-toolbar">
                            <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
                            <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
                            <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
                            <button type="button" data-cmd="insertUnorderedList" title="Bullet list">• List</button>
                            <button type="button" data-cmd="createLink" title="Link">Link</button>
                            <button type="button" data-cmd="removeFormat" title="Clear formatting">Clear</button>
                            <span class="leads-html-toolbar-spacer"></span>
                            <button type="button" class="is-active" data-html-mode="visual">Visual</button>
                            <button type="button" data-html-mode="source">HTML</button>
                        </div>
                        <div id="leadMessageHtmlVisual" class="leads-html-visual" contenteditable="true" data-placeholder="Write your email… Use Visual for formatting or HTML to paste a template." role="textbox" aria-multiline="true"></div>
                        <textarea id="leadMessageHtmlSource" class="leads-html-source" rows="8" hidden placeholder="<p>Hi @{{first_name}},</p><p>…</p>"></textarea>
                    </div>
                </div>
                <p class="leads-rules-help leads-message-tokens">Tokens: @{{first_name}}, @{{last_name}}, @{{name}}, @{{company}}. Mail supports HTML.</p>
            </div>
            <div class="modal-actions leads-rules-actions">
                <button type="button" class="btn btn-secondary" id="cancelLeadMessageBtn">Cancel</button>
                <button type="button" class="btn btn-primary" id="sendLeadMessageBtn">Send</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="leadActivityModal">
        <div class="modal-content lead-activity-modal">
            <div class="modal-header">
                <h3 id="leadActivityModalTitle">Lead activity</h3>
                <button type="button" class="modal-close-btn" id="closeLeadActivityModal">&times;</button>
            </div>
            <div class="lead-activity-full" id="leadActivityFull"></div>
            <div class="leads-pagination lead-activity-pagination">
                <span id="leadActivityPageInfo">Showing 0 of 0</span>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" id="leadActivityPrev" disabled>Previous</button>
                    <button type="button" class="btn btn-secondary btn-sm" id="leadActivityNext" disabled>Next</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="leadStatusesModal">
        <div class="modal-content leads-rules-modal">
            <div class="modal-header">
                <h3>Lead statuses</h3>
                <button type="button" class="modal-close-btn" id="closeLeadStatusesModal">&times;</button>
            </div>
            <div class="leads-rules-body">
                <p class="leads-rules-help">Add, rename, or delete statuses. Leads using a deleted status move to the default status. Snoozed cannot be deleted because reopen rules use it.</p>
                <div id="leadCompanyStatusList" class="leads-rule-list"></div>
                <form id="leadCompanyStatusForm" class="leads-label-create">
                    <input type="text" id="leadCompanyStatusName" maxlength="50" placeholder="New status name" required>
                    <button type="submit" class="btn btn-primary" id="saveLeadCompanyStatusBtn">Add status</button>
                </form>
            </div>
            <div class="modal-actions leads-rules-actions">
                <button type="button" class="btn btn-secondary" id="closeLeadStatusesBtn">Close</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="leadLabelsModal">
        <div class="modal-content leads-rules-modal">
            <div class="modal-header">
                <h3>Lead labels</h3>
                <button type="button" class="modal-close-btn" id="closeLeadLabelsModal">&times;</button>
            </div>
            <div class="leads-rules-body">
                <p class="leads-rules-help">Add, rename, recolor, or delete labels. Use them on leads, filters, and rules.</p>
                <div id="leadCompanyLabelList" class="leads-rule-list"></div>
                <form id="leadCompanyLabelForm" class="leads-label-create">
                    <input type="text" id="leadCompanyLabelName" maxlength="50" placeholder="New label name" required>
                    <input type="color" id="leadCompanyLabelColor" value="#4338ca" title="Label color" aria-label="Label color">
                    <button type="submit" class="btn btn-primary" id="saveLeadCompanyLabelBtn">Add label</button>
                </form>
            </div>
            <div class="modal-actions leads-rules-actions">
                <button type="button" class="btn btn-secondary" id="closeLeadLabelsBtn">Close</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="leadRulesModal">
        <div class="modal-content leads-rules-modal">
            <div class="modal-header">
                <h3 id="leadRulesModalTitle">Lead rules</h3>
                <button type="button" class="modal-close-btn" id="closeLeadRulesModal">&times;</button>
            </div>
            <div class="leads-rules-body">
                <p class="leads-rules-help" id="leadRulesHelp">When something happens on Phone, Inbox, Viber, WhatsApp, Facebook, or SMS, run actions on the matching lead.</p>
                <div id="leadRuleListView">
                    <input type="search" id="leadRuleSearch" class="leads-search leads-rule-search" placeholder="Search rules by name…">
                    <div id="leadRuleList" class="leads-rule-list"></div>
                    <div class="leads-pagination leads-rule-pagination">
                        <span id="leadRulesPageInfo">Showing 0 of 0</span>
                        <div>
                            <button type="button" class="btn btn-secondary btn-sm" id="leadRulesPrev" disabled>Previous</button>
                            <button type="button" class="btn btn-secondary btn-sm" id="leadRulesNext" disabled>Next</button>
                        </div>
                    </div>
                </div>
                <div id="leadRuleBuilder" hidden>
                    <label class="leads-rule-name-label">Name
                        <input type="text" id="leadRuleName" placeholder="Enter a name for this rule" maxlength="120">
                    </label>
                    <div class="leads-rule-section">
                        <div class="leads-rule-section-title">When</div>
                        <div id="leadRuleTriggers" class="leads-rule-extra-list"></div>
                        <button type="button" class="link-btn" id="btnAddLeadRuleTrigger">+ Add trigger</button>
                    </div>
                    <div class="leads-rule-section">
                        <div class="leads-rule-section-title">If</div>
                        <div class="leads-rule-card">
                            <div class="leads-rule-pill-row">
                                <span>Channel is</span>
                                <div class="leads-rule-channel-picker" id="leadRuleChannelPicker">
                                    <button type="button" class="leads-rule-channel-toggle" id="leadRuleChannelToggle">
                                        <span id="leadRuleChannelToggleLabel">All channels</span>
                                        <span>▾</span>
                                    </button>
                                    <div class="leads-rule-channel-menu" id="leadRuleChannelMenu" hidden></div>
                                </div>
                            </div>
                            <div class="leads-rule-pill-row">
                                <span>Shared inbox is</span>
                                <div class="leads-rule-channel-picker" id="leadRuleInboxPicker">
                                    <button type="button" class="leads-rule-channel-toggle" id="leadRuleInboxToggle">
                                        <span id="leadRuleInboxToggleLabel">All shared inboxes</span>
                                        <span>▾</span>
                                    </button>
                                    <div class="leads-rule-channel-menu" id="leadRuleInboxMenu" hidden></div>
                                </div>
                            </div>
                        </div>
                        <div id="leadRuleConditions" class="leads-rule-extra-list"></div>
                        <button type="button" class="link-btn" id="btnAddLeadRuleCondition">+ Add condition</button>
                    </div>
                    <div class="leads-rule-section">
                        <div class="leads-rule-section-title">Then</div>
                        <div id="leadRuleActions" class="leads-rule-extra-list"></div>
                        <button type="button" class="link-btn" id="btnAddLeadRuleAction">+ Add action</button>
                    </div>
                    <label class="leads-rule-stop">
                        <input type="checkbox" id="leadRuleStopProcessing">
                        <span>Stop processing other rules</span>
                    </label>
                </div>
            </div>
            <div class="modal-actions leads-rules-actions">
                <button type="button" class="btn btn-secondary" id="cancelLeadRuleBtn">Close</button>
                <button type="button" class="btn btn-primary" id="newLeadRuleBtn">New rule</button>
                <button type="button" class="btn btn-primary" id="saveLeadRuleBtn" hidden>Create rule</button>
            </div>
        </div>
    </div>
    </div>
    </div>
@endsection
