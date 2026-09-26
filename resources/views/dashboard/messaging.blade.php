@extends('layouts.app')

@section('title', 'Messages')

@push('styles')
    @vite(['resources/css/pages/messaging.css'])
@endpush

@push('scripts')
    @vite(['resources/js/pages/messaging.js'])
@endpush

@section('content')
<div class="msg-page-wrapper">
<div class="msg-page" id="messagingApp" data-api-base="{{ url('api/messaging') }}" data-csrf="{{ csrf_token() }}" data-user-id="{{ auth()->id() }}">
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
                <div id="chatsListItems" aria-busy="true">
                    @include('partials.skeleton-threads')
                </div>
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
                        <button type="button" class="msg-icon-btn" id="msgMentionBtn" onclick="window.insertMentionTrigger()" title="Mention member" hidden>
                            <span class="msg-mention-at">@</span>
                        </button>
                    </div>
                    <div class="msg-mention-popup" id="msgMentionPopup" hidden></div>
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
