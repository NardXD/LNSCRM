@extends('layouts.app')

@section('title', 'Inbox')

@push('styles')
    @vite(['resources/css/pages/inbox.css'])
@endpush

@push('scripts')
    @vite(['resources/js/pages/inbox.js'])
@endpush

@section('content')
@php $inboxPopout = request()->boolean('popout'); @endphp
<script>
if (new URLSearchParams(location.search).get('popout') === '1') {
    document.documentElement.classList.add('inbox-is-popout');
    document.title = 'Loading… - Inbox';
}
</script>
<div class="inbox-page-wrapper">
<div class="inbox-app" id="inboxApp"
     data-api="{{ url('api/inbox') }}"
     data-csrf="{{ csrf_token() }}"
     data-user-id="{{ auth()->id() }}"
     data-connect="{{ route('inbox.connect.outlook') }}">

    @if(session('status') === 'outlook-mail-connected')
        <div class="inbox-toast success">Outlook mailbox connected. New mail will sync in the background.</div>
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
                </div>
            </div>

            <div class="inbox-nav-section">
                <div class="inbox-nav-label">Views</div>
                <button type="button" class="inbox-nav-item active" data-view="open" data-scope="all">
                    <span>Open</span><span class="inbox-count" id="countOpen">0</span>
                </button>
                <div id="viewGroups"></div>
            </div>

            <div class="inbox-nav-section inbox-labels-section">
                <div class="inbox-nav-label-row">
                    <div class="inbox-nav-label">Pinned</div>
                    <button type="button" class="inbox-mini-btn" id="btnCustomizeLabels" title="Customize labels">+</button>
                </div>
                <div class="inbox-labels-search">
                    <input type="search" id="sidebarLabelSearch" placeholder="Search labels…" autocomplete="off" aria-label="Search labels">
                </div>
                <div id="sidebarLabelList"></div>
            </div>

            <div class="inbox-nav-section">
                <div class="inbox-nav-label-row">
                    <div class="inbox-nav-label">Inboxes</div>
                    <button type="button" class="inbox-mini-btn" id="btnNewInbox" title="New shared inbox">+</button>
                </div>
                <div id="inboxList">
                    <div class="inbox-skel-mailbox" aria-hidden="true">
                        <span class="inbox-skel-line w-70"></span>
                        <span class="inbox-skel-line w-50"></span>
                    </div>
                    <div class="inbox-skel-mailbox" aria-hidden="true">
                        <span class="inbox-skel-line w-60"></span>
                        <span class="inbox-skel-line w-40"></span>
                    </div>
                </div>
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
                        <input type="search" id="inboxSearch" placeholder="Quick search or message ID…" autocomplete="off">
                        <button type="button" class="inbox-btn ghost" id="btnToggleAdvancedSearch" title="Advanced search">Filters</button>
                    </div>
                    <div class="inbox-adv-chips" id="advFilterChips"></div>
                    <div class="inbox-list-merge-bar" id="listMergeBar" hidden>
                        <span class="inbox-list-merge-count" id="listMergeCount">0 selected</span>
                        <button type="button" class="inbox-btn ghost" id="btnArchiveSelected">Archive</button>
                        <button type="button" class="inbox-btn primary" id="btnMergeSelected">Merge conversations</button>
                        <button type="button" class="inbox-btn ghost" id="btnClearChecked">Clear</button>
                    </div>
                </div>
                <div class="inbox-label-folders" id="labelFolders" hidden></div>
            </div>
            <div class="inbox-conversation-list" id="conversationList" aria-busy="true" title="Click to open. Double-click to pop out. Ctrl+click (Cmd+click on Mac) to select threads, then Archive.">
                <div class="inbox-skel-list" aria-hidden="true">
                    @for ($i = 0; $i < 8; $i++)
                        <div class="inbox-skel-conv">
                            <span class="inbox-skel-line w-35"></span>
                            <span class="inbox-skel-line w-55"></span>
                            <span class="inbox-skel-line w-80"></span>
                            <span class="inbox-skel-line w-70"></span>
                        </div>
                    @endfor
                </div>
            </div>
        </section>

        {{-- Thread --}}
        <section class="inbox-thread-pane">
            <div class="inbox-thread-placeholder" id="threadPlaceholder" @if($inboxPopout) style="display:none;" @endif>
                <div class="inbox-placeholder-card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <h3>Your shared inbox</h3>
                    <p>Connect personal or shared Outlook mailboxes and keep teammate assignment in sync with leads.</p>
                </div>
            </div>

            <div class="inbox-thread{{ $inboxPopout ? ' is-loading' : '' }}" id="threadView" style="display:{{ $inboxPopout ? 'flex' : 'none' }};" @if($inboxPopout) aria-busy="true" @endif>
                <div class="inbox-thread-header">
                    <div class="inbox-thread-heading">
                        <h2 id="threadSubject">{{ $inboxPopout ? 'Loading…' : '' }}</h2>
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

                <div class="inbox-messages" id="threadMessages">
                    @if($inboxPopout)
                        <div class="inbox-skel-thread" aria-hidden="true">
                            <div class="inbox-skel-msg">
                                <div class="inbox-skel-avatar"></div>
                                <div class="inbox-skel-msg-body">
                                    <span class="inbox-skel-line w-40"></span>
                                    <span class="inbox-skel-line w-90"></span>
                                    <span class="inbox-skel-line w-70"></span>
                                </div>
                            </div>
                            <div class="inbox-skel-msg">
                                <div class="inbox-skel-avatar"></div>
                                <div class="inbox-skel-msg-body">
                                    <span class="inbox-skel-line w-35"></span>
                                    <span class="inbox-skel-line w-80"></span>
                                    <span class="inbox-skel-line w-55"></span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="inbox-composer" id="composerArea">
                    <div class="inbox-composer-modes">
                        <button type="button" class="inbox-composer-mode is-active" data-composer-mode="comment" id="btnModeComment">Comment</button>
                        <button type="button" class="inbox-composer-mode" data-composer-mode="reply" id="btnModeReply">Reply</button>
                        <button type="button" class="inbox-composer-mode" data-composer-mode="forward" id="btnModeForward">Forward</button>
                        <button type="button" class="inbox-composer-mode" data-composer-mode="resend" id="btnModeResend">Resend</button>
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
                <div class="inbox-search-select" id="assignSearchSelect">
                    <button type="button" class="inbox-select inbox-search-select-btn" id="assignSearchToggle" aria-haspopup="listbox" aria-expanded="false" aria-controls="assignSearchMenu">
                        <span id="assignSearchLabel">Unassigned</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="inbox-search-select-menu" id="assignSearchMenu" hidden>
                        <div class="inbox-assign-search">
                            <input type="search" id="assignSearchInput" placeholder="Search teammates…" autocomplete="off" aria-label="Search teammates">
                        </div>
                        <div class="inbox-assign-list" id="assignSearchList" role="listbox"></div>
                    </div>
                    <select id="assignSelect" class="inbox-select" tabindex="-1" aria-hidden="true">
                        <option value="">Unassigned</option>
                    </select>
                </div>
            </div>
            <div class="inbox-props-block">
                <div class="inbox-props-label">Labels</div>
                <div id="conversationTags" class="inbox-tag-pills"></div>
                <div class="inbox-search-select" id="addTagSearchSelect">
                    <button type="button" class="inbox-select inbox-search-select-btn" id="addTagSearchToggle" aria-haspopup="listbox" aria-expanded="false" aria-controls="addTagSearchMenu">
                        <span id="addTagSearchLabel">Add existing label…</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div class="inbox-search-select-menu" id="addTagSearchMenu" hidden>
                        <div class="inbox-assign-search">
                            <input type="search" id="addTagSearchInput" placeholder="Search labels…" autocomplete="off" aria-label="Search labels">
                        </div>
                        <div class="inbox-assign-list" id="addTagSearchList" role="listbox"></div>
                    </div>
                    <select id="addTagSelect" class="inbox-select" tabindex="-1" aria-hidden="true">
                        <option value="">Add existing label…</option>
                    </select>
                </div>
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
        <h3 id="composeModalTitle">New message</h3>
        <p class="inbox-modal-help" id="composeModalHelp">Send email through a connected Outlook inbox.</p>
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
                    <button type="button" data-compose-send-mode="share-draft">Share as draft…</button>
                    <div class="inbox-send-later" id="composeSendLaterFields" hidden>
                        Send at
                        <input type="datetime-local" id="composeSendLaterAt">
                        <button type="button" class="inbox-btn primary" id="btnConfirmComposeSendLater">Schedule send</button>
                    </div>
                    <div class="inbox-share-draft" id="composeShareDraftFields" hidden>
                        <input type="search" id="composeShareDraftSearch" placeholder="Search teammates…" autocomplete="off" aria-label="Search teammates">
                        <div class="inbox-share-draft-list" id="composeShareDraftList"></div>
                        <button type="button" class="inbox-btn primary" id="btnConfirmComposeShareDraft">Share draft</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="inbox-modal inbox-modal-wide" id="modalReply" style="display:none;">
        <h3 id="replyModalTitle">Reply</h3>
        <p class="inbox-modal-help" id="replyModalHelp">Email reply via Outlook.</p>
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
                    <button type="button" data-send-mode="share-draft">Share as draft…</button>
                    <div class="inbox-send-later" id="sendLaterFields" hidden>
                        Send at
                        <input type="datetime-local" id="sendLaterAt">
                        <button type="button" class="inbox-btn primary" id="btnConfirmSendLater">Schedule send</button>
                    </div>
                    <div class="inbox-share-draft" id="replyShareDraftFields" hidden>
                        <input type="search" id="replyShareDraftSearch" placeholder="Search teammates…" autocomplete="off" aria-label="Search teammates">
                        <div class="inbox-share-draft-list" id="replyShareDraftList"></div>
                        <button type="button" class="inbox-btn primary" id="btnConfirmReplyShareDraft">Share draft</button>
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
        <p class="inbox-modal-help">Attach files (up to 5, 10 MB each) or insert images inline in Visual mode. Select text or an image, then click Link to make it clickable.</p>
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
                <p class="inbox-modal-help">Saved to your account only. Other users keep their own signatures. The default is added automatically to compose and replies.</p>
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
                <p class="inbox-modal-help">Saved to your account only. Other users keep their own signatures. The default is added automatically to compose and replies.</p>
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

    <div class="inbox-modal inbox-modal-list inbox-modal-sidebar-labels" id="modalSidebarLabels" style="display:none;" role="dialog" aria-labelledby="sidebarLabelsTitle">
        <div class="inbox-tpl-list-head">
            <div class="inbox-tpl-list-head-text">
                <h3 id="sidebarLabelsTitle">Pinned labels</h3>
                <p class="inbox-modal-help">Choose which labels appear in your sidebar.</p>
            </div>
            <button type="button" class="inbox-tpl-close-btn" data-close-modal aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="inbox-tpl-list-toolbar">
            <div class="inbox-tpl-search-wrap">
                <svg class="inbox-tpl-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="sidebarLabelPickerSearch" class="inbox-tpl-search" placeholder="Search labels…" autocomplete="off" aria-label="Search labels to pin">
            </div>
        </div>
        <div class="inbox-label-picker-meta">
            <span id="sidebarLabelPickerCount">0 selected</span>
            <div class="inbox-label-picker-meta-actions">
                <button type="button" class="inbox-tpl-link-btn" id="btnSidebarLabelsSelectAll">Select all</button>
                <button type="button" class="inbox-tpl-link-btn muted" id="btnSidebarLabelsClear">Clear</button>
            </div>
        </div>
        <div class="inbox-label-picker-shell">
            <div class="inbox-label-picker" id="sidebarLabelPickerList"></div>
        </div>
        <div class="inbox-modal-actions">
            <button type="button" class="inbox-btn ghost" data-close-modal>Cancel</button>
            <button type="button" class="inbox-btn primary" id="btnSaveSidebarLabels">Save</button>
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

{{-- Photo / video attachment viewer --}}
<div class="inbox-lightbox-backdrop" id="mediaLightbox" hidden>
    <div class="inbox-lightbox" role="dialog" aria-modal="true" aria-label="Attachment preview">
        <div class="inbox-lightbox-toolbar">
            <span class="inbox-lightbox-name" id="mediaLightboxName"></span>
            <a class="inbox-lightbox-download" id="mediaLightboxDownload" href="#" download title="Download">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </a>
            <button type="button" class="inbox-lightbox-close" id="btnMediaLightboxClose" title="Close" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="inbox-lightbox-body" id="mediaLightboxBody"></div>
    </div>
</div>

</div>{{-- /.inbox-page-wrapper --}}
@endsection

