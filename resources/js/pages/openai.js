/* Vite page entry — IIFE preserves onclick globals */
(function () {
const CFG = window.__openaiConfig || {};
    let chatHistory = [];
    let currentChatId = null;
    let isWaitingForResponse = false;
    let conversationMessages = [];
    let pendingContextType = null;
    let dataSource = 'database';

    function setDataSource(source) {
        dataSource = source;
        document.querySelectorAll('.data-source-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.source === source);
        });
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        loadChatHistory();
        updateModelInfo();
        
        document.getElementById('modelSelect').addEventListener('change', updateModelInfo);
        document.getElementById('temperature').addEventListener('input', function() {
            document.getElementById('temperatureValue').textContent = this.value;
        });

        // Auto-resize textarea
        const chatInput = document.getElementById('chatInput');
        chatInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 200) + 'px';
        });
    });

    function updateModelInfo() {
        const model = document.getElementById('modelSelect').value;
        const modelNames = {
            'gpt-5.2': 'GPT-5.2',
            'gpt-5-mini': 'GPT-5 mini',
            'gpt-5-nano': 'GPT-5 nano',
            'gpt-4.1': 'GPT-4.1',
            'gpt-4.1-mini': 'GPT-4.1 mini',
            'gpt-4.1-nano': 'GPT-4.1 nano',
            'gpt-4o': 'GPT-4o',
            'gpt-4o-mini': 'GPT-4o mini',
            'gpt-3.5-turbo': 'GPT-3.5 Turbo'
        };
        const modelDescs = {
            'gpt-5.2': 'Best for coding and agentic tasks',
            'gpt-5-mini': 'Faster, cost-efficient for well-defined tasks',
            'gpt-5-nano': 'Fastest, most cost-efficient',
            'gpt-4.1': 'Smartest non-reasoning model',
            'gpt-4.1-mini': 'Smaller, faster version of GPT-4.1',
            'gpt-4.1-nano': 'Fastest GPT-4.1 tier',
            'gpt-4o': 'Fast, intelligent, flexible',
            'gpt-4o-mini': 'Fast, affordable for focused tasks',
            'gpt-3.5-turbo': 'Legacy, budget-friendly'
        };
        
        document.getElementById('modelBadge').textContent = modelNames[model] || model;
        document.querySelector('.model-desc').textContent = modelDescs[model] || '';
    }

    function handleInputKeydown(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    }

    async function sendMessage() {
        const input = document.getElementById('chatInput');
        const message = input.value.trim();

        if (!message || isWaitingForResponse) return;

        // Hide welcome message
        const welcomeEl = document.querySelector('.welcome-message');
        if (welcomeEl) welcomeEl.style.display = 'none';

        // Add user message
        addMessage('user', message);
        conversationMessages.push({ role: 'user', content: message });
        input.value = '';
        input.style.height = 'auto';

        // Show typing indicator
        showTypingIndicator();

        isWaitingForResponse = true;
        document.getElementById('sendBtn').disabled = true;

        const contextTypeToSend = pendingContextType;
        pendingContextType = null;

        try {
            const model = document.getElementById('modelSelect').value;
            const response = await fetch((CFG.chatUrl || "/api/openai/chat"), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    messages: conversationMessages,
                    model: model,
                    context_type: contextTypeToSend,
                    data_source: dataSource
                })
            });

            const data = await response.json();
            hideTypingIndicator();

            if (response.ok) {
                const content = data.content || '';
                addMessage('assistant', content);
                conversationMessages.push({ role: 'assistant', content });
            } else {
                const errorMsg = data.error || 'Unable to get a response. Please try again.';
                const isQuotaError = /quota|billing|exceeded|rate limit/i.test(errorMsg);
                const isConfigError = /not configured|API key|Invalid API key/i.test(errorMsg);
                const isModelError = /model/i.test(errorMsg) && /(not found|does not exist|invalid|access|unsupported)/i.test(errorMsg);
                let help = '';
                if (isQuotaError) {
                    help = '\n\nCheck your usage and billing at https://platform.openai.com/account/billing';
                } else if (isConfigError) {
                    help = '\n\nPlease ensure your OpenAI API key is configured correctly in Integrations.';
                } else if (isModelError) {
                    help = '\n\nThat model isn\'t available on this account. Switching back to GPT-4o — please try sending your message again.';
                    const modelSelect = document.getElementById('modelSelect');
                    modelSelect.value = 'gpt-4o';
                    updateModelInfo();
                }
                addMessage('assistant', 'Sorry, I encountered an error: ' + errorMsg + help);
                conversationMessages.push({ role: 'assistant', content: errorMsg });
            }
        } catch (error) {
            hideTypingIndicator();
            addMessage('assistant', 'Sorry, I could not connect to the server. Please check your connection and that the OpenAI API key is configured in Integrations.');
            conversationMessages.push({ role: 'assistant', content: 'Connection error.' });
        } finally {
            isWaitingForResponse = false;
            document.getElementById('sendBtn').disabled = false;
        }
    }

    function addMessage(role, content) {
        const messagesContainer = document.getElementById('chatMessages');
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${role}`;
        
        const time = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        const showTimestamps = document.getElementById('showTimestamps')?.checked !== false;
        
        messageDiv.innerHTML = `
            <div class="message-avatar">${role === 'user' ? 'U' : 'AI'}</div>
            <div class="message-content">
                <div class="message-bubble">${escapeHtml(content)}</div>
                ${showTimestamps ? `<div class="message-time">${time}</div>` : ''}
                <div class="message-actions">
                    <button class="message-action-btn" onclick="copyMessage(this)" title="Copy">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                    </button>
                </div>
            </div>
        `;
        
        messagesContainer.appendChild(messageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function showTypingIndicator() {
        const messagesContainer = document.getElementById('chatMessages');
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message assistant';
        typingDiv.id = 'typingIndicator';
        typingDiv.innerHTML = `
            <div class="message-avatar">AI</div>
            <div class="message-content">
                <div class="typing-indicator">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            </div>
        `;
        messagesContainer.appendChild(typingDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function hideTypingIndicator() {
        const indicator = document.getElementById('typingIndicator');
        if (indicator) indicator.remove();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function copyMessage(btn) {
        const messageBubble = btn.closest('.message-content').querySelector('.message-bubble');
        const text = messageBubble.textContent;
        navigator.clipboard.writeText(text).then(() => {
            btn.style.background = 'var(--success)';
            btn.style.borderColor = 'var(--success)';
            setTimeout(() => {
                btn.style.background = '';
                btn.style.borderColor = '';
            }, 1000);
        });
    }

    function startNewChat() {
        if (confirm('Start a new chat? Current conversation will be saved.')) {
            const container = document.getElementById('chatMessages');
            container.querySelectorAll('.message').forEach(el => el.remove());
            const welcomeEl = document.querySelector('.welcome-message');
            if (welcomeEl) welcomeEl.style.display = 'flex';
            currentChatId = null;
            conversationMessages = [];
        }
    }

    function clearChat() {
        if (confirm('Clear all messages in this chat?')) {
            const container = document.getElementById('chatMessages');
            container.querySelectorAll('.message').forEach(el => el.remove());
            const welcomeEl = document.querySelector('.welcome-message');
            if (welcomeEl) welcomeEl.style.display = 'flex';
            conversationMessages = [];
        }
    }

    function exportChat() {
        const messages = document.querySelectorAll('.message-bubble');
        let text = 'Chat Export\n' + '='.repeat(50) + '\n\n';
        messages.forEach((msg, index) => {
            const role = msg.closest('.message').classList.contains('user') ? 'User' : 'Assistant';
            text += `${role}: ${msg.textContent}\n\n`;
        });
        
        const blob = new Blob([text], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `chat-export-${Date.now()}.txt`;
        a.click();
        URL.revokeObjectURL(url);
    }

    function useSuggestion(text) {
        document.getElementById('chatInput').value = text;
        document.getElementById('chatInput').focus();
    }

    function useQuickAction(action) {
        const prompts = {
            'email': 'Write a professional email to a client about a project update',
            'summary': 'Summarize the following text:',
            'translate': 'Translate the following text to Spanish:',
            'reply': 'Draft a friendly, professional reply to this customer message:'
        };
        useSuggestion(prompts[action]);
    }

    function useSummaryAction(type) {
        pendingContextType = type;
        const prompts = {
            'leads': 'Generate a summary of my leads: status breakdown, top sources, unassigned leads, and recent activity.',
            'shared-inbox': 'Generate a summary of my shared inboxes: unread conversations, status breakdown, and recent activity.',
            'viber': 'Generate a summary of my Viber conversations: unread messages and recent activity.',
            'whatsapp': 'Generate a summary of my WhatsApp conversations: unread messages, messaging window status, and recent activity.',
            'facebook': 'Generate a summary of my Facebook and Instagram conversations: unread messages and recent activity by channel.',
            'sms': 'Generate a summary of my SMS conversations: unread messages and recent activity.',
            'broadcast': 'Generate a summary of my broadcast campaigns: status, delivery rates, and recent campaigns.',
            'knowledge-base': 'Generate a summary of my knowledge base: articles, FAQs, and guides by category.'
        };
        const prompt = prompts[type] || 'Generate a summary.';
        useSuggestion(prompt);
    }

    function loadChatHistory() {
        // In production, load from localStorage or API
        const history = JSON.parse(localStorage.getItem('chatHistory') || '[]');
        chatHistory = history;
        renderChatHistory();
    }

    function renderChatHistory() {
        const container = document.getElementById('chatHistory');
        if (chatHistory.length === 0) {
            container.innerHTML = '<div style="color: var(--text-muted); font-size: 0.875rem; text-align: center; padding: 1rem;">No chat history</div>';
            return;
        }
        
        container.innerHTML = chatHistory.map((chat, index) => `
            <button class="chat-history-item ${chat.id === currentChatId ? 'active' : ''}" onclick="loadChat('${chat.id}')">
                <div class="chat-history-title">${chat.title}</div>
                <div class="chat-history-date">${new Date(chat.date).toLocaleDateString()}</div>
            </button>
        `).join('');
    }

    function loadChat(chatId) {
        const chat = chatHistory.find(c => c.id === chatId);
        if (chat) {
            currentChatId = chatId;
            // Load chat messages
            renderChatHistory();
        }
    }

    // Settings
    function openSettings() {
        document.getElementById('settingsModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSettings() {
        document.getElementById('settingsModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    function saveSettings() {
        const settings = {
            defaultModel: document.getElementById('defaultModel').value,
            temperature: document.getElementById('temperature').value,
            maxTokens: document.getElementById('maxTokens').value,
            saveHistory: document.getElementById('saveHistory').checked,
            showTimestamps: document.getElementById('showTimestamps').checked
        };
        
        localStorage.setItem('openaiSettings', JSON.stringify(settings));
        alert('Settings saved successfully!');
        closeSettings();
    }

    document.getElementById('settingsModal').addEventListener('click', function(e) {
        if (e.target === this) closeSettings();
    });

    // Load saved settings
    const savedSettings = JSON.parse(localStorage.getItem('openaiSettings') || '{}');
    if (savedSettings.defaultModel) {
        document.getElementById('modelSelect').value = savedSettings.defaultModel;
        updateModelInfo();
    }
    if (savedSettings.temperature) {
        document.getElementById('temperature').value = savedSettings.temperature;
        document.getElementById('temperatureValue').textContent = savedSettings.temperature;
    }

    if (typeof clearChat === 'function') window.clearChat = clearChat;
    if (typeof closeSettings === 'function') window.closeSettings = closeSettings;
    if (typeof copyMessage === 'function') window.copyMessage = copyMessage;
    if (typeof exportChat === 'function') window.exportChat = exportChat;
    if (typeof loadChat === 'function') window.loadChat = loadChat;
    if (typeof openSettings === 'function') window.openSettings = openSettings;
    if (typeof saveSettings === 'function') window.saveSettings = saveSettings;
    if (typeof sendMessage === 'function') window.sendMessage = sendMessage;
    if (typeof setDataSource === 'function') window.setDataSource = setDataSource;
    if (typeof startNewChat === 'function') window.startNewChat = startNewChat;
    if (typeof useQuickAction === 'function') window.useQuickAction = useQuickAction;
    if (typeof useSummaryAction === 'function') window.useSummaryAction = useSummaryAction;
})();
