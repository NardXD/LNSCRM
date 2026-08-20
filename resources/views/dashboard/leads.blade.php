@extends('layouts.app')

@section('title', 'Leads')

@section('content')
    @php
        $leadFormOptions = $leadFormOptions ?? \App\Models\Lead::formOptions();
    @endphp
    <div class="page-header leads-header">
        <div>
            <h1 class="page-title">Leads</h1>
            <p class="page-subtitle">Store a customer’s phones, emails, and social names so Phone, Inbox, Viber, WhatsApp, Facebook, and SMS share one Contact history.</p>
        </div>
        <div class="leads-header-actions">
            <button type="button" class="btn btn-secondary" id="leadLabelsBtn">Labels</button>
            <button type="button" class="btn btn-secondary" id="leadRulesBtn">Rules</button>
            <button type="button" class="btn btn-primary" id="newLeadBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Lead
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
        <div class="leads-tabs" role="tablist">
            <button type="button" class="leads-tab active" data-status="all">All</button>
            <button type="button" class="leads-tab" data-status="new">New</button>
            <button type="button" class="leads-tab" data-status="contacted">Contacted</button>
            <button type="button" class="leads-tab" data-status="qualified">Qualified</button>
            <button type="button" class="leads-tab" data-status="converted">Converted</button>
            <button type="button" class="leads-tab" data-status="lost">Lost</button>
            <button type="button" class="leads-tab" data-status="snoozed">Snoozed</button>
            <button type="button" class="leads-tab" data-status="archived">Archived</button>
        </div>
    </div>

    <div class="leads-card">
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
            <div class="modal-header">
                <h3 id="leadModalTitle">New Lead</h3>
                <button type="button" class="modal-close-btn" id="closeLeadModal">&times;</button>
            </div>
            <div class="leads-modal-grid">
                <form id="leadForm" class="leads-form" novalidate>
                    <input type="hidden" id="leadId">
                    <div class="lead-form-tabs" role="tablist" aria-label="Lead form sections">
                        <button type="button" class="lead-form-tab active" role="tab" id="leadTabPrimary" data-lead-tab="primary" aria-selected="true">Primary lead info</button>
                        <button type="button" class="lead-form-tab" role="tab" id="leadTabAlternate" data-lead-tab="alternate" aria-selected="false">Alternate lead info</button>
                        <button type="button" class="lead-form-tab" role="tab" id="leadTabSource" data-lead-tab="source" aria-selected="false">How did you hear about us?</button>
                        <button type="button" class="lead-form-tab" role="tab" id="leadTabMatching" data-lead-tab="matching" aria-selected="false">Channel matching</button>
                        <button type="button" class="lead-form-tab" role="tab" id="leadTabNotes" data-lead-tab="notes" aria-selected="false">Labels and notes</button>
                    </div>

                    <section class="lead-form-panel active" data-lead-panel="primary" role="tabpanel" aria-labelledby="leadTabPrimary">
                        <div class="form-row form-row-3">
                            <div class="form-group">
                                <label for="leadTitle">Mr/Ms. *</label>
                                <select id="leadTitle" required>
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
                            <label for="leadAddress">Address *</label>
                            <input type="text" id="leadAddress" required maxlength="500" placeholder="Street address">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="leadCity">City *</label>
                                <input type="text" id="leadCity" required maxlength="255" placeholder="City">
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
                                <select id="leadStatus">
                                    <option value="new">New</option>
                                    <option value="contacted">Contacted</option>
                                    <option value="qualified">Qualified</option>
                                    <option value="converted">Converted</option>
                                    <option value="lost">Lost</option>
                                    <option value="snoozed">Snoozed</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="leadAssignedTo">Assigned</label>
                                <select id="leadAssignedTo">
                                    <option value="">Unassigned</option>
                                </select>
                            </div>
                        </div>
                        <h4>Customer type *</h4>
                        <div class="lead-radio-row">
                            @foreach ($leadFormOptions['customer_types'] as $value => $label)
                                <label class="lead-radio">
                                    <input type="radio" name="leadCustomerType" value="{{ $value }}" required>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="form-group lead-conditional" id="leadResidentialWrap" hidden>
                            <label for="leadResidentialType">Residential type *</label>
                            <select id="leadResidentialType">
                                <option value="">Select type</option>
                                @foreach ($leadFormOptions['residential_types'] as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="lead-conditional" id="leadBusinessWrap" hidden>
                            <div class="form-group">
                                <label for="leadBusinessIndustry">Business industry *</label>
                                <select id="leadBusinessIndustry">
                                    <option value="">Select industry</option>
                                    @foreach ($leadFormOptions['business_industries'] as $industry)
                                        <option value="{{ $industry }}">{{ $industry }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group" id="leadBusinessIndustryOtherWrap" hidden>
                                <label for="leadBusinessIndustryOther">Other industry *</label>
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
                            <label for="leadStorageReasonOther">Other reason *</label>
                            <input type="text" id="leadStorageReasonOther" maxlength="255" placeholder="Enter reason">
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
                    </section>
                    <section class="lead-form-panel" data-lead-panel="notes" role="tabpanel" aria-labelledby="leadTabNotes" hidden>
                    <div id="leadExtras" hidden>
                        <div class="form-group">
                            <label>Labels</label>
                            <div id="leadLabelsList" class="lead-label-list"></div>
                            <div class="lead-label-add">
                                <input type="text" id="leadLabelInput" list="leadLabelSuggestions" maxlength="50" placeholder="Add or create a label" autocomplete="off">
                                <datalist id="leadLabelSuggestions"></datalist>
                                <button type="button" class="btn btn-secondary btn-sm" id="addLeadLabelBtn">Add</button>
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

    <div class="modal-overlay" id="leadLabelsModal">
        <div class="modal-content leads-rules-modal">
            <div class="modal-header">
                <h3>Lead labels</h3>
                <button type="button" class="modal-close-btn" id="closeLeadLabelsModal">&times;</button>
            </div>
            <div class="leads-rules-body">
                <p class="leads-rules-help">Create labels here, then use them on leads, filters, and rules.</p>
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
                    <div id="leadRuleList" class="leads-rule-list"></div>
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
@endsection

@push('styles')
<style>
.leads-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; }
.leads-header-actions { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; }
.leads-toolbar { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem; }
.leads-search { flex: 1; min-width: 220px; padding: 0.55rem 0.85rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; background: var(--bg-card); }
.leads-label-filter { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; min-width: 220px; padding: 0.3rem 0.45rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-card); }
.leads-label-filter select { border: none; background: transparent; font-size: 0.9rem; color: var(--text-primary); min-width: 140px; padding: 0.25rem 0.2rem; }
.leads-source-filter, .leads-assignee-filter { min-width: 160px; padding: 0.5rem 0.7rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.9rem; background: var(--bg-card); color: var(--text-primary); }
.lead-label-filter-chips { display: flex; flex-wrap: wrap; gap: 0.3rem; }
.leads-tabs { display: flex; gap: 0.25rem; flex-wrap: wrap; }
.leads-tab { border: 1px solid var(--border); background: var(--bg-card); color: var(--text-secondary); border-radius: 999px; padding: 0.35rem 0.75rem; font-size: 0.8rem; font-weight: 600; cursor: pointer; }
.leads-tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }
.leads-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.leads-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.leads-table th { text-align: left; padding: 0.7rem 1rem; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); border-bottom: 1px solid var(--border); background: var(--bg-primary); }
.leads-table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); vertical-align: top; }
.leads-table tbody tr { cursor: pointer; }
.leads-table tbody tr:hover { background: var(--bg-primary); }
.lead-name { font-weight: 600; }
.lead-company { font-size: 0.78rem; color: var(--text-secondary); }
.lead-meta { font-size: 0.8rem; color: var(--text-secondary); }
.lead-badge { display: inline-block; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 0.15rem 0.45rem; border-radius: 999px; background: #eef2ff; color: #4338ca; }
.lead-badge.contacted { background: #e0f2fe; color: #0369a1; }
.lead-badge.qualified { background: #dcfce7; color: #166534; }
.lead-badge.converted { background: #d1fae5; color: #065f46; }
.lead-badge.lost { background: #fee2e2; color: #991b1b; }
.lead-badge.snoozed { background: #fef3c7; color: #92400e; }
.lead-badge.archived { background: #e2e8f0; color: #475569; }
.lead-assign { max-width: 160px; font-size: 0.8rem; padding: 0.3rem 0.45rem; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-card); color: var(--text-primary); }
.lead-assign:focus { outline: 2px solid var(--accent); outline-offset: 1px; }
.lead-label-list, .lead-notes-list { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 0.45rem; }
.lead-notes-list { flex-direction: column; flex-wrap: nowrap; }
.lead-label-chip { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.18rem 0.5rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
.lead-label-chip button { background: none; border: none; color: inherit; cursor: pointer; font-size: 0.9rem; line-height: 1; padding: 0; opacity: 0.8; }
.lead-label-add { display: flex; gap: 0.4rem; }
.lead-label-add input { flex: 1; }
.lead-note-item { padding: 0.7rem 0.8rem; background: var(--bg-primary); border-radius: 8px; border-left: 3px solid var(--accent); }
.lead-note-text { font-size: 0.875rem; line-height: 1.45; white-space: pre-wrap; }
.lead-note-meta { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; margin-top: 0.45rem; padding-top: 0.4rem; border-top: 1px solid var(--border); font-size: 0.75rem; color: var(--text-muted); }
.lead-note-empty { font-size: 0.8rem; color: var(--text-secondary); }
.empty-state { text-align: center; color: var(--text-secondary); padding: 2rem !important; }
.leads-pagination { display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1rem; font-size: 0.8rem; color: var(--text-secondary); }
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; }
.modal-overlay.open { display: flex; }
#leadActivityModal { z-index: 1100; }
.leads-modal { background: var(--bg-card); border-radius: 12px; width: min(1080px, 96vw); max-height: 92vh; overflow: hidden; display: flex; flex-direction: column; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); }
.modal-close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
.leads-modal-grid { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(280px, 0.9fr); min-height: 0; overflow: hidden; }
.leads-form { padding: 1.15rem 1.25rem; overflow-y: auto; max-height: calc(92vh - 60px); }
.leads-history { border-left: 1px solid var(--border); padding: 1.15rem 1.1rem; overflow-y: auto; background: var(--bg-primary); max-height: calc(92vh - 60px); }
.leads-history h4 { margin: 0; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); }
.leads-history-sub { margin: 1.25rem 0 0.75rem !important; padding-top: 1rem; border-top: 1px solid var(--border); }
.lead-activity-trigger { display: block; width: 100%; text-align: left; border: 1px solid var(--border); background: var(--bg-card); border-radius: 10px; padding: 0.75rem 0.85rem; cursor: pointer; color: inherit; }
.lead-activity-trigger:hover:not(:disabled) { border-color: var(--accent); }
.lead-activity-trigger:disabled { cursor: default; opacity: 0.85; }
.lead-activity-trigger-head { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; margin-bottom: 0.55rem; }
.lead-activity-trigger-head span { font-size: 0.75rem; font-weight: 700; color: var(--accent); white-space: nowrap; }
.lead-activity-modal { background: var(--bg-card); border-radius: 12px; width: min(560px, 96vw); max-height: 88vh; overflow: hidden; display: flex; flex-direction: column; }
.lead-activity-full { padding: 0.4rem 0.4rem 0.5rem; overflow-y: auto; min-height: 180px; }
.lead-activity-pagination { border-top: 1px solid var(--border); }
.lead-activity-item { display: block; width: 100%; text-align: left; padding: 0.7rem 0.85rem; border: 0; border-bottom: 1px solid var(--border); background: transparent; cursor: pointer; color: inherit; }
.lead-activity-item:hover { background: var(--bg-primary); }
.lead-activity-item.open { background: var(--bg-primary); }
.lead-activity-summary { font-size: 0.82rem; line-height: 1.4; color: var(--text-primary); }
.lead-activity-meta { margin-top: 0.15rem; font-size: 0.72rem; color: var(--text-muted); }
.lead-activity-details { margin-top: 0.45rem; padding-top: 0.4rem; border-top: 1px dashed var(--border); font-size: 0.75rem; line-height: 1.45; color: var(--text-secondary); }
.form-group { margin-bottom: 0.9rem; }
.form-group label { display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.3rem; }
.form-group input, .form-group textarea, .form-group select { width: 100%; padding: 0.5rem 0.7rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; font-family: inherit; }
.lead-form-tabs { display: flex; flex-wrap: wrap; gap: 0.35rem; margin: 0 0 1rem; position: sticky; top: 0; z-index: 3; background: var(--bg-card); padding: 0 0 0.7rem; }
.lead-form-tab { border: 1px solid var(--border); background: var(--bg-primary); color: var(--text-secondary); border-radius: 999px; padding: 0.4rem 0.75rem; font-size: 0.78rem; font-weight: 600; cursor: pointer; }
.lead-form-tab.active { background: var(--accent); border-color: var(--accent); color: #fff; }
.lead-form-panel { display: none; }
.lead-form-panel.active { display: block; }
.lead-form-panel > h4 { margin: 0 0 0.75rem; font-size: 0.74rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--text-secondary); }
.lead-form-panel > h4:not(:first-child) { margin-top: 1.1rem; padding-top: 0.85rem; border-top: 1px solid var(--border); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.form-row-3 { grid-template-columns: 110px 1fr 1fr; }
.lead-radio-row { display: flex; flex-wrap: wrap; gap: 0.55rem; margin-bottom: 0.75rem; }
.lead-radio { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.7rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; cursor: pointer; background: var(--bg-primary); }
.lead-radio:has(input:checked) { border-color: var(--accent); background: color-mix(in srgb, var(--accent) 10%, var(--bg-card)); }
.lead-radio input { width: auto; margin: 0; }
.lead-conditional { margin-top: 0.15rem; }
.identity-label { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem; }
.link-btn { background: none; border: none; color: var(--accent); font-weight: 600; font-size: 0.8rem; cursor: pointer; }
.identity-row { display: grid; grid-template-columns: 1fr auto; gap: 0.4rem; margin-bottom: 0.4rem; }
.identity-row input { width: 100%; padding: 0.45rem 0.6rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; }
.form-hint { margin: 0.35rem 0 0; font-size: 0.75rem; color: var(--text-muted); }
.icon-btn { border: 1px solid var(--border); background: #fff; border-radius: 8px; width: 34px; cursor: pointer; color: #991b1b; }
.modal-actions { display: flex; gap: 0.6rem; align-items: center; margin-top: 0.5rem; }
.form-error { color: #b91c1c; font-size: 0.82rem; margin: 0 0 0.75rem; }
.lh-item, .lh-event { padding: 0.5rem 0; border-bottom: 1px solid var(--border); }
.lh-title { font-weight: 600; font-size: 0.85rem; margin: 0.2rem 0; }
.lh-preview { font-size: 0.78rem; color: var(--text-secondary); }
.lh-link { font-size: 0.78rem; font-weight: 600; color: var(--accent); text-decoration: none; }
.chp-empty { font-size: 0.84rem; color: var(--text-secondary); }
.leads-rules-modal { background: var(--bg-card); border-radius: 12px; width: min(720px, 96vw); max-height: 92vh; overflow: hidden; display: flex; flex-direction: column; }
.leads-rules-body { padding: 1rem 1.25rem; overflow-y: auto; max-height: calc(92vh - 130px); }
.leads-rules-help { margin: 0 0 1rem; font-size: 0.85rem; color: var(--text-secondary); }
.leads-rule-list { display: flex; flex-direction: column; gap: 0.45rem; margin-bottom: 0.25rem; }
.leads-rule-row { display: flex; align-items: flex-start; gap: 0.45rem; padding: 0.65rem 0.7rem; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-primary); }
.leads-rule-row-main { flex: 1; min-width: 0; }
.leads-rule-row-name { font-size: 0.88rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.leads-rule-row-meta { margin-top: 0.2rem; font-size: 0.75rem; color: var(--text-muted); }
.leads-rule-row-actions { display: flex; flex-wrap: wrap; gap: 0.3rem; flex-shrink: 0; }
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
.leads-rule-create-keywords { grid-column: 1 / -1; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.4rem; }
.leads-rule-create-keywords label { display: flex; flex-direction: column; gap: 0.2rem; font-size: 0.72rem; color: var(--text-muted); }
.leads-rule-create-help { grid-column: 1 / -1; margin: 0; font-size: 0.72rem; color: var(--text-muted); }
.leads-rule-extra-card select, .leads-rule-extra-card input { width: 100%; padding: 0.4rem 0.5rem; border: 1px solid var(--border); border-radius: 6px; font-size: 0.82rem; background: var(--bg-card); }
.leads-rule-trigger-help { margin: 0.3rem 0 0; font-size: 0.75rem; color: var(--text-muted); }
.leads-rule-remove { border: none; background: none; color: #b91c1c; font-size: 1.1rem; cursor: pointer; padding: 0.2rem 0.35rem; }
.leads-rule-stop { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; margin: 0.75rem 0 0; }
.leads-rules-actions { padding: 0.85rem 1.25rem 1.1rem; margin: 0; border-top: 1px solid var(--border); }
.leads-label-create { display: flex; gap: 0.45rem; align-items: center; margin-top: 0.85rem; }
.leads-label-create input[type="text"] { flex: 1; min-width: 0; padding: 0.5rem 0.7rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; }
.leads-label-create input[type="color"] { width: 2.4rem; height: 2.2rem; padding: 0; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-card); cursor: pointer; }
.leads-label-row-color { width: 2rem; height: 1.85rem; padding: 0; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-card); cursor: pointer; }
        @media (max-width: 700px) {
            .leads-rule-extra-card, .leads-rule-extra-card.is-action { grid-template-columns: 1fr; }
            .leads-rule-create-keywords { grid-template-columns: 1fr; }
        }
@media (max-width: 860px) {
    .leads-modal-grid { grid-template-columns: 1fr; }
    .leads-history { border-left: 0; border-top: 1px solid var(--border); }
    .form-row, .form-row-3, .identity-row { grid-template-columns: 1fr; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    const api = '/api/leads';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const LEAD_OPTIONS = @json($leadFormOptions);
    const state = { page: 1, status: 'all', search: '', source: '', assignedTo: '', labelIds: [], editingId: null, editingRuleId: null, labels: [], notes: [], companyLabels: [], assignees: [], inboxes: [], activities: [], activityPage: 1, activityLastPage: 1, activityTotal: 0, rules: [], canManageRules: {{ !empty($canManageLeadRules) ? 'true' : 'false' }} };

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
    function statusBadge(lead) {
        const status = lead.status || 'new';
        const label = status === 'snoozed' && lead.reopen_at
            ? 'snoozed until ' + formatDate(lead.reopen_at)
            : status;
        return `<span class="lead-badge ${esc(status)}">${esc(label)}</span>`;
    }
    function chipText(hex) {
        const c = String(hex || '#4338ca').replace('#', '');
        if (c.length !== 6) return '#fff';
        const r = parseInt(c.slice(0, 2), 16), g = parseInt(c.slice(2, 4), 16), b = parseInt(c.slice(4, 6), 16);
        return (r * 299 + g * 587 + b * 114) / 1000 > 160 ? '#111' : '#fff';
    }
    function labelChips(labels) {
        return (labels || []).map(label =>
            `<span class="lead-label-chip" style="background:${esc(label.color || '#4338ca')};color:${chipText(label.color)}">${esc(label.name)}</span>`
        ).join(' ') || '<span class="lead-meta">—</span>';
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
        document.getElementById('leadLabelSuggestions').innerHTML =
            state.companyLabels.map(label => `<option value="${esc(label.name)}"></option>`).join('');
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
            return;
        }
        list.innerHTML = state.labels.map(label => `
            <span class="lead-label-chip" style="background:${esc(label.color || '#4338ca')};color:${chipText(label.color)}">
                ${esc(label.name)}
                <button type="button" data-remove-label="${label.id}" title="Remove label">&times;</button>
            </span>
        `).join('');
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
        } catch {
            state.companyLabels = [];
            renderCompanyLabelList();
        }
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
                    <div class="leads-rule-row-name">
                        <span class="lead-label-chip" style="background:${esc(label.color || '#4338ca')};color:${chipText(label.color)}">${esc(label.name)}</span>
                    </div>
                </div>
                <div class="leads-rule-row-actions">
                    <input type="color" class="leads-label-row-color" data-label-color="${label.id}" value="${esc(label.color || '#4338ca')}" title="Change color" aria-label="Change color">
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

    function addContactRow(listId, value, placeholder, opts = {}) {
        const list = document.getElementById(listId);
        if (!list) return;
        const row = document.createElement('div');
        row.className = 'identity-row';
        const type = opts.type || 'text';
        const required = opts.required ? 'required' : '';
        const max = opts.max || (type === 'email' ? '255' : '50');
        row.innerHTML = `
            <input type="${esc(type)}" class="id-value" value="${esc(value || '')}" placeholder="${esc(placeholder)}" maxlength="${esc(max)}" ${required}>
            <button type="button" class="icon-btn" title="Remove">&times;</button>
        `;
        row.querySelector('.icon-btn').addEventListener('click', () => {
            const keep = opts.keepOne && list.querySelectorAll('.identity-row').length <= 1;
            if (keep) {
                row.querySelector('.id-value').value = '';
                return;
            }
            row.remove();
        });
        list.appendChild(row);
    }
    function fillContactList(listId, items, placeholder, opts = {}) {
        const list = document.getElementById(listId);
        if (!list) return;
        list.innerHTML = '';
        const rows = (Array.isArray(items) ? items : [])
            .map(item => typeof item === 'string' ? item : (item?.value || ''))
            .map(value => String(value || '').trim())
            .filter(Boolean);
        if (!rows.length) {
            addContactRow(listId, '', placeholder, { ...opts, required: !!opts.required });
            return;
        }
        rows.forEach((value, index) => {
            addContactRow(listId, value, placeholder, { ...opts, required: !!opts.required && index === 0 });
        });
    }
    function readContactRows(listId) {
        const list = document.getElementById(listId);
        if (!list) return [];
        return [...list.querySelectorAll('.identity-row')].map(row => ({
            value: row.querySelector('.id-value').value.trim(),
        })).filter(item => item.value);
    }

    async function loadLeads() {
        const q = new URLSearchParams({ page: String(state.page), per_page: '20', status: state.status });
        if (state.search) q.set('search', state.search);
        if (state.source) q.set('source', state.source);
        if (state.assignedTo) q.set('assigned_to', state.assignedTo);
        state.labelIds.forEach(id => q.append('label_ids[]', id));
        const res = await fetch(api + '?' + q.toString(), { credentials: 'same-origin', headers: headers() });
        const data = await res.json();
        const rows = data.data || [];
        body.innerHTML = rows.length ? rows.map(lead => `
            <tr data-id="${lead.id}">
                <td>
                    <div class="lead-name">${esc([lead.title, lead.name].filter(Boolean).join(' '))}</div>
                    <div class="lead-company">${esc(lead.company_name || lead.source || '')}</div>
                </td>
                <td class="lead-meta">${esc((lead.phones || []).map(p => p.value).join(', ') || '—')}</td>
                <td class="lead-meta">${esc((lead.emails || []).map(e => e.value).join(', ') || '—')}</td>
                <td>${labelChips(lead.labels)}</td>
                <td onclick="event.stopPropagation()">
                    <select class="lead-assign" data-id="${lead.id}" aria-label="Assign lead">
                        ${assigneeOptions(lead.assigned_to, lead.assigned_user)}
                    </select>
                </td>
                <td>${statusBadge(lead)}</td>
                <td class="lead-meta">${esc(formatAt(lead.updated_at))}</td>
                <td><button type="button" class="btn btn-secondary btn-sm" data-open="${lead.id}">Open</button></td>
            </tr>
        `).join('') : `<tr><td colspan="8" class="empty-state">${state.search || state.labelIds.length || state.source || state.assignedTo ? 'No leads match this search.' : 'No leads yet. Create one to start matching conversations across channels.'}</td></tr>`;

        const pag = data.pagination || {};
        document.getElementById('leadsPageInfo').textContent = `Showing page ${pag.current_page || 1} of ${pag.last_page || 1} (${pag.total || 0} leads)`;
        document.getElementById('leadsPrev').disabled = (pag.current_page || 1) <= 1;
        document.getElementById('leadsNext').disabled = (pag.current_page || 1) >= (pag.last_page || 1);
        renderSourceFilter(data.sources || []);
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
        const resSelect = document.getElementById('leadResidentialType');
        const bizSelect = document.getElementById('leadBusinessIndustry');
        const bizOtherWrap = document.getElementById('leadBusinessIndustryOtherWrap');
        const bizOther = document.getElementById('leadBusinessIndustryOther');
        const reasonOtherWrap = document.getElementById('leadStorageReasonOtherWrap');
        const reasonOther = document.getElementById('leadStorageReasonOther');
        if (resSelect) resSelect.required = type === 'residential';
        if (bizSelect) bizSelect.required = type === 'business';
        if (bizOtherWrap) bizOtherWrap.hidden = type !== 'business' || industry !== 'Other';
        if (bizOther) bizOther.required = type === 'business' && industry === 'Other';
        if (reasonOtherWrap) reasonOtherWrap.hidden = reason !== 'Other';
        if (reasonOther) reasonOther.required = reason === 'Other';
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
        syncLeadProfileFields();
        errorEl.hidden = true;
        document.getElementById('leadModalTitle').textContent = 'New Lead';
        document.getElementById('deleteLeadBtn').hidden = true;
        document.getElementById('leadHistoryEmpty').hidden = false;
        document.getElementById('leadHistoryEmpty').textContent = 'Save this lead to load Phone, Inbox, Viber, WhatsApp, Facebook, and SMS history.';
        document.getElementById('leadHistoryBody').hidden = true;
        document.getElementById('leadHistoryBody').innerHTML = '';
        document.getElementById('leadNoteInput').value = '';
        document.getElementById('leadLabelInput').value = '';
        fillAssigneeSelect(document.getElementById('leadAssignedTo'), '');
        renderLabels([]);
        renderNotes([]);
        setExtrasVisible(false);
        state.editingId = null;
        renderActivities([]);
    }

    function fillForm(lead) {
        const parsed = splitName(lead.name);
        document.getElementById('leadId').value = lead.id;
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
        document.getElementById('leadStatus').value = lead.status || 'new';
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
        document.getElementById('leadModalTitle').textContent = [lead.title, lead.name].filter(Boolean).join(' ') || 'Lead';
        document.getElementById('deleteLeadBtn').hidden = false;
        state.editingId = lead.id;
        renderLabels(lead.labels || []);
        renderNotes(lead.notes || []);
        renderActivities(lead);
        setExtrasVisible(true);
        loadHistory(lead.id);
        if (activityModal.classList.contains('open')) {
            loadActivityPage(1).catch(() => {});
        }
    }

    async function loadHistory(id) {
        const empty = document.getElementById('leadHistoryEmpty');
        const pane = document.getElementById('leadHistoryBody');
        empty.hidden = false;
        empty.textContent = 'Loading contact history…';
        pane.hidden = true;
        try {
            const res = await fetch(api + '/' + id + '/history', { credentials: 'same-origin', headers: headers() });
            const data = await res.json();
            const threads = data.threads || [];
            const events = (data.events || []).slice(0, 20);
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
            empty.textContent = err.message || 'Could not load contact history.';
        }
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
        history.replaceState(null, '', url);
    }

    async function openLead(id) {
        const res = await fetch(api + '/' + id, { credentials: 'same-origin', headers: headers() });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Lead not found');
        showLeadTab('primary');
        fillForm(data.data);
        openModal();
        const url = new URL(window.location.href);
        url.searchParams.set('lead', id);
        history.replaceState(null, '', url);
    }

    document.getElementById('newLeadBtn').addEventListener('click', () => { resetForm(); openModal(); });
    document.getElementById('closeLeadModal').addEventListener('click', closeModal);
    document.getElementById('cancelLeadBtn').addEventListener('click', closeModal);
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
    document.querySelectorAll('.leads-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.leads-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            state.status = tab.dataset.status;
            state.page = 1;
            loadLeads();
        });
    });
    body.addEventListener('click', (e) => {
        if (e.target.closest('.lead-assign')) return;
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

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorEl.hidden = true;
        if (!form.checkValidity()) {
            const invalid = form.querySelector(':invalid');
            showLeadTabForElement(invalid);
            invalid?.focus();
            form.reportValidity();
            return;
        }
        const payload = {
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
            assigned_to: document.getElementById('leadAssignedTo').value || null,
            facebook_name: document.getElementById('leadFacebook').value.trim() || null,
            instagram_username: document.getElementById('leadInstagram').value.trim() || null,
        };
        const id = document.getElementById('leadId').value;
        document.getElementById('saveLeadBtn').disabled = true;
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
            await loadLeads();
            fillForm(data.data);
            if (data.data?.id) {
                const url = new URL(window.location.href);
                url.searchParams.set('lead', data.data.id);
                history.replaceState(null, '', url);
            }
        } catch (err) {
            errorEl.hidden = false;
            errorEl.textContent = err.message;
        } finally {
            document.getElementById('saveLeadBtn').disabled = false;
        }
    });

    document.getElementById('deleteLeadBtn').addEventListener('click', async () => {
        const id = document.getElementById('leadId').value;
        if (!id || !confirm('Delete this lead? Channel conversations stay, but this identity will be removed.')) return;
        const res = await fetch(api + '/' + id, { method: 'DELETE', credentials: 'same-origin', headers: headers() });
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            alert(data.message || 'Could not delete lead.');
            return;
        }
        closeModal();
        loadLeads();
    });

    async function addLeadNote() {
        const id = state.editingId;
        const input = document.getElementById('leadNoteInput');
        const text = input.value.trim();
        if (!id) return;
        if (!text) { input.focus(); return; }
        document.getElementById('addLeadNoteBtn').disabled = true;
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
            loadLeads();
        } catch (err) {
            errorEl.hidden = false;
            errorEl.textContent = err.message;
        } finally {
            document.getElementById('addLeadNoteBtn').disabled = false;
        }
    }

    async function addLeadLabel() {
        const id = state.editingId;
        const input = document.getElementById('leadLabelInput');
        const name = input.value.trim();
        if (!id) return;
        if (!name) { input.focus(); return; }
        if (state.labels.some(label => label.name.toLowerCase() === name.toLowerCase())) {
            input.value = '';
            return;
        }
        document.getElementById('addLeadLabelBtn').disabled = true;
        try {
            const res = await fetch(api + '/' + id + '/labels', {
                method: 'POST',
                credentials: 'same-origin',
                headers: headers(true),
                body: JSON.stringify({ name }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not add label.');
            input.value = '';
            renderLabels(data.labels || []);
            refreshActivities(id);
            if (data.data && !state.companyLabels.some(label => label.id === data.data.id)) {
                state.companyLabels.push(data.data);
                renderLabelSuggestions();
            }
            loadLeads();
        } catch (err) {
            errorEl.hidden = false;
            errorEl.textContent = err.message;
        } finally {
            document.getElementById('addLeadLabelBtn').disabled = false;
        }
    }

    document.getElementById('addLeadNoteBtn').addEventListener('click', addLeadNote);
    document.getElementById('addLeadLabelBtn').addEventListener('click', addLeadLabel);
    document.getElementById('leadLabelInput').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addLeadLabel();
        }
    });
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
        const res = await fetch(api + '/' + state.editingId + '/notes/' + noteId, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: headers(),
        });
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            alert(data.message || 'Could not delete note.');
            return;
        }
        renderNotes(state.notes.filter(note => String(note.id) !== String(noteId)));
        refreshActivities(state.editingId);
        loadLeads();
    });
    document.getElementById('leadLabelsList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-remove-label]');
        if (!btn || !state.editingId) return;
        const labelId = btn.dataset.removeLabel;
        const res = await fetch(api + '/' + state.editingId + '/labels/' + labelId, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: headers(),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            alert(data.message || 'Could not remove label.');
            return;
        }
        renderLabels(data.labels || state.labels.filter(label => String(label.id) !== String(labelId)));
        refreshActivities(state.editingId);
        loadLeads();
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
        { value: 'lead_status_changed', label: 'Status changed', help: 'When the lead status changes to this status.' },
        { value: 'lead_note_added', label: 'Note is added to lead', help: 'When a note is saved on the lead.' },
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
        const res = await fetch(api + '/rules', { credentials: 'same-origin', headers: headers() });
        const data = await res.json().catch(() => ({}));
        state.rules = data.data || [];
        if (data.meta && typeof data.meta.can_manage === 'boolean') state.canManageRules = data.meta.can_manage;
        state.inboxes = Array.isArray(data.meta?.inboxes) ? data.meta.inboxes : [];
        renderRuleInboxPicker();
        if (!document.getElementById('leadRuleListView')?.hidden) showRuleList();
    }
    function renderRuleList() {
        const list = document.getElementById('leadRuleList');
        if (!list) return;
        if (!state.rules.length) {
            list.innerHTML = '<div class="chp-empty">No rules yet.</div>';
            return;
        }
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
            ['lead_label', 'Lead has label'],
            ['label_added', 'Label added'],
        ];
        return fields.map(([value, label]) =>
            `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`
        ).join('');
    }
    function conditionOperatorOptions(selected = 'contains') {
        return [['contains', 'contains'], ['equals', 'equals'], ['starts_with', 'starts with']]
            .map(([value, label]) => `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`)
            .join('');
    }
    function conditionValueControl(field, selected = '') {
        if (field === 'lead_status' || field === 'status_changed') {
            const statuses = ['new', 'contacted', 'qualified', 'converted', 'lost', 'snoozed', 'archived'];
            return `<select data-rule-cond-value>${statuses.map(s =>
                `<option value="${s}" ${selected === s ? 'selected' : ''}>${s}</option>`
            ).join('')}</select>`;
        }
        if (field === 'lead_label' || field === 'label_added') {
            return `<select data-rule-cond-value>${(state.companyLabels || []).map(l =>
                `<option value="${field === 'label_added' ? l.id : esc(l.name)}" ${String(selected) === String(l.id) || String(selected) === String(l.name) ? 'selected' : ''}>${esc(l.name)}</option>`
            ).join('') || '<option value="">No labels</option>'}</select>`;
        }
        return `<input type="text" data-rule-cond-value placeholder="Value" value="${esc(selected || '')}">`;
    }
    function actionNeedsValue(type) {
        return !['create_lead', 'notify_assignee'].includes(type);
    }
    function actionTypeOptions(selected = 'assign') {
        return [
            ['create_lead', 'Create a lead'],
            ['assign', 'Assign lead to'],
            ['add_label', 'Add label'],
            ['set_status', 'Set status'],
            ['reopen_after_days', 'Reopen after days'],
            ['notify_assignee', 'Notify assignee'],
        ].map(([value, label]) =>
            `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`
        ).join('');
    }
    function actionValueOptions(type, selected = '') {
        if (type === 'assign') {
            return (state.assignees || []).map(m =>
                `<option value="${m.id}" ${String(selected) === String(m.id) ? 'selected' : ''}>${esc(m.name)}</option>`
            ).join('') || '<option value="">No teammates</option>';
        }
        if (type === 'add_label') {
            return (state.companyLabels || []).map(l =>
                `<option value="${l.id}" ${String(selected) === String(l.id) || String(selected) === String(l.name) ? 'selected' : ''}>${esc(l.name)}</option>`
            ).join('') || '<option value="">No labels</option>';
        }
        if (type === 'set_status') {
            const selectedStatus = selected || 'contacted';
            return ['new', 'contacted', 'qualified', 'converted', 'lost', 'archived'].map(s =>
                `<option value="${s}" ${selectedStatus === s ? 'selected' : ''}>${s}</option>`
            ).join('');
        }
        if (type === 'reopen_after_days') {
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
        const statuses = ['new', 'contacted', 'qualified', 'converted', 'lost', 'snoozed', 'archived'];
        return `<option value="">Select status…</option>` + statuses.map(s =>
            `<option value="${s}" ${selected === s ? 'selected' : ''}>${s}</option>`
        ).join('');
    }
    function triggerExtraKind(type) {
        if (type === 'lead_labeled') return 'label';
        if (type === 'lead_status_changed') return 'status';
        return '';
    }
    function triggerExtraOptions(type, selected = '') {
        if (type === 'lead_labeled') return triggerLabelOptions(selected);
        if (type === 'lead_status_changed') return triggerStatusOptions(selected);
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
                    <select data-rule-trigger-label ${kind ? '' : 'hidden disabled'}>${triggerExtraOptions(value, preset.label || preset.status || '')}</select>
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
            <select data-rule-cond-operator>${conditionOperatorOptions(preset.operator || 'contains')}</select>
            ${conditionValueControl(field, preset.value || '')}
            <button type="button" class="leads-rule-remove" data-remove-rule-row title="Remove">×</button>
        `;
        wrap.appendChild(row);
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
        const valueSel = row.querySelector('[data-rule-action-value]');
        const needsValue = actionNeedsValue(type);
        if (valueSel) {
            valueSel.hidden = type === 'create_lead';
            valueSel.disabled = !needsValue;
            if (type !== 'create_lead') {
                const fallback = type === 'set_status' ? 'contacted' : (type === 'reopen_after_days' ? '3' : '');
                const selected = (preset.value && typeof preset.value !== 'object') ? preset.value : fallback;
                valueSel.innerHTML = actionValueOptions(type, selected || fallback);
            }
        }
        row.querySelector('[data-create-lead-keywords]')?.remove();
        row.querySelector('.leads-rule-create-help')?.remove();
        if (type === 'create_lead') {
            row.insertAdjacentHTML('beforeend', createLeadKeywordsHtml(preset));
        }
    }
    function addRuleActionRow(preset = {}) {
        const wrap = document.getElementById('leadRuleActions');
        if (!wrap) return;
        const type = preset.type || 'assign';
        const needsValue = actionNeedsValue(type);
        const defaultValue = (preset.value && typeof preset.value !== 'object')
            ? preset.value
            : (type === 'reopen_after_days' ? '3' : '');
        const row = document.createElement('div');
        row.className = 'leads-rule-extra-card is-action' + (type === 'create_lead' ? ' is-create-lead' : '');
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
        }));
        conditions
            .filter(c => c.field && c.field !== 'channel' && c.field !== 'shared_inbox' && c.field !== 'inbox' && c.field !== 'label_added' && c.field !== 'status_changed')
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
        const del = e.target.closest('[data-delete-company-label]');
        if (!del) return;
        if (!confirm('Delete this label? It will be removed from all leads.')) return;
        const res = await fetch(api + '/labels/' + del.dataset.deleteCompanyLabel, {
            method: 'DELETE', credentials: 'same-origin', headers: headers(),
        });
        if (!res.ok) return alert('Could not delete label.');
        state.labelIds = state.labelIds.filter(id => id !== String(del.dataset.deleteCompanyLabel));
        await loadCompanyLabels();
        loadLeads();
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
        loadRules().catch(() => {});
        openRulesModal();
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
    });
    document.getElementById('leadRuleActions')?.addEventListener('change', (e) => {
        const typeSel = e.target.closest('[data-rule-action-type]');
        if (!typeSel) return;
        const row = typeSel.closest('.leads-rule-extra-card');
        syncActionRow(row, typeSel.value);
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
        const extra = payload.conditions.filter(c => c.field !== 'channel' && c.field !== 'shared_inbox');
        if (extra.some(c => !String(c.value || '').trim())) return alert('Each condition needs a value.');
        if (!payload.actions.length) return alert('Add at least one action.');
        for (const action of payload.actions) {
            if (['assign', 'add_label', 'set_status'].includes(action.type) && (action.value === null || action.value === '')) {
                return alert('That action needs a value.');
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
    Promise.all([loadCompanyLabels(), loadAssignees()]).then(() => loadLeads()).then(() => {
        const id = new URLSearchParams(window.location.search).get('lead');
        if (id) openLead(id).catch(() => {});
    });
})();
</script>
@endpush
