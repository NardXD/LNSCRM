@extends('layouts.app')

@section('title', 'Discussions')

@push('styles')
    @vite(['resources/css/pages/discussions.css'])
@endpush

@push('scripts')
    @vite(['resources/js/pages/discussions.js'])
@endpush

@section('content')
<div class="inbox-page-wrapper disc-page-wrapper">
<div class="inbox-app" id="discussionsApp"
     data-api="{{ url('api/discussions') }}"
     data-csrf="{{ csrf_token() }}"
     data-user-id="{{ auth()->id() }}">

    <div class="inbox-shell" id="discShell">
        <aside class="inbox-nav">
            <div class="inbox-nav-top">
                <div class="inbox-brand">
                    <span class="inbox-brand-mark"></span>
                    <div>
                        <div class="inbox-brand-title">Discussions</div>
                        <div class="inbox-brand-sub">Internal teammate threads</div>
                    </div>
                </div>
                <div class="inbox-nav-actions">
                    <button type="button" class="inbox-icon-btn" id="btnNewDiscussion" title="New discussion">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                </div>
            </div>

            <div class="inbox-nav-section">
                <div class="inbox-nav-label">Views</div>
                <button type="button" class="inbox-nav-item active" data-view="discussions"><span>Discussions</span><span class="inbox-count" data-count="discussions">0</span></button>
                <button type="button" class="inbox-nav-item" data-view="subscribed"><span>Subscribed</span><span class="inbox-count" data-count="subscribed">0</span></button>
                <button type="button" class="inbox-nav-item" data-view="open"><span>Open</span><span class="inbox-count" data-count="open">0</span></button>
                <button type="button" class="inbox-nav-item" data-view="assigned"><span>Assigned to me</span><span class="inbox-count" data-count="assigned">0</span></button>
                <button type="button" class="inbox-nav-item" data-view="snoozed"><span>Later</span><span class="inbox-count" data-count="snoozed">0</span></button>
                <button type="button" class="inbox-nav-item" data-view="archived"><span>Done</span><span class="inbox-count" data-count="archived">0</span></button>
            </div>

            <div class="inbox-nav-section">
                <div class="inbox-nav-label">Shared inboxes</div>
                <div id="discInboxList"></div>
            </div>

            <div class="inbox-nav-section">
                <div class="inbox-nav-label-row">
                    <div class="inbox-nav-label">Tags</div>
                    <button type="button" class="inbox-mini-btn" id="btnNewTag" title="New tag" hidden>+</button>
                </div>
                <div id="discTagList"></div>
            </div>

            <div class="inbox-nav-section inbox-tools-section">
                <button type="button" class="inbox-nav-item" id="btnOpenRules">
                    <span>Rules</span>
                </button>
            </div>
        </aside>

        <section class="inbox-list-pane">
            <div class="inbox-list-header">
                <div class="inbox-list-header-row">
                    <h2 id="discListTitle">Discussions</h2>
                    <button type="button" class="inbox-btn primary" id="btnNewDiscussionHeader">New</button>
                </div>
                <div class="inbox-search">
                    <div class="inbox-search-row">
                        <input type="search" id="discSearch" placeholder="Search discussions…" autocomplete="off">
                    </div>
                </div>
            </div>
            <div class="inbox-conversation-list" id="discThreadList"></div>
        </section>

        <section class="inbox-thread-pane" id="discDetailPane">
            <div class="inbox-thread-placeholder" id="discEmpty">
                <div class="inbox-placeholder-card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        <path d="M8 9h8"/><path d="M8 13h5"/>
                    </svg>
                    <h3>Your discussions</h3>
                    <p>Start an internal thread with teammates or a shared inbox — no customer email required.</p>
                    <div style="margin-top:1rem;">
                        <button type="button" class="inbox-btn primary" id="btnNewDiscussionEmpty">New discussion</button>
                    </div>
                </div>
            </div>

            <div class="inbox-thread" id="discDetail" style="display:none;">
                <div class="inbox-thread-header">
                    <div class="inbox-thread-heading">
                        <input type="text" id="discSubject" class="disc-subject-input" maxlength="255" aria-label="Subject">
                        <div class="inbox-thread-participants" id="discParticipants"></div>
                        <div class="inbox-thread-meta" id="discThreadMeta"></div>
                        <div class="inbox-conv-tags" id="discTagPicker" style="margin-top:0.45rem;"></div>
                    </div>
                    <div class="inbox-thread-actions">
                        <div class="inbox-pop" id="assignPop">
                            <button type="button" class="inbox-btn ghost inbox-assign-btn" id="btnAssignToggle" title="Assign">
                                <span id="discAssigneeLabel">Unassigned</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div class="inbox-pop-menu" id="assignMenu" hidden>
                                <div class="inbox-assign-list" id="assignList"></div>
                            </div>
                        </div>
                        <div class="inbox-pop" id="snoozePop">
                            <button type="button" class="inbox-icon-action" id="btnSnooze" title="Later / snooze">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            </button>
                            <div class="inbox-pop-menu" id="snoozeMenu" hidden>
                                <button type="button" data-snooze-hours="1">In 1 hour</button>
                                <button type="button" data-snooze-hours="4">In 4 hours</button>
                                <button type="button" data-snooze-hours="24">Tomorrow</button>
                                <button type="button" data-snooze-hours="168">Next week</button>
                                <div class="inbox-snooze-custom">
                                    Custom
                                    <input type="datetime-local" id="snoozeAt">
                                    <button type="button" class="inbox-btn primary" id="btnConfirmSnooze" style="margin-top:0.35rem;width:100%;">Snooze</button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="inbox-btn ghost inbox-archive-btn" id="btnArchive" title="Done / archive">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                            <span id="btnArchiveLabel">Done</span>
                        </button>
                        <div class="inbox-pop" id="morePop">
                            <button type="button" class="inbox-icon-action" id="btnThreadMore" title="More actions">
                                <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
                            </button>
                            <div class="inbox-pop-menu" id="moreMenu" hidden>
                                <div class="inbox-snooze-custom" style="border-bottom:1px solid var(--inbox-border);margin-bottom:0.25rem;">
                                    Move to inbox
                                    <select id="discMoveInbox" class="form-input" style="margin-top:0.3rem;width:100%;"></select>
                                </div>
                                <button type="button" id="btnUnsubscribe">Unsubscribe</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="inbox-thread-messages" id="discMessages"></div>

                <div class="inbox-composer" id="composerArea">
                    <div class="inbox-composer-modes">
                        <button type="button" class="inbox-composer-mode is-active">Comment</button>
                    </div>
                    <div class="inbox-composer-card">
                        <div class="inbox-composer-row">
                            <textarea id="discComment" class="inbox-composer-editor" rows="2" placeholder="Add an internal comment… Use @name to mention"></textarea>
                            <div class="inbox-composer-icons">
                                <button type="button" class="inbox-composer-icon" id="btnAttach" title="Attach files">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                </button>
                                <input type="file" id="discFile" hidden>
                            </div>
                        </div>
                        <div class="inbox-composer-bar" style="display:flex;">
                            <span class="inbox-composer-hint" id="discAttachLabel">Internal — visible to teammates only</span>
                            <button type="button" class="inbox-btn primary" id="btnSendComment">Add comment</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

{{-- Modals --}}
<div class="inbox-modal-backdrop" id="modalNewDiscussion" style="display:none;">
    <div class="inbox-modal inbox-modal-wide">
        <div class="inbox-modal-head">
            <h3>New discussion</h3>
            <button type="button" class="inbox-icon-btn" id="btnCloseNew" title="Close">×</button>
        </div>
        <p class="inbox-modal-help">Invite teammates or a shared inbox, set a topic, and start with a comment.</p>
        <label>To
            <div class="inbox-connect-modes" style="margin-top:0.35rem;">
                <label class="inbox-mode-option"><input type="radio" name="toMode" value="teammates" checked><div><strong>Teammates</strong><small>Specific people in your company</small></div></label>
                <label class="inbox-mode-option"><input type="radio" name="toMode" value="inbox"><div><strong>Shared inbox</strong><small>Visible to all members of that inbox</small></div></label>
            </div>
            <select id="newTeammates" multiple size="6" class="form-input" style="margin-top:0.5rem;"></select>
            <select id="newInbox" class="form-input" style="margin-top:0.5rem;display:none;"><option value="">Select shared inbox…</option></select>
        </label>
        <label>Subject<input type="text" id="newSubject" class="form-input" maxlength="255" placeholder="Topic"></label>
        <label>Comment<textarea id="newComment" class="form-input" rows="4" placeholder="Start the discussion…"></textarea></label>
        <div class="inbox-modal-actions">
            <button type="button" class="inbox-btn ghost" id="btnCancelNew">Cancel</button>
            <button type="button" class="inbox-btn primary" id="btnCreateDiscussion">Send</button>
        </div>
    </div>
</div>

<div class="inbox-modal-backdrop" id="modalRules" style="display:none;">
    <div class="inbox-modal inbox-modal-wide">
        <div class="inbox-modal-head">
            <h3>Discussion rules</h3>
            <button type="button" class="inbox-icon-btn" id="btnCloseRules" title="Close">×</button>
        </div>
        <div id="rulesList" class="disc-rules-list"></div>
        <div class="inbox-rule-grid" id="ruleForm" style="display:none;margin-top:0.75rem;">
            <label style="grid-column:1/-1;">Name <input type="text" id="ruleName" class="form-input"></label>
            <label>Trigger <select id="ruleTrigger" class="form-input"></select></label>
            <label>Action
                <select id="ruleActionType" class="form-input">
                    <option value="assign">Assign to teammate</option>
                    <option value="tag">Add tag</option>
                    <option value="archive">Archive</option>
                    <option value="move">Move to shared inbox</option>
                    <option value="notify_assignee">Notify assignee</option>
                </select>
            </label>
            <label style="grid-column:1/-1;">Action value <input type="text" id="ruleActionValue" class="form-input" placeholder="User id, tag name, or inbox id"></label>
            <div class="inbox-modal-actions" style="grid-column:1/-1;">
                <button type="button" class="inbox-btn ghost" id="btnCancelRule">Cancel</button>
                <button type="button" class="inbox-btn primary" id="btnSaveRule">Save rule</button>
            </div>
        </div>
        <div class="inbox-modal-actions" id="rulesFooter">
            <button type="button" class="inbox-btn primary" id="btnAddRule" hidden>Add rule</button>
        </div>
    </div>
</div>
</div>

@endsection

