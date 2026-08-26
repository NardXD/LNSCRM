@extends('layouts.app')

@section('title', 'Messages')

@section('content')
<div class="msg-page-wrapper">
<div class="msg-page" id="messagingApp" data-api-base="{{ url('api/messaging') }}" data-csrf="{{ csrf_token() }}">
    <div class="msg-layout">
        <aside class="msg-sidebar" id="msgSidebar">
            <div class="msg-sidebar-header">
                <div>
                    <h2>Messages</h2>
                    <p class="msg-sub">Internal team chat</p>
                </div>
                <div class="msg-header-actions">
                    <button type="button" class="msg-icon-btn" onclick="window.openCreateGroupModal()" title="Create group">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            <line x1="12" y1="11" x2="12" y2="17"/>
                            <line x1="9" y1="14" x2="15" y2="14"/>
                        </svg>
                    </button>
                    <button type="button" class="msg-icon-btn" onclick="window.openNewChatModal()" title="New conversation">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                    </button>
                    <button type="button" class="msg-icon-btn" id="msgRefreshBtn" title="Refresh">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                    </button>
                </div>
            </div>
            <div class="msg-search">
                <input type="search" id="conversationSearch" placeholder="Search conversations…" autocomplete="off">
            </div>
            <div class="msg-thread-list" id="chatsList">
                <div class="msg-list-hint" id="chatsListEmpty" style="display: none;">No conversations yet. Start a new chat or create a group.</div>
                <div id="chatsListItems"></div>
                <div class="msg-list-hint" id="chatsLoadMore" style="display: none;">Scroll for older chats</div>
            </div>
        </aside>

        <main class="msg-main" id="msgMain">
            <div class="msg-empty" id="messagingPlaceholder">
                <div class="msg-empty-card">
                    <h3>Select a conversation</h3>
                    <p>Choose a chat from the sidebar or start a new one.</p>
                </div>
            </div>

            <div class="msg-chat" id="msgChat" style="display:none;">
                <header class="msg-chat-header" id="chatHeader">
                    <button type="button" class="msg-icon-btn msg-back" id="chatBackBtn" onclick="window.goBackToConversationList()" aria-label="Back">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    </button>
                    <div class="msg-avatar" id="chatHeaderAvatar"></div>
                    <div class="msg-chat-meta">
                        <h3 id="chatHeaderName">Conversation</h3>
                        <span id="chatHeaderStatus">Team chat</span>
                    </div>
                    <div class="msg-chat-actions">
                        <button type="button" class="msg-icon-btn" onclick="window.startVideoCall()" title="Video call">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                        </button>
                        <button type="button" class="msg-icon-btn" onclick="window.startAudioCall()" title="Audio call">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </button>
                        <button type="button" class="msg-icon-btn" onclick="window.showChatInfo()" title="Chat info">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        </button>
                        <button type="button" class="msg-icon-btn msg-icon-danger" id="chatDeleteBtn" onclick="window.deleteChat()" title="Delete chat" style="display: none;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                        </button>
                    </div>
                </header>

                <div class="msg-messages" id="messagesArea">
                    <div class="msg-load-older" id="messagesLoadOlder" hidden>Loading earlier messages…</div>
                    <div class="msg-message-list" id="messageGroup"></div>
                </div>

                <footer class="msg-composer" id="messageInputArea">
                    <div class="msg-composer-tools">
                        <button type="button" class="msg-icon-btn" onclick="window.attachFile()" title="Attach file">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                        </button>
                        <button type="button" class="msg-icon-btn" onclick="window.attachImage()" title="Attach image">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        </button>
                        <button type="button" class="msg-icon-btn" onclick="window.showEmojiPicker()" title="Emoji">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                        </button>
                    </div>
                    <div class="attachment-preview-bar" id="attachmentPreviewBar" style="display: none;"></div>
                    <div class="emoji-picker-popover" id="emojiPickerPopover">
                        <div class="emoji-picker-grid" id="emojiPickerGrid"></div>
                    </div>
                    <div class="msg-edit-banner" id="msgEditBanner" hidden>
                        <span>Editing message</span>
                        <button type="button" id="msgEditCancel" onclick="window.cancelEditMessage()">Cancel</button>
                    </div>
                    <div class="msg-reply-banner" id="msgReplyBanner" hidden>
                        <div class="msg-reply-banner-copy">
                            <span class="msg-reply-banner-label">Replying to <strong id="msgReplyAuthor"></strong></span>
                            <span class="msg-reply-banner-preview" id="msgReplyPreview"></span>
                        </div>
                        <button type="button" onclick="window.cancelReplyMessage()">Cancel</button>
                    </div>
                    <div class="msg-composer-row">
                        <textarea id="messageInput" rows="1" placeholder="Message or paste an image"></textarea>
                        <button type="button" class="msg-send-btn" id="sendBtn" title="Send" aria-label="Send" disabled>
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l-1.4 1.4 5.6 5.6H4v2h12.2l-5.6 5.6L12 20l8-8z"/></svg>
                        </button>
                    </div>
                </footer>
            </div>
        </main>
    </div>
</div>
</div>

    <!-- Create Group Modal -->
    <div class="modal-overlay" id="createGroupModal">
        <div class="modal create-group-modal">
            <div class="modal-header">
                <h3 class="modal-title">Create Group</h3>
                <button type="button" class="modal-close" onclick="window.closeCreateGroupModal()" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <form class="modal-body" id="createGroupForm">
                <div class="form-group">
                    <label for="groupName">Group name</label>
                    <input type="text" id="groupName" class="form-input" placeholder="e.g. Design Team" required>
                </div>
                <div class="form-group">
                    <label>Group photo</label>
                    <div class="group-avatar-upload" id="groupAvatarUpload">
                        <div class="group-avatar-preview" id="groupAvatarPreview" onclick="window.pickGroupAvatar()" title="Add photo">
                            <div class="group-avatar-placeholder" id="groupAvatarPlaceholder">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21 15 16 10 5 21"/>
                                </svg>
                                <span>Add photo</span>
                            </div>
                            <img id="groupAvatarImg" class="group-avatar-img" src="" alt="" style="display: none;">
                        </div>
                        <button type="button" class="group-avatar-remove" id="groupAvatarRemove" onclick="window.clearGroupAvatar()" style="display: none;" title="Remove photo">✕</button>
                    </div>
                    <input type="file" id="groupAvatarInput" accept="image/*" style="display: none;">
                </div>
                <div class="form-group">
                    <label>Add members</label>
                    <div class="group-members-search">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                        <input type="text" class="form-input" placeholder="Search team members..." id="groupMemberSearch" oninput="filterGroupMembers(this.value)">
                    </div>
                    <div class="group-members-list" id="groupMembersList"></div>
                    <div class="group-selected-members" id="groupSelectedMembers"></div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="window.closeCreateGroupModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="createGroupBtn">Create Group</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Chat Info Modal -->
    <div class="modal-overlay" id="chatInfoModal">
        <div class="modal chat-info-modal">
            <div class="chat-info-modal-header">
                <h3 class="chat-info-modal-title" id="chatInfoTitle">Chat Info</h3>
                <button type="button" class="chat-info-modal-close" onclick="window.closeChatInfo()" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="chat-info-modal-body" id="chatInfoBody">
                <div id="chatInfoDirect" style="display: none;">
                    <div class="chat-info-user">
                        <div class="chat-info-avatar" id="chatInfoUserAvatar"></div>
                        <div class="chat-info-details">
                            <div class="chat-info-name" id="chatInfoUserName"></div>
                            <div class="chat-info-email" id="chatInfoUserEmail"></div>
                            <div class="chat-info-phone" id="chatInfoUserPhone" style="display: none;"></div>
                        </div>
                    </div>
                </div>
                <div id="chatInfoGroup" style="display: none;">
                    <div class="chat-info-group-header">
                        <div class="chat-info-group-avatar-wrap" id="chatInfoGroupAvatarWrap" onclick="window.pickChatInfoGroupPhoto()">
                            <div class="chat-info-group-avatar" id="chatInfoGroupAvatar"></div>
                            <div class="chat-info-group-avatar-overlay" id="chatInfoGroupAvatarOverlay">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                <span>Change photo</span>
                            </div>
                        </div>
                        <input type="file" id="chatInfoGroupPhotoInput" accept="image/*" style="display: none;">
                        <div class="chat-info-group-name" id="chatInfoGroupName"></div>
                    </div>
                    <div class="chat-info-section">
                        <div class="chat-info-section-header">
                            <span class="chat-info-section-title">Members</span>
                            <button type="button" class="chat-info-btn chat-info-btn-primary" id="chatInfoAddMemberBtn" onclick="window.openAddMemberToGroup()" style="display: none;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                Add member
                            </button>
                        </div>
                        <div class="chat-info-members-list" id="chatInfoMembersList"></div>
                    </div>
                    <div class="chat-info-section chat-info-section-card" id="chatInfoTransferSection" style="display: none;">
                        <div class="chat-info-section-header">
                            <span class="chat-info-section-title">Transfer ownership</span>
                        </div>
                        <p class="chat-info-transfer-hint">Transfer group ownership to another member. They will be able to add or remove members.</p>
                        <button type="button" class="chat-info-btn chat-info-btn-outline" id="chatInfoTransferBtn" onclick="window.openTransferOwnershipModal()">
                            Transfer ownership
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transfer Ownership Modal -->
    <div class="modal-overlay" id="transferOwnershipModal">
        <div class="modal create-group-modal">
            <div class="modal-header">
                <h3 class="modal-title">Transfer Ownership</h3>
                <button type="button" class="modal-close" onclick="window.closeTransferOwnershipModal()" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <p class="chat-info-transfer-hint" style="margin-bottom: 1rem;">Select a member to become the new group owner. You will no longer be able to add or remove members.</p>
                <div class="chat-info-members-list" id="transferOwnershipList"></div>
            </div>
        </div>
    </div>

    <!-- Add Member to Group Modal -->
    <div class="modal-overlay" id="addMemberToGroupModal">
        <div class="modal create-group-modal">
            <div class="modal-header">
                <h3 class="modal-title">Add Member</h3>
                <button type="button" class="modal-close" onclick="window.closeAddMemberToGroup()" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="group-members-search">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input type="text" class="form-input" placeholder="Search team members..." id="addMemberSearch" oninput="filterAddMemberList(this.value)">
                </div>
                <div class="group-members-list" id="addMemberToList"></div>
            </div>
        </div>
    </div>

    <div class="msg-image-lightbox" id="msgImageLightbox" hidden>
        <button type="button" class="msg-image-lightbox-close" id="msgImageLightboxClose" aria-label="Close preview">✕</button>
        <img id="msgImageLightboxImg" src="" alt="">
    </div>
@endsection

@push('styles')
<style>
    .main-content > .content:has(.msg-page-wrapper) {
        max-width: none !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .msg-page-wrapper {
        --msg-bg: #f4f5f7;
        --msg-panel: #ffffff;
        --msg-accent: #0ea5e9;
        --msg-accent-soft: #e0f2fe;
        --msg-green: #34C759;
        --msg-gray-bubble: #E9E9EB;
        --msg-imessage-bg: #ffffff;
        margin: 0;
        width: 100%;
        height: calc(100vh - 64px);
        min-height: calc(100vh - 64px);
        padding: 10px 12px 12px;
        background: var(--bg-primary, #fafafa);
    }

    .msg-page {
        height: 100%;
        width: 100%;
        position: relative;
        display: flex;
        flex-direction: column;
        background: var(--msg-panel);
        overflow: hidden;
        border: 1px solid var(--border);
        border-radius: 10px;
    }

    .msg-layout {
        display: grid;
        grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
        height: 100%;
        width: 100%;
        min-height: 0;
        background: var(--msg-panel);
    }

    .msg-sidebar {
        display: flex;
        flex-direction: column;
        min-height: 0;
        min-width: 0;
        background: var(--msg-panel);
        border-right: 1px solid var(--border);
    }
    .msg-sidebar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        min-height: 64px;
        padding: 1rem 1.15rem;
        border-bottom: 1px solid var(--border);
        flex-shrink: 0;
    }
    .msg-sidebar-header h2 { margin: 0; font-size: 1rem; font-weight: 700; }
    .msg-sub { margin: 0.15rem 0 0; color: var(--text-secondary); font-size: 0.75rem; }
    .msg-header-actions { display: flex; gap: 0.15rem; }
    .msg-search { padding: 0.7rem 1.15rem 0.8rem; flex-shrink: 0; }
    .msg-search input {
        width: 100%;
        padding: 0.45rem 0.7rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--msg-bg);
        color: var(--text-primary);
        font-size: 0.82rem;
    }
    .msg-thread-list { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 0.35rem 0.55rem 0.75rem; }
    .msg-thread {
        display: flex;
        gap: 0.6rem;
        align-items: center;
        padding: 0.55rem 0.65rem;
        cursor: pointer;
        border-radius: 10px;
    }
    .msg-thread:hover { background: var(--msg-bg); }
    .msg-thread.active { background: #eef0f3; }
    .msg-thread.unread .msg-thread-name { font-weight: 700; }
    .msg-thread-body { min-width: 0; flex: 1; }
    .msg-thread-top { display: flex; justify-content: space-between; gap: 0.5rem; align-items: baseline; }
    .msg-thread-name { font-weight: 600; font-size: 0.84rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-primary); }
    .msg-thread-time { color: var(--text-secondary); font-size: 0.68rem; white-space: nowrap; }
    .msg-thread-preview { color: var(--text-secondary); font-size: 0.75rem; margin-top: 0.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3; }
    .msg-badge {
        display: inline-flex; min-width: 1.05rem; height: 1.05rem; padding: 0 0.3rem;
        align-items: center; justify-content: center; border-radius: 999px;
        background: var(--msg-accent); color: #fff; font-size: 0.64rem; font-weight: 700; flex-shrink: 0;
    }
    .msg-list-hint { text-align: center; padding: 0.7rem; font-size: 0.72rem; color: var(--text-secondary); }
    .msg-avatar {
        width: 32px; height: 32px; border-radius: 8px; background: var(--msg-accent); color: #fff;
        display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.7rem; flex-shrink: 0;
        overflow: hidden;
    }
    .msg-avatar img, .msg-avatar .avatar-photo {
        width: 100%; height: 100%; object-fit: cover;
    }
    .msg-avatar.group {
        background: var(--msg-accent-soft);
        color: var(--msg-accent);
    }
    .msg-avatar.group svg { width: 16px; height: 16px; }

    .msg-main { display: flex; flex-direction: column; min-width: 0; min-height: 0; overflow: hidden; background: var(--msg-bg); }
    .msg-empty { flex: 1; display: flex; align-items: center; justify-content: center; padding: 2rem; }
    .msg-empty-card { text-align: center; max-width: 360px; }
    .msg-empty-card h3 { margin: 0 0 0.4rem; font-size: 1.05rem; }
    .msg-empty-card p { color: var(--text-secondary); margin: 0; font-size: 0.88rem; line-height: 1.45; }

    .msg-chat { display: flex; flex-direction: column; height: 100%; min-height: 0; background: var(--msg-imessage-bg); }
    .msg-chat-header {
        display: flex; align-items: center; gap: 0.75rem;
        min-height: 64px;
        padding: 1rem 1.15rem;
        border-bottom: 1px solid var(--border);
        background: var(--msg-panel); flex-shrink: 0;
    }
    .msg-chat-meta { flex: 1; min-width: 0; }
    .msg-chat-meta h3 { margin: 0; font-size: 0.92rem; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .msg-chat-meta span { color: var(--text-secondary); font-size: 0.72rem; }
    .msg-chat-actions { display: flex; gap: 0.15rem; }

    .msg-messages {
        flex: 1 1 auto;
        min-height: 0;
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
        padding: 0.75rem 1rem 1.1rem;
        display: flex;
        flex-direction: column;
        background: var(--msg-imessage-bg);
    }
    .msg-load-older {
        text-align: center;
        font-size: 0.7rem;
        color: #8e8e93;
        padding: 0.35rem 0 0.5rem;
        flex-shrink: 0;
    }
    .msg-message-list {
        margin-top: auto;
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-height: min-content;
        font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;
    }
    .msg-stamp {
        align-self: center;
        font-size: 11px;
        font-weight: 600;
        color: #8e8e93;
        letter-spacing: -0.01em;
        margin: 12px 0 8px;
        text-align: center;
        line-height: 1.3;
    }
    .msg-row {
        display: flex;
        align-items: flex-end;
        gap: 6px;
        max-width: min(78%, 520px);
    }
    .msg-row.inbound { align-self: flex-start; margin-left: 4px; }
    .msg-row.outbound { align-self: flex-end; margin-right: 10px; flex-direction: row-reverse; }
    .msg-row-avatar {
        width: 24px; height: 24px; border-radius: 7px; background: var(--msg-accent); color: #fff;
        display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.58rem;
        flex-shrink: 0; overflow: hidden; visibility: hidden;
    }
    .msg-row.inbound.tail .msg-row-avatar { visibility: visible; }
    .msg-row.outbound .msg-row-avatar { display: none; }
    .msg-row.direct .msg-row-avatar { display: none; }
    .msg-row-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .msg-col { display: flex; flex-direction: column; min-width: 0; position: relative; }
    .msg-row.outbound .msg-col { align-items: flex-end; }
    .msg-bubble-wrap {
        display: flex;
        align-items: center;
        gap: 6px;
        max-width: 100%;
        position: relative;
    }
    .msg-row.inbound .msg-bubble-wrap { flex-direction: row-reverse; }
    .msg-row.outbound .msg-bubble-wrap { flex-direction: row; }
    .msg-actions {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-shrink: 0;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.12s ease;
    }
    .msg-row:hover .msg-actions,
    .msg-bubble-wrap:focus-within .msg-actions {
        opacity: 1;
        pointer-events: auto;
    }
    .msg-edit-btn,
    .msg-reply-btn {
        width: 34px; height: 34px; border: 0; border-radius: 50%;
        background: #fff; color: #3a3a3c; cursor: pointer;
        box-shadow: 0 1px 4px rgba(0,0,0,0.16);
        display: inline-flex; align-items: center; justify-content: center;
        padding: 0; flex-shrink: 0;
    }
    .msg-edit-btn:hover,
    .msg-reply-btn:hover {
        background: #f2f2f7; color: #007aff;
    }
    .msg-edit-btn svg,
    .msg-reply-btn svg { width: 16px; height: 16px; }
    .msg-row.direct .msg-reply-btn { display: none !important; }
    .msg-quote {
        display: block; width: 100%; text-align: left;
        border: 0; border-left: 3px solid rgba(0,0,0,0.22);
        background: #fff;
        color: #111;
        border-radius: 8px;
        padding: 5px 8px 6px;
        margin: 0 0 6px;
        cursor: pointer;
        max-width: 260px;
    }
    .msg-bubble.outbound .msg-quote {
        border-left-color: rgba(0,0,0,0.22);
        background: #fff;
        color: #111;
    }
    .msg-quote-author {
        display: block; font-size: 11px; font-weight: 700; margin-bottom: 1px;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .msg-quote-body {
        display: block; font-size: 12px; opacity: 0.85; line-height: 1.25;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .msg-row-flash .msg-bubble { outline: 2px solid #007aff; outline-offset: 2px; }
    .msg-reply-banner {
        display: flex; align-items: flex-start; justify-content: space-between;
        gap: 0.75rem; padding: 0.35rem 0.15rem 0.2rem;
        border-left: 3px solid #007aff; padding-left: 0.65rem;
    }
    .msg-reply-banner[hidden] { display: none !important; }
    .msg-reply-banner-copy { min-width: 0; display: flex; flex-direction: column; gap: 1px; }
    .msg-reply-banner-label { font-size: 0.75rem; color: #007aff; }
    .msg-reply-banner-preview {
        font-size: 0.75rem; color: #8e8e93;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 320px;
    }
    .msg-reply-banner button {
        border: 0; background: none; color: #8e8e93; cursor: pointer; font-size: 0.78rem; flex-shrink: 0;
    }
    .msg-reply-banner button:hover { color: #111; }
    .msg-meta {
        display: flex; align-items: center; gap: 0.35rem;
        font-size: 11px; color: #8e8e93; margin: 2px 8px 0; line-height: 1.2;
    }
    .msg-row.outbound .msg-meta { justify-content: flex-end; }
    .msg-seen {
        font-size: 11px; color: #8e8e93; margin: 2px 8px 0; line-height: 1.2;
        cursor: default; user-select: none;
    }
    .msg-seen.is-clickable { cursor: pointer; }
    .msg-seen.is-clickable:hover { text-decoration: underline; }
    .msg-seen-popover {
        position: fixed; z-index: 2100;
        min-width: 180px; max-width: 260px; max-height: 240px; overflow-y: auto;
        background: #fff; border: 1px solid #e5e5ea; border-radius: 12px;
        box-shadow: 0 10px 28px rgba(0,0,0,0.14); padding: 0.5rem 0;
        display: none;
    }
    .msg-seen-popover.open { display: block; }
    .msg-seen-person {
        display: flex; align-items: center; gap: 0.5rem;
        padding: 0.4rem 0.75rem; font-size: 0.82rem; color: #111;
    }
    .msg-seen-person img, .msg-seen-person .msg-seen-initials {
        width: 24px; height: 24px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
    }
    .msg-seen-initials {
        background: var(--msg-accent); color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.6rem; font-weight: 700;
    }
    .msg-seen-empty { padding: 0.5rem 0.75rem; font-size: 0.8rem; color: #8e8e93; }
    .msg-edit-banner {
        display: flex; align-items: center; justify-content: space-between;
        gap: 0.75rem; font-size: 0.78rem; color: #007aff;
        padding: 0.2rem 0.15rem 0.15rem;
    }
    .msg-edit-banner[hidden] { display: none !important; }
    .msg-edit-banner button {
        border: 0; background: none; color: #8e8e93; cursor: pointer; font-size: 0.78rem;
    }
    .msg-edit-banner button:hover { color: #111; }
    .msg-composer.editing .msg-composer-tools { opacity: 0.45; pointer-events: none; }
    .msg-sender {
        display: none;
        font-size: 11px;
        font-weight: 500;
        color: #8e8e93;
        margin: 0 12px 2px;
    }
    .msg-row.inbound.group-start .msg-sender,
    .msg-row.inbound.solo .msg-sender { display: block; }
    .msg-row.direct .msg-sender { display: none !important; }
    .msg-bubble {
        position: relative;
        max-width: 100%;
        padding: 7px 13px 8px;
        border-radius: 18px;
        font-size: 15px;
        line-height: 1.32;
        letter-spacing: -0.01em;
        word-break: break-word;
        white-space: pre-wrap;
    }
    .msg-bubble.inbound {
        background: var(--msg-gray-bubble);
        color: #000;
    }
    .msg-bubble.outbound {
        background: var(--msg-green);
        color: #fff;
    }
    .msg-row.solo,
    .msg-row.group-start { margin-top: 8px; }
    .msg-stamp + .msg-row { margin-top: 0; }
    .msg-row.inbound.group-start:not(.solo) .msg-bubble { border-bottom-left-radius: 5px; }
    .msg-row.inbound.group-mid .msg-bubble { border-top-left-radius: 5px; border-bottom-left-radius: 5px; }
    .msg-row.inbound.group-end .msg-bubble { border-top-left-radius: 5px; }
    .msg-row.outbound.group-start:not(.solo) .msg-bubble { border-bottom-right-radius: 5px; }
    .msg-row.outbound.group-mid .msg-bubble { border-top-right-radius: 5px; border-bottom-right-radius: 5px; }
    .msg-row.outbound.group-end .msg-bubble { border-top-right-radius: 5px; }
    .msg-row.inbound.tail .msg-bubble::before,
    .msg-row.outbound.tail .msg-bubble::before {
        content: "";
        position: absolute;
        bottom: 0;
        width: 16px;
        height: 16px;
    }
    .msg-row.inbound.tail .msg-bubble::after,
    .msg-row.outbound.tail .msg-bubble::after {
        content: "";
        position: absolute;
        bottom: 0;
        width: 10px;
        height: 16px;
        background: var(--msg-imessage-bg);
    }
    .msg-row.inbound.tail .msg-bubble::before {
        left: -6px;
        background: var(--msg-gray-bubble);
        border-bottom-right-radius: 12px;
    }
    .msg-row.inbound.tail .msg-bubble::after {
        left: -10px;
        border-bottom-right-radius: 8px;
    }
    .msg-row.outbound.tail .msg-bubble::before {
        right: -6px;
        background: var(--msg-green);
        border-bottom-left-radius: 12px;
    }
    .msg-row.outbound.tail .msg-bubble::after {
        right: -10px;
        border-bottom-left-radius: 8px;
    }
    .msg-bubble img.msg-inline-image {
        display: block;
        max-width: min(240px, 100%);
        max-height: 180px;
        border-radius: 12px;
        margin: 2px 0;
        cursor: zoom-in;
    }
    .msg-image-lightbox {
        position: fixed;
        inset: 0;
        z-index: 3000;
        background: rgba(0, 0, 0, 0.82);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2.5rem 1.5rem 1.5rem;
        cursor: zoom-out;
    }
    .msg-image-lightbox[hidden] { display: none !important; }
    .msg-image-lightbox img {
        max-width: min(96vw, 1200px);
        max-height: calc(100vh - 5rem);
        width: auto;
        height: auto;
        object-fit: contain;
        border-radius: 8px;
        box-shadow: 0 16px 48px rgba(0, 0, 0, 0.45);
        cursor: default;
    }
    .msg-image-lightbox-close {
        position: absolute;
        top: 0.85rem;
        right: 0.85rem;
        width: 36px;
        height: 36px;
        border: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.14);
        color: #fff;
        font-size: 1.1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }
    .msg-image-lightbox-close:hover {
        background: rgba(255, 255, 255, 0.28);
    }
    .msg-bubble-text + .msg-inline-image,
    .msg-bubble-text + .msg-attachment { margin-top: 6px; }
    .msg-attachment {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.25rem;
        padding: 0.35rem 0.15rem;
        max-width: 280px;
    }
    .msg-attachment-icon { width: 22px; height: 22px; flex-shrink: 0; opacity: 0.9; }
    .msg-attachment-name { font-size: 0.82rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .msg-attachment a { color: inherit; text-decoration: underline; }

    .msg-composer {
        position: relative;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        padding: 0.45rem 0.85rem 0.75rem;
        border-top: 1px solid #e5e5ea;
        background: var(--msg-imessage-bg);
        flex-shrink: 0;
    }
    .msg-composer-tools { display: flex; gap: 0.1rem; }
    .msg-composer-row {
        display: flex; align-items: flex-end; gap: 0.45rem;
    }
    .msg-composer textarea {
        flex: 1; resize: none; min-height: 36px; max-height: 110px;
        padding: 8px 14px; border: 1px solid #c7c7cc; border-radius: 20px;
        background: #fff; color: #000; font: inherit; font-size: 15px; line-height: 1.3;
        font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;
    }
    .msg-composer textarea::placeholder { color: #8e8e93; }
    .msg-send-btn {
        width: 32px; height: 32px; border: 0; border-radius: 50%; padding: 0;
        background: var(--msg-green); color: #fff; cursor: pointer;
        display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .msg-send-btn svg { width: 16px; height: 16px; transform: rotate(-90deg); }
    .msg-send-btn:disabled { opacity: 0.6; cursor: not-allowed; }
    .msg-icon-btn {
        width: 32px; height: 32px; border: 0; border-radius: 8px; background: transparent;
        color: var(--text-secondary); cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
    }
    .msg-icon-btn:hover { background: var(--msg-bg); color: var(--text-primary); }
    .msg-icon-btn svg { width: 16px; height: 16px; }
    .msg-icon-danger:hover { background: #fef2f2; color: #ef4444; }
    .msg-back { display: none; }

    .attachment-preview-bar {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0;
        flex-wrap: wrap;
    }
    .attachment-preview-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.3rem 0.65rem;
        background: var(--msg-bg);
        border: 1px solid var(--border);
        border-radius: 16px;
        font-size: 0.78rem;
    }
    .attachment-preview-chip img {
        max-width: 40px;
        max-height: 40px;
        border-radius: 6px;
    }
    .attachment-preview-chip .chip-name {
        color: var(--text-primary);
        max-width: 120px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .attachment-preview-chip button {
        background: none;
        border: none;
        color: var(--text-muted, #8e8e93);
        cursor: pointer;
        padding: 0.15rem;
        display: flex;
    }
    .emoji-picker-popover {
        position: absolute;
        bottom: 100%;
        left: 0.5rem;
        margin-bottom: 0.5rem;
        background: var(--msg-panel);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        padding: 0.75rem;
        display: none;
        z-index: 100;
        max-height: 200px;
        overflow-y: auto;
    }
    .emoji-picker-popover.open { display: block; }
    .emoji-picker-grid {
        display: grid;
        grid-template-columns: repeat(8, 1fr);
        gap: 0.25rem;
    }
    .emoji-picker-btn {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        background: none;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }
    .emoji-picker-btn:hover { background: var(--msg-bg); }

    .avatar-initials {
        width: 36px; height: 36px; border-radius: 50%; background: var(--msg-accent); color: #fff;
        display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.75rem; flex-shrink: 0;
    }
    .avatar-photo {
        width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
    }

    /* Create Group Modal */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 2000;
        padding: 1rem;
    }

    .modal-overlay.open {
        display: flex;
    }

    .modal {
        background: var(--bg-card);
        border-radius: 12px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        max-width: 440px;
        width: 100%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
    }

    .modal-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .modal-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .modal-close {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: none;
        border: none;
        border-radius: 8px;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .modal-close:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .modal-close svg {
        width: 18px;
        height: 18px;
    }

    .modal-body {
        padding: 1.5rem;
        overflow-y: auto;
    }

    .form-group {
        margin-bottom: 1.25rem;
    }

    .form-group:last-of-type {
        margin-bottom: 1.5rem;
    }

    .form-group label {
        display: block;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .form-input {
        width: 100%;
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent);
    }

    .group-avatar-upload {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .group-avatar-preview {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        border: 2px dashed var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        cursor: pointer;
        background: var(--bg-primary);
        transition: border-color 0.15s;
    }

    .group-avatar-preview:hover {
        border-color: var(--accent);
    }

    .group-avatar-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.25rem;
        color: var(--text-muted);
        font-size: 0.75rem;
    }

    .group-avatar-placeholder svg {
        width: 28px;
        height: 28px;
    }

    .group-avatar-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .group-avatar-remove {
        width: 28px;
        height: 28px;
        border: none;
        border-radius: 50%;
        background: var(--border);
        color: var(--text-secondary);
        font-size: 0.875rem;
        cursor: pointer;
        flex-shrink: 0;
        transition: all 0.15s;
    }

    .group-avatar-remove:hover {
        background: #ef4444;
        color: #fff;
    }

    /* Chat Info Modal - Modern, larger design */
    .chat-info-modal {
        max-width: 520px;
        width: 100%;
        border-radius: 16px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .chat-info-modal-header {
        padding: 1.5rem 1.5rem 1.25rem;
        background: linear-gradient(180deg, rgba(95, 97, 230, 0.06) 0%, transparent 100%);
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .chat-info-modal-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
        letter-spacing: -0.02em;
    }

    .chat-info-modal-close {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 10px;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.2s;
    }

    .chat-info-modal-close:hover {
        background: var(--bg-card);
        color: var(--text-primary);
        border-color: var(--text-muted);
    }

    .chat-info-modal-close svg {
        width: 18px;
        height: 18px;
    }

    .chat-info-modal-body {
        padding: 1.5rem;
        max-height: 70vh;
        overflow-y: auto;
    }

    .chat-info-user {
        display: flex;
        gap: 1.5rem;
        align-items: center;
        padding: 1rem;
        background: var(--bg-primary);
        border-radius: 12px;
        border: 1px solid var(--border);
    }

    .chat-info-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--accent-light) 0%, rgba(95, 97, 230, 0.15) 100%);
        color: var(--accent);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1.75rem;
        flex-shrink: 0;
        overflow: hidden;
        border: 2px solid rgba(95, 97, 230, 0.2);
    }

    .chat-info-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .chat-info-details {
        flex: 1;
        min-width: 0;
    }

    .chat-info-name {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.375rem;
        letter-spacing: -0.01em;
    }

    .chat-info-email, .chat-info-phone {
        font-size: 0.9375rem;
        color: var(--text-secondary);
    }

    .chat-info-group-header {
        text-align: center;
        padding: 1.5rem 0 1.75rem;
        margin-bottom: 1.5rem;
        border-bottom: 1px solid var(--border);
    }

    .chat-info-group-avatar-wrap {
        position: relative;
        width: 112px;
        height: 112px;
        margin: 0 auto 1rem;
        border-radius: 50%;
        overflow: hidden;
        cursor: pointer;
        background: linear-gradient(135deg, var(--accent-light) 0%, rgba(95, 97, 230, 0.12) 100%);
        border: 3px solid rgba(95, 97, 230, 0.15);
        transition: border-color 0.2s, transform 0.2s;
    }

    .chat-info-group-avatar-wrap:hover {
        border-color: var(--accent);
        transform: scale(1.02);
    }

    .chat-info-group-avatar {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--accent);
        font-weight: 600;
        font-size: 2.25rem;
    }

    .chat-info-group-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .chat-info-group-avatar-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, 0.55);
        color: #fff;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        font-size: 0.75rem;
        font-weight: 500;
        opacity: 0;
        transition: opacity 0.2s;
    }

    .chat-info-group-avatar-overlay svg {
        width: 26px;
        height: 26px;
    }

    .chat-info-group-avatar-wrap:hover .chat-info-group-avatar-overlay {
        opacity: 1;
    }

    .chat-info-group-name {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        letter-spacing: -0.01em;
    }

    .chat-info-section {
        margin-top: 1.5rem;
    }

    .chat-info-section:first-of-type {
        margin-top: 0;
    }

    .chat-info-section-card {
        padding: 1.25rem;
        background: var(--bg-primary);
        border-radius: 12px;
        border: 1px solid var(--border);
    }

    .chat-info-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .chat-info-section-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .chat-info-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        border: 1px solid transparent;
    }

    .chat-info-btn-primary {
        background: var(--accent);
        color: white;
        border-color: var(--accent);
    }

    .chat-info-btn-primary:hover {
        background: var(--accent-hover);
        border-color: var(--accent-hover);
    }

    .chat-info-btn-outline {
        background: transparent;
        color: var(--accent);
        border-color: var(--border);
    }

    .chat-info-btn-outline:hover {
        background: var(--accent-light);
        border-color: var(--accent);
    }

    .chat-info-members-list {
        max-height: 240px;
        overflow-y: auto;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: var(--bg-card);
    }

    .chat-info-member {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.875rem 1rem;
        border-bottom: 1px solid var(--border);
        transition: background 0.15s;
    }

    .chat-info-member:last-child {
        border-bottom: none;
    }

    .chat-info-member-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--accent-light) 0%, rgba(95, 97, 230, 0.1) 100%);
        color: var(--accent);
        font-size: 0.875rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
    }

    .chat-info-member-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .chat-info-member-info {
        flex: 1;
        min-width: 0;
    }

    .chat-info-member-name {
        font-size: 0.9375rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .chat-info-member-email {
        font-size: 0.8125rem;
        color: var(--text-muted);
        margin-top: 0.125rem;
    }

    .chat-info-transfer-hint {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 0 0 1rem 0;
        line-height: 1.5;
    }

    .chat-info-member-clickable {
        cursor: pointer;
    }

    .chat-info-member-clickable:hover {
        background: var(--accent-light);
    }

    .chat-info-transfer-arrow {
        color: var(--text-muted);
        font-size: 1.125rem;
    }

    .chat-info-member-remove {
        padding: 0.375rem 0.75rem;
        font-size: 0.8125rem;
    }

    .chat-info-member-remove:hover {
        color: #dc2626;
        border-color: #dc2626;
        background: #fef2f2;
    }

    .btn-small {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }

    .group-members-search {
        position: relative;
    }

    .group-members-search .search-icon {
        position: absolute;
        left: 0.6rem;
        top: 50%;
        transform: translateY(-50%);
        width: 14px;
        height: 14px;
        color: var(--text-muted);
    }

    .group-members-search .form-input {
        padding: 0.4rem 0.65rem 0.4rem 2rem;
        font-size: 0.8125rem;
    }

    .group-members-list {
        margin-top: 0.5rem;
        max-height: 148px;
        overflow-y: auto;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg-primary);
    }

    .group-member-option {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.35rem 0.6rem;
        cursor: pointer;
        transition: background 0.15s;
        font-size: 0.8125rem;
        line-height: 1.2;
    }

    .group-member-option:hover {
        background: var(--bg-card);
    }

    .group-member-option.selected {
        background: var(--accent-light);
    }

    .group-member-option .avatar-initials {
        width: 24px;
        height: 24px;
        font-size: 0.625rem;
    }

    .group-member-option .avatar-photo {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .group-selected-members {
        margin-top: 0.5rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
    }

    .selected-member-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.15rem 0.4rem;
        background: var(--accent-light);
        color: var(--accent);
        border-radius: 16px;
        font-size: 0.75rem;
    }

    .selected-member-chip button {
        background: none;
        border: none;
        color: inherit;
        cursor: pointer;
        padding: 0;
        display: flex;
    }

    .selected-member-chip button svg {
        width: 14px;
        height: 14px;
    }

    .modal-actions {
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
    }

    @media (max-width: 900px) {
        .msg-page-wrapper { padding: 8px; }
        .msg-page-wrapper, .msg-page { height: auto; min-height: calc(100vh - 64px); overflow: visible; }
        .msg-layout { grid-template-columns: 1fr; height: auto; min-height: calc(100vh - 80px); }
        .msg-sidebar { min-height: calc(100vh - 64px); }
        .msg-sidebar.hidden-mobile { display: none; }
        .msg-main { min-height: calc(100vh - 64px); }
        .msg-main.hidden-mobile { display: none; }
        .msg-back { display: inline-flex; }
        .msg-chat { min-height: calc(100vh - 64px); }
        .msg-chat-actions .msg-icon-btn:nth-child(1),
        .msg-chat-actions .msg-icon-btn:nth-child(2) { display: none; }

        .modal-overlay {
            padding: 0.75rem;
            overflow-y: auto;
        }
        .modal {
            max-height: calc(100vh - 2rem);
            overflow-y: auto;
        }
        .chat-info-modal {
            max-width: 100%;
            border-radius: 12px;
        }
        .chat-info-modal-body { padding: 1.25rem; }
        .chat-info-group-avatar-wrap { width: 96px; height: 96px; }
        .chat-info-group-avatar { font-size: 1.875rem; }
    }
</style>
@endpush

@push('scripts')
<script>
(function() {
    const app = document.getElementById('messagingApp');
    const baseUrl = app.dataset.apiBase;
    const csrf = app.dataset.csrf;
    let currentConversationId = null;
    let currentConversationType = 'direct';
    let conversationReceipts = [];
    let editingMessageId = null;
    let replyingTo = null;
    let companyUsers = [];
    const GROUP_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>';
    const sidebar = document.getElementById('msgSidebar');
    const main = document.getElementById('msgMain');
    const chatEl = document.getElementById('msgChat');
    const emptyEl = document.getElementById('messagingPlaceholder');

    function api(url, options = {}) {
        const opts = {
            headers: {
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                ...(options.headers || {})
            },
            ...options
        };
        if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
            opts.body = JSON.stringify(opts.body);
        }
        return fetch(url, opts);
    }

    function formatListTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        const now = new Date();
        if (d.toDateString() === now.toDateString()) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
        return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }

    function formatStamp(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        const now = new Date();
        const time = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        if (d.toDateString() === now.toDateString()) return 'Today ' + time;
        const yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        if (d.toDateString() === yesterday.toDateString()) return 'Yesterday ' + time;
        const weekAgo = new Date(now);
        weekAgo.setDate(now.getDate() - 6);
        if (d > weekAgo) return d.toLocaleDateString([], { weekday: 'long' }) + ' ' + time;
        if (d.getFullYear() === now.getFullYear()) {
            return d.toLocaleDateString([], { month: 'short', day: 'numeric' }) + ' at ' + time;
        }
        return d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) + ' at ' + time;
    }

    function dayKey(iso) {
        if (!iso) return '';
        return new Date(iso).toDateString();
    }

    function shouldStamp(prevIso, iso) {
        if (!iso) return false;
        if (!prevIso) return true;
        if (dayKey(prevIso) !== dayKey(iso)) return true;
        return (new Date(iso) - new Date(prevIso)) > 45 * 60 * 1000;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // Load conversations (paginated)
    const CONVERSATIONS_PAGE_SIZE = 15;
    let conversationsOffset = 0;
    let conversationsHasMore = false;
    let conversationsSearch = '';
    let loadMoreInProgress = false;

    async function loadConversations(search = '') {
        conversationsOffset = 0;
        conversationsSearch = search;
        const params = new URLSearchParams({ limit: CONVERSATIONS_PAGE_SIZE, offset: 0 });
        if (search) params.set('search', search);
        const res = await api(baseUrl + '/conversations?' + params.toString());
        const json = await res.json();
        if (!json.success) return;
        conversationsHasMore = json.has_more ?? false;
        renderConversations(json.data, true);
    }

    async function loadMoreConversations() {
        if (loadMoreInProgress || !conversationsHasMore) return;
        loadMoreInProgress = true;
        conversationsOffset += CONVERSATIONS_PAGE_SIZE;
        const params = new URLSearchParams({ limit: CONVERSATIONS_PAGE_SIZE, offset: conversationsOffset });
        if (conversationsSearch) params.set('search', conversationsSearch);
        const res = await api(baseUrl + '/conversations?' + params.toString());
        const json = await res.json();
        if (!json.success) {
            conversationsOffset -= CONVERSATIONS_PAGE_SIZE;
        } else {
            conversationsHasMore = json.has_more ?? false;
            renderConversations(json.data, false);
        }
        loadMoreInProgress = false;
    }
    window.loadMoreConversations = loadMoreConversations;

    function createChatItem(c) {
        const item = document.createElement('div');
        item.className = 'msg-thread' + (c.id === currentConversationId ? ' active' : '') + (c.unread_count ? ' unread' : '');
        item.dataset.conversationId = c.id;
        const avatar = c.type === 'group'
            ? (c.avatar_photo
                ? '<div class="msg-avatar group"><img src="' + c.avatar_photo + '" alt=""></div>'
                : '<div class="msg-avatar group">' + GROUP_ICON + '</div>')
            : '<div class="msg-avatar">' + (c.avatar_photo ? '<img src="' + c.avatar_photo + '" alt="">' : escapeHtml(c.avatar_initials || '?')) + '</div>';
        item.innerHTML = avatar + `
            <div class="msg-thread-body">
                <div class="msg-thread-top">
                    <div class="msg-thread-name">${escapeHtml(c.name)}</div>
                    <div class="msg-thread-time">${formatListTime(c.last_message_at)}</div>
                </div>
                <div class="msg-thread-preview">${escapeHtml(c.preview || '')}</div>
            </div>
            ${c.unread_count ? '<span class="msg-badge">' + c.unread_count + '</span>' : ''}
        `;
        item.addEventListener('click', () => selectConversation(c.id));
        return item;
    }

    function renderConversations(conversations, replace = true) {
        const container = document.getElementById('chatsListItems');
        const empty = document.getElementById('chatsListEmpty');
        const loadMoreEl = document.getElementById('chatsLoadMore');
        if (replace) container.innerHTML = '';
        if (conversations.length === 0 && replace) {
            empty.style.display = 'block';
            if (loadMoreEl) loadMoreEl.style.display = 'none';
            return;
        }
        empty.style.display = 'none';
        conversations.forEach(c => container.appendChild(createChatItem(c)));
        if (loadMoreEl) loadMoreEl.style.display = conversationsHasMore ? 'block' : 'none';
    }

    async function selectConversation(id) {
        currentConversationId = id;
        document.querySelectorAll('.msg-thread').forEach(i => {
            i.classList.toggle('active', i.dataset.conversationId == id);
        });
        emptyEl.style.display = 'none';
        chatEl.style.display = 'flex';
        if (window.matchMedia('(max-width: 900px)').matches) {
            sidebar.classList.add('hidden-mobile');
            main.classList.remove('hidden-mobile');
        }
        await loadMessages(id);
    }

    window.goBackToConversationList = function() {
        sidebar.classList.remove('hidden-mobile');
        main.classList.add('hidden-mobile');
    };

    const MESSAGES_PAGE_SIZE = 25;
    let messagesHasMore = false;
    let messagesOldestId = null;
    let loadOlderInProgress = false;

    function stampMarkup(iso) {
        const el = document.createElement('div');
        el.className = 'msg-stamp';
        el.dataset.ts = iso || '';
        el.dataset.day = dayKey(iso);
        el.textContent = formatStamp(iso);
        return el;
    }

    function buildMessageElement(m) {
        const mine = m.is_mine || m.author === 'You';
        const dir = mine ? 'outbound' : 'inbound';
        const iso = m.created_at || '';
        const row = document.createElement('div');
        row.className = 'msg-row ' + dir + (currentConversationType === 'direct' ? ' direct' : '');
        row.dataset.messageId = m.id;
        row.dataset.direction = dir;
        row.dataset.author = m.author || '';
        row.dataset.ts = iso;
        row.dataset.day = dayKey(iso);
        row.dataset.body = m.body || '';
        row.dataset.editedAt = m.edited_at || '';
        row.dataset.hasAttachment = m.attachment_path ? '1' : '0';
        row.dataset.preview = m.body || (m.attachment_type === 'image' ? 'Photo' : (m.attachment_name || 'Attachment') || '');
        row.dataset.seenBy = JSON.stringify(m.seen_by || []);

        let quote = '';
        if (m.reply_to && m.reply_to.id) {
            quote = '<button type="button" class="msg-quote" onclick="window.scrollToRepliedMessage(' + Number(m.reply_to.id) + ', event)">' +
                '<span class="msg-quote-author">' + escapeHtml(m.reply_to.author || '') + '</span>' +
                '<span class="msg-quote-body">' + escapeHtml(m.reply_to.body || '') + '</span>' +
                '</button>';
        }
        let body = quote;
        if (m.body) body += '<div class="msg-bubble-text">' + escapeHtml(m.body) + '</div>';
        if (m.attachment_path) {
            if (m.attachment_type === 'image') {
                body += '<img class="msg-inline-image" src="' + escapeHtml(m.attachment_path) + '" alt="" onclick="window.openMessageImagePreview(this.src, event)">';
            } else {
                body += '<div class="msg-attachment"><svg class="msg-attachment-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg><a href="' + escapeHtml(m.attachment_path) + '" target="_blank" rel="noopener" download class="msg-attachment-name">' + escapeHtml(m.attachment_name || 'File') + '</a></div>';
            }
        }
        const avatarInner = m.author_photo
            ? '<img src="' + escapeHtml(m.author_photo) + '" alt="">'
            : escapeHtml(m.author_initials || '?');
        const editBtn = mine
            ? '<button type="button" class="msg-edit-btn" title="Edit" aria-label="Edit" onclick="window.startEditMessage(' + Number(m.id) + ', event)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg></button>'
            : '';
        const replyBtn = currentConversationType === 'group'
            ? '<button type="button" class="msg-reply-btn" title="Reply" aria-label="Reply" onclick="window.startReplyMessage(' + Number(m.id) + ', event)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg></button>'
            : '';
        const edited = m.edited_at ? '<div class="msg-meta"><span>Edited</span></div>' : '';
        const actions = (replyBtn || editBtn) ? '<div class="msg-actions">' + replyBtn + editBtn + '</div>' : '';
        row.innerHTML =
            '<div class="msg-row-avatar">' + avatarInner + '</div>' +
            '<div class="msg-col">' +
                '<div class="msg-sender">' + escapeHtml(m.author || '') + '</div>' +
                '<div class="msg-bubble-wrap">' + actions + '<div class="msg-bubble ' + dir + '">' + (body || '') + '</div></div>' +
                edited +
            '</div>';
        return row;
    }

    function seenForRow(row) {
        try {
            const fromServer = JSON.parse(row.dataset.seenBy || '[]');
            if (Array.isArray(fromServer)) return fromServer;
        } catch (_) { /* ignore */ }
        return [];
    }

    function applySeenLabels() {
        document.querySelectorAll('.msg-seen').forEach(el => el.remove());
        const rows = [...document.querySelectorAll('#messageGroup .msg-row.outbound')];
        const last = rows[rows.length - 1];
        if (!last) return;
        const seen = seenForRow(last);
        if (!seen.length) return;
        const el = document.createElement('div');
        const isGroup = currentConversationType === 'group';
        el.className = 'msg-seen' + (isGroup ? ' is-clickable' : '');
        if (!isGroup) {
            const when = seen[0].last_read_at
                ? ' · ' + new Date(seen[0].last_read_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                : '';
            el.textContent = 'Seen' + when;
        } else if (seen.length === 1) {
            el.textContent = 'Seen by ' + seen[0].name;
        } else {
            el.textContent = 'Seen by ' + seen.length;
        }
        if (isGroup) {
            el.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleSeenPopover(el, seen);
            });
        }
        last.querySelector('.msg-col').appendChild(el);
    }

    function toggleSeenPopover(anchor, seen) {
        let pop = document.getElementById('msgSeenPopover');
        if (!pop) {
            pop = document.createElement('div');
            pop.id = 'msgSeenPopover';
            pop.className = 'msg-seen-popover';
            document.body.appendChild(pop);
            document.addEventListener('click', function(e) {
                if (!pop.contains(e.target) && !e.target.closest('.msg-seen')) {
                    pop.classList.remove('open');
                }
            });
        }
        if (pop.classList.contains('open')) {
            pop.classList.remove('open');
            return;
        }
        pop.innerHTML = seen.length
            ? seen.map(s => {
                const avatar = s.photo
                    ? '<img src="' + escapeHtml(s.photo) + '" alt="">'
                    : '<span class="msg-seen-initials">' + escapeHtml(s.initials || '?') + '</span>';
                return '<div class="msg-seen-person">' + avatar + '<span>' + escapeHtml(s.name) + '</span></div>';
            }).join('')
            : '<div class="msg-seen-empty">No one has seen this yet</div>';
        const rect = anchor.getBoundingClientRect();
        pop.style.left = Math.max(8, Math.min(rect.right - 200, window.innerWidth - 228)) + 'px';
        pop.style.top = (rect.bottom + 6) + 'px';
        pop.classList.add('open');
    }

    function refreshThreadChrome() {
        const group = document.getElementById('messageGroup');
        const nodes = [...group.querySelectorAll('.msg-row')];
        nodes.forEach((node, i) => {
            const prev = nodes[i - 1];
            const next = nodes[i + 1];
            const samePrev = prev && prev.dataset.direction === node.dataset.direction && prev.dataset.author === node.dataset.author && !shouldStamp(prev.dataset.ts, node.dataset.ts);
            const sameNext = next && next.dataset.direction === node.dataset.direction && next.dataset.author === node.dataset.author && !shouldStamp(node.dataset.ts, next.dataset.ts);
            node.classList.toggle('solo', !samePrev && !sameNext);
            node.classList.toggle('group-start', !samePrev && sameNext);
            node.classList.toggle('group-mid', samePrev && sameNext);
            node.classList.toggle('group-end', samePrev && !sameNext);
            node.classList.toggle('tail', !sameNext);
        });
    }

    function lastRow() {
        const nodes = document.getElementById('messageGroup').querySelectorAll('.msg-row');
        return nodes[nodes.length - 1] || null;
    }

    function appendMessage(m, group) {
        const iso = m.created_at || '';
        const prev = lastRow();
        if (shouldStamp(prev && prev.dataset.ts, iso)) {
            group.appendChild(stampMarkup(iso));
        }
        group.appendChild(buildMessageElement(m));
        refreshThreadChrome();
        applySeenLabels();
    }

    function appendMessagesToGroup(group, messages) {
        const sorted = [...messages].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
        sorted.forEach(m => appendMessage(m, group));
    }

    function prependMessagesToGroup(group, messages) {
        const sorted = [...messages].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
        const firstStamp = group.firstElementChild && group.firstElementChild.classList.contains('msg-stamp') ? group.firstElementChild : null;
        const fragment = document.createDocumentFragment();
        let prevIso = null;
        sorted.forEach(m => {
            const iso = m.created_at || '';
            if (shouldStamp(prevIso, iso)) fragment.appendChild(stampMarkup(iso));
            fragment.appendChild(buildMessageElement(m));
            prevIso = iso;
        });
        group.insertBefore(fragment, group.firstChild);
        if (firstStamp && prevIso && !shouldStamp(prevIso, firstStamp.dataset.ts)) {
            firstStamp.remove();
        }
        refreshThreadChrome();
        applySeenLabels();
    }

    function setHeaderAvatar(conversation) {
        const headerAvatar = document.getElementById('chatHeaderAvatar');
        headerAvatar.classList.toggle('group', conversation.type === 'group' && !conversation.avatar_photo);
        if (conversation.avatar_photo) {
            headerAvatar.innerHTML = '<img src="' + conversation.avatar_photo + '" alt="">';
        } else if (conversation.type === 'group') {
            headerAvatar.innerHTML = GROUP_ICON;
        } else {
            headerAvatar.textContent = conversation.avatar_initials || '?';
        }
    }

    async function loadMessages(conversationId) {
        if (typeof window.cancelEditMessage === 'function') window.cancelEditMessage();
        if (typeof window.cancelReplyMessage === 'function') window.cancelReplyMessage();
        const params = new URLSearchParams({ limit: MESSAGES_PAGE_SIZE });
        const res = await api(baseUrl + '/conversations/' + conversationId + '/messages?' + params.toString());
        const json = await res.json();
        if (!json.success) return;
        const { conversation, messages, has_more, receipts } = json.data;
        conversationReceipts = receipts || [];
        currentConversationType = conversation.type || 'direct';
        messagesHasMore = has_more ?? false;
        messagesOldestId = messages.length > 0 ? messages[0].id : null;
        document.getElementById('chatHeaderName').textContent = conversation.name;
        document.getElementById('chatHeaderStatus').textContent = conversation.type === 'group' ? 'Group' : 'Direct message';
        const deleteBtn = document.getElementById('chatDeleteBtn');
        if (deleteBtn) {
            const canDelete = conversation.type === 'direct' || conversation.is_creator;
            deleteBtn.style.display = canDelete ? '' : 'none';
        }
        setHeaderAvatar(conversation);
        const group = document.getElementById('messageGroup');
        group.innerHTML = '';
        appendMessagesToGroup(group, messages);
        const loadOlderEl = document.getElementById('messagesLoadOlder');
        if (loadOlderEl) loadOlderEl.hidden = !messagesHasMore;
        document.getElementById('messagesArea').scrollTop = document.getElementById('messagesArea').scrollHeight;
        applySeenLabels();
        if (typeof window.updateHeaderMessagingBadge === 'function') window.updateHeaderMessagingBadge();
        if (typeof window.updateSidebarUnreadBadges === 'function') window.updateSidebarUnreadBadges();
    }

    async function loadOlderMessages() {
        if (!currentConversationId || loadOlderInProgress || !messagesHasMore || !messagesOldestId) return;
        loadOlderInProgress = true;
        const loadOlderEl = document.getElementById('messagesLoadOlder');
        if (loadOlderEl) loadOlderEl.hidden = false;
        const params = new URLSearchParams({ limit: MESSAGES_PAGE_SIZE, before_id: messagesOldestId });
        const res = await api(baseUrl + '/conversations/' + currentConversationId + '/messages?' + params.toString());
        const json = await res.json();
        if (!json.success) {
            loadOlderInProgress = false;
            if (loadOlderEl) loadOlderEl.hidden = !messagesHasMore;
            return;
        }
        const { messages, has_more } = json.data;
        messagesHasMore = has_more ?? false;
        if (messages.length > 0) {
            messagesOldestId = messages[0].id;
            const group = document.getElementById('messageGroup');
            const area = document.getElementById('messagesArea');
            const scrollHeightBefore = area.scrollHeight;
            prependMessagesToGroup(group, messages);
            area.scrollTop = area.scrollHeight - scrollHeightBefore;
        }
        if (loadOlderEl) loadOlderEl.hidden = !messagesHasMore;
        loadOlderInProgress = false;
    }
    window.loadOlderMessages = loadOlderMessages;


    // Search
    document.getElementById('conversationSearch').addEventListener('input', debounce(function() {
        loadConversations(this.value);
    }, 300));

    function debounce(fn, ms) {
        let t;
        return function() {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, arguments), ms);
        };
    }

    // Send message
    const messageInput = document.getElementById('messageInput');
    const sendBtn = document.getElementById('sendBtn');
    let pendingAttachment = null;

    function updateSendButtonState() {
        if (editingMessageId) {
            const row = document.querySelector('.msg-row[data-message-id="' + editingMessageId + '"]');
            const hasAttachment = row && row.dataset.hasAttachment === '1';
            sendBtn.disabled = !messageInput.value.trim() && !hasAttachment;
            return;
        }
        const hasText = messageInput.value.trim().length > 0;
        const hasAttachment = pendingAttachment !== null;
        sendBtn.disabled = !hasText && !hasAttachment;
    }

    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 110) + 'px';
        updateSendButtonState();
    });

    function setPendingAttachment(att) {
        if (pendingAttachment?.previewUrl) URL.revokeObjectURL(pendingAttachment.previewUrl);
        pendingAttachment = att;
        const bar = document.getElementById('attachmentPreviewBar');
        if (!att) {
            bar.style.display = 'none';
            bar.innerHTML = '';
        } else {
            bar.style.display = 'flex';
            if (att.type === 'image' && att.previewUrl) {
                bar.innerHTML = `
                    <div class="attachment-preview-chip">
                        <img src="${att.previewUrl}" alt="">
                        <span class="chip-name">${escapeHtml(att.name)}</span>
                        <button type="button" onclick="window.clearPendingAttachment()" aria-label="Remove">✕</button>
                    </div>
                `;
            } else {
                bar.innerHTML = `
                    <div class="attachment-preview-chip">
                        <span class="chip-name">${escapeHtml(att.name)}</span>
                        <button type="button" onclick="window.clearPendingAttachment()" aria-label="Remove">✕</button>
                    </div>
                `;
            }
        }
        updateSendButtonState();
    }

    window.clearPendingAttachment = async function() {
        const path = pendingAttachment?.path;
        if (path) {
            try {
                await fetch(baseUrl + '/attachments/discard', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ path })
                });
            } catch (_) { /* ignore */ }
        }
        setPendingAttachment(null);
    };

    async function uploadFile(file) {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('_token', csrf);
        const res = await fetch(baseUrl + '/attachments', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: fd
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Upload failed');
        return json.data;
    }

    function namedImageFile(file) {
        if (file.name && file.name !== 'blob') return file;
        const rawExt = (file.type.split('/')[1] || 'png').split('+')[0];
        const ext = rawExt === 'jpeg' ? 'jpg' : rawExt;
        return new File([file], 'pasted-image.' + ext, { type: file.type || 'image/png' });
    }

    async function queueChatAttachment(file, asImage) {
        if (!currentConversationId || editingMessageId) return;
        if (asImage && file.type && !file.type.startsWith('image/')) {
            alert('Please choose an image file');
            return;
        }
        const upload = (asImage || (file.type && file.type.startsWith('image/'))) ? namedImageFile(file) : file;
        if (pendingAttachment?.path) {
            await window.clearPendingAttachment();
        }
        try {
            const data = await uploadFile(upload);
            const isImage = data.type === 'image' || asImage;
            const previewUrl = isImage ? URL.createObjectURL(upload) : null;
            setPendingAttachment({
                path: data.path,
                name: data.name,
                type: isImage ? 'image' : data.type,
                previewUrl
            });
            messageInput.focus();
        } catch (err) {
            alert(err.message || 'Upload failed');
        }
    }

    function clipboardImageFile(clipboardData) {
        if (!clipboardData) return null;
        const items = clipboardData.items;
        if (items) {
            for (let i = 0; i < items.length; i++) {
                const item = items[i];
                if (item.kind === 'file' && item.type && item.type.startsWith('image/')) {
                    return item.getAsFile();
                }
            }
        }
        if (clipboardData.files && clipboardData.files.length) {
            const f = clipboardData.files[0];
            if (f && f.type && f.type.startsWith('image/')) return f;
        }
        return null;
    }

    function insertTextAtCursor(textarea, text) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const value = textarea.value;
        textarea.value = value.slice(0, start) + text + value.slice(end);
        textarea.selectionStart = textarea.selectionEnd = start + text.length;
        textarea.dispatchEvent(new Event('input'));
    }

    window.startReplyMessage = function(id, ev) {
        if (ev) ev.stopPropagation();
        if (currentConversationType !== 'group') return;
        const row = document.querySelector('.msg-row[data-message-id="' + id + '"]');
        if (!row) return;
        if (editingMessageId) window.cancelEditMessage();
        replyingTo = {
            id: Number(id),
            author: row.dataset.author || '',
            preview: row.dataset.preview || ''
        };
        document.getElementById('msgReplyAuthor').textContent = replyingTo.author;
        document.getElementById('msgReplyPreview').textContent = replyingTo.preview;
        document.getElementById('msgReplyBanner').hidden = false;
        messageInput.focus();
    };

    window.cancelReplyMessage = function() {
        replyingTo = null;
        const banner = document.getElementById('msgReplyBanner');
        if (banner) banner.hidden = true;
    };

    window.scrollToRepliedMessage = function(id, ev) {
        if (ev) ev.stopPropagation();
        const row = document.querySelector('.msg-row[data-message-id="' + id + '"]');
        if (!row) return;
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        row.classList.add('msg-row-flash');
        setTimeout(() => row.classList.remove('msg-row-flash'), 1400);
    };

    window.openMessageImagePreview = function(src, ev) {
        if (ev) ev.stopPropagation();
        if (!src) return;
        const box = document.getElementById('msgImageLightbox');
        const img = document.getElementById('msgImageLightboxImg');
        img.src = src;
        box.hidden = false;
    };

    window.closeMessageImagePreview = function() {
        const box = document.getElementById('msgImageLightbox');
        const img = document.getElementById('msgImageLightboxImg');
        box.hidden = true;
        img.src = '';
    };

    document.getElementById('msgImageLightbox').addEventListener('click', function(e) {
        if (e.target === this || e.target.id === 'msgImageLightboxClose') {
            window.closeMessageImagePreview();
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        const box = document.getElementById('msgImageLightbox');
        if (box && !box.hidden) {
            e.preventDefault();
            window.closeMessageImagePreview();
        }
    });

    window.startEditMessage = function(id, ev) {
        if (ev) ev.stopPropagation();
        const row = document.querySelector('.msg-row[data-message-id="' + id + '"]');
        if (!row) return;
        if (typeof window.cancelReplyMessage === 'function') window.cancelReplyMessage();
        editingMessageId = id;
        messageInput.value = row.dataset.body || '';
        messageInput.dispatchEvent(new Event('input'));
        document.getElementById('msgEditBanner').hidden = false;
        document.getElementById('messageInputArea').classList.add('editing');
        sendBtn.title = 'Save';
        sendBtn.setAttribute('aria-label', 'Save');
        messageInput.focus();
        updateSendButtonState();
    };

    window.cancelEditMessage = function() {
        editingMessageId = null;
        document.getElementById('msgEditBanner').hidden = true;
        document.getElementById('messageInputArea').classList.remove('editing');
        sendBtn.title = 'Send';
        sendBtn.setAttribute('aria-label', 'Send');
        messageInput.value = '';
        messageInput.style.height = 'auto';
        updateSendButtonState();
    };

    async function saveEditedMessage() {
        if (!editingMessageId || !currentConversationId) return;
        const text = messageInput.value.trim();
        const row = document.querySelector('.msg-row[data-message-id="' + editingMessageId + '"]');
        const hasAttachment = row && row.dataset.hasAttachment === '1';
        if (!text && !hasAttachment) return;
        const res = await api(baseUrl + '/conversations/' + currentConversationId + '/messages/' + editingMessageId + '/update', {
            method: 'POST',
            body: { body: text || null }
        });
        const json = await res.json();
        if (!json.success) {
            alert(json.message || 'Failed to edit message');
            return;
        }
        if (row) row.replaceWith(buildMessageElement(json.data));
        refreshThreadChrome();
        applySeenLabels();
        window.cancelEditMessage();
        loadConversations(document.getElementById('conversationSearch').value);
    }

    async function sendMessage() {
        if (editingMessageId) {
            await saveEditedMessage();
            return;
        }
        const text = messageInput.value.trim();
        if ((!text && !pendingAttachment) || !currentConversationId) return;
        const body = { body: text || null };
        if (pendingAttachment) {
            body.attachment_path = pendingAttachment.path;
            body.attachment_name = pendingAttachment.name;
            body.attachment_type = pendingAttachment.type;
        }
        if (replyingTo && currentConversationType === 'group') {
            body.reply_to_id = replyingTo.id;
        }
        const res = await api(baseUrl + '/conversations/' + currentConversationId + '/messages', {
            method: 'POST',
            body: body
        });
        const json = await res.json();
        if (json.success) {
            messageInput.value = '';
            messageInput.style.height = 'auto';
            setPendingAttachment(null);
            window.cancelReplyMessage();
            updateSendButtonState();
            const m = json.data;
            const group = document.getElementById('messageGroup');
            appendMessage(m, group);
            document.getElementById('messagesArea').scrollTop = document.getElementById('messagesArea').scrollHeight;
            loadConversations(document.getElementById('conversationSearch').value);
        }
    }

    function handleMessageInput(event) {
        if (event.key === 'Escape' && editingMessageId) {
            event.preventDefault();
            window.cancelEditMessage();
            return;
        }
        if (event.key === 'Escape' && replyingTo) {
            event.preventDefault();
            window.cancelReplyMessage();
            return;
        }
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    }
    messageInput.onkeydown = handleMessageInput;
    sendBtn.onclick = sendMessage;
    window.sendMessage = sendMessage;

    // Create Group Modal
    let groupAvatarPath = null;

    window.openCreateGroupModal = async function() {
        document.getElementById('createGroupModal').classList.add('open');
        document.getElementById('groupName').value = '';
        document.querySelectorAll('.group-member-option').forEach(el => el.classList.remove('selected'));
        updateSelectedMembersDisplay();
        window.clearGroupAvatar();
        await loadCompanyUsers('');
    }

    function closeCreateGroupModal() {
        document.getElementById('createGroupModal').classList.remove('open');
        window.clearGroupAvatar();
    }

    window.pickGroupAvatar = function() {
        document.getElementById('groupAvatarInput').click();
    };

    window.clearGroupAvatar = function() {
        groupAvatarPath = null;
        const placeholder = document.getElementById('groupAvatarPlaceholder');
        const img = document.getElementById('groupAvatarImg');
        const removeBtn = document.getElementById('groupAvatarRemove');
        if (placeholder) {
            placeholder.style.display = 'flex';
        }
        if (img) {
            img.src = '';
            img.style.display = 'none';
        }
        if (removeBtn) removeBtn.style.display = 'none';
    };

    document.getElementById('groupAvatarInput').onchange = async function(e) {
        const file = e.target.files?.[0];
        if (!file || !file.type.startsWith('image/')) return;
        e.target.value = '';
        try {
            const data = await uploadFile(file);
            groupAvatarPath = data.path;
            const img = document.getElementById('groupAvatarImg');
            const placeholder = document.getElementById('groupAvatarPlaceholder');
            const removeBtn = document.getElementById('groupAvatarRemove');
            img.src = data.url || ('/media/' + data.path);
            img.style.display = 'block';
            placeholder.style.display = 'none';
            removeBtn.style.display = 'block';
        } catch (err) {
            alert(err.message || 'Failed to upload photo');
        }
    };
    window.closeCreateGroupModal = closeCreateGroupModal;

    async function loadCompanyUsers(search) {
        const url = baseUrl + '/users' + (search ? '?search=' + encodeURIComponent(search) : '');
        const res = await api(url);
        const json = await res.json();
        if (!json.success) return;
        companyUsers = json.data;
        const list = document.getElementById('groupMembersList');
        list.innerHTML = companyUsers.map(u => `
            <div class="group-member-option" data-id="${u.id}" onclick="window.messagingToggleGroupMember(this)">
                ${u.photo ? '<img src="' + u.photo + '" alt="" class="avatar-photo">' : '<div class="avatar-initials">' + u.initials + '</div>'}
                <span>${escapeHtml(u.name)}</span>
            </div>
        `).join('');
    }

    window.messagingToggleGroupMember = function(el) {
        el.classList.toggle('selected');
        updateSelectedMembersDisplay();
    };

    function updateSelectedMembersDisplay() {
        const selected = document.querySelectorAll('.group-member-option.selected');
        const container = document.getElementById('groupSelectedMembers');
        container.innerHTML = '';
        selected.forEach(el => {
            const chip = document.createElement('span');
            chip.className = 'selected-member-chip';
            chip.innerHTML = el.querySelector('span').textContent;
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.setAttribute('aria-label', 'Remove');
            removeBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
            removeBtn.onclick = () => { el.classList.remove('selected'); updateSelectedMembersDisplay(); };
            chip.appendChild(removeBtn);
            container.appendChild(chip);
        });
    }

    document.getElementById('groupMemberSearch').oninput = debounce(function() {
        filterGroupMembers(this.value);
    }, 200);

    function filterGroupMembers(query) {
        loadCompanyUsers(query);
    }

    document.getElementById('createGroupForm').onsubmit = async function(e) {
        e.preventDefault();
        const name = document.getElementById('groupName').value.trim();
        const selected = [...document.querySelectorAll('.group-member-option.selected')].map(el => el.dataset.id);
        if (!name || selected.length === 0) return;
        const body = { type: 'group', name, participant_ids: selected };
        if (groupAvatarPath) body.photo_path = groupAvatarPath;
        const res = await api(baseUrl + '/conversations', {
            method: 'POST',
            body: body
        });
        const json = await res.json();
        if (json.success) {
            closeCreateGroupModal();
            loadConversations();
            selectConversation(json.data.id);
        } else {
            alert(json.message || 'Failed to create group');
        }
    };

    document.getElementById('createGroupModal').addEventListener('click', function(e) {
        if (e.target === this) closeCreateGroupModal();
    });

    // New Chat Modal
    let newChatModal = null;
    window.openNewChatModal = async function() {
        if (!newChatModal) {
            newChatModal = document.createElement('div');
            newChatModal.className = 'modal-overlay';
            newChatModal.id = 'newChatModal';
            newChatModal.innerHTML = `
                <div class="modal create-group-modal">
                    <div class="modal-header">
                        <h3 class="modal-title">New Chat</h3>
                        <button type="button" class="modal-close" onclick="window.messagingCloseNewChat()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Select a team member</label>
                            <div class="group-members-search">
                                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                                <input type="text" class="form-input" placeholder="Search..." id="newChatSearch">
                            </div>
                            <div class="group-members-list" id="newChatMembersList"></div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(newChatModal);
            document.getElementById('newChatSearch').oninput = debounce(function() { loadNewChatUsers(this.value); }, 200);
        }
        newChatModal.classList.add('open');
        loadNewChatUsers('');
    }

    window.messagingCloseNewChat = function() {
        document.getElementById('newChatModal').classList.remove('open');
    };

    async function loadNewChatUsers(search) {
        const url = baseUrl + '/users' + (search ? '?search=' + encodeURIComponent(search) : '');
        const res = await api(url);
        const json = await res.json();
        if (!json.success) return;
        const list = document.getElementById('newChatMembersList');
        list.innerHTML = json.data.map(u => `
            <div class="group-member-option" data-id="${u.id}" data-name="${escapeHtml(u.name)}" onclick="window.messagingStartDirectChat(this)">
                ${u.photo ? '<img src="' + u.photo + '" alt="" class="avatar-photo">' : '<div class="avatar-initials">' + u.initials + '</div>'}
                <span>${escapeHtml(u.name)}</span>
            </div>
        `).join('');
    }

    window.messagingStartDirectChat = async function(el) {
        const userId = el.dataset.id;
        const res = await api(baseUrl + '/conversations', {
            method: 'POST',
            body: { type: 'direct', participant_ids: [parseInt(userId)] }
        });
        const json = await res.json();
        if (json.success) {
            window.messagingCloseNewChat();
            loadConversations();
            selectConversation(json.data.id);
        }
    };

    document.addEventListener('click', function(e) {
        if (e.target.id === 'newChatModal') window.messagingCloseNewChat();
    });

    window.deleteChat = async function() {
        if (!currentConversationId) return;
        if (!confirm('Are you sure you want to delete this chat? All messages will be permanently removed.')) return;
        const res = await api(baseUrl + '/conversations/' + currentConversationId, { method: 'DELETE' });
        const json = await res.json();
        if (json.success) {
            currentConversationId = null;
            emptyEl.style.display = 'flex';
            chatEl.style.display = 'none';
            document.querySelectorAll('.msg-thread').forEach(i => i.classList.remove('active'));
            loadConversations(document.getElementById('conversationSearch').value);
        } else {
            alert(json.message || 'Failed to delete chat');
        }
    };

    window.startVideoCall = function() { alert('Video call - Future phase'); };
    window.startAudioCall = function() { alert('Audio call - Future phase'); };

    let chatInfoData = null;

    window.showChatInfo = async function() {
        if (!currentConversationId) return;
        const res = await api(baseUrl + '/conversations/' + currentConversationId);
        const json = await res.json();
        if (!json.success) {
            alert(json.message || 'Failed to load chat info');
            return;
        }
        chatInfoData = json.data;
        document.getElementById('chatInfoDirect').style.display = 'none';
        document.getElementById('chatInfoGroup').style.display = 'none';
        if (chatInfoData.type === 'direct') {
            document.getElementById('chatInfoTitle').textContent = 'Chat Info';
            document.getElementById('chatInfoDirect').style.display = 'block';
            const u = chatInfoData.user;
            const avatarEl = document.getElementById('chatInfoUserAvatar');
            avatarEl.innerHTML = u?.photo ? '<img src="' + u.photo + '" alt="">' : '<span>' + (u?.initials || '?') + '</span>';
            document.getElementById('chatInfoUserName').textContent = u?.name || 'Unknown';
            document.getElementById('chatInfoUserEmail').textContent = u?.email || '';
            const phoneEl = document.getElementById('chatInfoUserPhone');
            if (u?.phone) {
                phoneEl.textContent = u.phone;
                phoneEl.style.display = 'block';
            } else phoneEl.style.display = 'none';
        } else {
            document.getElementById('chatInfoTitle').textContent = 'Group Info';
            document.getElementById('chatInfoGroup').style.display = 'block';
            const isCreator = chatInfoData.is_creator;
            const avatarEl = document.getElementById('chatInfoGroupAvatar');
            avatarEl.innerHTML = chatInfoData.photo ? '<img src="' + chatInfoData.photo + '" alt="">' : '<span>' + (chatInfoData.name ? chatInfoData.name.slice(0, 2).toUpperCase() : '?') + '</span>';
            document.getElementById('chatInfoGroupName').textContent = chatInfoData.name || 'Group';
            document.getElementById('chatInfoAddMemberBtn').style.display = isCreator ? 'inline-block' : 'none';
            document.getElementById('chatInfoTransferSection').style.display = isCreator ? 'block' : 'none';
            const list = document.getElementById('chatInfoMembersList');
            list.innerHTML = chatInfoData.members.map(m => `
                <div class="chat-info-member" data-user-id="${m.id}">
                    <div class="chat-info-member-avatar">${m.photo ? '<img src="' + m.photo + '" alt="">' : m.initials}</div>
                    <div class="chat-info-member-info">
                        <div class="chat-info-member-name">${escapeHtml(m.name)}${m.is_me ? ' (You)' : ''}${m.is_creator ? ' • Creator' : ''}</div>
                        <div class="chat-info-member-email">${escapeHtml(m.email || '')}</div>
                    </div>
                    ${!m.is_me && isCreator ? '<button type="button" class="chat-info-btn chat-info-btn-outline chat-info-member-remove" onclick="window.removeMemberFromGroup(' + m.id + ')" title="Remove">Remove</button>' : ''}
                </div>
            `).join('');
        }
        document.getElementById('chatInfoModal').classList.add('open');
    };

    window.closeChatInfo = function() {
        document.getElementById('chatInfoModal').classList.remove('open');
        chatInfoData = null;
    };

    window.pickChatInfoGroupPhoto = function() {
        document.getElementById('chatInfoGroupPhotoInput').click();
    };

    document.getElementById('chatInfoGroupPhotoInput').onchange = async function(e) {
        const file = e.target.files?.[0];
        if (!file || !file.type.startsWith('image/')) return;
        e.target.value = '';
        try {
            const data = await uploadFile(file);
            const res = await api(baseUrl + '/conversations/' + currentConversationId + '/update', {
                method: 'POST',
                body: { photo_path: data.path }
            });
            const json = await res.json();
            if (json.success) {
                chatInfoData.photo = data.url || ('/media/' + data.path);
                const avatarEl = document.getElementById('chatInfoGroupAvatar');
                avatarEl.innerHTML = '<img src="' + chatInfoData.photo + '" alt="">';
                loadConversations(document.getElementById('conversationSearch').value);
                const headerAvatar = document.getElementById('chatHeaderAvatar');
                if (headerAvatar) {
                    headerAvatar.classList.remove('group');
                    headerAvatar.innerHTML = '<img src="' + chatInfoData.photo + '" alt="">';
                }
            } else alert(json.message || 'Failed to update photo');
        } catch (err) { alert(err.message || 'Failed'); }
    };

    let addMemberToListUsers = [];

    window.openAddMemberToGroup = async function() {
        document.getElementById('addMemberToGroupModal').classList.add('open');
        const excludeIds = (chatInfoData?.members || []).map(m => m.id);
        const params = new URLSearchParams();
        excludeIds.forEach(id => params.append('exclude[]', id));
        const url = baseUrl + '/users' + (excludeIds.length ? '?' + params.toString() : '');
        const res = await api(url);
        const json = await res.json();
        if (json.success) {
            addMemberToListUsers = json.data;
            renderAddMemberList('');
        }
    };

    window.closeAddMemberToGroup = function() {
        document.getElementById('addMemberToGroupModal').classList.remove('open');
    };

    function filterAddMemberList(query) {
        renderAddMemberList(query);
    }

    function renderAddMemberList(search) {
        const q = (search || '').toLowerCase();
        const filtered = addMemberToListUsers.filter(u =>
            !q || (u.name || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q)
        );
        const list = document.getElementById('addMemberToList');
        list.innerHTML = filtered.map(u => `
            <div class="group-member-option" data-id="${u.id}" onclick="window.addMemberToGroup(${u.id})">
                ${u.photo ? '<img src="' + u.photo + '" alt="" class="avatar-photo">' : '<div class="avatar-initials">' + u.initials + '</div>'}
                <span>${escapeHtml(u.name)}</span>
            </div>
        `).join('');
    }

    window.addMemberToGroup = async function(userId) {
        if (!currentConversationId) return;
        const res = await api(baseUrl + '/conversations/' + currentConversationId + '/members', {
            method: 'POST',
            body: { user_id: userId }
        });
        const json = await res.json();
        if (json.success) {
            window.closeAddMemberToGroup();
            window.closeChatInfo();
            await window.showChatInfo();
            loadConversations(document.getElementById('conversationSearch').value);
        } else alert(json.message || 'Failed to add member');
    };

    window.removeMemberFromGroup = async function(userId) {
        if (!currentConversationId) return;
        if (!confirm('Remove this member from the group?')) return;
        const res = await api(baseUrl + '/conversations/' + currentConversationId + '/members/' + userId + '/remove', { method: 'POST' });
        const json = await res.json();
        if (json.success) {
            chatInfoData.members = chatInfoData.members.filter(m => m.id !== userId);
            const row = document.querySelector('.chat-info-member[data-user-id="' + userId + '"]');
            if (row) row.remove();
            loadConversations(document.getElementById('conversationSearch').value);
        } else alert(json.message || 'Failed to remove member');
    };

    window.openTransferOwnershipModal = function() {
        document.getElementById('transferOwnershipModal').classList.add('open');
        const list = document.getElementById('transferOwnershipList');
        const members = (chatInfoData?.members || []).filter(m => !m.is_me);
        list.innerHTML = members.map(m => `
            <div class="chat-info-member chat-info-member-clickable" data-user-id="${m.id}" onclick="window.transferOwnershipToUser(${m.id})">
                <div class="chat-info-member-avatar">${m.photo ? '<img src="' + m.photo + '" alt="">' : m.initials}</div>
                <div class="chat-info-member-info">
                    <div class="chat-info-member-name">${escapeHtml(m.name)}${m.is_creator ? ' • Creator' : ''}</div>
                    <div class="chat-info-member-email">${escapeHtml(m.email || '')}</div>
                </div>
                <span class="chat-info-transfer-arrow">→</span>
            </div>
        `).join('') || '<p class="chat-info-transfer-hint">No other members to transfer to.</p>';
    };

    window.closeTransferOwnershipModal = function() {
        document.getElementById('transferOwnershipModal').classList.remove('open');
    };

    window.transferOwnershipToUser = async function(userId) {
        if (!currentConversationId) return;
        if (!confirm('Transfer ownership to this member? You will no longer be able to add or remove members.')) return;
        const res = await api(baseUrl + '/conversations/' + currentConversationId + '/transfer-ownership', {
            method: 'POST',
            body: { user_id: userId }
        });
        const json = await res.json();
        if (json.success) {
            window.closeTransferOwnershipModal();
            window.closeChatInfo();
            loadConversations(document.getElementById('conversationSearch').value);
        } else {
            alert(json.message || 'Failed to transfer ownership');
        }
    };

    document.getElementById('chatInfoModal').addEventListener('click', function(e) {
        if (e.target === this) window.closeChatInfo();
    });
    document.getElementById('addMemberToGroupModal').addEventListener('click', function(e) {
        if (e.target === this) window.closeAddMemberToGroup();
    });
    document.getElementById('transferOwnershipModal').addEventListener('click', function(e) {
        if (e.target === this) window.closeTransferOwnershipModal();
    });
    window.attachFile = function() {
        if (!currentConversationId) return;
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '';
        input.onchange = async function(e) {
            const file = e.target.files?.[0];
            if (!file) return;
            await queueChatAttachment(file, false);
        };
        input.click();
    };

    window.attachImage = function() {
        if (!currentConversationId) return;
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = async function(e) {
            const file = e.target.files?.[0];
            if (!file) return;
            await queueChatAttachment(file, true);
        };
        input.click();
    };

    document.getElementById('msgChat').addEventListener('paste', function(e) {
        if (editingMessageId) return;
        const imageFile = clipboardImageFile(e.clipboardData);
        if (!imageFile) return;
        e.preventDefault();
        const text = e.clipboardData.getData('text/plain');
        if (text) insertTextAtCursor(messageInput, text);
        queueChatAttachment(imageFile, true);
    });

    const emojiList = ['😀','😃','😄','😁','😅','😂','🤣','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','👍','👎','👌','✌️','🤞','🤟','🤘','🤙','👋','🤚','🖐️','✋','🖖','👏','🙌','🤝','🙏','✍️','💪','❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','🔥','⭐','🌟','✨','💫','🎉','🎊','🙌','👏','🙏'];
    const emojiPicker = document.getElementById('emojiPickerPopover');
    const emojiGrid = document.getElementById('emojiPickerGrid');
    emojiGrid.innerHTML = emojiList.map(e => '<button type="button" class="emoji-picker-btn" data-emoji="' + e + '">' + e + '</button>').join('');
    emojiGrid.querySelectorAll('button').forEach(btn => {
        btn.onclick = () => {
            const emoji = btn.dataset.emoji;
            const start = messageInput.selectionStart;
            const end = messageInput.selectionEnd;
            const text = messageInput.value;
            messageInput.value = text.slice(0, start) + emoji + text.slice(end);
            messageInput.selectionStart = messageInput.selectionEnd = start + emoji.length;
            messageInput.focus();
        };
    });

    window.showEmojiPicker = function() {
        emojiPicker.classList.toggle('open');
        document.addEventListener('click', function closeEmojiPicker(e) {
            if (!emojiPicker.contains(e.target) && !e.target.closest('[onclick*="showEmojiPicker"]')) {
                emojiPicker.classList.remove('open');
                document.removeEventListener('click', closeEmojiPicker);
            }
        });
    };
    function downloadFile(name) { /* Handled via link */ }

    document.getElementById('msgRefreshBtn')?.addEventListener('click', () => {
        loadConversations(document.getElementById('conversationSearch').value);
    });
    document.getElementById('chatsList').addEventListener('scroll', () => {
        if (loadMoreInProgress || !conversationsHasMore) return;
        const list = document.getElementById('chatsList');
        const remaining = list.scrollHeight - list.scrollTop - list.clientHeight;
        if (remaining < 120) loadMoreConversations();
    });
    document.getElementById('messagesArea').addEventListener('scroll', () => {
        if (document.getElementById('messagesArea').scrollTop < 48) loadOlderMessages();
    });

    // Init
    async function refreshOpenConversation() {
        if (!currentConversationId) return;
        const params = new URLSearchParams({ limit: MESSAGES_PAGE_SIZE });
        const res = await api(baseUrl + '/conversations/' + currentConversationId + '/messages?' + params.toString());
        const json = await res.json();
        if (!json.success) return;
        conversationReceipts = json.data.receipts || [];
        const group = document.getElementById('messageGroup');
        const area = document.getElementById('messagesArea');
        const nearBottom = area.scrollHeight - area.scrollTop - area.clientHeight < 80;
        let appended = false;
        (json.data.messages || []).forEach(m => {
            const existing = group.querySelector('.msg-row[data-message-id="' + m.id + '"]');
            if (existing) {
                existing.dataset.seenBy = JSON.stringify(m.seen_by || []);
                if (existing.dataset.body !== (m.body || '') || existing.dataset.editedAt !== (m.edited_at || '')) {
                    existing.replaceWith(buildMessageElement(m));
                }
            } else {
                appendMessage(m, group);
                appended = true;
            }
        });
        refreshThreadChrome();
        applySeenLabels();
        if (appended && nearBottom) {
            area.scrollTop = area.scrollHeight;
        }
    }

    loadConversations();

    // Poll for new chats, unread, edits, and seen-by updates
    setInterval(function() {
        if (document.visibilityState === 'visible') {
            loadConversations(document.getElementById('conversationSearch').value);
            refreshOpenConversation();
        }
    }, 15000);
})();
</script>
@endpush

