@extends('layouts.app')

@section('title', 'Leads')

@section('content')
    @php
        $leadFormOptions = $leadFormOptions ?? \App\Models\Lead::formOptions();
    @endphp
    <div class="ld-page">
    <div class="ld-top">
        <div class="ld-top-main">
            <h1 class="ld-title">Leads</h1>
            <p class="ld-subtitle">Phones, emails, and social names shared across Phone, Inbox, Viber, WhatsApp, Facebook, and SMS.</p>
        </div>
        <div class="leads-header-actions ld-top-actions">
            <button type="button" class="btn btn-secondary btn-sm" id="leadFollowUpDaysBtn">Follow-up days</button>
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
    </div>

    <div class="leads-tabs" role="tablist" id="leadStatusTabs">
        <button type="button" class="leads-tab active" data-status="all">All</button>
    </div>

    <div class="leads-followup-row">
        <div class="leads-followup-chips" id="leadFollowUpChips" role="tablist" aria-label="Follow-up days"></div>
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
                        <th>Phones</th>
                        <th>Emails</th>
                        <th>Labels</th>
                        <th>Assigned</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="leadsTableBody">
                    <tr><td colspan="8" class="empty-state">Loading leads…</td></tr>
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
                                <label>Phone *</label>
                                <button type="button" class="link-btn" id="addPrimaryPhoneBtn">+ Add phone</button>
                            </div>
                            <div id="primaryPhonesList" class="identity-list"></div>
                            <p class="form-hint">Used to match Phone, SMS, WhatsApp, and Viber.</p>
                        </div>
                        <div class="form-group">
                            <div class="identity-label">
                                <label>Email *</label>
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
                <p class="leads-rules-help leads-message-tokens">Tokens: @{{first_name}}, @{{last_name}}, @{{name}}, @{{follow_up_day}}, @{{company}}. Mail supports HTML.</p>
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

    <div class="modal-overlay" id="leadFollowUpDaysModal">
        <div class="modal-content leads-rules-modal">
            <div class="modal-header">
                <h3>Follow-up days</h3>
                <button type="button" class="modal-close-btn" id="closeLeadFollowUpDaysModal">&times;</button>
            </div>
            <div class="leads-rules-body">
                <p class="leads-rules-help">Each day is a follow-up bucket (4 → 4th Day FU), separate from Labels. Day 1 is the day after the lead was created. Open leads sit in the latest due bucket. Converted, lost, archived, Move in, and Not Interested leads are left out.</p>
                <div id="leadFollowUpDaysList" class="leads-rule-list"></div>
                <form id="leadFollowUpDaysForm" class="leads-label-create">
                    <label class="leads-followup-day-field">
                        <span>Day</span>
                        <input type="number" id="leadFollowUpDayInput" class="leads-followup-day-input" min="1" max="365" step="1" placeholder="7" required>
                    </label>
                    <button type="submit" class="btn btn-primary" id="addLeadFollowUpDayBtn">Add day</button>
                </form>
            </div>
            <div class="modal-actions leads-rules-actions">
                <button type="button" class="btn btn-secondary" id="closeLeadFollowUpDaysBtn">Close</button>
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
@endsection

@push('styles')
    @include('partials.leads-page-base-styles')
<style>
.lead-day-badge { display: inline-block; margin-left: 0.4rem; padding: 0.08rem 0.4rem; border-radius: 999px; background: #eef2ff; color: #3730a3; font-size: 0.68rem; font-weight: 700; vertical-align: middle; }
.lead-message-channels { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.lead-message-channel { border: 1px solid var(--border); background: var(--bg-card); border-radius: 8px; padding: 0.4rem 0.7rem; font-size: 0.8rem; font-weight: 600; cursor: pointer; color: var(--text-primary); }
.lead-message-channel.active { border-color: var(--accent); background: color-mix(in srgb, var(--accent) 10%, var(--bg-card)); color: var(--accent); }
.lead-message-channel:disabled { opacity: 0.45; cursor: not-allowed; }
.lead-message-to { display: flex; flex-wrap: wrap; gap: 0.4rem; }
.lead-message-to-item { display: flex; align-items: center; gap: 0.4rem; border: 1px solid var(--border); background: var(--bg-card); border-radius: 8px; padding: 0.35rem 0.65rem; font-size: 0.8rem; font-weight: 600; color: var(--text-primary); cursor: pointer; }
.lead-message-to-item:has(input:checked) { border-color: var(--accent); background: color-mix(in srgb, var(--accent) 10%, var(--bg-card)); color: var(--accent); }
.lead-message-to-item input { width: auto; margin: 0; }
#leadMessageToHelp { margin: 0.4rem 0 0; }
.leads-message-modal { background: var(--bg-card); border-radius: 8px; width: min(640px, 96vw); max-height: 92vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.14); }
.leads-message-body { padding: 0.65rem 0.85rem; overflow-y: auto; min-height: 0; }
.leads-message-body .form-group:last-of-type { margin-bottom: 0.5rem; }
.leads-message-tokens { margin-bottom: 0; }
.leads-message-modal .leads-rules-actions { justify-content: flex-end; }
.leads-html-editor { display: grid; gap: 0.45rem; border: 1px solid var(--border); border-radius: 10px; padding: 0.55rem; background: var(--bg-primary, #fafafa); }
.leads-html-toolbar { display: flex; flex-wrap: wrap; gap: 0.25rem; align-items: center; }
.leads-html-toolbar button { border: 1px solid var(--border); background: var(--bg-card); border-radius: 6px; padding: 0.25rem 0.45rem; font-size: 0.75rem; font-weight: 600; cursor: pointer; color: var(--text-primary); }
.leads-html-toolbar button:hover,
.leads-html-toolbar button.is-active { border-color: var(--accent); color: var(--accent); background: color-mix(in srgb, var(--accent) 10%, var(--bg-card)); }
.leads-html-toolbar-spacer { flex: 1; }
.leads-html-visual { min-height: 160px; max-height: 280px; overflow: auto; border: 1px solid var(--border); border-radius: 8px; padding: 0.65rem 0.75rem; background: var(--bg-card); font-size: 0.9rem; line-height: 1.5; }
.leads-html-visual:empty:before { content: attr(data-placeholder); color: var(--text-secondary); }
.leads-html-visual img { max-width: 100%; height: auto; }
.leads-html-visual a { color: var(--accent); text-decoration: underline; }
.leads-html-source { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.82rem; line-height: 1.55; white-space: pre-wrap; overflow-wrap: anywhere; min-height: 160px; max-height: 280px; width: 100%; border: 1px solid var(--border); border-radius: 8px; padding: 0.65rem 0.75rem; background: var(--bg-card); color: var(--text-primary); resize: vertical; }
.leads-busy-overlay {
    position: absolute;
    inset: 0;
    z-index: 6;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    background: color-mix(in srgb, var(--bg-card) 78%, transparent);
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-secondary);
}
.leads-busy-overlay[hidden], .leads-spinner[hidden] { display: none !important; }
.leads-spinner {
    width: 1.2rem;
    height: 1.2rem;
    border: 2px solid var(--border);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: leads-spin 0.7s linear infinite;
    display: inline-block;
    flex-shrink: 0;
}
@keyframes leads-spin { to { transform: rotate(360deg); } }
.btn.is-busy {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    pointer-events: none;
}
.btn.is-busy .leads-spinner {
    width: 0.9rem;
    height: 0.9rem;
    border-color: rgba(255,255,255,.35);
    border-top-color: #fff;
}
.btn-secondary.is-busy .leads-spinner {
    border-color: var(--border);
    border-top-color: var(--accent);
}
.icon-btn.is-busy .leads-spinner {
    width: 0.85rem;
    height: 0.85rem;
}
.lead-company { font-size: 0.6875rem; color: var(--text-secondary); }
.lead-source {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    margin-top: 0.28rem;
    max-width: 100%;
    padding: 0.12rem 0.45rem;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: var(--bg-primary);
    color: var(--text-secondary);
    font-size: 0.68rem;
    font-weight: 700;
    line-height: 1.2;
    text-decoration: none;
    white-space: nowrap;
}
.lead-source svg { width: 11px; height: 11px; flex-shrink: 0; }
.lead-source.has-thread {
    border-color: color-mix(in srgb, var(--accent) 35%, var(--border));
    background: color-mix(in srgb, var(--accent) 10%, var(--bg-card));
    color: var(--accent);
}
.lead-source.has-thread:hover { border-color: var(--accent); }
.lead-source.has-thread.whatsapp { border-color: #86efac; background: #ecfdf5; color: #047857; }
.lead-source.has-thread.viber { border-color: #c4b5fd; background: #f5f3ff; color: #5b21b6; }
.lead-source.has-thread.sms { border-color: #86efac; background: #f0fdf4; color: #166534; }
.lead-source.has-thread.facebook { border-color: #93c5fd; background: #eff6ff; color: #1d4ed8; }
.lead-source.has-thread.instagram { border-color: #f9a8d4; background: #fdf2f8; color: #be185d; }
.lead-source.has-thread.inbox { border-color: color-mix(in srgb, var(--accent) 35%, var(--border)); background: color-mix(in srgb, var(--accent) 10%, var(--bg-card)); color: var(--accent); }
.lead-source.has-thread.call,
.lead-source.has-thread.phone { border-color: #fcd34d; background: #fffbeb; color: #b45309; }
.lead-meta { font-size: 0.6875rem; color: var(--text-secondary); }
.lead-assign { max-width: 150px; font-size: 0.6875rem; padding: 0.25rem 0.4rem; border: 1px solid var(--border); border-radius: 5px; background: var(--bg-card); color: var(--text-primary); }
.lead-assign:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
.lead-label-list, .lead-notes-list { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 0.45rem; }
.lead-notes-list { flex-direction: column; flex-wrap: nowrap; }
.lead-label-add { display: flex; align-items: center; gap: 0.4rem; }
.lead-label-add select { flex: 1; min-width: 0; }
.lead-inbox-search { margin-top: 0.55rem; margin-bottom: 0; }
.lead-inbox-search input[type="search"] {
    width: 100%;
    box-sizing: border-box;
    padding: 0.5rem 0.7rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 0.875rem;
    font-family: inherit;
    background: var(--bg-card);
    color: var(--text-primary);
    appearance: none;
}
.lead-inbox-search input[type="search"]:focus {
    outline: none;
    border-color: var(--accent);
}
.lead-inbox-search input[type="search"]::-webkit-search-decoration,
.lead-inbox-search input[type="search"]::-webkit-search-cancel-button,
.lead-inbox-search input[type="search"]::-webkit-search-results-button,
.lead-inbox-search input[type="search"]::-webkit-search-results-decoration { display: none; }
.lead-inbox-list { display: grid; gap: 0.4rem; margin-top: 0.45rem; }
.lead-inbox-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.45rem 0.7rem;
    align-items: start;
    padding: 0.6rem 0.7rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--bg-primary);
}
.lead-inbox-row strong { display: block; font-size: 0.82rem; }
.lead-inbox-row .lead-meta { margin-top: 0.15rem; }
.lead-inbox-empty { font-size: 0.78rem; color: var(--text-muted); }
.lead-note-item { padding: 0.7rem 0.8rem; background: var(--bg-primary); border-radius: 8px; border-left: 3px solid var(--accent); }
.lead-note-text { font-size: 0.875rem; line-height: 1.45; white-space: pre-wrap; }
.lead-note-meta { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; margin-top: 0.45rem; padding-top: 0.4rem; border-top: 1px solid var(--border); font-size: 0.75rem; color: var(--text-muted); }
.lead-note-empty { font-size: 0.8rem; color: var(--text-secondary); }
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4); z-index: 1000; align-items: center; justify-content: center; padding: 0.75rem; }
.modal-overlay.open { display: flex; }
#leadActivityModal { z-index: 1100; }
.leads-modal { position: relative; background: var(--bg-card); border-radius: 8px; width: min(1040px, 96vw); max-height: 92vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.14); }
.modal-header { display: flex; justify-content: space-between; align-items: flex-start; padding: 0.65rem 0.85rem; border-bottom: 1px solid var(--border); gap: 0.65rem; }
.lead-modal-header { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(260px, 0.9fr); align-items: start; gap: 0.35rem 0.65rem; }
.lead-modal-heading { grid-column: 1; grid-row: 1; min-width: 0; }
.lead-modal-header-actions { grid-column: 2; grid-row: 1; justify-self: end; align-self: start; display: flex; align-items: center; gap: 0.45rem; flex-shrink: 0; }
.lead-modal-quote-btn { white-space: nowrap; text-decoration: none; }
.lead-modal-quote-btn[hidden] { display: none !important; }
.lead-modal-close { justify-self: end; align-self: start; }
.lead-modal-header-meta { grid-column: 1 / -1; display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(260px, 0.9fr); gap: 0.35rem 0.65rem; margin-top: 0.15rem; }
.lead-modal-heading h3 { margin: 0; font-size: 0.875rem; font-weight: 700; }
.lead-modal-added { margin: 0.15rem 0 0; font-size: 0.6875rem; color: var(--text-secondary); font-weight: 500; }
.lead-modal-meta-block { display: flex; flex-direction: column; gap: 0.2rem; max-width: 100%; min-width: 0; }
.lead-modal-meta-kicker { font-size: 0.625rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-muted); font-weight: 600; white-space: nowrap; }
.lead-modal-labels { display: flex; flex-wrap: wrap; gap: 0.25rem; align-items: center; }
.lead-modal-labels .lead-label-chip { font-size: 0.625rem; padding: 0.12rem 0.4rem; }
.lead-modal-facility { font-size: 0.6875rem; font-weight: 600; color: var(--text-secondary); line-height: 1.35; word-break: break-word; }
.lead-modal-heading-labels { grid-column: 1; align-items: flex-start; }
.lead-modal-header-facility { grid-column: 2; align-items: flex-end; text-align: right; }
.lead-modal-channel-links { display: flex; flex-wrap: wrap; gap: 0.3rem; margin-top: 0.35rem; }
.lead-modal-channel-links .lead-source { margin-top: 0; }
.modal-close-btn { background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--text-muted); line-height: 1; width: 26px; height: 26px; border-radius: 5px; flex-shrink: 0; }
.modal-close-btn:hover { background: var(--bg-primary); color: var(--text-primary); }
.leads-modal-grid { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(260px, 0.9fr); min-height: 0; overflow: hidden; }
.leads-form { padding: 0.75rem 0.85rem; overflow-y: auto; max-height: calc(92vh - 52px); }
.leads-history { border-left: 1px solid var(--border); padding: 0.75rem 0.8rem; overflow-y: auto; background: var(--bg-primary); max-height: calc(92vh - 52px); }
.leads-history h4 { margin: 0; font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); font-weight: 600; }
.leads-history-sub { margin: 0.85rem 0 0.5rem !important; padding-top: 0.65rem; border-top: 1px solid var(--border); }
.lead-activity-trigger { display: block; width: 100%; text-align: left; border: 1px solid var(--border); background: var(--bg-card); border-radius: 10px; padding: 0.75rem 0.85rem; cursor: pointer; color: inherit; }
.lead-activity-trigger:hover:not(:disabled) { border-color: var(--accent); }
.lead-activity-trigger:disabled { cursor: default; opacity: 0.85; }
.lead-activity-trigger-head { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; margin-bottom: 0.55rem; }
.lead-activity-trigger-head span { font-size: 0.75rem; font-weight: 700; color: var(--accent); white-space: nowrap; }
.lead-activity-modal { background: var(--bg-card); border-radius: 8px; width: min(520px, 96vw); max-height: 88vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.14); }
.lead-activity-full { padding: 0.4rem 0.4rem 0.5rem; overflow-y: auto; min-height: 180px; }
.lead-activity-pagination { border-top: 1px solid var(--border); }
.lead-activity-item { display: block; width: 100%; text-align: left; padding: 0.7rem 0.85rem; border: 0; border-bottom: 1px solid var(--border); background: transparent; cursor: pointer; color: inherit; }
.lead-activity-item:hover { background: var(--bg-primary); }
.lead-activity-item.open { background: var(--bg-primary); }
.lead-activity-summary { font-size: 0.82rem; line-height: 1.4; color: var(--text-primary); }
.lead-activity-meta { margin-top: 0.15rem; font-size: 0.72rem; color: var(--text-muted); }
.lead-activity-details { margin-top: 0.45rem; padding-top: 0.4rem; border-top: 1px dashed var(--border); font-size: 0.75rem; line-height: 1.45; color: var(--text-secondary); }
.form-group { margin-bottom: 0.65rem; }
.form-group label { display: block; font-size: 0.6875rem; font-weight: 600; margin-bottom: 0.25rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.03em; }
.form-group input, .form-group textarea, .form-group select {
    width: 100%;
    padding: 0.4rem 0.55rem;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 0.75rem;
    font-family: inherit;
    background: var(--bg-card);
    color: var(--text-primary);
}
.lead-form-tabs {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0;
    margin: 0 0 0.65rem;
    position: sticky;
    top: 0;
    z-index: 3;
    background: var(--bg-card);
    padding: 0 0 0.5rem;
    border: 1px solid var(--border);
    border-radius: 6px;
    overflow: hidden;
}
.lead-form-tab {
    min-width: 0;
    border: none;
    border-right: 1px solid var(--border);
    background: var(--bg-primary);
    color: var(--text-secondary);
    border-radius: 0;
    padding: 0.45rem 0.35rem;
    font-size: 0.625rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    text-align: center;
    line-height: 1.25;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.lead-form-tab:last-child { border-right: none; }
.lead-form-tab.active { background: #fff; color: var(--accent); box-shadow: inset 0 -2px 0 var(--accent); }
.lead-form-panel { display: none; }
.lead-form-panel.active { display: block; }
.lead-form-panel > h4 { margin: 0 0 0.55rem; font-size: 0.625rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); }
.lead-form-panel > h4:not(:first-child) { margin-top: 0.75rem; padding-top: 0.6rem; border-top: 1px solid var(--border); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
.form-row-3 { grid-template-columns: 90px 1fr 1fr; }
.lead-radio-row { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 0.55rem; }
.lead-radio { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.35rem 0.55rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.75rem; cursor: pointer; background: var(--bg-primary); margin: 0.15rem 0; }
.lead-radio:has(input:checked) { border-color: var(--accent); background: color-mix(in srgb, var(--accent) 10%, var(--bg-card)); }
.lead-radio input { width: auto; margin: 0; }
.lead-conditional { margin-top: 0.1rem; }
.identity-label { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem; }
.identity-label label { margin: 0; text-transform: none; font-size: 0.75rem; color: var(--text-primary); }
.link-btn { background: none; border: none; color: var(--accent); font-weight: 600; font-size: 0.6875rem; cursor: pointer; text-decoration: none; margin: 0.25rem 0; }
.link-btn:hover { text-decoration: underline; }
.identity-row { display: grid; grid-template-columns: 1fr auto; gap: 0.35rem; margin-bottom: 0.3rem; }
.identity-row input { width: 100%; padding: 0.35rem 0.5rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.75rem; }
.form-hint { margin: 0.25rem 0 0; font-size: 0.6875rem; color: var(--text-muted); line-height: 1.35; }
.icon-btn { border: 1px solid var(--border); background: #fff; border-radius: 6px; width: 30px; height: 30px; cursor: pointer; color: #991b1b; margin: 0.15rem 0; }
.icon-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.modal-actions { display: flex; gap: 0.35rem; align-items: center; margin-top: 0.35rem; flex-wrap: wrap; }
.form-error { color: #b91c1c; font-size: 0.75rem; margin: 0 0 0.55rem; }
.lh-item, .lh-event { padding: 0.5rem 0; border-bottom: 1px solid var(--border); }
.lh-title { font-weight: 600; font-size: 0.85rem; margin: 0.2rem 0; }
.lh-preview { font-size: 0.78rem; color: var(--text-secondary); }
.lh-link { font-size: 0.78rem; font-weight: 600; color: var(--accent); text-decoration: none; }
.chp-empty { font-size: 0.84rem; color: var(--text-secondary); }
.leads-rules-modal { background: var(--bg-card); border-radius: 8px; width: min(680px, 96vw); max-height: 92vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 12px 32px rgba(15, 23, 42, 0.14); }
.leads-rules-body { padding: 0.65rem 0.85rem; overflow-y: auto; max-height: calc(92vh - 110px); }
.leads-rules-help { margin: 0 0 0.65rem; font-size: 0.75rem; color: var(--text-secondary); line-height: 1.4; }
.leads-rule-search { width: 100%; margin-bottom: 0.5rem; padding: 0.4rem 0.55rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.75rem; background: var(--bg-card); }
.leads-rule-pagination { padding: 0.45rem 0 0; margin-top: 0.35rem; border-top: 1px solid var(--border); }
.leads-rule-list { display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 0.2rem; }
.leads-rule-row { display: flex; align-items: flex-start; gap: 0.35rem; padding: 0.45rem 0.55rem; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-primary); }
.leads-rule-row-main { flex: 1; min-width: 0; }
.leads-rule-row-name { font-size: 0.75rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.leads-rule-row-meta { margin-top: 0.15rem; font-size: 0.6875rem; color: var(--text-muted); }
.leads-rule-row-actions { display: flex; flex-wrap: wrap; gap: 0.25rem; flex-shrink: 0; align-items: center; }
.leads-rule-name-label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.75rem; }
.leads-rule-name-label input { width: 100%; margin-top: 0.3rem; padding: 0.5rem 0.7rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; }
.leads-rule-section { margin: 1rem 0 0.75rem; }
.leads-rule-section-title { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); margin-bottom: 0.45rem; }
.leads-rule-card { border: 1px solid var(--border); border-radius: 8px; padding: 0.65rem 0.75rem; background: var(--bg-primary); display: flex; flex-direction: column; gap: 0.55rem; }
.leads-rule-pill-row { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; font-size: 0.85rem; }
.leads-rule-channel-picker { position: relative; min-width: 200px; }
.leads-rule-channel-toggle { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; width: 100%; padding: 0.4rem 0.65rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-card); cursor: pointer; font-size: 0.85rem; }
.leads-rule-channel-menu { position: absolute; z-index: 5; top: calc(100% + 4px); left: 0; right: 0; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 0.35rem; box-shadow: 0 8px 24px rgba(0,0,0,.12); }
.leads-rule-channel-option { display: flex; align-items: center; gap: 0.45rem; padding: 0.35rem 0.45rem; border-radius: 6px; font-size: 0.84rem; cursor: pointer; }
.leads-rule-channel-option:hover { background: var(--bg-primary); }
.leads-rule-extra-list { display: flex; flex-direction: column; gap: 0.45rem; margin: 0.5rem 0; }
.leads-rule-extra-card { display: grid; grid-template-columns: 1fr 1fr minmax(0, 1.2fr) auto; gap: 0.4rem; align-items: start; padding: 0.5rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-primary); }
.leads-rule-extra-card.is-trigger { grid-template-columns: minmax(0, 1fr) auto; }
.leads-rule-trigger-fields { display: grid; gap: 0.4rem; }
.leads-rule-trigger-fields.has-label { grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); }
.leads-rule-extra-card.is-action { grid-template-columns: 1fr 1fr auto; }
.leads-rule-extra-card.is-action.is-create-lead { grid-template-columns: minmax(0, 1fr) auto; }
.leads-rule-extra-card.is-action.is-create-lead [data-rule-action-value] { display: none; }
.leads-rule-extra-card.is-action.is-delayed-status { grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.7fr) minmax(0, 1fr) auto; }
.leads-rule-delayed-help { grid-column: 1 / -1; margin: 0; font-size: 0.72rem; color: var(--text-muted); }
.leads-rule-extra-card.is-action.is-rr-selected { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto; }
.leads-rule-rr-users { grid-column: 1 / -1; display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 0.3rem; }
.leads-rule-rr-users label { display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; padding: 0.3rem 0.4rem; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-card); cursor: pointer; }
.leads-rule-rr-users input { width: auto; margin: 0; }
.leads-rule-rr-help { grid-column: 1 / -1; margin: 0; font-size: 0.72rem; color: var(--text-muted); }
.leads-rule-create-keywords { grid-column: 1 / -1; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.4rem; }
.leads-rule-create-keywords label { display: flex; flex-direction: column; gap: 0.2rem; font-size: 0.72rem; color: var(--text-muted); }
.leads-rule-create-help { grid-column: 1 / -1; margin: 0; font-size: 0.72rem; color: var(--text-muted); }
.leads-rule-extra-card select, .leads-rule-extra-card input { width: 100%; padding: 0.4rem 0.5rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.82rem; background: var(--bg-card); }
.leads-rule-trigger-help { margin: 0.3rem 0 0; font-size: 0.75rem; color: var(--text-muted); }
.leads-rule-remove { border: none; background: none; color: #b91c1c; font-size: 1.1rem; cursor: pointer; padding: 0.2rem 0.35rem; }
.leads-rule-stop { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; margin: 0.75rem 0 0; }
.leads-rules-actions { padding: 0.55rem 0.85rem 0.65rem; margin: 0; border-top: 1px solid var(--border); justify-content: flex-end; }
.leads-status-name-input { width: 100%; padding: 0.4rem 0.55rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; background: var(--bg-card); color: var(--text-primary); }
.leads-label-create { display: flex; gap: 0.45rem; align-items: center; margin-top: 0.85rem; }
.leads-label-create input[type="text"] { flex: 1; min-width: 0; padding: 0.5rem 0.7rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; background: var(--bg-card); color: var(--text-primary); }
.leads-label-create input[type="color"] { width: 2.4rem; height: 2.2rem; padding: 0; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-card); cursor: pointer; }
.leads-followup-day-field { display: flex; align-items: center; gap: 0.45rem; flex: 1; min-width: 0; height: 2.2rem; padding: 0 0.15rem 0 0.75rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-card); color: var(--text-secondary); font-size: 0.875rem; font-weight: 600; box-sizing: border-box; }
.leads-followup-day-field:focus-within { border-color: var(--accent); }
.leads-followup-day-input { flex: 1; min-width: 0; width: 100%; height: 100%; border: 0; background: transparent; color: var(--text-primary); font-size: 0.875rem; font-weight: 500; padding: 0 0.7rem 0 0; appearance: textfield; -moz-appearance: textfield; }
.leads-followup-day-input:focus { outline: none; }
.leads-followup-day-input::-webkit-outer-spin-button,
.leads-followup-day-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.leads-label-row-color { width: 2rem; height: 1.85rem; padding: 0; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-card); cursor: pointer; }
        @media (max-width: 700px) {
            .leads-rule-extra-card, .leads-rule-extra-card.is-action { grid-template-columns: 1fr; }
            .leads-rule-create-keywords { grid-template-columns: 1fr; }
        }
    .lead-storeganise-block {
        margin-top: 1.25rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border);
    }
    .lead-storeganise-block h4 {
        margin: 0 0 0.5rem;
        font-size: 0.95rem;
    }
    #leadStoreganiseStatus.is-success { color: #059669; }
    #leadStoreganiseStatus.is-error { color: #dc2626; }
    .lead-storeganise-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; margin-top: 0.5rem; }
@media (max-width: 860px) {
    .lead-modal-header { grid-template-columns: 1fr auto; }
    .lead-modal-header-meta { grid-template-columns: 1fr auto; }
    .lead-modal-header-facility { grid-column: 2; }
    .leads-modal-grid { grid-template-columns: 1fr; }
    .leads-history { border-left: 0; border-top: 1px solid var(--border); }
    .form-row, .form-row-3, .identity-row { grid-template-columns: 1fr; }
    .ld-top { flex-direction: column; align-items: stretch; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    const api = '/api/leads';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const LEAD_OPTIONS = @json($leadFormOptions);
    const LEAD_FOLLOW_UP = @json($leadFollowUpConfig ?? []);
    const STOREGANISE_CONNECTED = @json(!empty($storeganiseConnected));
    const CAN_VIEW_QUOTATION_BUILDER = @json(!empty($canViewQuotationBuilder));
    const LEAD_QUOTE_URL_BASE = @json(url('/quotation-builder/leads'));
    const state = { page: 1, status: 'all', search: '', source: '', assignedTo: '', labelIds: [], followUp: '', followUpDays: Array.isArray(LEAD_FOLLOW_UP.days) ? LEAD_FOLLOW_UP.days : [4, 10, 30, 90], followUpLabels: Array.isArray(LEAD_FOLLOW_UP.labels) ? LEAD_FOLLOW_UP.labels : [], followUpPlusMin: Number(LEAD_FOLLOW_UP.plus_min || 91), followUpCounts: {}, statusCounts: {}, editingId: null, editingRuleId: null, labels: [], notes: [], companyLabels: [], statuses: [], defaultStatus: 'new', assignees: [], inboxes: [], activities: [], activityPage: 1, activityLastPage: 1, activityTotal: 0, rules: [], rulesPage: 1, rulesLastPage: 1, rulesTotal: 0, rulesSearch: '', canManageRules: {{ !empty($canManageLeadRules) ? 'true' : 'false' }}, attachedInboxConversations: [], pendingInboxConversations: [], inboxSearchTimer: null, messageLeadId: '', messageChannels: [], messageChannel: '', leadPhones: [], leadName: '', savedLeadStoreganiseSiteId: null, storeganiseSites: [], storeganiseSitesLoaded: false, storeganiseAction: null };

    const body = document.getElementById('leadsTableBody');
    const modal = document.getElementById('leadModal');
    const activityModal = document.getElementById('leadActivityModal');
    const form = document.getElementById('leadForm');
    const errorEl = document.getElementById('leadFormError');

    function headers(json) {
        const h = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (csrf) h['X-CSRF-TOKEN'] = csrf;
        if (json) h['Content-Type'] = 'application/json';
        return h;
    }
    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function formatAt(iso) {
        if (!iso) return '—';
        try { return new Date(iso).toLocaleString(); } catch { return iso; }
    }
    function formatDate(iso) {
        if (!iso) return '';
        try { return new Date(iso).toLocaleDateString(); } catch { return iso; }
    }
    function setLeadModalAdded(lead) {
        const el = document.getElementById('leadModalAdded');
        if (!el) return;
        if (!lead?.created_at) {
            el.hidden = true;
            el.textContent = '';
            return;
        }
        const added = formatDate(lead.created_at);
        const days = Number.isFinite(Number(lead.follow_up_day))
            ? Math.max(0, Number(lead.follow_up_day))
            : null;
        let age = '';
        if (days === 0) age = 'today';
        else if (days === 1) age = '1 day';
        else if (days != null) age = days + ' days';
        el.textContent = age ? `Added ${added} · ${age}` : `Added ${added}`;
        el.hidden = false;
    }
    function activeLeadTab() {
        return document.querySelector('.lead-form-tab.active')?.dataset.leadTab || 'primary';
    }
    function leadQuoteUrl(leadId) {
        return `${LEAD_QUOTE_URL_BASE}/${encodeURIComponent(leadId)}/quote`;
    }
    function resetLeadStoreganiseFacilitySelect() {
        const select = document.getElementById('leadStoreganiseSite');
        if (!select) return;
        select.innerHTML = '<option value="">Select a facility…</option>';
        select.value = '';
    }
    function setSavedLeadStoreganiseSiteId(siteId) {
        const normalized = String(siteId ?? '').trim();
        state.savedLeadStoreganiseSiteId = normalized !== '' ? normalized : null;
    }
    function leadHasStoreganiseFacility() {
        if (!STOREGANISE_CONNECTED) return false;
        if (!(state.editingId || document.getElementById('leadId')?.value)) return false;

        return String(state.savedLeadStoreganiseSiteId || '').trim() !== '';
    }
    function updateLeadModalQuoteButton() {
        const btn = document.getElementById('leadModalQuoteBtn');
        if (!btn || !CAN_VIEW_QUOTATION_BUILDER) return;

        const leadId = state.editingId || document.getElementById('leadId')?.value || '';
        const onSourceTab = activeLeadTab() === 'source';
        const show = Boolean(leadId) && onSourceTab && leadHasStoreganiseFacility();

        btn.hidden = !show;
        btn.href = show ? leadQuoteUrl(leadId) : '#';
    }
    function renderLeadModalHeaderMeta(lead = null) {
        const metaRow = document.getElementById('leadModalHeaderMeta');
        const labelsWrap = document.getElementById('leadModalLabelsWrap');
        const labelsEl = document.getElementById('leadModalLabels');
        const facilityWrap = document.getElementById('leadModalStoreganiseWrap');
        const facilityEl = document.getElementById('leadModalStoreganise');
        if (!metaRow || !labelsWrap || !labelsEl || !facilityWrap || !facilityEl) return;

        const labels = mergedLeadLabels();
        const hasLabels = labels.length > 0;
        labelsWrap.hidden = !hasLabels;
        labelsEl.innerHTML = hasLabels ? labelChips(labels) : '';

        let facilityLabel = '';
        const siteId = String(state.savedLeadStoreganiseSiteId || document.getElementById('leadStoreganiseSite')?.value || '').trim();
        if (STOREGANISE_CONNECTED && (state.editingId || lead?.id) && siteId) {
            const site = state.storeganiseSites.find(s => String(s.id) === String(siteId));
            facilityLabel = site ? storeganiseSiteLabel(site) : siteId;
        }
        const hasFacility = facilityLabel !== '';
        facilityWrap.hidden = !hasFacility;
        facilityEl.textContent = facilityLabel;

        metaRow.hidden = !hasLabels && !hasFacility;
        updateLeadModalQuoteButton();
    }
    function statusName(slug) {
        const key = String(slug || '');
        const row = (state.statuses || []).find(s => s.slug === key);
        return row?.name || key || 'new';
    }
    function statusBadge(lead) {
        const status = lead.status || state.defaultStatus || 'new';
        const label = status === 'snoozed' && lead.reopen_at
            ? statusName(status) + ' until ' + formatDate(lead.reopen_at)
            : statusName(status);
        return `<span class="lead-badge ${esc(status)}">${esc(label)}</span>`;
    }
    function followUpLabelName(day) {
        const row = (state.followUpLabels || []).find(item => Number(item.day) === Number(day));
        return row?.name || `${day}${Number(day) === 1 ? 'st' : Number(day) === 2 ? 'nd' : Number(day) === 3 ? 'rd' : 'th'} Day FU`;
    }
    function sourceVisual(lead) {
        const source = String(lead.source || '').trim();
        const hasThread = !!lead.has_connected_thread;
        if (!hasThread) {
            return source ? `<div class="lead-company">${esc(source)}</div>` : '';
        }
        const channel = String(lead.connected_thread_channel || 'inbox');
        const label = source || lead.connected_thread_label || 'Connected thread';
        const title = lead.connected_thread_label
            ? `Open ${lead.connected_thread_label} thread`
            : 'Open connected thread';
        const icon = channelIcon(channel);
        const cls = `lead-source has-thread ${esc(channel)}`;
        if (lead.connected_thread_url) {
            return `<a class="${cls}" href="${esc(lead.connected_thread_url)}" title="${esc(title)}">${icon}<span>${esc(label)}</span></a>`;
        }
        return `<span class="${cls}" title="${esc(title)}">${icon}<span>${esc(label)}</span></span>`;
    }
    const LEAD_MODAL_CHANNEL_NAV = [
        { key: 'phone', label: 'Phone', channels: ['call'] },
        { key: 'inbox', label: 'Inbox', channels: ['inbox'] },
        { key: 'viber', label: 'Viber', channels: ['viber'] },
        { key: 'facebook', label: 'Facebook', channels: ['facebook', 'instagram'] },
        { key: 'sms', label: 'SMS', channels: ['sms'] },
    ];
    function channelIcon(channel) {
        if (channel === 'call' || channel === 'phone') {
            return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>`;
        }
        if (channel === 'whatsapp' || channel === 'viber' || channel === 'sms') {
            return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>`;
        }
        if (channel === 'facebook' || channel === 'instagram') {
            return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>`;
        }
        return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>`;
    }
    function pickLeadChannelLink(threads, events, channelIds) {
        for (const thread of threads || []) {
            const channel = String(thread.channel || '');
            if (channelIds.includes(channel) && thread.deep_link) {
                return { url: thread.deep_link, title: thread.title || thread.label || '' };
            }
        }
        for (const event of events || []) {
            const channel = String(event.channel || '');
            if (channelIds.includes(channel) && event.deep_link) {
                return { url: event.deep_link, title: event.label || '' };
            }
        }
        return null;
    }
    function leadPhoneValues(lead) {
        const values = [];
        const push = (value) => {
            const trimmed = String(value || '').trim();
            if (trimmed) values.push(trimmed);
        };
        for (const list of [lead?.primary_phones, lead?.phones, lead?.alt_phones]) {
            if (!Array.isArray(list)) continue;
            for (const item of list) push(typeof item === 'string' ? item : item?.value);
        }
        push(lead?.phone);
        push(lead?.alt_phone);
        return [...new Set(values)];
    }
    function leadPhoneChannelUrl(channelKey, phone, name) {
        const trimmed = String(phone || '').trim();
        if (!trimmed) return null;
        if (channelKey === 'phone') {
            return '/twilio/call?phone=' + encodeURIComponent(trimmed);
        }
        if (channelKey === 'sms') {
            const params = new URLSearchParams({ phone: trimmed });
            const leadName = String(name || '').trim();
            if (leadName) params.set('name', leadName);
            return '/sms?' + params.toString();
        }
        return null;
    }
    function clearLeadModalChannelLinks() {
        const el = document.getElementById('leadModalChannelLinks');
        if (!el) return;
        el.hidden = true;
        el.innerHTML = '';
    }
    function renderLeadModalChannelLinks(threads, events, leadPhones) {
        const el = document.getElementById('leadModalChannelLinks');
        if (!el) return;
        const primaryPhone = (leadPhones || [])[0] || '';
        const links = LEAD_MODAL_CHANNEL_NAV.map(def => {
            const picked = pickLeadChannelLink(threads, events, def.channels);
            let url = picked?.url || '';
            let title = picked?.title || '';
            if (!url && primaryPhone && (def.key === 'phone' || def.key === 'sms')) {
                url = leadPhoneChannelUrl(def.key, primaryPhone, state.leadName) || '';
                title = primaryPhone;
            }
            if (!url) return '';
            const iconChannel = def.key === 'phone' ? 'call' : def.key;
            const linkTitle = title ? `Open ${def.label}: ${title}` : `Open ${def.label}`;
            return `<a class="lead-source has-thread ${esc(def.key)}" href="${esc(url)}" title="${esc(linkTitle)}">${channelIcon(iconChannel)}<span>${esc(def.label)}</span></a>`;
        }).filter(Boolean).join('');
        if (!links) {
            el.hidden = true;
            el.innerHTML = '';
            return;
        }
        el.innerHTML = links;
        el.hidden = false;
    }
    function chipText(hex) {
        const c = String(hex || '#4338ca').replace('#', '');
        if (c.length !== 6) return '#fff';
        const r = parseInt(c.slice(0, 2), 16), g = parseInt(c.slice(2, 4), 16), b = parseInt(c.slice(4, 6), 16);
        return (r * 299 + g * 587 + b * 114) / 1000 > 160 ? '#111' : '#fff';
    }
    function labelChips(labels) {
        return (labels || []).map(label => {
            const color = label.color || '#4338ca';
            const fromFront = label.source === 'front';
            const cls = fromFront ? 'inbox-pill lead-label-chip--front' : 'inbox-pill';
            const title = fromFront ? ' title="Imported from Front.com — not yet saved on this lead"' : '';
            return `<span class="${cls}" style="background:${color}22;color:${color}"${title}>${esc(label.name)}</span>`;
        }).join(' ') || '<span class="lead-meta">—</span>';
    }
    function mergedLeadLabels() {
        const merged = new Map();
        (state.labels || []).forEach(label => {
            const key = String(label?.name || '').trim().toLowerCase();
            if (key) merged.set(key, { ...label, source: 'lead' });
        });
        (state.attachedInboxConversations || []).forEach(conversation => {
            (conversation.lead_labels || []).forEach(label => {
                const key = String(label?.name || '').trim().toLowerCase();
                if (key && !merged.has(key)) merged.set(key, { ...label, source: 'front' });
            });
        });
        return Array.from(merged.values());
    }
    function spinnerHtml() {
        return '<span class="leads-spinner" aria-hidden="true"></span>';
    }
    function setBusy(btn, busy, label) {
        if (!btn) return;
        if (busy) {
            if (btn.dataset.idleHtml == null) btn.dataset.idleHtml = btn.innerHTML;
            btn.disabled = true;
            btn.classList.add('is-busy');
            btn.setAttribute('aria-busy', 'true');
            btn.innerHTML = spinnerHtml() + '<span>' + esc(label || 'Please wait…') + '</span>';
            return;
        }
        btn.disabled = false;
        btn.classList.remove('is-busy');
        btn.removeAttribute('aria-busy');
        if (btn.dataset.idleHtml != null) btn.innerHTML = btn.dataset.idleHtml;
    }
    function setOverlay(id, busy, text) {
        const el = document.getElementById(id);
        if (!el) return;
        el.hidden = !busy;
        const label = el.querySelector('#leadModalBusyText, [data-busy-text]');
        if (label && text) label.textContent = text;
    }
    function leadRowHtml(lead) {
        return `
            <tr data-id="${lead.id}">
                <td>
                    <div class="lead-name">${esc([lead.title, lead.name].filter(Boolean).join(' '))}</div>
                    ${lead.company_name ? `<div class="lead-company">${esc(lead.company_name)}</div>` : ''}
                    ${sourceVisual(lead)}
                </td>
                <td class="lead-meta">${esc((lead.phones || []).map(p => p.value).join(', ') || '—')}</td>
                <td class="lead-meta">${esc((lead.emails || []).map(e => e.value).join(', ') || '—')}</td>
                <td>${labelChips(lead.labels)}</td>
                <td>
                    <select class="lead-assign" data-id="${lead.id}" aria-label="Assign lead">
                        ${assigneeOptions(lead.assigned_to, lead.assigned_user)}
                    </select>
                </td>
                <td>${statusBadge(lead)}</td>
                <td class="lead-meta">${esc(formatAt(lead.updated_at))}</td>
                <td>
                    <button type="button" class="btn btn-secondary btn-sm" data-message="${lead.id}">Message</button>
                </td>
            </tr>
        `;
    }
    function upsertLeadRow(lead) {
        if (!lead?.id) return;
        const existing = body.querySelector('tr[data-id="' + lead.id + '"]');
        if (existing) {
            existing.outerHTML = leadRowHtml(lead);
        } else if (body.querySelector('.empty-state')) {
            body.innerHTML = leadRowHtml(lead);
        } else {
            body.insertAdjacentHTML('afterbegin', leadRowHtml(lead));
        }
    }
    function removeLeadRow(id) {
        const existing = body.querySelector('tr[data-id="' + id + '"]');
        if (existing) existing.remove();
        if (!body.querySelector('tr[data-id]')) {
            body.innerHTML = `<tr><td colspan="8" class="empty-state">${state.search || state.labelIds.length || state.source || state.assignedTo || state.followUp ? 'No leads match this search.' : 'No leads yet. Create one to start matching conversations across channels.'}</td></tr>`;
        }
    }
    function assigneeOptions(selectedId, extraUser) {
        const users = [...state.assignees];
        if (extraUser && extraUser.id && !users.some(u => String(u.id) === String(extraUser.id))) {
            users.push(extraUser);
        }
        const selected = selectedId == null || selectedId === '' ? '' : String(selectedId);
        return `<option value="">Unassigned</option>` + users.map(user =>
            `<option value="${user.id}"${String(user.id) === selected ? ' selected' : ''}>${esc(user.name)}</option>`
        ).join('');
    }
    function fillAssigneeSelect(selectEl, selectedId, extraUser) {
        if (!selectEl) return;
        selectEl.innerHTML = assigneeOptions(selectedId, extraUser);
    }
    function setExtrasVisible(saved) {
        document.getElementById('leadExtras').hidden = !saved;
        document.getElementById('leadExtrasHint').hidden = saved;
    }
    function renderLabelSuggestions() {
        const select = document.getElementById('leadLabelSelect');
        if (select) {
            const attached = new Set((state.labels || []).map(label => String(label.id)));
            const available = (state.companyLabels || []).filter(label => !attached.has(String(label.id)));
            select.innerHTML = '<option value="">Select a label…</option>' + available.map(label =>
                `<option value="${esc(label.name)}">${esc(label.name)}</option>`
            ).join('');
            select.disabled = !state.editingId || !available.length;
        }
        renderLabelFilter();
    }
    function selectedFilterLabels() {
        return state.companyLabels.filter(label => state.labelIds.includes(String(label.id)));
    }
    function renderLabelFilter() {
        const chips = document.getElementById('leadLabelFilterChips');
        const select = document.getElementById('leadLabelFilterSelect');
        const selected = selectedFilterLabels();
        chips.innerHTML = selected.map(label => `
            <span class="lead-label-chip" style="background:${esc(label.color || '#4338ca')};color:${chipText(label.color)}">
                ${esc(label.name)}
                <button type="button" data-unfilter-label="${label.id}" title="Remove filter">&times;</button>
            </span>
        `).join('');
        const available = state.companyLabels.filter(label => !state.labelIds.includes(String(label.id)));
        select.innerHTML = `<option value="">${selected.length ? 'Add label…' : 'Filter labels…'}</option>` +
            available.map(label => `<option value="${label.id}">${esc(label.name)}</option>`).join('');
        select.hidden = available.length === 0 && selected.length > 0 && state.companyLabels.length > 0;
    }
    function renderSourceFilter(sources) {
        const select = document.getElementById('leadSourceFilter');
        if (!select) return;
        const fromDb = Array.isArray(sources) ? sources.filter(Boolean).map(String) : [];
        const list = [...new Set([...(LEAD_OPTIONS.sources || []), ...fromDb])];
        const current = state.source || '';
        if (current && current !== '__none__' && !list.includes(current)) list.push(current);
        select.innerHTML = `<option value="">All sources</option><option value="__none__">No source</option>` +
            list.map(source => `<option value="${esc(source)}">${esc(source)}</option>`).join('');
        select.value = current;
    }
    function renderAssigneeFilter() {
        const select = document.getElementById('leadAssigneeFilter');
        if (!select) return;
        const current = state.assignedTo || '';
        const users = [...state.assignees];
        if (current && current !== '__none__' && !users.some(user => String(user.id) === String(current))) {
            users.push({ id: current, name: 'Assignee #' + current });
        }
        select.innerHTML = `<option value="">All assignees</option><option value="__none__">Unassigned</option>` +
            users.map(user => `<option value="${esc(user.id)}">${esc(user.name)}</option>`).join('');
        select.value = current;
    }
    function renderLabels(labels) {
        state.labels = Array.isArray(labels) ? labels : [];
        const list = document.getElementById('leadLabelsList');
        if (!state.labels.length) {
            list.innerHTML = '<span class="lead-note-empty">No labels yet.</span>';
            renderLabelSuggestions();
            renderLeadModalHeaderMeta();
            return;
        }
        list.innerHTML = state.labels.map(label => `
            <span class="lead-label-chip" style="background:${esc(label.color || '#4338ca')};color:${chipText(label.color)}">
                ${esc(label.name)}
                <button type="button" data-remove-label="${label.id}" title="Remove label">&times;</button>
            </span>
        `).join('');
        renderLabelSuggestions();
        renderLeadModalHeaderMeta();
    }
    function renderNotes(notes) {
        state.notes = Array.isArray(notes) ? notes : [];
        const list = document.getElementById('leadNotesList');
        if (!state.notes.length) {
            list.innerHTML = '<div class="lead-note-empty">No notes yet. Add one below.</div>';
            return;
        }
        list.innerHTML = state.notes.map(note => `
            <div class="lead-note-item">
                <div class="lead-note-text">${esc(note.note)}</div>
                <div class="lead-note-meta">
                    <span>Added by ${esc(note.author || 'Unknown')}</span>
                    <span>
                        ${esc(note.time_ago || formatAt(note.created_at))}
                        <button type="button" class="icon-btn" data-remove-note="${note.id}" title="Delete note">&times;</button>
                    </span>
                </div>
            </div>
        `).join('');
    }
    function sortActivitiesDesc(items) {
        return [...(items || [])].sort((a, b) => {
            const timeDiff = new Date(b.created_at || 0) - new Date(a.created_at || 0);
            if (timeDiff !== 0) return timeDiff;
            return (Number(b.id) || 0) - (Number(a.id) || 0);
        });
    }
    function activityDetailLines(item) {
        const meta = item.meta || {};
        const lines = [];
        if (meta.from_user_name !== undefined || meta.to_user_name !== undefined || meta.from_user_id || meta.to_user_id) {
            lines.push((meta.from_user_name || 'Unassigned') + ' → ' + (meta.to_user_name || 'Unassigned'));
        }
        if (meta.field) {
            lines.push((meta.field || 'Field') + ': ' + (meta.from || '—') + ' → ' + (meta.to || '—'));
        } else if ((meta.from != null && meta.from !== '') || (meta.to != null && meta.to !== '')) {
            lines.push((meta.from || '—') + ' → ' + (meta.to || '—'));
        }
        if (meta.reason) lines.push('Reason: ' + meta.reason);
        if (meta.label) lines.push('Label: ' + meta.label);
        if (meta.value) lines.push((meta.type || 'Value') + ': ' + meta.value);
        if (meta.source) lines.push('Source: ' + meta.source);
        if (meta.site_name) lines.push('Facility: ' + meta.site_name);
        if (meta.user_id) lines.push('Storeganise user: ' + meta.user_id);
        if (meta.linked_existing) lines.push('Linked to existing Storeganise user.');
        if (Array.isArray(meta.duplicates) && meta.duplicates.length) {
            meta.duplicates.forEach((dup) => {
                const bits = [dup.name, dup.email, dup.phone].filter(Boolean).join(' · ');
                const matches = Array.isArray(dup.match_values) ? dup.match_values.join(', ') : '';
                lines.push('Possible duplicate: ' + (bits || dup.id || 'User') + (matches ? ' (' + matches + ')' : ''));
            });
        }
        if (Array.isArray(meta.match_values) && meta.match_values.length) {
            lines.push('Matched on: ' + meta.match_values.join(', '));
        }
        lines.push('By ' + (item.actor || 'System'));
        lines.push(formatAt(item.created_at));
        return lines;
    }
    function activityItemHtml(item) {
        const details = activityDetailLines(item);
        return `
            <div class="lead-activity-item" data-activity-id="${esc(item.id || '')}" role="button" tabindex="0">
                <div class="lead-activity-summary">${esc(item.summary || 'Updated this lead')}</div>
                <div class="lead-activity-meta">${esc(item.time_ago || formatAt(item.created_at))} · Click for details</div>
                <div class="lead-activity-details" hidden>
                    ${details.map(line => `<div>${esc(line)}</div>`).join('')}
                </div>
            </div>
        `;
    }
    function renderActivityPreview(latest, total) {
        const preview = document.getElementById('leadActivityPreview');
        const countEl = document.getElementById('leadActivityCount');
        const trigger = document.getElementById('leadActivityTrigger');
        state.activityTotal = Number(total || 0);
        if (trigger) trigger.disabled = !state.editingId;
        if (!latest) {
            if (preview) preview.innerHTML = '<p class="chp-empty">No lead activity yet.</p>';
            if (countEl) countEl.textContent = 'View all updates';
            return;
        }
        if (preview) {
            preview.innerHTML = `
                <div class="lead-activity-summary">${esc(latest.summary || 'Updated this lead')}</div>
                <div class="lead-activity-meta">${esc(latest.time_ago || formatAt(latest.created_at))}</div>
            `;
        }
        if (countEl) {
            const n = state.activityTotal || 1;
            countEl.textContent = 'View all ' + n + ' update' + (n === 1 ? '' : 's');
        }
        document.getElementById('leadActivityModalTitle').textContent =
            (leadDisplayName() || 'Lead') + ' · activity';
    }
    function renderActivityPage(items, pagination) {
        const full = document.getElementById('leadActivityFull');
        const page = pagination?.current_page || state.activityPage || 1;
        const last = pagination?.last_page || state.activityLastPage || 1;
        const total = pagination?.total ?? state.activityTotal ?? 0;
        state.activities = sortActivitiesDesc(items);
        state.activityPage = page;
        state.activityLastPage = last;
        state.activityTotal = total;
        if (full) {
            full.innerHTML = state.activities.length
                ? state.activities.map(activityItemHtml).join('')
                : '<p class="chp-empty" style="padding:1rem">No lead activity yet.</p>';
        }
        document.getElementById('leadActivityPageInfo').textContent =
            `Showing page ${page} of ${last} (${total} update${total === 1 ? '' : 's'})`;
        document.getElementById('leadActivityPrev').disabled = page <= 1;
        document.getElementById('leadActivityNext').disabled = page >= last;
        if (page === 1) {
            renderActivityPreview(state.activities[0] || null, total);
        } else {
            const countEl = document.getElementById('leadActivityCount');
            if (countEl) countEl.textContent = 'View all ' + total + ' update' + (total === 1 ? '' : 's');
        }
    }
    async function loadActivityPage(page) {
        if (!state.editingId) return;
        const q = new URLSearchParams({ page: String(page || 1), per_page: '20' });
        const res = await fetch(api + '/' + state.editingId + '/activity-log?' + q.toString(), {
            credentials: 'same-origin',
            headers: headers(),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || 'Could not load activity.');
        renderActivityPage(data.data || [], data.pagination || {});
    }
    function renderActivities(leadOrItems) {
        if (Array.isArray(leadOrItems)) {
            const items = sortActivitiesDesc(leadOrItems);
            renderActivityPreview(items[0] || null, items.length);
            return;
        }
        const lead = leadOrItems || {};
        renderActivityPreview(lead.latest_activity || (lead.activities || [])[0] || null, lead.activity_count ?? (lead.activities || []).length);
    }
    async function refreshActivities(id) {
        if (!id) return;
        try {
            await loadActivityPage(1);
        } catch {}
    }
    async function loadAssignees() {
        try {
            const res = await fetch(api + '/assignees', { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            state.assignees = data.data || [];
        } catch {
            state.assignees = [];
        }
        renderAssigneeFilter();
        fillAssigneeSelect(document.getElementById('leadAssignedTo'), document.getElementById('leadAssignedTo')?.value || '');
    }

    async function loadCompanyLabels() {
        try {
            const res = await fetch(api + '/labels', { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            state.companyLabels = data.data || [];
            renderLabelSuggestions();
            renderCompanyLabelList();
            syncOpenLeadLabelsFromCompany();
        } catch {
            state.companyLabels = [];
            renderCompanyLabelList();
        }
    }
    async function loadCompanyStatuses() {
        try {
            const res = await fetch(api + '/statuses', { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            state.statuses = data.data || [];
            state.defaultStatus = data.meta?.default || state.defaultStatus || 'new';
        } catch {
            state.statuses = [];
        }
        renderStatusTabs();
        fillStatusSelect(document.getElementById('leadStatus')?.value || state.defaultStatus);
        renderCompanyStatusList();
    }
    function statusOptions(selected) {
        const current = selected || state.defaultStatus || 'new';
        const rows = [...(state.statuses || [])];
        if (current && !rows.some(s => s.slug === current)) {
            rows.push({ slug: current, name: current });
        }
        return rows.map(s =>
            `<option value="${esc(s.slug)}" ${s.slug === current ? 'selected' : ''}>${esc(s.name)}</option>`
        ).join('');
    }
    function fillStatusSelect(selected) {
        const select = document.getElementById('leadStatus');
        if (!select) return;
        select.innerHTML = statusOptions(selected || state.defaultStatus || 'new');
    }
    function renderStatusTabs() {
        const wrap = document.getElementById('leadStatusTabs');
        if (!wrap) return;
        const current = state.status || 'all';
        const counts = state.statusCounts || {};
        const tabs = [{ slug: 'all', name: 'All' }, ...(state.statuses || [])];
        wrap.innerHTML = tabs.map(status => {
            const count = counts[status.slug];
            const badge = count == null ? '' : ` <span data-count="${esc(status.slug)}">${count}</span>`;
            return `<button type="button" class="leads-tab${current === status.slug ? ' active' : ''}" data-status="${esc(status.slug)}">${esc(status.name)}${badge}</button>`;
        }).join('');
        if (current !== 'all' && !(state.statuses || []).some(s => s.slug === current)) {
            state.status = 'all';
            wrap.querySelector('[data-status="all"]')?.classList.add('active');
        }
    }
    function renderCompanyStatusList() {
        const list = document.getElementById('leadCompanyStatusList');
        if (!list) return;
        if (!state.statuses.length) {
            list.innerHTML = '<div class="chp-empty">No statuses yet. Add one below.</div>';
            return;
        }
        list.innerHTML = state.statuses.map(status => `
            <div class="leads-rule-row">
                <div class="leads-rule-row-main">
                    <input type="text" class="leads-status-name-input" data-status-name="${status.id}" value="${esc(status.name)}" maxlength="50" aria-label="Status name">
                </div>
                <div class="leads-rule-row-actions">
                    <button type="button" class="btn btn-secondary btn-sm" data-save-company-status="${status.id}">Save</button>
                    ${status.is_locked ? '<span class="lead-meta">Required</span>' : `<button type="button" class="btn btn-secondary btn-sm" data-delete-company-status="${status.id}">Delete</button>`}
                </div>
            </div>
        `).join('');
    }
    function openStatusesModal() {
        document.getElementById('leadStatusesModal')?.classList.add('open');
        renderCompanyStatusList();
        loadCompanyStatuses();
        document.getElementById('leadCompanyStatusName')?.focus();
    }
    function closeStatusesModal() {
        document.getElementById('leadStatusesModal')?.classList.remove('open');
    }
    function renderCompanyLabelList() {
        const list = document.getElementById('leadCompanyLabelList');
        if (!list) return;
        if (!state.companyLabels.length) {
            list.innerHTML = '<div class="chp-empty">No labels yet. Add one below.</div>';
            return;
        }
        list.innerHTML = state.companyLabels.map(label => `
            <div class="leads-rule-row">
                <div class="leads-rule-row-main">
                    <input type="text" class="leads-status-name-input" data-label-name="${label.id}" value="${esc(label.name)}" maxlength="50" aria-label="Label name">
                </div>
                <div class="leads-rule-row-actions">
                    <input type="color" class="leads-label-row-color" data-label-color="${label.id}" value="${esc(label.color || '#4338ca')}" title="Change color" aria-label="Change color">
                    <button type="button" class="btn btn-secondary btn-sm" data-save-company-label="${label.id}">Save</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-delete-company-label="${label.id}">Delete</button>
                </div>
            </div>
        `).join('');
    }
    function openLabelsModal() {
        document.getElementById('leadLabelsModal')?.classList.add('open');
        renderCompanyLabelList();
        loadCompanyLabels();
        document.getElementById('leadCompanyLabelName')?.focus();
    }
    function closeLabelsModal() {
        document.getElementById('leadLabelsModal')?.classList.remove('open');
    }
    function syncOpenLeadLabelsFromCompany() {
        if (!state.labels.length) return;
        const next = state.labels.map(label => {
            const updated = state.companyLabels.find(item => String(item.id) === String(label.id));
            return updated ? { ...label, name: updated.name, color: updated.color } : label;
        });
        const changed = next.some((label, index) =>
            label.name !== state.labels[index].name || label.color !== state.labels[index].color
        );
        if (changed) renderLabels(next);
    }
    async function saveCompanyLabel(id) {
        const input = document.querySelector(`[data-label-name="${id}"]`);
        const colorEl = document.querySelector(`[data-label-color="${id}"]`);
        const name = input?.value.trim() || '';
        if (!name) { input?.focus(); return; }
        const save = document.querySelector(`[data-save-company-label="${id}"]`);
        if (save) save.disabled = true;
        try {
            const res = await fetch(api + '/labels/' + id, {
                method: 'PATCH', credentials: 'same-origin', headers: headers(true),
                body: JSON.stringify({ name, color: colorEl?.value || '#4338ca' }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not update label.');
            await loadCompanyLabels();
            loadLeads();
        } catch (err) {
            alert(err.message);
        } finally {
            if (save) save.disabled = false;
        }
    }

    function contactKindLabel(type) {
        return type === 'email' ? 'email address' : 'phone number';
    }
    function contactRoleLabel(listId) {
        return String(listId || '').startsWith('alt') ? 'alternate' : 'primary';
    }
    function addContactRow(listId, value, placeholder, opts = {}) {
        const list = document.getElementById(listId);
        if (!list) return;
        const row = document.createElement('div');
        row.className = 'identity-row';
        const type = opts.type || 'text';
        const required = opts.required ? 'required' : '';
        const max = opts.max || (type === 'email' ? '255' : '50');
        const identityId = opts.identityId ? String(opts.identityId) : '';
        if (identityId) row.dataset.identityId = identityId;
        row.innerHTML = `
            <input type="${esc(type)}" class="id-value" value="${esc(value || '')}" placeholder="${esc(placeholder)}" maxlength="${esc(max)}" ${required}>
            <button type="button" class="icon-btn" title="Remove" aria-label="Remove ${esc(contactKindLabel(type))}">&times;</button>
        `;
        row.querySelector('.icon-btn').addEventListener('click', () => removeContactRow(listId, row, opts));
        list.appendChild(row);
    }
    async function removeContactRow(listId, row, opts = {}) {
        const list = document.getElementById(listId);
        if (!list || !row) return;
        const btn = row.querySelector('.icon-btn');
        const input = row.querySelector('.id-value');
        const identityId = row.dataset.identityId || '';
        const value = (input?.value || '').trim();
        const kind = contactKindLabel(opts.type);
        const role = contactRoleLabel(listId);
        const keepLast = () => opts.keepOne && list.querySelectorAll('.identity-row').length <= 1;
        const clearOrRemove = () => {
            if (keepLast()) {
                if (input) input.value = '';
                delete row.dataset.identityId;
                return;
            }
            row.remove();
        };

        if (state.editingId && identityId) {
            if (!confirm(`Remove this ${role} ${kind} from the lead? This takes effect immediately.`)) return;
            if (btn) {
                btn.disabled = true;
                btn.classList.add('is-busy');
                btn.innerHTML = '<span class="leads-spinner" aria-hidden="true"></span>';
            }
            try {
                const res = await fetch(api + '/' + state.editingId + '/identities/' + identityId, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: headers(true),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || 'Could not remove this ' + kind + '.');
                clearOrRemove();
                if (data.data) {
                    upsertLeadRow(data.data);
                    renderActivities(data.data);
                    if (activityModal.classList.contains('open')) {
                        loadActivityPage(1).catch(() => {});
                    }
                }
                await loadLeads();
            } catch (err) {
                alert(err.message);
                if (btn) btn.disabled = false;
            }
            return;
        }

        if (value && !confirm(`Remove this ${role} ${kind}?`)) return;
        clearOrRemove();
    }
    function fillContactList(listId, items, placeholder, opts = {}) {
        const list = document.getElementById(listId);
        if (!list) return;
        list.innerHTML = '';
        const rows = (Array.isArray(items) ? items : [])
            .map(item => {
                if (typeof item === 'string') return { value: item.trim(), id: null };
                return { value: String(item?.value || '').trim(), id: item?.id || null };
            })
            .filter(item => item.value);
        if (!rows.length) {
            addContactRow(listId, '', placeholder, { ...opts, required: !!opts.required });
            return;
        }
        rows.forEach((item, index) => {
            addContactRow(listId, item.value, placeholder, {
                ...opts,
                required: !!opts.required && index === 0,
                identityId: item.id,
            });
        });
    }
    function readContactRows(listId) {
        const list = document.getElementById(listId);
        if (!list) return [];
        return [...list.querySelectorAll('.identity-row')].map(row => ({
            value: row.querySelector('.id-value').value.trim(),
        })).filter(item => item.value);
    }

    async function loadLeads(opts = {}) {
        if (opts.overlay !== false) setOverlay('leadsTableBusy', true);
        const q = new URLSearchParams({ page: String(state.page), per_page: '20', status: state.status });
        if (state.search) q.set('search', state.search);
        if (state.source) q.set('source', state.source);
        if (state.assignedTo) q.set('assigned_to', state.assignedTo);
        if (state.followUp) q.set('follow_up_day', String(state.followUp));
        state.labelIds.forEach(id => q.append('label_ids[]', id));
        try {
            const res = await fetch(api + '?' + q.toString(), { credentials: 'same-origin', headers: headers() });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not load leads.');
            const rows = data.data || [];
            body.innerHTML = rows.length
                ? rows.map(lead => leadRowHtml(lead)).join('')
                : `<tr><td colspan="8" class="empty-state">${state.search || state.labelIds.length || state.source || state.assignedTo || state.followUp ? 'No leads match this search.' : 'No leads yet. Create one to start matching conversations across channels.'}</td></tr>`;

            const pag = data.pagination || {};
            document.getElementById('leadsPageInfo').textContent = `Showing page ${pag.current_page || 1} of ${pag.last_page || 1} (${pag.total || 0} leads)`;
            document.getElementById('leadsPrev').disabled = (pag.current_page || 1) <= 1;
            document.getElementById('leadsNext').disabled = (pag.current_page || 1) >= (pag.last_page || 1);
            renderSourceFilter(data.sources || []);
            loadStatusCounts();
            loadFollowUpCounts();
        } catch (err) {
            body.innerHTML = '<tr><td colspan="8" class="empty-state">Could not load leads. Try again.</td></tr>';
            if (err?.message) console.error(err.message);
        } finally {
            if (opts.overlay !== false) setOverlay('leadsTableBusy', false);
        }
    }

    function applyFollowUpConfig(config) {
        const days = Array.isArray(config?.days) ? config.days.map(Number).filter(d => d >= 1) : [];
        state.followUpDays = days.length ? days : [4, 10, 30, 90];
        state.followUpLabels = Array.isArray(config?.labels) ? config.labels : [];
        state.followUpPlusMin = Number(config?.plus_min || (Math.max(...state.followUpDays) + 1));
        if (state.followUp && !state.followUpDays.includes(Number(state.followUp))) {
            state.followUp = '';
        }
        renderFollowUpChips();
        renderFollowUpDaysList();
    }
    function renderFollowUpChips() {
        const wrap = document.getElementById('leadFollowUpChips');
        if (!wrap) return;
        const counts = state.followUpCounts || {};
        const chips = [`<button type="button" class="leads-followup-chip ${state.followUp === '' ? 'active' : ''}" data-follow-up="">All</button>`]
            .concat(state.followUpDays.map(day => {
                const key = String(day);
                const count = counts[key] ?? 0;
                return `<button type="button" class="leads-followup-chip ${String(state.followUp) === key ? 'active' : ''}" data-follow-up="${esc(key)}">${esc(followUpLabelName(day))} <span data-count="${esc(key)}">${count}</span></button>`;
            }));
        wrap.innerHTML = chips.join('');
    }
    function renderFollowUpDaysList() {
        const list = document.getElementById('leadFollowUpDaysList');
        if (!list) return;
        if (!state.followUpDays.length) {
            list.innerHTML = '<p class="chp-empty">Add at least one follow-up day.</p>';
            return;
        }
        list.innerHTML = state.followUpDays.map(day => `
            <div class="leads-rule-row">
                <div class="leads-rule-row-main">
                    <div class="leads-rule-row-name">${esc(followUpLabelName(day))}</div>
                </div>
                <div class="leads-rule-row-actions">
                    <button type="button" class="link-btn" data-remove-follow-up-day="${day}">Remove</button>
                </div>
            </div>
        `).join('');
    }
    async function saveFollowUpDays(days) {
        const res = await fetch(api + '/follow-up-days', {
            method: 'PUT',
            credentials: 'same-origin',
            headers: headers(true),
            body: JSON.stringify({ days }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || 'Could not save follow-up days.');
        applyFollowUpConfig(data.data || {});
        loadCompanyLabels();
        loadFollowUpCounts();
        loadLeads();
    }
    function openFollowUpDaysModal() {
        renderFollowUpDaysList();
        document.getElementById('leadFollowUpDaysModal')?.classList.add('open');
        document.getElementById('leadFollowUpDayInput')?.focus();
    }
    function closeFollowUpDaysModal() {
        document.getElementById('leadFollowUpDaysModal')?.classList.remove('open');
    }

    async function loadFollowUpCounts() {
        const q = new URLSearchParams({ status: state.status });
        if (state.search) q.set('search', state.search);
        if (state.source) q.set('source', state.source);
        if (state.assignedTo) q.set('assigned_to', state.assignedTo);
        state.labelIds.forEach(id => q.append('label_ids[]', id));
        try {
            const res = await fetch(api + '/follow-up-counts?' + q.toString(), { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            const payload = data.data || {};
            if (payload.days) applyFollowUpConfig(payload);
            state.followUpCounts = payload.counts || payload;
            renderFollowUpChips();
        } catch {}
    }
    async function loadStatusCounts() {
        const q = new URLSearchParams({ status: state.status });
        if (state.search) q.set('search', state.search);
        if (state.source) q.set('source', state.source);
        if (state.assignedTo) q.set('assigned_to', state.assignedTo);
        if (state.followUp) q.set('follow_up_day', String(state.followUp));
        state.labelIds.forEach(id => q.append('label_ids[]', id));
        try {
            const res = await fetch(api + '/status-counts?' + q.toString(), { credentials: 'same-origin', headers: headers() });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) return;
            if (data.data && typeof data.data === 'object') {
                state.statusCounts = data.data;
                renderStatusTabs();
            }
        } catch {}
    }

    function val(id) {
        return (document.getElementById(id)?.value || '').trim();
    }
    function setVal(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value || '';
    }
    function splitName(name) {
        const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return { first: '', last: '' };
        if (parts.length === 1) return { first: parts[0], last: '' };
        return { first: parts[0], last: parts.slice(1).join(' ') };
    }
    function leadDisplayName() {
        const composed = [val('leadTitle'), val('leadFirstName'), val('leadLastName')].filter(Boolean).join(' ');
        return composed || 'Lead';
    }
    function selectedCustomerType() {
        return document.querySelector('input[name="leadCustomerType"]:checked')?.value || '';
    }
    function setCustomerType(value) {
        document.querySelectorAll('input[name="leadCustomerType"]').forEach((input) => {
            input.checked = input.value === value;
        });
    }
    function fillSourceSelect(value) {
        const select = document.getElementById('leadSource');
        if (!select) return;
        const options = LEAD_OPTIONS.sources || [];
        const current = value || '';
        select.innerHTML = `<option value="">Select one</option>` +
            options.map(source => `<option value="${esc(source)}">${esc(source)}</option>`).join('');
        if (current && !options.includes(current)) {
            select.insertAdjacentHTML('beforeend', `<option value="${esc(current)}">${esc(current)}</option>`);
        }
        select.value = current;
    }
    function syncLeadProfileFields() {
        const type = selectedCustomerType();
        const resWrap = document.getElementById('leadResidentialWrap');
        const bizWrap = document.getElementById('leadBusinessWrap');
        const industry = val('leadBusinessIndustry');
        const reason = val('leadStorageReason');
        if (resWrap) resWrap.hidden = type !== 'residential';
        if (bizWrap) bizWrap.hidden = type !== 'business';
        const bizOtherWrap = document.getElementById('leadBusinessIndustryOtherWrap');
        const reasonOtherWrap = document.getElementById('leadStorageReasonOtherWrap');
        if (bizOtherWrap) bizOtherWrap.hidden = type !== 'business' || industry !== 'Other';
        if (reasonOtherWrap) reasonOtherWrap.hidden = reason !== 'Other';
    }

    function showLeadTab(name) {
        const tabName = name || 'primary';
        document.querySelectorAll('.lead-form-tab').forEach((tab) => {
            const active = tab.dataset.leadTab === tabName;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        document.querySelectorAll('.lead-form-panel').forEach((panel) => {
            const active = panel.dataset.leadPanel === tabName;
            panel.classList.toggle('active', active);
            panel.hidden = !active;
        });
        if (tabName === 'matching') {
            searchInboxEmails(document.getElementById('leadInboxSearch').value.trim());
        }
        updateLeadModalQuoteButton();
    }
    function showLeadTabForElement(el) {
        const panel = el?.closest?.('[data-lead-panel]');
        if (panel?.dataset.leadPanel) showLeadTab(panel.dataset.leadPanel);
    }
    function resetForm() {
        form.reset();
        document.getElementById('leadId').value = '';
        showLeadTab('primary');
        fillContactList('primaryPhonesList', [], 'Phone number', { type: 'tel', required: true, keepOne: true });
        fillContactList('primaryEmailsList', [], 'name@company.com', { type: 'email', required: true, keepOne: true, max: '255' });
        fillContactList('altPhonesList', [], 'Phone number', { type: 'tel' });
        fillContactList('altEmailsList', [], 'name@company.com', { type: 'email', max: '255' });
        fillSourceSelect('');
        setCustomerType('');
        fillStatusSelect(state.defaultStatus);
        syncLeadProfileFields();
        errorEl.hidden = true;
        document.getElementById('leadModalTitle').textContent = 'New Lead';
        setLeadModalAdded(null);
        clearLeadModalChannelLinks();
        document.getElementById('deleteLeadBtn').hidden = true;
        document.getElementById('leadHistoryEmpty').hidden = false;
        document.getElementById('leadHistoryEmpty').textContent = 'Save this lead to load Phone, Inbox, Viber, WhatsApp, Facebook, and SMS history.';
        document.getElementById('leadHistoryBody').hidden = true;
        document.getElementById('leadHistoryBody').innerHTML = '';
        document.getElementById('leadNoteInput').value = '';
        const labelSelect = document.getElementById('leadLabelSelect');
        if (labelSelect) labelSelect.value = '';
        fillAssigneeSelect(document.getElementById('leadAssignedTo'), '');
        renderLabels([]);
        renderNotes([]);
        setExtrasVisible(false);
        state.editingId = null;
        state.savedLeadStoreganiseSiteId = null;
        resetLeadStoreganiseFacilitySelect();
        state.leadPhones = [];
        state.leadName = '';
        state.attachedInboxConversations = [];
        state.pendingInboxConversations = [];
        document.getElementById('leadInboxSearch').value = '';
        document.getElementById('leadInboxResults').innerHTML = '';
        renderAttachedInboxEmails();
        renderActivities([]);
        renderLeadModalHeaderMeta(null);
        renderStoreganiseBlock(null);
        updateLeadModalQuoteButton();
    }

    function storeganiseSiteLabel(site) {
        const name = String(site?.name || '').trim();
        const code = String(site?.code || '').trim();
        if (name && code && name.toLowerCase() !== code.toLowerCase()) {
            return `${name} (${code})`;
        }
        return name || code || String(site?.id || 'Facility');
    }

    async function loadStoreganiseSites() {
        if (!STOREGANISE_CONNECTED || state.storeganiseSitesLoaded) {
            return state.storeganiseSites;
        }
        const statusEl = document.getElementById('leadStoreganiseStatus');
        try {
            const res = await fetch('/api/integrations/storeganise/sites', {
                credentials: 'same-origin',
                headers: headers(),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && Array.isArray(data.sites)) {
                state.storeganiseSites = data.sites;
                state.storeganiseSitesLoaded = true;
                return state.storeganiseSites;
            }
            if (statusEl) {
                statusEl.hidden = false;
                statusEl.className = 'form-hint is-error';
                statusEl.textContent = data.error || 'Could not load Storeganise facilities. Refresh the page or check the integration.';
            }
        } catch (error) {
            console.error('Failed to load Storeganise facilities', error);
            if (statusEl) {
                statusEl.hidden = false;
                statusEl.className = 'form-hint is-error';
                statusEl.textContent = 'Could not load Storeganise facilities.';
            }
        }
        return state.storeganiseSites;
    }

    function renderStoreganiseSiteOptions(selectedId) {
        const select = document.getElementById('leadStoreganiseSite');
        if (!select) return;
        const options = ['<option value="">Select a facility…</option>'];
        state.storeganiseSites.forEach((site) => {
            const selected = String(site.id) === String(selectedId || '') ? ' selected' : '';
            options.push(`<option value="${esc(site.id)}"${selected}>${esc(storeganiseSiteLabel(site))}</option>`);
        });
        select.innerHTML = options.join('');
    }

    function renderStoreganiseActionButton() {
        const btn = document.getElementById('syncLeadStoreganiseBtn');
        const siteId = document.getElementById('leadStoreganiseSite')?.value || '';
        if (!btn) return;
        if (!siteId || !state.storeganiseAction) {
            btn.hidden = true;
            return;
        }
        btn.hidden = false;
        btn.disabled = false;
        btn.textContent = state.storeganiseAction === 'update' ? 'Update in Storeganise' : 'Push to Storeganise';
    }

    async function refreshStoreganiseAction(leadId, siteId) {
        state.storeganiseAction = null;
        renderStoreganiseActionButton();
        if (!STOREGANISE_CONNECTED || !leadId || !siteId) {
            renderLeadModalHeaderMeta();
            return;
        }
        try {
            const q = new URLSearchParams({ site_id: siteId });
            const res = await fetch(`${api}/${leadId}/storeganise/status?${q.toString()}`, {
                credentials: 'same-origin',
                headers: headers(),
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && (data.action === 'push' || data.action === 'update')) {
                state.storeganiseAction = data.action;
            }
        } catch (error) {
            console.error('Failed to resolve Storeganise action', error);
        }
        renderStoreganiseActionButton();
        renderLeadModalHeaderMeta();
    }

    async function renderStoreganiseBlock(lead) {
        const block = document.getElementById('leadStoreganiseBlock');
        if (!block) return;
        if (!STOREGANISE_CONNECTED) {
            block.hidden = true;
            renderLeadModalHeaderMeta(lead);
            return;
        }
        block.hidden = !lead?.id;
        if (!lead?.id) {
            state.storeganiseAction = null;
            renderStoreganiseActionButton();
            renderLeadModalHeaderMeta(null);
            return;
        }
        await loadStoreganiseSites();
        const selectedSite = lead.storeganise_site_id || document.getElementById('leadStoreganiseSite')?.value || '';
        renderStoreganiseSiteOptions(selectedSite);
        await refreshStoreganiseAction(lead.id, selectedSite || document.getElementById('leadStoreganiseSite')?.value || '');
        renderLeadModalHeaderMeta(lead);
    }

    async function submitStoreganiseAction(mode) {
        const siteId = document.getElementById('leadStoreganiseSite')?.value || '';
        const btn = document.getElementById('syncLeadStoreganiseBtn');
        if (!document.getElementById('leadId').value) {
            alert('Save this lead before syncing to Storeganise.');
            return;
        }
        if (!siteId) {
            alert('Select a facility first.');
            return;
        }
        const busyLabel = mode === 'update' ? 'Updating…' : 'Pushing…';
        setBusy(btn, true, busyLabel);
        try {
            const saved = await persistLeadForm({ reloadList: false, useOverlay: false });
            if (!saved?.id) {
                return;
            }
            const leadId = saved.id;
            const endpoint = mode === 'update' ? 'update' : 'push';
            const res = await fetch(`${api}/${leadId}/storeganise/${endpoint}`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers(true),
                body: JSON.stringify({ site_id: siteId }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                alert(data.error || data.message || `Failed to ${mode === 'update' ? 'update' : 'push'} lead in Storeganise.`);
                await refreshStoreganiseAction(leadId, siteId);
                return;
            }
            if (data.data) {
                fillForm(data.data);
                renderStoreganiseBlock(data.data);
                renderActivities(data.data);
            }
            alert(data.message || `Lead ${mode === 'update' ? 'updated in' : 'pushed to'} Storeganise.`);
        } catch (error) {
            console.error(error);
            alert(`Failed to ${mode === 'update' ? 'update' : 'push'} lead in Storeganise.`);
        } finally {
            setBusy(btn, false);
        }
    }

    async function syncLeadToStoreganise() {
        const mode = state.storeganiseAction === 'update' ? 'update' : 'push';
        await submitStoreganiseAction(mode);
    }

    function fillForm(lead) {
        const parsed = splitName(lead.name);
        document.getElementById('leadId').value = lead.id;
        setSavedLeadStoreganiseSiteId(lead.storeganise_site_id);
        resetLeadStoreganiseFacilitySelect();
        updateLeadModalQuoteButton();
        setVal('leadTitle', lead.title);
        setVal('leadFirstName', lead.first_name || parsed.first);
        setVal('leadLastName', lead.last_name || parsed.last);
        setVal('leadAddress', lead.address);
        setVal('leadCity', lead.city);
        setVal('leadPostal', lead.postal_code);
        fillContactList('primaryPhonesList', lead.primary_phones || (lead.phone ? [lead.phone] : []), 'Phone number', { type: 'tel', required: true, keepOne: true });
        fillContactList('primaryEmailsList', lead.primary_emails || (lead.email ? [lead.email] : []), 'name@company.com', { type: 'email', required: true, keepOne: true, max: '255' });
        setVal('leadCompany', lead.company_name);
        setVal('leadDob', lead.date_of_birth);
        setVal('leadAltTitle', lead.alt_title);
        setVal('leadAltFirstName', lead.alt_first_name);
        setVal('leadAltLastName', lead.alt_last_name);
        setVal('leadAltAddress', lead.alt_address);
        setVal('leadAltCity', lead.alt_city);
        setVal('leadAltPostal', lead.alt_postal_code);
        fillContactList('altPhonesList', lead.alt_phones || (lead.alt_phone ? [lead.alt_phone] : []), 'Phone number', { type: 'tel' });
        fillContactList('altEmailsList', lead.alt_emails || (lead.alt_email ? [lead.alt_email] : []), 'name@company.com', { type: 'email', max: '255' });
        fillStatusSelect(lead.status || state.defaultStatus);
        fillSourceSelect(lead.source || '');
        setCustomerType(lead.customer_type || '');
        setVal('leadResidentialType', lead.residential_type);
        setVal('leadBusinessIndustry', lead.business_industry);
        setVal('leadBusinessIndustryOther', lead.business_industry_other);
        setVal('leadStorageReason', lead.storage_reason);
        setVal('leadStorageReasonOther', lead.storage_reason_other);
        syncLeadProfileFields();
        fillAssigneeSelect(document.getElementById('leadAssignedTo'), lead.assigned_to, lead.assigned_user);
        document.getElementById('leadFacebook').value = lead.facebook_name || '';
        document.getElementById('leadInstagram').value = lead.instagram_username || '';
        document.getElementById('leadInboxSearch').value = '';
        document.getElementById('leadInboxResults').innerHTML = '';
        state.pendingInboxConversations = [];
        state.attachedInboxConversations = Array.isArray(lead.attached_inbox_conversations) ? lead.attached_inbox_conversations : [];
        renderAttachedInboxEmails();
        if (!lead.attached_inbox_conversations) {
            loadAttachedInboxEmails(lead.id);
        }
        document.getElementById('leadModalTitle').textContent = [lead.title, lead.name].filter(Boolean).join(' ') || 'Lead';
        setLeadModalAdded(lead);
        document.getElementById('deleteLeadBtn').hidden = false;
        state.editingId = lead.id;
        state.leadPhones = leadPhoneValues(lead);
        state.leadName = [lead.title, lead.name].filter(Boolean).join(' ').trim();
        renderLeadModalChannelLinks([], [], state.leadPhones);
        renderLabels(lead.labels || []);
        renderNotes(lead.notes || []);
        renderActivities(lead);
        setExtrasVisible(true);
        loadHistory(lead.id);
        if (activityModal.classList.contains('open')) {
            loadActivityPage(1).catch(() => {});
        }
        renderStoreganiseBlock(lead);
    }

    async function loadHistory(id) {
        const empty = document.getElementById('leadHistoryEmpty');
        const pane = document.getElementById('leadHistoryBody');
        empty.hidden = false;
        empty.textContent = 'Loading contact history…';
        pane.hidden = true;
        renderLeadModalChannelLinks([], [], state.leadPhones);
        try {
            const res = await fetch(api + '/' + id + '/history', { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            const threads = data.threads || [];
            const events = (data.events || []).slice(0, 20);
            renderLeadModalChannelLinks(threads, data.events || [], state.leadPhones);
            if (!threads.length && !events.length) {
                empty.textContent = 'No matching conversations yet. History appears after this person messages any channel.';
                return;
            }
            empty.hidden = true;
            pane.hidden = false;
            pane.innerHTML = `
                ${threads.map(t => `
                    <div class="lh-item">
                        <span class="lead-badge">${esc(t.label || t.channel)}</span>
                        <div class="lh-title">${esc(t.title || '')}</div>
                        <div class="lh-preview">${esc(t.preview || '')}</div>
                        ${t.deep_link ? `<a class="lh-link" href="${esc(t.deep_link)}">Open thread →</a>` : ''}
                    </div>
                `).join('')}
                <h4 style="margin-top:1rem">Timeline</h4>
                ${events.map(ev => `
                    <div class="lh-event">
                        <span class="lead-badge">${esc(ev.label || ev.channel)}</span>
                        <span class="lead-meta">${esc(ev.direction || '')} · ${esc(formatAt(ev.at))}</span>
                        <div class="lh-preview">${esc(ev.preview || '')}</div>
                    </div>
                `).join('')}
            `;
        } catch (err) {
            renderLeadModalChannelLinks([], [], state.leadPhones);
            empty.textContent = err.message || 'Could not load contact history.';
        }
    }

    function inboxEmailIds(list) {
        return (list || []).map(c => Number(c.id));
    }
    function inboxEmailMeta(c) {
        const from = c.from_name || c.from_email || 'Unknown sender';
        const mailbox = c.inbox?.name || c.inbox?.email || 'Shared inbox';
        const subject = c.subject || '(No subject)';
        return { from, mailbox, subject };
    }
    function renderAttachedInboxEmails() {
        renderLeadModalHeaderMeta();
        const list = document.getElementById('leadInboxAttachedList');
        if (!list) return;
        const items = state.editingId ? state.attachedInboxConversations : state.pendingInboxConversations;
        if (!items.length) {
            list.innerHTML = '<p class="lead-inbox-empty">No shared emails attached yet.</p>';
            return;
        }
        list.innerHTML = items.map(c => {
            const meta = inboxEmailMeta(c);
            return `
                <div class="lead-inbox-row" data-inbox-id="${c.id}">
                    <div>
                        <strong>${esc(meta.subject)}</strong>
                        <div class="lead-meta">${esc(meta.from)}${c.from_email && c.from_name ? ' · ' + esc(c.from_email) : ''}</div>
                        <div class="lead-meta">${esc(meta.mailbox)}</div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" data-inbox-detach="${c.id}">Remove</button>
                </div>
            `;
        }).join('');
    }
    function renderInboxSearchResults(items) {
        const box = document.getElementById('leadInboxResults');
        if (!box) return;
        const attached = new Set(inboxEmailIds(state.editingId ? state.attachedInboxConversations : state.pendingInboxConversations));
        const rows = (items || []).filter(c => !attached.has(Number(c.id)));
        if (!rows.length) {
            box.innerHTML = '<p class="lead-inbox-empty">No matching shared emails.</p>';
            return;
        }
        box.innerHTML = rows.map(c => {
            const meta = inboxEmailMeta(c);
            return `
                <div class="lead-inbox-row" data-inbox-id="${c.id}">
                    <div>
                        <strong>${esc(meta.subject)}</strong>
                        <div class="lead-meta">${esc(meta.from)}${c.from_email && c.from_name ? ' · ' + esc(c.from_email) : ''}</div>
                        <div class="lead-meta">${esc(meta.mailbox)}</div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" data-inbox-attach="${c.id}">Attach</button>
                </div>
            `;
        }).join('');
        box._results = rows;
    }
    async function loadAttachedInboxEmails(id) {
        if (!id) return;
        try {
            const res = await fetch(api + '/' + id + '/inbox-conversations', { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Could not load attached emails.');
            state.attachedInboxConversations = data.data || [];
            renderAttachedInboxEmails();
        } catch (err) {
            document.getElementById('leadInboxAttachedList').innerHTML =
                `<p class="lead-inbox-empty">${esc(err.message || 'Could not load attached emails.')}</p>`;
        }
    }
    async function searchInboxEmails(q) {
        const box = document.getElementById('leadInboxResults');
        if (!box) return;
        box.innerHTML = '<p class="lead-inbox-empty">Searching…</p>';
        try {
            const params = new URLSearchParams({ q });
            if (state.editingId) params.set('except_lead_id', String(state.editingId));
            const res = await fetch(api + '/inbox-conversations?' + params.toString(), { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Could not search emails.');
            renderInboxSearchResults(data.data || []);
        } catch (err) {
            box.innerHTML = `<p class="lead-inbox-empty">${esc(err.message || 'Could not search emails.')}</p>`;
        }
    }
    async function attachInboxEmail(conversation) {
        if (state.editingId) {
            const res = await fetch(api + '/' + state.editingId + '/inbox-conversations', {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers(true),
                body: JSON.stringify({ conversation_id: conversation.id }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not attach email.');
            state.attachedInboxConversations = data.data?.attached_inbox_conversations
                || (data.conversation ? state.attachedInboxConversations.concat([data.conversation]) : state.attachedInboxConversations);
            renderAttachedInboxEmails();
            loadHistory(state.editingId);
            return;
        }
        if (!state.pendingInboxConversations.some(c => Number(c.id) === Number(conversation.id))) {
            state.pendingInboxConversations.push(conversation);
        }
        renderAttachedInboxEmails();
    }
    async function detachInboxEmail(id) {
        if (state.editingId) {
            const res = await fetch(api + '/' + state.editingId + '/inbox-conversations/' + id, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: headers(),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not detach email.');
            state.attachedInboxConversations = data.data || [];
            renderAttachedInboxEmails();
            loadHistory(state.editingId);
            return;
        }
        state.pendingInboxConversations = state.pendingInboxConversations.filter(c => Number(c.id) !== Number(id));
        renderAttachedInboxEmails();
    }

    function openModal() { modal.classList.add('open'); }
    function closeActivityModal() {
        activityModal.classList.remove('open');
    }
    async function openActivityModal() {
        if (!state.editingId) return;
        activityModal.classList.add('open');
        document.getElementById('leadActivityFull').innerHTML = '<p class="chp-empty" style="padding:1rem">Loading updates…</p>';
        try {
            await loadActivityPage(1);
        } catch (err) {
            document.getElementById('leadActivityFull').innerHTML =
                `<p class="chp-empty" style="padding:1rem">${esc(err.message || 'Could not load activity.')}</p>`;
        }
    }
    function closeModal() {
        closeActivityModal();
        modal.classList.remove('open');
        const url = new URL(window.location.href);
        url.searchParams.delete('lead');
        url.searchParams.delete('tab');
        history.replaceState(null, '', url);
    }

    function resolveLeadTab(name) {
        const allowed = ['primary', 'alternate', 'source', 'matching', 'notes'];
        return allowed.includes(name) ? name : 'primary';
    }

    async function openLead(id, options = {}) {
        const res = await fetch(api + '/' + id, { credentials: 'same-origin', headers: headers() });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Lead not found');
        fillForm(data.data);
        const tab = resolveLeadTab(options.tab || new URLSearchParams(window.location.search).get('tab') || 'primary');
        showLeadTab(tab);
        openModal();
        const url = new URL(window.location.href);
        url.searchParams.set('lead', id);
        if (tab !== 'primary') {
            url.searchParams.set('tab', tab);
        } else {
            url.searchParams.delete('tab');
        }
        history.replaceState(null, '', url);
    }

    document.getElementById('newLeadBtn').addEventListener('click', () => { resetForm(); openModal(); });
    document.getElementById('closeLeadModal').addEventListener('click', closeModal);
    document.getElementById('cancelLeadBtn').addEventListener('click', closeModal);
    document.getElementById('syncLeadStoreganiseBtn')?.addEventListener('click', () => { syncLeadToStoreganise(); });
    document.getElementById('leadStoreganiseSite')?.addEventListener('change', () => {
        const leadId = document.getElementById('leadId')?.value || '';
        const siteId = document.getElementById('leadStoreganiseSite')?.value || '';
        refreshStoreganiseAction(leadId, siteId);
        renderLeadModalHeaderMeta();
        updateLeadModalQuoteButton();
    });
    document.getElementById('leadActivityTrigger').addEventListener('click', openActivityModal);
    document.getElementById('closeLeadActivityModal').addEventListener('click', closeActivityModal);
    document.getElementById('leadActivityPrev').addEventListener('click', () => {
        if (state.activityPage > 1) loadActivityPage(state.activityPage - 1);
    });
    document.getElementById('leadActivityNext').addEventListener('click', () => {
        if (state.activityPage < state.activityLastPage) loadActivityPage(state.activityPage + 1);
    });
    activityModal.addEventListener('click', (e) => {
        if (e.target === activityModal) closeActivityModal();
    });
    document.getElementById('leadActivityFull').addEventListener('click', (e) => {
        const item = e.target.closest('.lead-activity-item');
        if (!item) return;
        const details = item.querySelector('.lead-activity-details');
        if (!details) return;
        const opening = details.hidden;
        item.classList.toggle('open', opening);
        details.hidden = !opening;
    });
    document.getElementById('leadActivityFull').addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const item = e.target.closest('.lead-activity-item');
        if (!item) return;
        e.preventDefault();
        item.click();
    });
    document.getElementById('addPrimaryPhoneBtn').addEventListener('click', () => addContactRow('primaryPhonesList', '', 'Phone number', { type: 'tel', keepOne: true }));
    document.getElementById('addPrimaryEmailBtn').addEventListener('click', () => addContactRow('primaryEmailsList', '', 'name@company.com', { type: 'email', keepOne: true, max: '255' }));
    document.getElementById('addAltPhoneBtn').addEventListener('click', () => addContactRow('altPhonesList', '', 'Phone number', { type: 'tel' }));
    document.getElementById('addAltEmailBtn').addEventListener('click', () => addContactRow('altEmailsList', '', 'name@company.com', { type: 'email', max: '255' }));
    document.getElementById('leadInboxSearch').addEventListener('input', () => {
        const q = document.getElementById('leadInboxSearch').value.trim();
        clearTimeout(state.inboxSearchTimer);
        state.inboxSearchTimer = setTimeout(() => searchInboxEmails(q), 250);
    });
    document.getElementById('leadInboxAttachedList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-inbox-detach]');
        if (!btn) return;
        btn.disabled = true;
        try {
            await detachInboxEmail(btn.dataset.inboxDetach);
        } catch (err) {
            alert(err.message || 'Could not detach email.');
        } finally {
            btn.disabled = false;
        }
    });
    document.getElementById('leadInboxResults').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-inbox-attach]');
        if (!btn) return;
        const id = Number(btn.dataset.inboxAttach);
        const results = document.getElementById('leadInboxResults')._results || [];
        const conversation = results.find(c => Number(c.id) === id);
        if (!conversation) return;
        btn.disabled = true;
        try {
            await attachInboxEmail(conversation);
            await searchInboxEmails(document.getElementById('leadInboxSearch').value.trim());
        } catch (err) {
            alert(err.message || 'Could not attach email.');
        } finally {
            btn.disabled = false;
        }
    });
    document.querySelectorAll('.lead-form-tab').forEach((tab) => {
        tab.addEventListener('click', () => showLeadTab(tab.dataset.leadTab));
    });
    document.querySelectorAll('input[name="leadCustomerType"]').forEach((input) => {
        input.addEventListener('change', syncLeadProfileFields);
    });
    document.getElementById('leadBusinessIndustry')?.addEventListener('change', syncLeadProfileFields);
    document.getElementById('leadStorageReason')?.addEventListener('change', syncLeadProfileFields);
    document.getElementById('leadsPrev').addEventListener('click', () => { state.page = Math.max(1, state.page - 1); loadLeads(); });
    document.getElementById('leadsNext').addEventListener('click', () => { state.page += 1; loadLeads(); });

    let searchTimer;
    document.getElementById('leadSearch').addEventListener('input', (e) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            state.search = e.target.value.trim();
            state.page = 1;
            loadLeads();
        }, 250);
    });
    document.getElementById('leadLabelFilterSelect').addEventListener('change', (e) => {
        const id = e.target.value;
        if (id && !state.labelIds.includes(id)) {
            state.labelIds.push(id);
            state.page = 1;
            renderLabelFilter();
            loadLeads();
        }
        e.target.value = '';
    });
    document.getElementById('leadLabelFilterChips').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-unfilter-label]');
        if (!btn) return;
        state.labelIds = state.labelIds.filter(id => id !== String(btn.dataset.unfilterLabel));
        state.page = 1;
        renderLabelFilter();
        loadLeads();
    });
    document.getElementById('leadSourceFilter')?.addEventListener('change', (e) => {
        state.source = e.target.value || '';
        state.page = 1;
        loadLeads();
    });
    document.getElementById('leadAssigneeFilter')?.addEventListener('change', (e) => {
        state.assignedTo = e.target.value || '';
        state.page = 1;
        loadLeads();
    });
    document.getElementById('leadStatusTabs')?.addEventListener('click', (e) => {
        const tab = e.target.closest('.leads-tab');
        if (!tab) return;
        document.querySelectorAll('#leadStatusTabs .leads-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        state.status = tab.dataset.status;
        state.page = 1;
        loadLeads();
    });
    document.getElementById('leadFollowUpChips')?.addEventListener('click', (e) => {
        const chip = e.target.closest('.leads-followup-chip');
        if (!chip) return;
        state.followUp = chip.dataset.followUp || '';
        state.page = 1;
        renderFollowUpChips();
        loadLeads();
    });
    document.getElementById('leadFollowUpDaysBtn')?.addEventListener('click', openFollowUpDaysModal);
    document.getElementById('closeLeadFollowUpDaysModal')?.addEventListener('click', closeFollowUpDaysModal);
    document.getElementById('closeLeadFollowUpDaysBtn')?.addEventListener('click', closeFollowUpDaysModal);
    document.getElementById('leadFollowUpDaysForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = document.getElementById('leadFollowUpDayInput');
        const day = Number(input?.value);
        if (!Number.isInteger(day) || day < 1 || day > 365) {
            return alert('Enter a day from 1 to 365.');
        }
        if (state.followUpDays.includes(day)) {
            input.value = '';
            return;
        }
        const btn = document.getElementById('addLeadFollowUpDayBtn');
        btn.disabled = true;
        try {
            await saveFollowUpDays([...state.followUpDays, day]);
            input.value = '';
        } catch (err) {
            alert(err.message);
        } finally {
            btn.disabled = false;
        }
    });
    document.getElementById('leadFollowUpDaysList')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-remove-follow-up-day]');
        if (!btn) return;
        const day = Number(btn.dataset.removeFollowUpDay);
        const next = state.followUpDays.filter(d => d !== day);
        if (!next.length) return alert('Keep at least one follow-up day.');
        try {
            await saveFollowUpDays(next);
        } catch (err) {
            alert(err.message);
        }
    });
    function closeMessageModal() {
        document.getElementById('leadMessageModal')?.classList.remove('open');
        state.messageLeadId = '';
        state.messageChannels = [];
        state.messageChannel = '';
    }
    function isMailFollowUp() {
        return currentMessageChannel()?.id === 'inbox';
    }
    function getMailHtmlEditor() {
        return {
            root: document.getElementById('leadMessageHtmlEditor'),
            visual: document.getElementById('leadMessageHtmlVisual'),
            source: document.getElementById('leadMessageHtmlSource'),
        };
    }
    function sanitizeFollowUpHtml(html) {
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
    function htmlToPlainFollowUp(html) {
        const wrap = document.createElement('div');
        wrap.innerHTML = String(html || '');
        return (wrap.textContent || '').replace(/\u00a0/g, ' ').trim();
    }
    function plainToFollowUpHtml(text) {
        return esc(String(text || '')).replace(/\r\n|\r|\n/g, '<br>');
    }
    function setMailEditorMode(mode) {
        const ed = getMailHtmlEditor();
        if (!ed.root) return;
        const visualMode = mode !== 'source';
        if (visualMode) {
            if (ed.visual && ed.source) ed.visual.innerHTML = sanitizeFollowUpHtml(ed.source.value);
            if (ed.visual) ed.visual.hidden = false;
            if (ed.source) ed.source.hidden = true;
        } else {
            if (ed.source && ed.visual) ed.source.value = sanitizeFollowUpHtml(ed.visual.innerHTML);
            if (ed.visual) ed.visual.hidden = true;
            if (ed.source) {
                ed.source.hidden = false;
                ed.source.focus();
            }
        }
        ed.root.querySelectorAll('[data-html-mode]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.dataset.htmlMode === (visualMode ? 'visual' : 'source'));
        });
    }
    function setMailEditorContent(html) {
        const ed = getMailHtmlEditor();
        const clean = sanitizeFollowUpHtml(html || '');
        if (ed.visual) ed.visual.innerHTML = clean;
        if (ed.source) ed.source.value = clean;
        setMailEditorMode('visual');
    }
    function getMailEditorContent() {
        const ed = getMailHtmlEditor();
        if (!ed.source) return '';
        if (ed.source.hidden === false) return sanitizeFollowUpHtml(ed.source.value.trim());
        return sanitizeFollowUpHtml((ed.visual?.innerHTML || '').trim());
    }
    function syncFollowUpComposer(isMail) {
        const htmlWrap = document.getElementById('leadMessageHtmlWrap');
        const plainWrap = document.getElementById('leadMessagePlainWrap');
        const subjectWrap = document.getElementById('leadMessageSubjectWrap');
        const wasMail = htmlWrap && !htmlWrap.hidden;
        if (wasMail && !isMail) {
            const html = getMailEditorContent();
            const plain = htmlToPlainFollowUp(html);
            const textarea = document.getElementById('leadMessageBody');
            if (textarea && plain) textarea.value = plain;
        } else if (!wasMail && isMail) {
            const textarea = document.getElementById('leadMessageBody');
            const plain = textarea?.value || '';
            if (plain.trim()) setMailEditorContent(plainToFollowUpHtml(plain));
        }
        if (htmlWrap) htmlWrap.hidden = !isMail;
        if (plainWrap) plainWrap.hidden = isMail;
        if (subjectWrap) subjectWrap.hidden = !isMail;
        fillMessageMailboxes(isMail);
        fillMessageRecipients(isMail);
    }
    function getFollowUpBody() {
        return isMailFollowUp() ? getMailEditorContent() : (document.getElementById('leadMessageBody')?.value || '');
    }
    function setFollowUpBody(value) {
        if (isMailFollowUp()) setMailEditorContent(value || '');
        else document.getElementById('leadMessageBody').value = value || '';
    }
    async function openMessageModal(leadId) {
        state.messageLeadId = String(leadId || '');
        const title = document.getElementById('leadMessageModalTitle');
        const err = document.getElementById('leadMessageError');
        if (err) { err.hidden = true; err.textContent = ''; }
        if (title) title.textContent = 'Send follow-up';
        document.getElementById('leadMessageModal')?.classList.add('open');
        document.getElementById('leadMessageBody').value = '';
        document.getElementById('leadMessageSubject').value = '';
        const toList = document.getElementById('leadMessageTo');
        if (toList) toList.innerHTML = '';
        setMailEditorContent('');
        setMailEditorMode('visual');
        if (!state.messageLeadId) return;
        try {
            const res = await fetch(api + '/' + state.messageLeadId + '/message-channels', { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Could not load channels.');
            state.messageChannels = data.data?.channels || [];
            renderMessageChannels();
        } catch (e) {
            if (err) { err.hidden = false; err.textContent = e.message; }
        }
    }
    function renderMessageChannels() {
        const wrap = document.getElementById('leadMessageChannels');
        if (!wrap) return;
        const firstAvailable = state.messageChannels.find(c => c.available);
        if (!state.messageChannel || !state.messageChannels.some(c => c.id === state.messageChannel && c.available)) {
            state.messageChannel = firstAvailable?.id || '';
        }
        wrap.innerHTML = state.messageChannels.map(ch => `
            <button type="button" class="lead-message-channel ${ch.id === state.messageChannel ? 'active' : ''}" data-channel="${esc(ch.id)}" ${ch.available ? '' : 'disabled'} title="${esc(ch.reason || ch.label)}">${esc(ch.label)}</button>
        `).join('') || '<p class="chp-empty">No channels available.</p>';
        fillMessageTemplates();
    }
    function currentMessageChannel() {
        return state.messageChannels.find(c => c.id === state.messageChannel) || null;
    }
    function mailboxOptionLabel(box) {
        const name = String(box.name || 'Mailbox');
        const email = String(box.email || '');
        const kind = box.type === 'shared' ? 'shared' : 'personal';
        return email ? `${name} — ${email} (${kind})` : `${name} (${kind})`;
    }
    function fillMessageMailboxes(isMail) {
        const wrap = document.getElementById('leadMessageMailboxWrap');
        const sel = document.getElementById('leadMessageMailbox');
        if (!wrap || !sel) return;
        const boxes = isMail ? (currentMessageChannel()?.mailboxes || []) : [];
        const previous = sel.value;
        sel.innerHTML = boxes.length
            ? boxes.map(box => `<option value="${box.id}">${esc(mailboxOptionLabel(box))}</option>`).join('')
            : '<option value="">No connected mailbox</option>';
        const shared = boxes.find(box => box.type === 'shared');
        if (previous && boxes.some(box => String(box.id) === String(previous))) sel.value = previous;
        else if (shared) sel.value = String(shared.id);
        else if (boxes[0]) sel.value = String(boxes[0].id);
        wrap.hidden = !isMail;
    }
    function fillMessageRecipients(isMail) {
        const wrap = document.getElementById('leadMessageToWrap');
        const list = document.getElementById('leadMessageTo');
        if (!wrap || !list) return;
        const emails = isMail ? (currentMessageChannel()?.emails || []) : [];
        const previous = new Set(getSelectedToEmails().map((email) => email.toLowerCase()));
        const selectAll = previous.size === 0;
        list.innerHTML = emails.length
            ? emails.map((email) => {
                const checked = selectAll || previous.has(String(email).toLowerCase()) ? 'checked' : '';
                return `<label class="lead-message-to-item"><input type="checkbox" value="${esc(email)}" ${checked}> <span>${esc(email)}</span></label>`;
            }).join('')
            : '<p class="chp-empty">No email addresses on this lead.</p>';
        wrap.hidden = !isMail;
    }
    function getSelectedToEmails() {
        return [...document.querySelectorAll('#leadMessageTo input[type="checkbox"]:checked')].map((el) => el.value);
    }
    function fillMessageTemplates() {
        const sel = document.getElementById('leadMessageTemplate');
        const ch = currentMessageChannel();
        const templates = ch?.templates || [];
        sel.innerHTML = `<option value="">Custom message</option>` + templates.map(t =>
            `<option value="${t.id}">${esc(t.name)}</option>`
        ).join('');
        syncFollowUpComposer(ch?.id === 'inbox');
        applyMessageTemplate();
    }
    function applyMessageTemplate() {
        const ch = currentMessageChannel();
        const id = document.getElementById('leadMessageTemplate').value;
        const tpl = (ch?.templates || []).find(t => String(t.id) === String(id));
        if (tpl) {
            setFollowUpBody(tpl.body || '');
            if (tpl.subject) document.getElementById('leadMessageSubject').value = tpl.subject;
        }
    }
    document.getElementById('leadMessageChannels')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-channel]');
        if (!btn || btn.disabled) return;
        state.messageChannel = btn.dataset.channel;
        renderMessageChannels();
    });
    document.getElementById('leadMessageTemplate')?.addEventListener('change', applyMessageTemplate);
    document.getElementById('leadMessageHtmlEditor')?.addEventListener('mousedown', (e) => {
        if (e.target.closest('button')) e.preventDefault();
    });
    document.getElementById('leadMessageHtmlEditor')?.addEventListener('click', (e) => {
        const modeBtn = e.target.closest('[data-html-mode]');
        if (modeBtn) {
            e.preventDefault();
            setMailEditorMode(modeBtn.dataset.htmlMode);
            return;
        }
        const cmdBtn = e.target.closest('[data-cmd]');
        if (!cmdBtn) return;
        e.preventDefault();
        const ed = getMailHtmlEditor();
        if (ed.source && !ed.source.hidden) {
            alert('Switch to Visual to use formatting, or edit the HTML directly.');
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
        if (ed.source) ed.source.value = sanitizeFollowUpHtml(ed.visual?.innerHTML || '');
    });
    document.getElementById('leadMessageHtmlVisual')?.addEventListener('input', () => {
        const ed = getMailHtmlEditor();
        if (ed.source) ed.source.value = sanitizeFollowUpHtml(ed.visual?.innerHTML || '');
    });
    document.getElementById('closeLeadMessageModal')?.addEventListener('click', closeMessageModal);
    document.getElementById('cancelLeadMessageBtn')?.addEventListener('click', closeMessageModal);
    document.getElementById('sendLeadMessageBtn')?.addEventListener('click', async () => {
        const err = document.getElementById('leadMessageError');
        const btn = document.getElementById('sendLeadMessageBtn');
        if (!state.messageChannel) {
            if (err) { err.hidden = false; err.textContent = 'Choose a channel.'; }
            return;
        }
        if (isMailFollowUp() && !document.getElementById('leadMessageMailbox')?.value) {
            if (err) { err.hidden = false; err.textContent = 'Choose a mailbox to send from.'; }
            return;
        }
        if (isMailFollowUp() && getSelectedToEmails().length < 1) {
            if (err) { err.hidden = false; err.textContent = 'Choose at least one recipient.'; }
            return;
        }
        const payload = {
            channel: state.messageChannel,
            template_id: document.getElementById('leadMessageTemplate').value ? Number(document.getElementById('leadMessageTemplate').value) : null,
            body: getFollowUpBody(),
            subject: document.getElementById('leadMessageSubject').value || null,
            inbox_id: isMailFollowUp() && document.getElementById('leadMessageMailbox')?.value
                ? Number(document.getElementById('leadMessageMailbox').value)
                : null,
            to: isMailFollowUp() ? getSelectedToEmails() : null,
        };
        btn.disabled = true;
        try {
            const res = await fetch(api + '/' + state.messageLeadId + '/messages', {
                method: 'POST', credentials: 'same-origin', headers: headers(true),
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not send.');
            closeMessageModal();
            loadLeads();
        } catch (e) {
            if (err) { err.hidden = false; err.textContent = e.message; }
        } finally {
            btn.disabled = false;
        }
    });
    body.addEventListener('click', (e) => {
        const messageBtn = e.target.closest('[data-message]');
        if (messageBtn) {
            openMessageModal(messageBtn.dataset.message);
            return;
        }
        if (e.target.closest('a, button, input, select, textarea')) {
            return;
        }
        const row = e.target.closest('tr[data-id]');
        if (row) openLead(row.dataset.id);
    });
    body.addEventListener('change', async (e) => {
        const select = e.target.closest('.lead-assign');
        if (!select) return;
        const id = select.dataset.id;
        const assignedTo = select.value || null;
        select.disabled = true;
        try {
            const res = await fetch(api + '/' + id + '/assign', {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: headers(true),
                body: JSON.stringify({ assigned_to: assignedTo }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not assign lead.');
            if (data.data) upsertLeadRow(data.data);
            if (String(state.editingId) === String(id) && data.data) {
                fillAssigneeSelect(document.getElementById('leadAssignedTo'), data.data.assigned_to, data.data.assigned_user);
                renderActivities(data.data);
            }
        } catch (err) {
            alert(err.message);
            loadLeads();
        } finally {
            select.disabled = false;
        }
    });

    function collectLeadFormPayload() {
        return {
            title: val('leadTitle') || null,
            first_name: val('leadFirstName'),
            last_name: val('leadLastName'),
            name: [val('leadFirstName'), val('leadLastName')].filter(Boolean).join(' '),
            address: val('leadAddress'),
            city: val('leadCity'),
            postal_code: val('leadPostal') || null,
            primary_phones: readContactRows('primaryPhonesList'),
            primary_emails: readContactRows('primaryEmailsList'),
            phone: (readContactRows('primaryPhonesList')[0] || {}).value || null,
            email: (readContactRows('primaryEmailsList')[0] || {}).value || null,
            company_name: val('leadCompany') || null,
            date_of_birth: val('leadDob') || null,
            alt_title: val('leadAltTitle') || null,
            alt_first_name: val('leadAltFirstName') || null,
            alt_last_name: val('leadAltLastName') || null,
            alt_address: val('leadAltAddress') || null,
            alt_city: val('leadAltCity') || null,
            alt_postal_code: val('leadAltPostal') || null,
            alt_phones: readContactRows('altPhonesList'),
            alt_emails: readContactRows('altEmailsList'),
            alt_phone: (readContactRows('altPhonesList')[0] || {}).value || null,
            alt_email: (readContactRows('altEmailsList')[0] || {}).value || null,
            status: document.getElementById('leadStatus').value,
            source: val('leadSource') || null,
            customer_type: selectedCustomerType() || null,
            residential_type: val('leadResidentialType') || null,
            business_industry: val('leadBusinessIndustry') || null,
            business_industry_other: val('leadBusinessIndustryOther') || null,
            storage_reason: val('leadStorageReason') || null,
            storage_reason_other: val('leadStorageReasonOther') || null,
            storeganise_site_id: document.getElementById('leadStoreganiseSite')?.value || null,
            assigned_to: document.getElementById('leadAssignedTo').value || null,
            facebook_name: document.getElementById('leadFacebook').value.trim() || null,
            instagram_username: document.getElementById('leadInstagram').value.trim() || null,
            inbox_conversation_ids: state.pendingInboxConversations.map(c => c.id),
        };
    }

    async function persistLeadForm(options = {}) {
        const { reloadList = true, useOverlay = true } = options;
        errorEl.hidden = true;
        if (!form.checkValidity()) {
            const invalid = form.querySelector(':invalid');
            showLeadTabForElement(invalid);
            invalid?.focus();
            form.reportValidity();
            return null;
        }
        const payload = collectLeadFormPayload();
        const id = document.getElementById('leadId').value;
        const saveBtn = document.getElementById('saveLeadBtn');
        const busyLabel = id ? 'Saving…' : 'Adding…';
        if (useOverlay) {
            setBusy(saveBtn, true, busyLabel);
            setOverlay('leadModalBusy', true, busyLabel);
        }
        try {
            const res = await fetch(id ? api + '/' + id : api, {
                method: id ? 'PUT' : 'POST',
                credentials: 'same-origin',
                headers: headers(true),
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const firstError = data.errors ? Object.values(data.errors)[0]?.[0] : null;
                throw new Error(firstError || data.message || 'Could not save lead.');
            }
            upsertLeadRow(data.data);
            if (reloadList) {
                await loadLeads();
            }
            fillForm(data.data);
            if (data.data?.id) {
                const url = new URL(window.location.href);
                url.searchParams.set('lead', data.data.id);
                history.replaceState(null, '', url);
            }
            return data.data;
        } catch (err) {
            errorEl.hidden = false;
            errorEl.textContent = err.message;
            return null;
        } finally {
            if (useOverlay) {
                setOverlay('leadModalBusy', false);
                setBusy(saveBtn, false);
            }
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        await persistLeadForm();
    });

    document.getElementById('deleteLeadBtn').addEventListener('click', async () => {
        const id = document.getElementById('leadId').value;
        if (!id || !confirm('Delete this lead? Channel conversations stay, but this identity will be removed.')) return;
        const deleteBtn = document.getElementById('deleteLeadBtn');
        setBusy(deleteBtn, true, 'Deleting…');
        setOverlay('leadModalBusy', true, 'Deleting…');
        try {
            const res = await fetch(api + '/' + id, { method: 'DELETE', credentials: 'same-origin', headers: headers() });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message || 'Could not delete lead.');
            }
            removeLeadRow(id);
            closeModal();
            await loadLeads();
        } catch (err) {
            alert(err.message);
        } finally {
            setOverlay('leadModalBusy', false);
            setBusy(deleteBtn, false);
        }
    });

    async function addLeadNote() {
        const id = state.editingId;
        const input = document.getElementById('leadNoteInput');
        const text = input.value.trim();
        if (!id) return;
        if (!text) { input.focus(); return; }
        const noteBtn = document.getElementById('addLeadNoteBtn');
        setBusy(noteBtn, true, 'Adding…');
        try {
            const res = await fetch(api + '/' + id + '/notes', {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers(true),
                body: JSON.stringify({ note: text }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not add note.');
            input.value = '';
            renderNotes([data.data, ...state.notes]);
            refreshActivities(id);
            await loadLeads();
        } catch (err) {
            errorEl.hidden = false;
            errorEl.textContent = err.message;
        } finally {
            setBusy(noteBtn, false);
        }
    }

    async function addLeadLabel() {
        const id = state.editingId;
        const select = document.getElementById('leadLabelSelect');
        const name = (select?.value || '').trim();
        const busy = document.getElementById('leadLabelBusy');
        if (!id || !select) return;
        if (!name) return;
        if (state.labels.some(label => label.name.toLowerCase() === name.toLowerCase())) {
            select.value = '';
            return;
        }
        select.disabled = true;
        if (busy) busy.hidden = false;
        try {
            const res = await fetch(api + '/' + id + '/labels', {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers(true),
                body: JSON.stringify({ name }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not add label.');
            select.value = '';
            renderLabels(data.labels || []);
            refreshActivities(id);
            if (data.data && !state.companyLabels.some(label => label.id === data.data.id)) {
                state.companyLabels.push(data.data);
            }
            renderLabelSuggestions();
            await loadLeads();
        } catch (err) {
            errorEl.hidden = false;
            errorEl.textContent = err.message;
            renderLabelSuggestions();
        } finally {
            if (busy) busy.hidden = true;
        }
    }

    document.getElementById('addLeadNoteBtn').addEventListener('click', addLeadNote);
    document.getElementById('leadLabelSelect').addEventListener('change', addLeadLabel);
    document.getElementById('leadNoteInput').addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            addLeadNote();
        }
    });
    document.getElementById('leadNotesList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-remove-note]');
        if (!btn || !state.editingId) return;
        const noteId = btn.dataset.removeNote;
        setBusy(btn, true, 'Deleting…');
        try {
            const res = await fetch(api + '/' + state.editingId + '/notes/' + noteId, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: headers(),
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(data.message || 'Could not delete note.');
            }
            renderNotes(state.notes.filter(note => String(note.id) !== String(noteId)));
            refreshActivities(state.editingId);
            await loadLeads();
        } catch (err) {
            alert(err.message);
            setBusy(btn, false);
        }
    });
    document.getElementById('leadLabelsList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-remove-label]');
        if (!btn || !state.editingId) return;
        const labelId = btn.dataset.removeLabel;
        setBusy(btn, true, 'Deleting…');
        try {
            const res = await fetch(api + '/' + state.editingId + '/labels/' + labelId, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: headers(),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not remove label.');
            renderLabels(data.labels || state.labels.filter(label => String(label.id) !== String(labelId)));
            refreshActivities(state.editingId);
            await loadLeads();
        } catch (err) {
            alert(err.message);
            setBusy(btn, false);
        }
    });

    const RULE_TRIGGERS = [
        { value: 'inbound_message', label: 'Inbound message is received', help: 'Any inbound Inbox, Viber, WhatsApp, Facebook, or SMS message.' },
        { value: 'inbound_message_new', label: 'Inbound message is received (new conversation)', help: 'Only the first inbound message that starts a conversation.' },
        { value: 'outbound_message_new', label: 'Outbound message is sent (new conversation)', help: 'When you start a new Inbox, Viber, WhatsApp, Facebook, or SMS thread.' },
        { value: 'outbound_reply', label: 'Outbound reply is sent', help: 'When a reply is sent on an existing conversation.' },
        { value: 'inbound_call', label: 'Inbound call is received', help: 'When a phone call comes in.' },
        { value: 'outbound_call', label: 'Outbound call is placed', help: 'When an outbound phone call is placed.' },
        { value: 'lead_assigned', label: 'Lead is assigned', help: 'When a teammate is assigned to the lead.' },
        { value: 'lead_labeled', label: 'Label added', help: 'When this label is added to the lead.' },
        { value: 'lead_status_changed', label: 'Status changed', help: 'When the lead status changes to this status. Delayed actions, like set status after X days, start counting from this change date.' },
        { value: 'lead_note_added', label: 'Note is added to lead', help: 'When a note is saved on the lead.' },
        { value: 'follow_up_day_reached', label: 'Follow-up day is reached', help: 'Once a day when the lead reaches this follow-up day. Day 1 is the day after it was created. Use labels like Inquiry or Move in only when you want a rule to depend on a tag, not on the follow-up bucket itself.' },
    ];
    const RULE_CHANNELS = [
        ['phone', 'Phone'],
        ['inbox', 'Inbox'],
        ['viber', 'Viber'],
        ['whatsapp', 'WhatsApp'],
        ['facebook', 'Facebook'],
        ['sms', 'SMS'],
    ];

    const rulesModal = document.getElementById('leadRulesModal');
    function showRuleList() {
        state.editingRuleId = null;
        const listView = document.getElementById('leadRuleListView');
        const builder = document.getElementById('leadRuleBuilder');
        const help = document.getElementById('leadRulesHelp');
        const title = document.getElementById('leadRulesModalTitle');
        const saveBtn = document.getElementById('saveLeadRuleBtn');
        const newBtn = document.getElementById('newLeadRuleBtn');
        const cancelBtn = document.getElementById('cancelLeadRuleBtn');
        if (listView) listView.hidden = false;
        if (builder) builder.hidden = true;
        if (help) help.hidden = false;
        if (title) title.textContent = 'Lead rules';
        if (saveBtn) saveBtn.hidden = true;
        if (newBtn) newBtn.hidden = !state.canManageRules;
        if (cancelBtn) cancelBtn.textContent = 'Close';
        const menu = document.getElementById('leadRuleChannelMenu');
        if (menu) menu.hidden = true;
        const inboxMenu = document.getElementById('leadRuleInboxMenu');
        if (inboxMenu) inboxMenu.hidden = true;
        renderRuleList();
    }
    function showRuleEditor() {
        const listView = document.getElementById('leadRuleListView');
        const builder = document.getElementById('leadRuleBuilder');
        const help = document.getElementById('leadRulesHelp');
        const title = document.getElementById('leadRulesModalTitle');
        const saveBtn = document.getElementById('saveLeadRuleBtn');
        const newBtn = document.getElementById('newLeadRuleBtn');
        const cancelBtn = document.getElementById('cancelLeadRuleBtn');
        if (listView) listView.hidden = true;
        if (builder) builder.hidden = false;
        if (help) help.hidden = true;
        if (title) title.textContent = state.editingRuleId ? 'Edit rule' : 'New rule';
        if (saveBtn) {
            saveBtn.hidden = !state.canManageRules;
            saveBtn.textContent = state.editingRuleId ? 'Save changes' : 'Create rule';
        }
        if (newBtn) newBtn.hidden = true;
        if (cancelBtn) cancelBtn.textContent = 'Back';
        renderRuleChannelPicker();
        renderRuleInboxPicker();
    }
    function openRulesModal() {
        rulesModal.classList.add('open');
        showRuleList();
        renderRuleChannelPicker();
        renderRuleInboxPicker();
    }
    function closeRulesModal() {
        rulesModal.classList.remove('open');
        state.editingRuleId = null;
        const menu = document.getElementById('leadRuleChannelMenu');
        if (menu) menu.hidden = true;
        const inboxMenu = document.getElementById('leadRuleInboxMenu');
        if (inboxMenu) inboxMenu.hidden = true;
    }
    async function loadRules() {
        const q = new URLSearchParams({ page: String(state.rulesPage || 1), per_page: '10' });
        if (state.rulesSearch) q.set('search', state.rulesSearch);
        const res = await fetch(api + '/rules?' + q.toString(), { credentials: 'same-origin', headers: headers() });
        const data = await res.json().catch(() => ({}));
        const pag = data.pagination || {};
        const lastPage = Math.max(1, Number(pag.last_page) || 1);
        const currentPage = Number(pag.current_page) || 1;
        if (currentPage > lastPage && (Number(pag.total) || 0) > 0) {
            state.rulesPage = lastPage;
            return loadRules();
        }
        state.rules = data.data || [];
        state.rulesPage = currentPage;
        state.rulesLastPage = lastPage;
        state.rulesTotal = Number(pag.total) || 0;
        if (data.meta && typeof data.meta.can_manage === 'boolean') state.canManageRules = data.meta.can_manage;
        state.inboxes = Array.isArray(data.meta?.inboxes) ? data.meta.inboxes : [];
        renderRuleInboxPicker();
        if (!document.getElementById('leadRuleListView')?.hidden) showRuleList();
    }
    function renderRuleList() {
        const list = document.getElementById('leadRuleList');
        if (!list) return;
        if (!state.rules.length) {
            list.innerHTML = `<div class="chp-empty">${state.rulesSearch ? 'No rules match this search.' : 'No rules yet.'}</div>`;
        } else {
            list.innerHTML = state.rules.map(rule => `
                <div class="leads-rule-row" title="${esc(rule.name)}">
                    <div class="leads-rule-row-main">
                        <div class="leads-rule-row-name">${esc(rule.name)}</div>
                        <div class="leads-rule-row-meta">${rule.is_active ? 'On' : 'Off'} · Last applied ${rule.last_applied_at ? formatAt(rule.last_applied_at) : 'never'}</div>
                    </div>
                    ${state.canManageRules ? `
                        <div class="leads-rule-row-actions">
                            <button type="button" class="btn btn-secondary btn-sm" data-edit-lead-rule="${rule.id}">Edit</button>
                            <button type="button" class="btn btn-secondary btn-sm" data-toggle-lead-rule="${rule.id}">${rule.is_active ? 'On' : 'Off'}</button>
                            <button type="button" class="btn btn-secondary btn-sm" data-delete-lead-rule="${rule.id}">Delete</button>
                        </div>
                    ` : ''}
                </div>
            `).join('');
        }
        const info = document.getElementById('leadRulesPageInfo');
        const prev = document.getElementById('leadRulesPrev');
        const next = document.getElementById('leadRulesNext');
        if (info) info.textContent = `Showing page ${state.rulesPage || 1} of ${state.rulesLastPage || 1} (${state.rulesTotal || 0} rules)`;
        if (prev) prev.disabled = (state.rulesPage || 1) <= 1;
        if (next) next.disabled = (state.rulesPage || 1) >= (state.rulesLastPage || 1);
    }
    function selectedRuleChannels() {
        return [...document.querySelectorAll('#leadRuleChannelMenu input[type="checkbox"]:checked')]
            .map(cb => cb.value)
            .filter(Boolean);
    }
    function updateRuleChannelLabel() {
        const ids = selectedRuleChannels();
        const label = document.getElementById('leadRuleChannelToggleLabel');
        if (!label) return;
        if (!ids.length) {
            label.textContent = 'All channels';
            return;
        }
        const names = RULE_CHANNELS.filter(([id]) => ids.includes(id)).map(([, name]) => name);
        label.textContent = names.length <= 2 ? names.join(', ') : `${names.length} channels selected`;
    }
    function renderRuleChannelPicker() {
        const menu = document.getElementById('leadRuleChannelMenu');
        if (!menu) return;
        const prev = new Set(selectedRuleChannels());
        menu.innerHTML = RULE_CHANNELS.map(([id, name]) => `
            <label class="leads-rule-channel-option">
                <input type="checkbox" value="${id}" ${prev.has(id) ? 'checked' : ''}>
                <span>${esc(name)}</span>
            </label>
        `).join('');
        updateRuleChannelLabel();
    }
    function selectedRuleInboxes() {
        return [...document.querySelectorAll('#leadRuleInboxMenu input[type="checkbox"]:checked')]
            .map(cb => Number(cb.value))
            .filter(id => id > 0);
    }
    function inboxDisplayName(inbox) {
        return inbox?.name || inbox?.email || ('Inbox #' + inbox?.id);
    }
    function updateRuleInboxLabel() {
        const ids = selectedRuleInboxes().map(String);
        const label = document.getElementById('leadRuleInboxToggleLabel');
        if (!label) return;
        if (!ids.length) {
            label.textContent = 'All shared inboxes';
            return;
        }
        const names = (state.inboxes || []).filter(inbox => ids.includes(String(inbox.id))).map(inboxDisplayName);
        label.textContent = names.length <= 2 ? names.join(', ') : `${names.length} inboxes selected`;
    }
    function renderRuleInboxPicker() {
        const menu = document.getElementById('leadRuleInboxMenu');
        if (!menu) return;
        const prev = new Set(selectedRuleInboxes().map(String));
        const inboxes = state.inboxes || [];
        menu.innerHTML = inboxes.length
            ? inboxes.map(inbox => `
                <label class="leads-rule-channel-option">
                    <input type="checkbox" value="${inbox.id}" ${prev.has(String(inbox.id)) ? 'checked' : ''}>
                    <span>${esc(inboxDisplayName(inbox))}</span>
                </label>
            `).join('')
            : '<div class="chp-empty" style="padding:0.45rem;">No shared inboxes yet.</div>';
        updateRuleInboxLabel();
    }
    function triggerOptions(selected = 'inbound_message') {
        return RULE_TRIGGERS.map(t =>
            `<option value="${t.value}" ${t.value === selected ? 'selected' : ''}>${esc(t.label)}</option>`
        ).join('');
    }
    function triggerHelp(value) {
        return RULE_TRIGGERS.find(t => t.value === value)?.help || '';
    }
    function conditionFieldOptions(selected = 'contact_name') {
        const fields = [
            ['contact_name', 'Contact name'],
            ['phone', 'Phone'],
            ['email', 'Email'],
            ['subject', 'Subject'],
            ['message', 'Message'],
            ['lead_status', 'Lead status'],
            ['status_changed', 'Status changed'],
            ['lead_label', 'Lead label'],
            ['label_added', 'Label added'],
        ];
        return fields.map(([value, label]) =>
            `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`
        ).join('');
    }
    function conditionOperatorOptions(field, selected = '') {
        if (field === 'lead_label') {
            const current = selected === 'does_not_have' || selected === 'not_equals' ? 'does_not_have' : 'equals';
            return [['equals', 'has'], ['does_not_have', "doesn't have"]]
                .map(([value, label]) => `<option value="${value}" ${current === value ? 'selected' : ''}>${label}</option>`)
                .join('');
        }
        return [['contains', 'contains'], ['equals', 'equals'], ['starts_with', 'starts with']]
            .map(([value, label]) => `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`)
            .join('');
    }
    function conditionValueControl(field, selected = '') {
        if (field === 'lead_status' || field === 'status_changed') {
            return `<select data-rule-cond-value>${statusOptions(selected)}</select>`;
        }
        if (field === 'lead_label' || field === 'label_added') {
            return `<select data-rule-cond-value>${(state.companyLabels || []).map(l =>
                `<option value="${field === 'label_added' ? l.id : esc(l.name)}" ${String(selected) === String(l.id) || String(selected) === String(l.name) ? 'selected' : ''}>${esc(l.name)}</option>`
            ).join('') || '<option value="">No labels</option>'}</select>`;
        }
        return `<input type="text" data-rule-cond-value placeholder="Value" value="${esc(selected || '')}">`;
    }
    function actionNeedsValue(type) {
        return !['create_lead', 'notify_assignee', 'unsnooze'].includes(type);
    }
    function actionTypeOptions(selected = 'assign') {
        return [
            ['create_lead', 'Create a lead'],
            ['assign', 'Assign lead to'],
            ['add_label', 'Add label'],
            ['set_status', 'Set status'],
            ['set_status_after_days', 'Set status after days'],
            ['reopen_after_days', 'Reopen after days'],
            ['unsnooze', 'Unsnooze lead'],
            ['notify_assignee', 'Notify assignee'],
        ].map(([value, label]) =>
            `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`
        ).join('');
    }
    function actionValueOptions(type, selected = '') {
        if (type === 'assign') {
            const current = String(selected || '');
            const users = (state.assignees || []).map(m =>
                `<option value="${m.id}" ${current === String(m.id) ? 'selected' : ''}>${esc(m.name)}</option>`
            ).join('');
            return `
                <optgroup label="All teammates">
                    <option value="__round_robin__" ${current === '__round_robin__' ? 'selected' : ''}>Round robin (all teammates)</option>
                    <option value="__round_robin_selected__" ${current === '__round_robin_selected__' ? 'selected' : ''}>Round robin among selected teammates</option>
                </optgroup>
                <optgroup label="Available for inbound calls">
                    <option value="__available_round_robin__" ${current === '__available_round_robin__' ? 'selected' : ''}>Round robin among available agents</option>
                    <option value="__available__" ${current === '__available__' ? 'selected' : ''}>Any available agent</option>
                </optgroup>
                <optgroup label="Teammate">${users || '<option value="" disabled>No teammates</option>'}</optgroup>
            `;
        }
        if (type === 'add_label') {
            return (state.companyLabels || []).map(l =>
                `<option value="${l.id}" ${String(selected) === String(l.id) || String(selected) === String(l.name) ? 'selected' : ''}>${esc(l.name)}</option>`
            ).join('') || '<option value="">No labels</option>';
        }
        if (type === 'set_status') {
            const selectedStatus = selected || 'contacted';
            return (state.statuses || []).filter(s => s.slug !== 'snoozed').map(s =>
                `<option value="${esc(s.slug)}" ${selectedStatus === s.slug ? 'selected' : ''}>${esc(s.name)}</option>`
            ).join('');
        }
        if (type === 'reopen_after_days' || type === 'set_status_after_days') {
            const days = [1, 2, 3, 5, 7, 14, 30, 60, 90];
            const selectedDay = String(selected || '3');
            const opts = days.map(d =>
                `<option value="${d}" ${selectedDay === String(d) ? 'selected' : ''}>${d} day${d === 1 ? '' : 's'}</option>`
            ).join('');
            return opts + (days.includes(Number(selectedDay)) ? '' : `<option value="${esc(selectedDay)}" selected>${esc(selectedDay)} days</option>`);
        }
        return '<option value="">—</option>';
    }
    function triggerLabelOptions(selected = '') {
        const labels = state.companyLabels || [];
        const opts = labels.map(l =>
            `<option value="${l.id}" ${String(selected) === String(l.id) || String(selected) === String(l.name) ? 'selected' : ''}>${esc(l.name)}</option>`
        ).join('');
        return `<option value="">Select label…</option>` + (opts || '<option value="" disabled>No labels yet</option>');
    }
    function triggerStatusOptions(selected = '') {
        return `<option value="">Select status…</option>` + (state.statuses || []).map(s =>
            `<option value="${esc(s.slug)}" ${selected === s.slug ? 'selected' : ''}>${esc(s.name)}</option>`
        ).join('');
    }
    function triggerFollowUpDayOptions(selected = '') {
        const days = state.followUpDays.length ? state.followUpDays : [4, 10, 30, 90];
        const current = String(selected || days[0] || '4');
        const opts = days.map(d => `<option value="${d}" ${current === String(d) ? 'selected' : ''}>${esc(followUpLabelName(d))}</option>`).join('');
        return opts + (days.includes(Number(current)) ? '' : `<option value="${esc(current)}" selected>${esc(followUpLabelName(current))}</option>`);
    }
    function triggerExtraKind(type) {
        if (type === 'lead_labeled') return 'label';
        if (type === 'lead_status_changed') return 'status';
        if (type === 'follow_up_day_reached') return 'day';
        return '';
    }
    function triggerExtraOptions(type, selected = '') {
        if (type === 'lead_labeled') return triggerLabelOptions(selected);
        if (type === 'lead_status_changed') return triggerStatusOptions(selected);
        if (type === 'follow_up_day_reached') return triggerFollowUpDayOptions(selected);
        return '<option value="">—</option>';
    }
    function syncTriggerLabelSelect(row) {
        const type = row?.querySelector('[data-rule-trigger]')?.value;
        const fields = row?.querySelector('.leads-rule-trigger-fields');
        const extraSel = row?.querySelector('[data-rule-trigger-label]');
        if (!extraSel) return;
        const kind = triggerExtraKind(type);
        extraSel.hidden = !kind;
        extraSel.disabled = !kind;
        fields?.classList.toggle('has-label', !!kind);
        if (kind) extraSel.innerHTML = triggerExtraOptions(type, extraSel.value);
    }
    function addRuleTriggerRow(preset = {}) {
        const wrap = document.getElementById('leadRuleTriggers');
        if (!wrap) return;
        const value = preset.value || 'inbound_message';
        const kind = triggerExtraKind(value);
        const row = document.createElement('div');
        row.className = 'leads-rule-extra-card is-trigger';
        row.innerHTML = `
            <div>
                <div class="leads-rule-trigger-fields${kind ? ' has-label' : ''}">
                    <select data-rule-trigger>${triggerOptions(value)}</select>
                    <select data-rule-trigger-label ${kind ? '' : 'hidden disabled'}>${triggerExtraOptions(value, preset.label || preset.status || preset.day || '')}</select>
                </div>
                <p class="leads-rule-trigger-help">${esc(triggerHelp(value))}</p>
            </div>
            <button type="button" class="leads-rule-remove" data-remove-rule-row title="Remove">×</button>
        `;
        wrap.appendChild(row);
    }
    function addRuleConditionRow(preset = {}) {
        const wrap = document.getElementById('leadRuleConditions');
        if (!wrap) return;
        const field = preset.field || 'contact_name';
        const row = document.createElement('div');
        row.className = 'leads-rule-extra-card';
        row.innerHTML = `
            <select data-rule-cond-field>${conditionFieldOptions(field)}</select>
            <select data-rule-cond-operator>${conditionOperatorOptions(field, preset.operator || (field === 'lead_label' ? 'equals' : 'contains'))}</select>
            ${conditionValueControl(field, preset.value || '')}
            <button type="button" class="leads-rule-remove" data-remove-rule-row title="Remove">×</button>
        `;
        wrap.appendChild(row);
    }
    function assignMode(value) {
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            return String(value.mode || '');
        }
        return String(value || '');
    }
    function delayedStatusDays(value) {
        if (value && typeof value === 'object' && !Array.isArray(value) && value.days != null && value.days !== '') {
            return String(value.days);
        }
        if (value != null && value !== '' && typeof value !== 'object') {
            return String(value);
        }
        return '3';
    }
    function delayedStatusSlug(value) {
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            return String(value.status || 'contacted');
        }
        return 'contacted';
    }
    function delayedStatusSelectHtml(selected = 'contacted') {
        return `<select data-rule-action-status>${actionValueOptions('set_status', selected || 'contacted')}</select>`;
    }
    function selectedRoundRobinIds(value) {
        if (value && typeof value === 'object' && Array.isArray(value.user_ids)) {
            return value.user_ids.map(String);
        }
        return [];
    }
    function roundRobinUsersHtml(selectedIds = []) {
        const chosen = new Set((selectedIds || []).map(String));
        const users = state.assignees || [];
        if (!users.length) {
            return '<p class="leads-rule-rr-help">No teammates to select.</p>';
        }
        return `
            <div class="leads-rule-rr-users" data-rr-users>
                ${users.map(m => `
                    <label>
                        <input type="checkbox" value="${m.id}" ${chosen.has(String(m.id)) ? 'checked' : ''}>
                        <span>${esc(m.name)}</span>
                    </label>
                `).join('')}
            </div>
            <p class="leads-rule-rr-help">Leads rotate through the teammates you check, whether or not they are available for inbound calls.</p>
        `;
    }
    function syncAssignTeammatePicker(row, preset = {}) {
        if (!row) return;
        row.querySelector('[data-rr-users]')?.remove();
        row.querySelector('.leads-rule-rr-help')?.remove();
        const type = row.querySelector('[data-rule-action-type]')?.value;
        const mode = row.querySelector('[data-rule-action-value]')?.value;
        const selected = type === 'assign' && mode === '__round_robin_selected__';
        row.classList.toggle('is-rr-selected', selected);
        if (selected) {
            row.insertAdjacentHTML('beforeend', roundRobinUsersHtml(selectedRoundRobinIds(preset.value)));
        }
    }
    function actionKeywordValues(preset = {}) {
        const value = (preset.value && typeof preset.value === 'object' && !Array.isArray(preset.value))
            ? preset.value
            : {};
        return {
            name: value.name || value.name_keyword || 'Name',
            phone: value.phone || value.phone_keyword || 'Phone',
            email: value.email || value.email_keyword || 'Email',
        };
    }
    function createLeadKeywordsHtml(preset = {}) {
        const kw = actionKeywordValues(preset);
        return `
            <div class="leads-rule-create-keywords" data-create-lead-keywords>
                <label>Name keyword<input type="text" data-lead-keyword="name" placeholder="Name" maxlength="80" value="${esc(kw.name)}"></label>
                <label>Phone keyword<input type="text" data-lead-keyword="phone" placeholder="Phone" maxlength="80" value="${esc(kw.phone)}"></label>
                <label>Email keyword<input type="text" data-lead-keyword="email" placeholder="Email" maxlength="80" value="${esc(kw.email)}"></label>
            </div>
            <p class="leads-rule-create-help">Read these labels from the message or email body, e.g. Name: Jane Doe. Comma-separate aliases like Full name, Name.</p>
        `;
    }
    function syncActionRow(row, type, preset = {}) {
        if (!row) return;
        row.classList.toggle('is-create-lead', type === 'create_lead');
        row.classList.toggle('is-delayed-status', type === 'set_status_after_days');
        const valueSel = row.querySelector('[data-rule-action-value]');
        const needsValue = actionNeedsValue(type);
        if (valueSel) {
            valueSel.hidden = type === 'create_lead';
            valueSel.disabled = !needsValue;
            if (type !== 'create_lead') {
                let fallback = '';
                if (type === 'set_status') fallback = 'contacted';
                if (type === 'reopen_after_days' || type === 'set_status_after_days') fallback = '3';
                const selected = type === 'set_status_after_days'
                    ? delayedStatusDays(preset.value)
                    : (assignMode(preset.value) || fallback);
                valueSel.innerHTML = actionValueOptions(type, selected || fallback);
            }
        }
        row.querySelector('[data-create-lead-keywords]')?.remove();
        row.querySelector('.leads-rule-create-help')?.remove();
        row.querySelector('[data-rule-action-status]')?.remove();
        row.querySelector('.leads-rule-delayed-help')?.remove();
        if (type === 'create_lead') {
            row.insertAdjacentHTML('beforeend', createLeadKeywordsHtml(preset));
        }
        if (type === 'set_status_after_days') {
            valueSel?.insertAdjacentHTML('afterend', delayedStatusSelectHtml(delayedStatusSlug(preset.value)));
            row.insertAdjacentHTML('beforeend', '<p class="leads-rule-delayed-help">The countdown starts when this rule’s trigger happens, for example the date the status changed to Qualified.</p>');
        }
        syncAssignTeammatePicker(row, preset);
    }
    function addRuleActionRow(preset = {}) {
        const wrap = document.getElementById('leadRuleActions');
        if (!wrap) return;
        const type = preset.type || 'assign';
        const needsValue = actionNeedsValue(type);
        let defaultValue = assignMode(preset.value)
            || ((preset.value && typeof preset.value !== 'object') ? preset.value : '');
        if (!defaultValue && (type === 'reopen_after_days' || type === 'set_status_after_days')) {
            defaultValue = delayedStatusDays(preset.value);
        }
        const row = document.createElement('div');
        row.className = 'leads-rule-extra-card is-action' + (type === 'create_lead' ? ' is-create-lead' : '') + (type === 'set_status_after_days' ? ' is-delayed-status' : '');
        row.innerHTML = `
            <select data-rule-action-type>${actionTypeOptions(type)}</select>
            <select data-rule-action-value ${needsValue ? '' : 'disabled hidden'}>${actionValueOptions(type, defaultValue)}</select>
            <button type="button" class="leads-rule-remove" data-remove-rule-row title="Remove">×</button>
        `;
        wrap.appendChild(row);
        syncActionRow(row, type, preset);
    }
    function resetRuleBuilder() {
        state.editingRuleId = null;
        const name = document.getElementById('leadRuleName');
        const stop = document.getElementById('leadRuleStopProcessing');
        if (name) name.value = '';
        if (stop) stop.checked = false;
        document.getElementById('leadRuleTriggers').innerHTML = '';
        document.getElementById('leadRuleConditions').innerHTML = '';
        document.getElementById('leadRuleActions').innerHTML = '';
        renderRuleChannelPicker();
        renderRuleInboxPicker();
        document.querySelectorAll('#leadRuleChannelMenu input[type="checkbox"]').forEach(cb => { cb.checked = false; });
        document.querySelectorAll('#leadRuleInboxMenu input[type="checkbox"]').forEach(cb => { cb.checked = false; });
        updateRuleChannelLabel();
        updateRuleInboxLabel();
        addRuleTriggerRow({ value: 'inbound_message' });
        addRuleActionRow();
    }
    function fillRuleBuilder(rule) {
        state.editingRuleId = rule.id;
        const name = document.getElementById('leadRuleName');
        const stop = document.getElementById('leadRuleStopProcessing');
        if (name) name.value = rule.name || '';
        if (stop) stop.checked = !!rule.stop_processing;
        document.getElementById('leadRuleTriggers').innerHTML = '';
        document.getElementById('leadRuleConditions').innerHTML = '';
        document.getElementById('leadRuleActions').innerHTML = '';
        const conditions = Array.isArray(rule.conditions) ? rule.conditions : [];
        const channel = conditions.find(c => c.field === 'channel');
        const inboxCond = conditions.find(c => c.field === 'shared_inbox' || c.field === 'inbox');
        const addedLabel = conditions.find(c => c.field === 'label_added');
        const changedStatus = conditions.find(c => c.field === 'status_changed');
        const followUpDay = conditions.find(c => c.field === 'follow_up_day');
        renderRuleChannelPicker();
        renderRuleInboxPicker();
        const selected = new Set((Array.isArray(channel?.value) ? channel.value : []).map(String));
        document.querySelectorAll('#leadRuleChannelMenu input[type="checkbox"]').forEach(cb => {
            cb.checked = selected.has(cb.value);
        });
        updateRuleChannelLabel();
        const selectedInboxes = new Set((Array.isArray(inboxCond?.value) ? inboxCond.value : []).map(String));
        document.querySelectorAll('#leadRuleInboxMenu input[type="checkbox"]').forEach(cb => {
            cb.checked = selectedInboxes.has(cb.value);
        });
        updateRuleInboxLabel();
        const triggers = Array.isArray(rule.triggers) ? rule.triggers : [];
        if (!triggers.length) addRuleTriggerRow({ value: 'inbound_message' });
        else triggers.forEach(trigger => addRuleTriggerRow({
            value: trigger,
            label: trigger === 'lead_labeled' ? (addedLabel?.value || '') : '',
            status: trigger === 'lead_status_changed' ? (changedStatus?.value || '') : '',
            day: trigger === 'follow_up_day_reached' ? (followUpDay?.value || '2') : '',
        }));
        conditions
            .filter(c => c.field && c.field !== 'channel' && c.field !== 'shared_inbox' && c.field !== 'inbox' && c.field !== 'label_added' && c.field !== 'status_changed' && c.field !== 'follow_up_day')
            .forEach(c => addRuleConditionRow(c));
        const actions = Array.isArray(rule.actions) ? rule.actions : [];
        if (!actions.length) addRuleActionRow();
        else actions.forEach(action => addRuleActionRow(action));
    }
    function collectRulePayload() {
        const triggers = [];
        document.querySelectorAll('#leadRuleTriggers [data-rule-trigger]').forEach(sel => {
            if (sel.value && !triggers.includes(sel.value)) triggers.push(sel.value);
        });
        const conditions = [
            { field: 'channel', operator: 'in', value: selectedRuleChannels() },
            { field: 'shared_inbox', operator: 'in', value: selectedRuleInboxes() },
        ];
        document.querySelectorAll('#leadRuleTriggers .leads-rule-extra-card').forEach(row => {
            const sel = row.querySelector('[data-rule-trigger]');
            const extraVal = row.querySelector('[data-rule-trigger-label]')?.value;
            if (sel?.value === 'lead_labeled' && extraVal) {
                conditions.push({ field: 'label_added', operator: 'equals', value: extraVal });
            }
            if (sel?.value === 'lead_status_changed' && extraVal) {
                conditions.push({ field: 'status_changed', operator: 'equals', value: extraVal });
            }
            if (sel?.value === 'follow_up_day_reached' && extraVal) {
                conditions.push({ field: 'follow_up_day', operator: 'equals', value: extraVal });
            }
        });
        document.querySelectorAll('#leadRuleConditions .leads-rule-extra-card').forEach(row => {
            const field = row.querySelector('[data-rule-cond-field]')?.value;
            const operator = row.querySelector('[data-rule-cond-operator]')?.value;
            const value = row.querySelector('[data-rule-cond-value]')?.value?.trim() || '';
            if (field && operator) conditions.push({ field, operator, value });
        });
        const actions = [];
        document.querySelectorAll('#leadRuleActions .leads-rule-extra-card').forEach(row => {
            const type = row.querySelector('[data-rule-action-type]')?.value;
            if (!type) return;
            if (type === 'create_lead') {
                actions.push({
                    type,
                    value: {
                        name: row.querySelector('[data-lead-keyword="name"]')?.value.trim() || '',
                        phone: row.querySelector('[data-lead-keyword="phone"]')?.value.trim() || '',
                        email: row.querySelector('[data-lead-keyword="email"]')?.value.trim() || '',
                    },
                });
                return;
            }
            const valueSel = row.querySelector('[data-rule-action-value]');
            if (type === 'set_status_after_days') {
                actions.push({
                    type,
                    value: {
                        days: Number(valueSel?.value || 0),
                        status: row.querySelector('[data-rule-action-status]')?.value || '',
                    },
                });
                return;
            }
            if (type === 'assign' && valueSel?.value === '__round_robin_selected__') {
                const userIds = [...row.querySelectorAll('[data-rr-users] input:checked')]
                    .map(cb => Number(cb.value))
                    .filter(id => id > 0);
                actions.push({ type, value: { mode: '__round_robin_selected__', user_ids: userIds } });
                return;
            }
            actions.push({ type, value: valueSel && !valueSel.disabled ? (valueSel.value || null) : null });
        });
        return {
            name: document.getElementById('leadRuleName')?.value.trim() || '',
            stop_processing: !!document.getElementById('leadRuleStopProcessing')?.checked,
            triggers,
            conditions,
            actions,
        };
    }

    document.getElementById('leadLabelsBtn')?.addEventListener('click', openLabelsModal);
    document.getElementById('leadStatusesBtn')?.addEventListener('click', openStatusesModal);
    document.getElementById('closeLeadStatusesModal')?.addEventListener('click', closeStatusesModal);
    document.getElementById('closeLeadStatusesBtn')?.addEventListener('click', closeStatusesModal);
    document.getElementById('leadCompanyStatusForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const nameEl = document.getElementById('leadCompanyStatusName');
        const name = nameEl?.value.trim() || '';
        if (!name) { nameEl?.focus(); return; }
        const btn = document.getElementById('saveLeadCompanyStatusBtn');
        btn.disabled = true;
        try {
            const res = await fetch(api + '/statuses', {
                method: 'POST', credentials: 'same-origin', headers: headers(true),
                body: JSON.stringify({ name }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not create status.');
            if (nameEl) nameEl.value = '';
            await loadCompanyStatuses();
        } catch (err) {
            alert(err.message);
        } finally {
            btn.disabled = false;
        }
    });
    document.getElementById('leadCompanyStatusList')?.addEventListener('click', async (e) => {
        const save = e.target.closest('[data-save-company-status]');
        if (save) {
            const id = save.dataset.saveCompanyStatus;
            const input = document.querySelector(`[data-status-name="${id}"]`);
            const name = input?.value.trim() || '';
            if (!name) { input?.focus(); return; }
            save.disabled = true;
            try {
                const res = await fetch(api + '/statuses/' + id, {
                    method: 'PATCH', credentials: 'same-origin', headers: headers(true),
                    body: JSON.stringify({ name }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || 'Could not update status.');
                await loadCompanyStatuses();
                loadLeads();
            } catch (err) {
                alert(err.message);
            } finally {
                save.disabled = false;
            }
            return;
        }
        const del = e.target.closest('[data-delete-company-status]');
        if (!del) return;
        if (!confirm('Delete this status? Leads using it will move to the default status.')) return;
        const res = await fetch(api + '/statuses/' + del.dataset.deleteCompanyStatus, {
            method: 'DELETE', credentials: 'same-origin', headers: headers(),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            alert(data.message || 'Could not delete status.');
            return;
        }
        await loadCompanyStatuses();
        loadLeads();
    });
    document.getElementById('closeLeadLabelsModal')?.addEventListener('click', closeLabelsModal);
    document.getElementById('closeLeadLabelsBtn')?.addEventListener('click', closeLabelsModal);
    document.getElementById('leadCompanyLabelForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const nameEl = document.getElementById('leadCompanyLabelName');
        const colorEl = document.getElementById('leadCompanyLabelColor');
        const name = nameEl?.value.trim() || '';
        if (!name) { nameEl?.focus(); return; }
        const btn = document.getElementById('saveLeadCompanyLabelBtn');
        btn.disabled = true;
        try {
            const res = await fetch(api + '/labels', {
                method: 'POST', credentials: 'same-origin', headers: headers(true),
                body: JSON.stringify({ name, color: colorEl?.value || '#4338ca' }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not create label.');
            if (nameEl) nameEl.value = '';
            await loadCompanyLabels();
        } catch (err) {
            alert(err.message);
        } finally {
            btn.disabled = false;
        }
    });
    document.getElementById('leadCompanyLabelList')?.addEventListener('click', async (e) => {
        const save = e.target.closest('[data-save-company-label]');
        if (save) {
            await saveCompanyLabel(save.dataset.saveCompanyLabel);
            return;
        }
        const del = e.target.closest('[data-delete-company-label]');
        if (!del) return;
        if (!confirm('Delete this label? It will be removed from all leads.')) return;
        const res = await fetch(api + '/labels/' + del.dataset.deleteCompanyLabel, {
            method: 'DELETE', credentials: 'same-origin', headers: headers(),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) return alert(data.message || 'Could not delete label.');
        state.labelIds = state.labelIds.filter(id => id !== String(del.dataset.deleteCompanyLabel));
        await loadCompanyLabels();
        loadFollowUpCounts();
        loadLeads();
    });
    document.getElementById('leadCompanyLabelList')?.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        const input = e.target.closest('[data-label-name]');
        if (!input) return;
        e.preventDefault();
        saveCompanyLabel(input.dataset.labelName);
    });
    document.getElementById('leadCompanyLabelList')?.addEventListener('change', async (e) => {
        const color = e.target.closest('[data-label-color]');
        if (!color) return;
        const res = await fetch(api + '/labels/' + color.dataset.labelColor, {
            method: 'PATCH', credentials: 'same-origin', headers: headers(true),
            body: JSON.stringify({ color: color.value }),
        });
        if (!res.ok) return alert('Could not update label color.');
        await loadCompanyLabels();
        loadLeads();
    });
    document.getElementById('leadRulesBtn')?.addEventListener('click', () => {
        state.rulesPage = 1;
        state.rulesSearch = '';
        const search = document.getElementById('leadRuleSearch');
        if (search) search.value = '';
        loadRules().catch(() => {});
        openRulesModal();
    });
    document.getElementById('leadRulesPrev')?.addEventListener('click', () => {
        if (state.rulesPage > 1) {
            state.rulesPage -= 1;
            loadRules().catch(() => {});
        }
    });
    document.getElementById('leadRulesNext')?.addEventListener('click', () => {
        if (state.rulesPage < state.rulesLastPage) {
            state.rulesPage += 1;
            loadRules().catch(() => {});
        }
    });
    let ruleSearchTimer;
    document.getElementById('leadRuleSearch')?.addEventListener('input', (e) => {
        clearTimeout(ruleSearchTimer);
        ruleSearchTimer = setTimeout(() => {
            state.rulesSearch = e.target.value.trim();
            state.rulesPage = 1;
            loadRules().catch(() => {});
        }, 250);
    });
    document.getElementById('closeLeadRulesModal')?.addEventListener('click', closeRulesModal);
    document.getElementById('cancelLeadRuleBtn')?.addEventListener('click', () => {
        if (document.getElementById('leadRuleBuilder')?.hidden) closeRulesModal();
        else showRuleList();
    });
    document.getElementById('newLeadRuleBtn')?.addEventListener('click', () => {
        if (!state.canManageRules) return alert('You do not have permission to add rules.');
        resetRuleBuilder();
        showRuleEditor();
    });
    document.getElementById('btnAddLeadRuleTrigger')?.addEventListener('click', () => {
        const used = new Set([...document.querySelectorAll('#leadRuleTriggers [data-rule-trigger]')].map(s => s.value));
        const next = RULE_TRIGGERS.find(t => !used.has(t.value));
        addRuleTriggerRow({ value: next?.value || 'inbound_message' });
    });
    document.getElementById('btnAddLeadRuleCondition')?.addEventListener('click', () => addRuleConditionRow());
    document.getElementById('btnAddLeadRuleAction')?.addEventListener('click', () => addRuleActionRow());
    document.getElementById('leadRuleChannelToggle')?.addEventListener('click', (e) => {
        e.preventDefault();
        const menu = document.getElementById('leadRuleChannelMenu');
        const inboxMenu = document.getElementById('leadRuleInboxMenu');
        if (inboxMenu) inboxMenu.hidden = true;
        if (menu) menu.hidden = !menu.hidden;
    });
    document.getElementById('leadRuleChannelMenu')?.addEventListener('change', updateRuleChannelLabel);
    document.getElementById('leadRuleInboxToggle')?.addEventListener('click', (e) => {
        e.preventDefault();
        const menu = document.getElementById('leadRuleInboxMenu');
        const channelMenu = document.getElementById('leadRuleChannelMenu');
        if (channelMenu) channelMenu.hidden = true;
        if (menu) menu.hidden = !menu.hidden;
    });
    document.getElementById('leadRuleInboxMenu')?.addEventListener('change', updateRuleInboxLabel);
    document.getElementById('leadRuleTriggers')?.addEventListener('change', (e) => {
        const sel = e.target.closest('[data-rule-trigger]');
        if (!sel) return;
        const row = sel.closest('.leads-rule-extra-card');
        const help = row?.querySelector('.leads-rule-trigger-help');
        if (help) help.textContent = triggerHelp(sel.value);
        syncTriggerLabelSelect(row);
    });
    document.getElementById('leadRuleConditions')?.addEventListener('change', (e) => {
        const fieldSel = e.target.closest('[data-rule-cond-field]');
        if (!fieldSel) return;
        const row = fieldSel.closest('.leads-rule-extra-card');
        const current = row.querySelector('[data-rule-cond-value]');
        const wrap = document.createElement('div');
        wrap.innerHTML = conditionValueControl(fieldSel.value, '');
        current?.replaceWith(wrap.firstElementChild);
        const op = row.querySelector('[data-rule-cond-operator]');
        const opWrap = document.createElement('div');
        opWrap.innerHTML = `<select data-rule-cond-operator>${conditionOperatorOptions(fieldSel.value, '')}</select>`;
        op?.replaceWith(opWrap.firstElementChild);
    });
    document.getElementById('leadRuleActions')?.addEventListener('change', (e) => {
        const typeSel = e.target.closest('[data-rule-action-type]');
        if (typeSel) {
            const row = typeSel.closest('.leads-rule-extra-card');
            syncActionRow(row, typeSel.value);
            return;
        }
        const valueSel = e.target.closest('[data-rule-action-value]');
        if (!valueSel) return;
        const row = valueSel.closest('.leads-rule-extra-card');
        if (row?.querySelector('[data-rule-action-type]')?.value === 'assign') {
            syncAssignTeammatePicker(row);
        }
    });
    document.getElementById('leadRuleBuilder')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove-rule-row]');
        if (btn) btn.closest('.leads-rule-extra-card')?.remove();
    });
    document.getElementById('leadRuleList')?.addEventListener('click', async (e) => {
        if (!state.canManageRules) return;
        const del = e.target.closest('[data-delete-lead-rule]');
        if (del) {
            if (!confirm('Delete this rule?')) return;
            const res = await fetch(api + '/rules/' + del.dataset.deleteLeadRule, {
                method: 'DELETE', credentials: 'same-origin', headers: headers(),
            });
            if (!res.ok) return alert('Could not delete rule.');
            await loadRules();
            return;
        }
        const toggle = e.target.closest('[data-toggle-lead-rule]');
        if (toggle) {
            const rule = state.rules.find(r => String(r.id) === String(toggle.dataset.toggleLeadRule));
            if (!rule) return;
            const res = await fetch(api + '/rules/' + rule.id, {
                method: 'PATCH', credentials: 'same-origin', headers: headers(true),
                body: JSON.stringify({ is_active: !rule.is_active }),
            });
            if (!res.ok) return alert('Could not update rule.');
            await loadRules();
            return;
        }
        const edit = e.target.closest('[data-edit-lead-rule]');
        if (edit) {
            const rule = state.rules.find(r => String(r.id) === String(edit.dataset.editLeadRule));
            if (!rule) return;
            fillRuleBuilder(rule);
            showRuleEditor();
        }
    });
    document.getElementById('saveLeadRuleBtn')?.addEventListener('click', async () => {
        if (!state.canManageRules) return alert('You do not have permission to add rules.');
        const payload = collectRulePayload();
        if (!payload.name) return alert('Enter a name for this rule.');
        if (!payload.triggers.length) return alert('Add at least one trigger.');
        if (payload.triggers.includes('lead_labeled')) {
            const labeled = payload.conditions.find(c => c.field === 'label_added');
            if (!labeled || !String(labeled.value || '').trim()) {
                return alert('Choose which label was added.');
            }
        }
        if (payload.triggers.includes('lead_status_changed')) {
            const changed = payload.conditions.find(c => c.field === 'status_changed');
            if (!changed || !String(changed.value || '').trim()) {
                return alert('Choose which status was set.');
            }
        }
        if (payload.triggers.includes('follow_up_day_reached')) {
            const day = payload.conditions.find(c => c.field === 'follow_up_day');
            if (!day || !String(day.value || '').trim()) {
                return alert('Choose which follow-up day this rule runs on.');
            }
        }
        const extra = payload.conditions.filter(c => c.field !== 'channel' && c.field !== 'shared_inbox');
        if (extra.some(c => !String(c.value || '').trim())) return alert('Each condition needs a value.');
        if (!payload.actions.length) return alert('Add at least one action.');
        for (const action of payload.actions) {
            if (action.type === 'assign') {
                if (action.value && typeof action.value === 'object' && action.value.mode === '__round_robin_selected__') {
                    if (!Array.isArray(action.value.user_ids) || !action.value.user_ids.length) {
                        return alert('Select teammates for round robin.');
                    }
                    continue;
                }
                if (action.value === null || action.value === '') {
                    return alert('That action needs a value.');
                }
                continue;
            }
            if (['add_label', 'set_status'].includes(action.type) && (action.value === null || action.value === '')) {
                return alert('That action needs a value.');
            }
            if (action.type === 'set_status_after_days') {
                const days = Number(action.value?.days);
                const status = String(action.value?.status || '').trim();
                if (!Number.isFinite(days) || days < 1 || days > 365) {
                    return alert('Choose how many days before the status changes (1–365).');
                }
                if (!status) {
                    return alert('Choose which status to set after the delay.');
                }
            }
            if (action.type === 'reopen_after_days') {
                const days = Number(action.value);
                if (!Number.isFinite(days) || days < 1 || days > 365) {
                    return alert('Choose how many days before reopen (1–365).');
                }
            }
        }
        const btn = document.getElementById('saveLeadRuleBtn');
        btn.disabled = true;
        const editingId = state.editingRuleId;
        try {
            const res = await fetch(editingId ? api + '/rules/' + editingId : api + '/rules', {
                method: editingId ? 'PATCH' : 'POST',
                credentials: 'same-origin',
                headers: headers(true),
                body: JSON.stringify(payload),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || (editingId ? 'Failed to update rule' : 'Failed to create rule'));
            state.editingRuleId = null;
            await loadRules();
            showRuleList();
        } catch (err) {
            alert(err.message);
        } finally {
            btn.disabled = false;
        }
    });

    resetForm();
    Promise.all([loadCompanyLabels(), loadCompanyStatuses(), loadAssignees()]).then(() => {
        applyFollowUpConfig({ days: state.followUpDays, plus_min: state.followUpPlusMin });
        return loadLeads();
    }).then(() => {
        const params = new URLSearchParams(window.location.search);
        const id = params.get('lead');
        const tab = params.get('tab') || undefined;
        if (id) openLead(id, { tab }).catch(() => {});
    });
})();
</script>
@endpush
