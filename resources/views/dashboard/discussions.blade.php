@extends('layouts.app')

@section('title', 'Discussions')

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

<style>
.main-content > .content:has(.disc-page-wrapper) {
    max-width: none !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
}

.disc-page-wrapper {
    --inbox-bg: #f4f5f7;
    --inbox-panel: #ffffff;
    --inbox-border: #e6e8ec;
    --inbox-text: #1f2937;
    --inbox-muted: #6b7280;
    --inbox-accent: #2f6fed;
    --inbox-accent-soft: #e8f0fe;
    margin: 0;
    width: 100%;
    height: calc(100vh - 64px);
    min-height: calc(100vh - 64px);
}
.disc-page-wrapper .inbox-app {
    height: 100%;
    width: 100%;
    margin: 0;
    display: flex;
    flex-direction: column;
    background: var(--inbox-bg);
    color: var(--inbox-text);
    font-family: "Segoe UI", "IBM Plex Sans", system-ui, sans-serif;
    overflow: hidden;
    position: relative;
}
.disc-page-wrapper .inbox-shell {
    display: grid;
    grid-template-columns: 280px minmax(300px, 380px) minmax(0, 1fr);
    height: 100%;
    width: 100%;
    min-height: 0;
}
.disc-page-wrapper .inbox-nav,
.disc-page-wrapper .inbox-list-pane,
.disc-page-wrapper .inbox-thread-pane {
    background: var(--inbox-panel);
    border-right: 1px solid var(--inbox-border);
    min-height: 0;
    overflow: auto;
}
.disc-page-wrapper .inbox-nav { display: flex; flex-direction: column; padding: 0.75rem; gap: 0.25rem; overflow: auto; border-right: 1px solid var(--inbox-border); }
.disc-page-wrapper .inbox-nav-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; gap: 0.35rem; }
.disc-page-wrapper .inbox-brand { display: flex; gap: 0.65rem; align-items: center; flex: 1; min-width: 0; }
.disc-page-wrapper .inbox-brand-mark {
    width: 28px; height: 28px; border-radius: 8px;
    background: linear-gradient(135deg, #2f6fed, #0ea5e9);
}
.disc-page-wrapper .inbox-brand-title { font-weight: 700; font-size: 0.95rem; }
.disc-page-wrapper .inbox-brand-sub { font-size: 0.75rem; color: var(--inbox-muted); }
.disc-page-wrapper .inbox-icon-btn, .disc-page-wrapper .inbox-mini-btn {
    border: none; background: transparent; color: var(--inbox-muted);
    width: 28px; height: 28px; border-radius: 6px; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
}
.disc-page-wrapper .inbox-icon-btn:hover, .disc-page-wrapper .inbox-mini-btn:hover { background: var(--inbox-bg); color: var(--inbox-text); }
.disc-page-wrapper .inbox-icon-btn svg { width: 16px; height: 16px; }
.disc-page-wrapper .inbox-nav-section { margin-top: 0.75rem; }
.disc-page-wrapper .inbox-nav-label, .disc-page-wrapper .inbox-nav-label-row {
    font-size: 0.72rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    color: var(--inbox-muted); padding: 0.35rem 0.4rem;
}
.disc-page-wrapper .inbox-nav-label-row { display: flex; justify-content: space-between; align-items: center; }
.disc-page-wrapper .inbox-nav-item {
    width: 100%; display: flex; justify-content: space-between; align-items: center;
    border: none; background: transparent; text-align: left; padding: 0.45rem 0.55rem;
    border-radius: 8px; cursor: pointer; color: var(--inbox-text); font-size: 0.875rem; font-family: inherit;
}
.disc-page-wrapper .inbox-nav-item:hover { background: var(--inbox-bg); }
.disc-page-wrapper .inbox-nav-item.active { background: var(--inbox-accent-soft); color: var(--inbox-accent); font-weight: 600; }
.disc-page-wrapper .inbox-count {
    background: #eef2f7; color: var(--inbox-muted); border-radius: 999px;
    padding: 0.05rem 0.4rem; font-size: 0.7rem; font-weight: 600;
}
.disc-page-wrapper .inbox-nav-item.active .inbox-count { background: #fff; color: var(--inbox-accent); }
.disc-page-wrapper .inbox-inbox-row {
    display: flex; align-items: center; gap: 0.45rem; width: 100%;
    border: none; background: transparent; text-align: left; padding: 0.4rem 0.55rem;
    border-radius: 8px; cursor: pointer; font-size: 0.84rem; color: var(--inbox-text); font-family: inherit;
}
.disc-page-wrapper .inbox-inbox-row:hover { background: var(--inbox-bg); }
.disc-page-wrapper .inbox-inbox-row.active { background: var(--inbox-accent-soft); color: var(--inbox-accent); }
.disc-page-wrapper .inbox-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.disc-page-wrapper .inbox-label-empty { padding: 0.4rem 0.5rem; font-size: 0.75rem; color: var(--inbox-muted); }

.disc-page-wrapper .inbox-btn {
    border: 1px solid transparent; border-radius: 6px; padding: 0.28rem 0.55rem; cursor: pointer;
    font-size: 0.75rem; font-weight: 600; font-family: inherit; line-height: 1.2;
    display: inline-flex; align-items: center; justify-content: center; gap: 0.25rem; white-space: nowrap;
}
.disc-page-wrapper .inbox-btn.primary { background: var(--inbox-accent); border-color: var(--inbox-accent); color: #fff; }
.disc-page-wrapper .inbox-btn.primary:hover { filter: brightness(0.95); }
.disc-page-wrapper .inbox-btn.ghost { background: var(--inbox-bg); border-color: var(--inbox-border); color: var(--inbox-text); }
.disc-page-wrapper .inbox-btn.ghost.is-active { border-color: var(--inbox-accent); color: var(--inbox-accent); background: var(--inbox-accent-soft); }

.disc-page-wrapper .inbox-list-pane { display: flex; flex-direction: column; overflow: hidden; }
.disc-page-wrapper .inbox-list-header { padding: 1rem 1rem 0.5rem; border-bottom: 1px solid var(--inbox-border); background: #fff; flex-shrink: 0; }
.disc-page-wrapper .inbox-list-header h2 { font-size: 1rem; margin: 0; }
.disc-page-wrapper .inbox-list-header-row { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.65rem; }
.disc-page-wrapper .inbox-list-header-row .inbox-btn { padding: 0.24rem 0.5rem; font-size: 0.72rem; }
.disc-page-wrapper .inbox-search { display: grid; gap: 0.5rem; }
.disc-page-wrapper .inbox-search-row { display: flex; gap: 0.4rem; align-items: center; }
.disc-page-wrapper .inbox-search-row input { flex: 1; width: 100%; border: 1px solid var(--inbox-border); border-radius: 8px; padding: 0.5rem 0.7rem; font-size: 0.84rem; background: var(--inbox-bg); font-family: inherit; }
.disc-page-wrapper .inbox-conversation-list { padding: 0.35rem; flex: 1; min-height: 0; overflow: auto; }
.disc-page-wrapper .inbox-conv {
    width: 100%; text-align: left; border: none; background: transparent;
    padding: 0.75rem 0.7rem; border-radius: 10px; cursor: pointer; display: grid; gap: 0.2rem; font-family: inherit;
}
.disc-page-wrapper .inbox-conv:hover { background: var(--inbox-bg); }
.disc-page-wrapper .inbox-conv.active { background: #eef0f3; }
.disc-page-wrapper .inbox-conv.active.unread { background: var(--inbox-accent-soft); }
.disc-page-wrapper .inbox-conv:not(.unread) .inbox-conv-subject { color: #6b7280; font-weight: 500; }
.disc-page-wrapper .inbox-conv:not(.unread) .inbox-conv-snippet { color: #9ca3af; }
.disc-page-wrapper .inbox-conv.unread .inbox-conv-subject { color: var(--inbox-text); }
.disc-page-wrapper .inbox-conv-top { display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.78rem; color: var(--inbox-muted); }
.disc-page-wrapper .inbox-conv-subject { font-size: 0.84rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.disc-page-wrapper .inbox-conv-snippet { font-size: 0.78rem; color: var(--inbox-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.disc-page-wrapper .inbox-conv-tags { display: flex; flex-wrap: wrap; gap: 0.25rem; margin-top: 0.25rem; }
.disc-page-wrapper .inbox-pill {
    display: inline-flex; align-items: center; gap: 0.25rem;
    font-size: 0.68rem; font-weight: 600; padding: 0.1rem 0.4rem; border-radius: 999px;
    background: #f1f5f9; color: #334155;
}
.disc-page-wrapper .inbox-empty, .disc-page-wrapper .inbox-thread-placeholder {
    display: flex; align-items: center; justify-content: center; min-height: 240px;
    color: var(--inbox-muted); padding: 2rem; text-align: center; flex: 1;
}
.disc-page-wrapper .inbox-placeholder-card { max-width: 360px; }
.disc-page-wrapper .inbox-placeholder-card svg { width: 48px; height: 48px; margin: 0 auto 1rem; color: var(--inbox-accent); }
.disc-page-wrapper .inbox-placeholder-card h3 { margin: 0 0 0.4rem; color: var(--inbox-text); }
.disc-page-wrapper .inbox-placeholder-card p { margin: 0; font-size: 0.9rem; line-height: 1.45; }

.disc-page-wrapper .inbox-thread-pane { display: flex; flex-direction: column; overflow: hidden; background: #f4f5f7; border-right: none; }
.disc-page-wrapper .inbox-thread { display: flex; flex-direction: column; height: 100%; min-height: 0; flex: 1; }
.disc-page-wrapper .inbox-thread-header {
    display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start;
    padding: 0.9rem 1.15rem 0.85rem; border-bottom: 1px solid var(--inbox-border); background: #fff; flex-shrink: 0;
}
.disc-page-wrapper .inbox-thread-heading { min-width: 0; flex: 1; }
.disc-page-wrapper .disc-subject-input {
    width: 100%; margin: 0; border: 0; background: transparent; padding: 0;
    font-size: 1.05rem; font-weight: 700; letter-spacing: -0.01em; line-height: 1.3;
    color: var(--inbox-text); font-family: inherit;
}
.disc-page-wrapper .disc-subject-input:focus { outline: none; box-shadow: 0 1px 0 var(--inbox-accent); }
.disc-page-wrapper .inbox-thread-meta { font-size: 0.75rem; color: var(--inbox-muted); margin-top: 0.2rem; }
.disc-page-wrapper .inbox-thread-participants { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; margin-top: 0.45rem; }
.disc-page-wrapper .inbox-chip {
    display: inline-flex; align-items: center; gap: 0.3rem; max-width: 220px;
    padding: 0.12rem 0.45rem 0.12rem 0.2rem; border-radius: 999px; background: #f3f4f6;
    color: #374151; font-size: 0.72rem; font-weight: 600; border: 1px solid #e5e7eb;
}
.disc-page-wrapper .inbox-chip-avatar {
    width: 16px; height: 16px; border-radius: 4px; display: inline-flex; align-items: center; justify-content: center;
    color: #fff; font-size: 0.55rem; font-weight: 700; flex-shrink: 0; background: #64748b;
}
.disc-page-wrapper .inbox-chip-add {
    width: 22px; height: 22px; border-radius: 6px;
    border: 1px dashed #d1d5db; background: #fff; color: #6b7280;
    cursor: pointer; font-size: 0.9rem; line-height: 1;
    display: inline-flex; align-items: center; justify-content: center; padding: 0;
    font-family: inherit;
}
.disc-page-wrapper .inbox-chip-add:hover { border-color: var(--inbox-accent); color: var(--inbox-accent); }
.disc-page-wrapper .inbox-participants-pop { position: relative; display: inline-flex; }
.disc-page-wrapper .inbox-participants-menu {
    position: absolute; top: calc(100% + 6px); left: 0; z-index: 40;
    min-width: 240px; max-width: 300px; background: #fff; border: 1px solid var(--inbox-border);
    border-radius: 10px; box-shadow: 0 12px 28px rgba(15,23,42,.12); padding: 0.35rem; display: grid; gap: 0.1rem;
}
.disc-page-wrapper .inbox-participants-menu[hidden] { display: none !important; }
.disc-page-wrapper .inbox-participants-head {
    padding: 0.35rem 0.55rem 0.45rem; font-size: 0.72rem; font-weight: 700;
    letter-spacing: .04em; text-transform: uppercase; color: var(--inbox-muted);
}
.disc-page-wrapper .inbox-participants-search {
    padding: 0 0.35rem 0.35rem;
}
.disc-page-wrapper .inbox-participants-search input {
    width: 100%; box-sizing: border-box; border: 1px solid var(--inbox-border); border-radius: 7px;
    padding: 0.35rem 0.5rem; font: inherit; font-size: 0.8rem; background: #fff;
}
.disc-page-wrapper .inbox-participants-add-list {
    display: grid; gap: 0.08rem; max-height: 220px; overflow: auto;
}
.disc-page-wrapper .inbox-participants-add-list button {
    border: none; background: transparent; text-align: left; padding: 0.45rem 0.55rem;
    border-radius: 7px; cursor: pointer; font: inherit; font-size: 0.82rem; color: var(--inbox-text);
    display: grid; gap: 0.08rem;
}
.disc-page-wrapper .inbox-participants-add-list button:hover { background: var(--inbox-bg); }
.disc-page-wrapper .inbox-participants-add-list .inbox-assign-email {
    display: block; font-size: 0.7rem; color: var(--inbox-muted);
}
.disc-page-wrapper .inbox-participants-empty {
    padding: 0.55rem; font-size: 0.78rem; color: var(--inbox-muted);
}
.disc-page-wrapper .inbox-thread-actions { display: flex; gap: 0.4rem; align-items: center; flex-shrink: 0; }
.disc-page-wrapper .inbox-icon-action {
    width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--inbox-border); background: #fff;
    color: #4b5563; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
}
.disc-page-wrapper .inbox-icon-action:hover, .disc-page-wrapper .inbox-icon-action.is-open { background: var(--inbox-bg); color: var(--inbox-text); }
.disc-page-wrapper .inbox-icon-action svg { width: 14px; height: 14px; }
.disc-page-wrapper .inbox-assign-btn { padding: 0.24rem 0.5rem; gap: 0.2rem; }
.disc-page-wrapper .inbox-assign-btn svg { width: 12px; height: 12px; }
.disc-page-wrapper .inbox-archive-btn { padding: 0.24rem 0.5rem; }
.disc-page-wrapper .inbox-archive-btn svg { width: 13px; height: 13px; }
.disc-page-wrapper .inbox-pop { position: relative; }
.disc-page-wrapper .inbox-pop-menu {
    position: absolute; top: calc(100% + 6px); right: 0; z-index: 40;
    min-width: 200px; background: #fff; border: 1px solid var(--inbox-border); border-radius: 10px;
    box-shadow: 0 12px 28px rgba(15,23,42,.12); padding: 0.35rem; display: grid; gap: 0.1rem;
}
.disc-page-wrapper .inbox-pop-menu[hidden] { display: none !important; }
.disc-page-wrapper .inbox-pop-menu > button {
    border: none; background: transparent; text-align: left; padding: 0.45rem 0.55rem;
    border-radius: 7px; cursor: pointer; font: inherit; font-size: 0.82rem; color: var(--inbox-text);
}
.disc-page-wrapper .inbox-pop-menu > button:hover { background: var(--inbox-bg); }
.disc-page-wrapper .inbox-assign-list { display: grid; gap: 0.08rem; max-height: 240px; overflow: auto; }
.disc-page-wrapper .inbox-assign-list button {
    border: none; background: transparent; text-align: left; padding: 0.45rem 0.55rem;
    border-radius: 7px; cursor: pointer; font: inherit; font-size: 0.82rem;
}
.disc-page-wrapper .inbox-assign-list button:hover { background: var(--inbox-bg); }
.disc-page-wrapper .inbox-snooze-custom {
    display: grid; gap: 0.25rem; padding: 0.4rem 0.55rem 0.5rem;
    font-size: 0.72rem; font-weight: 600; color: var(--inbox-muted);
}
.disc-page-wrapper .inbox-snooze-custom input,
.disc-page-wrapper .inbox-snooze-custom select,
.disc-page-wrapper .form-input {
    width: 100%; border: 1px solid var(--inbox-border); border-radius: 7px;
    padding: 0.35rem 0.45rem; font: inherit; font-size: 0.82rem; background: #fff; color: var(--inbox-text);
}

.disc-page-wrapper .inbox-thread-messages {
    flex: 1; min-height: 0; overflow: auto; padding: 0.75rem 1rem 1rem; display: grid; gap: 0.65rem; align-content: start;
}
.disc-page-wrapper .disc-msg {
    background: #fff; border: 1px solid var(--inbox-border); border-radius: 12px; padding: 0.7rem 0.85rem;
}
.disc-page-wrapper .disc-msg-head {
    display: flex; align-items: center; gap: 0.55rem; margin-bottom: 0.45rem;
}
.disc-page-wrapper .inbox-avatar {
    width: 28px; height: 28px; border-radius: 6px; display: inline-flex; align-items: center; justify-content: center;
    color: #fff; font-size: 0.68rem; font-weight: 700; flex-shrink: 0; background: #64748b; overflow: hidden;
}
.disc-page-wrapper .inbox-avatar img { width: 100%; height: 100%; object-fit: cover; }
.disc-page-wrapper .disc-msg-from { font-weight: 650; color: var(--inbox-text); font-size: 0.84rem; }
.disc-page-wrapper .disc-msg-time { margin-left: auto; font-size: 0.75rem; color: #9ca3af; }
.disc-page-wrapper .disc-msg-body { font-size: 0.88rem; line-height: 1.45; white-space: pre-wrap; color: var(--inbox-text); }

.disc-page-wrapper .inbox-composer { border-top: 1px solid var(--inbox-border); padding: 0.7rem 0.9rem 0.85rem; background: #fff; flex-shrink: 0; }
.disc-page-wrapper .inbox-composer-modes { display: flex; gap: 0.35rem; margin-bottom: 0.45rem; }
.disc-page-wrapper .inbox-composer-mode {
    border: 1px solid transparent; background: transparent; border-radius: 999px;
    padding: 0.2rem 0.65rem; font-size: 0.75rem; font-weight: 600; color: var(--inbox-muted); cursor: default; font-family: inherit;
}
.disc-page-wrapper .inbox-composer-mode.is-active {
    background: var(--inbox-accent-soft); color: var(--inbox-accent); border-color: #c7d7fb;
}
.disc-page-wrapper .inbox-composer-card {
    border: 1px solid var(--inbox-border); border-radius: 12px; background: #fff; overflow: hidden;
}
.disc-page-wrapper .inbox-composer-row { display: flex; align-items: flex-end; gap: 0.25rem; padding: 0.35rem 0.35rem 0.15rem 0.55rem; }
.disc-page-wrapper .inbox-composer-editor {
    flex: 1; border: none; resize: none; min-height: 44px; max-height: 140px;
    padding: 0.45rem 0.25rem; font: inherit; font-size: 0.88rem; background: transparent; color: var(--inbox-text);
}
.disc-page-wrapper .inbox-composer-editor:focus { outline: none; }
.disc-page-wrapper .inbox-composer-icons { display: flex; gap: 0.1rem; padding-bottom: 0.25rem; }
.disc-page-wrapper .inbox-composer-icon {
    width: 28px; height: 28px; border: none; background: transparent; border-radius: 6px;
    color: #9ca3af; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
}
.disc-page-wrapper .inbox-composer-icon:hover { background: #f3f4f6; color: var(--inbox-text); }
.disc-page-wrapper .inbox-composer-icon svg { width: 16px; height: 16px; }
.disc-page-wrapper .inbox-composer-bar { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; padding: 0.35rem 0.55rem 0.45rem; }
.disc-page-wrapper .inbox-composer-hint { font-size: 0.75rem; color: var(--inbox-muted); }

.disc-page-wrapper .inbox-tag-toggle {
    border: 1px solid var(--inbox-border); background: #fff; border-radius: 999px;
    padding: 0.15rem 0.5rem; font-size: 0.72rem; font-weight: 600; cursor: pointer; font-family: inherit; color: #475569;
}
.disc-page-wrapper .inbox-tag-toggle.on { background: var(--inbox-accent-soft); border-color: #c7d7fb; color: var(--inbox-accent); }

.inbox-modal-backdrop {
    position: fixed; inset: 0; background: rgba(15, 23, 42, .35); z-index: 80;
    display: flex; align-items: center; justify-content: center; padding: 1rem; overflow: auto;
}
.inbox-modal {
    width: min(480px, 100%); background: #fff; border-radius: 14px; padding: 1.25rem;
    box-shadow: 0 24px 60px rgba(0,0,0,.18); display: grid; gap: 0.75rem;
    max-height: min(90vh, 900px); overflow: auto;
}
.inbox-modal-wide { width: min(640px, 100%); }
.inbox-modal-head { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; }
.inbox-modal-head h3 { margin: 0; }
.inbox-modal-help { margin: 0; color: var(--inbox-muted, #6b7280); font-size: 0.84rem; }
.inbox-modal label { display: grid; gap: 0.3rem; font-size: 0.8rem; font-weight: 600; color: #374151; }
.inbox-modal-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem; }
.inbox-connect-modes { display: grid; gap: 0.5rem; }
.inbox-mode-option {
    display: flex; gap: 0.65rem; align-items: flex-start;
    border: 1px solid var(--inbox-border, #e6e8ec); border-radius: 10px; padding: 0.7rem 0.8rem;
    cursor: pointer; font-weight: 500;
}
.inbox-mode-option:has(input:checked) { border-color: var(--inbox-accent, #2f6fed); background: var(--inbox-accent-soft, #e8f0fe); }
.inbox-mode-option input { margin-top: 0.2rem; }
.inbox-mode-option strong { display: block; font-size: 0.84rem; }
.inbox-mode-option small { display: block; font-weight: 400; color: var(--inbox-muted, #6b7280); margin-top: 0.15rem; line-height: 1.35; }
.inbox-rule-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem; }
.disc-rules-list { display: grid; gap: 0.5rem; }
.disc-rule-row {
    display: flex; justify-content: space-between; gap: 0.5rem; align-items: center;
    padding: 0.65rem 0.75rem; border: 1px solid var(--inbox-border, #e6e8ec); border-radius: 10px;
}

@media (max-width: 1100px) {
    .disc-page-wrapper .inbox-shell { grid-template-columns: 220px 1fr; }
    .disc-page-wrapper .inbox-thread-pane { display: none; }
    .disc-page-wrapper .inbox-shell.has-detail { grid-template-columns: 1fr; }
    .disc-page-wrapper .inbox-shell.has-detail .inbox-nav,
    .disc-page-wrapper .inbox-shell.has-detail .inbox-list-pane { display: none; }
    .disc-page-wrapper .inbox-shell.has-detail .inbox-thread-pane { display: flex; }
}
@media (max-width: 860px) {
    .disc-page-wrapper, .disc-page-wrapper .inbox-app { height: auto; min-height: calc(100vh - 64px); overflow: visible; }
    .disc-page-wrapper .inbox-shell { grid-template-columns: 1fr; height: auto; }
    .disc-page-wrapper .inbox-nav, .disc-page-wrapper .inbox-list-pane { max-height: 360px; }
}
</style>

<script>
(function () {
    const app = document.getElementById('discussionsApp');
    const API = app.dataset.api;
    const CSRF = app.dataset.csrf;
    const USER_ID = Number(app.dataset.userId);

    const state = {
        view: 'discussions',
        sharedInboxId: null,
        tagId: null,
        items: [],
        current: null,
        messages: [],
        teammates: [],
        inboxes: [],
        tags: [],
        rules: [],
        permissions: { create_tags: false, create_rules: false },
        ruleMeta: { triggers: {}, actions: {} },
        attachment: null,
        counts: {},
    };

    const el = (id) => document.getElementById(id);

    async function api(path, opts = {}) {
        const res = await fetch(API + path, {
            method: opts.method || 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                ...(opts.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
            },
            body: opts.body instanceof FormData ? opts.body : (opts.body ? JSON.stringify(opts.body) : undefined),
            credentials: 'same-origin',
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.success === false) {
            throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] || 'Request failed');
        }
        return data;
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function relativeTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        const diff = (Date.now() - d.getTime()) / 1000;
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h';
        if (diff < 86400 * 7) return Math.floor(diff / 86400) + 'd';
        return d.toLocaleDateString();
    }

    function updateCounts(counts = {}) {
        state.counts = counts;
        document.querySelectorAll('[data-count]').forEach((node) => {
            node.textContent = counts[node.dataset.count] ?? 0;
        });
    }

    function closePopMenus(exceptId) {
        ['assignMenu', 'snoozeMenu', 'moreMenu', 'addParticipantMenu'].forEach((id) => {
            if (id === exceptId) return;
            const m = el(id);
            if (m) m.hidden = true;
        });
    }

    function filterAddParticipantList(query) {
        const list = el('addParticipantList');
        if (!list || !state.current) return;
        const q = String(query || '').trim().toLowerCase();
        const participantIds = new Set((state.current.participants || []).map((p) => Number(p.id)));
        const addable = state.teammates.filter((t) => {
            if (participantIds.has(Number(t.id))) return false;
            if (!q) return true;
            return String(t.name || '').toLowerCase().includes(q)
                || String(t.email || '').toLowerCase().includes(q);
        });
        list.innerHTML = addable.length ? addable.map((t) => `
            <button type="button" data-add-user="${t.id}">
                <span class="inbox-assign-name">${escapeHtml(t.name)}</span>
                <span class="inbox-assign-email">${escapeHtml(t.email || '')}</span>
            </button>
        `).join('') : `<div class="inbox-participants-empty">${q ? 'No matching teammates.' : 'Everyone is already in this discussion.'}</div>`;
    }

    function renderNav() {
        el('discInboxList').innerHTML = state.inboxes.map((i) => `
            <button type="button" class="inbox-inbox-row ${state.sharedInboxId === i.id ? 'active' : ''}" data-inbox="${i.id}">
                <span class="inbox-dot" style="background:${escapeHtml(i.color || '#2f6fed')}"></span>
                <span>${escapeHtml(i.name)}</span>
            </button>
        `).join('') || '<div class="inbox-label-empty">No shared inboxes</div>';

        el('discTagList').innerHTML = state.tags.map((t) => `
            <button type="button" class="inbox-inbox-row ${state.tagId === t.id ? 'active' : ''}" data-tag="${t.id}">
                <span class="inbox-dot" style="background:${escapeHtml(t.color || '#64748b')}"></span>
                <span>${escapeHtml(t.name)}</span>
            </button>
        `).join('') || '<div class="inbox-label-empty">No tags</div>';

        el('btnNewTag').hidden = !state.permissions.create_tags;
        el('btnAddRule').hidden = !state.permissions.create_rules;
    }

    function renderList() {
        const titles = {
            discussions: 'Discussions', subscribed: 'Subscribed', open: 'Open',
            assigned: 'Assigned to me', snoozed: 'Later', archived: 'Done'
        };
        el('discListTitle').textContent = titles[state.view] || 'Discussions';
        el('discThreadList').innerHTML = state.items.map((item) => {
            const unread = (item.unread_count || 0) > 0;
            return `<button type="button" class="inbox-conv ${state.current?.id === item.id ? 'active' : ''} ${unread ? 'unread' : ''}" data-id="${item.id}">
                <div class="inbox-conv-top">
                    <span>${escapeHtml(item.assignee?.name || (item.is_subscribed ? 'Subscribed' : 'Discussion'))}</span>
                    <span>${escapeHtml(relativeTime(item.last_message_at))}</span>
                </div>
                <div class="inbox-conv-subject">${escapeHtml(item.subject || '(No subject)')}</div>
                <div class="inbox-conv-snippet">${escapeHtml(item.preview || '')}</div>
                <div class="inbox-conv-tags">
                    ${(item.tags || []).map((t) => `<span class="inbox-pill" style="background:${escapeHtml(t.color || '#f1f5f9')}">${escapeHtml(t.name)}</span>`).join('')}
                    ${item.shared_inbox ? `<span class="inbox-pill">${escapeHtml(item.shared_inbox.name)}</span>` : ''}
                </div>
            </button>`;
        }).join('') || '<div class="inbox-empty">No conversations in this view.</div>';
    }

    function fillSelects() {
        const move = el('discMoveInbox');
        const teammates = el('newTeammates');
        const inbox = el('newInbox');
        const curMove = move.value;
        move.innerHTML = '<option value="">No shared inbox</option>' + state.inboxes.map((i) =>
            `<option value="${i.id}">${escapeHtml(i.name)}</option>`
        ).join('');
        teammates.innerHTML = state.teammates.filter((t) => t.id !== USER_ID).map((t) =>
            `<option value="${t.id}">${escapeHtml(t.name)} (${escapeHtml(t.email || '')})</option>`
        ).join('');
        inbox.innerHTML = '<option value="">Select shared inbox…</option>' + state.inboxes.map((i) =>
            `<option value="${i.id}">${escapeHtml(i.name)}</option>`
        ).join('');
        move.value = curMove;

        el('assignList').innerHTML = `<button type="button" data-assign="">Unassigned</button>` +
            state.teammates.map((t) => `<button type="button" data-assign="${t.id}">
                <span class="inbox-assign-name">${escapeHtml(t.name)}</span>
                <span class="inbox-assign-email">${escapeHtml(t.email || '')}</span>
            </button>`).join('');
    }

    function renderDetail() {
        const empty = el('discEmpty');
        const detail = el('discDetail');
        if (!state.current) {
            empty.style.display = 'flex';
            detail.style.display = 'none';
            el('discShell')?.classList.remove('has-detail');
            return;
        }
        empty.style.display = 'none';
        detail.style.display = 'flex';
        el('discShell')?.classList.add('has-detail');
        const c = state.current;
        el('discSubject').value = c.subject || '';
        el('discAssigneeLabel').textContent = c.assignee?.name || 'Unassigned';
        el('discMoveInbox').value = c.shared_inbox?.id || '';
        el('btnArchiveLabel').textContent = c.status === 'archived' ? 'Reopen' : 'Done';
        const meta = [];
        if (c.shared_inbox) meta.push(c.shared_inbox.name);
        if (c.reopen_at) meta.push('Snoozed until ' + new Date(c.reopen_at).toLocaleString());
        if (c.status === 'archived') meta.push('Done');
        el('discThreadMeta').textContent = meta.join(' · ');
        const participantIds = new Set((c.participants || []).map((p) => Number(p.id)));
        const addable = state.teammates.filter((t) => !participantIds.has(Number(t.id)));
        el('discParticipants').innerHTML = (c.participants || []).map((p) =>
            `<span class="inbox-chip"><span class="inbox-chip-avatar">${escapeHtml(p.initials || '?')}</span><span>${escapeHtml(p.name)}${p.is_me ? ' (you)' : ''}</span></span>`
        ).join('') + `
            <div class="inbox-pop inbox-participants-pop">
                <button type="button" class="inbox-chip-add" id="btnAddParticipant" title="Add participants" aria-haspopup="menu" aria-expanded="false">+</button>
                <div class="inbox-pop-menu inbox-participants-menu" id="addParticipantMenu" hidden>
                    <div class="inbox-participants-head">Add participants</div>
                    <div class="inbox-participants-search">
                        <input type="search" id="addParticipantSearch" placeholder="Search teammates…" autocomplete="off">
                    </div>
                    <div class="inbox-participants-add-list" id="addParticipantList">
                        ${addable.length ? addable.map((t) => `
                            <button type="button" data-add-user="${t.id}">
                                <span class="inbox-assign-name">${escapeHtml(t.name)}</span>
                                <span class="inbox-assign-email">${escapeHtml(t.email || '')}</span>
                            </button>
                        `).join('') : '<div class="inbox-participants-empty">Everyone is already in this discussion.</div>'}
                    </div>
                </div>
            </div>`;
        const selected = new Set((c.tags || []).map((t) => t.id));
        el('discTagPicker').innerHTML = state.tags.map((t) =>
            `<button type="button" class="inbox-tag-toggle ${selected.has(t.id) ? 'on' : ''}" data-tag-toggle="${t.id}">${escapeHtml(t.name)}</button>`
        ).join('');
        el('discMessages').innerHTML = state.messages.map((m) => {
            const u = m.user || {};
            const avatar = u.photo
                ? `<img src="${escapeHtml(u.photo)}" alt="">`
                : escapeHtml(u.initials || '?');
            const attach = m.attachment
                ? `<div style="margin-top:0.4rem;"><a href="${escapeHtml(m.attachment.url)}" target="_blank" rel="noopener">${escapeHtml(m.attachment.name || 'Attachment')}</a></div>`
                : '';
            return `<div class="disc-msg">
                <div class="disc-msg-head">
                    <div class="inbox-avatar">${avatar}</div>
                    <div class="disc-msg-from">${escapeHtml(u.name || 'Someone')}</div>
                    <div class="disc-msg-time">${m.created_at ? new Date(m.created_at).toLocaleString() : ''}</div>
                </div>
                <div class="disc-msg-body">${escapeHtml(m.body || '')}${attach}</div>
            </div>`;
        }).join('') || '<div class="inbox-empty">No comments yet.</div>';
        el('discMessages').scrollTop = el('discMessages').scrollHeight;
    }

    function renderRules() {
        const triggers = state.ruleMeta.triggers || {};
        el('ruleTrigger').innerHTML = Object.entries(triggers).map(([k, v]) =>
            `<option value="${k}">${escapeHtml(v)}</option>`
        ).join('');
        el('rulesList').innerHTML = state.rules.map((r) => `
            <div class="disc-rule-row">
                <div>
                    <strong>${escapeHtml(r.name)}</strong>
                    <div class="inbox-conv-snippet">${escapeHtml((r.triggers || []).join(', '))} → ${(r.actions || []).map((a) => a.type).join(', ')}</div>
                </div>
                <button type="button" class="inbox-btn ghost" data-del-rule="${r.id}" ${state.permissions.create_rules ? '' : 'hidden'}>Delete</button>
            </div>
        `).join('') || '<div class="inbox-label-empty">No rules yet.</div>';
    }

    async function bootstrap() {
        const data = await api('/bootstrap');
        const d = data.data || {};
        state.teammates = d.teammates || [];
        state.inboxes = d.shared_inboxes || [];
        state.tags = d.tags || [];
        state.rules = d.rules || [];
        state.permissions = d.permissions || state.permissions;
        state.ruleMeta = d.rule_meta || state.ruleMeta;
        updateCounts(d.counts || {});
        fillSelects();
        renderNav();
        renderRules();
    }

    async function loadList() {
        const params = new URLSearchParams({ view: state.view, limit: '50' });
        if (state.sharedInboxId) params.set('shared_inbox_id', state.sharedInboxId);
        if (state.tagId) params.set('tag_id', state.tagId);
        const q = el('discSearch').value.trim();
        if (q) params.set('search', q);
        const data = await api('/conversations?' + params.toString());
        state.items = data.data || [];
        updateCounts(data.counts || state.counts);
        renderList();
    }

    async function openDiscussion(id) {
        const data = await api('/conversations/' + id);
        state.current = data.data.conversation;
        state.messages = data.data.messages || [];
        renderList();
        renderDetail();
    }

    function showModal(id) { el(id).style.display = 'flex'; }
    function hideModal(id) { el(id).style.display = 'none'; }

    function openNewModal() {
        showModal('modalNewDiscussion');
        el('newSubject').value = '';
        el('newComment').value = '';
        el('newTeammates').selectedIndex = -1;
        el('newInbox').value = '';
        document.querySelector('input[name="toMode"][value="teammates"]').checked = true;
        toggleToMode();
    }

    function toggleToMode() {
        const mode = document.querySelector('input[name="toMode"]:checked')?.value;
        el('newTeammates').style.display = mode === 'teammates' ? '' : 'none';
        el('newInbox').style.display = mode === 'inbox' ? '' : 'none';
    }

    async function createDiscussion() {
        const mode = document.querySelector('input[name="toMode"]:checked')?.value;
        const body = {
            subject: el('newSubject').value.trim(),
            comment: el('newComment').value.trim(),
        };
        if (mode === 'inbox') {
            body.shared_inbox_id = Number(el('newInbox').value) || null;
        } else {
            body.teammate_ids = Array.from(el('newTeammates').selectedOptions).map((o) => Number(o.value));
        }
        const data = await api('/conversations', { method: 'POST', body });
        hideModal('modalNewDiscussion');
        await loadList();
        await openDiscussion(data.data.id);
    }

    function mentionedIds(text) {
        const ids = [];
        for (const t of state.teammates) {
            if (text.toLowerCase().includes('@' + String(t.name || '').toLowerCase())) ids.push(t.id);
        }
        return ids;
    }

    async function sendComment() {
        if (!state.current) return;
        const body = el('discComment').value.trim();
        const payload = { body, mentioned_user_ids: mentionedIds(body) };
        if (state.attachment) {
            payload.attachment_path = state.attachment.path;
            payload.attachment_name = state.attachment.name;
            payload.attachment_type = state.attachment.type;
        }
        await api('/conversations/' + state.current.id + '/messages', { method: 'POST', body: payload });
        el('discComment').value = '';
        state.attachment = null;
        el('discAttachLabel').textContent = 'Internal — visible to teammates only';
        await openDiscussion(state.current.id);
        await loadList();
    }

    document.querySelectorAll('.inbox-nav-item[data-view]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            document.querySelectorAll('.inbox-nav-item[data-view]').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
            state.view = btn.dataset.view;
            state.sharedInboxId = null;
            state.tagId = null;
            renderNav();
            await loadList();
        });
    });

    el('discInboxList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-inbox]');
        if (!btn) return;
        state.sharedInboxId = Number(btn.dataset.inbox);
        state.tagId = null;
        renderNav();
        await loadList();
    });

    el('discTagList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-tag]');
        if (!btn) return;
        state.tagId = Number(btn.dataset.tag);
        state.sharedInboxId = null;
        renderNav();
        await loadList();
    });

    el('discThreadList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-id]');
        if (!btn) return;
        await openDiscussion(Number(btn.dataset.id));
    });

    ['btnNewDiscussion', 'btnNewDiscussionHeader', 'btnNewDiscussionEmpty'].forEach((id) => {
        el(id)?.addEventListener('click', openNewModal);
    });
    el('btnCloseNew').addEventListener('click', () => hideModal('modalNewDiscussion'));
    el('btnCancelNew').addEventListener('click', () => hideModal('modalNewDiscussion'));
    el('btnCreateDiscussion').addEventListener('click', () => createDiscussion().catch((err) => alert(err.message)));
    document.querySelectorAll('input[name="toMode"]').forEach((r) => r.addEventListener('change', toggleToMode));

    let searchTimer;
    el('discSearch').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadList().catch(console.error), 250);
    });

    el('discSubject').addEventListener('change', async () => {
        if (!state.current) return;
        try {
            const data = await api('/conversations/' + state.current.id, {
                method: 'PATCH',
                body: { subject: el('discSubject').value.trim() },
            });
            state.current = data.data;
            await loadList();
        } catch (err) { alert(err.message); }
    });

    el('btnAssignToggle').addEventListener('click', (e) => {
        e.stopPropagation();
        const menu = el('assignMenu');
        const open = menu.hidden;
        closePopMenus(open ? 'assignMenu' : null);
        menu.hidden = !open;
    });
    el('assignList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-assign]');
        if (!btn || !state.current) return;
        const val = btn.dataset.assign;
        try {
            const data = await api('/conversations/' + state.current.id + '/assign', {
                method: 'POST',
                body: { assigned_to: val ? Number(val) : null },
            });
            state.current = data.data;
            el('assignMenu').hidden = true;
            await loadList();
            renderDetail();
        } catch (err) { alert(err.message); }
    });

    el('btnSnooze').addEventListener('click', (e) => {
        e.stopPropagation();
        const menu = el('snoozeMenu');
        const open = menu.hidden;
        closePopMenus(open ? 'snoozeMenu' : null);
        if (open) {
            const d = new Date(Date.now() + 24 * 3600 * 1000);
            el('snoozeAt').value = new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        }
        menu.hidden = !open;
    });
    el('snoozeMenu').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-snooze-hours]');
        if (!btn || !state.current) return;
        const hours = Number(btn.dataset.snoozeHours);
        try {
            const data = await api('/conversations/' + state.current.id + '/snooze', {
                method: 'POST',
                body: { reopen_at: new Date(Date.now() + hours * 3600 * 1000).toISOString() },
            });
            state.current = data.data;
            el('snoozeMenu').hidden = true;
            await loadList();
            renderDetail();
        } catch (err) { alert(err.message); }
    });
    el('btnConfirmSnooze').addEventListener('click', async () => {
        if (!state.current) return;
        try {
            const data = await api('/conversations/' + state.current.id + '/snooze', {
                method: 'POST',
                body: { reopen_at: new Date(el('snoozeAt').value).toISOString() },
            });
            state.current = data.data;
            el('snoozeMenu').hidden = true;
            await loadList();
            renderDetail();
        } catch (err) { alert(err.message); }
    });

    el('btnArchive').addEventListener('click', async () => {
        if (!state.current) return;
        try {
            const archived = state.current.status !== 'archived';
            const data = await api('/conversations/' + state.current.id + '/archive', {
                method: 'POST',
                body: { archived },
            });
            state.current = data.data;
            await loadList();
            renderDetail();
        } catch (err) { alert(err.message); }
    });

    el('btnThreadMore').addEventListener('click', (e) => {
        e.stopPropagation();
        const menu = el('moreMenu');
        const open = menu.hidden;
        closePopMenus(open ? 'moreMenu' : null);
        menu.hidden = !open;
    });
    el('btnUnsubscribe').addEventListener('click', async () => {
        if (!state.current) return;
        if (!confirm('Unsubscribe from this discussion?')) return;
        try {
            await api('/conversations/' + state.current.id + '/unsubscribe', { method: 'POST' });
            state.current = null;
            state.messages = [];
            el('moreMenu').hidden = true;
            renderDetail();
            await loadList();
        } catch (err) { alert(err.message); }
    });

    el('discParticipants').addEventListener('click', async (e) => {
        if (e.target.closest('#addParticipantMenu') || e.target.closest('#btnAddParticipant')) {
            e.stopPropagation();
        }

        const toggle = e.target.closest('#btnAddParticipant');
        if (toggle) {
            const menu = el('addParticipantMenu');
            if (!menu) return;
            const open = menu.hidden;
            closePopMenus(open ? 'addParticipantMenu' : null);
            menu.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                const search = el('addParticipantSearch');
                if (search) {
                    search.value = '';
                    filterAddParticipantList('');
                    setTimeout(() => search.focus(), 0);
                }
            }
            return;
        }

        const addBtn = e.target.closest('[data-add-user]');
        if (!addBtn || !state.current) return;
        const userId = Number(addBtn.dataset.addUser);
        if (!userId) return;
        try {
            const data = await api('/conversations/' + state.current.id + '/participants', {
                method: 'POST',
                body: { user_ids: [userId] },
            });
            state.current = data.data;
            renderDetail();
            await loadList();
            const menu = el('addParticipantMenu');
            const btn = el('btnAddParticipant');
            if (menu && btn) {
                menu.hidden = false;
                btn.setAttribute('aria-expanded', 'true');
                filterAddParticipantList(el('addParticipantSearch')?.value || '');
            }
        } catch (err) { alert(err.message); }
    });

    el('discParticipants').addEventListener('input', (e) => {
        if (e.target?.id !== 'addParticipantSearch') return;
        filterAddParticipantList(e.target.value);
    });
    el('discMoveInbox').addEventListener('change', async () => {
        if (!state.current) return;
        try {
            const val = el('discMoveInbox').value;
            const data = await api('/conversations/' + state.current.id + '/move', {
                method: 'POST',
                body: { shared_inbox_id: val ? Number(val) : null },
            });
            state.current = data.data;
            el('moreMenu').hidden = true;
            await loadList();
            renderDetail();
        } catch (err) { alert(err.message); }
    });

    document.addEventListener('click', () => closePopMenus());

    el('discTagPicker').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-tag-toggle]');
        if (!btn || !state.current) return;
        const id = Number(btn.dataset.tagToggle);
        const selected = new Set((state.current.tags || []).map((t) => t.id));
        if (selected.has(id)) selected.delete(id); else selected.add(id);
        try {
            const data = await api('/conversations/' + state.current.id + '/tags', {
                method: 'POST',
                body: { tag_ids: Array.from(selected) },
            });
            state.current = data.data;
            renderDetail();
            await loadList();
        } catch (err) { alert(err.message); }
    });

    el('btnAttach').addEventListener('click', () => el('discFile').click());
    el('discFile').addEventListener('change', async () => {
        const file = el('discFile').files?.[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('file', file);
        try {
            const data = await api('/attachments', { method: 'POST', body: fd });
            state.attachment = data.data;
            el('discAttachLabel').textContent = data.data.name;
        } catch (err) { alert(err.message); }
        el('discFile').value = '';
    });
    el('btnSendComment').addEventListener('click', () => sendComment().catch((err) => alert(err.message)));

    el('btnNewTag').addEventListener('click', async () => {
        const name = prompt('Tag name');
        if (!name) return;
        try {
            await api('/tags', { method: 'POST', body: { name, color: '#2f6fed' } });
            await bootstrap();
            renderDetail();
        } catch (err) { alert(err.message); }
    });

    el('btnOpenRules').addEventListener('click', () => {
        renderRules();
        showModal('modalRules');
        el('ruleForm').style.display = 'none';
    });
    el('btnCloseRules').addEventListener('click', () => hideModal('modalRules'));
    el('btnAddRule').addEventListener('click', () => {
        el('ruleForm').style.display = 'grid';
        el('ruleName').value = '';
        el('ruleActionValue').value = '';
    });
    el('btnCancelRule').addEventListener('click', () => el('ruleForm').style.display = 'none');
    el('btnSaveRule').addEventListener('click', async () => {
        try {
            await api('/rules', {
                method: 'POST',
                body: {
                    name: el('ruleName').value.trim(),
                    triggers: [el('ruleTrigger').value],
                    actions: [{ type: el('ruleActionType').value, value: el('ruleActionValue').value.trim() }],
                },
            });
            await bootstrap();
            renderRules();
            el('ruleForm').style.display = 'none';
        } catch (err) { alert(err.message); }
    });
    el('rulesList').addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-del-rule]');
        if (!btn) return;
        try {
            await api('/rules/' + btn.dataset.delRule, { method: 'DELETE' });
            await bootstrap();
            renderRules();
        } catch (err) { alert(err.message); }
    });

    (async function init() {
        try {
            await bootstrap();
            await loadList();
            const params = new URLSearchParams(location.search);
            const cid = Number(params.get('conversation') || 0);
            if (cid) await openDiscussion(cid);
        } catch (err) {
            console.error(err);
            el('discThreadList').innerHTML = `<div class="inbox-empty">${escapeHtml(err.message)}</div>`;
        }
    })();
})();
</script>
@endsection
