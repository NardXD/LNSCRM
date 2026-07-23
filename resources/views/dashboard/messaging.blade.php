@extends('layouts.app')

@section('title', 'Messaging System')

@section('content')
    <div class="messaging-page-wrapper" id="messagingApp" data-api-base="{{ url('api/messaging') }}" data-csrf="{{ csrf_token() }}">
    <div class="messaging-container">
        <div class="messaging-layout">
            <!-- Sidebar -->
            <div class="messaging-sidebar">
                <div class="sidebar-header">
                    <h2 class="sidebar-title">Messages</h2>
                    <div class="sidebar-header-actions">
                        <button class="icon-btn" onclick="window.openCreateGroupModal()" title="Create Group">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                <line x1="12" y1="11" x2="12" y2="17"/>
                                <line x1="9" y1="14" x2="15" y2="14"/>
                            </svg>
                        </button>
                        <button class="icon-btn" onclick="window.openNewChatModal()" title="New Chat">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Search -->
                <div class="sidebar-search">
                    <div class="search-input-wrap">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                        <input type="text" class="search-input" placeholder="Search conversations..." id="conversationSearch">
                    </div>
                </div>

                <!-- Chats List -->
                <div class="chats-list active" id="chatsList">
                    <div class="chats-list-empty" id="chatsListEmpty" style="display: none;">
                        <p class="chats-empty-text">No conversations yet. Start a new chat or create a group.</p>
                    </div>
                    <div id="chatsListItems"></div>
                    <div class="chats-load-more" id="chatsLoadMore" style="display: none;">
                        <button type="button" class="chats-see-more-btn" id="chatsSeeMoreBtn" onclick="window.loadMoreConversations()">See more</button>
                    </div>
                </div>
            </div>

            <!-- Main Chat Area -->
            <div class="messaging-main">
                <!-- No conversation selected -->
                <div class="messaging-placeholder" id="messagingPlaceholder">
                    <div class="placeholder-content">
                        <svg class="placeholder-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        <h3>Select a conversation</h3>
                        <p>Choose a chat from the sidebar or start a new one.</p>
                    </div>
                </div>
                <!-- Chat Header (hidden until conversation selected) -->
                <div class="chat-header" id="chatHeader" style="display: none;">
                    <button type="button" class="chat-back-btn" id="chatBackBtn" onclick="window.goBackToConversationList()" aria-label="Back to conversations">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"/>
                            <polyline points="12 19 5 12 12 5"/>
                        </svg>
                    </button>
                    <div class="chat-header-left">
                        <div class="chat-header-avatar" id="chatHeaderAvatar">
                            <div class="avatar-initials" id="chatHeaderInitials"></div>
                        </div>
                        <div class="chat-header-info">
                            <h3 class="chat-header-name" id="chatHeaderName"></h3>
                            <span class="chat-header-status" id="chatHeaderStatus">Conversation</span>
                        </div>
                    </div>
                    <div class="chat-header-actions">
                        <button class="icon-btn" onclick="window.startVideoCall()" title="Video Call">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M23 7l-7 5 7 5V7z"/>
                                <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                            </svg>
                        </button>
                        <button class="icon-btn" onclick="window.startAudioCall()" title="Audio Call">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                            </svg>
                        </button>
                        <button class="icon-btn" onclick="window.showChatInfo()" title="Chat Info">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="16" x2="12" y2="12"/>
                                <line x1="12" y1="8" x2="12.01" y2="8"/>
                            </svg>
                        </button>
                        <button class="icon-btn icon-btn-danger" id="chatDeleteBtn" onclick="window.deleteChat()" title="Delete Chat" style="display: none;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                <line x1="10" y1="11" x2="10" y2="17"/>
                                <line x1="14" y1="11" x2="14" y2="17"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Messages Area (hidden until conversation selected) -->
                <div class="messages-area" id="messagesArea" style="display: none;">
                    <div class="messages-load-older" id="messagesLoadOlder" style="display: none;">
                        <button type="button" class="messages-see-more-btn" id="messagesSeeMoreBtn" onclick="window.loadOlderMessages()">Load older messages</button>
                    </div>
                    <div class="message-group" id="messageGroup"></div>
                </div>

                <!-- Message Input (hidden until conversation selected) -->
                <div class="message-input-area" id="messageInputArea" style="display: none;">
                    <div class="message-input-toolbar">
                        <button class="toolbar-btn" onclick="window.attachFile()" title="Attach File">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                            </svg>
                        </button>
                        <button class="toolbar-btn" onclick="window.attachImage()" title="Attach Image">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                <polyline points="21 15 16 10 5 21"/>
                            </svg>
                        </button>
                        <button class="toolbar-btn" onclick="window.showEmojiPicker()" title="Emoji">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                                <line x1="9" y1="9" x2="9.01" y2="9"/>
                                <line x1="15" y1="9" x2="15.01" y2="9"/>
                            </svg>
                        </button>
                    </div>
                    <div class="attachment-preview-bar" id="attachmentPreviewBar" style="display: none;"></div>
                    <div class="emoji-picker-popover" id="emojiPickerPopover">
                        <div class="emoji-picker-grid" id="emojiPickerGrid"></div>
                    </div>
                    <div class="message-input-wrapper">
                        <textarea 
                            class="message-input" 
                            id="messageInput" 
                            placeholder="Type a message..."
                            rows="1"
                            onkeydown="handleMessageInput(event)"
                        ></textarea>
                        <button class="send-btn" onclick="window.sendMessage()" id="sendBtn" disabled>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="22" y1="2" x2="11" y2="13"/>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
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
                <div class="group-members-list" id="addMemberToList" style="max-height: 240px;"></div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    /* Full-page messaging: break out of content padding */
    .messaging-page-wrapper {
        margin: -2rem -2rem -2rem -2rem;
        min-height: calc(100vh - 64px);
    }

    @media (max-width: 1024px) {
        .messaging-page-wrapper {
            margin: -1.5rem -1.5rem -1.5rem -1.5rem;
        }
    }

    @media (max-width: 768px) {
        .messaging-page-wrapper {
            margin: -1rem -1rem -1rem -1rem;
        }
    }

    @media (max-width: 480px) {
        .messaging-page-wrapper {
            margin: -0.75rem -0.75rem -0.75rem -0.75rem;
        }
    }

    .messaging-container {
        height: calc(100vh - 64px);
        min-height: 300px;
        display: flex;
        flex-direction: column;
        padding-top: 1rem;
        padding-bottom: 2rem;
    }

    .messaging-layout {
        display: flex;
        height: 100%;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
    }

    /* Sidebar */
    .messaging-sidebar {
        width: 320px;
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        background: var(--bg-primary);
    }

    .sidebar-header {
        padding: 1.25rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid var(--border);
    }

    .sidebar-header-actions {
        display: flex;
        gap: 0.5rem;
    }

    .sidebar-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .icon-btn {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: none;
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        -webkit-tap-highlight-color: transparent;
    }

    .icon-btn:hover {
        background: var(--bg-card);
        border-color: var(--accent);
        color: var(--accent);
    }

    .icon-btn svg {
        width: 18px;
        height: 18px;
    }

    .icon-btn-danger {
        color: var(--text-muted);
    }

    .icon-btn-danger:hover {
        border-color: #ef4444;
        color: #ef4444;
        background: #fef2f2;
    }

    .icon-btn-small {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        -webkit-tap-highlight-color: transparent;
    }

    .icon-btn-small:hover {
        color: var(--accent);
    }

    .icon-btn-small svg {
        width: 14px;
        height: 14px;
    }

    .sidebar-search {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border);
    }

    .sidebar-search .search-input-wrap {
        position: relative;
        display: block;
    }

    .sidebar-search .search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        width: 18px;
        height: 18px;
        pointer-events: none;
        flex-shrink: 0;
        z-index: 1;
    }

    .sidebar-search .search-input {
        width: 100%;
        padding: 0.625rem 0.75rem 0.625rem 2.5rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        transition: all 0.15s;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .chats-list {
        flex: 1;
        overflow-y: auto;
        display: none;
    }

    .chats-list.active {
        display: block;
    }

    .chat-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.875rem 1.25rem;
        cursor: pointer;
        transition: all 0.15s;
        border-bottom: 1px solid var(--border);
        position: relative;
    }

    .chat-item:hover {
        background: var(--bg-card);
    }

    .chat-item.active {
        background: var(--accent-light);
        border-left: 3px solid var(--accent);
    }

    .chat-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: var(--accent);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.875rem;
        flex-shrink: 0;
        position: relative;
        overflow: hidden;
    }

    .chat-avatar .avatar-photo,
    .avatar-photo {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .chat-avatar.group {
        background: var(--accent-light);
        color: var(--accent);
    }

    .chat-avatar.group svg {
        width: 24px;
        height: 24px;
    }

    .online-indicator {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 12px;
        height: 12px;
        background: #10b981;
        border: 2px solid var(--bg-primary);
        border-radius: 50%;
    }

    .chat-info {
        flex: 1;
        min-width: 0;
    }

    .chat-header-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.25rem;
    }

    .chat-name {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.875rem;
    }

    .chat-time {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .chat-preview {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .unread-badge {
        background: var(--accent);
        color: white;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 0.125rem 0.5rem;
        border-radius: 12px;
        min-width: 20px;
        text-align: center;
    }

    .chats-list-empty {
        padding: 2rem 1.25rem;
        text-align: center;
    }

    .chats-empty-text {
        font-size: 0.875rem;
        color: var(--text-muted);
    }

    .chats-load-more {
        padding: 0.75rem 1.25rem;
        text-align: center;
        border-top: 1px solid var(--border);
    }

    .chats-see-more-btn {
        background: none;
        border: 1px solid var(--border);
        color: var(--text-secondary);
        font-size: 0.8125rem;
        padding: 0.375rem 1rem;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s;
    }

    .chats-see-more-btn:hover:not(:disabled) {
        background: var(--bg-primary);
        color: var(--text-primary);
        border-color: var(--text-muted);
    }

    .chats-see-more-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* Main Chat Area */
    .messaging-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: var(--bg-card);
    }

    .messaging-placeholder {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
    }

    .placeholder-content {
        text-align: center;
    }

    .placeholder-icon {
        width: 64px;
        height: 64px;
        color: var(--text-muted);
        margin-bottom: 1rem;
    }

    .placeholder-content h3 {
        font-size: 1.125rem;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .placeholder-content p {
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .chat-header {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .chat-header-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .chat-header-avatar {
        position: relative;
    }

    .chat-header-avatar .avatar-initials {
        width: 40px;
        height: 40px;
    }

    .chat-header-avatar .avatar-photo,
    .chat-header-avatar .chat-header-avatar-img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
    }

    .chat-header-avatar-group {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--accent-light);
        color: var(--accent);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .chat-header-avatar-group svg {
        width: 22px;
        height: 22px;
    }

    .chat-header-info {
        display: flex;
        flex-direction: column;
    }

    .chat-header-name {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .chat-header-status {
        font-size: 0.8125rem;
        color: var(--text-secondary);
    }

    .chat-header-actions {
        display: flex;
        gap: 0.5rem;
    }

    /* Messages Area */
    .messages-area {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .messages-load-older {
        padding: 0.75rem 0;
        text-align: center;
        flex-shrink: 0;
    }

    .messages-see-more-btn {
        background: none;
        border: 1px solid var(--border);
        color: var(--text-secondary);
        font-size: 0.8125rem;
        padding: 0.375rem 1rem;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s;
    }

    .messages-see-more-btn:hover:not(:disabled) {
        background: var(--bg-primary);
        color: var(--text-primary);
        border-color: var(--text-muted);
    }

    .messages-see-more-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .message-group {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .message-date {
        text-align: center;
        font-size: 0.75rem;
        color: var(--text-muted);
        padding: 0.5rem 0;
        position: sticky;
        top: 0;
        background: var(--bg-card);
        z-index: 1;
    }

    .message {
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
    }

    .message--mine {
        flex-direction: row-reverse;
        align-self: flex-end;
    }

    .message--mine .message-content {
        align-items: flex-end;
    }

    .message--mine .message-header {
        flex-direction: row-reverse;
    }

    .message--mine .message-text,
    .message--mine .message-attachment .attachment-preview {
        background: var(--accent-light);
        border-color: transparent;
    }

    .message--mine .message-content {
        max-width: 75%;
    }

    .message--theirs .message-content {
        max-width: 75%;
    }

    .message-avatar {
        flex-shrink: 0;
    }

    .message-avatar .avatar-initials {
        width: 36px;
        height: 36px;
        font-size: 0.8125rem;
    }

    .message-avatar .avatar-photo {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
    }

    .message-content {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }

    .message-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.25rem;
    }

    .message-author {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.875rem;
    }

    .message-time {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .message-text {
        color: var(--text-primary);
        font-size: 0.875rem;
        line-height: 1.5;
        word-wrap: break-word;
    }

    .message-attachment {
        margin-top: 0.5rem;
    }

    .attachment-preview {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 8px;
        max-width: 400px;
    }

    .attachment-icon {
        width: 40px;
        height: 40px;
        color: var(--accent);
        flex-shrink: 0;
    }

    .attachment-info {
        flex: 1;
        min-width: 0;
    }

    .attachment-name {
        font-weight: 500;
        color: var(--text-primary);
        font-size: 0.875rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .attachment-size {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .attachment-download {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--accent);
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s;
        flex-shrink: 0;
    }

    .attachment-download:hover {
        background: var(--accent-hover);
    }

    .attachment-download svg {
        width: 16px;
        height: 16px;
    }

    /* Message Input */
    .message-input-area {
        position: relative;
        border-top: 1px solid var(--border);
        padding: 1rem 1.5rem;
    }

    .message-input-toolbar {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }

    .attachment-preview-bar {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 0;
        flex-wrap: wrap;
    }

    .attachment-preview-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.375rem 0.75rem;
        background: var(--accent-light);
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.8125rem;
    }

    .attachment-preview-chip img {
        max-width: 48px;
        max-height: 48px;
        border-radius: 4px;
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
        color: var(--text-muted);
        cursor: pointer;
        padding: 0.25rem;
        display: flex;
    }

    .attachment-preview-chip button:hover {
        color: var(--text-primary);
    }

    .emoji-picker-popover {
        position: absolute;
        bottom: 100%;
        left: 0;
        margin-bottom: 0.5rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        padding: 0.75rem;
        display: none;
        z-index: 100;
        max-height: 200px;
        overflow-y: auto;
    }

    .emoji-picker-popover.open {
        display: block;
    }

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
        transition: background 0.15s;
    }

    .emoji-picker-btn:hover {
        background: var(--bg-primary);
    }

    .toolbar-btn {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: none;
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        -webkit-tap-highlight-color: transparent;
    }

    .toolbar-btn:hover {
        background: var(--bg-primary);
        border-color: var(--accent);
        color: var(--accent);
    }

    .toolbar-btn svg {
        width: 18px;
        height: 18px;
    }

    .message-input-wrapper {
        display: flex;
        align-items: flex-end;
        gap: 0.75rem;
        background: var(--bg-primary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.75rem;
    }

    .message-input {
        flex: 1;
        border: none;
        background: transparent;
        color: var(--text-primary);
        font-size: 0.875rem;
        font-family: inherit;
        resize: none;
        max-height: 120px;
        overflow-y: auto;
        outline: none;
    }

    .message-input::placeholder {
        color: var(--text-muted);
    }

    .send-btn {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--accent);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.15s;
        flex-shrink: 0;
        -webkit-tap-highlight-color: transparent;
    }

    .send-btn:hover:not(:disabled) {
        background: var(--accent-hover);
    }

    .send-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .send-btn svg {
        width: 18px;
        height: 18px;
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
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        width: 18px;
        height: 18px;
        color: var(--text-muted);
    }

    .group-members-search .form-input {
        padding-left: 2.5rem;
    }

    .group-members-list {
        margin-top: 0.75rem;
        max-height: 180px;
        overflow-y: auto;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg-primary);
    }

    .group-member-option {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        cursor: pointer;
        transition: background 0.15s;
    }

    .group-member-option:hover {
        background: var(--bg-card);
    }

    .group-member-option.selected {
        background: var(--accent-light);
    }

    .group-member-option .avatar-initials {
        width: 36px;
        height: 36px;
        font-size: 0.8125rem;
    }

    .group-member-option .avatar-photo {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .group-selected-members {
        margin-top: 0.75rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .selected-member-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.25rem 0.5rem;
        background: var(--accent-light);
        color: var(--accent);
        border-radius: 20px;
        font-size: 0.8125rem;
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

    /* Mobile: back button (hidden on desktop) */
    .chat-back-btn {
        display: none;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .messaging-container {
            min-height: 280px;
            padding-bottom: 1rem;
        }

        .messaging-layout {
            position: relative;
        }

        .messaging-sidebar {
            width: 100%;
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            z-index: 100;
            transform: translateX(0);
            transition: transform 0.3s ease;
        }

        .messaging-page-wrapper.mobile-chat-open .messaging-sidebar {
            transform: translateX(-100%);
        }

        .messaging-main {
            width: 100%;
        }

        .chat-back-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            min-width: 40px;
            background: none;
            border: none;
            color: var(--text-primary);
            cursor: pointer;
            margin-right: 0.5rem;
            border-radius: 8px;
            -webkit-tap-highlight-color: transparent;
        }

        .chat-back-btn:hover {
            background: var(--bg-primary);
        }

        .chat-back-btn svg {
            width: 22px;
            height: 22px;
        }

        .chat-header {
            padding: 0.75rem 1rem;
            flex-wrap: nowrap;
            gap: 0.5rem;
        }

        .chat-header-left {
            flex: 1;
            min-width: 0;
        }

        .chat-header-name {
            font-size: 0.9375rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-header-actions {
            flex-shrink: 0;
        }

        .chat-header-actions .icon-btn {
            width: 36px;
            height: 36px;
        }

        .chat-header-actions .icon-btn svg {
            width: 16px;
            height: 16px;
        }

        .messages-area {
            padding: 1rem;
        }

        .message-input-area {
            padding: 0.75rem 1rem;
        }

        .message-input-wrapper {
            padding: 0.5rem 0.625rem;
        }

        .message-content {
            max-width: 85%;
        }

        .message--mine .message-content {
            max-width: 85%;
        }

        .attachment-preview {
            max-width: 100%;
        }

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

        .chat-info-modal-body {
            padding: 1.25rem;
        }

        .chat-info-group-avatar-wrap {
            width: 96px;
            height: 96px;
        }

        .chat-info-group-avatar {
            font-size: 1.875rem;
        }
    }

    @media (max-width: 480px) {
        .messaging-sidebar {
            width: 100%;
        }

        .sidebar-header {
            padding: 1rem;
        }

        .sidebar-search {
            padding: 0.75rem 1rem;
        }

        .chat-item {
            padding: 0.75rem 1rem;
        }

        .chat-avatar {
            width: 40px;
            height: 40px;
            font-size: 0.75rem;
        }

        .chat-header-actions .icon-btn:nth-child(1),
        .chat-header-actions .icon-btn:nth-child(2) {
            display: none;
        }

        .message-avatar .avatar-initials {
            width: 28px;
            height: 28px;
            font-size: 0.75rem;
        }

        .message-avatar .avatar-photo {
            width: 28px;
            height: 28px;
        }

        .message-content {
            max-width: 90%;
        }

        .message--mine .message-content {
            max-width: 90%;
        }

        .toolbar-btn {
            width: 32px;
            height: 32px;
        }

        .toolbar-btn svg {
            width: 16px;
            height: 16px;
        }

        .send-btn {
            width: 32px;
            height: 32px;
        }

        .send-btn svg {
            width: 16px;
            height: 16px;
        }
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
    let companyUsers = [];

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

    function formatTime(dateStr) {
        const d = new Date(dateStr);
        const now = new Date();
        const diff = now - d;
        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return Math.floor(diff/60000) + 'm ago';
        if (diff < 86400000) return Math.floor(diff/3600000) + 'h ago';
        if (diff < 604800000) return Math.floor(diff/86400000) + 'd ago';
        return d.toLocaleDateString();
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
        const btn = document.getElementById('chatsSeeMoreBtn');
        if (btn) btn.disabled = true;
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
        if (btn) btn.disabled = false;
    }
    window.loadMoreConversations = loadMoreConversations;

    function createChatItem(c) {
        const item = document.createElement('div');
        item.className = 'chat-item' + (c.id === currentConversationId ? ' active' : '');
        item.dataset.conversationId = c.id;
            const avatar = c.type === 'group'
                ? (c.avatar_photo
                    ? '<div class="chat-avatar group"><img src="' + c.avatar_photo + '" alt="" class="avatar-photo"></div>'
                    : '<div class="chat-avatar group"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>')
                : '<div class="chat-avatar">' + (c.avatar_photo ? '<img src="' + c.avatar_photo + '" alt="" class="avatar-photo">' : '<div class="avatar-initials">' + (c.avatar_initials || '?') + '</div>') + '</div>';
        item.innerHTML = avatar + `
            <div class="chat-info">
                <div class="chat-header-row">
                    <span class="chat-name">${escapeHtml(c.name)}</span>
                    <span class="chat-time">${formatTime(c.last_message_at)}</span>
                </div>
                <div class="chat-preview">${escapeHtml(c.preview)}</div>
            </div>
            ${c.unread_count ? '<span class="unread-badge">' + c.unread_count + '</span>' : ''}
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
        document.querySelectorAll('.chat-item').forEach(i => {
            i.classList.toggle('active', i.dataset.conversationId == id);
        });
        document.getElementById('messagingPlaceholder').style.display = 'none';
        document.getElementById('chatHeader').style.display = 'flex';
        document.getElementById('messagesArea').style.display = 'flex';
        document.getElementById('messageInputArea').style.display = 'block';
        if (window.matchMedia('(max-width: 768px)').matches) {
            document.getElementById('messagingApp').classList.add('mobile-chat-open');
        }
        await loadMessages(id);
    }

    window.goBackToConversationList = function() {
        document.getElementById('messagingApp').classList.remove('mobile-chat-open');
    };

    const MESSAGES_PAGE_SIZE = 25;
    let messagesHasMore = false;
    let messagesOldestId = null;
    let loadOlderInProgress = false;

    function buildMessageElement(m) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'message ' + (m.author === 'You' ? 'message--mine' : 'message--theirs');
        msgDiv.dataset.messageId = m.id;
        let body = '';
        if (m.body) body = '<div class="message-text">' + escapeHtml(m.body) + '</div>' + body;
        if (m.attachment_path) {
            const isImg = m.attachment_type === 'image';
            body += '<div class="message-attachment"><div class="attachment-preview">';
            if (isImg) {
                body += '<img src="' + m.attachment_path + '" alt="" style="max-width:200px;max-height:150px;border-radius:8px;">';
            } else {
                body += '<svg class="attachment-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg><div class="attachment-info"><div class="attachment-name">' + escapeHtml(m.attachment_name || 'File') + '</div></div>';
            }
            body += '<a href="' + m.attachment_path + '" target="_blank" class="attachment-download" download><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></a></div></div>';
        }
        const msgAvatar = m.author_photo ? '<img src="' + m.author_photo + '" alt="" class="avatar-photo">' : '<div class="avatar-initials">' + m.author_initials + '</div>';
        msgDiv.innerHTML = `
            <div class="message-avatar">${msgAvatar}</div>
            <div class="message-content">
                <div class="message-header">
                    <span class="message-author">${escapeHtml(m.author)}</span>
                    <span class="message-time">${new Date(m.created_at).toLocaleTimeString([], {hour:'numeric',minute:'2-digit'})}</span>
                </div>
                ${body || '<div class="message-text"></div>'}
            </div>
        `;
        return msgDiv;
    }

    function appendMessagesToGroup(group, messages) {
        const sorted = [...messages].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
        const byDate = {};
        sorted.forEach(m => {
            const d = new Date(m.created_at).toDateString();
            if (!byDate[d]) byDate[d] = [];
            byDate[d].push(m);
        });
        const sortedDates = Object.keys(byDate).sort((a, b) => new Date(a) - new Date(b));
        sortedDates.forEach(date => {
            const dDiv = document.createElement('div');
            dDiv.className = 'message-date';
            dDiv.textContent = new Date(date).toLocaleDateString() === new Date().toDateString() ? 'Today' : new Date(date).toLocaleDateString();
            group.appendChild(dDiv);
            byDate[date].forEach(m => group.appendChild(buildMessageElement(m)));
        });
    }

    function prependMessagesToGroup(group, messages) {
        const sorted = [...messages].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
        const byDate = {};
        sorted.forEach(m => {
            const d = new Date(m.created_at).toDateString();
            if (!byDate[d]) byDate[d] = [];
            byDate[d].push(m);
        });
        const fragment = document.createDocumentFragment();
        const sortedDates = Object.keys(byDate).sort((a, b) => new Date(a) - new Date(b));
        sortedDates.forEach(date => {
            const dDiv = document.createElement('div');
            dDiv.className = 'message-date';
            dDiv.textContent = new Date(date).toLocaleDateString() === new Date().toDateString() ? 'Today' : new Date(date).toLocaleDateString();
            fragment.appendChild(dDiv);
            byDate[date].forEach(m => fragment.appendChild(buildMessageElement(m)));
        });
        group.insertBefore(fragment, group.firstChild);
        // Merge consecutive duplicate date headers
        const dates = group.querySelectorAll('.message-date');
        for (let i = dates.length - 1; i > 0; i--) {
            if (dates[i].textContent === dates[i - 1].textContent) dates[i].remove();
        }
    }

    async function loadMessages(conversationId) {
        const params = new URLSearchParams({ limit: MESSAGES_PAGE_SIZE });
        const res = await api(baseUrl + '/conversations/' + conversationId + '/messages?' + params.toString());
        const json = await res.json();
        if (!json.success) return;
        const { conversation, messages, has_more } = json.data;
        messagesHasMore = has_more ?? false;
        messagesOldestId = messages.length > 0 ? messages[0].id : null;
        document.getElementById('chatHeaderName').textContent = conversation.name;
        const deleteBtn = document.getElementById('chatDeleteBtn');
        if (deleteBtn) {
            const canDelete = conversation.type === 'direct' || conversation.is_creator;
            deleteBtn.style.display = canDelete ? '' : 'none';
        }
        const headerAvatar = document.getElementById('chatHeaderAvatar');
        if (conversation.avatar_photo) {
            headerAvatar.innerHTML = '<img src="' + conversation.avatar_photo + '" alt="" class="avatar-photo chat-header-avatar-img">';
        } else if (conversation.type === 'group') {
            headerAvatar.innerHTML = '<div class="chat-header-avatar-group"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>';
        } else {
            headerAvatar.innerHTML = '<div class="avatar-initials" id="chatHeaderInitials">' + (conversation.avatar_initials || '?') + '</div>';
        }
        const group = document.getElementById('messageGroup');
        group.innerHTML = '';
        appendMessagesToGroup(group, messages);
        const loadOlderEl = document.getElementById('messagesLoadOlder');
        if (loadOlderEl) loadOlderEl.style.display = messagesHasMore ? 'block' : 'none';
        document.getElementById('messagesArea').scrollTop = document.getElementById('messagesArea').scrollHeight;
        if (typeof window.updateHeaderMessagingBadge === 'function') window.updateHeaderMessagingBadge();
    }

    async function loadOlderMessages() {
        if (!currentConversationId || loadOlderInProgress || !messagesHasMore || !messagesOldestId) return;
        loadOlderInProgress = true;
        const btn = document.getElementById('messagesSeeMoreBtn');
        if (btn) btn.disabled = true;
        const params = new URLSearchParams({ limit: MESSAGES_PAGE_SIZE, before_id: messagesOldestId });
        const res = await api(baseUrl + '/conversations/' + currentConversationId + '/messages?' + params.toString());
        const json = await res.json();
        if (!json.success) {
            loadOlderInProgress = false;
            if (btn) btn.disabled = false;
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
        const loadOlderEl = document.getElementById('messagesLoadOlder');
        if (loadOlderEl) loadOlderEl.style.display = messagesHasMore ? 'block' : 'none';
        loadOlderInProgress = false;
        if (btn) btn.disabled = false;
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
        const hasText = messageInput.value.trim().length > 0;
        const hasAttachment = pendingAttachment !== null;
        sendBtn.disabled = !hasText && !hasAttachment;
    }

    messageInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
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

    async function sendMessage() {
        const text = messageInput.value.trim();
        if ((!text && !pendingAttachment) || !currentConversationId) return;
        const body = { body: text || null };
        if (pendingAttachment) {
            body.attachment_path = pendingAttachment.path;
            body.attachment_name = pendingAttachment.name;
            body.attachment_type = pendingAttachment.type;
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
            updateSendButtonState();
            const m = json.data;
            const group = document.getElementById('messageGroup');
            const lastDate = group.querySelector('.message-date:last-of-type');
            const today = new Date().toDateString();
            if (!lastDate || lastDate.textContent !== 'Today') {
                const dDiv = document.createElement('div');
                dDiv.className = 'message-date';
                dDiv.textContent = 'Today';
                group.appendChild(dDiv);
            }
            group.appendChild(buildMessageElement(m));
            document.getElementById('messagesArea').scrollTop = document.getElementById('messagesArea').scrollHeight;
            loadConversations(document.getElementById('conversationSearch').value);
        }
    }

    function handleMessageInput(event) {
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
            img.src = data.url || ('/storage/' + data.path);
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
            document.getElementById('messagingPlaceholder').style.display = 'flex';
            document.getElementById('chatHeader').style.display = 'none';
            document.getElementById('messagesArea').style.display = 'none';
            document.getElementById('messageInputArea').style.display = 'none';
            document.querySelectorAll('.chat-item').forEach(i => i.classList.remove('active'));
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
                chatInfoData.photo = data.url || ('/storage/' + data.path);
                const avatarEl = document.getElementById('chatInfoGroupAvatar');
                avatarEl.innerHTML = '<img src="' + chatInfoData.photo + '" alt="">';
                loadConversations(document.getElementById('conversationSearch').value);
                const headerAvatar = document.getElementById('chatHeaderAvatar');
                if (headerAvatar) headerAvatar.innerHTML = '<img src="' + chatInfoData.photo + '" alt="" class="avatar-photo chat-header-avatar-img">';
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
            try {
                const data = await uploadFile(file);
                setPendingAttachment({ path: data.path, name: data.name, type: data.type });
            } catch (err) {
                alert(err.message || 'Upload failed');
            }
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
            try {
                const data = await uploadFile(file);
                const previewUrl = URL.createObjectURL(file);
                setPendingAttachment({ path: data.path, name: data.name, type: 'image', previewUrl });
            } catch (err) {
                alert(err.message || 'Upload failed');
            }
        };
        input.click();
    };

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

    // Init
    loadConversations();

    // Poll for new chats and unread updates (refresh every 15 seconds)
    setInterval(function() {
        if (document.visibilityState === 'visible') {
            loadConversations(document.getElementById('conversationSearch').value);
        }
    }, 15000);
})();
</script>
@endpush

