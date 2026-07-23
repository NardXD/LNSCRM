/**
 * Text chat during an active live view session.
 */
window.LiveViewChat = (function () {
    const signaling = window.LiveViewSignaling;
    let sessionId = null;
    let pollTimer = null;
    let pollInFlight = false;
    let sending = false;
    const displayedMessageIds = new Set();
    let onMessage = () => {};
    let onReplaceMessage = () => {};
    let onRemoveMessage = () => {};
    let onSendingChange = () => {};
    let onUnread = () => {};

    function configure(options = {}) {
        onMessage = options.onMessage || onMessage;
        onReplaceMessage = options.onReplaceMessage || onReplaceMessage;
        onRemoveMessage = options.onRemoveMessage || onRemoveMessage;
        onSendingChange = options.onSendingChange || onSendingChange;
        onUnread = options.onUnread || onUnread;
    }

    function resetDisplayedMessages() {
        displayedMessageIds.clear();
    }

    function markMessagesDisplayed(messages = []) {
        messages.forEach((message) => {
            if (message?.id) {
                displayedMessageIds.add(message.id);
            }
        });
    }

    function highestDisplayedId() {
        if (displayedMessageIds.size === 0) {
            return 0;
        }

        return Math.max(...displayedMessageIds);
    }

    function setSession(id) {
        sessionId = id;
        resetDisplayedMessages();
    }

    function clearSession() {
        sessionId = null;
        resetDisplayedMessages();
        stopPolling();
        setSendingState(false);
    }

    function setSendingState(active) {
        sending = !!active;
        onSendingChange(sending);
    }

    function isSending() {
        return sending;
    }

    function resolveIsMine(message) {
        if (!message) {
            return false;
        }

        if (message.is_mine === true) {
            return true;
        }

        const userId = Number(window.__liveViewUserId);
        const senderId = Number(message.sender_id);

        return Number.isFinite(userId) && userId > 0 && senderId === userId;
    }

    function withChatPerspective(message) {
        return {
            ...message,
            is_mine: resolveIsMine(message),
        };
    }

    function recordMessage(message) {
        const normalized = withChatPerspective(message);

        if (!normalized?.id || displayedMessageIds.has(normalized.id)) {
            return false;
        }

        displayedMessageIds.add(normalized.id);
        onMessage(normalized);
        return true;
    }

    function recordMessages(messages = []) {
        const sorted = [...messages].sort((a, b) => a.id - b.id);
        sorted.forEach((message) => recordMessage(message));
    }

    async function loadMessages(page = 1) {
        if (!sessionId) {
            return { messages: [], pagination: null };
        }

        const data = await signaling.api(
            `/api/live-view/sessions/${sessionId}/messages?page=${page}&per_page=50`
        );

        const messages = data.messages || [];
        markMessagesDisplayed(messages.map(withChatPerspective));

        return {
            messages: messages.map(withChatPerspective),
            pagination: data.pagination || null,
        };
    }

    async function syncMessages() {
        if (!sessionId || pollInFlight || sending) {
            return [];
        }

        pollInFlight = true;

        try {
            const sinceId = highestDisplayedId();
            const params = sinceId > 0
                ? `since_id=${sinceId}&per_page=100`
                : 'page=1&per_page=50';

            const data = await signaling.api(
                `/api/live-view/sessions/${sessionId}/messages?${params}`
            );

            const messages = data.messages || [];
            recordMessages(messages);

            return messages;
        } finally {
            pollInFlight = false;
        }
    }

    async function sendMessage(body) {
        if (!sessionId) {
            throw new Error('No active live view session.');
        }

        if (sending) {
            throw new Error('A message is already being sent.');
        }

        const trimmed = (body || '').trim();
        if (!trimmed) {
            throw new Error('Message cannot be empty.');
        }

        const tempId = `pending-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

        setSendingState(true);
        onMessage({
            id: tempId,
            body: trimmed,
            is_mine: true,
            pending: true,
            sent_at: new Date().toISOString(),
            sender_name: 'You',
        });

        try {
            const data = await signaling.api(`/api/live-view/sessions/${sessionId}/messages`, {
                method: 'POST',
                body: JSON.stringify({ body: trimmed }),
            });

            const message = data.message;
            if (message?.id) {
                onReplaceMessage(tempId, message);
                displayedMessageIds.add(message.id);
            } else {
                onRemoveMessage(tempId);
            }

            return message;
        } catch (error) {
            onRemoveMessage(tempId);
            throw error;
        } finally {
            setSendingState(false);
        }
    }

    function handleSignal(signal) {
        if (signal.signal_type !== 'chat-message') {
            return;
        }

        const payload = signal.payload || {};
        if (!payload.message_id || !payload.body) {
            return;
        }

        recordMessage({
            id: payload.message_id,
            body: payload.body,
            sender_id: payload.sender_id,
            sender_name: payload.sender_name,
            sent_at: payload.sent_at,
            is_mine: false,
        });
    }

    function startRealtime() {
        stopRealtime();
        if (!sessionId) {
            return;
        }

        syncMessages().catch((error) => {
            console.warn('Live view chat initial sync failed', error);
        });

        signaling.subscribeSessionChat(sessionId, (message) => {
            recordMessage(message);
        });
    }

    function stopRealtime() {
        if (sessionId) {
            signaling.unsubscribeSessionChat(sessionId);
        }
        pollInFlight = false;
    }

    function startPolling(intervalMs = 5000) {
        startRealtime();
    }

    function stopPolling() {
        stopRealtime();
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function notifyUnread(count = 1) {
        onUnread(count);
    }

    return {
        configure,
        setSession,
        clearSession,
        resetDisplayedMessages,
        markMessagesDisplayed,
        loadMessages,
        syncMessages,
        sendMessage,
        handleSignal,
        startRealtime,
        startPolling,
        stopPolling,
        notifyUnread,
        isSending,
    };
})();
