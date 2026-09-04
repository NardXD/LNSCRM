@extends('layouts.app')

@section('title', 'Inbox')

@section('content')
<div class="inbox-page-wrapper">
<div class="inbox-app" id="inboxApp"
     data-api="{{ url('api/inbox') }}"
     data-csrf="{{ csrf_token() }}"
     data-user-id="{{ auth()->id() }}"
     data-connect="{{ route('inbox.connect.outlook') }}">

    @if(session('status') === 'outlook-mail-connected')
        <div class="inbox-toast success">Outlook mailbox connected. Click Sync to import mail.</div>
    @endif
    @if(session('error'))
        <div class="inbox-toast error">{{ session('error') }}</div>
    @endif

    <div class="inbox-shell">
        {{-- Left nav (Front-style) --}}
        <aside class="inbox-nav">
            <div class="inbox-nav-top">
                <div class="inbox-brand">
                    <span class="inbox-brand-mark"></span>
                    <div>
                        <div class="inbox-brand-title">Inbox</div>
                        <div class="inbox-brand-sub" id="mailStatusLabel">Outlook</div>
                    </div>
                </div>
                <div class="inbox-nav-actions">
                    <button type="button" class="inbox-icon-btn" id="btnCompose" title="New mail">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    </button>
                    <button type="button" class="inbox-icon-btn" id="btnSync" title="Sync selected mailbox (also auto-checks every 45s)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    </button>
                </div>
            </div>

            <div class="inbox-nav-section">
                <div class="inbox-nav-label">Views</div>
                <button type="button" class="inbox-nav-item active" data-view="open" data-scope="all">
                    <span>Open</span><span class="inbox-count" id="countOpen">0</span>
                </button>
                <button type="button" class="inbox-nav-item" data-view="assigned_to_me" data-scope="all">
                    <span>Assigned to me</span>
                </button>
                <button type="button" class="inbox-nav-item" data-view="unassigned" data-scope="all">
                    <span>Unassigned</span>
                </button>
                <button type="button" class="inbox-nav-item" data-view="archived" data-scope="all">
                    <span>Archived</span>
                </button>
                <button type="button" class="inbox-nav-item" data-view="snoozed" data-scope="all">
                    <span>Snoozed</span>
                </button>
            </div>

            <div class="inbox-nav-section">
                <div class="inbox-nav-label-row">
                    <div class="inbox-nav-label">Inboxes</div>
                    <button type="button" class="inbox-mini-btn" id="btnNewInbox" title="New shared inbox">+</button>
                </div>
                <div id="inboxList"></div>
            </div>

            <div class="inbox-nav-section inbox-tools-section">
                <button type="button" class="inbox-submenu-toggle is-expanded" id="btnToggleInboxTools" aria-expanded="true">
                    <svg class="inbox-submenu-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    <span>Inbox menu</span>
                </button>
                <div class="inbox-submenu" id="inboxToolsSubmenu">
                    <div class="inbox-tool-group is-expanded" data-tool-group="templates">
                        <button type="button" class="inbox-template-manage-btn" id="btnOpenTemplateList" title="Manage templates">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                            <span>Templates</span>
                            <span class="inbox-template-count" id="templateCount"></span>
                        </button>
                    </div>

                    <div class="inbox-tool-group is-expanded" data-tool-group="signatures">
                        <button type="button" class="inbox-template-manage-btn" id="btnOpenSignatureList" title="Manage signatures">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M12 20h9"/>
                                <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                            </svg>
                            <span>Signatures</span>
                            <span class="inbox-template-count" id="signatureCount"></span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="inbox-nav-footer">
                <button type="button" class="inbox-connect-btn" id="btnConnectOutlook">Connect Personal MS365</button>
                <button type="button" class="inbox-disconnect-btn" id="btnDisconnectOutlook" style="display:none;">Disconnect Personal</button>
            </div>
        </aside>

        {{-- Conversation list --}}
        <section class="inbox-list-pane">
            <div class="inbox-list-header">
                <div class="inbox-list-header-row">
                    <h2 id="listTitle">Open</h2>
                    <button type="button" class="inbox-btn primary" id="btnComposeHeader">Compose</button>
                </div>
                <div class="inbox-search">
                    <div class="inbox-search-row">
                        <input type="search" id="inboxSearch" placeholder="Quick search…" autocomplete="off">
                        <button type="button" class="inbox-btn ghost" id="btnToggleAdvancedSearch" title="Advanced search">Filters</button>
                    </div>
                    <div class="inbox-adv-chips" id="advFilterChips"></div>
                    <div class="inbox-list-merge-bar" id="listMergeBar" hidden>
                        <span class="inbox-list-merge-count" id="listMergeCount">0 selected</span>
                        <button type="button" class="inbox-btn primary" id="btnMergeSelected">Merge conversations</button>
                        <button type="button" class="inbox-btn ghost" id="btnClearChecked">Clear</button>
                    </div>
                </div>
            </div>
            <div class="inbox-conversation-list" id="conversationList" title="Ctrl+click (Cmd+click on Mac) to select multiple threads">
                <div class="inbox-empty" id="listEmpty">Select an inbox or connect Outlook to get started.</div>
            </div>
        </section>

        {{-- Thread --}}
        <section class="inbox-thread-pane">
            <div class="inbox-thread-placeholder" id="threadPlaceholder">
                <div class="inbox-placeholder-card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <h3>Your shared inbox</h3>
                    <p>Connect personal or shared Outlook mailboxes and keep teammate assignment in sync with leads.</p>
                </div>
            </div>

            <div class="inbox-thread" id="threadView" style="display:none;">
                <div class="inbox-thread-header">
                    <div class="inbox-thread-heading">
                        <h2 id="threadSubject"></h2>
                        <div class="inbox-thread-participants" id="threadParticipants"></div>
                        <div class="inbox-thread-meta" id="threadMeta"></div>
                    </div>
                    <div class="inbox-thread-actions">
                        <button type="button" class="inbox-icon-action" id="btnToggleProps" title="Show details" aria-expanded="false" aria-controls="propsPane">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
                        </button>
                        <div class="inbox-pop" id="threadMorePop">
                            <button type="button" class="inbox-icon-action" id="btnThreadMore" title="More actions" aria-haspopup="menu">
                                <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
                            </button>
                            <div class="inbox-pop-menu" id="threadMoreMenu" hidden>
                                <button type="button" data-thread-action="unread">Mark as unread</button>
                                <button type="button" data-thread-action="merge">Merge conversation…</button>
                                <button type="button" data-thread-action="unmerge" id="btnUnmergeMenu" hidden>Unmerge conversations…</button>
                                <button type="button" id="btnSpam">Move to spam</button>
                                <button type="button" id="btnTrash">Move to trash</button>
                            </div>
                        </div>
                        <div class="inbox-pop" id="snoozePop">
                            <button type="button" class="inbox-icon-action" id="btnSnooze" title="Snooze" aria-haspopup="menu">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                            </button>
                            <div class="inbox-pop-menu inbox-snooze-menu" id="snoozeMenu" hidden>
                                <button type="button" data-snooze="later_today">Later today</button>
                                <button type="button" data-snooze="tomorrow">Tomorrow morning</button>
                                <button type="button" data-snooze="monday">Next week</button>
                                <button type="button" data-snooze="3d">In 3 days</button>
                                <label class="inbox-snooze-custom">Custom
                                    <input type="datetime-local" id="snoozeCustom">
                                </label>
                            </div>
                        </div>
                        <div class="inbox-pop" id="assignPop">
                            <button type="button" class="inbox-btn ghost inbox-assign-btn" id="btnAssignToggle" aria-haspopup="menu">
                                <span id="assignBtnLabel">Assign</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div class="inbox-pop-menu inbox-assign-menu" id="assignMenu" hidden></div>
                        </div>
                        <button type="button" class="inbox-btn ghost" id="btnRestore" style="display:none;">Move to inbox</button>
                        <button type="button" class="inbox-btn ghost" id="btnReopen" style="display:none;">Reopen</button>
                        <button type="button" class="inbox-btn ghost inbox-archive-btn" id="btnArchive">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
                            <span id="archiveBtnLabel">Archive</span>
                        </button>
                    </div>
                </div>

                <div class="inbox-messages" id="threadMessages"></div>

                <div class="inbox-composer" id="composerArea">
                    <div class="inbox-composer-modes">
                        <button type="button" class="inbox-composer-mode is-active" data-composer-mode="comment" id="btnModeComment">Comment</button>
                        <button type="button" class="inbox-composer-mode" data-composer-mode="reply" id="btnModeReply">Reply</button>
                    </div>

                    <div class="inbox-composer-card">
                    <div id="commentComposerPanel">
                        <div class="inbox-composer-row">
                            <div class="inbox-mention-popup" id="commentMentionPopup" hidden></div>
                            <div id="commentBody" class="inbox-composer-editor" contenteditable="true" data-placeholder="Add an internal comment…" role="textbox" aria-multiline="true"></div>
                            <div class="inbox-composer-icons">
                                <button type="button" class="inbox-composer-icon" id="btnCommentAttach" title="Attach files">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                </button>
                                <input type="file" id="commentAttachInput" multiple hidden>
                                <button type="button" class="inbox-composer-icon" id="btnCommentMention" title="Mention teammate">@</button>
                                <div class="inbox-pop" id="commentEmojiPop">
                                    <button type="button" class="inbox-composer-icon" id="btnCommentEmoji" title="Emoji">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                                    </button>
                                    <div class="inbox-pop-menu inbox-emoji-menu" id="commentEmojiMenu" hidden></div>
                                </div>
                                <button type="button" class="inbox-composer-icon" id="btnComposerExpand" title="Expand">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="inbox-attach-chips" id="commentAttachChips"></div>
                        <div class="inbox-composer-bar">
                            <span class="inbox-composer-hint">Internal — visible to teammates only</span>
                            <button type="button" class="inbox-btn primary" id="btnSendComment">Add comment</button>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Right properties panel --}}
        <aside class="inbox-props" id="propsPane" style="display:none;" hidden>
            <div class="inbox-props-head">
                <div class="inbox-props-title">Details</div>
                <button type="button" class="inbox-icon-btn" id="btnHideProps" title="Hide details">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
            </div>
            <div class="inbox-props-block">
                <div class="inbox-props-label">Assignee</div>
                <select id="assignSelect" class="inbox-select">
                    <option value="">Unassigned</option>
                </select>
            </div>
            <div class="inbox-props-block">
                <div class="inbox-props-label">Labels</div>
                <div id="conversationTags" class="inbox-tag-pills"></div>
                <select id="addTagSelect" class="inbox-select">
                    <option value="">Add existing label…</option>
                </select>
                <div id="addLeadLabelRow" class="inbox-lead-label-add" hidden>
                    <input type="text" id="addLeadLabelInput" class="inbox-select" maxlength="50" placeholder="New label">
                    <button type="button" class="inbox-btn ghost" id="btnAddLeadLabel">Add</button>
                </div>
            </div>
            <div class="inbox-props-block">
                <div class="inbox-props-label">Inbox</div>
                <div id="propInboxName" class="inbox-prop-value"></div>
            </div>
            <div class="inbox-props-block">
                <div class="inbox-props-label">Contact</div>
                <div id="propContact" class="inbox-prop-value"></div>
                <div id="propContactLead" class="inbox-prop-lead"></div>
            </div>
            <div class="inbox-props-block">
                <div class="inbox-props-label">Thread activity</div>
                <div id="conversationHistory" class="inbox-history-list"></div>
            </div>
            <div class="inbox-props-block">
                <div class="inbox-props-label">Contact history (all channels)</div>
                <div id="inboxContactHistory" class="chp-panel chp-visible" style="width:100%;max-width:none;border:0;background:transparent;height:auto;display:block;"
                     data-api="/api/crm/contact-history"
                     data-can-save-lead="{{ auth()->user()?->hasPermission('view_leads') ? '1' : '0' }}">
                    <div class="chp-body" id="inboxContactHistoryBody" style="padding:0;overflow:visible;">
                        <p class="chp-empty">Select a conversation to see cross-channel history.</p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</div>

{{-- Modals --}}
<div class="inbox-modal-backdrop" id="modalBackdrop" style="display:none;">
    <div class="inbox-modal inbox-modal-wide" id="modalCompose" style="display:none;">
        <h3>New message</h3>
        <p class="inbox-modal-help">Send email through a connected Outlook inbox.</p>
        <label>From
            <select id="composeFrom" class="form-input"></select>
        </label>
        <label>To
            <input type="text" id="composeTo" class="form-input" placeholder="name@company.com, other@company.com">
        </label>
        <label>Cc
            <input type="text" id="composeCc" class="form-input" placeholder="optional">
        </label>
        <label>Subject
            <input type="text" id="composeSubject" class="form-input" placeholder="Subject">
        </label>
        <div class="inbox-composer-tools">
            <button type="button" class="inbox-composer-tool" id="btnComposeAttach" title="Attach files">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                Attach
            </button>
            <input type="file" id="composeAttachInput" multiple hidden>
            <button type="button" class="inbox-composer-tool" id="btnComposeMention" title="Mention teammate">@ Mention</button>
            <div class="inbox-template-picker" data-template-picker="compose">
                <button type="button" class="inbox-composer-tool" data-template-picker-toggle title="Insert template">Template…</button>
                <div class="inbox-template-picker-menu" hidden>
                    <div class="inbox-template-picker-head">Insert template</div>
                    <input type="search" class="inbox-tool-search" data-template-picker-search placeholder="Search templates…" autocomplete="off">
                    <div class="inbox-template-picker-list" data-template-picker-list></div>
                </div>
            </div>
        </div>
        <div class="inbox-attach-chips" id="composeAttachChips"></div>
        <div class="inbox-mention-popup" id="composeMentionPopup" hidden></div>
        <label>Message
            <div id="composeBody" class="inbox-composer-editor form-input" contenteditable="true" data-placeholder="Write your message… Type @ to mention teammates." role="textbox" aria-multiline="true"></div>
        </label>
        <div class="inbox-modal-actions">
            <button type="button" class="inbox-btn ghost" data-close-modal>Cancel</button>
            <div class="inbox-send-group inbox-pop" id="composeSendPop">
                <button type="button" class="inbox-send-main" id="btnSendCompose">Send</button>
                <button type="button" class="inbox-send-caret" id="btnSendComposeMenu" title="More send options" aria-haspopup="menu" aria-label="More send options">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="inbox-pop-menu inbox-send-menu inbox-compose-send-menu" id="composeSendMenu" hidden>
                    <button type="button" data-compose-send-mode="send">Send</button>
                    <button type="button" data-compose-send-mode="later">Send later…</button>
                    <div class="inbox-send-later" id="composeSendLaterFields" hidden>
                        Send at
                        <input type="datetime-local" id="composeSendLaterAt">
                        <button type="button" class="inbox-btn primary" id="btnConfirmComposeSendLater">Schedule send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="inbox-modal inbox-modal-wide" id="modalReply" style="display:none;">
        <h3 id="replyModalTitle">Reply</h3>
        <p class="inbox-modal-help">Email reply via Outlook.</p>
        <label>From
            <select id="replyFrom" class="form-input" aria-label="From"></select>
        </label>
        <label>To
            <input type="text" id="replyTo" class="form-input" placeholder="name@company.com, other@company.com" autocomplete="off">
        </label>
        <label>Cc
            <input type="text" id="replyCc" class="form-input" placeholder="optional" autocomplete="off">
        </label>
        <div class="inbox-composer-tools">
            <button type="button" class="inbox-composer-tool" id="btnReplyAttach" title="Attach files">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                Attach
            </button>
            <input type="file" id="replyAttachInput" multiple hidden>
            <button type="button" class="inbox-composer-tool" id="btnReplyMention" title="Mention teammate">@ Mention</button>
            <div class="inbox-template-picker" data-template-picker="reply">
                <button type="button" class="inbox-composer-tool" data-template-picker-toggle title="Insert template">Template…</button>
                <div class="inbox-template-picker-menu" hidden>
                    <div class="inbox-template-picker-head">Insert template</div>
                    <input type="search" class="inbox-tool-search" data-template-picker-search placeholder="Search templates…" autocomplete="off">
                    <div class="inbox-template-picker-list" data-template-picker-list></div>
                </div>
            </div>
        </div>
        <div class="inbox-attach-chips" id="replyAttachChips"></div>
        <div class="inbox-mention-popup" id="replyMentionPopup" hidden></div>
        <label>Message
            <div id="replyBody" class="inbox-composer-editor form-input" contenteditable="true" data-placeholder="Write a reply… Type @ to mention teammates." role="textbox" aria-multiline="true"></div>
        </label>
        <div class="inbox-modal-actions">
            <span class="inbox-composer-hint" id="composerHint">Reply via Outlook</span>
            <button type="button" class="inbox-btn ghost" data-close-modal>Cancel</button>
            <div class="inbox-send-group inbox-pop" id="sendReplyPop">
                <button type="button" class="inbox-send-main" id="btnSendReply">Send reply</button>
                <button type="button" class="inbox-send-caret" id="btnSendReplyMenu" title="More send options" aria-haspopup="menu" aria-label="More send options">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="inbox-pop-menu inbox-send-menu" id="sendReplyMenu" hidden>
                    <button type="button" data-send-mode="send">Send reply</button>
                    <button type="button" data-send-mode="archive">Send and archive</button>
                    <button type="button" data-send-mode="later">Send later…</button>
                    <button type="button" data-send-mode="draft">Save as draft</button>
                    <div class="inbox-send-later" id="sendLaterFields" hidden>
                        Send at
                        <input type="datetime-local" id="sendLaterAt">
                        <button type="button" class="inbox-btn primary" id="btnConfirmSendLater">Schedule send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="inbox-modal" id="modalInbox" style="display:none;">
        <h3>New shared inbox</h3>
        <p class="inbox-modal-help">Create a team inbox, invite members, then sign in with Microsoft 365 to sync mail.</p>
        <label>Name<input type="text" id="newInboxName" class="form-input" placeholder="Support"></label>
        <div class="inbox-connect-modes">
            <label class="inbox-mode-option">
                <input type="radio" name="connectMode" id="modeMailboxLogin" value="mailbox_login" checked>
                <span>
                    <strong>Sign in with Microsoft 365 mailbox</strong>
                    <small>Login as the shared inbox account (e.g. support@yourcompany.com). Recommended.</small>
                </span>
            </label>
            <label class="inbox-mode-option">
                <input type="radio" name="connectMode" id="modeSharedMailbox" value="shared_mailbox">
                <span>
                    <strong>Use a shared mailbox address</strong>
                    <small>Login as a user who has Full Access to that Microsoft 365 shared mailbox.</small>
                </span>
            </label>
        </div>
        <label id="newInboxEmailLabel">Mailbox email (optional hint)
            <input type="email" id="newInboxEmail" class="form-input" placeholder="support@yourcompany.com">
        </label>
        <label>Members
            <select id="newInboxMembers" class="form-input" multiple size="6"></select>
        </label>
        <div class="inbox-modal-actions inbox-modal-actions-split">
            <button type="button" class="inbox-btn ghost" data-close-modal>Cancel</button>
            <div class="inbox-modal-actions-right">
                <button type="button" class="inbox-btn ghost" id="btnSaveInbox">Create only</button>
                <button type="button" class="inbox-btn primary" id="btnSaveInboxConnect">Create &amp; sign in with Microsoft 365</button>
            </div>
        </div>
    </div>

    <div class="inbox-modal inbox-modal-list" id="modalTemplateList" style="display:none;">
        <div class="inbox-tpl-list-head">
            <div class="inbox-tpl-list-head-text">
                <h3>Templates</h3>
                <p class="inbox-modal-help">Reusable HTML snippets for compose and replies. Insert images into the body or attach files that send with the template.</p>
            </div>
            <button type="button" class="inbox-tpl-close-btn" id="btnCloseTemplateList" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="inbox-tpl-list-toolbar">
            <div class="inbox-tpl-search-wrap">
                <svg class="inbox-tpl-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="templateListSearch" class="inbox-tpl-search" placeholder="Search templates…" autocomplete="off">
            </div>
            <button type="button" class="inbox-btn primary" id="btnNewTemplate" style="display:none;">New template</button>
        </div>
        <div class="inbox-tpl-list-shell">
            <div class="inbox-tpl-list" id="templateList"></div>
        </div>
        <div class="inbox-tpl-pagination" id="templateListPagination" hidden>
            <span class="inbox-tpl-pagination-info" id="templateListPaginationInfo"></span>
            <div class="inbox-tpl-pagination-controls">
                <button type="button" class="inbox-tpl-page-btn" id="templateListPrevPage" disabled>Previous</button>
                <span class="inbox-tpl-page-status" id="templateListPageStatus"></span>
                <button type="button" class="inbox-tpl-page-btn" id="templateListNextPage">Next</button>
            </div>
        </div>
    </div>

    <div class="inbox-modal inbox-modal-xwide" id="modalTemplate" style="display:none;">
        <h3 id="templateModalTitle">New template</h3>
        <p class="inbox-modal-help">Reusable HTML snippets for compose and replies. Insert images into the body or attach files that send with the template.</p>
        <label>Name<input type="text" id="newTemplateName" class="form-input" placeholder="Follow-up"></label>
        <label>Subject (optional)<input type="text" id="newTemplateSubject" class="form-input" placeholder="Re: your inquiry"></label>
        <div class="inbox-composer-tools">
            <button type="button" class="inbox-composer-tool" id="btnTemplateAttach" title="Attach files">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                Attach
            </button>
            <button type="button" class="inbox-composer-tool" id="btnTemplateImage" title="Insert image">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                Image
            </button>
            <button type="button" class="inbox-composer-tool" id="btnTemplateLink" title="Insert link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                Link
            </button>
            <input type="file" id="templateAttachInput" multiple hidden>
            <input type="file" id="templateImageInput" accept="image/*" hidden>
        </div>
        <div class="inbox-attach-chips" id="templateAttachChips"></div>
        <div class="inbox-html-editor inbox-html-editor-tall" data-html-editor="template">
            <div class="inbox-html-toolbar">
                <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
                <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
                <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
                <button type="button" data-cmd="insertUnorderedList" title="Bullet list">• List</button>
                <button type="button" data-html-link title="Insert or edit a link on selected text or image">Link</button>
                <button type="button" data-cmd="removeFormat" title="Clear formatting">Clear</button>
                <span class="inbox-html-toolbar-spacer"></span>
                <button type="button" class="is-active" data-html-mode="visual">Visual</button>
                <button type="button" data-html-mode="source">HTML</button>
            </div>
            <div class="inbox-html-visual" id="newTemplateVisual" contenteditable="true" data-placeholder="Write your template…"></div>
            <textarea class="form-input inbox-html-source" id="newTemplateBody" rows="12" hidden placeholder="<p>Hi,</p><p>Thanks for reaching out…</p>"></textarea>
        </div>
        <p class="inbox-modal-help">Attach files (up to 5, 3 MB each) or insert images inline in Visual mode. Select text or an image, then click Link to make it clickable.</p>
        <div class="inbox-modal-actions inbox-modal-actions-split">
            <button type="button" class="inbox-btn ghost" id="btnDeleteTemplate" style="display:none;">Delete</button>
            <div class="inbox-modal-actions-right">
                <button type="button" class="inbox-btn ghost" data-close-modal>Cancel</button>
                <button type="button" class="inbox-btn primary" id="btnSaveTemplate">Create</button>
            </div>
        </div>
    </div>

    <div class="inbox-modal inbox-modal-list" id="modalSignatureList" style="display:none;">
        <div class="inbox-tpl-list-head">
            <div class="inbox-tpl-list-head-text">
                <h3>Signatures</h3>
                <p class="inbox-modal-help">Saved per browser user. The default signature is added automatically to compose and replies.</p>
            </div>
            <button type="button" class="inbox-tpl-close-btn" id="btnCloseSignatureList" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="inbox-tpl-list-toolbar">
            <div class="inbox-tpl-search-wrap">
                <svg class="inbox-tpl-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="signatureListSearch" class="inbox-tpl-search" placeholder="Search signatures…" autocomplete="off">
            </div>
            <button type="button" class="inbox-btn primary" id="btnNewSignature">New signature</button>
        </div>
        <div class="inbox-tpl-list-shell">
            <div class="inbox-tpl-list" id="signatureList"></div>
        </div>
        <div class="inbox-tpl-pagination" id="signatureListPagination" hidden>
            <span class="inbox-tpl-pagination-info" id="signatureListPaginationInfo"></span>
            <div class="inbox-tpl-pagination-controls">
                <button type="button" class="inbox-tpl-page-btn" id="signatureListPrevPage" disabled>Previous</button>
                <span class="inbox-tpl-page-status" id="signatureListPageStatus"></span>
                <button type="button" class="inbox-tpl-page-btn" id="signatureListNextPage">Next</button>
            </div>
        </div>
    </div>

    <div class="inbox-modal inbox-modal-xwide" id="modalSignature" style="display:none;">
        <div class="inbox-tpl-list-head">
            <div class="inbox-tpl-list-head-text">
                <h3 id="signatureModalTitle">New signature</h3>
                <p class="inbox-modal-help">Saved per browser user. The default signature is added automatically to compose and replies.</p>
            </div>
            <button type="button" class="inbox-tpl-close-btn" id="btnCloseSignatureModal" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <label>Name<input type="text" id="newSignatureName" class="form-input" placeholder="Default"></label>
        <div class="inbox-composer-tools">
            <button type="button" class="inbox-composer-tool" id="btnSignatureImage" title="Insert image">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                Image
            </button>
            <input type="file" id="signatureImageInput" accept="image/*" hidden>
        </div>
        <div class="inbox-html-editor inbox-html-editor-tall" data-html-editor="signature">
            <div class="inbox-html-toolbar">
                <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
                <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
                <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
                <button type="button" data-cmd="insertUnorderedList" title="Bullet list">• List</button>
                <button type="button" data-html-link title="Insert or edit a link on selected text or image">Link</button>
                <button type="button" data-cmd="removeFormat" title="Clear formatting">Clear</button>
                <span class="inbox-html-toolbar-spacer"></span>
                <button type="button" class="is-active" data-html-mode="visual">Visual</button>
                <button type="button" data-html-mode="source">HTML</button>
            </div>
            <div class="inbox-html-visual" id="newSignatureVisual" contenteditable="true" data-placeholder="Best regards,<br>Your Name"></div>
            <textarea class="form-input inbox-html-source" id="newSignatureBody" rows="12" hidden placeholder="<p>Best regards,<br><strong>Your Name</strong></p>"></textarea>
        </div>
        <p class="inbox-modal-help">Insert images inline in Visual mode (e.g. company logo).</p>
        <div class="inbox-modal-actions">
            <button type="button" class="inbox-btn ghost" data-close-modal>Cancel</button>
            <button type="button" class="inbox-btn primary" id="btnSaveSignature">Create</button>
        </div>
    </div>

    <div class="inbox-modal" id="modalMembers" style="display:none;">
        <h3>Inbox members</h3>
        <p class="inbox-modal-help" id="membersInboxName"></p>
        <div id="membersEditor"></div>
        <div class="inbox-modal-actions">
            <button type="button" class="inbox-btn ghost" data-close-modal>Cancel</button>
            <button type="button" class="inbox-btn primary" id="btnSaveMembers">Save members</button>
        </div>
    </div>

    <div class="inbox-modal inbox-modal-wide" id="modalMerge" style="display:none;" role="dialog" aria-labelledby="mergeModalTitle">
        <h3 id="mergeModalTitle">Merge conversation</h3>
        <p class="inbox-modal-help" id="mergeModalHelp">Combine another thread from this mailbox. Works in personal and shared inboxes; threads must belong to the same inbox.</p>

        <div id="mergeMergedSection" hidden>
            <div class="inbox-nav-label">Merged into this thread</div>
            <div id="mergeMergedList" class="inbox-merge-results"></div>
            <button type="button" class="inbox-btn ghost" id="btnUnmergeAll" style="margin-top:0.35rem;">Unmerge all</button>
        </div>

        <label>Find a thread in this inbox
            <input type="search" id="mergeSearch" class="form-input" placeholder="Search subject or sender" autocomplete="off">
        </label>
        <div id="mergeCandidateList" class="inbox-merge-results"></div>
        <div class="inbox-modal-actions">
            <button type="button" class="inbox-btn ghost" data-close-modal>Close</button>
        </div>
    </div>

    <div class="inbox-modal inbox-modal-wide" id="modalAdvancedSearch" style="display:none;" role="dialog" aria-labelledby="advSearchTitle">
        <div class="inbox-modal-head">
            <h3 id="advSearchTitle">Advanced search</h3>
            <button type="button" class="inbox-btn ghost" data-close-modal aria-label="Close">×</button>
        </div>
        <p class="inbox-modal-help">Filter conversations by inbox, sender, recipient, subject, body, and more.</p>
        <div class="inbox-adv-grid" id="advancedSearch">
            <label>Inbox
                <select id="advInbox" class="form-input">
                    <option value="">All inboxes</option>
                </select>
            </label>
            <label>Folder
                <select id="advFolder" class="form-input">
                    <option value="">Current view</option>
                    <option value="any">Any folder</option>
                    <option value="inbox">Inbox</option>
                    <option value="drafts">Drafts</option>
                    <option value="sent">Sent</option>
                    <option value="trash">Trash</option>
                    <option value="spam">Spam</option>
                </select>
            </label>
            <label class="inbox-suggest-field">From
                <div class="inbox-suggest-wrap">
                    <input type="text" id="advFrom" class="form-input" placeholder="name or email" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="advFromSuggest" aria-expanded="false">
                    <ul class="inbox-suggest-list" id="advFromSuggest" role="listbox" hidden></ul>
                </div>
            </label>
            <label class="inbox-suggest-field">To
                <div class="inbox-suggest-wrap">
                    <input type="text" id="advTo" class="form-input" placeholder="recipient email" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="advToSuggest" aria-expanded="false">
                    <ul class="inbox-suggest-list" id="advToSuggest" role="listbox" hidden></ul>
                </div>
            </label>
            <label>Subject
                <input type="text" id="advSubject" class="form-input" placeholder="subject contains">
            </label>
            <label>Message body
                <input type="text" id="advBody" class="form-input" placeholder="body contains">
            </label>
            <label>Assigned to
                <select id="advAssigned" class="form-input">
                    <option value="">Anyone</option>
                    <option value="0">Unassigned</option>
                </select>
            </label>
            <label>Read status
                <select id="advRead" class="form-input">
                    <option value="">Any</option>
                    <option value="0">Unread</option>
                    <option value="1">Read</option>
                </select>
            </label>
            <label>Date from
                <input type="date" id="advDateFrom" class="form-input">
            </label>
            <label>Date to
                <input type="date" id="advDateTo" class="form-input">
            </label>
        </div>
        <div class="inbox-modal-actions">
            <button type="button" class="inbox-btn ghost" id="btnClearAdvancedSearch">Clear</button>
            <button type="button" class="inbox-btn ghost" data-close-modal>Cancel</button>
            <button type="button" class="inbox-btn primary" id="btnApplyAdvancedSearch">Search</button>
        </div>
    </div>
</div>

<div class="inbox-link-dialog-backdrop" id="htmlLinkDialog" hidden>
    <div class="inbox-link-dialog" role="dialog" aria-modal="true" aria-labelledby="htmlLinkDialogTitle">
        <h4 id="htmlLinkDialogTitle">Insert link</h4>
        <p class="inbox-modal-help" id="htmlLinkHint">Add a URL for the selected text or image. Recipients can click it in the email.</p>
        <label>URL
            <input type="url" id="htmlLinkUrl" class="form-input" placeholder="https://example.com" autocomplete="off">
        </label>
        <label id="htmlLinkTextWrap">Link text
            <input type="text" id="htmlLinkText" class="form-input" placeholder="Click here" autocomplete="off">
        </label>
        <div class="inbox-modal-actions inbox-modal-actions-split">
            <button type="button" class="inbox-btn ghost" id="btnHtmlLinkRemove" hidden>Remove link</button>
            <div class="inbox-modal-actions-right">
                <button type="button" class="inbox-btn ghost" id="btnHtmlLinkCancel">Cancel</button>
                <button type="button" class="inbox-btn primary" id="btnHtmlLinkApply">Apply</button>
            </div>
        </div>
    </div>
</div>
<div class="inbox-html-link-tip" id="inboxHtmlLinkTip" hidden></div>

{{-- Sync progress overlay --}}
<div class="inbox-sync-overlay" id="syncOverlay" hidden>
    <div class="inbox-sync-card" role="status" aria-live="polite">
        <div class="inbox-sync-spinner" aria-hidden="true"></div>
        <h3 class="inbox-sync-title">Syncing emails</h3>
        <p class="inbox-sync-emails" id="syncEmailCount">0 / 0</p>
        <p class="inbox-sync-status" id="syncStatusText">Preparing…</p>
        <div class="inbox-sync-bar" aria-hidden="true">
            <div class="inbox-sync-bar-fill" id="syncBarFill" style="width:0%"></div>
        </div>
        <div class="inbox-sync-meta">
            <span id="syncPercent">0%</span>
            <span id="syncDetail">0 / 0 emails</span>
        </div>
        <p class="inbox-sync-count" id="syncNewCount">0 new messages</p>
    </div>
</div>

</div>{{-- /.inbox-page-wrapper --}}

<style>
/* Force inbox to use full main-content width (override .content max-width:1400px). */
.main-content > .content:has(.inbox-page-wrapper) {
    max-width: none !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
}

.inbox-page-wrapper {
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

.inbox-app {
    height: 100%;
    width: 100%;
    margin: 0;
    display: flex;
    flex-direction: column;
    background: var(--inbox-bg);
    color: var(--inbox-text);
    font-family: "Segoe UI", "IBM Plex Sans", system-ui, sans-serif;
    overflow: hidden;
}
.inbox-toast {
    position: absolute; top: 12px; right: 16px; z-index: 50;
    padding: 0.65rem 1rem; border-radius: 8px; font-size: 0.875rem;
    box-shadow: 0 8px 24px rgba(0,0,0,.08);
}
.inbox-toast.success { background: #ecfdf5; color: #065f46; }
.inbox-toast.error { background: #fef2f2; color: #991b1b; }
.inbox-shell {
    display: grid;
    /* 3 columns by default — do not reserve empty props space */
    grid-template-columns: 280px minmax(300px, 380px) minmax(0, 1fr);
    height: 100%;
    width: 100%;
    min-height: 0;
    position: relative;
}
.inbox-shell.with-props {
    grid-template-columns: 280px minmax(280px, 340px) minmax(0, 1fr) 260px;
}
.inbox-nav, .inbox-list-pane, .inbox-thread-pane, .inbox-props {
    background: var(--inbox-panel);
    border-right: 1px solid var(--inbox-border);
    min-height: 0;
    overflow: auto;
}
.inbox-props { border-right: none; border-left: 1px solid var(--inbox-border); padding: 1rem; }
.inbox-props-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin: -0.25rem 0 1rem;
}
.inbox-props-title {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--inbox-muted);
}
.inbox-props-head .inbox-icon-btn svg { width: 16px; height: 16px; }
.inbox-nav { display: flex; flex-direction: column; padding: 0.75rem; gap: 0.25rem; overflow: auto; }
.inbox-nav-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
.inbox-brand { display: flex; gap: 0.65rem; align-items: center; }
.inbox-brand-mark {
    width: 28px; height: 28px; border-radius: 8px;
    background: linear-gradient(135deg, #2f6fed, #0ea5e9);
}
.inbox-brand-title { font-weight: 700; font-size: 0.95rem; }
.inbox-brand-sub { font-size: 0.75rem; color: var(--inbox-muted); }
.inbox-icon-btn, .inbox-mini-btn {
    border: none; background: transparent; color: var(--inbox-muted);
    width: 28px; height: 28px; border-radius: 6px; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
}
.inbox-icon-btn:hover, .inbox-mini-btn:hover { background: var(--inbox-bg); color: var(--inbox-text); }
.inbox-icon-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.inbox-icon-btn svg { width: 16px; height: 16px; }
.inbox-icon-btn.is-syncing svg,
.inbox-sync-inbox-btn.is-syncing svg { animation: inbox-spin 0.9s linear infinite; }
.inbox-sync-inbox-btn svg { display: block; }
@keyframes inbox-spin { to { transform: rotate(360deg); } }
.inbox-sync-overlay {
    position: fixed; inset: 0; z-index: 80;
    background: rgba(15, 23, 42, 0.45);
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
}
.inbox-sync-overlay[hidden] { display: none !important; }
.inbox-sync-card {
    width: min(420px, 100%);
    background: #fff;
    border-radius: 14px;
    padding: 1.35rem 1.4rem 1.2rem;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.22);
    text-align: center;
}
.inbox-sync-spinner {
    width: 36px; height: 36px; margin: 0 auto 0.85rem;
    border: 3px solid #e5e7eb; border-top-color: var(--inbox-accent, #2f6fed);
    border-radius: 50%;
    animation: inbox-spin 0.8s linear infinite;
}
.inbox-sync-title { margin: 0 0 0.35rem; font-size: 1.05rem; font-weight: 650; color: #1f2937; }
.inbox-sync-emails {
    margin: 0 0 0.45rem;
    font-size: 1.35rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    color: #111827;
    letter-spacing: -0.02em;
}
.inbox-sync-status { margin: 0 0 1rem; font-size: 0.88rem; color: #6b7280; min-height: 1.25em; }
.inbox-sync-bar {
    height: 10px; border-radius: 999px; background: #eef1f6; overflow: hidden;
}
.inbox-sync-bar-fill {
    height: 100%; width: 0%;
    background: linear-gradient(90deg, #2f6fed, #4f8cff);
    border-radius: 999px;
    transition: width 0.25s ease;
}
.inbox-sync-meta {
    display: flex; justify-content: space-between;
    margin-top: 0.55rem; font-size: 0.78rem; color: #6b7280; font-variant-numeric: tabular-nums;
}
.inbox-sync-count { margin: 0.85rem 0 0; font-size: 0.82rem; color: #374151; font-weight: 600; }
.inbox-nav-section { margin-top: 0.75rem; }
.inbox-submenu-toggle {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    width: 100%;
    border: none;
    background: transparent;
    color: var(--inbox-muted);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
    padding: 0.35rem 0.4rem;
    border-radius: 8px;
    cursor: pointer;
}
.inbox-submenu-toggle:hover { background: var(--inbox-bg); color: var(--inbox-text); }
.inbox-submenu-chevron,
.inbox-tool-chevron {
    width: 12px;
    height: 12px;
    flex-shrink: 0;
    transition: transform 0.15s ease;
}
.inbox-submenu-toggle.is-expanded .inbox-submenu-chevron,
.inbox-tool-group.is-expanded .inbox-tool-chevron { transform: rotate(90deg); }
.inbox-submenu {
    display: none;
    padding: 0.15rem 0 0.2rem 0.15rem;
    max-height: min(42vh, 360px);
    overflow: auto;
    -webkit-overflow-scrolling: touch;
}
.inbox-submenu-toggle.is-expanded + .inbox-submenu { display: grid; gap: 0.2rem; }
.inbox-tool-group {
    border-radius: 8px;
}
.inbox-tool-group-head {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.inbox-tool-group-toggle {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 0.35rem;
    border: none;
    background: transparent;
    color: var(--inbox-text);
    font-size: 0.82rem;
    font-weight: 600;
    text-align: left;
    padding: 0.35rem 0.4rem;
    border-radius: 7px;
    cursor: pointer;
}
.inbox-tool-group-toggle:hover { background: var(--inbox-bg); }
.inbox-tool-group-body {
    display: none;
    padding: 0.05rem 0 0.25rem 0.85rem;
    max-height: 180px;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
}
.inbox-tool-group.is-expanded .inbox-tool-group-body { display: grid; gap: 0.08rem; }
.inbox-tool-search {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid var(--inbox-border, #e2e5ea);
    border-radius: 6px;
    background: #fff;
    color: var(--inbox-text);
    font-size: 0.75rem;
    padding: 0.3rem 0.45rem;
    margin: 0.1rem 0 0.25rem;
    outline: none;
}
.inbox-tool-search:focus {
    border-color: var(--inbox-accent, #2563eb);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.12);
}
.inbox-tool-search::-webkit-search-cancel-button { cursor: pointer; }
.inbox-tool-empty {
    padding: 0.35rem 0.45rem;
    font-size: 0.75rem;
    color: var(--inbox-muted);
}
.inbox-tool-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.35rem;
    width: 100%;
    border: none;
    background: transparent;
    text-align: left;
    padding: 0.32rem 0.45rem;
    border-radius: 7px;
    cursor: pointer;
    color: var(--inbox-text);
    font-size: 0.8rem;
}
.inbox-tool-row:hover { background: var(--inbox-bg); }
.inbox-tool-row-title {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    border: none;
    background: transparent;
    text-align: left;
    font: inherit;
    color: inherit;
    cursor: pointer;
    padding: 0;
}
.inbox-tool-row-title:hover { color: var(--inbox-accent); }
.inbox-html-editor {
    display: grid;
    gap: 0.45rem;
    border: 1px solid var(--inbox-border);
    border-radius: 10px;
    padding: 0.55rem;
    background: #fafafa;
}
.inbox-html-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    align-items: center;
}
.inbox-html-toolbar button {
    border: 1px solid var(--inbox-border);
    background: #fff;
    border-radius: 6px;
    padding: 0.25rem 0.45rem;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    color: var(--inbox-text);
}
.inbox-html-toolbar button:hover,
.inbox-html-toolbar button.is-active {
    border-color: var(--inbox-accent);
    color: var(--inbox-accent);
    background: var(--inbox-accent-soft);
}
.inbox-html-toolbar-spacer { flex: 1; }
.inbox-html-visual {
    min-height: 140px;
    max-height: 220px;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    border: 1px solid var(--inbox-border);
    border-radius: 8px;
    padding: 0.65rem 0.75rem;
    background: #fff;
    font-size: 0.9rem;
    line-height: 1.5;
}
.inbox-html-visual:empty:before {
    content: attr(data-placeholder);
    color: var(--inbox-muted);
}
.inbox-html-source {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.82rem;
    line-height: 1.55;
    tab-size: 2;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    word-break: break-word;
    min-height: 140px;
    max-height: 220px;
    overflow: auto;
    resize: vertical;
}
.inbox-nav-label, .inbox-nav-label-row {
    font-size: 0.68rem; text-transform: uppercase; letter-spacing: .06em;
    color: var(--inbox-muted); font-weight: 600; margin: 0.4rem 0.35rem;
}
.inbox-nav-label-row { display: flex; justify-content: space-between; align-items: center; }
.inbox-nav-item {
    width: 100%; display: flex; justify-content: space-between; align-items: center;
    border: none; background: transparent; text-align: left; padding: 0.45rem 0.55rem;
    border-radius: 8px; cursor: pointer; color: var(--inbox-text); font-size: 0.875rem;
}
.inbox-nav-item:hover { background: var(--inbox-bg); }
.inbox-nav-item.active { background: var(--inbox-accent-soft); color: var(--inbox-accent); font-weight: 600; }
.inbox-count {
    background: #eef2f7; color: var(--inbox-muted); border-radius: 999px;
    padding: 0.05rem 0.4rem; font-size: 0.7rem; font-weight: 600;
}
.inbox-nav-item.active .inbox-count { background: #fff; color: var(--inbox-accent); }
.inbox-inbox-row, .inbox-rule-row {
    display: flex; align-items: center; gap: 0.45rem; width: 100%;
    border: none; background: transparent; text-align: left; padding: 0.4rem 0.55rem;
    border-radius: 8px; cursor: pointer; font-size: 0.84rem; color: var(--inbox-text);
}
.inbox-inbox-row:hover, .inbox-rule-row:hover { background: var(--inbox-bg); }
.inbox-inbox-row.active { background: var(--inbox-accent-soft); color: var(--inbox-accent); }
.inbox-mini-btn.is-pinned {
    color: var(--inbox-accent);
    background: var(--inbox-accent-soft);
}
.inbox-mailbox { margin-bottom: 0.45rem; }
.inbox-mailbox-head {
    display: grid;
    grid-template-columns: 14px 8px minmax(0, 1fr);
    column-gap: 0.4rem;
    row-gap: 0.15rem;
    align-items: start;
    width: 100%;
    border: none;
    background: transparent;
    text-align: left;
    padding: 0.45rem 0.4rem;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.84rem;
    color: var(--inbox-text);
}
.inbox-mailbox-head:hover { background: var(--inbox-bg); }
.inbox-mailbox-head.is-selected { background: var(--inbox-accent-soft); }
.inbox-mailbox-chevron {
    width: 14px; height: 14px; color: var(--inbox-muted);
    transition: transform 0.15s ease; margin-top: 0.15rem;
    grid-column: 1; grid-row: 1;
}
.inbox-mailbox.is-expanded .inbox-mailbox-chevron { transform: rotate(90deg); }
.inbox-mailbox-head > .inbox-dot {
    grid-column: 2; grid-row: 1;
    margin-top: 0.4rem;
}
.inbox-mailbox-meta {
    grid-column: 3;
    grid-row: 1 / span 2;
    min-width: 0;
    display: grid;
    gap: 0.12rem;
}
.inbox-mailbox-name-row {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    min-width: 0;
}
.inbox-mailbox-name {
    font-weight: 600;
    font-size: 0.84rem;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    flex: 1;
    min-width: 0;
}
.inbox-mailbox-sub {
    display: block;
    font-size: 0.68rem;
    color: var(--inbox-muted);
    line-height: 1.35;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.inbox-mailbox-actions {
    display: inline-flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 0.2rem;
    flex-shrink: 0;
}
.inbox-mailbox-folders {
    display: none;
    padding: 0.05rem 0 0.2rem 0.9rem;
}
.inbox-mailbox.is-expanded .inbox-mailbox-folders { display: grid; gap: 0.08rem; }
.inbox-folder-row {
    display: flex; justify-content: space-between; align-items: center; width: 100%;
    border: none; background: transparent; text-align: left;
    padding: 0.32rem 0.5rem; border-radius: 7px; cursor: pointer;
    color: var(--inbox-muted); font-size: 0.8rem;
}
.inbox-folder-row:hover { background: var(--inbox-bg); color: var(--inbox-text); }
.inbox-folder-row.active {
    background: var(--inbox-accent-soft);
    color: var(--inbox-accent);
    font-weight: 600;
}
.inbox-folder-row .inbox-count { font-size: 0.68rem; }
.inbox-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.inbox-nav-footer { margin-top: auto; padding-top: 1rem; display: grid; gap: 0.4rem; }
.inbox-connect-btn, .inbox-disconnect-btn, .inbox-btn {
    border: 1px solid transparent;
    border-radius: 8px;
    padding: 0.55rem 0.85rem;
    cursor: pointer;
    font-size: 0.84rem;
    font-weight: 600;
    font-family: inherit;
    line-height: 1.3;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
    white-space: nowrap;
    -webkit-appearance: none;
    appearance: none;
}
.inbox-connect-btn,
.inbox-btn.primary {
    background: var(--inbox-accent, #2f6fed);
    border-color: var(--inbox-accent, #2f6fed);
    color: #fff;
}
.inbox-connect-btn:hover,
.inbox-btn.primary:hover {
    filter: brightness(0.95);
}
.inbox-disconnect-btn,
.inbox-btn.ghost {
    background: var(--inbox-bg, #f4f5f7);
    border-color: var(--inbox-border, #e6e8ec);
    color: var(--inbox-text, #1f2937);
}
.inbox-btn.ghost.is-active {
    border-color: var(--inbox-accent, #2f6fed);
    color: var(--inbox-accent, #2f6fed);
    background: var(--inbox-accent-soft, #e8f0fe);
}
.inbox-btn:disabled { opacity: .5; cursor: not-allowed; filter: none; }
.inbox-list-header { padding: 1rem 1rem 0.5rem; border-bottom: 1px solid var(--inbox-border); background: #fff; z-index: 2; flex-shrink: 0; }
.inbox-list-pane {
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.inbox-conversation-list {
    padding: 0.35rem;
    flex: 1;
    min-height: 0;
    overflow: auto;
}
.inbox-list-loading {
    text-align: center;
    padding: 0.75rem;
    font-size: 0.78rem;
    color: var(--inbox-muted);
}
.inbox-list-end {
    text-align: center;
    padding: 0.65rem;
    font-size: 0.72rem;
    color: var(--inbox-muted);
}
.inbox-list-header h2 { font-size: 1rem; margin: 0; }
.inbox-list-header-row { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.65rem; }
.inbox-list-header-row .inbox-btn { padding: 0.4rem 0.75rem; font-size: 0.8rem; }
.inbox-modal#modalCompose { width: min(640px, 100%); }
.inbox-connect-modes { display: grid; gap: 0.5rem; }
.inbox-mode-option {
    display: flex; gap: 0.65rem; align-items: flex-start;
    border: 1px solid var(--inbox-border); border-radius: 10px; padding: 0.7rem 0.8rem;
    cursor: pointer; font-weight: 500;
}
.inbox-mode-option:has(input:checked) { border-color: var(--inbox-accent); background: var(--inbox-accent-soft); }
.inbox-mode-option input { margin-top: 0.2rem; }
.inbox-mode-option strong { display: block; font-size: 0.84rem; }
.inbox-mode-option small { display: block; font-weight: 400; color: var(--inbox-muted); margin-top: 0.15rem; line-height: 1.35; }
.inbox-modal-actions-split { justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem; }
.inbox-modal-actions-right { display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: flex-end; }
.inbox-connect-ms365 {
    border: 1px solid #d1d5db; background: #fff; color: #1f2937;
    border-radius: 6px; padding: 0.15rem 0.4rem; font-size: 0.65rem; font-weight: 700;
    cursor: pointer; white-space: nowrap;
}
.inbox-connect-ms365:hover { border-color: var(--inbox-accent); color: var(--inbox-accent); }
.inbox-nav-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; gap: 0.35rem; }
.inbox-nav-top .inbox-brand { flex: 1; min-width: 0; }
.inbox-search { display: grid; gap: 0.5rem; }
.inbox-search-row { display: flex; gap: 0.4rem; align-items: center; }
.inbox-search-row input { flex: 1; }
.inbox-search input, .inbox-adv-grid .form-input {
    width: 100%; border: 1px solid var(--inbox-border); border-radius: 8px;
    padding: 0.5rem 0.7rem; font-size: 0.84rem; background: var(--inbox-bg);
}
.inbox-adv-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.65rem 0.75rem;
}
.inbox-adv-grid label {
    display: grid;
    gap: 0.25rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: #374151;
}
.inbox-modal-wide { width: min(640px, 100%); }
.inbox-html-editor-tall .inbox-html-visual,
.inbox-html-editor-tall .inbox-html-source {
    min-height: 220px;
    max-height: 420px;
}
.inbox-html-visual img { max-width: 100%; height: auto; }
.inbox-html-visual a { color: var(--inbox-accent); text-decoration: underline; }
.inbox-html-visual a img { outline: 2px solid rgba(47, 111, 237, 0.35); outline-offset: 2px; border-radius: 2px; }
.inbox-link-dialog-backdrop {
    position: fixed;
    inset: 0;
    z-index: 120;
    background: rgba(15, 23, 42, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.inbox-link-dialog-backdrop[hidden] { display: none !important; }
.inbox-link-dialog {
    width: min(420px, 100%);
    background: #fff;
    border-radius: 14px;
    padding: 1.15rem 1.2rem 1.2rem;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.2);
    display: grid;
    gap: 0.7rem;
}
.inbox-link-dialog h4 { margin: 0; font-size: 1.02rem; }
.inbox-link-dialog label { display: grid; gap: 0.3rem; font-size: 0.8rem; font-weight: 600; color: #374151; }
.inbox-link-dialog .form-input { width: 100%; }
.inbox-html-link-tip {
    position: fixed;
    z-index: 140;
    max-width: min(380px, calc(100vw - 16px));
    padding: 0.4rem 0.6rem;
    border-radius: 8px;
    background: #111827;
    color: #fff;
    font-size: 0.75rem;
    line-height: 1.4;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.28);
    pointer-events: none;
    word-break: break-all;
}
.inbox-html-link-tip[hidden] { display: none !important; }
.inbox-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}
.inbox-modal-head h3 { margin: 0; }
.inbox-suggest-wrap { position: relative; }
.inbox-suggest-list {
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 4px);
    z-index: 90;
    margin: 0;
    padding: 0.25rem;
    list-style: none;
    background: #fff;
    border: 1px solid var(--inbox-border);
    border-radius: 10px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, .14);
    max-height: 220px;
    overflow: auto;
}
.inbox-suggest-list[hidden] { display: none !important; }
.inbox-suggest-item {
    width: 100%;
    text-align: left;
    border: none;
    background: transparent;
    border-radius: 8px;
    padding: 0.45rem 0.55rem;
    cursor: pointer;
    display: grid;
    gap: 0.1rem;
    font: inherit;
}
.inbox-suggest-item:hover,
.inbox-suggest-item.is-active { background: var(--inbox-accent-soft); }
.inbox-suggest-email { font-size: 0.82rem; font-weight: 600; color: var(--inbox-text); }
.inbox-suggest-name { font-size: 0.72rem; color: var(--inbox-muted); }
.inbox-adv-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
}
.inbox-adv-chips:empty { display: none; }
.inbox-adv-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.68rem;
    font-weight: 600;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    background: var(--inbox-accent-soft);
    color: var(--inbox-accent);
    border: 1px solid transparent;
    cursor: pointer;
    font-family: inherit;
}
.inbox-adv-chip:hover { border-color: var(--inbox-accent); }
.inbox-conv {
    width: 100%; text-align: left; border: none; background: transparent;
    padding: 0.75rem 0.7rem; border-radius: 10px; cursor: pointer; display: grid; gap: 0.2rem;
    user-select: none;
}
.inbox-conv:hover { background: var(--inbox-bg); }
.inbox-conv.active { background: #eef0f3; }
.inbox-conv.active.unread { background: var(--inbox-accent-soft); }
.inbox-conv.is-checked {
    background: var(--inbox-accent-soft);
    box-shadow: inset 3px 0 0 var(--inbox-accent);
}
.inbox-conv.is-checked.active { background: #dce7fb; }
.inbox-list-merge-bar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.45rem;
    margin-top: 0.55rem;
    padding: 0.45rem 0.15rem 0.15rem;
}
.inbox-list-merge-bar[hidden] { display: none !important; }
.inbox-list-merge-count {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--inbox-text);
    margin-right: auto;
}
.inbox-conv:not(.unread) .inbox-conv-from,
.inbox-conv:not(.unread) .inbox-conv-subject { color: #6b7280; font-weight: 500; }
.inbox-conv:not(.unread) .inbox-conv-snippet { color: #9ca3af; }
.inbox-conv.unread .inbox-conv-from { font-weight: 700; color: var(--inbox-text); }
.inbox-conv.unread .inbox-conv-subject { color: var(--inbox-text); }
.inbox-conv-top { display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.78rem; color: var(--inbox-muted); }
.inbox-conv-time {
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
    border-radius: 4px;
    padding: 0 0.15rem;
}
.inbox-conv-time:hover { color: var(--inbox-accent); }
.inbox-conv-time.is-absolute {
    color: var(--inbox-text);
    font-weight: 600;
}
.inbox-conv-from { color: var(--inbox-text); font-size: 0.88rem; }
.inbox-conv-subject { font-size: 0.84rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.inbox-conv-snippet { font-size: 0.78rem; color: var(--inbox-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.inbox-conv-tags { display: flex; flex-wrap: wrap; gap: 0.25rem; margin-top: 0.25rem; }
.inbox-pill {
    display: inline-flex; align-items: center; gap: 0.25rem;
    font-size: 0.68rem; font-weight: 600; padding: 0.1rem 0.4rem; border-radius: 999px;
    background: #f1f5f9; color: #334155;
}
.inbox-merge-results {
    display: grid;
    gap: 0.35rem;
    max-height: 280px;
    overflow: auto;
    margin-top: 0.25rem;
}
.inbox-merge-row {
    width: 100%;
    text-align: left;
    border: 1px solid var(--inbox-border);
    background: #fff;
    border-radius: 10px;
    padding: 0.55rem 0.7rem;
    cursor: pointer;
    display: grid;
    gap: 0.12rem;
    font: inherit;
    color: inherit;
}
.inbox-merge-row:hover { border-color: var(--inbox-accent); background: var(--inbox-accent-soft); }
.inbox-merge-row-from { font-size: 0.82rem; font-weight: 600; }
.inbox-merge-row-subject { font-size: 0.78rem; color: var(--inbox-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.inbox-merge-row-meta { font-size: 0.72rem; color: var(--inbox-muted); }
.inbox-merge-unmerge {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
}
.inbox-merge-unmerge .inbox-merge-row { flex: 1; cursor: default; }
.inbox-merge-unmerge .inbox-merge-row:hover { border-color: var(--inbox-border); background: #fff; }
.inbox-empty, .inbox-thread-placeholder {
    display: flex; align-items: center; justify-content: center; min-height: 240px;
    color: var(--inbox-muted); padding: 2rem; text-align: center;
}
.inbox-placeholder-card { max-width: 360px; }
.inbox-placeholder-card svg { width: 48px; height: 48px; margin: 0 auto 1rem; color: var(--inbox-accent); }
.inbox-placeholder-card h3 { margin: 0 0 0.4rem; color: var(--inbox-text); }
.inbox-placeholder-card p { margin: 0; font-size: 0.9rem; line-height: 1.45; }
.inbox-thread-pane {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #f4f5f7;
}
.inbox-thread { display: flex; flex-direction: column; height: 100%; min-height: 0; flex: 1; }
.inbox-thread-placeholder { flex: 1; }
.inbox-thread-header {
    display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start;
    padding: 0.9rem 1.15rem 0.85rem; border-bottom: 1px solid var(--inbox-border);
    background: #fff;
    flex-shrink: 0;
}
.inbox-thread-heading { min-width: 0; flex: 1; }
.inbox-thread-heading h2 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    line-height: 1.3;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.inbox-thread-meta { font-size: 0.75rem; color: var(--inbox-muted); margin-top: 0.2rem; }
.inbox-thread-meta:empty { display: none; }
.inbox-thread-participants {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    margin-top: 0.45rem;
}
.inbox-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    max-width: 220px;
    padding: 0.12rem 0.45rem 0.12rem 0.2rem;
    border-radius: 999px;
    background: #f3f4f6;
    color: #374151;
    font-size: 0.72rem;
    font-weight: 600;
    border: 1px solid #e5e7eb;
}
.inbox-chip-avatar {
    width: 16px; height: 16px; border-radius: 4px;
    display: inline-flex; align-items: center; justify-content: center;
    color: #fff; font-size: 0.55rem; font-weight: 700; flex-shrink: 0;
}
.inbox-chip span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.inbox-chip-add {
    width: 22px; height: 22px; border-radius: 6px;
    border: 1px dashed #d1d5db; background: #fff; color: #6b7280;
    cursor: pointer; font-size: 0.9rem; line-height: 1;
    display: inline-flex; align-items: center; justify-content: center;
}
.inbox-chip-add:hover { border-color: var(--inbox-accent); color: var(--inbox-accent); }
.inbox-participants-chip {
    cursor: pointer;
    border-style: solid;
}
.inbox-participants-chip .inbox-chip-avatar {
    margin-left: -4px;
}
.inbox-participants-chip .inbox-chip-avatar:first-child {
    margin-left: 0;
}
.inbox-participants-chip.is-open,
.inbox-participants-chip:hover {
    background: #eef2ff;
    border-color: #c7d2fe;
    color: #3730a3;
}
.inbox-pop.inbox-participants-pop {
    position: relative;
}
.inbox-participants-menu.inbox-pop-menu {
    left: 0;
    right: auto;
    width: 320px;
    min-width: 320px;
    max-width: min(320px, calc(100vw - 24px));
    padding: 0.55rem 0 0.45rem;
    display: flex;
    flex-direction: column;
    gap: 0;
    overflow: hidden;
    box-sizing: border-box;
}
.inbox-participants-head {
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: .03em;
    text-transform: uppercase;
    color: var(--inbox-muted);
    padding: 0.1rem 0.9rem 0.5rem;
    flex-shrink: 0;
}
.inbox-participants-list {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
    max-height: min(420px, 55vh);
    overflow-x: hidden;
    overflow-y: auto;
    padding: 0 0.35rem;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}
.inbox-participants-list::-webkit-scrollbar {
    width: 6px;
}
.inbox-participants-list::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 999px;
}
.inbox-participants-list::-webkit-scrollbar-track {
    background: transparent;
}
.inbox-participant-row {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.45rem 0.55rem;
    border-radius: 8px;
    min-width: 0;
    box-sizing: border-box;
    width: 100%;
}
.inbox-participant-row:hover { background: var(--inbox-bg); }
.inbox-participant-avatar {
    width: 28px; height: 28px; border-radius: 999px;
    display: inline-flex; align-items: center; justify-content: center;
    color: #fff; font-size: 0.7rem; font-weight: 700; flex: 0 0 28px;
}
.inbox-participant-name {
    flex: 1 1 auto;
    min-width: 0;
    font-size: 0.86rem;
    font-weight: 600;
    color: var(--inbox-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.inbox-participant-status {
    flex: 0 0 auto;
    max-width: 9.5rem;
    font-size: 0.75rem;
    color: var(--inbox-muted);
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.inbox-participant-status.is-read { color: #64748b; }
.inbox-participant-status.is-unread { color: #94a3b8; }
.inbox-participants-foot {
    margin-top: 0.35rem;
    padding: 0.55rem 0.9rem 0.25rem;
    border-top: 1px solid var(--inbox-border);
    font-size: 0.75rem;
    color: var(--inbox-muted);
    display: flex;
    align-items: flex-start;
    gap: 0.4rem;
    flex-shrink: 0;
    line-height: 1.35;
}
.inbox-participants-foot svg { flex: 0 0 14px; margin-top: 1px; }
.inbox-participants-foot span { min-width: 0; overflow-wrap: anywhere; }
.inbox-participants-foot strong { color: var(--inbox-text); font-weight: 600; }
.inbox-thread-actions {
    display: flex;
    gap: 0.4rem;
    align-items: center;
    flex-shrink: 0;
}
.inbox-icon-action {
    width: 34px; height: 34px; border-radius: 8px;
    border: 1px solid var(--inbox-border); background: #fff;
    color: #4b5563; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
}
.inbox-icon-action:hover, .inbox-icon-action.is-open { background: var(--inbox-bg); color: var(--inbox-text); }
.inbox-icon-action.is-active {
    background: #eef4ff;
    border-color: #c7d7fb;
    color: var(--inbox-accent, #2f6fed);
}
.inbox-icon-action svg { width: 16px; height: 16px; }
.inbox-assign-btn {
    padding: 0.38rem 0.7rem;
    gap: 0.3rem;
}
.inbox-assign-btn svg { width: 14px; height: 14px; }
.inbox-archive-btn { padding: 0.38rem 0.75rem; }
.inbox-archive-btn svg { width: 15px; height: 15px; }
.inbox-pop { position: relative; }
.inbox-pop-menu {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    z-index: 40;
    min-width: 200px;
    background: #fff;
    border: 1px solid var(--inbox-border);
    border-radius: 10px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.14);
    padding: 0.3rem;
    display: grid;
    gap: 0.08rem;
}
.inbox-pop-menu[hidden] { display: none !important; }
.inbox-pop-menu button {
    width: 100%;
    text-align: left;
    border: none;
    background: transparent;
    border-radius: 7px;
    padding: 0.45rem 0.55rem;
    font: inherit;
    font-size: 0.82rem;
    color: var(--inbox-text);
    cursor: pointer;
}
.inbox-pop-menu button:hover,
.inbox-pop-menu button.is-active { background: var(--inbox-accent-soft); color: var(--inbox-accent); }
.inbox-assign-menu { min-width: 240px; max-height: 280px; overflow: auto; }
.inbox-snooze-custom {
    display: grid;
    gap: 0.25rem;
    padding: 0.4rem 0.55rem 0.5rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--inbox-muted);
}
.inbox-snooze-custom input {
    width: 100%;
    border: 1px solid var(--inbox-border);
    border-radius: 7px;
    padding: 0.35rem 0.45rem;
    font: inherit;
    font-size: 0.78rem;
}
.inbox-messages {
    flex: 1;
    overflow: auto;
    padding: 0.85rem 1.1rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
    background: #f4f5f7;
}
.inbox-msg {
    border: 1px solid #e7e9ee;
    border-radius: 12px;
    padding: 0;
    background: #fff;
    box-shadow: 0 1px 0 rgba(15, 23, 42, 0.03);
}
.inbox-msg.outbound { background: #fff; }
.inbox-msg-row {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 0.55rem 0.8rem;
    cursor: pointer;
    min-width: 0;
}
.inbox-msg.is-expanded .inbox-msg-row { cursor: pointer; padding-bottom: 0.35rem; }
.inbox-msg-row:hover { background: #fafbfc; border-radius: 12px; }
.inbox-msg.is-expanded .inbox-msg-row:hover { background: transparent; }
.inbox-avatar {
    width: 28px; height: 28px; border-radius: 6px;
    display: inline-flex; align-items: center; justify-content: center;
    color: #fff; font-size: 0.68rem; font-weight: 700; flex-shrink: 0;
    letter-spacing: 0.02em;
}
.inbox-msg-summary {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: baseline;
    gap: 0.4rem;
}
.inbox-msg-from { font-weight: 650; color: var(--inbox-text); font-size: 0.84rem; white-space: nowrap; }
.inbox-msg-email { font-size: 0.78rem; color: #9ca3af; white-space: nowrap; }
.inbox-msg-preview {
    font-size: 0.8rem;
    color: #9ca3af;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
    flex: 1;
}
.inbox-msg:not(.is-expanded) .inbox-msg-head-actions { display: none; }
.inbox-msg.is-expanded .inbox-msg-preview { display: none; }
.inbox-msg-meta {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-shrink: 0;
    color: #9ca3af;
    font-size: 0.75rem;
}
.inbox-seen {
    color: #7c3aed;
    font-weight: 650;
    font-size: 0.72rem;
}
.inbox-msg-clip { display: inline-flex; color: #9ca3af; }
.inbox-msg-clip svg { width: 14px; height: 14px; }
.inbox-msg-time { white-space: nowrap; }
.inbox-msg-expanded { display: none; padding: 0 0.9rem 0.9rem 3.4rem; }
.inbox-msg.is-expanded .inbox-msg-expanded { display: block; }
.inbox-msg-recipients {
    font-size: 0.78rem;
    color: #6b7280;
    margin: 0 0 0.7rem;
    line-height: 1.45;
}
.inbox-msg-recipients strong { color: #9ca3af; font-weight: 600; margin-right: 0.25rem; }
.inbox-msg-head-actions {
    display: flex;
    align-items: center;
    gap: 0.2rem;
}
.inbox-msg-head-actions button {
    width: 28px; height: 28px; border: none; background: transparent;
    color: #9ca3af; border-radius: 6px; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
}
.inbox-msg-head-actions button:hover { background: #f3f4f6; color: var(--inbox-text); }
.inbox-msg-head-actions svg { width: 15px; height: 15px; }
.inbox-msg.internal { background: #fffbeb; border-color: #f3e8c8; }
.inbox-msg.internal .inbox-msg-from { color: #92400e; }
.inbox-composer { border-top: 1px solid var(--inbox-border); padding: 0.7rem 0.9rem 0.85rem; background: #fff; position: relative; flex-shrink: 0; }
.inbox-composer-card {
    border: 1px solid #e6e8ec;
    border-radius: 12px;
    background: #fff;
    padding: 0.15rem 0.25rem 0.25rem;
}
.inbox-composer-row {
    display: flex;
    align-items: flex-end;
    gap: 0.15rem;
    position: relative;
}
.inbox-composer .inbox-composer-editor {
    flex: 1;
    min-width: 0;
    border: none;
    min-height: 40px;
    max-height: 88px;
    padding: 0.55rem 0.65rem;
    border-radius: 10px;
    background: transparent;
    resize: none;
}
.inbox-composer.is-expanded .inbox-composer-editor { max-height: 220px; min-height: 88px; }
.inbox-composer-icons {
    display: flex;
    align-items: center;
    gap: 0.05rem;
    padding: 0.2rem 0.25rem 0.3rem 0;
    flex-shrink: 0;
}
.inbox-composer-icon {
    width: 30px; height: 30px; border: none; background: transparent;
    color: #6b7280; border-radius: 7px; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.88rem; font-weight: 700;
}
.inbox-composer-icon:hover { background: #f3f4f6; color: var(--inbox-text); }
.inbox-composer-icon svg { width: 16px; height: 16px; }
.inbox-composer:not(.is-expanded) .inbox-composer-bar { display: none; }
.inbox-composer .inbox-composer-bar { padding: 0 0.55rem 0.45rem; margin-top: 0.15rem; }
.inbox-composer .inbox-mention-popup {
    left: 0.5rem;
    right: auto;
    bottom: calc(100% + 4px);
    width: min(280px, 70vw);
}
.inbox-emoji-menu {
    display: flex;
    flex-wrap: wrap;
    min-width: 210px;
    gap: 0.15rem;
}
.inbox-emoji-menu button {
    width: 34px;
    text-align: center;
    font-size: 1.1rem;
    padding: 0.25rem;
}
.inbox-composer-modes {
    display: flex;
    gap: 0.15rem;
    margin-bottom: 0.45rem;
}
.inbox-msg-body {
    font-size: 0.9rem;
    line-height: 1.5;
    overflow-wrap: anywhere;
    color: var(--inbox-text);
}
.inbox-msg-body img { max-width: 100%; height: auto; }
.inbox-msg-body p { margin: 0 0 0.65em; }
.inbox-msg-body p:last-child { margin-bottom: 0; }
.inbox-msg-body a { color: var(--inbox-accent); }
.inbox-msg-body ul, .inbox-msg-body ol { margin: 0.35em 0 0.65em; padding-left: 1.35em; }
.inbox-msg-body blockquote {
    margin: 0.5em 0;
    padding: 0.15em 0 0.15em 0.75em;
    border-left: 3px solid var(--inbox-border);
    color: var(--inbox-muted);
}
.inbox-msg-body table { max-width: 100%; border-collapse: collapse; }
.inbox-msg-body pre, .inbox-msg-body code {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.84em;
}
.inbox-msg-body .inbox-email-signature {
    margin-top: 0.75rem;
    padding-top: 0.65rem;
    border-top: 1px solid var(--inbox-border);
}
.inbox-composer-mode {
    border: 1px solid var(--inbox-border);
    background: #fff;
    color: var(--inbox-muted);
    border-radius: 999px;
    padding: 0.28rem 0.75rem;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
}
.inbox-composer-mode:hover { color: var(--inbox-text); border-color: #c5cedb; }
.inbox-composer-mode.is-active {
    background: var(--inbox-accent-soft);
    border-color: var(--inbox-accent);
    color: var(--inbox-accent);
}
.inbox-composer-mode:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.inbox-msg.internal {
    background: #fffbeb;
    border-color: #f6e05e;
}
.inbox-msg.activity {
    border: none;
    background: transparent;
    padding: 0.15rem 0.25rem;
    box-shadow: none;
}
.inbox-activity-line {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    justify-content: center;
    gap: 0.45rem 0.75rem;
    text-align: center;
    font-size: 0.78rem;
    color: var(--inbox-muted);
    padding: 0.35rem 0.5rem;
}
.inbox-activity-line strong { color: var(--inbox-text); font-weight: 600; }
.inbox-activity-time { white-space: nowrap; opacity: 0.85; }
.inbox-history-list {
    display: grid;
    gap: 0.55rem;
    max-height: 280px;
    overflow: auto;
}
.inbox-history-item {
    font-size: 0.78rem;
    color: var(--inbox-text);
    line-height: 1.35;
}
.inbox-history-item time {
    display: block;
    margin-top: 0.15rem;
    color: var(--inbox-muted);
    font-size: 0.7rem;
}
.inbox-msg.internal .inbox-msg-label {
    display: inline-block;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #92400e;
    background: #fef3c7;
    border-radius: 999px;
    padding: 0.1rem 0.45rem;
    margin-right: 0.4rem;
}
.inbox-msg-attachments {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-top: 0.55rem;
}
.inbox-msg-attach {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border: 1px solid var(--inbox-border);
    border-radius: 8px;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    color: var(--inbox-accent);
    text-decoration: none;
    background: #fff;
}
.inbox-msg-attach:hover { background: var(--inbox-accent-soft); }
.inbox-composer textarea,
.inbox-composer-editor {
    width: 100%; border: 1px solid var(--inbox-border); border-radius: 10px;
    padding: 0.75rem; resize: vertical; min-height: 84px; font: inherit;
    background: #fff; color: var(--inbox-text); line-height: 1.5;
    overflow: auto; -webkit-overflow-scrolling: touch;
}
.inbox-composer-editor {
    max-height: 240px;
    outline: none;
}
.inbox-composer-editor.form-input { min-height: 160px; max-height: 280px; }
.inbox-composer-editor:empty:before {
    content: attr(data-placeholder);
    color: var(--inbox-muted);
    pointer-events: none;
}
.inbox-composer-editor .inbox-mention {
    color: var(--inbox-accent);
    font-weight: 600;
    background: var(--inbox-accent-soft);
    border-radius: 4px;
    padding: 0 0.15rem;
}
.inbox-composer-editor img { max-width: 100%; height: auto; }
.inbox-composer-tools {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.45rem;
}
.inbox-composer-tool {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border: 1px solid var(--inbox-border);
    background: #fff;
    color: var(--inbox-text);
    border-radius: 8px;
    padding: 0.3rem 0.55rem;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
}
.inbox-composer-tool svg {
    width: 1em;
    height: 1em;
    flex-shrink: 0;
}
.inbox-composer-editor .inbox-email-signature,
.inbox-composer-editor[contenteditable] .inbox-email-signature {
    margin-top: 0.75rem;
    padding-top: 0.65rem;
    border-top: 1px solid var(--inbox-border);
    color: var(--inbox-text);
}
.inbox-tool-row.is-default-signature .inbox-tool-row-title {
    font-weight: 600;
}
.inbox-composer-tool:hover { border-color: var(--inbox-accent); color: var(--inbox-accent); background: var(--inbox-accent-soft); }
.inbox-template-picker { position: relative; }
.inbox-template-picker.is-open { z-index: 40; }
.inbox-template-picker-menu {
    display: none;
    position: fixed;
    width: min(320px, calc(100vw - 16px));
    max-height: min(360px, calc(100vh - 24px));
    background: #fff;
    border: 1px solid var(--inbox-border);
    border-radius: 12px;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.18);
    padding: 0.55rem;
    gap: 0.4rem;
    z-index: 90;
    box-sizing: border-box;
    overflow: hidden;
    grid-template-rows: auto auto minmax(0, 1fr);
}
.inbox-template-picker.is-open .inbox-template-picker-menu {
    display: grid;
}
.inbox-template-picker-head {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--inbox-muted);
    padding: 0.05rem 0.2rem 0.1rem;
}
.inbox-template-picker-menu .inbox-tool-search {
    margin: 0;
    font-size: 0.82rem;
    padding: 0.45rem 0.6rem;
    border-radius: 8px;
}
.inbox-template-picker-list {
    display: grid;
    align-content: start;
    gap: 0.12rem;
    min-height: 0;
    overflow: auto;
    -webkit-overflow-scrolling: touch;
}
.inbox-template-picker-item {
    width: 100%;
    border: none;
    background: transparent;
    text-align: left;
    padding: 0.45rem 0.55rem;
    border-radius: 8px;
    cursor: pointer;
    font: inherit;
    color: var(--inbox-text);
    display: grid;
    gap: 0.12rem;
}
.inbox-template-picker-item-name {
    font-size: 0.84rem;
    font-weight: 600;
    line-height: 1.3;
}
.inbox-template-picker-item-meta {
    font-size: 0.74rem;
    color: var(--inbox-muted);
    line-height: 1.35;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.inbox-template-picker-item:hover,
.inbox-template-picker-item.is-active { background: var(--inbox-accent-soft); color: var(--inbox-accent); }
.inbox-template-picker-item:hover .inbox-template-picker-item-meta,
.inbox-template-picker-item.is-active .inbox-template-picker-item-meta { color: var(--inbox-accent); }
.inbox-template-picker-empty {
    padding: 1.1rem 0.6rem;
    font-size: 0.8rem;
    color: var(--inbox-muted);
    text-align: center;
}
.inbox-template-manage-btn {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    width: 100%;
    margin: 0;
    padding: 0.45rem 0.55rem;
    border: 1px solid var(--inbox-border);
    border-radius: 8px;
    background: #fff;
    color: var(--inbox-text);
    font: inherit;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    text-align: left;
}
.inbox-template-manage-btn svg {
    width: 15px;
    height: 15px;
    flex-shrink: 0;
    color: var(--inbox-muted);
}
.inbox-template-manage-btn:hover {
    border-color: var(--inbox-accent);
    background: var(--inbox-accent-soft);
}
.inbox-template-count {
    margin-left: auto;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--inbox-muted);
    background: var(--inbox-bg);
    border-radius: 999px;
    padding: 0.1rem 0.45rem;
    flex-shrink: 0;
}
.inbox-template-count:empty { display: none; }
.inbox-modal.inbox-modal-list {
    width: min(560px, 92vw);
    max-width: 92vw;
}
.inbox-tpl-list-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
}
.inbox-tpl-list-head-text { min-width: 0; }
.inbox-tpl-close-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--inbox-muted);
    cursor: pointer;
    flex-shrink: 0;
    padding: 0;
}
.inbox-tpl-close-btn svg { width: 18px; height: 18px; }
.inbox-tpl-close-btn:hover { background: var(--inbox-bg); color: var(--inbox-text); }
.inbox-tpl-list-toolbar {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}
.inbox-tpl-search-wrap {
    position: relative;
    flex: 1;
    min-width: 0;
}
.inbox-tpl-search-icon {
    position: absolute;
    left: 0.65rem;
    top: 50%;
    transform: translateY(-50%);
    width: 15px;
    height: 15px;
    color: #9ca3af;
    pointer-events: none;
}
.inbox-tpl-search {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid var(--inbox-border);
    border-radius: 8px;
    padding: 0.5rem 0.65rem 0.5rem 2.1rem;
    font: inherit;
    font-size: 0.84rem;
    background: #fff;
    color: var(--inbox-text);
    outline: none;
    appearance: none;
    -webkit-appearance: none;
}
.inbox-tpl-search:focus {
    border-color: var(--inbox-accent);
    box-shadow: 0 0 0 3px rgba(47, 111, 237, 0.12);
}
.inbox-tpl-search::-webkit-search-cancel-button { -webkit-appearance: none; }
.inbox-tpl-list-shell {
    border: 1px solid var(--inbox-border);
    border-radius: 10px;
    background: var(--inbox-bg);
    overflow: hidden;
    min-height: 200px;
    max-height: min(52vh, 420px);
    display: flex;
    flex-direction: column;
}
.inbox-tpl-list {
    flex: 1;
    overflow: auto;
    display: flex;
    flex-direction: column;
}
.inbox-tpl-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.7rem 0.85rem;
    background: #fff;
    border-bottom: 1px solid var(--inbox-border);
}
.inbox-tpl-row:last-child { border-bottom: 0; }
.inbox-tpl-row:hover { background: #f9fafb; }
.inbox-tpl-row-main { flex: 1; min-width: 0; }
.inbox-tpl-row-name {
    font-size: 0.86rem;
    font-weight: 700;
    color: var(--inbox-text);
    margin-bottom: 0.1rem;
}
.inbox-tpl-row-subject {
    font-size: 0.76rem;
    color: var(--inbox-muted);
    margin-bottom: 0.12rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.inbox-tpl-row-preview {
    font-size: 0.78rem;
    line-height: 1.4;
    color: var(--inbox-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.inbox-tpl-row-actions {
    display: flex;
    align-items: center;
    gap: 0.1rem;
    flex-shrink: 0;
}
.inbox-tpl-link-btn {
    border: 0;
    background: transparent;
    padding: 0.35rem 0.55rem;
    border-radius: 6px;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--inbox-accent);
    cursor: pointer;
}
.inbox-tpl-link-btn:hover { background: var(--inbox-accent-soft); }
.inbox-tpl-link-btn.muted { color: var(--inbox-muted); }
.inbox-tpl-link-btn.muted:hover { background: var(--inbox-bg); color: var(--inbox-text); }
.inbox-tpl-empty {
    font-size: 0.84rem;
    color: var(--inbox-muted);
    padding: 2.5rem 1rem;
    text-align: center;
    background: #fff;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}
.inbox-tpl-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    flex-wrap: wrap;
}
.inbox-tpl-pagination[hidden] { display: none !important; }
.inbox-tpl-pagination-info {
    font-size: 0.78rem;
    color: var(--inbox-muted);
}
.inbox-tpl-pagination-controls {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    margin-left: auto;
}
.inbox-tpl-page-status {
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--inbox-muted);
    min-width: 5.5rem;
    text-align: center;
}
.inbox-tpl-page-btn {
    border: 1px solid var(--inbox-border);
    background: #fff;
    border-radius: 8px;
    padding: 0.35rem 0.65rem;
    font: inherit;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--inbox-text);
    cursor: pointer;
}
.inbox-tpl-page-btn:hover:not(:disabled) {
    border-color: var(--inbox-accent);
    color: var(--inbox-accent);
}
.inbox-tpl-page-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.inbox-tpl-row.is-default-signature {
    background: var(--inbox-accent-soft);
}
.inbox-tpl-row.is-default-signature:hover {
    background: var(--inbox-accent-soft);
}
.inbox-tpl-default-badge {
    display: inline-flex;
    align-items: center;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--inbox-accent);
    background: rgba(47, 111, 237, 0.1);
    border-radius: 999px;
    padding: 0.12rem 0.45rem;
    margin-left: 0.35rem;
    vertical-align: middle;
}
.inbox-attach-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
    margin-bottom: 0.4rem;
}
.inbox-attach-chips:empty { display: none; }
.inbox-attach-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    max-width: 100%;
    border: 1px solid var(--inbox-border);
    background: var(--inbox-bg);
    border-radius: 999px;
    padding: 0.15rem 0.45rem 0.15rem 0.55rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--inbox-text);
}
.inbox-attach-chip span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 180px;
}
.inbox-attach-chip button {
    border: none;
    background: transparent;
    color: var(--inbox-muted);
    cursor: pointer;
    font-size: 0.9rem;
    line-height: 1;
    padding: 0;
}
.inbox-mention-popup {
    position: absolute;
    left: 1rem;
    right: 1rem;
    bottom: calc(100% - 0.5rem);
    z-index: 20;
    max-height: 200px;
    overflow: auto;
    background: #fff;
    border: 1px solid var(--inbox-border);
    border-radius: 10px;
    box-shadow: 0 12px 28px rgba(15, 23, 42, .14);
    padding: 0.25rem;
}
.inbox-mention-popup[hidden] { display: none !important; }
.inbox-mention-item {
    width: 100%;
    text-align: left;
    border: none;
    background: transparent;
    border-radius: 8px;
    padding: 0.45rem 0.55rem;
    cursor: pointer;
    display: grid;
    gap: 0.1rem;
    font: inherit;
}
.inbox-mention-item:hover,
.inbox-mention-item.is-active { background: var(--inbox-accent-soft); }
.inbox-mention-name { font-size: 0.82rem; font-weight: 600; color: var(--inbox-text); }
.inbox-mention-email { font-size: 0.72rem; color: var(--inbox-muted); }
.inbox-composer-bar { display: flex; justify-content: space-between; align-items: center; margin-top: 0.55rem; gap: 0.75rem; }
.inbox-composer-hint { font-size: 0.75rem; color: var(--inbox-muted); }
.inbox-modal-actions .inbox-composer-hint { margin-right: auto; align-self: center; }
.inbox-send-group {
    --inbox-send-bg: var(--inbox-accent, #2f6fed);
    position: relative;
    display: inline-flex;
    align-items: stretch;
    height: 36px;
    border-radius: 8px;
    background: var(--inbox-send-bg);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
    overflow: visible;
    flex-shrink: 0;
}
.inbox-send-group:has(.inbox-send-main:disabled),
.inbox-send-group:has(.inbox-send-caret:disabled) {
    opacity: 0.55;
}
.inbox-send-main,
.inbox-send-caret {
    appearance: none;
    -webkit-appearance: none;
    margin: 0;
    border: 0;
    background: transparent;
    color: #fff;
    font: inherit;
    font-size: 0.84rem;
    font-weight: 600;
    line-height: 1;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    transition: background 0.12s ease;
}
.inbox-send-main {
    padding: 0 0.95rem 0 1rem;
    border-radius: 8px 0 0 8px;
    white-space: nowrap;
}
.inbox-send-caret {
    width: 32px;
    padding: 0;
    border-radius: 0 8px 8px 0;
    border-left: 1px solid rgba(255, 255, 255, 0.22);
}
.inbox-send-caret svg {
    width: 14px;
    height: 14px;
    display: block;
}
.inbox-send-main:hover,
.inbox-send-caret:hover,
.inbox-send-caret.is-open {
    background: rgba(0, 0, 0, 0.12);
}
.inbox-send-main:focus-visible,
.inbox-send-caret:focus-visible {
    outline: 2px solid #fff;
    outline-offset: -3px;
    z-index: 1;
}
.inbox-send-main:disabled,
.inbox-send-caret:disabled {
    cursor: not-allowed;
}
.inbox-send-menu {
    right: 0;
    left: auto;
    min-width: 220px;
    bottom: calc(100% + 8px);
    top: auto;
}
.inbox-send-later {
    display: grid;
    gap: 0.25rem;
    padding: 0.4rem 0.55rem 0.5rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--inbox-muted);
    border-top: 1px solid var(--inbox-border);
    margin-top: 0.15rem;
}
.inbox-send-later[hidden] { display: none !important; }
.inbox-send-later input {
    width: 100%;
    border: 1px solid var(--inbox-border);
    border-radius: 7px;
    padding: 0.35rem 0.45rem;
    font: inherit;
    font-size: 0.78rem;
}
.inbox-send-menu .inbox-send-later .inbox-btn {
    width: 100%;
    margin-top: 0.25rem;
    text-align: center;
    justify-content: center;
    background: var(--inbox-accent, #2f6fed);
    border: 1px solid var(--inbox-accent, #2f6fed);
    color: #fff;
    border-radius: 8px;
    padding: 0.4rem 0.65rem;
    font-weight: 600;
}
.inbox-compose-send-menu {
    bottom: auto;
    top: calc(100% + 8px);
}
.inbox-msg.scheduled {
    border-style: dashed;
    background: #f8fafc;
}
.inbox-msg.scheduled .inbox-msg-preview { color: var(--inbox-muted); }
.inbox-scheduled-actions {
    display: flex;
    gap: 0.35rem;
    align-items: center;
    margin-top: 0.45rem;
}
.inbox-scheduled-actions .inbox-btn { font-size: 0.75rem; padding: 0.28rem 0.65rem; }
.inbox-modal .inbox-composer-tools { margin-top: 0.15rem; }
.inbox-modal .inbox-mention-popup {
    position: static;
    margin-bottom: 0.4rem;
    bottom: auto;
    left: auto;
    right: auto;
}
.inbox-props-block { margin-bottom: 1.25rem; }
.inbox-props-label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: .05em; color: var(--inbox-muted); font-weight: 600; margin-bottom: 0.4rem; }
.inbox-select, .inbox-modal .form-input {
    width: 100%; border: 1px solid var(--inbox-border); border-radius: 8px; padding: 0.5rem 0.65rem; font-size: 0.84rem; background: #fff;
}
.inbox-prop-value { font-size: 0.88rem; }
.inbox-prop-lead { margin-top: 0.5rem; display: grid; gap: 0.4rem; }
.inbox-prop-lead .inbox-btn { font-size: 0.78rem; padding: 0.28rem 0.7rem; }
.inbox-attach-lead { display: grid; gap: 0.3rem; }
.inbox-attach-lead .form-input { width: 100%; }
.inbox-props .chp-save-lead { display: inline-block; margin-top: 0.45rem; padding: 0.28rem 0.6rem; border: 1px solid #0b5cab; border-radius: 6px; background: #fff; color: #0b5cab; font-size: 0.78rem; font-weight: 600; cursor: pointer; }
.inbox-props .chp-panel { display: block !important; width: 100%; max-width: none; border: 0; background: transparent; height: auto; min-height: 0; }
.inbox-props .chp-body { padding: 0; overflow: visible; }
.inbox-props .chp-name { font-weight: 700; font-size: 0.95rem; margin-bottom: 0.25rem; }
.inbox-props .chp-meta { font-size: 0.8rem; color: var(--inbox-muted); margin: 0.15rem 0; word-break: break-all; }
.inbox-props .chp-assigned { font-size: 0.8rem; font-weight: 600; color: #0b5cab; margin: 0.15rem 0; }
.inbox-props .chp-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--inbox-muted); margin: 0.85rem 0 0.4rem; }
.inbox-props .chp-item { display: block; text-decoration: none; color: inherit; padding: 0.5rem 0; border-bottom: 1px solid var(--inbox-border); }
.inbox-props .chp-item-title { font-size: 0.84rem; font-weight: 600; margin: 0.2rem 0; }
.inbox-props .chp-item-preview { font-size: 0.78rem; color: var(--inbox-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.inbox-props .chp-event { padding: 0.4rem 0; border-bottom: 1px solid var(--inbox-border); }
.inbox-props .chp-dir { font-size: 0.72rem; color: var(--inbox-muted); }
.inbox-props .chp-empty { font-size: 0.84rem; color: var(--inbox-muted); margin: 0; }
.inbox-props .chp-link { display: inline-block; margin-top: 0.4rem; font-size: 0.82rem; font-weight: 600; color: #0b5cab; text-decoration: none; }
.inbox-props .chp-badge { display: inline-block; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; padding: 0.12rem 0.4rem; border-radius: 4px; background: #fff4e5; color: #b45309; }
.inbox-props .chp-badge.whatsapp { background: #e8f8ef; color: #128c7e; }
.inbox-props .chp-badge.viber { background: #efeaff; color: #5b3cc4; }
.inbox-props .chp-badge.sms { background: #e8f8ef; color: #0f7b4c; }
.inbox-props .chp-badge.call { background: #f1f3f5; color: #495057; }
.inbox-props .chp-badge.facebook { background: #e8f1ff; color: #1877f2; }
.inbox-tag-pills { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 0.5rem; min-height: 1.25rem; }
.inbox-lead-label-add { display: flex; gap: 0.4rem; margin-top: 0.45rem; align-items: stretch; }
.inbox-lead-label-add input { flex: 1; min-width: 0; }
.inbox-modal-backdrop {
    position: fixed; inset: 0; background: rgba(15, 23, 42, .35); z-index: 80;
    display: flex; align-items: center; justify-content: center; padding: 1rem;
    overflow: auto;
}
.inbox-modal {
    width: min(480px, 100%); background: #fff; border-radius: 14px; padding: 1.25rem;
    box-shadow: 0 24px 60px rgba(0,0,0,.18); display: grid; gap: 0.75rem;
    max-height: min(90vh, 900px);
    overflow-x: hidden;
    overflow-y: auto;
    overscroll-behavior: contain;
}
.inbox-modal.inbox-modal-xwide,
.inbox-modal#modalTemplate,
.inbox-modal#modalSignature {
    width: min(1200px, 92vw);
    max-width: 92vw;
}
.inbox-modal#modalReply {
    width: min(880px, 94vw);
    max-width: 94vw;
}
.inbox-modal h3 { margin: 0; }
.inbox-modal-help { margin: 0; color: var(--inbox-muted); font-size: 0.84rem; }
.inbox-modal label { display: grid; gap: 0.3rem; font-size: 0.8rem; font-weight: 600; color: #374151; }
.inbox-modal-actions { display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 0.5rem; position: sticky; bottom: -0.25rem; background: #fff; padding-top: 0.35rem; }
.inbox-rule-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem; }

.inbox-rule-name-label { display: block; margin-bottom: 1.1rem; }
.inbox-rule-name-input {
    margin-top: 0.35rem;
    background: #f3f4f6 !important;
    border-color: transparent !important;
}
.inbox-rule-section { margin: 1.15rem 0 0.85rem; }
.inbox-rule-section-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--inbox-text);
    margin-bottom: 0.55rem;
}
.inbox-rule-card {
    border: 1px solid var(--inbox-border);
    border-radius: 10px;
    background: #fff;
    padding: 0.85rem 1rem;
}
.inbox-rule-pill-row {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    flex-wrap: wrap;
}
.inbox-rule-pill-text {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--inbox-text);
}
.inbox-rule-info {
    width: 16px;
    height: 16px;
    border-radius: 999px;
    border: 1px solid #c5cedb;
    color: var(--inbox-muted);
    font-size: 0.68rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: help;
}
.inbox-rule-add {
    display: inline-flex;
    margin-top: 0.55rem;
    border: 0;
    background: transparent;
    color: var(--inbox-accent);
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0.2rem 0;
}
.inbox-rule-add:hover { text-decoration: underline; }
.inbox-rule-add:disabled {
    color: #9aa3b2;
    cursor: not-allowed;
    text-decoration: none;
}
.inbox-rule-extra-list {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
    margin-top: 0.55rem;
}
.inbox-rule-extra-card {
    border: 1px solid var(--inbox-border);
    border-radius: 10px;
    background: #fff;
    padding: 0.75rem 0.85rem;
    display: grid;
    grid-template-columns: 1fr 1fr 1.2fr auto;
    gap: 0.5rem;
    align-items: center;
}
.inbox-rule-extra-card.is-trigger {
    grid-template-columns: 1fr auto;
}
.inbox-rule-extra-card.is-action {
    grid-template-columns: 1.2fr 1.4fr auto;
}
.inbox-rule-trigger-help {
    margin: 0.15rem 0 0;
    font-size: 0.75rem;
    color: var(--inbox-muted);
    font-weight: 400;
}
.inbox-rule-extra-card .form-input { margin: 0; }
.inbox-rule-remove {
    border: 0;
    background: transparent;
    color: var(--inbox-muted);
    font-size: 1.1rem;
    line-height: 1;
    cursor: pointer;
    padding: 0.2rem 0.35rem;
}
.inbox-rule-remove:hover { color: #b91c1c; }
.inbox-rule-inbox-row { justify-content: flex-start; }
.inbox-rule-inbox-picker { position: relative; min-width: 220px; }
.inbox-rule-inbox-toggle {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    border: 1px solid var(--inbox-border);
    border-radius: 8px;
    background: #fff;
    padding: 0.45rem 0.7rem;
    font-size: 0.86rem;
    color: var(--inbox-text);
    cursor: pointer;
}
.inbox-rule-chevron { color: var(--inbox-muted); font-size: 0.75rem; }
.inbox-rule-inbox-menu {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: 30;
    background: #fff;
    border: 1px solid var(--inbox-border);
    border-radius: 10px;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.12);
    max-height: 220px;
    overflow: auto;
    padding: 0.35rem;
}
.inbox-rule-inbox-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 0.55rem;
    border-radius: 8px;
    font-size: 0.84rem;
    cursor: pointer;
}
.inbox-rule-inbox-option:hover { background: var(--inbox-bg); }
.inbox-rule-stop {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    margin: 1.1rem 0 0.35rem;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--inbox-text);
    cursor: pointer;
}
.inbox-rule-stop input { width: 16px; height: 16px; }

@media (max-width: 720px) {
    .inbox-rule-extra-card,
    .inbox-rule-extra-card.is-action {
        grid-template-columns: 1fr;
    }
}
.inbox-member-row { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.35rem 0; border-bottom: 1px solid var(--inbox-border); font-size: 0.84rem; }
@media (max-width: 1100px) {
    .inbox-shell,
    .inbox-shell.with-props { grid-template-columns: 260px 300px minmax(0, 1fr); }
    .inbox-props {
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        width: min(280px, 86vw);
        z-index: 20;
        box-shadow: -12px 0 28px rgba(15, 23, 42, 0.12);
        background: var(--inbox-panel);
    }
    .inbox-shell:not(.with-props) .inbox-props { display: none !important; }
}
@media (max-width: 860px) {
    .inbox-page-wrapper,
    .inbox-app { height: auto; min-height: calc(100vh - 64px); overflow: visible; }
    .inbox-shell,
    .inbox-shell.with-props { grid-template-columns: 1fr; height: auto; }
    .inbox-nav, .inbox-list-pane { max-height: 360px; }
    .inbox-adv-grid { grid-template-columns: 1fr; }
}
</style>

<script>
(function () {
    const root = document.getElementById('inboxApp');
    if (!root) return;
    const API = root.dataset.api;
    const CSRF = root.dataset.csrf;
    const CONNECT = root.dataset.connect;
    const USER_ID = Number(root.dataset.userId || 0);

    const MAILBOX_FOLDERS = [
        { view: 'open', label: 'Inbox', countKey: 'open_count' },
        { view: 'archived', label: 'Archived', countKey: 'archived_count' },
        { view: 'snoozed', label: 'Snoozed', countKey: 'snoozed_count' },
        { view: 'drafts', label: 'Drafts', countKey: 'drafts_count' },
        { view: 'sent', label: 'Sent', countKey: 'sent_count' },
        { view: 'trash', label: 'Trash', countKey: 'trash_count' },
        { view: 'spam', label: 'Spam', countKey: 'spam_count' },
    ];

    const state = {
        inboxes: [],
        rules: [],
        templates: [],
        pendingLocalTemplates: [],
        signatures: [],
        defaultSignatureId: null,
        permissions: {
            create_templates: false,
            create_rules: false,
        },
        members: [],
        leadLabels: [],
        conversations: [],
        checkedIds: [],
        selectedInboxId: null,
        view: 'open',
        selectedId: null,
        conversation: null,
        editingMembersInboxId: null,
        searchTimer: null,
        expandedInboxIds: {},
        expandedToolGroups: { templates: true, signatures: false, rules: false },
        inboxToolsOpen: true,
        listPage: 1,
        listLastPage: 1,
        listLoading: false,
        listHasMore: true,
        syncingInboxId: null,
        autoSyncRunning: false,
        autoSyncTimer: null,
        advancedOpen: false,
        replyAttachments: [],
        composeAttachments: [],
        commentAttachments: [],
        templateAttachments: [],
        composerMode: 'comment',
        composerCanReply: true,
        composerExpanded: false,
        propsOpen: false,
        expandedMessageIds: {},
        replyAll: false,
        replyCcEmails: [],
        replyDraftId: null,
        editingTemplateId: null,
        templateSearch: '',
        templateListPage: 1,
        returnToTemplateList: false,
        signatureSearch: '',
        signatureListPage: 1,
        returnToSignatureList: false,
        editingSignatureId: null,
        filters: {
            from: '',
            to: '',
            subject: '',
            body: '',
            folder: '',
            inbox_id: '',
            assigned_to: '',
            is_read: '',
            date_from: '',
            date_to: '',
        },
    };

    const TOOLS_STORAGE_KEY = 'lnscrm_inbox_tools_v1';

    function loadLocalTools() {
        try {
            const raw = localStorage.getItem(TOOLS_STORAGE_KEY);
            if (!raw) return;
            const data = JSON.parse(raw);
            // Templates are company-shared in the database; only keep personal prefs locally.
            state.pendingLocalTemplates = (Array.isArray(data.templates) ? data.templates : []).map(t => ({
                ...t,
                body_html: t.body_html || (t.body && /<[a-z][\s\S]*>/i.test(t.body) ? t.body : null),
                format: 'html',
            }));
            state.signatures = (Array.isArray(data.signatures) ? data.signatures : []).map(s => ({
                ...s,
                body_html: s.body_html || (s.body && /<[a-z][\s\S]*>/i.test(s.body) ? s.body : null),
                format: 'html',
            }));
            state.defaultSignatureId = data.defaultSignatureId || (state.signatures[0]?.id ?? null);
            if (state.defaultSignatureId && !state.signatures.some(s => String(s.id) === String(state.defaultSignatureId))) {
                state.defaultSignatureId = state.signatures[0]?.id ?? null;
            }
        } catch (_) {
            state.pendingLocalTemplates = [];
            state.signatures = [];
            state.defaultSignatureId = null;
        }
    }

    function saveLocalTools() {
        localStorage.setItem(TOOLS_STORAGE_KEY, JSON.stringify({
            // Keep empty templates array so older clients don't crash; source of truth is the API.
            templates: [],
            signatures: state.signatures,
            defaultSignatureId: state.defaultSignatureId,
        }));
    }

    function sanitizeHtml(html) {
        const wrap = document.createElement('div');
        wrap.innerHTML = String(html || '');
        wrap.querySelectorAll('script,iframe,object,embed,link,meta').forEach(n => n.remove());
        wrap.querySelectorAll('*').forEach(node => {
            [...node.attributes].forEach(attr => {
                const name = attr.name.toLowerCase();
                const value = String(attr.value || '');
                if (name.startsWith('on') || (name === 'href' && /^\s*javascript:/i.test(value))) {
                    node.removeAttribute(attr.name);
                }
            });
        });
        return wrap.innerHTML;
    }

    function decodeEscapedHtml(html) {
        const value = String(html || '');
        if (!value) return '';
        if (!/<[a-z][\s\S]*>/i.test(value) && /&lt;[a-z]/i.test(value)) {
            const tmp = document.createElement('textarea');
            tmp.innerHTML = value;
            return tmp.value;
        }
        return value;
    }

    function plainToHtml(text) {
        const t = String(text || '').trim();
        if (!t) return '';
        if (/<[a-z][\s\S]*>/i.test(t)) return sanitizeHtml(t);
        return sanitizeHtml(t.replace(/\r\n|\n|\r/g, '<br>'));
    }

    function extractEmailDocumentParts(html) {
        const source = decodeEscapedHtml(String(html || '').trim());
        if (!source) return { styles: '', body: '' };

        const looksLikeDocument = /<!DOCTYPE/i.test(source)
            || /<html[\s>]/i.test(source)
            || /<body[\s>]/i.test(source)
            || /<head[\s>]/i.test(source);

        if (!looksLikeDocument) {
            return { styles: '', body: sanitizeHtml(source) };
        }

        try {
            const doc = new DOMParser().parseFromString(source, 'text/html');
            doc.querySelectorAll('script,iframe,object,embed,link,meta').forEach(n => n.remove());
            doc.querySelectorAll('*').forEach(node => {
                [...node.attributes].forEach(attr => {
                    const name = attr.name.toLowerCase();
                    const value = String(attr.value || '');
                    if (name.startsWith('on') || (name === 'href' && /^\s*javascript:/i.test(value))) {
                        node.removeAttribute(attr.name);
                    }
                });
            });

            const styles = [...doc.querySelectorAll('style')]
                .map(node => String(node.textContent || ''))
                .filter(Boolean)
                .join('\n')
                // Email CSS often targets body/html — remap to the shadow root wrapper.
                .replace(/(^|[,{\s])(?:html|body)\b/gi, '$1.email-root');
            doc.querySelectorAll('style').forEach(node => node.remove());

            return {
                styles,
                body: doc.body ? doc.body.innerHTML : sanitizeHtml(source),
            };
        } catch (err) {
            return { styles: '', body: sanitizeHtml(source) };
        }
    }

    const EMAIL_QUOTE_SELECTORS = [
        '.gmail_quote',
        '.gmail_quote_container',
        '.gmail_extra',
        '.gmail_attr',
        '#divRplyFwdMsg',
        '#x_divRplyFwdMsg',
        '[id$="divRplyFwdMsg"]',
        '#appendonsend',
        '[id$="appendonsend"]',
        '#OLK_SRC_BODY_SECTION',
        '.OutlookMessageHeader',
        'blockquote.gmail_quote',
        'blockquote[type="cite"]',
        '.moz-cite-prefix',
        '#yahoo_quoted',
        '.yahoo_quoted',
        '.protonmail_quote',
    ].join(',');

    function emailNodeMeaningfulText(node) {
        if (!node) return '';
        if (node.nodeType === Node.TEXT_NODE) return String(node.textContent || '').replace(/\s+/g, ' ').trim();
        if (node.nodeType !== Node.ELEMENT_NODE) return '';
        const clone = node.cloneNode(true);
        clone.querySelectorAll('style,script').forEach(n => n.remove());
        return String(clone.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function looksLikeQuotedReplyHeader(text) {
        const t = String(text || '').replace(/\s+/g, ' ').trim();
        if (!t) return false;
        if (/^-----Original Message-----/i.test(t)) return true;
        if (/^-----Forwarded message-----/i.test(t)) return true;
        if (/^_{8,}/.test(t)) return true;
        if (/^On .{8,160} wrote:\s*$/i.test(t)) return true;
        if (/^(From|Van|De|Von|Da)\s*:/i.test(t) && /(Sent|Date|Verzonden|To|À|An|Subject|Onderwerp)\s*:/i.test(t)) return true;
        return false;
    }

    function isEmailQuoteStartNode(node) {
        if (!node) return false;
        if (node.nodeType === Node.TEXT_NODE) {
            return looksLikeQuotedReplyHeader(node.textContent);
        }
        if (node.nodeType !== Node.ELEMENT_NODE) return false;
        if (node.matches?.(EMAIL_QUOTE_SELECTORS)) return true;
        const tag = node.tagName.toLowerCase();
        if (tag === 'hr') {
            let next = node.nextSibling;
            while (next && ((next.nodeType === Node.TEXT_NODE && !String(next.textContent || '').trim()) || next.nodeType === Node.COMMENT_NODE)) {
                next = next.nextSibling;
            }
            if (next && looksLikeQuotedReplyHeader(emailNodeMeaningfulText(next).slice(0, 500))) return true;
        }
        const own = emailNodeMeaningfulText(node);
        if (own && own.length < 800 && looksLikeQuotedReplyHeader(own)) return true;
        return false;
    }

    function isEmptyQuoteBoundary(node) {
        if (!node || node.nodeType !== Node.ELEMENT_NODE) return false;
        if (!node.matches?.('#appendonsend, [id$="appendonsend"]')) return false;
        return !emailNodeMeaningfulText(node) && !node.querySelector?.('img');
    }

    function findEmailQuoteStart(root) {
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT);
        let sawReplyText = false;
        let node = walker.nextNode();
        while (node) {
            if (isEmptyQuoteBoundary(node)) {
                if (sawReplyText) return node;
                node = walker.nextNode();
                continue;
            }
            if (isEmailQuoteStartNode(node)) {
                return sawReplyText ? node : null;
            }
            const text = node.nodeType === Node.TEXT_NODE
                ? String(node.textContent || '').replace(/\s+/g, ' ').trim()
                : '';
            if (text) sawReplyText = true;
            node = walker.nextNode();
        }
        return null;
    }

    function removeNodeAndFollowingSiblings(node) {
        let current = node;
        while (current) {
            const next = current.nextSibling;
            current.remove();
            current = next;
        }
    }

    function trimTrailingEmailChrome(root) {
        while (root.lastChild) {
            const last = root.lastChild;
            if (last.nodeType === Node.COMMENT_NODE) {
                last.remove();
                continue;
            }
            if (last.nodeType === Node.TEXT_NODE && !String(last.textContent || '').trim()) {
                last.remove();
                continue;
            }
            if (last.nodeType === Node.ELEMENT_NODE) {
                const tag = last.tagName.toLowerCase();
                if (tag === 'br' || tag === 'hr') {
                    last.remove();
                    continue;
                }
                const empty = !emailNodeMeaningfulText(last) && !last.querySelector?.('img');
                if (empty) {
                    last.remove();
                    continue;
                }
            }
            break;
        }
    }

    function stripQuotedEmailHistoryHtml(html) {
        const source = String(html || '').trim();
        if (!source) return '';
        const wrap = document.createElement('div');
        wrap.innerHTML = source;
        const quoteStart = findEmailQuoteStart(wrap);
        if (quoteStart) {
            let parent = quoteStart.parentNode;
            removeNodeAndFollowingSiblings(quoteStart);
            while (parent && parent !== wrap && !emailNodeMeaningfulText(parent) && !parent.querySelector?.('img')) {
                const nextParent = parent.parentNode;
                parent.remove();
                parent = nextParent;
            }
        }
        trimTrailingEmailChrome(wrap);
        const result = wrap.innerHTML.trim();
        return emailNodeMeaningfulText(wrap) || wrap.querySelector('img') ? result : source;
    }

    function stripQuotedEmailHistoryPlain(text) {
        const source = String(text || '');
        if (!source.trim()) return source;
        const cut = source.search(new RegExp(
            '(?:\\r?\\n)(?:\\s*)(?:'
            + '-----Original Message-----'
            + '|-----Forwarded message-----'
            + '|From:\\s.+\\r?\\nSent:\\s'
            + '|On .{8,160} wrote:\\s*$'
            + '|________________________________'
            + ')',
            'im'
        ));
        if (cut <= 0) return source;
        const kept = source.slice(0, cut).trim();
        return kept || source;
    }

    function mountEmailBody(host, message) {
        if (!host) return;

        try {
            const rawHtml = String(message?.body_html || '').trim();
            const plain = stripQuotedEmailHistoryPlain(String(message?.body_text || '').trim());

            host.classList.remove('is-framed');
            host.innerHTML = '';

            if (!rawHtml) {
                host.innerHTML = plainToHtml(plain) || '<span style="color:var(--inbox-muted)">No content</span>';
                return;
            }

            const parts = extractEmailDocumentParts(rawHtml);
            const bodyHtml = stripQuotedEmailHistoryHtml(parts.body || sanitizeHtml(decodeEscapedHtml(rawHtml)));
            if (!bodyHtml) {
                host.innerHTML = plainToHtml(plain) || '<span style="color:var(--inbox-muted)">No content</span>';
                return;
            }

            // Shadow DOM keeps Outlook <style> rules without leaking into the CRM chrome
            // and avoids blank iframe/srcdoc issues.
            if (typeof host.attachShadow === 'function') {
                const shadow = host.attachShadow({ mode: 'open' });
                const baseStyle = document.createElement('style');
                baseStyle.textContent = `
                    :host { display: block; }
                    .email-root {
                        color: #1f2937;
                        font: 14px/1.5 "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                        word-wrap: break-word;
                        overflow-wrap: anywhere;
                    }
                    .email-root img {
                        max-width: 100% !important;
                        height: auto !important;
                        display: inline-block;
                    }
                    .email-root table { max-width: 100%; border-collapse: collapse; }
                    .email-root a { color: #2563eb; }
                `;
                shadow.appendChild(baseStyle);
                if (parts.styles) {
                    const emailStyle = document.createElement('style');
                    emailStyle.textContent = parts.styles;
                    shadow.appendChild(emailStyle);
                }
                const root = document.createElement('div');
                root.className = 'email-root';
                root.innerHTML = bodyHtml;
                shadow.appendChild(root);
                return;
            }

            // Fallback for older browsers
            host.innerHTML = bodyHtml;
        } catch (err) {
            host.classList.remove('is-framed');
            host.textContent = String(message?.body_text || message?.body_html || 'Unable to render message').slice(0, 4000);
        }
    }

    function formatMessageBodyHtml(message) {
        const rawHtml = String(message?.body_html || '').trim();
        if (rawHtml) {
            const parts = extractEmailDocumentParts(rawHtml);
            return parts.body || sanitizeHtml(decodeEscapedHtml(rawHtml));
        }
        return plainToHtml(message?.body_text || '');
    }

    function htmlToPlain(html) {
        const wrap = document.createElement('div');
        wrap.innerHTML = String(html || '');
        return (wrap.textContent || '').trim();
    }

    function getHtmlEditor(kind) {
        return {
            root: document.querySelector(`[data-html-editor="${kind}"]`),
            visual: el(kind === 'template' ? 'newTemplateVisual' : 'newSignatureVisual'),
            source: el(kind === 'template' ? 'newTemplateBody' : 'newSignatureBody'),
        };
    }

    const HTML_VOID_TAGS = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr']);
    const HTML_INLINE_TAGS = new Set(['a', 'abbr', 'b', 'br', 'em', 'i', 'img', 'small', 'span', 'strong', 'sub', 'sup', 'u', 'code']);
    const HTML_RAW_TAGS = new Set(['pre', 'textarea', 'script', 'style']);

    function formatHtmlAttributes(el, indent) {
        const attrs = [...el.attributes];
        if (!attrs.length) return '';
        const parts = attrs.map((attr) => `${attr.name}="${escapeHtml(attr.value)}"`);
        const long = parts.some((part) => part.length > 56) || el.tagName.toLowerCase() === 'img';
        if (!long && parts.join(' ').length <= 72) {
            return ' ' + parts.join(' ');
        }
        return '\n' + parts.map((part) => `${indent}  ${part}`).join('\n') + '\n' + indent;
    }

    function escapeHtmlText(str) {
        return String(str ?? '').replace(/[&<>]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[ch]));
    }

    function serializeHtmlInline(el) {
        const tag = el.tagName.toLowerCase();
        const attrStr = [...el.attributes].map((attr) => ` ${attr.name}="${escapeHtml(attr.value)}"`).join('');
        if (HTML_VOID_TAGS.has(tag)) return `<${tag}${attrStr}>`;
        let inner = '';
        el.childNodes.forEach((child) => {
            if (child.nodeType === Node.TEXT_NODE) inner += escapeHtmlText(child.textContent);
            else if (child.nodeType === Node.ELEMENT_NODE) inner += serializeHtmlInline(child);
        });
        return `<${tag}${attrStr}>${inner}</${tag}>`;
    }

    function htmlNodeIsInlineOnly(el) {
        if (!el.childNodes.length) return true;
        for (const child of el.childNodes) {
            if (child.nodeType === Node.COMMENT_NODE) continue;
            if (child.nodeType === Node.TEXT_NODE) continue;
            if (child.nodeType !== Node.ELEMENT_NODE) return false;
            const tag = child.tagName.toLowerCase();
            if (!HTML_INLINE_TAGS.has(tag) || !htmlNodeIsInlineOnly(child)) return false;
        }
        return true;
    }

    function serializeHtmlPretty(nodes, depth) {
        const indent = '  '.repeat(depth);
        let out = '';
        nodes.forEach((child) => {
            if (child.nodeType === Node.COMMENT_NODE) {
                const text = String(child.textContent || '').trim();
                if (text) out += `${indent}<!-- ${text} -->\n`;
                return;
            }
            if (child.nodeType === Node.TEXT_NODE) {
                const text = String(child.textContent || '').replace(/\s+/g, ' ').trim();
                if (text) out += `${indent}${escapeHtmlText(text)}\n`;
                return;
            }
            if (child.nodeType !== Node.ELEMENT_NODE) return;
            const tag = child.tagName.toLowerCase();
            const attrs = formatHtmlAttributes(child, indent);
            if (HTML_VOID_TAGS.has(tag)) {
                out += `${indent}<${tag}${attrs}>\n`;
                return;
            }
            if (HTML_RAW_TAGS.has(tag)) {
                out += `${indent}<${tag}${attrs}>${child.innerHTML}</${tag}>\n`;
                return;
            }
            if (htmlNodeIsInlineOnly(child)) {
                const compact = serializeHtmlInline(child);
                if (compact.length <= 96) {
                    out += `${indent}${compact}\n`;
                    return;
                }
            }
            const inner = serializeHtmlPretty(child.childNodes, depth + 1);
            if (!inner.trim()) {
                out += `${indent}<${tag}${attrs}></${tag}>\n`;
                return;
            }
            out += `${indent}<${tag}${attrs}>\n${inner}${indent}</${tag}>\n`;
        });
        return out;
    }

    function beautifyHtml(html) {
        const wrap = document.createElement('div');
        wrap.innerHTML = String(html || '').trim();
        return serializeHtmlPretty(wrap.childNodes, 0).replace(/[ \t]+\n/g, '\n').trim();
    }

    function setHtmlEditorContent(kind, html) {
        const ed = getHtmlEditor(kind);
        const clean = sanitizeHtml(html || '');
        if (ed.visual) {
            ed.visual.innerHTML = clean;
            decorateHtmlLinks(ed.visual);
        }
        if (ed.source) ed.source.value = beautifyHtml(clean);
    }

    function getHtmlEditorContent(kind) {
        const ed = getHtmlEditor(kind);
        if (!ed.source) return '';
        if (ed.source.hidden === false) {
            return sanitizeHtml(ed.source.value.trim());
        }
        return sanitizeHtml((ed.visual?.innerHTML || '').trim());
    }

    function setHtmlEditorMode(kind, mode) {
        const ed = getHtmlEditor(kind);
        if (!ed.root) return;
        const visualMode = mode !== 'source';
        if (visualMode) {
            if (ed.visual && ed.source) {
                ed.visual.innerHTML = sanitizeHtml(ed.source.value);
                decorateHtmlLinks(ed.visual);
            }
            if (ed.visual) ed.visual.hidden = false;
            if (ed.source) ed.source.hidden = true;
        } else {
            if (ed.source && ed.visual) ed.source.value = beautifyHtml(sanitizeHtml(ed.visual.innerHTML));
            if (ed.visual) ed.visual.hidden = true;
            if (ed.source) {
                ed.source.hidden = false;
                ed.source.focus();
            }
        }
        ed.root.querySelectorAll('[data-html-mode]').forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.htmlMode === (visualMode ? 'visual' : 'source'));
        });
    }

    function appendHtmlToComposer(html) {
        const clean = sanitizeHtml(html);
        if (!clean) return false;

        const composeOpen = el('modalCompose')?.style.display === 'grid';
        const kind = composeOpen ? 'compose' : 'reply';
        const target = getComposerEl(kind);
        if (!target) return false;

        if (isComposerEmpty(kind)) {
            setComposerHtml(kind, clean);
        } else {
            target.innerHTML = sanitizeHtml((target.innerHTML || '') + '<br><br>' + clean);
        }
        placeCaretAtEnd(target);
        target.focus();
        decorateHtmlLinks(target);
        return true;
    }

    function getComposerEl(kind) {
        if (kind === 'compose') return el('composeBody');
        if (kind === 'comment') return el('commentBody');
        return el('replyBody');
    }

    function attachmentBucket(kind) {
        if (kind === 'compose') return 'composeAttachments';
        if (kind === 'comment') return 'commentAttachments';
        if (kind === 'template') return 'templateAttachments';
        return 'replyAttachments';
    }

    function fileAttachmentsOnly(files) {
        return (files || []).filter((f) => !f.isInline);
    }

    function prepareEmailSendPayload(body, files) {
        const attachments = fileAttachmentsOnly(files).map((file) => ({
            name: file.name,
            contentType: file.contentType,
            contentBytes: file.contentBytes,
        }));
        let inlineCount = 0;
        const preparedBody = String(body || '').replace(
            /<img\b[^>]*\ssrc=(["'])data:image\/([^;]+);base64,([^"']+)\1[^>]*>/gi,
            (match, quote, ext, bytes) => {
                inlineCount += 1;
                const contentId = `inbox-img-${inlineCount}-${Math.random().toString(36).slice(2, 8)}`;
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

    function setComposerMode() {
        state.composerMode = 'comment';
        document.querySelectorAll('[data-composer-mode]').forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.composerMode === 'comment');
        });
        hideMentionPopup('comment');
    }

    function openReplyModal(message = null, opts = {}) {
        if (!state.composerCanReply) return;
        const replyAll = !!opts.replyAll;
        const force = !!opts.force;
        state.replyAll = replyAll;
        if (force) state.replyDraftId = null;
        const titleEl = el('replyModalTitle');
        if (titleEl) titleEl.textContent = replyAll ? 'Reply all' : 'Reply';
        hideMentionPopup('reply');
        openModal('modalReply');
        populateReplyHeaders(message, { replyAll, force });
        applyComposerSignature('reply', stripSignatureHtml(getComposerHtml('reply')));
        el('replyBody')?.focus();
    }

    function openDraftReplyModal(message) {
        if (!message || !state.composerCanReply) return;
        state.replyAll = false;
        state.replyDraftId = (message.external_message_id && !String(message.external_message_id).startsWith('local-'))
            ? message.external_message_id
            : null;
        const titleEl = el('replyModalTitle');
        if (titleEl) titleEl.textContent = 'Edit draft';
        hideMentionPopup('reply');
        openModal('modalReply');
        fillReplyFromSelect();
        if (el('replyTo')) el('replyTo').value = parseEmailList(message.to || message.to_emails).join(', ');
        if (el('replyCc')) el('replyCc').value = parseEmailList(message.cc || message.cc_emails).join(', ');
        setComposerHtml('reply', message.body_html || '');
        el('composerHint').textContent = 'Send draft via Outlook';
        el('replyBody')?.focus();
    }

    function getComposerHtml(kind) {
        return sanitizeHtml(getComposerEl(kind)?.innerHTML || '');
    }

    function setComposerHtml(kind, html) {
        const node = getComposerEl(kind);
        if (!node) return;
        const clean = sanitizeHtml(html || '');
        node.innerHTML = clean;
        decorateHtmlLinks(node);
        // Keep :empty placeholder working when cleared.
        if (!htmlToPlain(clean)) node.innerHTML = '';
    }

    function getDefaultSignature() {
        if (!state.signatures.length) return null;
        const preferred = state.defaultSignatureId
            ? state.signatures.find(s => String(s.id) === String(state.defaultSignatureId))
            : null;
        return preferred || state.signatures[0];
    }

    function signatureBlockHtml(sig) {
        if (!sig) return '';
        const html = sanitizeHtml(sig.body_html || plainToHtml(sig.body || ''));
        if (!html) return '';
        return `<div class="inbox-email-signature" data-email-signature="${escapeHtml(String(sig.id))}"><br>${html}</div>`;
    }

    function stripSignatureHtml(html) {
        const wrap = document.createElement('div');
        wrap.innerHTML = sanitizeHtml(html || '');
        wrap.querySelectorAll('[data-email-signature]').forEach(n => n.remove());
        return sanitizeHtml(wrap.innerHTML);
    }

    function buildComposerWithSignature(bodyHtml = '') {
        const message = sanitizeHtml(bodyHtml || '');
        const sigBlock = signatureBlockHtml(getDefaultSignature());
        if (!sigBlock) return message;
        return (message || '<div><br></div>') + sigBlock;
    }

    function applyComposerSignature(kind, bodyHtml = '') {
        setComposerHtml(kind, buildComposerWithSignature(bodyHtml));
        const editor = getComposerEl(kind);
        if (!editor) return;
        const range = document.createRange();
        const sel = window.getSelection();
        range.setStart(editor, 0);
        range.collapse(true);
        sel?.removeAllRanges();
        sel?.addRange(range);
    }

    function insertHtmlBeforeSignature(kind, html) {
        const editor = getComposerEl(kind);
        const clean = sanitizeHtml(html);
        if (!editor || !clean) return;
        const sig = editor.querySelector('[data-email-signature]');
        if (!sig) {
            if (isComposerEmpty(kind)) setComposerHtml(kind, clean);
            else editor.innerHTML = sanitizeHtml((editor.innerHTML || '') + '<br><br>' + clean);
            decorateHtmlLinks(editor);
            return;
        }
        const spacer = document.createElement('div');
        spacer.innerHTML = clean + '<br>';
        while (spacer.firstChild) {
            sig.parentNode.insertBefore(spacer.firstChild, sig);
        }
        decorateHtmlLinks(editor);
    }

    function setDefaultSignature(signatureId) {
        const item = state.signatures.find(s => String(s.id) === String(signatureId));
        if (!item) return;
        state.defaultSignatureId = item.id;
        saveLocalTools();
        updateSignatureCount();
        if (el('modalSignatureList')?.style.display === 'grid') {
            renderSignatureList();
        }
        // Refresh open composers so the active default is used.
        if (el('modalCompose')?.style.display === 'grid') {
            applyComposerSignature('compose', stripSignatureHtml(getComposerHtml('compose')));
        }
        if (state.selectedId) {
            applyComposerSignature('reply', stripSignatureHtml(getComposerHtml('reply')));
        }
    }

    function isComposerEmpty(kind) {
        return !htmlToPlain(stripSignatureHtml(getComposerHtml(kind)));
    }

    function placeCaretAtEnd(node) {
        if (!node) return;
        const range = document.createRange();
        const sel = window.getSelection();
        range.selectNodeContents(node);
        range.collapse(false);
        sel?.removeAllRanges();
        sel?.addRange(range);
    }

    let savedHtmlEditorSelection = null;

    function saveHtmlEditorSelection(editorKind) {
        const ed = getHtmlEditor(editorKind);
        if (!ed.visual) return;
        const sel = window.getSelection();
        if (!sel || !sel.rangeCount || !ed.visual.contains(sel.anchorNode)) {
            savedHtmlEditorSelection = { kind: editorKind, atEnd: true };
            return;
        }
        savedHtmlEditorSelection = {
            kind: editorKind,
            atEnd: false,
            range: sel.getRangeAt(0).cloneRange(),
        };
    }

    function restoreHtmlEditorSelection(editorKind) {
        const saved = savedHtmlEditorSelection;
        savedHtmlEditorSelection = null;
        if (!saved || saved.kind !== editorKind) return false;
        const ed = getHtmlEditor(editorKind);
        if (!ed.visual) return false;
        ed.visual.focus();
        if (saved.atEnd || !saved.range) {
            placeCaretAtEnd(ed.visual);
            return true;
        }
        const sel = window.getSelection();
        sel?.removeAllRanges();
        sel?.addRange(saved.range);
        return true;
    }

    let htmlLinkState = null;

    function normalizeLinkUrl(raw) {
        const url = String(raw || '').trim();
        if (!url) return '';
        if (/^(javascript|data|vbscript):/i.test(url)) return '';
        if (/^(https?:\/\/|mailto:|tel:|#|\/)/i.test(url)) return url;
        return 'https://' + url;
    }

    function captureHtmlEditorRange(kind) {
        const ed = getHtmlEditor(kind);
        if (!ed.visual) return null;
        const sel = window.getSelection();
        if (sel?.rangeCount && ed.visual.contains(sel.anchorNode)) {
            return sel.getRangeAt(0).cloneRange();
        }
        if (savedHtmlEditorSelection?.kind === kind && savedHtmlEditorSelection.range) {
            return savedHtmlEditorSelection.range.cloneRange();
        }
        const range = document.createRange();
        range.selectNodeContents(ed.visual);
        range.collapse(false);
        return range;
    }

    function imageFromRange(range, editor) {
        if (!range || !editor) return null;
        const root = range.commonAncestorContainer;
        const el = root.nodeType === 1 ? root : root.parentElement;
        if (!el || !editor.contains(el)) return null;
        if (el.tagName === 'IMG') return el;
        const imgs = [...editor.querySelectorAll('img')].filter((img) => {
            try { return range.intersectsNode(img); } catch (_) { return false; }
        });
        return imgs.length === 1 ? imgs[0] : null;
    }

    function anchorFromRange(range, editor, preferred) {
        if (preferred && editor.contains(preferred)) return preferred;
        if (!range || !editor) return null;
        let node = range.commonAncestorContainer;
        if (node.nodeType === 3) node = node.parentElement;
        const fromAncestor = node?.closest?.('a');
        if (fromAncestor && editor.contains(fromAncestor)) return fromAncestor;
        const img = imageFromRange(range, editor);
        const fromImg = img?.closest('a');
        return fromImg && editor.contains(fromImg) ? fromImg : null;
    }

    function configureLinkAnchor(anchor, url) {
        anchor.setAttribute('href', url);
        anchor.setAttribute('target', '_blank');
        anchor.setAttribute('rel', 'noopener noreferrer');
        anchor.setAttribute('title', url);
    }

    function decorateHtmlLinks(root) {
        if (!root) return;
        root.querySelectorAll('a[href]').forEach((anchor) => {
            const href = anchor.getAttribute('href') || '';
            if (href && !anchor.getAttribute('title')) {
                anchor.setAttribute('title', href);
            }
        });
    }

    function hideHtmlLinkTip() {
        const tip = el('inboxHtmlLinkTip');
        if (tip) tip.hidden = true;
    }

    function showHtmlLinkTip(anchor) {
        const tip = el('inboxHtmlLinkTip');
        const href = String(anchor?.getAttribute('href') || '').trim();
        if (!tip || !href || href === '#') return;
        tip.textContent = href;
        tip.hidden = false;
        const rect = anchor.getBoundingClientRect();
        const margin = 8;
        const tipWidth = Math.min(380, window.innerWidth - 16);
        tip.style.maxWidth = `${tipWidth}px`;
        let left = rect.left;
        let top = rect.bottom + 6;
        const size = tip.getBoundingClientRect();
        if (left + size.width > window.innerWidth - margin) {
            left = Math.max(margin, window.innerWidth - size.width - margin);
        }
        if (left < margin) left = margin;
        if (top + size.height > window.innerHeight - margin) {
            top = Math.max(margin, rect.top - size.height - 6);
        }
        tip.style.left = `${left}px`;
        tip.style.top = `${top}px`;
    }

    function bindHtmlLinkHover(root) {
        if (!root || root.dataset.linkHoverBound === '1') return;
        root.dataset.linkHoverBound = '1';
        root.addEventListener('mouseover', (e) => {
            const anchor = e.target.closest('a[href]');
            if (!anchor || !root.contains(anchor)) return;
            showHtmlLinkTip(anchor);
        });
        root.addEventListener('mouseout', (e) => {
            const anchor = e.target.closest('a[href]');
            if (!anchor || !root.contains(anchor)) return;
            if (e.relatedTarget && anchor.contains(e.relatedTarget)) return;
            hideHtmlLinkTip();
        });
        root.addEventListener('scroll', hideHtmlLinkTip, true);
    }

    function closeHtmlLinkDialog() {
        hideHtmlLinkTip();
        const dialog = el('htmlLinkDialog');
        if (dialog) dialog.hidden = true;
        htmlLinkState = null;
    }

    function openHtmlLinkDialog(kind, preferredAnchor = null) {
        const ed = getHtmlEditor(kind);
        if (!ed.visual) return;
        if (ed.source && !ed.source.hidden) {
            alert('Switch to Visual mode to insert links, or paste an <a href="..."> tag in HTML mode.');
            return;
        }
        const range = captureHtmlEditorRange(kind);
        const img = imageFromRange(range, ed.visual);
        const existing = anchorFromRange(range, ed.visual, preferredAnchor);
        const selectedText = String(range?.toString() || '').trim();
        htmlLinkState = { kind, range, img, existing };
        el('htmlLinkDialogTitle').textContent = existing ? 'Edit link' : 'Insert link';
        el('htmlLinkHint').textContent = img && !selectedText
            ? 'This image will become a clickable link in the email.'
            : 'Recipients can click the selected text or image in the email.';
        el('htmlLinkUrl').value = existing?.getAttribute('href') || 'https://';
        const textWrap = el('htmlLinkTextWrap');
        const showText = !existing && !img && !selectedText;
        if (textWrap) textWrap.hidden = !showText;
        el('htmlLinkText').value = selectedText;
        const removeBtn = el('btnHtmlLinkRemove');
        if (removeBtn) removeBtn.hidden = !existing;
        const dialog = el('htmlLinkDialog');
        if (dialog) dialog.hidden = false;
        setTimeout(() => {
            el('htmlLinkUrl')?.focus();
            el('htmlLinkUrl')?.select();
        }, 0);
    }

    function unwrapAnchor(anchor) {
        const parent = anchor.parentNode;
        if (!parent) return;
        while (anchor.firstChild) parent.insertBefore(anchor.firstChild, anchor);
        parent.removeChild(anchor);
    }

    function applyHtmlLink() {
        if (!htmlLinkState) return;
        const { kind, range, img, existing } = htmlLinkState;
        const ed = getHtmlEditor(kind);
        if (!ed.visual || !range) return;
        const url = normalizeLinkUrl(el('htmlLinkUrl')?.value);
        if (!url) {
            alert('Enter a valid URL, such as https://example.com');
            return;
        }
        const linkText = String(el('htmlLinkText')?.value || '').trim() || url.replace(/^https?:\/\//i, '');
        ed.visual.focus();
        try {
            const sel = window.getSelection();
            sel?.removeAllRanges();
            sel?.addRange(range);
        } catch (_) {}

        if (existing && ed.visual.contains(existing)) {
            configureLinkAnchor(existing, url);
        } else if (img && ed.visual.contains(img)) {
            const parentLink = img.closest('a');
            if (parentLink && ed.visual.contains(parentLink)) {
                configureLinkAnchor(parentLink, url);
            } else {
                const a = document.createElement('a');
                configureLinkAnchor(a, url);
                img.parentNode.insertBefore(a, img);
                a.appendChild(img);
            }
        } else if (range.collapsed) {
            const a = document.createElement('a');
            configureLinkAnchor(a, url);
            a.textContent = linkText;
            range.insertNode(a);
        } else {
            const a = document.createElement('a');
            configureLinkAnchor(a, url);
            try {
                a.appendChild(range.extractContents());
                range.insertNode(a);
            } catch (_) {
                document.execCommand('createLink', false, url);
                ed.visual.querySelectorAll('a[href]').forEach((node) => {
                    if (!node.getAttribute('target')) configureLinkAnchor(node, node.getAttribute('href') || url);
                });
            }
        }
        if (ed.source) ed.source.value = sanitizeHtml(ed.visual.innerHTML || '');
        decorateHtmlLinks(ed.visual);
        closeHtmlLinkDialog();
    }

    function removeHtmlLink() {
        if (!htmlLinkState?.existing) {
            closeHtmlLinkDialog();
            return;
        }
        const ed = getHtmlEditor(htmlLinkState.kind);
        if (ed.visual?.contains(htmlLinkState.existing)) {
            unwrapAnchor(htmlLinkState.existing);
            if (ed.source) ed.source.value = sanitizeHtml(ed.visual.innerHTML || '');
        }
        closeHtmlLinkDialog();
    }

    function templateHasBody(html) {
        return Boolean(htmlToPlain(html) || /<img\b/i.test(String(html || '')));
    }

    function insertHtmlAtCaret(editor, html) {
        if (!editor) return false;
        editor.focus();
        const clean = sanitizeHtml(html);
        const before = editor.innerHTML;
        if (document.queryCommandSupported?.('insertHTML') || true) {
            try {
                document.execCommand('insertHTML', false, clean);
                if (editor.innerHTML !== before || editor.querySelector('img')) {
                    return true;
                }
            } catch (_) {}
        }
        editor.innerHTML = sanitizeHtml((before || '') + clean);
        placeCaretAtEnd(editor);
        return true;
    }

    function getTextBeforeCaret(editor) {
        const sel = window.getSelection();
        if (!sel || !sel.rangeCount || !editor.contains(sel.anchorNode)) return '';
        const range = sel.getRangeAt(0).cloneRange();
        range.selectNodeContents(editor);
        range.setEnd(sel.getRangeAt(0).endContainer, sel.getRangeAt(0).endOffset);
        return range.toString();
    }

    const MAX_ATTACH_BYTES = 3 * 1024 * 1024;
    const MAX_ATTACH_COUNT = 5;
    const TEMPLATE_PAGE_SIZE = 5;
    const SIGNATURE_PAGE_SIZE = 5;

    function refreshTemplateSelects() {
        document.querySelectorAll('[data-template-picker]').forEach(picker => {
            renderTemplatePickerList(picker);
        });
    }

    function templatesMatchingQuery(query) {
        const q = (query || '').trim().toLowerCase();
        if (!q) return state.templates;
        return state.templates.filter(t => {
            const haystack = [
                t.name || '',
                t.subject || '',
                t.body || '',
                htmlToPlain(t.body_html || ''),
            ].join(' ').toLowerCase();
            return haystack.includes(q);
        });
    }

    function templatePickerItemMeta(t) {
        const subject = String(t.subject || '').trim();
        if (subject) return subject;
        const preview = htmlToPlain(t.body_html || t.body || '').replace(/\s+/g, ' ').trim();
        return preview;
    }

    function renderTemplatePickerList(picker) {
        if (!picker) return;
        const list = picker.querySelector('[data-template-picker-list]');
        const search = picker.querySelector('[data-template-picker-search]');
        if (!list) return;
        const items = templatesMatchingQuery(search?.value || '');
        if (!state.templates.length) {
            list.innerHTML = '<div class="inbox-template-picker-empty">No templates yet</div>';
            positionTemplatePickerMenu(picker);
            return;
        }
        if (!items.length) {
            list.innerHTML = '<div class="inbox-template-picker-empty">No matches</div>';
            positionTemplatePickerMenu(picker);
            return;
        }
        list.innerHTML = items.map(t => {
            const meta = templatePickerItemMeta(t);
            return `
            <button type="button" class="inbox-template-picker-item" data-insert-template-id="${escapeHtml(t.id)}" title="${escapeHtml(t.subject || t.name)}">
                <span class="inbox-template-picker-item-name">${escapeHtml(t.name || 'Untitled')}</span>
                ${meta ? `<span class="inbox-template-picker-item-meta">${escapeHtml(meta)}</span>` : ''}
            </button>
        `;
        }).join('');
        positionTemplatePickerMenu(picker);
    }

    function resetTemplatePickerMenuPosition(menu) {
        if (!menu) return;
        menu.style.top = '';
        menu.style.bottom = '';
        menu.style.left = '';
        menu.style.right = '';
        menu.style.width = '';
        menu.style.maxHeight = '';
    }

    function positionTemplatePickerMenu(picker) {
        if (!picker?.classList.contains('is-open')) return;
        const menu = picker.querySelector('.inbox-template-picker-menu');
        const toggle = picker.querySelector('[data-template-picker-toggle]');
        if (!menu || menu.hidden || !toggle) return;
        const rect = toggle.getBoundingClientRect();
        const margin = 8;
        const width = Math.min(320, Math.max(240, window.innerWidth - (margin * 2)));
        let left = rect.right - width;
        if (left < margin) left = margin;
        if (left + width > window.innerWidth - margin) {
            left = Math.max(margin, window.innerWidth - width - margin);
        }
        const spaceAbove = Math.max(0, rect.top - margin);
        const spaceBelow = Math.max(0, window.innerHeight - rect.bottom - margin);
        const openAbove = spaceAbove >= 200 || spaceAbove >= spaceBelow;
        const available = Math.max(160, openAbove ? spaceAbove : spaceBelow);
        menu.style.width = `${width}px`;
        menu.style.left = `${left}px`;
        menu.style.right = 'auto';
        menu.style.maxHeight = `${Math.min(360, available)}px`;
        if (openAbove) {
            menu.style.top = 'auto';
            menu.style.bottom = `${window.innerHeight - rect.top + 6}px`;
        } else {
            menu.style.bottom = 'auto';
            menu.style.top = `${rect.bottom + 6}px`;
        }
    }

    function syncOpenTemplatePickerPosition() {
        document.querySelectorAll('[data-template-picker].is-open').forEach(positionTemplatePickerMenu);
    }

    function closeTemplatePickers(except = null) {
        document.querySelectorAll('[data-template-picker]').forEach(picker => {
            if (except && picker === except) return;
            picker.classList.remove('is-open');
            const menu = picker.querySelector('.inbox-template-picker-menu');
            if (menu) {
                menu.hidden = true;
                resetTemplatePickerMenuPosition(menu);
            }
        });
    }

    function openTemplatePicker(picker) {
        if (!picker) return;
        closeTemplatePickers(picker);
        picker.classList.add('is-open');
        const menu = picker.querySelector('.inbox-template-picker-menu');
        if (menu) menu.hidden = false;
        const search = picker.querySelector('[data-template-picker-search]');
        if (search) search.value = '';
        renderTemplatePickerList(picker);
        requestAnimationFrame(() => {
            positionTemplatePickerMenu(picker);
            search?.focus();
        });
    }

    function filteredTemplates() {
        return templatesMatchingQuery(state.templateSearch || '');
    }

    function paginatedTemplateListItems() {
        const items = filteredTemplates();
        const total = items.length;
        const totalPages = Math.max(1, Math.ceil(total / TEMPLATE_PAGE_SIZE));
        const page = Math.min(Math.max(1, state.templateListPage), totalPages);
        state.templateListPage = page;
        const start = (page - 1) * TEMPLATE_PAGE_SIZE;
        const end = Math.min(start + TEMPLATE_PAGE_SIZE, total);
        return {
            items: items.slice(start, end),
            total,
            totalPages,
            page,
            from: total ? start + 1 : 0,
            to: end,
        };
    }

    function renderTemplateListPagination(meta) {
        const bar = el('templateListPagination');
        if (!bar) return;
        if (!meta.total || meta.total <= TEMPLATE_PAGE_SIZE) {
            bar.hidden = true;
            return;
        }
        bar.hidden = false;
        const info = el('templateListPaginationInfo');
        const status = el('templateListPageStatus');
        const prev = el('templateListPrevPage');
        const next = el('templateListNextPage');
        if (info) info.textContent = `Showing ${meta.from}–${meta.to} of ${meta.total}`;
        if (status) status.textContent = `Page ${meta.page} of ${meta.totalPages}`;
        if (prev) prev.disabled = meta.page <= 1;
        if (next) next.disabled = meta.page >= meta.totalPages;
    }

    function updateTemplateCount() {
        const countEl = el('templateCount');
        if (!countEl) return;
        countEl.textContent = state.templates.length ? String(state.templates.length) : '';
    }

    function renderTemplateList() {
        const list = el('templateList');
        const search = el('templateListSearch');
        if (search && document.activeElement !== search) {
            search.value = state.templateSearch || '';
        }
        if (!list) return;
        const meta = paginatedTemplateListItems();
        renderTemplateListPagination(meta);

        if (!state.templates.length) {
            list.innerHTML = `<div class="inbox-tpl-empty">No templates yet.${state.permissions.create_templates ? ' Click <strong>New template</strong> to add one.' : ''}</div>`;
            return;
        }
        if (!meta.total) {
            list.innerHTML = '<div class="inbox-tpl-empty">No matches</div>';
            return;
        }
        list.innerHTML = meta.items.map(t => {
            const preview = htmlToPlain(t.body_html || t.body || '');
            const subject = t.subject
                ? `<div class="inbox-tpl-row-subject">${escapeHtml(t.subject)}</div>`
                : '';
            return `
            <div class="inbox-tpl-row" data-template-id="${t.id}">
                <div class="inbox-tpl-row-main">
                    <div class="inbox-tpl-row-name">${escapeHtml(t.name)}</div>
                    ${subject}
                    <div class="inbox-tpl-row-preview">${escapeHtml(preview)}</div>
                </div>
                <div class="inbox-tpl-row-actions">
                    <button type="button" class="inbox-tpl-link-btn" data-use-template="${t.id}">Use</button>
                    ${state.permissions.create_templates ? `
                        <button type="button" class="inbox-tpl-link-btn muted" data-edit-template="${t.id}">Edit</button>
                        <button type="button" class="inbox-tpl-link-btn muted" data-delete-template="${t.id}">Delete</button>
                    ` : ''}
                </div>
            </div>
        `;
        }).join('');
    }

    function openTemplateListModal() {
        state.templateListPage = 1;
        state.templateSearch = '';
        if (el('templateListSearch')) el('templateListSearch').value = '';
        if (el('btnNewTemplate')) el('btnNewTemplate').style.display = state.permissions.create_templates ? '' : 'none';
        renderTemplateList();
        openModal('modalTemplateList');
        setTimeout(() => el('templateListSearch')?.focus(), 30);
    }

    function useTemplateFromList(templateId) {
        if (el('modalCompose')?.style.display === 'grid') {
            insertTemplateInto('compose', templateId);
            closeModal();
            return;
        }
        if (state.selectedId && state.composerCanReply && el('replyBody')) {
            insertTemplateInto('reply', templateId);
            closeModal();
            return;
        }
        openComposeModal();
        insertTemplateInto('compose', templateId);
    }

    function signaturesMatchingQuery(query) {
        const q = String(query || '').trim().toLowerCase();
        if (!q) return state.signatures;
        return state.signatures.filter(s => {
            const haystack = [s.name || '', s.body || '', htmlToPlain(s.body_html || '')].join(' ').toLowerCase();
            return haystack.includes(q);
        });
    }

    function filteredSignatures() {
        return signaturesMatchingQuery(state.signatureSearch || '');
    }

    function paginatedSignatureListItems() {
        const items = filteredSignatures();
        const total = items.length;
        const totalPages = Math.max(1, Math.ceil(total / SIGNATURE_PAGE_SIZE));
        const page = Math.min(Math.max(1, state.signatureListPage), totalPages);
        state.signatureListPage = page;
        const start = (page - 1) * SIGNATURE_PAGE_SIZE;
        const end = Math.min(start + SIGNATURE_PAGE_SIZE, total);
        return {
            items: items.slice(start, end),
            total,
            totalPages,
            page,
            from: total ? start + 1 : 0,
            to: end,
        };
    }

    function renderSignatureListPagination(meta) {
        const bar = el('signatureListPagination');
        if (!bar) return;
        if (!meta.total || meta.total <= SIGNATURE_PAGE_SIZE) {
            bar.hidden = true;
            return;
        }
        bar.hidden = false;
        const info = el('signatureListPaginationInfo');
        const status = el('signatureListPageStatus');
        const prev = el('signatureListPrevPage');
        const next = el('signatureListNextPage');
        if (info) info.textContent = `Showing ${meta.from}–${meta.to} of ${meta.total}`;
        if (status) status.textContent = `Page ${meta.page} of ${meta.totalPages}`;
        if (prev) prev.disabled = meta.page <= 1;
        if (next) next.disabled = meta.page >= meta.totalPages;
    }

    function updateSignatureCount() {
        const countEl = el('signatureCount');
        if (!countEl) return;
        countEl.textContent = state.signatures.length ? String(state.signatures.length) : '';
    }

    function renderSignatureList() {
        const list = el('signatureList');
        const search = el('signatureListSearch');
        if (search && document.activeElement !== search) {
            search.value = state.signatureSearch || '';
        }
        if (!list) return;
        const meta = paginatedSignatureListItems();
        renderSignatureListPagination(meta);

        if (!state.signatures.length) {
            list.innerHTML = '<div class="inbox-tpl-empty">No signatures yet. Click <strong>New signature</strong> to add one.</div>';
            return;
        }
        if (!meta.total) {
            list.innerHTML = '<div class="inbox-tpl-empty">No matches</div>';
            return;
        }
        list.innerHTML = meta.items.map(s => {
            const isDefault = String(state.defaultSignatureId || state.signatures[0]?.id) === String(s.id);
            const preview = htmlToPlain(s.body_html || s.body || '');
            return `
            <div class="inbox-tpl-row ${isDefault ? 'is-default-signature' : ''}" data-signature-id="${s.id}">
                <div class="inbox-tpl-row-main">
                    <div class="inbox-tpl-row-name">
                        ${escapeHtml(s.name)}
                        ${isDefault ? '<span class="inbox-tpl-default-badge">Default</span>' : ''}
                    </div>
                    <div class="inbox-tpl-row-preview">${escapeHtml(preview)}</div>
                </div>
                <div class="inbox-tpl-row-actions">
                    ${!isDefault ? `<button type="button" class="inbox-tpl-link-btn" data-default-signature="${s.id}">Set default</button>` : ''}
                    <button type="button" class="inbox-tpl-link-btn muted" data-edit-signature="${s.id}">Edit</button>
                    <button type="button" class="inbox-tpl-link-btn muted" data-delete-signature="${s.id}">Delete</button>
                </div>
            </div>
        `;
        }).join('');
    }

    function openSignatureListModal() {
        state.signatureListPage = 1;
        state.signatureSearch = '';
        if (el('signatureListSearch')) el('signatureListSearch').value = '';
        renderSignatureList();
        openModal('modalSignatureList');
        setTimeout(() => el('signatureListSearch')?.focus(), 30);
    }

    function openSignatureModal(signatureId = null) {
        state.returnToSignatureList = el('modalSignatureList')?.style.display === 'grid';
        const item = signatureId
            ? state.signatures.find(s => String(s.id) === String(signatureId))
            : null;
        state.editingSignatureId = item ? item.id : null;
        el('signatureModalTitle').textContent = item ? 'Edit signature' : 'New signature';
        el('btnSaveSignature').textContent = item ? 'Save' : 'Create';
        el('newSignatureName').value = item?.name || '';
        setHtmlEditorContent('signature', item?.body_html || plainToHtml(item?.body || '') || '');
        setHtmlEditorMode('signature', 'visual');
        openModal('modalSignature');
        setTimeout(() => el('newSignatureName')?.focus(), 50);
    }

    function deleteSignatureById(signatureId) {
        const item = state.signatures.find(s => String(s.id) === String(signatureId));
        if (!item) return;
        if (!confirm(`Delete signature "${item.name}"?`)) return;
        state.signatures = state.signatures.filter(s => String(s.id) !== String(signatureId));
        if (String(state.defaultSignatureId) === String(signatureId)) {
            state.defaultSignatureId = state.signatures[0]?.id ?? null;
        }
        saveLocalTools();
        updateSignatureCount();
        if (el('modalSignatureList')?.style.display === 'grid') {
            renderSignatureList();
        }
        if (el('modalCompose')?.style.display === 'grid') {
            applyComposerSignature('compose', stripSignatureHtml(getComposerHtml('compose')));
        }
        if (state.selectedId) {
            applyComposerSignature('reply', stripSignatureHtml(getComposerHtml('reply')));
        }
    }

    function renderAttachChips(kind) {
        const chips = el(
            kind === 'compose' ? 'composeAttachChips'
                : (kind === 'comment' ? 'commentAttachChips'
                    : (kind === 'template' ? 'templateAttachChips' : 'replyAttachChips'))
        );
        const files = fileAttachmentsOnly(state[attachmentBucket(kind)] || []);
        if (!chips) return;
        chips.innerHTML = files.map((f, idx) => `
            <span class="inbox-attach-chip">
                <span title="${escapeHtml(f.name)}">${escapeHtml(f.name)}</span>
                <button type="button" data-remove-attach="${kind}:${idx}" aria-label="Remove">×</button>
            </span>
        `).join('');
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

    async function addAttachments(kind, fileList) {
        const bucket = attachmentBucket(kind);
        const incoming = [...(fileList || [])];
        if (!incoming.length) return;
        const currentFiles = fileAttachmentsOnly(state[bucket] || []);
        if (currentFiles.length + incoming.length > MAX_ATTACH_COUNT) {
            alert(`You can attach up to ${MAX_ATTACH_COUNT} files.`);
            return;
        }
        try {
            const files = await Promise.all(incoming.map(readFileAsAttachment));
            state[bucket] = [...currentFiles, ...files];
            renderAttachChips(kind);
        } catch (err) {
            alert(err.message || 'Could not attach file.');
        }
    }

    async function insertHtmlEditorImage(editorKind, file) {
        if (!file) return;
        if (file.size > MAX_ATTACH_BYTES) {
            alert(`${file.name} is larger than 3 MB.`);
            return;
        }
        const ed = getHtmlEditor(editorKind);
        if (!ed.visual) return;
        if (ed.source && !ed.source.hidden) {
            alert('Switch to Visual mode to insert images at the cursor, or paste an <img> tag in HTML mode.');
            return;
        }
        try {
            const attachment = await readFileAsAttachment(file);
            const imgHtml = `<img src="data:${attachment.contentType};base64,${attachment.contentBytes}" alt="${escapeHtml(file.name)}" style="max-width:100%;height:auto;">`;
            restoreHtmlEditorSelection(editorKind) || placeCaretAtEnd(ed.visual);
            const beforeHtml = ed.visual.innerHTML;
            insertHtmlAtCaret(ed.visual, imgHtml);
            if (!ed.visual.querySelector('img') && beforeHtml === ed.visual.innerHTML) {
                ed.visual.innerHTML = sanitizeHtml((beforeHtml || '') + imgHtml);
                placeCaretAtEnd(ed.visual);
            }
            if (ed.source) ed.source.value = sanitizeHtml(ed.visual?.innerHTML || '');
        } catch (err) {
            alert(err.message || 'Could not insert image.');
        }
    }

    function insertAtCursor(editor, text) {
        insertHtmlAtCaret(editor, escapeHtml(text));
    }

    function mentionQueryAtCursor(editor) {
        const before = getTextBeforeCaret(editor);
        const match = before.match(/(^|[\s\u00a0])@([a-zA-Z0-9._\- ]*)$/);
        if (!match) return null;
        return { query: match[2] || '' };
    }

    function filteredMembers(query) {
        const q = String(query || '').trim().toLowerCase();
        return (state.members || []).filter(m => {
            if (!q) return true;
            return (m.name || '').toLowerCase().includes(q) || (m.email || '').toLowerCase().includes(q);
        }).slice(0, 8);
    }

    function mentionPopupId(kind) {
        if (kind === 'compose') return 'composeMentionPopup';
        if (kind === 'comment') return 'commentMentionPopup';
        return 'replyMentionPopup';
    }

    function renderMentionPopup(kind, query) {
        const popup = el(mentionPopupId(kind));
        if (!popup) return;
        const members = filteredMembers(query);
        if (!members.length) {
            popup.hidden = true;
            popup.innerHTML = '';
            return;
        }
        popup.innerHTML = members.map((m, idx) => `
            <button type="button" class="inbox-mention-item ${idx === 0 ? 'is-active' : ''}" data-mention-kind="${kind}" data-mention-id="${m.id}">
                <span class="inbox-mention-name">${escapeHtml(m.name)}</span>
                <span class="inbox-mention-email">${escapeHtml(m.email || '')}</span>
            </button>
        `).join('');
        popup.hidden = false;
    }

    function hideMentionPopup(kind) {
        const popup = el(mentionPopupId(kind));
        if (!popup) return;
        popup.hidden = true;
        popup.innerHTML = '';
    }

    function deleteMentionTrigger(editor) {
        // Remove trailing @query before caret by replacing it with empty selection text.
        const sel = window.getSelection();
        if (!sel || !sel.rangeCount || !editor.contains(sel.anchorNode)) return;
        const before = getTextBeforeCaret(editor);
        const match = before.match(/(^|[\s\u00a0])(@[a-zA-Z0-9._\- ]*)$/);
        if (!match) return;
        const removeLen = match[2].length;
        for (let i = 0; i < removeLen; i++) {
            document.execCommand('delete', false, null);
        }
    }

    function applyMention(kind, member) {
        const editor = getComposerEl(kind);
        if (!editor || !member) return;
        deleteMentionTrigger(editor);
        insertHtmlAtCaret(editor, `<span class="inbox-mention" contenteditable="false" data-mention-user-id="${member.id}">@${escapeHtml(member.name)}</span>&nbsp;`);

        if (member.email && kind === 'compose') {
            const cc = el('composeCc');
            const parts = (cc.value || '').split(',').map(s => s.trim()).filter(Boolean);
            if (!parts.some(p => p.toLowerCase() === member.email.toLowerCase())) {
                parts.push(member.email);
                cc.value = parts.join(', ');
            }
        } else if (member.email && kind === 'reply') {
            addEmailToField('replyCc', member.email);
        }
        hideMentionPopup(kind);
    }

    function insertTemplateInto(kind, templateId) {
        const item = state.templates.find(t => String(t.id) === String(templateId));
        if (!item) return;
        const html = sanitizeHtml(item.body_html || plainToHtml(item.body || ''));
        if (kind === 'compose' && item.subject && !el('composeSubject').value.trim()) {
            el('composeSubject').value = item.subject;
        }
        insertHtmlBeforeSignature(kind, html);
        const bucket = attachmentBucket(kind);
        const existing = fileAttachmentsOnly(state[bucket] || []);
        const fromTemplate = fileAttachmentsOnly(item.attachments || []).map((a) => ({
            name: a.name,
            contentType: a.contentType || 'application/octet-stream',
            contentBytes: a.contentBytes,
            size: a.size || Math.round(String(a.contentBytes || '').length * 0.75),
        }));
        const room = Math.max(0, MAX_ATTACH_COUNT - existing.length);
        if (fromTemplate.length && room < fromTemplate.length) {
            alert(`Template has ${fromTemplate.length} attachment(s), but only ${room} more can be added (max ${MAX_ATTACH_COUNT}).`);
        }
        if (room > 0 && fromTemplate.length) {
            state[bucket] = [...existing, ...fromTemplate.slice(0, room)];
            renderAttachChips(kind);
        }
        const editor = getComposerEl(kind);
        if (!editor) return;
        editor.focus();
        const sig = editor.querySelector('[data-email-signature]');
        if (sig) {
            const range = document.createRange();
            const sel = window.getSelection();
            range.setStartBefore(sig);
            range.collapse(true);
            sel?.removeAllRanges();
            sel?.addRange(range);
        } else {
            placeCaretAtEnd(editor);
        }
    }

    function bindComposerExtras(kind) {
        const attachBtn = el(kind === 'compose' ? 'btnComposeAttach' : (kind === 'comment' ? 'btnCommentAttach' : 'btnReplyAttach'));
        const attachInput = el(kind === 'compose' ? 'composeAttachInput' : (kind === 'comment' ? 'commentAttachInput' : 'replyAttachInput'));
        const mentionBtn = el(kind === 'compose' ? 'btnComposeMention' : (kind === 'comment' ? 'btnCommentMention' : 'btnReplyMention'));
        const templatePicker = document.querySelector(`[data-template-picker="${kind}"]`);
        const editor = getComposerEl(kind);
        const chips = el(kind === 'compose' ? 'composeAttachChips' : (kind === 'comment' ? 'commentAttachChips' : 'replyAttachChips'));
        const popup = el(mentionPopupId(kind));

        attachBtn?.addEventListener('click', () => attachInput?.click());
        attachInput?.addEventListener('change', async () => {
            await addAttachments(kind, attachInput.files);
            attachInput.value = '';
        });

        chips?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-remove-attach]');
            if (!btn) return;
            const [bucketKind, idx] = btn.dataset.removeAttach.split(':');
            const bucket = attachmentBucket(bucketKind);
            const files = fileAttachmentsOnly(state[bucket] || []);
            files.splice(Number(idx), 1);
            state[bucket] = files;
            renderAttachChips(bucketKind);
        });

        mentionBtn?.addEventListener('click', () => {
            if (!editor) return;
            insertHtmlAtCaret(editor, '@');
            renderMentionPopup(kind, '');
            editor.focus();
        });

        popup?.addEventListener('click', (e) => {
            const item = e.target.closest('[data-mention-id]');
            if (!item) return;
            const member = state.members.find(m => String(m.id) === String(item.dataset.mentionId));
            applyMention(kind, member);
        });

        const toggle = templatePicker?.querySelector('[data-template-picker-toggle]');
        const search = templatePicker?.querySelector('[data-template-picker-search]');
        const list = templatePicker?.querySelector('[data-template-picker-list]');
        toggle?.addEventListener('click', (e) => {
            e.stopPropagation();
            if (templatePicker.classList.contains('is-open')) {
                closeTemplatePickers();
            } else {
                openTemplatePicker(templatePicker);
            }
        });
        search?.addEventListener('input', () => renderTemplatePickerList(templatePicker));
        search?.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeTemplatePickers();
                return;
            }
            const items = [...(list?.querySelectorAll('[data-insert-template-id]') || [])];
            if (!items.length) return;
            const active = list.querySelector('.is-active') || items[0];
            let idx = Math.max(0, items.indexOf(active));
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                items[idx]?.classList.remove('is-active');
                idx = (idx + 1) % items.length;
                items[idx]?.classList.add('is-active');
                items[idx]?.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                items[idx]?.classList.remove('is-active');
                idx = (idx - 1 + items.length) % items.length;
                items[idx]?.classList.add('is-active');
                items[idx]?.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const chosen = list.querySelector('.is-active') || items[0];
                if (chosen) {
                    insertTemplateInto(kind, chosen.dataset.insertTemplateId);
                    closeTemplatePickers();
                }
            }
        });
        list?.addEventListener('click', (e) => {
            const item = e.target.closest('[data-insert-template-id]');
            if (!item) return;
            insertTemplateInto(kind, item.dataset.insertTemplateId);
            closeTemplatePickers();
        });

        editor?.addEventListener('input', () => {
            const info = mentionQueryAtCursor(editor);
            if (info) renderMentionPopup(kind, info.query);
            else hideMentionPopup(kind);
        });

        editor?.addEventListener('keydown', (e) => {
            const popupEl = el(mentionPopupId(kind));
            if (!popupEl || popupEl.hidden) return;
            const items = [...popupEl.querySelectorAll('[data-mention-id]')];
            if (!items.length) return;
            const active = popupEl.querySelector('.is-active');
            let idx = Math.max(0, items.indexOf(active));
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                items[idx]?.classList.remove('is-active');
                idx = (idx + 1) % items.length;
                items[idx]?.classList.add('is-active');
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                items[idx]?.classList.remove('is-active');
                idx = (idx - 1 + items.length) % items.length;
                items[idx]?.classList.add('is-active');
            } else if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                const member = state.members.find(m => String(m.id) === String(items[idx].dataset.mentionId));
                applyMention(kind, member);
            } else if (e.key === 'Escape') {
                hideMentionPopup(kind);
            }
        });
    }

    const el = (id) => document.getElementById(id);

    function folderLabel(view) {
        return MAILBOX_FOLDERS.find(f => f.view === view)?.label
            || ({ open: 'Open', assigned_to_me: 'Assigned to me', unassigned: 'Unassigned', archived: 'Archived', snoozed: 'Snoozed' }[view] || view);
    }

    function updateListTitle() {
        if (state.selectedInboxId) {
            const inbox = state.inboxes.find(i => i.id === state.selectedInboxId);
            el('listTitle').textContent = (inbox?.name || 'Inbox') + ' · ' + folderLabel(state.view);
            return;
        }
        el('listTitle').textContent = folderLabel(state.view);
    }

    async function api(path, options = {}) {
        const res = await fetch(API + path, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            ...options,
            body: options.body ? JSON.stringify(options.body) : undefined,
        });
        const data = await res.json().catch(() => null);
        if (!res.ok) throw new Error((data && data.message) || `Request failed (${res.status})`);
        return data || {};
    }

    function initials(name) {
        return (name || '?').split(/\s+/).slice(0, 2).map(s => s[0]?.toUpperCase() || '').join('');
    }

    function formatRelativeTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';

        let secs = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
        const days = Math.floor(secs / 86400);
        secs %= 86400;
        const hours = Math.floor(secs / 3600);
        secs %= 3600;
        const mins = Math.floor(secs / 60);

        if (days > 0) return `${days}d ${hours}h ${mins}m`;
        if (hours > 0) return `${hours}h ${mins}m`;
        if (mins > 0) return `${mins}m`;
        return 'just now';
    }

    function formatAbsoluteTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        return d.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    }

    function pad2(n) {
        return String(n).padStart(2, '0');
    }

    /** datetime-local value from a Date in the browser's local wall clock */
    function toDatetimeLocalValue(date) {
        const d = date instanceof Date ? date : new Date(date);
        return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}T${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
    }

    /**
     * Convert datetime-local input to an API send_at string (app timezone wall clock).
     * Avoids UTC shifts from Date#toISOString().
     */
    function datetimeLocalToApi(raw) {
        const value = String(raw || '').trim();
        if (!value) return null;
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value)) {
            return value.replace('T', ' ') + ':00';
        }
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/.test(value)) {
            return value.replace('T', ' ').slice(0, 19);
        }
        return value;
    }

    function isDatetimeLocalInFuture(raw) {
        const api = datetimeLocalToApi(raw);
        if (!api) return false;
        const when = new Date(api.replace(' ', 'T'));
        return !Number.isNaN(when.getTime()) && when.getTime() > Date.now();
    }

    function timeAgo(iso) {
        return formatRelativeTime(iso);
    }

    function refreshConversationTimes() {
        document.querySelectorAll('[data-conv-time]').forEach(node => {
            const iso = node.dataset.convTime;
            if (!iso) {
                node.textContent = '';
                return;
            }
            if (node.classList.contains('is-absolute')) {
                node.textContent = formatAbsoluteTime(iso);
                node.title = 'Click to show relative time';
            } else {
                node.textContent = formatRelativeTime(iso);
                node.title = 'Click to show date & time';
            }
        });
    }

    function openModal(id) {
        el('modalBackdrop').style.display = 'flex';
        ['modalCompose','modalReply','modalInbox','modalTemplateList','modalTemplate','modalSignatureList','modalSignature','modalRule','modalMembers','modalMerge','modalAdvancedSearch'].forEach(m => {
            const node = el(m);
            if (node) node.style.display = m === id ? 'grid' : 'none';
        });
        state.advancedOpen = id === 'modalAdvancedSearch';
        updateAdvancedToggleState();
    }
    function closeModal() {
        const returningFromTemplateEdit = el('modalTemplate')?.style.display === 'grid' && state.returnToTemplateList;
        if (returningFromTemplateEdit) {
            state.returnToTemplateList = false;
            state.editingTemplateId = null;
            state.templateAttachments = [];
            renderAttachChips('template');
            openTemplateListModal();
            return;
        }
        const returningFromSignatureEdit = el('modalSignature')?.style.display === 'grid' && state.returnToSignatureList;
        if (returningFromSignatureEdit) {
            state.returnToSignatureList = false;
            state.editingSignatureId = null;
            openSignatureListModal();
            return;
        }
        el('modalBackdrop').style.display = 'none';
        closeHtmlLinkDialog();
        state.advancedOpen = false;
        updateAdvancedToggleState();
        state.editingTemplateId = null;
        state.editingSignatureId = null;
        state.returnToTemplateList = false;
        state.returnToSignatureList = false;
        state.templateAttachments = [];
        renderAttachChips('template');
        closeTemplatePickers();
    }

    let mergeSearchTimer = null;

    function mergeFolderLabel(folder) {
        return { inbox: 'Inbox', drafts: 'Drafts', sent: 'Sent', trash: 'Trash', spam: 'Spam' }[folder] || folder || 'Inbox';
    }

    function renderMergeCandidates(conversations) {
        const list = el('mergeCandidateList');
        if (!list) return;
        if (!conversations.length) {
            list.innerHTML = '<div class="inbox-tool-empty">No other threads in this inbox match.</div>';
            return;
        }
        list.innerHTML = conversations.map(c => `
            <button type="button" class="inbox-merge-row" data-merge-id="${c.id}">
                <span class="inbox-merge-row-from">${escapeHtml(c.from_name || c.from_email || 'Unknown')}</span>
                <span class="inbox-merge-row-subject">${escapeHtml(c.subject || '(No subject)')}</span>
                <span class="inbox-merge-row-meta">${escapeHtml(mergeFolderLabel(c.folder))} · ${escapeHtml(formatThreadTime(c.last_message_at) || '')}</span>
            </button>
        `).join('');
    }

    function renderMergedThreads() {
        const section = el('mergeMergedSection');
        const list = el('mergeMergedList');
        const threads = state.conversation?.merged_threads || [];
        if (!section || !list) return;
        section.hidden = threads.length < 1;
        if (!threads.length) {
            list.innerHTML = '';
            return;
        }
        list.innerHTML = threads.map(c => `
            <div class="inbox-merge-unmerge">
                <div class="inbox-merge-row">
                    <span class="inbox-merge-row-from">${escapeHtml(c.from_name || c.from_email || 'Unknown')}</span>
                    <span class="inbox-merge-row-subject">${escapeHtml(c.subject || '(No subject)')}</span>
                    <span class="inbox-merge-row-meta">${escapeHtml(mergeFolderLabel(c.folder))} · ${escapeHtml(formatThreadTime(c.last_message_at) || '')}</span>
                </div>
                <button type="button" class="inbox-btn ghost" data-unmerge-id="${c.id}">Unmerge</button>
            </div>
        `).join('');
    }

    async function loadMergeCandidates(q = '') {
        if (!state.selectedId) return;
        const params = new URLSearchParams();
        if (q) params.set('q', q);
        const data = await api('/conversations/' + state.selectedId + '/merge-candidates?' + params.toString());
        renderMergeCandidates(data.conversations || []);
    }

    async function openMergeModal() {
        if (!state.conversation) return;
        const inbox = state.conversation.inbox || state.inboxes.find(i => Number(i.id) === Number(state.conversation.inbox_id));
        const kind = inbox?.type === 'personal' ? 'personal inbox' : 'shared inbox';
        el('mergeModalHelp').textContent = inbox?.name
            ? `Merge and unmerge threads in this ${kind} (${inbox.name}). Threads from other inboxes cannot be combined here.`
            : 'Merge and unmerge works in personal and shared inboxes. Threads must belong to the same inbox.';
        if (el('mergeSearch')) el('mergeSearch').value = '';
        renderMergedThreads();
        renderMergeCandidates([]);
        openModal('modalMerge');
        try {
            await loadMergeCandidates();
        } catch (err) {
            el('mergeCandidateList').innerHTML = `<div class="inbox-tool-empty">${escapeHtml(err.message || 'Could not load threads.')}</div>`;
        }
        setTimeout(() => el('mergeSearch')?.focus(), 50);
    }

    async function mergeSelectedConversation(sourceId) {
        if (!state.selectedId || !sourceId) return;
        const row = el('mergeCandidateList')?.querySelector(`[data-merge-id="${sourceId}"]`);
        const label = row?.querySelector('.inbox-merge-row-subject')?.textContent || 'this conversation';
        if (!confirm(`Merge “${label}” into the open thread?\n\nBoth must belong to this same personal or shared inbox. You can unmerge them later.`)) {
            return;
        }
        await api('/conversations/' + state.selectedId + '/merge', {
            method: 'POST',
            body: { conversation_id: Number(sourceId) },
        });
        closeModal();
        await loadBootstrap();
        await loadConversations();
        await openConversation(state.selectedId);
    }

    async function unmergeSelectedConversation(sourceId = null) {
        if (!state.selectedId) return;
        const all = sourceId == null;
        const ok = all
            ? confirm('Unmerge every conversation that was merged into this thread?')
            : confirm('Split this conversation back out into its own thread?');
        if (!ok) return;
        await api('/conversations/' + state.selectedId + '/unmerge', {
            method: 'POST',
            body: all ? {} : { conversation_id: Number(sourceId) },
        });
        await loadBootstrap();
        await loadConversations();
        await openConversation(state.selectedId);
        renderMergedThreads();
        try {
            await loadMergeCandidates(el('mergeSearch')?.value || '');
        } catch (err) {
            console.warn(err);
        }
        if (!(state.conversation?.merged_threads || []).length) {
            closeModal();
        }
    }

    function openTemplateModal(templateId = null) {
        if (!state.permissions.create_templates) {
            alert('You do not have permission to manage templates.');
            return;
        }
        state.returnToTemplateList = el('modalTemplateList')?.style.display === 'grid';
        const item = templateId
            ? state.templates.find(t => String(t.id) === String(templateId))
            : null;
        state.editingTemplateId = item ? item.id : null;
        el('templateModalTitle').textContent = item ? 'Edit template' : 'New template';
        el('btnSaveTemplate').textContent = item ? 'Save' : 'Create';
        const deleteBtn = el('btnDeleteTemplate');
        if (deleteBtn) deleteBtn.style.display = item ? '' : 'none';
        el('newTemplateName').value = item?.name || '';
        el('newTemplateSubject').value = item?.subject || '';
        setHtmlEditorContent('template', item?.body_html || plainToHtml(item?.body || '') || '');
        setHtmlEditorMode('template', 'visual');
        state.templateAttachments = fileAttachmentsOnly(item?.attachments || []).map((a) => ({
            name: a.name,
            contentType: a.contentType || 'application/octet-stream',
            contentBytes: a.contentBytes,
            size: a.size || Math.round(String(a.contentBytes || '').length * 0.75),
        }));
        renderAttachChips('template');
        openModal('modalTemplate');
        setTimeout(() => el('newTemplateName')?.focus(), 50);
    }

    function deleteTemplateById(templateId) {
        if (!state.permissions.create_templates) {
            alert('You do not have permission to manage templates.');
            return Promise.resolve(false);
        }
        if (!templateId) return Promise.resolve(false);
        const item = state.templates.find(t => String(t.id) === String(templateId));
        if (!item) return Promise.resolve(false);
        if (!confirm(`Delete template "${item.name}"?\n\nThis removes it for everyone in your company.`)) {
            return Promise.resolve(false);
        }
        return api('/templates/' + templateId, { method: 'DELETE' })
            .then(() => {
                state.templates = state.templates.filter(t => String(t.id) !== String(templateId));
                updateTemplateCount();
                refreshTemplateSelects();
                if (el('modalTemplateList')?.style.display === 'grid') {
                    renderTemplateList();
                }
                return true;
            })
            .catch(err => {
                alert(err.message || 'Failed to delete template');
                return false;
            });
    }

    function openComposeModal() {
        const connected = state.inboxes.filter(i => i.connected);
        if (!connected.length) {
            alert('Connect an Outlook inbox first.');
            return;
        }
        el('composeFrom').innerHTML = connected.map(i =>
            `<option value="${i.id}" ${state.selectedInboxId === i.id ? 'selected' : ''}>${escapeHtml(i.name)} (${escapeHtml(i.email || 'Outlook')})</option>`
        ).join('');
        if (!el('composeFrom').value && connected[0]) {
            el('composeFrom').value = String(connected[0].id);
        }
        el('composeTo').value = '';
        el('composeCc').value = '';
        el('composeSubject').value = '';
        applyComposerSignature('compose');
        state.composeAttachments = [];
        renderAttachChips('compose');
        hideMentionPopup('compose');
        refreshTemplateSelects();
        openModal('modalCompose');
        setTimeout(() => el('composeTo').focus(), 50);
    }

    function renderNav() {
        const inboxList = el('inboxList');
        inboxList.innerHTML = state.inboxes.map(inbox => {
            const expanded = !!state.expandedInboxIds[inbox.id] || state.selectedInboxId === inbox.id;
            if (state.selectedInboxId === inbox.id) state.expandedInboxIds[inbox.id] = true;
            const folders = MAILBOX_FOLDERS.map(folder => {
                const active = state.selectedInboxId === inbox.id && state.view === folder.view;
                const count = inbox[folder.countKey] || 0;
                return `
                    <button type="button" class="inbox-folder-row ${active ? 'active' : ''}"
                        data-inbox-id="${inbox.id}" data-folder-view="${folder.view}">
                        <span>${folder.label}</span>
                        ${count ? `<span class="inbox-count">${count}</span>` : '<span></span>'}
                    </button>
                `;
            }).join('');

            return `
            <div class="inbox-mailbox ${expanded ? 'is-expanded' : ''}" data-mailbox-id="${inbox.id}">
                <div class="inbox-mailbox-head ${state.selectedInboxId === inbox.id ? 'is-selected' : ''}" data-inbox-toggle="${inbox.id}">
                    <svg class="inbox-mailbox-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                    <span class="inbox-dot" style="background:${inbox.color || '#2f6fed'}"></span>
                    <span class="inbox-mailbox-meta">
                        <span class="inbox-mailbox-name-row">
                            <span class="inbox-mailbox-name" title="${escapeHtml(inbox.name)}">${escapeHtml(inbox.name)}</span>
                            <span class="inbox-mailbox-actions">
                                ${inbox.connected ? `<button type="button" class="inbox-mini-btn inbox-sync-inbox-btn" data-sync-inbox="${inbox.id}" title="Sync ${escapeHtml(inbox.name)}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                                </button>` : ''}
                                ${inbox.type === 'shared' ? `<button type="button" class="inbox-mini-btn" data-manage-members="${inbox.id}" title="Members">M</button>` : ''}
                                ${inbox.type === 'shared' ? `<button type="button" class="inbox-connect-ms365" data-connect-inbox="${inbox.id}" title="${inbox.connected ? 'Reconnect Microsoft 365' : 'Sign in with Microsoft 365'}">${inbox.connected ? 'MS365' : 'Sign in'}</button>` : ''}
                                ${inbox.unread_count ? `<span class="inbox-count">${inbox.unread_count}</span>` : ''}
                            </span>
                        </span>
                        <span class="inbox-mailbox-sub" title="${escapeHtml(inbox.email || '')}">${escapeHtml(inbox.email || 'no email set')} · ${inbox.type}${inbox.connected ? '' : ' · not connected'}</span>
                    </span>
                </div>
                <div class="inbox-mailbox-folders">${folders}</div>
            </div>
            `;
        }).join('') || '<div style="padding:0.4rem 0.55rem;font-size:0.8rem;color:var(--inbox-muted);">No inboxes yet</div>';

        updateTemplateCount();
        updateSignatureCount();

        // Keep tool-group expand state in sync with render
        document.querySelectorAll('[data-tool-group]').forEach(group => {
            const key = group.dataset.toolGroup;
            group.classList.toggle('is-expanded', !!state.expandedToolGroups[key]);
        });
        const toolsToggle = el('btnToggleInboxTools');
        if (toolsToggle) {
            toolsToggle.classList.toggle('is-expanded', !!state.inboxToolsOpen);
            toolsToggle.setAttribute('aria-expanded', state.inboxToolsOpen ? 'true' : 'false');
        }

        const openCount = state.inboxes.reduce((n, i) => n + (i.open_count || 0), 0);
        el('countOpen').textContent = openCount;

        // Highlight global views only when not scoped to a mailbox folder
        document.querySelectorAll('[data-view][data-scope="all"]').forEach(btn => {
            const active = !state.selectedInboxId && state.view === btn.dataset.view;
            btn.classList.toggle('active', active);
        });

        const assign = el('assignSelect');
        const current = assign.value;
        assign.innerHTML = '<option value="">Unassigned</option>' + state.members.map(m =>
            `<option value="${m.id}">${escapeHtml(m.name)}</option>`
        ).join('');
        assign.value = current;
        if (state.conversation) renderAssignMenu();

        el('newInboxMembers').innerHTML = state.members.map(m =>
            `<option value="${m.id}">${escapeHtml(m.name)} (${escapeHtml(m.email || '')})</option>`
        ).join('');

        // Rule builder uses a custom inbox picker; refresh it with current inboxes.
        renderRuleInboxPicker();

        const advInbox = el('advInbox');
        if (advInbox) {
            const prev = advInbox.value || state.filters.inbox_id || '';
            advInbox.innerHTML = '<option value="">All inboxes</option>' + state.inboxes.map(i =>
                `<option value="${i.id}">${escapeHtml(i.name)}</option>`
            ).join('');
            advInbox.value = prev;
        }

        const advAssigned = el('advAssigned');
        if (advAssigned) {
            const prevA = advAssigned.value !== ''
                ? advAssigned.value
                : (state.filters.assigned_to !== '' && state.filters.assigned_to != null
                    ? String(state.filters.assigned_to)
                    : '');
            advAssigned.innerHTML = '<option value="">Anyone</option><option value="0">Unassigned</option>' +
                state.members.map(m => `<option value="${m.id}">${escapeHtml(m.name)}</option>`).join('');
            advAssigned.value = prevA;
        }

        refreshRuleActionValueSelects();
        updateListTitle();
        renderFilterChips();
        refreshTemplateSelects();
    }

    function selectedRuleInboxIds() {
        return [...document.querySelectorAll('#ruleInboxMenu input[type="checkbox"]:checked')]
            .map(cb => Number(cb.value))
            .filter(id => Number.isFinite(id));
    }

    function updateRuleInboxToggleLabel() {
        const ids = selectedRuleInboxIds();
        const label = el('ruleInboxToggleLabel');
        if (!label) return;
        if (!ids.length) {
            label.textContent = 'Select inboxes';
            return;
        }
        const names = state.inboxes
            .filter(i => ids.includes(Number(i.id)))
            .map(i => i.name);
        label.textContent = names.length <= 2
            ? names.join(', ')
            : `${names.length} inboxes selected`;
    }

    function renderRuleInboxPicker() {
        const menu = el('ruleInboxMenu');
        if (!menu) return;
        const prev = new Set(selectedRuleInboxIds().map(String));
        menu.innerHTML = state.inboxes.map(i => `
            <label class="inbox-rule-inbox-option">
                <input type="checkbox" value="${i.id}" ${prev.has(String(i.id)) ? 'checked' : ''}>
                <span>${escapeHtml(i.name)}</span>
            </label>
        `).join('') || '<div class="inbox-tool-empty" style="padding:0.5rem;">No inboxes</div>';
        updateRuleInboxToggleLabel();
    }

    function conditionFieldOptions(selected = 'from_email') {
        const fields = [
            ['from_email', 'From email'],
            ['from_name', 'From name'],
            ['subject', 'Subject'],
            ['snippet', 'Body preview'],
        ];
        return fields.map(([value, label]) =>
            `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`
        ).join('');
    }

    function conditionOperatorOptions(selected = 'contains') {
        const ops = [
            ['contains', 'contains'],
            ['equals', 'equals'],
            ['starts_with', 'starts with'],
        ];
        return ops.map(([value, label]) =>
            `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`
        ).join('');
    }

    function actionTypeOptions(selected = 'assign') {
        const types = [
            ['assign', 'Assign to'],
            ['notify_assignee', 'Notify assignee'],
            ['archive', 'Archive'],
            ['reopen', 'Reopen now'],
            ['reopen_after_days', 'Reopen after days'],
            ['mark_read', 'Mark read'],
            ['mark_unread', 'Mark unread'],
        ];
        return types.map(([value, label]) =>
            `<option value="${value}" ${value === selected ? 'selected' : ''}>${label}</option>`
        ).join('');
    }

    function actionValueOptions(type, selected = '') {
        if (type === 'assign') {
            return state.members.map(m =>
                `<option value="${m.id}" ${String(selected) === String(m.id) ? 'selected' : ''}>${escapeHtml(m.name)}</option>`
            ).join('') || '<option value="">No teammates</option>';
        }
        if (type === 'reopen_after_days') {
            const days = [1, 2, 3, 5, 7, 14, 30, 60, 90];
            const selectedDay = String(selected || '3');
            const opts = days.map(d =>
                `<option value="${d}" ${selectedDay === String(d) ? 'selected' : ''}>${d} day${d === 1 ? '' : 's'}</option>`
            ).join('');
            return opts + (days.includes(Number(selectedDay)) ? '' : `<option value="${escapeHtml(selectedDay)}" selected>${escapeHtml(selectedDay)} days</option>`);
        }
        return '<option value="">—</option>';
    }

    function addRuleConditionRow(preset = {}) {
        const wrap = el('ruleExtraConditions');
        if (!wrap) return;
        const row = document.createElement('div');
        row.className = 'inbox-rule-extra-card';
        row.innerHTML = `
            <select class="form-input" data-rule-cond-field>${conditionFieldOptions(preset.field || 'from_email')}</select>
            <select class="form-input" data-rule-cond-operator>${conditionOperatorOptions(preset.operator || 'contains')}</select>
            <input type="text" class="form-input" data-rule-cond-value placeholder="Value" value="${escapeHtml(preset.value || '')}">
            <button type="button" class="inbox-rule-remove" data-remove-rule-row title="Remove">×</button>
        `;
        wrap.appendChild(row);
    }

    function addRuleActionRow(preset = {}) {
        const wrap = el('ruleActions');
        if (!wrap) return;
        const type = preset.type || 'assign';
        const needsValue = !['archive', 'reopen', 'notify_assignee', 'mark_read', 'mark_unread'].includes(type);
        const row = document.createElement('div');
        row.className = 'inbox-rule-extra-card is-action';
        row.innerHTML = `
            <select class="form-input" data-rule-action-type>${actionTypeOptions(type)}</select>
            <select class="form-input" data-rule-action-value ${needsValue ? '' : 'disabled'}>
                ${actionValueOptions(type, preset.value || (type === 'reopen_after_days' ? '3' : ''))}
            </select>
            <button type="button" class="inbox-rule-remove" data-remove-rule-row title="Remove">×</button>
        `;
        wrap.appendChild(row);
    }

    function refreshRuleActionValueSelects() {
        document.querySelectorAll('#ruleActions [data-rule-action-type]').forEach(typeSel => {
            const row = typeSel.closest('.inbox-rule-extra-card');
            const valueSel = row?.querySelector('[data-rule-action-value]');
            if (!valueSel) return;
            const type = typeSel.value;
            const prev = valueSel.value;
            const needsValue = !['archive', 'reopen', 'notify_assignee', 'mark_read', 'mark_unread'].includes(type);
            valueSel.innerHTML = actionValueOptions(type, type === 'reopen_after_days' ? (prev || '3') : prev);
            valueSel.disabled = !needsValue;
        });
    }

    const RULE_TRIGGERS = [
        { value: 'inbound_message', label: 'Inbound message is received', help: 'Any inbound email, including replies in existing threads.' },
        { value: 'inbound_message_new', label: 'Inbound message is received (new conversation)', help: 'Only when a brand-new conversation is created.' },
        { value: 'outbound_message_new', label: 'Outbound message is sent (new conversation)', help: 'When you compose and send a new email.' },
        { value: 'outbound_reply', label: 'Outbound reply is sent', help: 'When a reply is sent on an existing conversation.' },
        { value: 'conversation_assigned', label: 'Conversation is assigned', help: 'When a teammate is assigned to the conversation.' },
        { value: 'conversation_archived', label: 'Conversation is archived', help: 'When a conversation is archived.' },
        { value: 'conversation_moved', label: 'Conversation is moved', help: 'When moved to spam, trash, inbox, or similar.' },
        { value: 'comment_added', label: 'Internal comment is added', help: 'When a teammate posts an internal comment.' },
    ];

    function triggerOptions(selected = 'inbound_message') {
        return RULE_TRIGGERS.map(t =>
            `<option value="${t.value}" ${t.value === selected ? 'selected' : ''}>${escapeHtml(t.label)}</option>`
        ).join('');
    }

    function triggerHelp(value) {
        return RULE_TRIGGERS.find(t => t.value === value)?.help || '';
    }

    function addRuleTriggerRow(preset = {}) {
        const wrap = el('ruleTriggers');
        if (!wrap) return;
        const value = preset.value || 'inbound_message';
        const row = document.createElement('div');
        row.className = 'inbox-rule-extra-card is-trigger';
        row.innerHTML = `
            <div>
                <select class="form-input" data-rule-trigger>${triggerOptions(value)}</select>
                <p class="inbox-rule-trigger-help" data-rule-trigger-help>${escapeHtml(triggerHelp(value))}</p>
            </div>
            <button type="button" class="inbox-rule-remove" data-remove-rule-row title="Remove">×</button>
        `;
        wrap.appendChild(row);
        refreshRuleTriggerAddState();
    }

    function refreshRuleTriggerAddState() {
        const btn = el('btnAddRuleTrigger');
        if (!btn) return;
        const count = document.querySelectorAll('#ruleTriggers [data-rule-trigger]').length;
        btn.disabled = count >= RULE_TRIGGERS.length;
        btn.title = btn.disabled ? 'All triggers added' : 'Add another trigger';
    }

    function resetRuleBuilder() {
        if (el('ruleName')) el('ruleName').value = '';
        if (el('ruleStopProcessing')) el('ruleStopProcessing').checked = false;
        if (el('ruleTriggers')) el('ruleTriggers').innerHTML = '';
        if (el('ruleExtraConditions')) el('ruleExtraConditions').innerHTML = '';
        if (el('ruleActions')) el('ruleActions').innerHTML = '';
        document.querySelectorAll('#ruleInboxMenu input[type="checkbox"]').forEach(cb => { cb.checked = false; });
        updateRuleInboxToggleLabel();
        const menu = el('ruleInboxMenu');
        if (menu) menu.hidden = true;
        addRuleTriggerRow({ value: 'inbound_message' });
        addRuleActionRow();
    }

    function openRuleModal() {
        renderRuleInboxPicker();
        resetRuleBuilder();
        openModal('modalRule');
        setTimeout(() => el('ruleName')?.focus(), 50);
    }

    function collectRulePayload() {
        const name = el('ruleName')?.value.trim() || '';
        const inboxIds = selectedRuleInboxIds();
        const triggers = [];
        document.querySelectorAll('#ruleTriggers [data-rule-trigger]').forEach(sel => {
            if (sel.value && !triggers.includes(sel.value)) triggers.push(sel.value);
        });
        const conditions = [
            { field: 'inbox', operator: 'in', value: inboxIds },
        ];
        document.querySelectorAll('#ruleExtraConditions .inbox-rule-extra-card').forEach(row => {
            const field = row.querySelector('[data-rule-cond-field]')?.value;
            const operator = row.querySelector('[data-rule-cond-operator]')?.value;
            const value = row.querySelector('[data-rule-cond-value]')?.value.trim() || '';
            if (field && operator) {
                conditions.push({ field, operator, value });
            }
        });
        const actions = [];
        document.querySelectorAll('#ruleActions .inbox-rule-extra-card').forEach(row => {
            const type = row.querySelector('[data-rule-action-type]')?.value;
            const valueSel = row.querySelector('[data-rule-action-value]');
            if (!type) return;
            actions.push({
                type,
                value: valueSel && !valueSel.disabled ? (valueSel.value || null) : null,
            });
        });
        return {
            name,
            shared_inbox_id: null,
            stop_processing: !!el('ruleStopProcessing')?.checked,
            triggers,
            conditions,
            actions,
        };
    }
    function leadLabelChipsHtml(labels) {
        if (window.LnsAssignedLead?.chips) return window.LnsAssignedLead.chips(labels, escapeHtml);
        const names = (labels || []).map(l => l?.name).filter(Boolean);
        return names.length ? `<div class="channel-assigned">${names.map(escapeHtml).join(', ')}</div>` : '';
    }

    function conversationLead(c) {
        return (c || state.conversation)?.lead || null;
    }

    function conversationAssigneeId(c) {
        const conv = c || state.conversation;
        const lead = conversationLead(conv);
        if (lead) return lead.assigned_to || '';
        return conv?.assigned_to || '';
    }

    function conversationAssignee(c) {
        const conv = c || state.conversation;
        const lead = conversationLead(conv);
        if (lead) return lead.assigned_user || null;
        return conv?.assignee || null;
    }

    function conversationTagItems(c) {
        const conv = c || state.conversation;
        const leadLabels = conversationLead(conv)?.labels || [];
        const conversationLabels = conv?.lead_labels || [];
        const inboxTags = conv?.tags || [];
        const merged = new Map();

        leadLabels.forEach(label => {
            const key = String(label?.name || '').trim().toLowerCase();
            if (key) {
                merged.set(key, { ...label, source: 'lead' });
            }
        });

        conversationLabels.forEach(label => {
            const key = String(label?.name || '').trim().toLowerCase();
            if (key && !merged.has(key)) {
                merged.set(key, { ...label, source: 'conversation-label' });
            }
        });

        inboxTags.forEach(tag => {
            const key = String(tag?.name || '').trim().toLowerCase();
            if (key && !merged.has(key)) {
                merged.set(key, { ...tag, source: 'inbox' });
            }
        });

        return Array.from(merged.values());
    }

    function conversationLabelPillHtml(label, { removable = true } = {}) {
        let removeBtn = '';
        if (removable) {
            if (label.source === 'lead') {
                removeBtn = `<button type="button" data-remove-lead-label="${label.id}" style="border:none;background:transparent;cursor:pointer;color:inherit;">×</button>`;
            } else if (label.source === 'conversation-label') {
                removeBtn = `<button type="button" data-remove-conversation-label="${label.id}" style="border:none;background:transparent;cursor:pointer;color:inherit;">×</button>`;
            } else {
                removeBtn = `<button type="button" data-remove-inbox-tag="${label.id}" style="border:none;background:transparent;cursor:pointer;color:inherit;">×</button>`;
            }
        }

        return `<span class="inbox-pill" style="background:${label.color}22;color:${label.color}">${escapeHtml(label.name)} ${removeBtn}</span>`;
    }

    function leadAssignedBlock(lead, withLink = true) {
        if (!lead?.crm_url || !withLink) return '';
        return `<a class="chp-link" href="${escapeHtml(lead.crm_url)}" target="_blank" rel="noopener">Open lead →</a>`;
    }

    function conversationSnippetText(c) {
        const selected = state.conversation;
        const messages = (selected && Number(selected.id) === Number(c?.id) && Array.isArray(selected.messages))
            ? selected.messages
            : (c?.messages || []);
        if (messages.length) {
            const last = [...messages].sort((a, b) => String(a.sent_at || '').localeCompare(String(b.sent_at || ''))).at(-1);
            const preview = messagePreviewText(last);
            if (preview) return preview;
        }
        return stripQuotedEmailHistoryPlain(String(c?.snippet || '')).replace(/\s+/g, ' ').trim();
    }

    function conversationRowHtml(c) {
        const at = c.last_message_at || '';
        return `
            <button type="button" class="inbox-conv ${c.id === state.selectedId ? 'active' : ''} ${isConversationChecked(c.id) ? 'is-checked' : ''} ${c.is_read ? '' : 'unread'}" data-conv-id="${c.id}">
                <div class="inbox-conv-top">
                    <span>${escapeHtml(c.inbox?.name || '')}</span>
                    <span class="inbox-conv-time" data-conv-time="${escapeHtml(at)}" title="Click to show date & time">${formatRelativeTime(at)}</span>
                </div>
                <div class="inbox-conv-from">${escapeHtml(c.from_name || c.from_email || 'Unknown')}</div>
                <div class="inbox-conv-subject">${escapeHtml(c.subject || '(No subject)')}</div>
                <div class="inbox-conv-snippet">${escapeHtml(conversationSnippetText(c))}</div>
                <div class="inbox-conv-tags">
                    ${conversationTagItems(c).map(t => `<span class="inbox-pill" style="background:${t.color}22;color:${t.color}">${escapeHtml(t.name)}</span>`).join('')}
                    ${conversationAssignee(c)?.name ? `<span class="inbox-pill">${escapeHtml(conversationAssignee(c).name)}</span>` : ''}
                    ${c.reopen_at ? `<span class="inbox-pill">Snoozed ${escapeHtml(formatThreadTime(c.reopen_at) ? 'until ' + formatAbsoluteTime(c.reopen_at) : '')}</span>` : ''}
                    ${c.merged_count ? `<span class="inbox-pill">Merged</span>` : ''}
                </div>
            </button>
        `;
    }

    function isConversationChecked(id) {
        return state.checkedIds.some(checkedId => Number(checkedId) === Number(id));
    }

    function conversationInboxId(c) {
        return Number(c?.inbox_id || c?.inbox?.id || 0);
    }

    function checkedConversations() {
        return state.checkedIds
            .map(id => state.conversations.find(c => Number(c.id) === Number(id)))
            .filter(Boolean);
    }

    function syncCheckedRows() {
        el('conversationList')?.querySelectorAll('.inbox-conv').forEach(btn => {
            btn.classList.toggle('is-checked', isConversationChecked(btn.dataset.convId));
        });
        updateMergeBar();
    }

    function updateMergeBar() {
        const bar = el('listMergeBar');
        const btn = el('btnMergeSelected');
        const countEl = el('listMergeCount');
        if (!bar || !btn || !countEl) return;

        const selected = checkedConversations();
        const count = selected.length;
        if (count < 2) {
            bar.hidden = true;
            return;
        }

        const inboxIds = [...new Set(selected.map(conversationInboxId).filter(Boolean))];
        const sameInbox = inboxIds.length === 1;
        bar.hidden = false;
        countEl.textContent = count + ' selected';
        btn.disabled = !sameInbox;
        btn.textContent = sameInbox ? 'Merge conversations' : 'Same inbox required';
        btn.title = sameInbox
            ? 'Merge the selected threads into one conversation'
            : 'Select threads from one personal or shared inbox';
    }

    function clearCheckedConversations() {
        state.checkedIds = [];
        syncCheckedRows();
    }

    function toggleCheckedConversation(id) {
        const next = new Set(state.checkedIds.map(Number));
        if (next.size === 0 && state.selectedId && Number(state.selectedId) !== Number(id)) {
            next.add(Number(state.selectedId));
        }
        if (next.has(Number(id))) next.delete(Number(id));
        else next.add(Number(id));
        state.checkedIds = [...next];
        syncCheckedRows();
    }

    async function mergeCheckedConversations() {
        const selected = checkedConversations();
        if (selected.length < 2) return;
        const inboxIds = [...new Set(selected.map(conversationInboxId).filter(Boolean))];
        if (inboxIds.length !== 1) {
            alert('Select threads from the same personal or shared inbox.');
            return;
        }

        const target = selected.find(c => Number(c.id) === Number(state.selectedId)) || selected[0];
        const sourceIds = selected
            .map(c => Number(c.id))
            .filter(id => id !== Number(target.id));
        if (!confirm(`Merge ${selected.length} conversations into “${target.subject || '(No subject)'}”?\n\nThey must belong to the same inbox. You can unmerge them later.`)) {
            return;
        }

        await api('/conversations/' + target.id + '/merge', {
            method: 'POST',
            body: { conversation_ids: sourceIds },
        });
        clearCheckedConversations();
        await loadBootstrap();
        await loadConversations();
        await openConversation(target.id);
    }

    function listFooterHtml() {
        if (state.listLoading) {
            return '<div class="inbox-list-loading" id="listLoadingMore">Loading older emails…</div>';
        }
        if (state.listHasMore) {
            return '<div class="inbox-list-loading" id="listLoadingMore" hidden></div>';
        }
        return '<div class="inbox-list-end">All emails loaded</div>';
    }

    function renderConversations() {
        const list = el('conversationList');
        if (!state.conversations.length) {
            list.innerHTML = '<div class="inbox-empty" id="listEmpty">No conversations in this view.</div>';
            return;
        }
        list.innerHTML = state.conversations.map(conversationRowHtml).join('') + listFooterHtml();
        syncCheckedRows();
    }

    function updateListFooter() {
        const list = el('conversationList');
        if (!list) return;
        const old = list.querySelector('#listLoadingMore, .inbox-list-end');
        if (old) old.outerHTML = listFooterHtml();
        else list.insertAdjacentHTML('beforeend', listFooterHtml());
    }

    function hasActiveFilters() {
        const f = state.filters;
        return !!(f.from || f.to || f.subject || f.body || f.folder || f.inbox_id
            || f.assigned_to !== '' || f.is_read !== '' || f.date_from || f.date_to);
    }

    function syncAdvancedFormFromState() {
        const f = state.filters;
        if (el('advFrom')) el('advFrom').value = f.from || '';
        if (el('advTo')) el('advTo').value = f.to || '';
        if (el('advSubject')) el('advSubject').value = f.subject || '';
        if (el('advBody')) el('advBody').value = f.body || '';
        if (el('advFolder')) el('advFolder').value = f.folder || '';
        if (el('advInbox')) el('advInbox').value = f.inbox_id || '';
        if (el('advAssigned')) el('advAssigned').value = f.assigned_to === 0 || f.assigned_to === '0' ? '0' : (f.assigned_to || '');
        if (el('advRead')) el('advRead').value = f.is_read === 0 || f.is_read === '0' ? '0' : (f.is_read || '');
        if (el('advDateFrom')) el('advDateFrom').value = f.date_from || '';
        if (el('advDateTo')) el('advDateTo').value = f.date_to || '';
    }

    function readAdvancedFormIntoState() {
        state.filters = {
            from: (el('advFrom')?.value || '').trim(),
            to: (el('advTo')?.value || '').trim(),
            subject: (el('advSubject')?.value || '').trim(),
            body: (el('advBody')?.value || '').trim(),
            folder: el('advFolder')?.value || '',
            inbox_id: el('advInbox')?.value || '',
            assigned_to: el('advAssigned')?.value ?? '',
            is_read: el('advRead')?.value ?? '',
            date_from: el('advDateFrom')?.value || '',
            date_to: el('advDateTo')?.value || '',
        };
    }

    function clearAdvancedFilters({ reload = true } = {}) {
        state.filters = {
            from: '', to: '', subject: '', body: '', folder: '',
            inbox_id: '', assigned_to: '', is_read: '', date_from: '', date_to: '',
        };
        syncAdvancedFormFromState();
        updateAdvancedToggleState();
        renderFilterChips();
        if (reload) loadConversations({ append: false });
    }

    function applyAdvancedFilters() {
        readAdvancedFormIntoState();
        updateAdvancedToggleState();
        renderFilterChips();
        closeModal();
        loadConversations({ append: false });
    }

    function updateAdvancedToggleState() {
        const btn = el('btnToggleAdvancedSearch');
        if (!btn) return;
        btn.classList.toggle('is-active', hasActiveFilters() || state.advancedOpen);
        btn.textContent = hasActiveFilters() ? 'Filters ●' : 'Filters';
    }

    function setAdvancedOpen(open) {
        if (open) {
            syncAdvancedFormFromState();
            openModal('modalAdvancedSearch');
            setTimeout(() => el('advFrom')?.focus(), 50);
        } else {
            closeModal();
        }
    }

    function renderFilterChips() {
        const wrap = el('advFilterChips');
        if (!wrap) return;

        const chips = [];
        const f = state.filters;
        const push = (key, label, value) => {
            chips.push(`<button type="button" class="inbox-adv-chip" data-clear-filter="${key}" title="Remove filter">
                <span>${escapeHtml(label)}: ${escapeHtml(value)}</span><span aria-hidden="true">×</span>
            </button>`);
        };

        if (f.inbox_id) {
            const inbox = state.inboxes.find(i => String(i.id) === String(f.inbox_id));
            push('inbox_id', 'Inbox', inbox?.name || f.inbox_id);
        }
        if (f.folder) {
            const folderLabels = { any: 'Any folder', inbox: 'Inbox', drafts: 'Drafts', sent: 'Sent', trash: 'Trash', spam: 'Spam' };
            push('folder', 'Folder', folderLabels[f.folder] || f.folder);
        }
        if (f.from) push('from', 'From', f.from);
        if (f.to) push('to', 'To', f.to);
        if (f.subject) push('subject', 'Subject', f.subject);
        if (f.body) push('body', 'Body', f.body);
        if (f.assigned_to !== '' && f.assigned_to != null) {
            if (String(f.assigned_to) === '0') push('assigned_to', 'Assigned', 'Unassigned');
            else {
                const m = state.members.find(x => String(x.id) === String(f.assigned_to));
                push('assigned_to', 'Assigned', m?.name || f.assigned_to);
            }
        }
        if (f.is_read !== '' && f.is_read != null) {
            push('is_read', 'Read', String(f.is_read) === '1' || f.is_read === true ? 'Read' : 'Unread');
        }
        if (f.date_from) push('date_from', 'From date', f.date_from);
        if (f.date_to) push('date_to', 'To date', f.date_to);

        const q = el('inboxSearch')?.value?.trim();
        if (q) {
            chips.push(`<button type="button" class="inbox-adv-chip" data-clear-filter="search" title="Clear quick search">
                <span>Search: ${escapeHtml(q)}</span><span aria-hidden="true">×</span>
            </button>`);
        }

        wrap.innerHTML = chips.join('');
        updateAdvancedToggleState();
    }

    async function loadConversations({ append = false } = {}) {
        if (state.listLoading) return;
        if (append && !state.listHasMore) return;

        const page = append ? state.listPage + 1 : 1;
        state.listLoading = true;
        if (append) updateListFooter();

        try {
            const params = new URLSearchParams({ view: state.view, page: String(page) });

            const filterInboxId = state.filters.inbox_id || state.selectedInboxId;
            if (filterInboxId) params.set('inbox_id', String(filterInboxId));

            const q = el('inboxSearch').value.trim();
            if (q) params.set('search', q);

            const f = state.filters;
            if (f.from) params.set('from', f.from);
            if (f.to) params.set('to', f.to);
            if (f.subject) params.set('subject', f.subject);
            if (f.body) params.set('body', f.body);
            if (f.folder) params.set('folder', f.folder);
            if (f.assigned_to !== '' && f.assigned_to != null) params.set('assigned_to', String(f.assigned_to));
            if (f.is_read !== '' && f.is_read != null) params.set('is_read', String(f.is_read));
            if (f.date_from) params.set('date_from', f.date_from);
            if (f.date_to) params.set('date_to', f.date_to);

            const data = await api('/conversations?' + params.toString());
            const batch = data.conversations || [];
            const meta = data.meta || {};

            state.listPage = meta.current_page || page;
            state.listLastPage = meta.last_page || 1;
            state.listHasMore = state.listPage < state.listLastPage;

            if (append) {
                const seen = new Set(state.conversations.map(c => c.id));
                const fresh = batch.filter(c => !seen.has(c.id));
                state.conversations = state.conversations.concat(fresh);
                const list = el('conversationList');
                const footer = list.querySelector('#listLoadingMore, .inbox-list-end');
                const html = fresh.map(conversationRowHtml).join('');
                if (footer) footer.insertAdjacentHTML('beforebegin', html);
                else list.insertAdjacentHTML('beforeend', html);
            } else {
                state.conversations = batch;
                state.checkedIds = state.checkedIds.filter(id =>
                    state.conversations.some(c => Number(c.id) === Number(id))
                );
                renderConversations();
                const list = el('conversationList');
                if (list) list.scrollTop = 0;
            }
        } catch (err) {
            if (!append) {
                el('conversationList').innerHTML = `<div class="inbox-empty">${escapeHtml(err.message)}</div>`;
            }
        } finally {
            state.listLoading = false;
            updateListFooter();
            renderFilterChips();
        }
    }

    async function openConversation(id) {
        if (el('modalReply')?.style.display === 'grid') closeModal();
        state.selectedId = id;
        state.replyAttachments = [];
        state.commentAttachments = [];
        state.replyCcEmails = [];
        state.replyAll = false;
        state.replyDraftId = null;
        if (el('replyTo')) el('replyTo').value = '';
        if (el('replyCc')) el('replyCc').value = '';
        state.expandedMessageIds = {};
        state.composerExpanded = false;
        renderAttachChips('reply');
        renderAttachChips('comment');
        hideMentionPopup('reply');
        hideMentionPopup('comment');
        setComposerHtml('comment', '');
        setComposerHtml('reply', '');
        applyComposerSignature('reply');
        el('composerHint').textContent = 'Reply via Outlook';
        refreshTemplateSelects();
        // Update active highlight without rebuilding the whole list.
        el('conversationList')?.querySelectorAll('.inbox-conv').forEach(btn => {
            btn.classList.toggle('active', Number(btn.dataset.convId) === id);
        });
        const prev = state.conversations.find(c => Number(c.id) === Number(id));
        const wasUnread = !!(prev && !prev.is_read);
        const data = await api('/conversations/' + id);
        state.conversation = data.conversation;
        if (data.conversation?.id && Number(data.conversation.id) !== Number(id)) {
            state.selectedId = data.conversation.id;
            id = data.conversation.id;
            el('conversationList')?.querySelectorAll('.inbox-conv').forEach(btn => {
                btn.classList.toggle('active', Number(btn.dataset.convId) === Number(id));
            });
        }
        if (wasUnread && prev) {
            prev.is_read = true;
            el('conversationList')?.querySelector(`[data-conv-id="${id}"]`)?.classList.remove('unread');
            const isOpenInbox = (prev.folder || 'inbox') === 'inbox' && (prev.status || 'open') === 'open';
            if (isOpenInbox) {
                const inboxId = prev.inbox_id || data.conversation.inbox_id || data.conversation.inbox?.id;
                const inbox = state.inboxes.find(i => Number(i.id) === Number(inboxId));
                if (inbox && Number(inbox.unread_count) > 0) {
                    inbox.unread_count = Number(inbox.unread_count) - 1;
                }
                renderNav();
            }
        }
        renderThread();
        const draftMsg = [...(data.conversation?.messages || [])].reverse().find(m => m.is_draft);
        if (draftMsg && state.composerCanReply) {
            openDraftReplyModal(draftMsg);
        }
        window.updateHeaderNotificationsBadge?.();
    }

    function formatThreadTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';
        const secs = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
        const days = Math.floor(secs / 86400);
        const hours = Math.floor(secs / 3600);
        const mins = Math.floor(secs / 60);
        if (days >= 1) return days === 1 ? '1 day' : `${days} days`;
        if (hours >= 1) return hours === 1 ? '1 hour' : `${hours} hours`;
        if (mins >= 1) return mins === 1 ? '1 min' : `${mins} min`;
        return 'just now';
    }

    function avatarHue(seed) {
        const colors = ['#4f46e5', '#0ea5e9', '#059669', '#d97706', '#dc2626', '#7c3aed', '#db2777', '#2563eb'];
        let h = 0;
        for (const ch of String(seed || '?')) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
        return colors[h % colors.length];
    }

    function parseEmailList(value) {
        const parts = Array.isArray(value)
            ? value.map(v => String(v || '').trim()).filter(Boolean)
            : String(value || '').split(/[,;]+/).map(v => v.trim()).filter(Boolean);
        return parts.map(extractEmailAddress).filter(email => email.includes('@'));
    }

    function messagePreviewText(m) {
        const html = String(m?.body_html || '').trim();
        const source = html
            ? htmlToPlain(stripQuotedEmailHistoryHtml(html))
            : stripQuotedEmailHistoryPlain(String(m?.body_text || ''));
        return source.replace(/\s+/g, ' ').trim();
    }

    function collectParticipants(c) {
        const map = new Map();
        const add = (email, name) => {
            const key = String(email || '').trim().toLowerCase();
            if (!key || !key.includes('@')) return;
            if (!map.has(key)) map.set(key, { email: key, name: name || key.split('@')[0] });
            else if (name && map.get(key).name === map.get(key).email.split('@')[0]) map.get(key).name = name;
        };
        add(c.from_email, c.from_name);
        (c.messages || []).forEach(m => {
            add(m.from_email, m.from_name);
            parseEmailList(m.to || m.to_emails).forEach(email => add(email, ''));
            parseEmailList(m.cc || m.cc_emails).forEach(email => add(email, ''));
        });
        return [...map.values()];
    }

    function formatReadReceipt(member) {
        if (!member?.is_read || !member?.last_read_at) return 'Unread';
        const d = new Date(member.last_read_at);
        if (Number.isNaN(d.getTime())) return 'Read';
        const secs = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
        const days = Math.floor(secs / 86400);
        const hours = Math.floor(secs / 3600);
        const mins = Math.floor(secs / 60);
        if (days >= 1) return days === 1 ? 'Read 1 day ago' : `Read ${days} days ago`;
        if (hours >= 1) return hours === 1 ? 'Read 1 hour ago' : `Read ${hours} hours ago`;
        if (mins >= 1) return mins === 1 ? 'Read 1 min ago' : `Read ${mins} mins ago`;
        return 'Read just now';
    }

    function participantsMenuHtml(c) {
        const members = Array.isArray(c?.member_reads) ? c.member_reads : [];
        if (!members.length) return '';
        const inbox = c.inbox || state.inboxes.find(i => Number(i.id) === Number(c.inbox_id));
        const inboxName = inbox?.name || 'this inbox';
        const readCount = members.filter(m => m.is_read).length;
        const preview = members.slice(0, 3).map(m => `
            <span class="inbox-chip-avatar" style="background:${avatarHue(m.email || m.name)}">${escapeHtml(initials(m.name))}</span>
        `).join('');
        const rows = members.map(m => {
            const status = formatReadReceipt(m);
            const statusClass = m.is_read ? 'is-read' : 'is-unread';
            return `
                <div class="inbox-participant-row" title="${escapeHtml(m.email || '')}">
                    <span class="inbox-participant-avatar" style="background:${avatarHue(m.email || m.name)}">${escapeHtml(initials(m.name))}</span>
                    <span class="inbox-participant-name">${escapeHtml(m.name || m.email || 'Member')}</span>
                    <span class="inbox-participant-status ${statusClass}">${escapeHtml(status)}</span>
                </div>`;
        }).join('');
        return `
            <div class="inbox-pop inbox-participants-pop" id="participantsPop">
                <button type="button" class="inbox-chip inbox-participants-chip" id="btnParticipants" title="Who has read" aria-haspopup="menu" aria-expanded="false">
                    ${preview}
                    <span>${readCount}/${members.length} read</span>
                </button>
                <div class="inbox-pop-menu inbox-participants-menu" id="participantsMenu" hidden>
                    <div class="inbox-participants-head">Participants</div>
                    <div class="inbox-participants-list">${rows}</div>
                    <div class="inbox-participants-foot">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span>Members of <strong>${escapeHtml(inboxName)}</strong> can view</span>
                    </div>
                </div>
            </div>`;
    }

    function closeThreadPops() {
        ['threadMoreMenu', 'snoozeMenu', 'assignMenu', 'commentEmojiMenu', 'sendReplyMenu', 'composeSendMenu', 'participantsMenu'].forEach(id => {
            const node = el(id);
            if (node) node.hidden = true;
        });
        document.querySelectorAll('.inbox-icon-action.is-open, .inbox-assign-btn.is-open, .inbox-send-caret.is-open, .inbox-participants-chip.is-open').forEach(btn => {
            btn.classList.remove('is-open');
            if (btn.id === 'btnParticipants') btn.setAttribute('aria-expanded', 'false');
        });
        const laterFields = el('sendLaterFields');
        if (laterFields) laterFields.hidden = true;
        const composeLater = el('composeSendLaterFields');
        if (composeLater) composeLater.hidden = true;
    }

    function togglePop(menuId, btn) {
        const menu = el(menuId);
        if (!menu) return;
        const willOpen = menu.hidden;
        closeThreadPops();
        menu.hidden = !willOpen;
        btn?.classList.toggle('is-open', willOpen);
        if (btn?.id === 'btnParticipants') {
            btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        }
    }

    function renderAssignMenu() {
        const menu = el('assignMenu');
        if (!menu) return;
        const current = conversationAssigneeId() || '';
        menu.innerHTML = '<button type="button" data-assign="">Unassigned</button>' +
            state.members.map(m =>
                `<button type="button" data-assign="${m.id}" class="${Number(m.id) === Number(current) ? 'is-active' : ''}">${escapeHtml(m.name)}</button>`
            ).join('');
    }

    function snoozeUntilDate(preset) {
        const now = new Date();
        if (preset === 'later_today') {
            const d = new Date(now);
            d.setHours(18, 0, 0, 0);
            if (d <= now) d.setHours(now.getHours() + 3, 0, 0, 0);
            return d;
        }
        if (preset === 'tomorrow') {
            const d = new Date(now);
            d.setDate(d.getDate() + 1);
            d.setHours(9, 0, 0, 0);
            return d;
        }
        if (preset === 'monday') {
            const d = new Date(now);
            const add = d.getDay() === 0 ? 1 : (8 - d.getDay());
            d.setDate(d.getDate() + add);
            d.setHours(9, 0, 0, 0);
            return d;
        }
        if (preset === '3d') {
            const d = new Date(now);
            d.setDate(d.getDate() + 3);
            d.setHours(9, 0, 0, 0);
            return d;
        }
        return null;
    }

    async function assignConversation(userId) {
        if (!state.selectedId) return;
        await api('/conversations/' + state.selectedId + '/assign', {
            method: 'POST',
            body: { assigned_to: userId ? Number(userId) : null },
        });
        await openConversation(state.selectedId);
        await loadConversations();
    }

    async function snoozeConversation(until) {
        if (!state.selectedId || !until) return;
        const untilValue = until instanceof Date
            ? datetimeLocalToApi(toDatetimeLocalValue(until))
            : (typeof until === 'string' ? (datetimeLocalToApi(until) || until) : null);
        if (!untilValue) return;
        await api('/conversations/' + state.selectedId + '/snooze', {
            method: 'POST',
            body: { until: untilValue },
        });
        state.conversation = null;
        state.selectedId = null;
        renderThread();
        await loadBootstrap();
        await loadConversations();
    }

    function conversationInbox() {
        const c = state.conversation;
        if (!c) return null;
        return c.inbox || state.inboxes.find(i => Number(i.id) === Number(c.inbox_id)) || null;
    }

    function mailboxEmail(inbox) {
        return String(inbox?.email || inbox?.account_email || '').trim().toLowerCase();
    }

    function extractEmailAddress(value) {
        const raw = String(value || '').trim();
        const angle = raw.match(/<([^>]+)>/);
        return (angle ? angle[1] : raw).trim().toLowerCase();
    }

    function addEmailToField(fieldId, email) {
        const input = el(fieldId);
        if (!input || !email) return;
        const next = extractEmailAddress(email);
        if (!next.includes('@')) return;
        const parts = parseEmailList(input.value);
        if (parts.some(existing => existing === next)) return;
        parts.push(next);
        input.value = parts.join(', ');
    }

    function fillReplyFromSelect() {
        const select = el('replyFrom');
        if (!select) return;
        const previous = select.value;
        const connected = state.inboxes.filter(i => i.connected);
        const currentId = Number(state.conversation?.inbox_id || state.conversation?.inbox?.id || 0);
        const options = connected.slice();
        if (currentId && !options.some(i => Number(i.id) === currentId)) {
            const current = conversationInbox();
            if (current) options.unshift(current);
        }
        select.innerHTML = options.map(i => {
            const address = i.email || i.account_email || '';
            const label = address ? `${i.name || address} (${address})` : (i.name || 'Inbox');
            return `<option value="${escapeHtml(String(i.id))}">${escapeHtml(label)}</option>`;
        }).join('');
        if (previous && [...select.options].some(o => o.value === previous)) {
            select.value = previous;
        } else if (currentId && [...select.options].some(o => o.value === String(currentId))) {
            select.value = String(currentId);
        } else if (options[0]) {
            select.value = String(options[0].id);
        }
    }

    function replySourceMessage(preferred) {
        if (preferred) return preferred;
        const messages = state.conversation?.messages || [];
        const inbound = [...messages].slice().reverse().find(m => m.direction === 'inbound');
        return inbound || messages[messages.length - 1] || null;
    }

    function defaultReplyRecipients(message, replyAll) {
        const mine = mailboxEmail(conversationInbox());
        const source = replySourceMessage(message);
        const fromList = parseEmailList(source?.from_email || state.conversation?.from_email);
        const replyToList = parseEmailList(source?.reply_to || source?.reply_to_emails);
        const toList = parseEmailList(source?.to || source?.to_emails);
        const ccList = parseEmailList(source?.cc || source?.cc_emails);
        const notMe = email => email && email !== mine;
        const isFromMe = fromList.some(email => email === mine);
        const replyTarget = (replyToList.filter(notMe).length ? replyToList : fromList).filter(notMe);

        if (replyAll) {
            const unique = [...new Set([...replyTarget, ...toList, ...ccList].filter(notMe))];
            return { to: unique.slice(0, 1), cc: unique.slice(1) };
        }

        let to = isFromMe
            ? toList.filter(notMe)
            : replyTarget;
        if (!to.length) {
            to = parseEmailList(state.conversation?.from_email).filter(notMe);
        }
        to = [...new Set(to)];
        const toSet = new Set(to);
        const cc = [...new Set(ccList.filter(notMe).filter(email => !toSet.has(email)))];
        return { to, cc };
    }

    function populateReplyHeaders(message = null, { replyAll = false, force = false } = {}) {
        fillReplyFromSelect();
        const toEl = el('replyTo');
        const ccEl = el('replyCc');
        if (!force && toEl?.value.trim()) return;
        const { to, cc } = defaultReplyRecipients(message, replyAll);
        if (toEl) toEl.value = to.join(', ');
        if (ccEl) ccEl.value = cc.join(', ');
    }

    function startReplyFromMessage(message, replyAll) {
        openReplyModal(message, { replyAll: !!replyAll, force: true });
    }

    function clipIconHtml() {
        return '<span class="inbox-msg-clip" title="Has attachments"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg></span>';
    }

    function editIconHtml() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';
    }

    function emailCardHtml(m, expanded) {
        const name = m.from_name || m.from_email || 'Unknown';
        const email = m.from_email || '';
        const preview = messagePreviewText(m);
        const isDraft = !!m.is_draft;
        const toList = parseEmailList(m.to || m.to_emails);
        const ccList = parseEmailList(m.cc || m.cc_emails);
        const replyToList = parseEmailList(m.reply_to || m.reply_to_emails);
        const attachments = (m.attachments || []).map(a => `
            <a class="inbox-msg-attach" href="${escapeHtml(a.download_url)}" target="_blank" rel="noopener">
                ${escapeHtml(a.name || 'Attachment')}
            </a>
        `).join('');
        const recipients = [
            toList.length ? `<div><strong>To</strong> ${escapeHtml(toList.join(', '))}</div>` : '',
            ccList.length ? `<div><strong>Cc</strong> ${escapeHtml(ccList.join(', '))}</div>` : '',
            replyToList.length ? `<div><strong>Reply-To</strong> ${escapeHtml(replyToList.join(', '))}</div>` : '',
        ].join('');
        return `
            <div class="inbox-msg ${m.direction} ${expanded ? 'is-expanded' : ''} ${isDraft ? 'scheduled' : ''}" data-msg-id="${escapeHtml(String(m.id))}">
                <div class="inbox-msg-row">
                    <span class="inbox-avatar" style="background:${avatarHue(email || name)}">${escapeHtml(initials(name))}</span>
                    <div class="inbox-msg-summary">
                        <span class="inbox-msg-from">${escapeHtml(name)}</span>
                        ${isDraft ? '<span class="inbox-msg-email">Draft</span>' : (email ? `<span class="inbox-msg-email">${escapeHtml(email)}</span>` : '')}
                        <span class="inbox-msg-preview">${escapeHtml(preview)}</span>
                    </div>
                    <div class="inbox-msg-meta">
        ${m.direction === 'inbound' && m.is_read ? '<span class="inbox-seen">Seen</span>' : ''}
                        ${(m.attachments || []).length ? clipIconHtml() : ''}
                        <span class="inbox-msg-time" title="${escapeHtml(m.sent_at ? formatAbsoluteTime(m.sent_at) : '')}">${escapeHtml(formatThreadTime(m.sent_at))}</span>
                        <div class="inbox-msg-head-actions">
                            ${isDraft ? `
                                <button type="button" data-edit-draft="${escapeHtml(String(m.id))}" title="Continue editing draft">
                                    ${editIconHtml()}
                                </button>
                            ` : `
                                <button type="button" data-reply-msg="${escapeHtml(String(m.id))}" title="Reply all">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
                                </button>
                            `}
                        </div>
                    </div>
                </div>
                <div class="inbox-msg-expanded">
                    ${recipients ? `<div class="inbox-msg-recipients">${recipients}</div>` : ''}
                    <div class="inbox-msg-body" data-email-body="${escapeHtml(String(m.id))}"></div>
                    ${attachments ? `<div class="inbox-msg-attachments">${attachments}</div>` : ''}
                    ${isDraft ? `
                        <div class="inbox-scheduled-actions">
                            <span class="inbox-composer-hint">This draft hasn't been sent yet.</span>
                            <button type="button" class="inbox-btn primary" data-edit-draft="${escapeHtml(String(m.id))}">Continue editing</button>
                        </div>
                    ` : ''}
                </div>
            </div>`;
    }

    function commentCardHtml(comment, expanded) {
        const name = comment.user?.name || 'Teammate';
        const preview = String(comment.body_text || htmlToPlain(comment.body_html || '') || '').replace(/\s+/g, ' ').trim();
        const attachments = (comment.attachments || []).map(a => `
            <a class="inbox-msg-attach" href="${escapeHtml(a.download_url)}" target="_blank" rel="noopener">
                ${escapeHtml(a.name || 'Attachment')}
            </a>
        `).join('');
        return `
            <div class="inbox-msg internal ${expanded ? 'is-expanded' : ''}" data-comment-id="${escapeHtml(String(comment.id))}">
                <div class="inbox-msg-row">
                    <span class="inbox-avatar" style="background:#d97706">${escapeHtml(initials(name))}</span>
                    <div class="inbox-msg-summary">
                        <span class="inbox-msg-from">${escapeHtml(name)}</span>
                        <span class="inbox-msg-email">internal comment</span>
                        <span class="inbox-msg-preview">${escapeHtml(preview)}</span>
                    </div>
                    <div class="inbox-msg-meta">
                        ${(comment.attachments || []).length ? clipIconHtml() : ''}
                        <span class="inbox-msg-time" title="${escapeHtml(comment.created_at ? formatAbsoluteTime(comment.created_at) : '')}">${escapeHtml(formatThreadTime(comment.created_at))}</span>
                    </div>
                </div>
                <div class="inbox-msg-expanded">
                    <div class="inbox-msg-body">${formatMessageBodyHtml(comment)}</div>
                    ${attachments ? `<div class="inbox-msg-attachments">${attachments}</div>` : ''}
                </div>
            </div>`;
    }

    function scheduledReplyCardHtml(item) {
        const name = item.user?.name || 'You';
        const preview = String(item.body_text || htmlToPlain(item.body_html || '') || '').replace(/\s+/g, ' ').trim();
        const when = item.send_at ? formatAbsoluteTime(item.send_at) : 'later';
        const isCompose = item.type === 'compose';
        const kindLabel = isCompose ? 'scheduled message' : 'scheduled reply';
        const attachNote = Number(item.attachment_count || 0) > 0
            ? ` · ${item.attachment_count} attachment${Number(item.attachment_count) === 1 ? '' : 's'}`
            : '';
        const archiveNote = item.archive_after ? ' · then archive' : '';
        return `
            <div class="inbox-msg scheduled is-expanded" data-scheduled-reply-id="${escapeHtml(String(item.id))}">
                <div class="inbox-msg-row" style="cursor:default;">
                    <span class="inbox-avatar" style="background:#64748b">${escapeHtml(initials(name))}</span>
                    <div class="inbox-msg-summary">
                        <span class="inbox-msg-from">${escapeHtml(name)}</span>
                        <span class="inbox-msg-email">${escapeHtml(kindLabel)}</span>
                        <span class="inbox-msg-preview">${escapeHtml(preview)}</span>
                    </div>
                    <div class="inbox-msg-meta">
                        <span class="inbox-msg-time" title="${escapeHtml(when)}">${escapeHtml(when)}</span>
                    </div>
                </div>
                <div class="inbox-msg-expanded">
                    <div class="inbox-msg-body">${formatMessageBodyHtml({ body_html: item.body_html, body_text: item.body_text })}</div>
                    <div class="inbox-scheduled-actions">
                        <span class="inbox-composer-hint">Scheduled for ${escapeHtml(when)}${escapeHtml(attachNote)}${escapeHtml(archiveNote)}</span>
                        <button type="button" class="inbox-btn ghost" data-cancel-scheduled="${escapeHtml(String(item.id))}">Cancel</button>
                    </div>
                </div>
            </div>`;
    }

    function applyPropsPaneVisibility() {
        const pane = el('propsPane');
        const shell = document.querySelector('.inbox-shell');
        const toggle = el('btnToggleProps');
        const open = !!(state.conversation && state.propsOpen);
        if (pane) {
            pane.style.display = open ? 'block' : 'none';
            pane.hidden = !open;
        }
        shell?.classList.toggle('with-props', open);
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.classList.toggle('is-active', open);
            toggle.title = open ? 'Hide details' : 'Show details';
        }
    }

    function setPropsOpen(open) {
        state.propsOpen = !!open;
        applyPropsPaneVisibility();
    }

    function renderThread() {
        const c = state.conversation;
        if (!c) {
            el('threadPlaceholder').style.display = 'flex';
            el('threadView').style.display = 'none';
            applyPropsPaneVisibility();
            return;
        }
        el('threadPlaceholder').style.display = 'none';
        el('threadView').style.display = 'flex';
        applyPropsPaneVisibility();
        el('threadSubject').textContent = c.subject || '(No subject)';

        const snoozedUntil = c.reopen_at && new Date(c.reopen_at) > new Date() ? c.reopen_at : null;
        const mergedCount = Number(c.merged_count || (c.merged_threads || []).length || 0);
        const metaBits = [];
        if (snoozedUntil) metaBits.push('Snoozed until ' + formatAbsoluteTime(snoozedUntil));
        if (mergedCount) metaBits.push(mergedCount === 1 ? '1 conversation merged in' : mergedCount + ' conversations merged in');
        el('threadMeta').textContent = metaBits.join(' · ');

        const people = collectParticipants(c);
        const inbox = c.inbox || state.inboxes.find(i => Number(i.id) === Number(c.inbox_id));
        const isShared = inbox?.type === 'shared';
        el('threadParticipants').innerHTML = people.slice(0, 6).map(p => `
            <span class="inbox-chip" title="${escapeHtml(p.email)}">
                <span class="inbox-chip-avatar" style="background:${avatarHue(p.email)}">${escapeHtml(initials(p.name))}</span>
                <span>${escapeHtml(p.name || p.email)}</span>
            </span>
        `).join('') + (people.length > 6 ? `<span class="inbox-chip">+${people.length - 6}</span>` : '') +
            (isShared ? participantsMenuHtml(c) : '') +
            '<button type="button" class="inbox-chip-add" id="btnAddParticipant" title="Assign teammate">+</button>';

        const folder = c.folder || 'inbox';
        const isInboxOpen = folder === 'inbox' && c.status === 'open';
        const isArchived = folder === 'inbox' && c.status === 'archived';
        const isTrashOrSpam = folder === 'trash' || folder === 'spam' || c.status === 'trashed' || c.status === 'spam';

        el('btnArchive').style.display = isInboxOpen ? '' : 'none';
        el('btnSnooze').style.display = isInboxOpen ? '' : 'none';
        el('btnSpam').style.display = (folder === 'inbox' || folder === 'trash') ? '' : 'none';
        el('btnTrash').style.display = (!isTrashOrSpam && folder !== 'trash') ? '' : 'none';
        el('btnRestore').style.display = isTrashOrSpam ? '' : 'none';
        el('btnReopen').style.display = isArchived ? '' : 'none';
        const unmergeBtn = el('btnUnmergeMenu');
        if (unmergeBtn) unmergeBtn.hidden = mergedCount < 1;
        el('assignBtnLabel').textContent = c.assignee?.name || 'Assign';
        el('archiveBtnLabel').textContent = inbox?.type === 'personal' ? 'Archive in my inbox' : 'Archive';
        renderAssignMenu();

        const canReply = folder === 'inbox' || folder === 'sent' || folder === 'drafts';
        state.composerCanReply = canReply;
        el('composerArea').style.display = '';
        el('composerArea').classList.toggle('is-expanded', !!state.composerExpanded);
        const replyModeBtn = el('btnModeReply');
        if (replyModeBtn) {
            replyModeBtn.disabled = !canReply;
            replyModeBtn.title = canReply ? 'Email reply via Outlook' : 'Reply unavailable in this folder';
        }
        setComposerMode();
        if (!state.replyAll) {
            el('composerHint').textContent = folder === 'drafts' ? 'Send draft via Outlook' : 'Reply via Outlook';
        }
        el('replyBody').dataset.placeholder = folder === 'drafts' ? 'Edit and send…' : 'Write a reply… Type @ to mention teammates.';
        const me = state.members.find(m => Number(m.id) === USER_ID);
        const assignee = conversationAssignee(c);
        el('commentBody').dataset.placeholder = assignee?.name
            ? `Add internal comment visible to ${me?.name ? 'you' : 'your team'} and ${assignee.name}.`
            : 'Add internal comment visible to your team.';

        el('assignSelect').value = conversationAssigneeId(c) || '';
        el('propInboxName').textContent = c.inbox?.name || '—';
        el('propContact').textContent = `${c.from_name || ''} · ${c.from_email || ''}`;
        const propLead = el('propContactLead');
        if (propLead) {
            propLead.innerHTML = leadAssignedBlock(c.lead);
        }

        const tagItems = conversationTagItems(c);
        const hasLead = !!conversationLead(c);
        const canManageLeadLabels = hasLead;
        el('conversationTags').innerHTML = tagItems.length
            ? tagItems.map(t => conversationLabelPillHtml(t, { removable: true })).join('')
            : (canManageLeadLabels
                ? `<span style="color:var(--inbox-muted);font-size:0.8rem;">No labels</span>`
                : `<span style="color:var(--inbox-muted);font-size:0.8rem;">Save as a lead to add labels, or run Front import to tag this thread</span>`);

        const used = new Set(tagItems.map(t => Number(t.id)));
        const addSelect = el('addTagSelect');
        if (addSelect) {
            addSelect.style.display = canManageLeadLabels ? '' : 'none';
            addSelect.innerHTML = '<option value="">Add existing label…</option>' +
                (state.leadLabels || []).filter(t => !used.has(Number(t.id)))
                    .map(t => `<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('');
        }
        const leadLabelRow = el('addLeadLabelRow');
        if (leadLabelRow) leadLabelRow.hidden = !canManageLeadLabels;
        const leadLabelInput = el('addLeadLabelInput');
        if (leadLabelInput) leadLabelInput.value = '';

        const emails = [...(c.messages || [])].sort((a, b) => String(a.sent_at || '').localeCompare(String(b.sent_at || '')));
        const lastEmailId = emails.length ? String(emails[emails.length - 1].id) : null;
        const isEmailExpanded = (id) => {
            const key = String(id);
            if (Object.prototype.hasOwnProperty.call(state.expandedMessageIds, key)) return !!state.expandedMessageIds[key];
            return key === String(lastEmailId);
        };

        const timeline = [
            ...(c.messages || []).map(m => ({
                type: 'email',
                message: m,
                sort: m.sent_at || '',
                html: emailCardHtml(m, isEmailExpanded(m.id)),
            })),
            ...(c.comments || []).map(comment => ({
                type: 'comment',
                sort: comment.created_at || '',
                html: commentCardHtml(comment, true),
            })),
            ...(c.scheduled_replies || []).map(item => ({
                type: 'scheduled',
                sort: item.send_at || item.created_at || '',
                html: scheduledReplyCardHtml(item),
            })),
            ...(c.activities || []).map(activity => ({
                type: 'activity',
                sort: activity.created_at || '',
                html: `
            <div class="inbox-msg activity" data-activity-action="${escapeHtml(activity.action || '')}">
                <div class="inbox-activity-line">
                    <span>${escapeHtml(activity.summary || 'Updated conversation')}</span>
                    <span class="inbox-activity-time">${escapeHtml(formatThreadTime(activity.created_at))}</span>
                </div>
            </div>`,
            })),
        ].sort((a, b) => String(a.sort).localeCompare(String(b.sort)));

        el('threadMessages').innerHTML = timeline.map(item => item.html).join('')
            || '<div class="inbox-empty">No messages</div>';

        timeline.forEach(item => {
            if (item.type !== 'email' || !item.message) return;
            const id = String(item.message.id);
            const host = el('threadMessages').querySelector('[data-email-body="' + id.replace(/"/g, '') + '"]');
            mountEmailBody(host, item.message);
        });

        el('threadMessages').scrollTop = el('threadMessages').scrollHeight;

        const history = [...(c.activities || [])].sort((a, b) => String(b.created_at || '').localeCompare(String(a.created_at || '')));
        el('conversationHistory').innerHTML = history.length
            ? history.map(activity => `
                <div class="inbox-history-item">
                    <div>${escapeHtml(activity.summary || 'Updated conversation')}</div>
                    <time>${activity.created_at ? new Date(activity.created_at).toLocaleString() : ''}</time>
                </div>
            `).join('')
            : '<div style="color:var(--inbox-muted);font-size:0.8rem;">No history yet</div>';

        const snippet = conversationSnippetText(c);
        c.snippet = snippet;
        const listRow = state.conversations.find(row => Number(row.id) === Number(c.id));
        if (listRow) listRow.snippet = snippet;
        const snippetEl = el('conversationList')?.querySelector(`[data-conv-id="${c.id}"] .inbox-conv-snippet`);
        if (snippetEl) snippetEl.textContent = snippet;

        loadInboxContactHistory(c);
    }

    function extractContactEmail(value) {
        const raw = String(value || '').trim();
        const angle = raw.match(/<([^>]+@[^>]+)>/);
        if (angle) return angle[1].trim().toLowerCase();
        return raw.includes('@') ? raw.toLowerCase() : '';
    }

    function renderInboxContactHistoryFallback(root, data, opts = {}) {
        const excludeChannel = opts.excludeChannel || null;
        const excludeId = opts.excludeId != null ? Number(opts.excludeId) : null;
        const contact = data.contact || {};
        const threads = (data.threads || []).filter((t) => {
            if (excludeChannel && t.channel === excludeChannel && Number(t.conversation_id) === excludeId) {
                return false;
            }
            return true;
        });
        const events = (data.events || []).slice(0, 25);
        const contactHtml = `
            <div class="chp-name">${escapeHtml(contact.display_name || 'Contact')}</div>
            ${(contact.matched_phones || []).slice(0, 2).map((p) => `<div class="chp-meta">${escapeHtml(p)}</div>`).join('')}
            ${(contact.matched_emails || []).slice(0, 2).map((em) => `<div class="chp-meta">${escapeHtml(em)}</div>`).join('')}
            ${contact.lead?.assigned_user?.name ? `<div class="chp-assigned">Assigned to ${escapeHtml(contact.lead.assigned_user.name)}${contact.lead.status ? ' · ' + escapeHtml(contact.lead.status) : ''}</div>` : (contact.lead ? `<div class="chp-meta">Lead${contact.lead.status ? ' · ' + escapeHtml(contact.lead.status) : ''} · Unassigned</div>` : '')}
            ${leadLabelChipsHtml(contact.lead?.labels)}
            ${contact.lead?.crm_url ? `<a class="chp-link" href="${escapeHtml(contact.lead.crm_url)}" target="_blank" rel="noopener">Open lead →</a>` : ''}
            ${contact.client?.crm_url ? `<a class="chp-link" href="${escapeHtml(contact.client.crm_url)}" target="_blank" rel="noopener">Open client →</a>` : ''}
            ${!contact.lead && opts.canSaveLead !== false ? `<button type="button" class="chp-save-lead" data-chp-save-lead>Save as lead</button>` : ''}
        `;
        const threadsHtml = threads.length
            ? threads.map((t) => `
                <a class="chp-item" href="${escapeHtml(t.deep_link || '#')}">
                    <span class="chp-badge ${escapeHtml(t.channel || '')}">${escapeHtml(t.label || t.channel)}</span>
                    <div class="chp-item-title">${escapeHtml(t.title || '')}</div>
                    <div class="chp-item-preview">${escapeHtml(t.preview || '')}</div>
                </a>`).join('')
            : '<p class="chp-empty">No other channel threads found.</p>';
        const eventsHtml = events.length
            ? events.map((ev) => `
                <div class="chp-event">
                    <span class="chp-badge ${escapeHtml(ev.channel || '')}">${escapeHtml(ev.label || ev.channel)}</span>
                    <span class="chp-dir">${escapeHtml(ev.direction || '')} · ${escapeHtml(ev.at ? new Date(ev.at).toLocaleString() : '')}</span>
                    <div class="chp-item-preview">${escapeHtml(ev.preview || '')}</div>
                </div>`).join('')
            : '<p class="chp-empty">No timeline events.</p>';
        root.innerHTML = `
            <div class="chp-section">${contactHtml}</div>
            <div class="chp-section">
                <div class="chp-label">Other channels</div>
                ${threadsHtml}
            </div>
            <div class="chp-section">
                <div class="chp-label">Timeline</div>
                ${eventsHtml}
            </div>
        `;
        const saveBtn = root.querySelector('[data-chp-save-lead]');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => saveInboxAsLead(root, opts, contact));
        }
    }

    function updateContactLeadAction(data, opts = {}) {
        const wrap = el('propContactLead');
        if (!wrap) return;
        const canSave = el('inboxContactHistory')?.dataset.canSaveLead !== '0';
        const contact = data?.contact || {};
        const conversation = state.conversation;
        const attached = !!(conversation?.lead_id);
        const isSharedMailbox = (conversation?.inbox?.type || '') === 'shared';
        const lead = conversationLead() || contact.lead;
        const emails = [...(contact.matched_emails || []), opts.email].filter(Boolean);
        const phones = [...(contact.matched_phones || []), opts.phone].filter(Boolean);
        const parts = [];

        if (lead?.crm_url) {
            parts.push(leadAssignedBlock(lead));
            if (attached) {
                parts.push('<button type="button" class="inbox-btn ghost" id="btnDetachLead">Detach email</button>');
            }
        } else if (canSave && (emails.length || phones.length)) {
            parts.push('<button type="button" class="inbox-btn ghost" id="btnSaveAsLead">Save as lead</button>');
        }

        if (canSave && isSharedMailbox) {
            parts.push(`
                <div class="inbox-attach-lead">
                    <input type="search" id="inboxAttachLeadSearch" class="form-input" placeholder="Attach to existing lead" autocomplete="off">
                    <div id="inboxAttachLeadResults" class="inbox-merge-results" hidden></div>
                </div>
            `);
        }

        wrap.innerHTML = parts.join('');
        el('btnSaveAsLead')?.addEventListener('click', () => {
            const body = el('inboxContactHistoryBody') || el('inboxContactHistory');
            saveInboxAsLead(body, opts, contact, el('btnSaveAsLead'));
        });
        el('btnDetachLead')?.addEventListener('click', () => detachInboxLead());
        bindInboxAttachLeadSearch();
    }

    let inboxAttachLeadTimer = null;
    function bindInboxAttachLeadSearch() {
        const input = el('inboxAttachLeadSearch');
        const results = el('inboxAttachLeadResults');
        if (!input || !results) return;
        input.addEventListener('input', () => {
            const q = input.value.trim();
            clearTimeout(inboxAttachLeadTimer);
            if (q.length < 2) {
                results.hidden = true;
                results.innerHTML = '';
                return;
            }
            inboxAttachLeadTimer = setTimeout(() => searchLeadsToAttach(q, results), 250);
        });
    }

    async function searchLeadsToAttach(q, results) {
        results.hidden = false;
        results.innerHTML = '<div class="inbox-merge-row-meta">Searching…</div>';
        try {
            const res = await fetch('/api/leads?' + new URLSearchParams({ search: q, per_page: '8' }).toString(), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || 'Could not search leads.');
            const leads = data.data || [];
            if (!leads.length) {
                results.innerHTML = '<div class="inbox-merge-row-meta">No matching leads.</div>';
                return;
            }
            results.innerHTML = leads.map((lead) => `
                <button type="button" class="inbox-merge-row" data-attach-lead="${lead.id}">
                    <span class="inbox-merge-row-from">${escapeHtml(lead.name || 'Lead')}</span>
                    <span class="inbox-merge-row-meta">${escapeHtml([lead.email, lead.status].filter(Boolean).join(' · '))}</span>
                </button>
            `).join('');
            results.querySelectorAll('[data-attach-lead]').forEach((btn) => {
                btn.addEventListener('click', () => attachInboxLead(Number(btn.dataset.attachLead)));
            });
        } catch (err) {
            results.innerHTML = `<div class="inbox-merge-row-meta">${escapeHtml(err.message || 'Could not search leads.')}</div>`;
        }
    }

    async function attachInboxLead(leadId) {
        if (!state.selectedId || !leadId) return;
        try {
            const data = await api('/conversations/' + state.selectedId + '/lead', {
                method: 'POST',
                body: { lead_id: leadId },
            });
            if (data.conversation) {
                state.conversation = data.conversation;
                const idx = state.conversations.findIndex((c) => Number(c.id) === Number(data.conversation.id));
                if (idx >= 0) state.conversations[idx] = { ...state.conversations[idx], ...data.conversation };
            }
            await openConversation(state.selectedId);
        } catch (err) {
            alert(err.message || 'Could not attach this email.');
        }
    }

    async function detachInboxLead() {
        if (!state.selectedId) return;
        try {
            const data = await api('/conversations/' + state.selectedId + '/lead', { method: 'DELETE' });
            if (data.conversation) {
                state.conversation = data.conversation;
            }
            await openConversation(state.selectedId);
        } catch (err) {
            alert(err.message || 'Could not detach this email.');
        }
    }

    async function saveInboxAsLead(bodyEl, opts, contact, button) {
        const extraBtn = button || el('btnSaveAsLead');
        if (extraBtn) {
            extraBtn.disabled = true;
            extraBtn.textContent = 'Saving…';
        }
        if (window.LnsContactHistory?.saveAsLead) {
            await window.LnsContactHistory.saveAsLead(bodyEl, opts, contact);
            if (extraBtn?.isConnected) {
                extraBtn.disabled = false;
                extraBtn.textContent = 'Save as lead';
            }
            return;
        }
        const name = String(contact.display_name || opts.name || opts.email || 'New lead').trim();
        const phones = (contact.matched_phones || []).filter(Boolean);
        if (opts.phone && !phones.length) phones.push(opts.phone);
        const emails = (contact.matched_emails || []).filter(Boolean);
        if (opts.email && !emails.length) emails.push(opts.email);
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const btn = button || bodyEl?.querySelector('[data-chp-save-lead]');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Saving…';
        }
        try {
            const res = await fetch('/api/leads', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                },
                body: JSON.stringify({
                    name,
                    phones,
                    emails,
                    source: 'inbox',
                    inbox_conversation_ids: state.selectedId ? [state.selectedId] : [],
                }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok && !data.existing_lead_id) {
                throw new Error(data.message || 'Could not save lead.');
            }
            if (typeof opts.onSaved === 'function') {
                opts.onSaved(data, { existing: !res.ok });
                return;
            }
        } catch (err) {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Save as lead';
            }
            alert(err.message || 'Could not save lead.');
        }
    }

    async function loadInboxContactHistory(c) {
        const root = el('inboxContactHistory');
        const body = el('inboxContactHistoryBody') || root;
        if (!body || !c) return;

        const extractedName = String(c.extracted_name || (c.extracted_names || [])[0] || '').trim();
        const extractedPhones = c.extracted_phones || [];
        const extractedEmails = c.extracted_emails || [];
        const email = extractedEmails[0] || extractContactEmail(c.from_email);
        const name = extractedName || String(c.from_name || '').trim();
        const phone = extractedPhones[0] || String(c.phone || c.from_phone || '').trim();
        const canSaveLead = root?.dataset.canSaveLead !== '0';
        const opts = {
            email,
            name,
            phone,
            excludeChannel: 'inbox',
            excludeId: c.id,
            limit: 60,
            source: 'inbox',
            canSaveLead,
            extracted_name: extractedName,
            extracted_names: c.extracted_names || [],
            extracted_phones: extractedPhones,
            extracted_emails: extractedEmails,
            onSaved: async () => {
                if (state.selectedId) {
                    await openConversation(state.selectedId);
                    await loadConversations();
                    await loadBootstrap();
                }
            },
        };

        if (!email && !name && !phone) {
            body.innerHTML = '<p class="chp-empty">No email or name on this conversation to look up history.</p>';
            updateContactLeadAction(null, opts);
            return;
        }

        body.innerHTML = '<p class="chp-empty">Loading contact history…</p>';

        if (window.LnsContactHistory?.load) {
            try {
                const data = await window.LnsContactHistory.load(root, opts);
                updateContactLeadAction(data, opts);
                return;
            } catch (e) {
                console.warn('LnsContactHistory.load failed, using inbox fallback', e);
            }
        }

        const q = new URLSearchParams();
        if (email) q.set('email', email);
        if (name) q.set('name', name);
        if (phone) q.set('phone', phone);
        q.set('limit', '60');

        try {
            const res = await fetch('/api/crm/contact-history?' + q.toString(), {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.error || data.message || 'Failed to load history');
            if (window.LnsContactHistory?.renderPanel) {
                window.LnsContactHistory.renderPanel(body, data, opts);
            } else {
                renderInboxContactHistoryFallback(body, data, opts);
            }
            updateContactLeadAction(data, opts);
        } catch (err) {
            body.innerHTML = `<p class="chp-empty">${escapeHtml(err.message || 'Could not load contact history.')}</p>`;
            updateContactLeadAction(null, opts);
        }
    }

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));
    }

    async function loadBootstrap() {
        const data = await api('/bootstrap');
        state.inboxes = data.inboxes || [];
        state.templates = (data.templates || []).map(t => ({
            ...t,
            body: t.body || t.body_text || '',
            body_html: t.body_html || null,
            format: 'html',
        }));
        state.rules = data.rules || [];
        state.members = data.members || [];
        state.leadLabels = data.lead_labels || [];
        state.permissions = {
            create_templates: !!(data.permissions && data.permissions.create_templates),
            create_rules: !!(data.permissions && data.permissions.create_rules),
        };
        if (el('btnNewTemplate')) el('btnNewTemplate').style.display = state.permissions.create_templates ? '' : 'none';
        if (el('btnNewRule')) el('btnNewRule').style.display = state.permissions.create_rules ? '' : 'none';
        el('mailStatusLabel').textContent = data.mail_connected ? (data.mail_email || 'Connected') : (data.outlook_configured ? 'Not connected' : 'Configure OAuth in Integrations');
        el('btnConnectOutlook').style.display = data.mail_connected ? 'none' : '';
        el('btnDisconnectOutlook').style.display = data.mail_connected ? '' : 'none';
        el('btnConnectOutlook').disabled = !data.outlook_configured && !data.mail_connected;
        await migrateLocalTemplatesIfNeeded();
        renderNav();
        refreshTemplateSelects();
        await loadConversations();
    }

    async function migrateLocalTemplatesIfNeeded() {
        const local = Array.isArray(state.pendingLocalTemplates) ? state.pendingLocalTemplates : [];
        if (!local.length) return;
        if (!state.permissions.create_templates) return;
        try {
            const payload = local
                .map(t => ({
                    name: String(t.name || '').trim(),
                    subject: t.subject || null,
                    body_html: t.body_html || null,
                    body: t.body || t.body_text || null,
                    body_text: t.body_text || t.body || null,
                }))
                .filter(t => t.name && (t.body_html || t.body || t.body_text));
            if (!payload.length) {
                state.pendingLocalTemplates = [];
                saveLocalTools();
                return;
            }
            const result = await api('/templates/import', {
                method: 'POST',
                body: { templates: payload },
            });
            state.templates = (result.templates || []).map(t => ({
                ...t,
                body: t.body || t.body_text || '',
                body_html: t.body_html || null,
                format: 'html',
            }));
            state.pendingLocalTemplates = [];
            saveLocalTools();
            if (result.imported > 0) {
                el('mailStatusLabel').textContent = `Imported ${result.imported} template${result.imported === 1 ? '' : 's'} for your company`;
            }
        } catch (err) {
            console.warn('Failed to migrate local templates', err);
        }
    }

    // Events
    document.querySelectorAll('[data-view][data-scope="all"]').forEach(btn => {
        btn.addEventListener('click', async () => {
            state.view = btn.dataset.view;
            state.selectedInboxId = null;
            renderNav();
            await loadConversations();
        });
    });

    el('inboxList').addEventListener('click', async (e) => {
        const manage = e.target.closest('[data-manage-members]');
        if (manage) {
            e.stopPropagation();
            openMembersModal(Number(manage.dataset.manageMembers));
            return;
        }
        const connectBtn = e.target.closest('[data-connect-inbox]');
        if (connectBtn) {
            e.stopPropagation();
            const inbox = state.inboxes.find(i => i.id === Number(connectBtn.dataset.connectInbox));
            const url = inbox?.connect_url || (CONNECT + '?intent=shared&shared_inbox_id=' + connectBtn.dataset.connectInbox);
            window.location = url;
            return;
        }

        const syncBtn = e.target.closest('[data-sync-inbox]');
        if (syncBtn) {
            e.stopPropagation();
            runInboxSync(Number(syncBtn.dataset.syncInbox));
            return;
        }

        const folderBtn = e.target.closest('[data-folder-view]');
        if (folderBtn) {
            const id = Number(folderBtn.dataset.inboxId);
            state.selectedInboxId = id;
            state.view = folderBtn.dataset.folderView;
            state.expandedInboxIds[id] = true;
            renderNav();
            await loadConversations();
            return;
        }

        const toggle = e.target.closest('[data-inbox-toggle]');
        if (toggle) {
            const id = Number(toggle.dataset.inboxToggle);
            if (e.target.closest('.inbox-mailbox-chevron')) {
                state.expandedInboxIds[id] = !state.expandedInboxIds[id];
                renderNav();
                return;
            }
            state.expandedInboxIds[id] = true;
            state.selectedInboxId = id;
            state.view = 'open';
            renderNav();
            await loadConversations();
        }
    });

        el('ruleList')?.addEventListener('click', async (e) => {
        const del = e.target.closest('[data-delete-rule]');
        if (del) {
            if (!state.permissions.create_rules) return;
            await api('/rules/' + del.dataset.deleteRule, { method: 'DELETE' });
            await loadBootstrap();
            return;
        }
        const toggle = e.target.closest('[data-toggle-rule]');
        if (toggle) {
            if (!state.permissions.create_rules) return;
            const rule = state.rules.find(r => r.id === Number(toggle.dataset.toggleRule));
            if (!rule) return;
            await api('/rules/' + rule.id, { method: 'PATCH', body: { is_active: !rule.is_active } });
            await loadBootstrap();
        }
    });

    el('conversationList').addEventListener('click', (e) => {
        const time = e.target.closest('[data-conv-time]');
        if (time) {
            e.preventDefault();
            e.stopPropagation();
            time.classList.toggle('is-absolute');
            const iso = time.dataset.convTime;
            if (time.classList.contains('is-absolute')) {
                time.textContent = formatAbsoluteTime(iso);
                time.title = 'Click to show relative time';
            } else {
                time.textContent = formatRelativeTime(iso);
                time.title = 'Click to show date & time';
            }
            return;
        }
        const row = e.target.closest('[data-conv-id]');
        if (!row) return;
        const id = Number(row.dataset.convId);
        if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            toggleCheckedConversation(id);
            return;
        }
        clearCheckedConversations();
        openConversation(id);
    });
    el('conversationList').addEventListener('contextmenu', (e) => {
        if (e.ctrlKey) e.preventDefault();
    });
    el('btnMergeSelected')?.addEventListener('click', async () => {
        try {
            await mergeCheckedConversations();
        } catch (err) {
            alert(err.message);
        }
    });
    el('btnClearChecked')?.addEventListener('click', () => {
        clearCheckedConversations();
    });

    el('conversationList').addEventListener('scroll', () => {
        const list = el('conversationList');
        if (!list || state.listLoading || !state.listHasMore) return;
        const remaining = list.scrollHeight - list.scrollTop - list.clientHeight;
        if (remaining < 160) {
            loadConversations({ append: true });
        }
    });

    el('inboxSearch').addEventListener('input', () => {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(() => {
            renderFilterChips();
            loadConversations({ append: false });
        }, 300);
    });

    el('btnToggleAdvancedSearch').addEventListener('click', () => {
        setAdvancedOpen(!state.advancedOpen);
    });

    el('btnApplyAdvancedSearch').addEventListener('click', () => applyAdvancedFilters());
    el('btnClearAdvancedSearch').addEventListener('click', () => clearAdvancedFilters({ reload: true }));

    function bindEmailSuggest(inputId, listId, field) {
        const input = el(inputId);
        const list = el(listId);
        if (!input || !list) return;

        let timer = null;
        let items = [];
        let activeIndex = -1;
        let reqToken = 0;

        const hide = () => {
            list.hidden = true;
            list.innerHTML = '';
            items = [];
            activeIndex = -1;
            input.setAttribute('aria-expanded', 'false');
        };

        const render = () => {
            if (!items.length) {
                hide();
                return;
            }
            list.innerHTML = items.map((item, idx) => `
                <li role="option" id="${listId}-opt-${idx}">
                    <button type="button" class="inbox-suggest-item ${idx === activeIndex ? 'is-active' : ''}" data-suggest-idx="${idx}">
                        <span class="inbox-suggest-email">${escapeHtml(item.email)}</span>
                        ${item.name ? `<span class="inbox-suggest-name">${escapeHtml(item.name)}</span>` : ''}
                    </button>
                </li>
            `).join('');
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        };

        const selectIndex = (idx) => {
            const item = items[idx];
            if (!item) return false;
            input.value = item.email;
            hide();
            input.focus();
            return true;
        };

        const fetchSuggestions = async (q) => {
            const token = ++reqToken;
            try {
                const params = new URLSearchParams({ q, field });
                const data = await api('/email-suggestions?' + params.toString());
                if (token !== reqToken) return;
                items = data.suggestions || [];
                activeIndex = items.length ? 0 : -1;
                render();
            } catch (_) {
                if (token === reqToken) hide();
            }
        };

        input.addEventListener('input', () => {
            const q = input.value.trim();
            clearTimeout(timer);
            if (q.length < 1) {
                hide();
                return;
            }
            timer = setTimeout(() => fetchSuggestions(q), 200);
        });

        input.addEventListener('keydown', (e) => {
            if (list.hidden || !items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                e.stopPropagation();
                activeIndex = (activeIndex + 1) % items.length;
                render();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                e.stopPropagation();
                activeIndex = (activeIndex - 1 + items.length) % items.length;
                render();
            } else if (e.key === 'Enter' && activeIndex >= 0) {
                e.preventDefault();
                e.stopPropagation();
                selectIndex(activeIndex);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                hide();
            } else if (e.key === 'Tab') {
                hide();
            }
        });

        list.addEventListener('mousedown', (e) => {
            const btn = e.target.closest('[data-suggest-idx]');
            if (!btn) return;
            e.preventDefault();
            selectIndex(Number(btn.dataset.suggestIdx));
        });

        input.addEventListener('blur', () => {
            setTimeout(hide, 120);
        });

        input._hasOpenSuggest = () => !list.hidden && items.length > 0;
    }

    bindEmailSuggest('advFrom', 'advFromSuggest', 'any');
    bindEmailSuggest('advTo', 'advToSuggest', 'any');

    el('modalAdvancedSearch').addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
            if (e.target.id === 'advFrom' || e.target.id === 'advTo') {
                if (e.target._hasOpenSuggest && e.target._hasOpenSuggest()) return;
            }
            e.preventDefault();
            applyAdvancedFilters();
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            closeModal();
        }
    });

    el('advFilterChips').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-clear-filter]');
        if (!btn) return;
        const key = btn.dataset.clearFilter;
        if (key === 'search') {
            el('inboxSearch').value = '';
        } else if (key in state.filters) {
            state.filters[key] = '';
            syncAdvancedFormFromState();
        }
        renderFilterChips();
        loadConversations({ append: false });
    });

    const SYNC_FOLDERS = [
        { key: 'inbox', label: 'Inbox' },
        { key: 'drafts', label: 'Drafts' },
        { key: 'sent', label: 'Sent' },
        { key: 'trash', label: 'Trash' },
        { key: 'spam', label: 'Spam' },
    ];

    function setSyncProgress(done, total, status, newCount, barRatio = null) {
        const safeTotal = Math.max(0, Number(total) || 0);
        const safeDone = Math.max(0, Number(done) || 0);
        const cappedDone = safeTotal > 0 ? Math.min(safeDone, safeTotal) : safeDone;
        const pct = barRatio != null
            ? Math.min(100, Math.max(0, Math.round(Number(barRatio) * 100)))
            : (safeTotal > 0
                ? Math.min(100, Math.round((cappedDone / safeTotal) * 100))
                : (safeDone > 0 ? 100 : 0));
        el('syncBarFill').style.width = pct + '%';
        el('syncPercent').textContent = pct + '%';
        el('syncDetail').textContent = cappedDone.toLocaleString() + ' / ' + safeTotal.toLocaleString() + ' to sync';
        el('syncEmailCount').textContent = cappedDone.toLocaleString() + ' / ' + safeTotal.toLocaleString();
        el('syncStatusText').textContent = status;
        el('syncNewCount').textContent = (newCount || 0).toLocaleString() + ' new message' + (newCount === 1 ? '' : 's');
    }

    function showSyncOverlay(show) {
        const overlay = el('syncOverlay');
        if (!overlay) return;
        overlay.hidden = !show;
        el('btnSync').classList.toggle('is-syncing', show);
        document.querySelectorAll('[data-sync-inbox]').forEach(btn => {
            btn.classList.toggle('is-syncing', show && Number(btn.dataset.syncInbox) === Number(state.syncingInboxId));
            btn.disabled = show;
        });
    }

    async function runInboxSync(inboxId, options = {}) {
        const quiet = !!options.quiet;
        const recentOnly = !!options.recentOnly || quiet;
        const skipRefresh = !!options.skipRefresh;
        if (state.syncingInboxId) return 0;

        const inbox = (state.inboxes || []).find(i => i.id === Number(inboxId));
        if (!inbox) {
            if (!quiet) alert('Select a personal or shared mailbox to sync.');
            return 0;
        }
        if (!inbox.connected) {
            if (!quiet) alert('Connect this mailbox to Microsoft 365 before syncing.');
            return 0;
        }

        state.syncingInboxId = inbox.id;
        if (!quiet) {
            el('btnSync').disabled = true;
            showSyncOverlay(true);
            setSyncProgress(0, 0, `Counting unsynced emails in ${inbox.name}…`, 0);
        } else {
            el('btnSync')?.classList.add('is-syncing');
            document.querySelectorAll(`[data-sync-inbox="${inbox.id}"]`).forEach(btn => {
                btn.classList.add('is-syncing');
            });
        }

        let totalEmails = 0;
        let totalNew = 0;
        let scanDone = 0;
        let scanTotal = 0;

        try {
            let already = 0;
            let foldersToSync = [];

            if (recentOnly) {
                // Lightweight newest-first probe — used by auto-sync.
                foldersToSync = [
                    { key: 'inbox', label: 'Inbox', remaining: 100, graph: 100, probe: true },
                    { key: 'sent', label: 'Sent', remaining: 50, graph: 50, probe: true },
                ];
                totalEmails = 0;
                scanTotal = 150;
                if (!quiet) {
                    setSyncProgress(0, 1, `Checking ${inbox.name} for new mail…`, 0, 0);
                }
            } else {
                const totals = await api('/sync-totals', {
                    method: 'POST',
                    body: { inbox_id: inbox.id },
                });
                totalEmails = totals?.remaining ?? totals?.total ?? 0;
                const inboxMeta = (totals?.inboxes || []).find(i => i.id === inbox.id) || totals?.inboxes?.[0] || {};
                already = inboxMeta.already_synced ?? totals?.already_synced ?? 0;
                const folderRemaining = inboxMeta.folders_remaining || {};
                const folderGraph = inboxMeta.folders || {};
                const foldersFailed = new Set(inboxMeta.folders_failed || []);

                // Always probe Inbox (and Sent) newest-first even when count delta is 0 —
                // Graph totalItemCount can match local while brand-new messages are still missing.
                // A folder whose Graph count lookup failed (throttling, timeout) is unknown,
                // not zero — always probe it too, or a transient error would make this pass
                // silently skip a folder that may have plenty of unsynced mail.
                foldersToSync = SYNC_FOLDERS
                    .map(f => {
                        const remaining = folderRemaining[f.key] || 0;
                        const graph = folderGraph[f.key] || 0;
                        const failed = foldersFailed.has(f.key);
                        const probe = failed || ((f.key === 'inbox' || f.key === 'sent') && remaining <= 0 && graph > 0);
                        return {
                            ...f,
                            remaining: probe ? 100 : remaining,
                            graph,
                            probe,
                        };
                    })
                    .filter(f => f.remaining > 0)
                    .sort((a, b) => {
                        // Inbox first so new mail lands before deep folder catch-up.
                        if (a.key === 'inbox') return -1;
                        if (b.key === 'inbox') return 1;
                        return b.remaining - a.remaining;
                    });

                scanTotal = foldersToSync.reduce((sum, f) => sum + (f.probe ? Math.min(100, f.graph || 100) : (f.graph || 0)), 0);
                if (totalEmails <= 0) {
                    totalEmails = foldersToSync.reduce((sum, f) => sum + (f.probe ? 0 : f.remaining), 0);
                }
            }

            if (!foldersToSync.length) {
                if (!quiet) {
                    setSyncProgress(0, 0, already
                        ? `${inbox.name} is up to date — ${already.toLocaleString()} emails already synced`
                        : `No emails to sync in ${inbox.name}`, 0, 1);
                    await new Promise(r => setTimeout(r, 900));
                    if (!skipRefresh) await loadBootstrap();
                    el('mailStatusLabel').textContent = already
                        ? `${inbox.name} · up to date`
                        : `${inbox.name} · nothing to sync`;
                }
                return 0;
            }

            if (!quiet) {
                setSyncProgress(
                    0,
                    Math.max(totalEmails, 1),
                    totalEmails > 0
                        ? `Syncing ${inbox.name}: ${totalEmails.toLocaleString()} new` +
                            (already ? ` (${already.toLocaleString()} already synced)` : '') +
                            '…'
                        : `Checking ${inbox.name} for new mail…`,
                    0,
                    0
                );
            }

            for (const folder of foldersToSync) {
                let nextLink = null;
                let folderFetched = 0;
                let folderImported = 0;
                const folderTarget = folder.remaining;
                let guard = 0;

                do {
                    const result = await api('/sync', {
                        method: 'POST',
                        body: {
                            all: false,
                            paged: true,
                            inbox_id: inbox.id,
                            folder: folder.key,
                            recent_only: recentOnly,
                            next_link: nextLink,
                            fetched_so_far: folderFetched,
                        },
                    });

                    const fetched = result?.fetched ?? 0;
                    const synced = result?.synced ?? 0;
                    const skipped = result?.skipped ?? Math.max(0, fetched - synced);
                    totalNew += synced;
                    folderFetched += fetched;
                    folderImported += synced;
                    scanDone += fetched;

                    if (totalEmails > 0 && totalNew > totalEmails) {
                        totalEmails = totalNew;
                    }

                    if (!quiet) {
                        const barRatio = scanTotal > 0 ? (scanDone / scanTotal) : null;
                        setSyncProgress(
                            totalNew,
                            Math.max(totalEmails, totalNew, 1),
                            synced > 0
                                ? `Syncing ${inbox.name} · ${folder.label}…`
                                : (skipped > 0
                                    ? `Checking ${inbox.name} · ${folder.label} (${folderFetched.toLocaleString()} scanned)`
                                    : `Syncing ${inbox.name} · ${folder.label}…`),
                            totalNew,
                            barRatio
                        );
                    }

                    nextLink = result?.next_link || null;

                    // Backend marks caught_up when a newest-first page is all already synced.
                    if (result?.caught_up || result?.done) {
                        nextLink = null;
                    }

                    // First page all skipped while count-delta is tiny → already have recent mail.
                    // Only short-circuit for the cheap auto-probe (recentOnly) or a synthetic
                    // "always check newest" probe entry — NOT for a real full/backfill folder,
                    // where a small remaining count can mean "almost caught up after an earlier
                    // interrupted sync" rather than "nothing left," and the unsynced mail can be
                    // further back than the very first page.
                    const looksIncremental = recentOnly || folder.probe;
                    if (looksIncremental && synced === 0 && fetched > 0 && folderFetched === fetched) {
                        nextLink = null;
                    }

                    if (folderTarget > 0 && folderImported >= folderTarget) {
                        nextLink = null;
                        const folderGraphCount = folder.graph || folderFetched;
                        if (!folder.probe && folderGraphCount > folderFetched) {
                            scanDone += (folderGraphCount - folderFetched);
                        }
                    }

                    // Auto-sync: never walk more than a couple pages per folder.
                    if (quiet && guard >= 2) {
                        nextLink = null;
                    }

                    guard++;
                    if (guard > 2000) break;
                } while (nextLink);
            }

            if (!quiet) {
                setSyncProgress(
                    totalEmails || totalNew,
                    totalEmails || totalNew,
                    `Finished ${inbox.name}…`,
                    totalNew,
                    1
                );
            }

            if (!skipRefresh) {
                await loadBootstrap();
                if (state.selectedId) await openConversation(state.selectedId);
            }

            if (!quiet) {
                el('mailStatusLabel').textContent = totalNew
                    ? `${inbox.name}: synced ${totalNew.toLocaleString()} new`
                    : `${inbox.name}: already up to date`;
            }
            return totalNew;
        } catch (err) {
            if (!quiet) {
                alert(err.message || 'Sync failed');
            } else {
                console.warn('Inbox auto-sync failed', err);
            }
            return 0;
        } finally {
            state.syncingInboxId = null;
            if (!quiet) {
                showSyncOverlay(false);
                el('btnSync').disabled = false;
                el('btnSync').title = 'Sync selected mailbox (also auto-checks every 45s)';
            } else {
                el('btnSync')?.classList.remove('is-syncing');
                document.querySelectorAll('[data-sync-inbox]').forEach(btn => {
                    btn.classList.remove('is-syncing');
                    btn.disabled = false;
                });
            }
        }
    }

    const AUTO_SYNC_INTERVAL_MS = 45000;

    async function runAutoSyncAll() {
        if (state.syncingInboxId || state.autoSyncRunning || document.hidden) return;
        const connected = (state.inboxes || []).filter(i => i.connected);
        if (!connected.length) return;

        state.autoSyncRunning = true;
        let imported = 0;
        try {
            for (const inbox of connected) {
                if (document.hidden || state.syncingInboxId) break;
                imported += await runInboxSync(inbox.id, {
                    quiet: true,
                    recentOnly: true,
                    skipRefresh: true,
                }) || 0;
            }
            if (imported > 0) {
                await loadBootstrap();
                if (state.selectedId) await openConversation(state.selectedId);
                el('mailStatusLabel').textContent = `Auto-synced ${imported.toLocaleString()} new`;
            }
        } catch (err) {
            console.warn('Inbox auto-sync pass failed', err);
        } finally {
            state.autoSyncRunning = false;
        }
    }

    function startAutoSync() {
        if (state.autoSyncTimer) clearInterval(state.autoSyncTimer);
        state.autoSyncTimer = setInterval(runAutoSyncAll, AUTO_SYNC_INTERVAL_MS);
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            runAutoSyncAll();
        }
    });

    el('btnSync').addEventListener('click', async () => {
        if (state.selectedInboxId) {
            await runInboxSync(state.selectedInboxId);
            return;
        }
        const connected = (state.inboxes || []).filter(i => i.connected);
        if (connected.length === 1) {
            await runInboxSync(connected[0].id);
            return;
        }
        if (!connected.length) {
            alert('Connect at least one personal or shared mailbox first.');
            return;
        }
        alert('Select a mailbox in the sidebar, then sync — or use the sync icon on that mailbox.');
    });

    el('btnConnectOutlook').addEventListener('click', () => { window.location = CONNECT; });
    el('btnDisconnectOutlook').addEventListener('click', async () => {
        if (!confirm('Disconnect your Outlook mailbox?')) return;
        await api('/disconnect', { method: 'POST', body: {} });
        await loadBootstrap();
    });

    el('btnArchive').addEventListener('click', async () => {
        if (!state.selectedId) return;
        await api('/conversations/' + state.selectedId + '/status', { method: 'PATCH', body: { status: 'archived' } });
        state.conversation = null; state.selectedId = null;
        renderThread();
        await loadBootstrap();
        await loadConversations();
    });
    el('btnSpam').addEventListener('click', async () => {
        if (!state.selectedId) return;
        await api('/conversations/' + state.selectedId + '/status', { method: 'PATCH', body: { status: 'spam' } });
        state.conversation = null; state.selectedId = null;
        renderThread();
        await loadBootstrap();
        await loadConversations();
    });
    el('btnTrash').addEventListener('click', async () => {
        if (!state.selectedId) return;
        await api('/conversations/' + state.selectedId + '/status', { method: 'PATCH', body: { status: 'trashed' } });
        state.conversation = null; state.selectedId = null;
        renderThread();
        await loadBootstrap();
        await loadConversations();
    });
    el('btnRestore').addEventListener('click', async () => {
        if (!state.selectedId) return;
        await api('/conversations/' + state.selectedId + '/status', { method: 'PATCH', body: { status: 'open' } });
        state.conversation = null; state.selectedId = null;
        renderThread();
        await loadBootstrap();
        await loadConversations();
    });
    el('btnReopen').addEventListener('click', async () => {
        if (!state.selectedId) return;
        await api('/conversations/' + state.selectedId + '/status', { method: 'PATCH', body: { status: 'open' } });
        await openConversation(state.selectedId);
        await loadConversations();
    });

    el('btnThreadMore')?.addEventListener('click', (e) => {
        e.stopPropagation();
        togglePop('threadMoreMenu', e.currentTarget);
    });
    el('btnSnooze')?.addEventListener('click', (e) => {
        e.stopPropagation();
        togglePop('snoozeMenu', e.currentTarget);
    });
    el('btnAssignToggle')?.addEventListener('click', (e) => {
        e.stopPropagation();
        renderAssignMenu();
        togglePop('assignMenu', e.currentTarget);
    });
    el('assignMenu')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-assign]');
        if (!btn) return;
        closeThreadPops();
        await assignConversation(btn.dataset.assign || null);
    });
    el('snoozeMenu')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-snooze]');
        if (!btn) return;
        closeThreadPops();
        const until = snoozeUntilDate(btn.dataset.snooze);
        try {
            await snoozeConversation(until);
        } catch (err) {
            alert(err.message);
        }
    });
    el('snoozeCustom')?.addEventListener('change', async () => {
        const raw = el('snoozeCustom').value;
        if (!raw) return;
        if (!isDatetimeLocalInFuture(raw)) {
            alert('Pick a future date and time.');
            return;
        }
        closeThreadPops();
        try {
            await snoozeConversation(raw);
        } catch (err) {
            alert(err.message);
        }
    });
    el('threadMoreMenu')?.addEventListener('click', async (e) => {
        const merge = e.target.closest('[data-thread-action="merge"]');
        if (merge) {
            closeThreadPops();
            try {
                await openMergeModal();
            } catch (err) {
                alert(err.message);
            }
            return;
        }
        const unmerge = e.target.closest('[data-thread-action="unmerge"]');
        if (unmerge) {
            closeThreadPops();
            try {
                await openMergeModal();
            } catch (err) {
                alert(err.message);
            }
            return;
        }
        const unread = e.target.closest('[data-thread-action="unread"]');
        if (!unread || !state.selectedId) return;
        closeThreadPops();
        await api('/conversations/' + state.selectedId + '/read', { method: 'PATCH', body: { is_read: false } });
        const row = state.conversations.find(c => Number(c.id) === Number(state.selectedId));
        if (row) row.is_read = false;
        if (state.conversation && Number(state.conversation.id) === Number(state.selectedId)) {
            state.conversation.is_read = false;
            if (Array.isArray(state.conversation.member_reads)) {
                state.conversation.member_reads = state.conversation.member_reads.map(m =>
                    Number(m.id) === USER_ID ? { ...m, is_read: false, last_read_at: null } : m
                );
            }
            renderThread();
        }
        el('conversationList')?.querySelector(`[data-conv-id="${state.selectedId}"]`)?.classList.add('unread');
        await loadConversations();
    });
    el('mergeSearch')?.addEventListener('input', () => {
        clearTimeout(mergeSearchTimer);
        mergeSearchTimer = setTimeout(() => {
            loadMergeCandidates(el('mergeSearch').value.trim()).catch(err => {
                el('mergeCandidateList').innerHTML = `<div class="inbox-tool-empty">${escapeHtml(err.message || 'Search failed.')}</div>`;
            });
        }, 250);
    });
    el('mergeCandidateList')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-merge-id]');
        if (!btn) return;
        try {
            await mergeSelectedConversation(btn.dataset.mergeId);
        } catch (err) {
            alert(err.message);
        }
    });
    el('mergeMergedList')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-unmerge-id]');
        if (!btn) return;
        try {
            await unmergeSelectedConversation(btn.dataset.unmergeId);
        } catch (err) {
            alert(err.message);
        }
    });
    el('btnUnmergeAll')?.addEventListener('click', async () => {
        try {
            await unmergeSelectedConversation(null);
        } catch (err) {
            alert(err.message);
        }
    });
    el('threadParticipants')?.addEventListener('click', (e) => {
        const participantsBtn = e.target.closest('#btnParticipants');
        if (participantsBtn) {
            e.stopPropagation();
            togglePop('participantsMenu', participantsBtn);
            return;
        }
        if (!e.target.closest('#btnAddParticipant')) return;
        e.stopPropagation();
        renderAssignMenu();
        togglePop('assignMenu', el('btnAssignToggle'));
    });
    el('threadMessages')?.addEventListener('click', (e) => {
        const cancelBtn = e.target.closest('[data-cancel-scheduled]');
        if (cancelBtn) {
            e.preventDefault();
            e.stopPropagation();
            const id = cancelBtn.dataset.cancelScheduled;
            if (!id || !state.selectedId) return;
            (async () => {
                if (!confirm('Cancel this scheduled message?')) return;
                try {
                    const data = await api('/conversations/' + state.selectedId + '/scheduled-replies/' + id, { method: 'DELETE' });
                    if (data.deleted) {
                        state.conversation = null;
                        state.selectedId = null;
                        renderThread();
                        await loadBootstrap();
                        await loadConversations();
                        return;
                    }
                    await openConversation(state.selectedId);
                } catch (err) {
                    alert(err.message);
                }
            })();
            return;
        }
        const replyBtn = e.target.closest('[data-reply-msg]');
        if (replyBtn) {
            e.preventDefault();
            e.stopPropagation();
            const msg = (state.conversation?.messages || []).find(m => String(m.id) === String(replyBtn.dataset.replyMsg));
            if (msg) startReplyFromMessage(msg, true);
            return;
        }
        const editDraftBtn = e.target.closest('[data-edit-draft]');
        if (editDraftBtn) {
            e.preventDefault();
            e.stopPropagation();
            const msg = (state.conversation?.messages || []).find(m => String(m.id) === String(editDraftBtn.dataset.editDraft));
            if (msg) openDraftReplyModal(msg);
            return;
        }
        if (e.target.closest('a, button')) return;
        if (e.target.closest('.inbox-msg-expanded')) return;
        const row = e.target.closest('.inbox-msg-row');
        if (!row) return;
        const sel = window.getSelection();
        if (sel && !sel.isCollapsed) return;
        const card = row.closest('.inbox-msg[data-msg-id], .inbox-msg[data-comment-id]');
        if (!card) return;
        card.classList.toggle('is-expanded');
        if (card.dataset.msgId) {
            state.expandedMessageIds[card.dataset.msgId] = card.classList.contains('is-expanded');
        }
    });
    el('btnComposerExpand')?.addEventListener('click', () => {
        state.composerExpanded = !state.composerExpanded;
        el('composerArea')?.classList.toggle('is-expanded', state.composerExpanded);
    });
    el('commentBody')?.addEventListener('focus', () => {
        el('composerArea')?.classList.add('is-expanded');
        state.composerExpanded = true;
    });
    el('commentBody')?.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
            e.preventDefault();
            el('btnSendComment')?.click();
        }
    });
    el('replyBody')?.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
            e.preventDefault();
            el('btnSendReply')?.click();
        }
    });
    const EMOJIS = ['👍', '🙂', '😂', '🎉', '🙏', '✅', '👀', '❤️', '🔥', '👏'];
    el('commentEmojiMenu') && (el('commentEmojiMenu').innerHTML = EMOJIS.map(emo =>
        `<button type="button" data-emoji="${emo}">${emo}</button>`
    ).join(''));
    el('btnCommentEmoji')?.addEventListener('click', (e) => {
        e.stopPropagation();
        togglePop('commentEmojiMenu', e.currentTarget);
    });
    el('commentEmojiMenu')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-emoji]');
        if (!btn) return;
        const editor = el('commentBody');
        if (editor) insertHtmlAtCaret(editor, btn.dataset.emoji);
        closeThreadPops();
        editor?.focus();
    });
    document.addEventListener('click', (e) => {
        if (e.target.closest('.inbox-pop')) return;
        closeThreadPops();
    });

    el('assignSelect').addEventListener('change', async () => {
        const val = el('assignSelect').value;
        await assignConversation(val || null);
    });

    async function attachConversationLabel({ labelId = null, name = null } = {}) {
        if (!state.selectedId) return;
        const lead = conversationLead();
        try {
            await api('/conversations/' + state.selectedId + '/lead-labels', {
                method: 'POST',
                body: {
                    lead_id: lead?.id || null,
                    label_id: labelId || null,
                    name: name || null,
                },
            });
            await loadBootstrap();
            await openConversation(state.selectedId);
            await loadConversations();
        } catch (err) {
            alert(err.message || 'Could not add label.');
        }
    }

    el('addTagSelect').addEventListener('change', async () => {
        if (!state.selectedId || !el('addTagSelect').value) return;
        if (!conversationLead()) {
            el('addTagSelect').value = '';
            return;
        }
        const labelId = Number(el('addTagSelect').value);
        await attachConversationLabel({ labelId });
    });

    el('btnAddLeadLabel')?.addEventListener('click', async () => {
        const input = el('addLeadLabelInput');
        const name = String(input?.value || '').trim();
        if (!name) {
            input?.focus();
            return;
        }
        await attachConversationLabel({ name });
    });
    el('addLeadLabelInput')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            el('btnAddLeadLabel')?.click();
        }
    });

    el('conversationTags').addEventListener('click', async (e) => {
        const leadBtn = e.target.closest('[data-remove-lead-label]');
        if (leadBtn && state.selectedId) {
            try {
                await api('/conversations/' + state.selectedId + '/lead-labels/' + leadBtn.dataset.removeLeadLabel + (conversationLead()?.id ? '?lead_id=' + conversationLead().id : ''), {
                    method: 'DELETE',
                });
                await openConversation(state.selectedId);
                await loadConversations();
            } catch (err) {
                alert(err.message || 'Could not remove label.');
            }
            return;
        }

        const conversationLabelBtn = e.target.closest('[data-remove-conversation-label]');
        if (conversationLabelBtn && state.selectedId) {
            try {
                await api('/conversations/' + state.selectedId + '/labels/' + conversationLabelBtn.dataset.removeConversationLabel, {
                    method: 'DELETE',
                });
                await openConversation(state.selectedId);
                await loadConversations();
            } catch (err) {
                alert(err.message || 'Could not remove label.');
            }
            return;
        }

        const inboxBtn = e.target.closest('[data-remove-inbox-tag]');
        if (inboxBtn && state.selectedId) {
            try {
                const removeId = Number(inboxBtn.dataset.removeInboxTag);
                const tagIds = (state.conversation?.tags || [])
                    .map(t => Number(t.id))
                    .filter(id => id > 0 && id !== removeId);
                await api('/conversations/' + state.selectedId + '/tags', {
                    method: 'POST',
                    body: { tag_ids: tagIds },
                });
                await openConversation(state.selectedId);
                await loadConversations();
            } catch (err) {
                alert(err.message || 'Could not remove label.');
            }
        }
    });

    function extractMentionUserIds(kind) {
        const editor = getComposerEl(kind);
        if (!editor) return [];
        return [...editor.querySelectorAll('[data-mention-user-id]')]
            .map(node => Number(node.dataset.mentionUserId))
            .filter(id => Number.isFinite(id));
    }

    el('btnSendComment')?.addEventListener('click', async () => {
        if (!state.selectedId) return;
        const html = getComposerHtml('comment');
        const hasFiles = state.commentAttachments.length > 0;
        if (isComposerEmpty('comment') && !hasFiles) {
            return alert('Write a comment or attach a file.');
        }
        el('btnSendComment').disabled = true;
        try {
            const body = isComposerEmpty('comment') ? '<p>Attachment</p>' : html;
            const data = await api('/conversations/' + state.selectedId + '/comments', {
                method: 'POST',
                body: {
                    body,
                    mentioned_user_ids: extractMentionUserIds('comment'),
                    attachments: state.commentAttachments.map(a => ({
                        name: a.name,
                        contentType: a.contentType,
                        contentBytes: a.contentBytes,
                    })),
                },
            });
            setComposerHtml('comment', '');
            state.commentAttachments = [];
            renderAttachChips('comment');
            hideMentionPopup('comment');
            await openConversation(state.selectedId);
            await loadConversations();
        } catch (err) {
            alert(err.message);
        } finally {
            el('btnSendComment').disabled = false;
        }
    });

    el('btnSendReply').addEventListener('click', async () => {
        await sendReply({});
    });

    el('btnSendReplyMenu')?.addEventListener('click', (e) => {
        e.stopPropagation();
        togglePop('sendReplyMenu', e.currentTarget);
    });

    el('sendReplyMenu')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-send-mode]');
        if (!btn) return;
        e.stopPropagation();
        const mode = btn.dataset.sendMode;
        if (mode === 'later') {
            const fields = el('sendLaterFields');
            if (fields) {
                fields.hidden = false;
                const input = el('sendLaterAt');
                if (input && !input.value) {
                    input.value = toDatetimeLocalValue(new Date(Date.now() + 60 * 60 * 1000));
                }
                input?.focus();
            }
            return;
        }
        closeThreadPops();
        if (mode === 'archive') {
            await sendReply({ archive: true });
        } else if (mode === 'draft') {
            await saveReplyDraft();
        } else {
            await sendReply({});
        }
    });

    el('btnConfirmSendLater')?.addEventListener('click', async (e) => {
        e.stopPropagation();
        const raw = el('sendLaterAt')?.value;
        if (!raw) return alert('Pick a date and time.');
        if (!isDatetimeLocalInFuture(raw)) {
            return alert('Choose a future date and time.');
        }
        closeThreadPops();
        await sendReply({ sendAt: datetimeLocalToApi(raw) });
    });

    async function sendReply(opts = {}) {
        if (!state.selectedId) return;
        const html = getComposerHtml('reply');
        if (isComposerEmpty('reply')) return alert('Write a reply first.');
        const to = (el('replyTo')?.value || '').trim();
        const cc = (el('replyCc')?.value || '').trim();
        const inboxId = Number(el('replyFrom')?.value || 0);
        if (!to) return alert('Add at least one To recipient.');
        const archive = !!opts.archive;
        const sendAt = opts.sendAt || null;
        el('btnSendReply').disabled = true;
        el('btnSendReplyMenu') && (el('btnSendReplyMenu').disabled = true);
        try {
            const payload = {
                body: html,
                to,
                cc: cc || null,
                attachments: state.replyAttachments.map(a => ({
                    name: a.name,
                    contentType: a.contentType,
                    contentBytes: a.contentBytes,
                })),
            };
            if (inboxId) payload.inbox_id = inboxId;
            if (archive) payload.archive = true;
            if (sendAt) payload.send_at = sendAt;
            if (state.replyDraftId) payload.draft_message_id = state.replyDraftId;
            const prepared = prepareEmailSendPayload(payload.body, state.replyAttachments);
            payload.body = prepared.body;
            payload.attachments = prepared.attachments;
            const data = await api('/conversations/' + state.selectedId + '/reply', { method: 'POST', body: payload });
            applyComposerSignature('reply');
            state.replyAttachments = [];
            state.replyCcEmails = [];
            state.replyAll = false;
            state.replyDraftId = null;
            if (el('replyTo')) el('replyTo').value = '';
            if (el('replyCc')) el('replyCc').value = '';
            renderAttachChips('reply');
            hideMentionPopup('reply');
            el('composerHint').textContent = 'Reply via Outlook';
            if (el('modalReply')?.style.display === 'grid') closeModal();

            if (data.scheduled) {
                await openConversation(data.conversation?.id || state.selectedId);
                await loadConversations();
                return;
            }

            if (data.archived || archive) {
                state.conversation = null;
                state.selectedId = null;
                renderThread();
                await loadBootstrap();
                await loadConversations();
                return;
            }

            await openConversation(data.conversation?.id || state.selectedId);
            await loadConversations();
        } catch (err) {
            alert(err.message);
        } finally {
            el('btnSendReply').disabled = false;
            if (el('btnSendReplyMenu')) el('btnSendReplyMenu').disabled = false;
        }
    }

    async function saveReplyDraft() {
        if (!state.selectedId) return;
        const html = getComposerHtml('reply');
        if (isComposerEmpty('reply')) return alert('Write a reply first.');
        const to = (el('replyTo')?.value || '').trim();
        const cc = (el('replyCc')?.value || '').trim();
        const inboxId = Number(el('replyFrom')?.value || 0);
        if (!to) return alert('Add at least one To recipient.');
        el('btnSendReply').disabled = true;
        el('btnSendReplyMenu') && (el('btnSendReplyMenu').disabled = true);
        const hint = el('composerHint');
        const hintPrevText = hint ? hint.textContent : '';
        try {
            const payload = { body: html, to, cc: cc || null };
            if (inboxId) payload.inbox_id = inboxId;
            if (state.replyDraftId) payload.draft_message_id = state.replyDraftId;
            const prepared = prepareEmailSendPayload(payload.body, state.replyAttachments);
            payload.body = prepared.body;
            payload.attachments = prepared.attachments;
            const data = await api('/conversations/' + state.selectedId + '/save-draft', { method: 'POST', body: payload });
            state.replyDraftId = data.draft_message_id || state.replyDraftId;
            if (hint) {
                hint.textContent = 'Draft saved to Outlook';
                setTimeout(() => {
                    if (el('modalReply')?.style.display === 'grid' && hint.textContent === 'Draft saved to Outlook') {
                        hint.textContent = hintPrevText || 'Reply via Outlook';
                    }
                }, 2500);
            }
            await loadConversations();
        } catch (err) {
            alert(err.message);
        } finally {
            el('btnSendReply').disabled = false;
            if (el('btnSendReplyMenu')) el('btnSendReplyMenu').disabled = false;
        }
    }

    el('btnModeComment')?.addEventListener('click', () => setComposerMode());
    el('btnModeReply')?.addEventListener('click', () => openReplyModal());

    el('btnOpenTemplateList')?.addEventListener('click', openTemplateListModal);
    el('btnCloseTemplateList')?.addEventListener('click', closeModal);
    el('btnNewTemplate')?.addEventListener('click', () => {
        if (!state.permissions.create_templates) return;
        openTemplateModal();
    });
    el('templateListSearch')?.addEventListener('input', () => {
        state.templateSearch = el('templateListSearch').value || '';
        state.templateListPage = 1;
        renderTemplateList();
    });
    el('templateList')?.addEventListener('click', (e) => {
        const useBtn = e.target.closest('[data-use-template]');
        if (useBtn) {
            useTemplateFromList(useBtn.dataset.useTemplate);
            return;
        }
        const editTemplate = e.target.closest('[data-edit-template]');
        if (editTemplate) {
            openTemplateModal(editTemplate.dataset.editTemplate);
            return;
        }
        const delTemplate = e.target.closest('[data-delete-template]');
        if (delTemplate) {
            deleteTemplateById(delTemplate.dataset.deleteTemplate);
        }
    });
    el('templateListPrevPage')?.addEventListener('click', () => {
        if (state.templateListPage > 1) {
            state.templateListPage -= 1;
            renderTemplateList();
        }
    });
    el('templateListNextPage')?.addEventListener('click', () => {
        const meta = paginatedTemplateListItems();
        if (state.templateListPage < meta.totalPages) {
            state.templateListPage += 1;
            renderTemplateList();
        }
    });
    el('btnOpenSignatureList')?.addEventListener('click', openSignatureListModal);
    el('btnCloseSignatureList')?.addEventListener('click', closeModal);
    el('btnCloseSignatureModal')?.addEventListener('click', closeModal);
    el('btnNewSignature')?.addEventListener('click', () => openSignatureModal());
    el('signatureListSearch')?.addEventListener('input', () => {
        state.signatureSearch = el('signatureListSearch').value || '';
        state.signatureListPage = 1;
        renderSignatureList();
    });
    el('signatureList')?.addEventListener('click', (e) => {
        const defaultBtn = e.target.closest('[data-default-signature]');
        if (defaultBtn) {
            setDefaultSignature(defaultBtn.dataset.defaultSignature);
            return;
        }
        const editBtn = e.target.closest('[data-edit-signature]');
        if (editBtn) {
            openSignatureModal(editBtn.dataset.editSignature);
            return;
        }
        const delBtn = e.target.closest('[data-delete-signature]');
        if (delBtn) {
            deleteSignatureById(delBtn.dataset.deleteSignature);
        }
    });
    el('signatureListPrevPage')?.addEventListener('click', () => {
        if (state.signatureListPage > 1) {
            state.signatureListPage -= 1;
            renderSignatureList();
        }
    });
    el('signatureListNextPage')?.addEventListener('click', () => {
        const meta = paginatedSignatureListItems();
        if (state.signatureListPage < meta.totalPages) {
            state.signatureListPage += 1;
            renderSignatureList();
        }
    });
    el('btnNewInbox').addEventListener('click', () => openModal('modalInbox'));
    el('btnNewRule')?.addEventListener('click', () => {
        if (!state.permissions.create_rules) return;
        openRuleModal();
    });
    el('btnAddRuleTrigger')?.addEventListener('click', () => {
        const used = new Set([...document.querySelectorAll('#ruleTriggers [data-rule-trigger]')].map(s => s.value));
        const next = RULE_TRIGGERS.find(t => !used.has(t.value));
        addRuleTriggerRow({ value: next?.value || 'inbound_message' });
    });
    el('btnAddRuleCondition')?.addEventListener('click', () => addRuleConditionRow());
    el('btnAddRuleAction')?.addEventListener('click', () => addRuleActionRow());
    el('ruleInboxToggle')?.addEventListener('click', (e) => {
        e.preventDefault();
        const menu = el('ruleInboxMenu');
        if (!menu) return;
        menu.hidden = !menu.hidden;
    });
    el('ruleInboxMenu')?.addEventListener('change', () => updateRuleInboxToggleLabel());
    document.addEventListener('click', (e) => {
        const picker = el('ruleInboxPicker');
        const menu = el('ruleInboxMenu');
        if (!picker || !menu || menu.hidden) return;
        if (!picker.contains(e.target)) menu.hidden = true;
    });
    el('ruleTriggers')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove-rule-row]');
        if (!btn) return;
        const wrap = el('ruleTriggers');
        if (wrap && wrap.querySelectorAll('[data-rule-trigger]').length <= 1) {
            alert('Keep at least one trigger.');
            return;
        }
        btn.closest('.inbox-rule-extra-card')?.remove();
        refreshRuleTriggerAddState();
    });
    el('ruleTriggers')?.addEventListener('change', (e) => {
        if (!e.target.matches('[data-rule-trigger]')) return;
        const help = e.target.closest('.inbox-rule-extra-card')?.querySelector('[data-rule-trigger-help]');
        if (help) help.textContent = triggerHelp(e.target.value);
    });
    el('ruleExtraConditions')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove-rule-row]');
        if (btn) btn.closest('.inbox-rule-extra-card')?.remove();
    });
    el('ruleActions')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove-rule-row]');
        if (btn) btn.closest('.inbox-rule-extra-card')?.remove();
    });
    el('ruleActions')?.addEventListener('change', (e) => {
        if (e.target.matches('[data-rule-action-type]')) refreshRuleActionValueSelects();
    });
    el('btnCompose').addEventListener('click', openComposeModal);
    el('btnComposeHeader').addEventListener('click', openComposeModal);
    document.querySelectorAll('[data-close-modal]').forEach(b => b.addEventListener('click', closeModal));
    el('modalBackdrop').addEventListener('click', (e) => { if (e.target === el('modalBackdrop')) closeModal(); });

    el('btnToggleInboxTools').addEventListener('click', () => {
        state.inboxToolsOpen = !state.inboxToolsOpen;
        renderNav();
    });
    el('btnToggleProps')?.addEventListener('click', () => {
        if (!state.conversation) return;
        setPropsOpen(!state.propsOpen);
    });
    el('btnHideProps')?.addEventListener('click', () => setPropsOpen(false));

    el('inboxToolsSubmenu').addEventListener('click', (e) => {
        const toggle = e.target.closest('[data-tool-toggle]');
        if (toggle) {
            const key = toggle.dataset.toolToggle;
            state.expandedToolGroups[key] = !state.expandedToolGroups[key];
            renderNav();
            return;
        }
    });

    document.querySelectorAll('[data-html-editor]').forEach(editor => {
        const kind = editor.dataset.htmlEditor;
        editor.addEventListener('mousedown', (e) => {
            if (e.target.closest('[data-html-link], [data-cmd]')) {
                saveHtmlEditorSelection(kind);
            }
        });
        editor.addEventListener('click', (e) => {
            const modeBtn = e.target.closest('[data-html-mode]');
            if (modeBtn) {
                e.preventDefault();
                setHtmlEditorMode(kind, modeBtn.dataset.htmlMode);
                return;
            }
            const cmdBtn = e.target.closest('[data-cmd]');
            const linkBtn = e.target.closest('[data-html-link]');
            if (linkBtn) {
                e.preventDefault();
                openHtmlLinkDialog(kind);
                return;
            }
            if (!cmdBtn) return;
            e.preventDefault();
            const ed = getHtmlEditor(kind);
            if (ed.source && !ed.source.hidden) {
                alert('Switch to Visual mode to use formatting buttons, or paste HTML in HTML mode.');
                return;
            }
            ed.visual?.focus();
            const cmd = cmdBtn.dataset.cmd;
            if (cmd === 'createLink') {
                openHtmlLinkDialog(kind);
            } else {
                document.execCommand(cmd, false, null);
            }
            if (ed.source) ed.source.value = sanitizeHtml(ed.visual?.innerHTML || '');
        });

        editor.addEventListener('input', () => {
            const ed = getHtmlEditor(kind);
            if (ed.source?.hidden !== false && ed.visual && ed.source) {
                ed.source.value = sanitizeHtml(ed.visual.innerHTML);
            }
        });
        edVisualClick(editor, kind);
        bindHtmlLinkHover(editor.querySelector('.inbox-html-visual'));
    });

    function edVisualClick(editor, kind) {
        editor.querySelector('.inbox-html-visual')?.addEventListener('click', (e) => {
            const visual = e.currentTarget;
            const img = e.target.closest('img');
            if (img && visual.contains(img)) {
                const range = document.createRange();
                range.selectNode(img);
                const sel = window.getSelection();
                sel?.removeAllRanges();
                sel?.addRange(range);
                saveHtmlEditorSelection(kind);
            }
            const link = e.target.closest('a');
            if (link && visual.contains(link)) {
                e.preventDefault();
            }
        });
    }

    el('btnHtmlLinkApply')?.addEventListener('click', applyHtmlLink);
    el('btnHtmlLinkCancel')?.addEventListener('click', closeHtmlLinkDialog);
    el('btnHtmlLinkRemove')?.addEventListener('click', removeHtmlLink);
    el('htmlLinkDialog')?.addEventListener('click', (e) => {
        if (e.target === el('htmlLinkDialog')) closeHtmlLinkDialog();
    });
    el('htmlLinkUrl')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            applyHtmlLink();
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            closeHtmlLinkDialog();
        }
    });
    el('htmlLinkText')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            applyHtmlLink();
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            closeHtmlLinkDialog();
        }
    });
    el('btnTemplateAttach')?.addEventListener('click', () => el('templateAttachInput')?.click());
    el('templateAttachInput')?.addEventListener('change', async (e) => {
        await addAttachments('template', e.target.files);
        e.target.value = '';
    });
    el('templateAttachChips')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove-attach]');
        if (!btn) return;
        const [bucketKind, idx] = btn.dataset.removeAttach.split(':');
        const bucket = attachmentBucket(bucketKind);
        const files = fileAttachmentsOnly(state[bucket] || []);
        files.splice(Number(idx), 1);
        state[bucket] = files;
        renderAttachChips(bucketKind);
    });
    el('btnTemplateImage')?.addEventListener('mousedown', () => saveHtmlEditorSelection('template'));
    el('btnTemplateImage')?.addEventListener('click', () => el('templateImageInput')?.click());
    el('btnTemplateLink')?.addEventListener('mousedown', () => saveHtmlEditorSelection('template'));
    el('btnTemplateLink')?.addEventListener('click', () => openHtmlLinkDialog('template'));
    el('templateImageInput')?.addEventListener('change', async (e) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        await insertHtmlEditorImage('template', file);
    });

    el('btnSignatureImage')?.addEventListener('mousedown', () => saveHtmlEditorSelection('signature'));
    el('btnSignatureImage')?.addEventListener('click', () => el('signatureImageInput')?.click());
    el('signatureImageInput')?.addEventListener('change', async (e) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        await insertHtmlEditorImage('signature', file);
    });

    el('btnSaveTemplate').addEventListener('click', async () => {
        if (!state.permissions.create_templates) {
            return alert('You do not have permission to manage templates.');
        }
        const name = el('newTemplateName').value.trim();
        const bodyHtml = getHtmlEditorContent('template');
        if (!name || !templateHasBody(bodyHtml)) {
            alert('Name and body are required.');
            return;
        }
        const payload = {
            name,
            subject: el('newTemplateSubject').value.trim() || null,
            body: htmlToPlain(bodyHtml),
            body_html: bodyHtml,
            body_text: htmlToPlain(bodyHtml),
            attachments: fileAttachmentsOnly(state.templateAttachments).map((a) => ({
                name: a.name,
                contentType: a.contentType,
                contentBytes: a.contentBytes,
            })),
        };
        const editingId = state.editingTemplateId;
        const btn = el('btnSaveTemplate');
        btn.disabled = true;
        try {
            let saved;
            if (editingId) {
                const data = await api('/templates/' + editingId, { method: 'PUT', body: payload });
                saved = data.template;
                const idx = state.templates.findIndex(t => String(t.id) === String(editingId));
                if (idx >= 0) state.templates[idx] = { ...state.templates[idx], ...saved, format: 'html' };
                else state.templates.unshift({ ...saved, format: 'html' });
            } else {
                const data = await api('/templates', { method: 'POST', body: payload });
                saved = data.template;
                state.templates.unshift({ ...saved, format: 'html' });
            }
            state.templates.sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' }));
            const returnToList = state.returnToTemplateList;
            state.returnToTemplateList = false;
            closeModal();
            updateTemplateCount();
            refreshTemplateSelects();
            if (returnToList) openTemplateListModal();
        } catch (err) {
            alert(err.message || 'Failed to save template');
        } finally {
            btn.disabled = false;
        }
    });

    el('btnDeleteTemplate').addEventListener('click', async () => {
        if (!state.editingTemplateId) return;
        const returnToList = state.returnToTemplateList;
        if (await deleteTemplateById(state.editingTemplateId)) {
            state.returnToTemplateList = false;
            closeModal();
            if (returnToList) openTemplateListModal();
        }
    });

    el('btnSaveSignature').addEventListener('click', () => {
        const name = el('newSignatureName').value.trim();
        const bodyHtml = getHtmlEditorContent('signature');
        if (!name || !htmlToPlain(bodyHtml)) {
            alert('Name and signature are required.');
            return;
        }
        const body = htmlToPlain(bodyHtml);
        if (state.editingSignatureId) {
            const idx = state.signatures.findIndex(s => String(s.id) === String(state.editingSignatureId));
            if (idx >= 0) {
                state.signatures[idx] = {
                    ...state.signatures[idx],
                    name,
                    body,
                    body_html: bodyHtml,
                    format: 'html',
                };
            }
        } else {
            const item = {
                id: 'sig_' + Date.now(),
                name,
                body,
                body_html: bodyHtml,
                format: 'html',
            };
            state.signatures.unshift(item);
            if (!state.defaultSignatureId) {
                state.defaultSignatureId = item.id;
            }
        }
        const returnToList = state.returnToSignatureList;
        state.returnToSignatureList = false;
        state.editingSignatureId = null;
        saveLocalTools();
        closeModal();
        updateSignatureCount();
        if (el('modalCompose')?.style.display === 'grid') {
            applyComposerSignature('compose', stripSignatureHtml(getComposerHtml('compose')));
        }
        if (state.selectedId) {
            applyComposerSignature('reply', stripSignatureHtml(getComposerHtml('reply')));
        }
        if (returnToList) {
            openSignatureListModal();
        }
    });

    el('btnSendCompose').addEventListener('click', async () => {
        await sendCompose({});
    });

    el('btnSendComposeMenu')?.addEventListener('click', (e) => {
        e.stopPropagation();
        togglePop('composeSendMenu', e.currentTarget);
    });

    el('composeSendMenu')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-compose-send-mode]');
        if (!btn) return;
        e.stopPropagation();
        const mode = btn.dataset.composeSendMode;
        if (mode === 'later') {
            const fields = el('composeSendLaterFields');
            if (fields) {
                fields.hidden = false;
                const input = el('composeSendLaterAt');
                if (input && !input.value) {
                    input.value = toDatetimeLocalValue(new Date(Date.now() + 60 * 60 * 1000));
                }
                input?.focus();
            }
            return;
        }
        closeThreadPops();
        await sendCompose({});
    });

    el('btnConfirmComposeSendLater')?.addEventListener('click', async (e) => {
        e.stopPropagation();
        const raw = el('composeSendLaterAt')?.value;
        if (!raw) return alert('Pick a date and time.');
        if (!isDatetimeLocalInFuture(raw)) {
            return alert('Choose a future date and time.');
        }
        closeThreadPops();
        await sendCompose({ sendAt: datetimeLocalToApi(raw) });
    });

    async function sendCompose(opts = {}) {
        const inboxId = Number(el('composeFrom').value);
        const to = el('composeTo').value.trim();
        const subject = el('composeSubject').value.trim();
        const html = getComposerHtml('compose');
        if (!inboxId) return alert('Select a From inbox.');
        if (!to) return alert('Add at least one recipient.');
        if (!subject) return alert('Subject is required.');
        if (isComposerEmpty('compose')) return alert('Write a message first.');

        const sendAt = opts.sendAt || null;
        el('btnSendCompose').disabled = true;
        el('btnSendCompose').textContent = sendAt ? 'Scheduling…' : 'Sending…';
        if (el('btnSendComposeMenu')) el('btnSendComposeMenu').disabled = true;
        try {
            const payload = {
                inbox_id: inboxId,
                to,
                cc: el('composeCc').value.trim() || null,
                subject,
                body: html,
                attachments: state.composeAttachments.map(a => ({
                    name: a.name,
                    contentType: a.contentType,
                    contentBytes: a.contentBytes,
                })),
            };
            if (sendAt) payload.send_at = sendAt;
            const prepared = prepareEmailSendPayload(payload.body, state.composeAttachments);
            payload.body = prepared.body;
            payload.attachments = prepared.attachments;
            const data = await api('/compose', { method: 'POST', body: payload });
            state.composeAttachments = [];
            renderAttachChips('compose');
            hideMentionPopup('compose');
            setComposerHtml('compose', '');
            closeModal();

            if (data.scheduled) {
                state.view = 'drafts';
                state.selectedInboxId = inboxId;
                state.expandedInboxIds[inboxId] = true;
                await loadBootstrap();
                await loadConversations();
                if (data.conversation?.id) {
                    await openConversation(data.conversation.id);
                }
                return;
            }

            state.view = 'sent';
            state.selectedInboxId = inboxId;
            state.expandedInboxIds[inboxId] = true;
            await loadBootstrap();
            await loadConversations();
            if (data.conversation?.id) {
                await openConversation(data.conversation.id);
            }
        } catch (err) {
            alert(err.message);
        } finally {
            el('btnSendCompose').disabled = false;
            el('btnSendCompose').textContent = 'Send';
            if (el('btnSendComposeMenu')) el('btnSendComposeMenu').disabled = false;
        }
    }

    function getConnectMode() {
        return document.querySelector('input[name="connectMode"]:checked')?.value || 'mailbox_login';
    }

    function updateInboxEmailLabel() {
        const mode = getConnectMode();
        const label = el('newInboxEmailLabel');
        if (!label) return;
        label.childNodes[0].textContent = mode === 'shared_mailbox'
            ? 'Shared mailbox email (required) '
            : 'Mailbox email (required) ';
        el('newInboxEmail').placeholder = mode === 'shared_mailbox'
            ? 'support@yourcompany.com'
            : 'inquiry@yourcompany.com — must match the MS365 account you sign in with';
        el('newInboxEmail').required = true;
    }

    document.querySelectorAll('input[name="connectMode"]').forEach(r => {
        r.addEventListener('change', updateInboxEmailLabel);
    });

    async function createSharedInbox(andConnect) {
        const name = el('newInboxName').value.trim();
        if (!name) return alert('Name required');
        const mode = getConnectMode();
        const email = el('newInboxEmail').value.trim();
        if (!email) {
            return alert(mode === 'shared_mailbox'
                ? 'Shared mailbox email is required.'
                : 'Mailbox email is required. Sign in with that same Microsoft 365 account.');
        }
        const member_ids = [...el('newInboxMembers').selectedOptions].map(o => Number(o.value));
        const data = await api('/inboxes', {
            method: 'POST',
            body: {
                name,
                email: email || null,
                external_mailbox: mode === 'shared_mailbox' ? email : null,
                connect_mode: mode,
                member_ids,
            },
        });
        el('newInboxName').value = '';
        el('newInboxEmail').value = '';
        closeModal();
        if (andConnect && data.connect_url) {
            window.location = data.connect_url;
            return;
        }
        await loadBootstrap();
        if (data.inbox && !data.inbox.connected) {
            alert('Shared inbox created. Click “Sign in” next to it to connect Microsoft 365.');
        }
    }

    el('btnSaveInbox').addEventListener('click', async () => {
        try {
            await createSharedInbox(false);
        } catch (err) {
            alert(err.message);
        }
    });
    el('btnSaveInboxConnect').addEventListener('click', async () => {
        el('btnSaveInboxConnect').disabled = true;
        try {
            await createSharedInbox(true);
        } catch (err) {
            alert(err.message);
            el('btnSaveInboxConnect').disabled = false;
        }
    });

    el('btnSaveRule')?.addEventListener('click', async () => {
        if (!state.permissions.create_rules) return alert('You do not have permission to add rules.');
        const payload = collectRulePayload();
        if (!payload.name) return alert('Enter a name for this rule.');
        if (!payload.triggers.length) return alert('Add at least one trigger.');
        const extraConditions = payload.conditions.filter(c => c.field !== 'inbox');
        if (extraConditions.some(c => !String(c.value || '').trim())) {
            return alert('Each condition needs a value.');
        }
        if (!payload.actions.length) {
            return alert('Add at least one action.');
        }
        for (const action of payload.actions) {
            if (['assign'].includes(action.type) && (action.value === null || action.value === '')) {
                return alert('Assign actions need a teammate.');
            }
            if (action.type === 'reopen_after_days') {
                const days = Number(action.value);
                if (!Number.isFinite(days) || days < 1 || days > 365) {
                    return alert('Choose how many days before reopen (1–365).');
                }
            }
        }
        const btn = el('btnSaveRule');
        btn.disabled = true;
        try {
            await api('/rules', { method: 'POST', body: payload });
            closeModal();
            await loadBootstrap();
        } catch (err) {
            alert(err.message || 'Failed to create rule');
        } finally {
            btn.disabled = false;
        }
    });

    function openMembersModal(inboxId) {
        const inbox = state.inboxes.find(i => i.id === inboxId);
        if (!inbox) return;
        state.editingMembersInboxId = inboxId;
        el('membersInboxName').textContent = inbox.name;
        const selected = new Map((inbox.members || []).map(m => [m.id, m.role]));
        el('membersEditor').innerHTML = state.members.map(m => {
            const role = selected.get(m.id);
            const checked = role ? 'checked' : '';
            return `<div class="inbox-member-row">
                <label style="display:flex;align-items:center;gap:0.5rem;font-weight:500;">
                    <input type="checkbox" data-member-id="${m.id}" ${checked}>
                    ${escapeHtml(m.name)}
                </label>
                <select data-member-role="${m.id}">
                    <option value="member" ${role === 'member' ? 'selected' : ''}>Member</option>
                    <option value="admin" ${role === 'admin' ? 'selected' : ''}>Admin</option>
                </select>
            </div>`;
        }).join('');
        openModal('modalMembers');
    }

    el('btnSaveMembers').addEventListener('click', async () => {
        if (!state.editingMembersInboxId) return;
        const members = [];
        el('membersEditor').querySelectorAll('[data-member-id]').forEach(cb => {
            if (!cb.checked) return;
            const id = Number(cb.dataset.memberId);
            const role = el('membersEditor').querySelector(`[data-member-role="${id}"]`)?.value || 'member';
            members.push({ user_id: id, role });
        });
        await api('/inboxes/' + state.editingMembersInboxId + '/members', { method: 'PUT', body: { members } });
        closeModal();
        await loadBootstrap();
    });

    bindComposerExtras('comment');
    bindComposerExtras('reply');
    bindComposerExtras('compose');
    bindHtmlLinkHover(el('commentBody'));
    bindHtmlLinkHover(el('replyBody'));
    bindHtmlLinkHover(el('composeBody'));
    setComposerMode();
    window.addEventListener('resize', syncOpenTemplatePickerPosition);
    document.addEventListener('scroll', syncOpenTemplatePickerPosition, true);
    document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-template-picker]')) closeTemplatePickers();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (el('htmlLinkDialog') && !el('htmlLinkDialog').hidden) {
                e.preventDefault();
                closeHtmlLinkDialog();
                return;
            }
            closeTemplatePickers();
            if (state.checkedIds.length) clearCheckedConversations();
            if (el('modalBackdrop')?.style.display === 'flex') {
                closeModal();
            }
        }
    });
    loadLocalTools();
    loadBootstrap().then(async () => {
        const params = new URLSearchParams(window.location.search);
        const conversationId = Number(params.get('conversation') || 0);
        if (conversationId) {
            await openConversation(conversationId);
            params.delete('conversation');
            const next = params.toString();
            window.history.replaceState({}, '', window.location.pathname + (next ? '?' + next : ''));
        }
        startAutoSync();
        // First quiet check shortly after load so new mail appears without clicking Sync.
        setTimeout(runAutoSyncAll, 4000);
    }).catch(err => {
        el('conversationList').innerHTML = `<div class="inbox-empty">${escapeHtml(err.message)}</div>`;
    });

    // Keep relative hours/minutes fresh while the inbox is open.
    setInterval(refreshConversationTimes, 30000);
})();
</script>
@endsection
