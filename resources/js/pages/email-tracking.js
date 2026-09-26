/* Vite page entry — IIFE preserves onclick globals */
(function () {
// Data
    let templates = [
        { id: 1, name: 'Sales Introduction', category: 'sales', subject: 'Introduction to Our Services', body: 'Hi {{first_name}},\n\nI hope this email finds you well. I wanted to introduce you to our company and the services we offer...', trackOpens: true, trackClicks: true },
        { id: 2, name: 'Follow-up Email', category: 'follow-up', subject: 'Following up on our conversation', body: 'Hi {{first_name}},\n\nI wanted to follow up on our recent conversation about {{product}}...', trackOpens: true, trackClicks: true },
        { id: 3, name: 'Welcome Email', category: 'welcome', subject: 'Welcome to {{company}}!', body: 'Hi {{first_name}},\n\nWelcome to {{company}}! We\'re excited to have you on board...', trackOpens: true, trackClicks: false },
        { id: 4, name: 'Product Nurture', category: 'nurture', subject: 'Learn more about {{product}}', body: 'Hi {{first_name}},\n\nI thought you might be interested in learning more about {{product}}...', trackOpens: true, trackClicks: true }
    ];

    let sequences = [
        { id: 1, name: 'Sales Follow-up Sequence', description: '5-step follow-up sequence for sales', steps: [
            { templateId: 1, delay: 0, delayUnit: 'days' },
            { templateId: 2, delay: 3, delayUnit: 'days' },
            { templateId: 2, delay: 7, delayUnit: 'days' },
            { templateId: 4, delay: 14, delayUnit: 'days' }
        ], active: true, sent: 245, opened: 168, replied: 42 }
    ];

    let trackingData = [
        { id: 1, recipient: 'john@example.com', subject: 'Introduction to Our Services', type: 'Template', sent: '2025-01-15 10:00', opened: '2025-01-15 14:30', replied: '2025-01-16 09:00', clicked: true, status: 'replied' },
        { id: 2, recipient: 'sarah@example.com', subject: 'Following up on our conversation', type: 'Sequence', sent: '2025-01-15 11:00', opened: '2025-01-15 15:20', replied: null, clicked: true, status: 'opened' },
        { id: 3, recipient: 'mike@example.com', subject: 'Welcome to Our Company!', type: 'Template', sent: '2025-01-16 09:00', opened: null, replied: null, clicked: false, status: 'pending' },
        { id: 4, recipient: 'lisa@example.com', subject: 'Learn more about our product', type: 'Sequence', sent: '2025-01-16 10:30', opened: '2025-01-16 11:15', replied: '2025-01-16 14:00', clicked: true, status: 'replied' }
    ];

    let currentEditingTemplate = null;
    let currentEditingSequence = null;
    let sequenceStepCounter = 0;

    // Tab Switching
    function switchTab(tab) {
        document.querySelectorAll('.email-tab').forEach(t => t.classList.remove('active'));
        document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById(`${tab}Tab`).classList.add('active');

        if (tab === 'templates') {
            renderTemplates();
        } else if (tab === 'sequences') {
            renderSequences();
        } else if (tab === 'tracking') {
            renderTracking();
        } else if (tab === 'analytics') {
            renderAnalytics();
        } else if (tab === 'accounts') {
            renderConnectedAccounts();
            updateConnectButtons();
        }
    }

    // Templates
    function renderTemplates() {
        const grid = document.getElementById('templatesGrid');
        grid.innerHTML = templates.map(template => `
            <div class="template-card" onclick="editTemplate(${template.id})">
                <div class="template-header">
                    <div>
                        <div class="template-title">${template.name}</div>
                        <span class="template-category">${template.category}</span>
                    </div>
                </div>
                <div class="template-subject">${template.subject}</div>
                <div class="template-preview">${template.body.substring(0, 150)}...</div>
                <div class="template-actions" onclick="event.stopPropagation()">
                    <button class="template-action-btn" onclick="useTemplate(${template.id})">Use</button>
                    <button class="template-action-btn" onclick="editTemplate(${template.id})">Edit</button>
                    <button class="template-action-btn" onclick="deleteTemplate(${template.id})">Delete</button>
                </div>
            </div>
        `).join('');
    }

    function openTemplateModal() {
        currentEditingTemplate = null;
        document.getElementById('templateModalTitle').textContent = 'New Email Template';
        document.getElementById('templateForm').reset();
        document.getElementById('templateModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeTemplateModal() {
        document.getElementById('templateModal').classList.remove('active');
        document.body.style.overflow = '';
        currentEditingTemplate = null;
    }

    function editTemplate(id) {
        const template = templates.find(t => t.id === id);
        if (!template) return;

        currentEditingTemplate = template;
        document.getElementById('templateModalTitle').textContent = 'Edit Email Template';
        document.getElementById('templateName').value = template.name;
        document.getElementById('templateCategorySelect').value = template.category;
        document.getElementById('templateSubject').value = template.subject;
        document.getElementById('templateBody').value = template.body;
        document.getElementById('templateTrackOpens').checked = template.trackOpens;
        document.getElementById('templateTrackClicks').checked = template.trackClicks;

        document.getElementById('templateModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function saveTemplate(e) {
        e.preventDefault();

        const template = {
            name: document.getElementById('templateName').value,
            category: document.getElementById('templateCategorySelect').value,
            subject: document.getElementById('templateSubject').value,
            body: document.getElementById('templateBody').value,
            trackOpens: document.getElementById('templateTrackOpens').checked,
            trackClicks: document.getElementById('templateTrackClicks').checked
        };

        if (currentEditingTemplate) {
            const index = templates.findIndex(t => t.id === currentEditingTemplate.id);
            templates[index] = { ...currentEditingTemplate, ...template };
        } else {
            template.id = Date.now();
            templates.push(template);
        }

        closeTemplateModal();
        renderTemplates();
    }

    function deleteTemplate(id) {
        if (confirm('Are you sure you want to delete this template?')) {
            templates = templates.filter(t => t.id !== id);
            renderTemplates();
        }
    }

    function useTemplate(id) {
        alert(`Template ${id} would be used to send an email`);
    }

    function insertVariable(variable) {
        const textarea = document.getElementById('templateBody');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        const variableText = '{{' + variable + '}}';
        textarea.value = text.substring(0, start) + variableText + text.substring(end);
        textarea.focus();
        textarea.setSelectionRange(start + variableText.length, start + variableText.length);
    }

    // Handle variable button clicks
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.editor-btn[data-variable]').forEach(btn => {
            btn.addEventListener('click', function() {
                const variable = this.getAttribute('data-variable');
                insertVariable(variable);
            });
        });
    });

    function previewTemplate() {
        alert('Template preview would be displayed here');
    }

    // Sequences
    function renderSequences() {
        const list = document.getElementById('sequencesList');
        list.innerHTML = sequences.map(sequence => {
            const stepsHtml = sequence.steps.map((step, index) => {
                const template = templates.find(t => t.id === step.templateId);
                return `
                    <div class="sequence-step-preview">
                        <div class="step-number">${index + 1}</div>
                        <div class="step-info">
                            <div class="step-title">${template ? template.name : 'Template'}</div>
                            <div class="step-delay">Send after ${step.delay} ${step.delayUnit}</div>
                        </div>
                    </div>
                `;
            }).join('');

            return `
                <div class="sequence-card" onclick="editSequence(${sequence.id})">
                    <div class="sequence-header">
                        <div class="sequence-title">${sequence.name}</div>
                        <div class="sequence-stats">
                            <div class="sequence-stat">
                                <span>Sent: ${sequence.sent}</span>
                            </div>
                            <div class="sequence-stat">
                                <span>Opened: ${sequence.opened}</span>
                            </div>
                            <div class="sequence-stat">
                                <span>Replied: ${sequence.replied}</span>
                            </div>
                        </div>
                    </div>
                    <div class="sequence-steps-preview">
                        ${stepsHtml}
                    </div>
                </div>
            `;
        }).join('');
    }

    function openSequenceModal() {
        currentEditingSequence = null;
        sequenceStepCounter = 0;
        document.getElementById('sequenceModalTitle').textContent = 'New Email Sequence';
        document.getElementById('sequenceForm').reset();
        document.getElementById('sequenceSteps').innerHTML = '';
        document.getElementById('sequenceModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSequenceModal() {
        document.getElementById('sequenceModal').classList.remove('active');
        document.body.style.overflow = '';
        currentEditingSequence = null;
    }

    function addSequenceStep() {
        sequenceStepCounter++;
        const stepsContainer = document.getElementById('sequenceSteps');
        const stepHtml = `
            <div class="sequence-step" data-step-id="${sequenceStepCounter}">
                <div class="step-header">
                    <div class="step-title">Step ${sequenceStepCounter}</div>
                    <button type="button" class="step-remove" onclick="removeSequenceStep(${sequenceStepCounter})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
                <div class="form-group">
                    <label class="form-label">Template *</label>
                    <select class="form-input step-template" required>
                        <option value="">Select a template</option>
                        ${templates.map(t => `<option value="${t.id}">${t.name}</option>`).join('')}
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Delay *</label>
                        <input type="number" class="form-input step-delay" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Delay Unit *</label>
                        <select class="form-input step-delay-unit" required>
                            <option value="hours">Hours</option>
                            <option value="days" selected>Days</option>
                            <option value="weeks">Weeks</option>
                        </select>
                    </div>
                </div>
            </div>
        `;
        stepsContainer.insertAdjacentHTML('beforeend', stepHtml);
    }

    function removeSequenceStep(stepId) {
        const step = document.querySelector(`[data-step-id="${stepId}"]`);
        if (step) step.remove();
    }

    function editSequence(id) {
        const sequence = sequences.find(s => s.id === id);
        if (!sequence) return;

        currentEditingSequence = sequence;
        document.getElementById('sequenceModalTitle').textContent = 'Edit Email Sequence';
        document.getElementById('sequenceName').value = sequence.name;
        document.getElementById('sequenceDescription').value = sequence.description || '';

        const stepsContainer = document.getElementById('sequenceSteps');
        stepsContainer.innerHTML = '';
        sequence.steps.forEach((step, index) => {
            sequenceStepCounter = index + 1;
            const stepHtml = `
                <div class="sequence-step" data-step-id="${sequenceStepCounter}">
                    <div class="step-header">
                        <div class="step-title">Step ${sequenceStepCounter}</div>
                        <button type="button" class="step-remove" onclick="removeSequenceStep(${sequenceStepCounter})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Template *</label>
                        <select class="form-input step-template" required>
                            <option value="">Select a template</option>
                            ${templates.map(t => `<option value="${t.id}" ${t.id === step.templateId ? 'selected' : ''}>${t.name}</option>`).join('')}
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Delay *</label>
                            <input type="number" class="form-input step-delay" min="0" value="${step.delay}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Delay Unit *</label>
                            <select class="form-input step-delay-unit" required>
                                <option value="hours" ${step.delayUnit === 'hours' ? 'selected' : ''}>Hours</option>
                                <option value="days" ${step.delayUnit === 'days' ? 'selected' : ''}>Days</option>
                                <option value="weeks" ${step.delayUnit === 'weeks' ? 'selected' : ''}>Weeks</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;
            stepsContainer.insertAdjacentHTML('beforeend', stepHtml);
        });

        document.getElementById('sequenceModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function saveSequence(e) {
        e.preventDefault();

        const steps = Array.from(document.querySelectorAll('.sequence-step')).map(step => ({
            templateId: parseInt(step.querySelector('.step-template').value),
            delay: parseInt(step.querySelector('.step-delay').value),
            delayUnit: step.querySelector('.step-delay-unit').value
        }));

        const sequence = {
            name: document.getElementById('sequenceName').value,
            description: document.getElementById('sequenceDescription').value,
            steps: steps
        };

        if (currentEditingSequence) {
            const index = sequences.findIndex(s => s.id === currentEditingSequence.id);
            sequences[index] = { ...currentEditingSequence, ...sequence };
        } else {
            sequence.id = Date.now();
            sequence.active = true;
            sequence.sent = 0;
            sequence.opened = 0;
            sequence.replied = 0;
            sequences.push(sequence);
        }

        closeSequenceModal();
        renderSequences();
    }

    // Tracking
    function renderTracking() {
        const tbody = document.getElementById('trackingTableBody');
        tbody.innerHTML = trackingData.map(item => `
            <tr>
                <td>${item.recipient}</td>
                <td>${item.subject}</td>
                <td>${item.type}</td>
                <td>${item.sent}</td>
                <td>${item.opened || '-'}</td>
                <td>${item.replied || '-'}</td>
                <td>${item.clicked ? 'Yes' : 'No'}</td>
                <td><span class="status-badge ${item.status}">${item.status.charAt(0).toUpperCase() + item.status.slice(1)}</span></td>
            </tr>
        `).join('');

        // Mobile cards
        const cards = document.getElementById('trackingCards');
        cards.innerHTML = trackingData.map(item => `
            <div class="tracking-card">
                <div class="tracking-card-header">
                    <div class="tracking-card-title">${item.subject}</div>
                    <span class="status-badge ${item.status}">${item.status.charAt(0).toUpperCase() + item.status.slice(1)}</span>
                </div>
                <div class="tracking-card-details">
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Recipient</span>
                        <span class="tracking-card-value">${item.recipient}</span>
                    </div>
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Type</span>
                        <span class="tracking-card-value">${item.type}</span>
                    </div>
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Sent</span>
                        <span class="tracking-card-value">${item.sent}</span>
                    </div>
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Opened</span>
                        <span class="tracking-card-value">${item.opened || '-'}</span>
                    </div>
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Replied</span>
                        <span class="tracking-card-value">${item.replied || '-'}</span>
                    </div>
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Clicked</span>
                        <span class="tracking-card-value">${item.clicked ? 'Yes' : 'No'}</span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    // Analytics
    function renderAnalytics() {
        // Top templates
        const topTemplates = templates.slice(0, 5);
        const topTemplatesList = document.getElementById('topTemplatesList');
        topTemplatesList.innerHTML = topTemplates.map((template, index) => `
            <div class="top-template-item">
                <div class="top-template-name">${template.name}</div>
                <div class="top-template-stats">
                    <span>Opens: 68%</span>
                    <span>Replies: 24%</span>
                </div>
            </div>
        `).join('');

        // Sequence performance
        const sequencePerformanceList = document.getElementById('sequencePerformanceList');
        sequencePerformanceList.innerHTML = sequences.map(sequence => `
            <div class="sequence-performance-item">
                <div class="top-template-name">${sequence.name}</div>
                <div class="top-template-stats">
                    <span>Sent: ${sequence.sent}</span>
                    <span>Opened: ${sequence.opened}</span>
                    <span>Replied: ${sequence.replied}</span>
                </div>
            </div>
        `).join('');
    }

    // Close modals on outside click
    document.getElementById('templateModal').addEventListener('click', function(e) {
        if (e.target === this) closeTemplateModal();
    });

    document.getElementById('sequenceModal').addEventListener('click', function(e) {
        if (e.target === this) closeSequenceModal();
    });

    // Email Accounts
    let connectedAccounts = [];

    function renderConnectedAccounts() {
        const grid = document.getElementById('connectedAccountsGrid');
        if (connectedAccounts.length === 0) {
            grid.innerHTML = '<div style="color: var(--text-muted); font-size: 0.875rem; grid-column: 1 / -1;">No email accounts connected yet. Connect an account below to start tracking emails.</div>';
            return;
        }

        grid.innerHTML = connectedAccounts.map(account => `
            <div class="account-card">
                <div class="account-icon ${account.type}">
                    ${account.type === 'google' ? `
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                    ` : `
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7.5 7.5h9v9h-9z" fill="#0078D4"/>
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" fill="#0078D4"/>
                        </svg>
                    `}
                </div>
                <div class="account-info">
                    <div class="account-email">${account.email}</div>
                    <div class="account-status">
                        <span class="status-dot"></span>
                        <span>Connected • ${account.type === 'google' ? 'Gmail' : 'Outlook'}</span>
                    </div>
                </div>
                <div class="account-actions">
                    <button class="account-action-btn" onclick="testConnection('${account.id}')" title="Test Connection">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </button>
                    <button class="account-action-btn danger" onclick="disconnectAccount('${account.id}')" title="Disconnect">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
            </div>
        `).join('');
    }

    function connectGoogleAccount() {
        // In a real application, this would redirect to Google OAuth
        if (confirm('This will redirect you to Google to authorize email access. Continue?')) {
            // Simulate connection
            const account = {
                id: 'google_' + Date.now(),
                type: 'google',
                email: 'user@gmail.com',
                connectedAt: new Date().toISOString()
            };
            connectedAccounts.push(account);
            renderConnectedAccounts();
            updateConnectButtons();
            alert('Google account connected successfully!');
        }
    }

    function connectOutlookAccount() {
        // In a real application, this would redirect to Microsoft OAuth
        if (confirm('This will redirect you to Microsoft to authorize email access. Continue?')) {
            // Simulate connection
            const account = {
                id: 'outlook_' + Date.now(),
                type: 'outlook',
                email: 'user@outlook.com',
                connectedAt: new Date().toISOString()
            };
            connectedAccounts.push(account);
            renderConnectedAccounts();
            updateConnectButtons();
            alert('Outlook account connected successfully!');
        }
    }

    function disconnectAccount(accountId) {
        if (confirm('Are you sure you want to disconnect this email account? Email tracking will stop for this account.')) {
            connectedAccounts = connectedAccounts.filter(acc => acc.id !== accountId);
            renderConnectedAccounts();
            updateConnectButtons();
            alert('Account disconnected successfully.');
        }
    }

    function testConnection(accountId) {
        const account = connectedAccounts.find(acc => acc.id === accountId);
        if (account) {
            alert(`Testing connection to ${account.email}...\n\nConnection successful!`);
        }
    }

    function updateConnectButtons() {
        const hasGoogle = connectedAccounts.some(acc => acc.type === 'google');
        const hasOutlook = connectedAccounts.some(acc => acc.type === 'outlook');

        const googleBtn = document.getElementById('connectGoogleBtn');
        const outlookBtn = document.getElementById('connectOutlookBtn');

        if (hasGoogle) {
            googleBtn.classList.add('connected');
            googleBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Connected';
            googleBtn.onclick = () => alert('Google account is already connected');
        } else {
            googleBtn.classList.remove('connected');
            googleBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>Connect Google';
            googleBtn.onclick = connectGoogleAccount;
        }

        if (hasOutlook) {
            outlookBtn.classList.add('connected');
            outlookBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Connected';
            outlookBtn.onclick = () => alert('Outlook account is already connected');
        } else {
            outlookBtn.classList.remove('connected');
            outlookBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>Connect Outlook';
            outlookBtn.onclick = connectOutlookAccount;
        }
    }

    // Initialize
    renderTemplates();
    renderConnectedAccounts();
    updateConnectButtons();

    if (typeof addSequenceStep === 'function') window.addSequenceStep = addSequenceStep;
    if (typeof closeSequenceModal === 'function') window.closeSequenceModal = closeSequenceModal;
    if (typeof closeTemplateModal === 'function') window.closeTemplateModal = closeTemplateModal;
    if (typeof connectGoogleAccount === 'function') window.connectGoogleAccount = connectGoogleAccount;
    if (typeof connectOutlookAccount === 'function') window.connectOutlookAccount = connectOutlookAccount;
    if (typeof deleteTemplate === 'function') window.deleteTemplate = deleteTemplate;
    if (typeof disconnectAccount === 'function') window.disconnectAccount = disconnectAccount;
    if (typeof editSequence === 'function') window.editSequence = editSequence;
    if (typeof editTemplate === 'function') window.editTemplate = editTemplate;
    if (typeof getElementById === 'function') window.getElementById = getElementById;
    if (typeof openSequenceModal === 'function') window.openSequenceModal = openSequenceModal;
    if (typeof openTemplateModal === 'function') window.openTemplateModal = openTemplateModal;
    if (typeof previewTemplate === 'function') window.previewTemplate = previewTemplate;
    if (typeof removeSequenceStep === 'function') window.removeSequenceStep = removeSequenceStep;
    if (typeof stopPropagation === 'function') window.stopPropagation = stopPropagation;
    if (typeof switchTab === 'function') window.switchTab = switchTab;
    if (typeof testConnection === 'function') window.testConnection = testConnection;
    if (typeof useTemplate === 'function') window.useTemplate = useTemplate;
})();
