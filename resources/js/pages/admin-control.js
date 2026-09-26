/* Vite page entry — IIFE preserves onclick globals */
(function () {
// Sample Data
    const subscriptionPlans = [
        { id: 1, name: 'Basic', price: 29, period: 'month', features: ['5 Users', '10GB Storage', 'Email Support'], active: true, featured: false },
        { id: 2, name: 'Professional', price: 79, period: 'month', features: ['20 Users', '100GB Storage', 'Priority Support', 'API Access'], active: true, featured: true },
        { id: 3, name: 'Enterprise', price: 199, period: 'month', features: ['Unlimited Users', '1TB Storage', '24/7 Support', 'API Access', 'Custom Integrations'], active: true, featured: false }
    ];

    const companyBilling = [
        { id: 1, company: 'Acme Corporation', plan: 'Professional', status: 'active', cycle: 'Monthly', amount: 79, nextBilling: '2026-02-01' },
        { id: 2, company: 'TechStart Inc', plan: 'Enterprise', status: 'active', cycle: 'Yearly', amount: 2388, nextBilling: '2027-01-01' },
        { id: 3, company: 'BrandCo', plan: 'Basic', status: 'trial', cycle: 'Trial', amount: 0, nextBilling: '2026-01-20' },
        { id: 4, company: 'ShopNow', plan: 'Professional', status: 'active', cycle: 'Monthly', amount: 79, nextBilling: '2026-02-01' },
        { id: 5, company: 'CloudTech', plan: 'Basic', status: 'expired', cycle: 'Monthly', amount: 29, nextBilling: '-' }
    ];

    // Available modules list
    const availableModules = [
        { id: 'dashboard', name: 'Dashboard', description: 'Main dashboard and overview', route: 'dashboard' },
        { id: 'time-tracking', name: 'Time Tracking', description: 'Track employee time and attendance', route: 'time-tracking' },
        { id: 'user-management', name: 'User Management', description: 'Manage users and permissions', route: 'user-management' },
        { id: 'employee-monitoring', name: 'Employee Monitoring', description: 'Monitor employee activity', route: 'employee-monitoring' },
        { id: 'phone-system', name: 'Phone System', description: 'VoIP phone system integration', route: 'phone-system' },
        { id: 'payroll', name: 'Payroll', description: 'Automated payroll processing', route: 'payroll' },
        { id: 'project-management', name: 'Project Management', description: 'Project tracking and management', route: 'project-management' },
        { id: 'messaging', name: 'Messaging', description: 'Internal messaging system', route: 'messaging' },
        { id: 'billing', name: 'Billing & Payments', description: 'Invoice and payment management', route: 'billing' },
        { id: 'client-management', name: 'Client Management', description: 'CRM and client database', route: 'client-management' },
        { id: 'tickets', name: 'Tickets & Helpdesk', description: 'Support ticket system', route: 'tickets' },
        { id: 'knowledge-base', name: 'Knowledge Base', description: 'Documentation and knowledge base', route: 'knowledge-base' },
        { id: 'integrations', name: 'Integrations', description: 'Third-party integrations', route: 'integrations' },
        { id: 'quotation-builder', name: 'Quotation Builder', description: 'Create and manage quotations', route: 'quotation-builder' },
        { id: 'calendar', name: 'Calendar', description: 'Calendar and scheduling', route: 'calendar' },
        { id: 'email-tracking', name: 'Email Tracking', description: 'Track email opens and clicks', route: 'email-tracking' },
        { id: 'openai', name: 'AI Assistant', description: 'OpenAI integration', route: 'openai' }
    ];

    // Company module access (which modules each company can access)
    const companyModuleAccess = {
        1: ['dashboard', 'time-tracking', 'user-management', 'employee-monitoring', 'project-management', 'billing', 'client-management'], // Acme Corporation
        2: availableModules.map(m => m.id), // TechStart Inc - all modules
        3: ['dashboard', 'time-tracking', 'user-management', 'employee-monitoring'], // BrandCo - limited
        4: ['dashboard', 'time-tracking', 'user-management', 'project-management', 'billing', 'client-management', 'messaging'], // ShopNow
        5: ['dashboard', 'billing'] // CloudTech - expired, minimal access
    };

    let currentCompanyId = null;

    const recentPayments = [
        { id: 1, company: 'Acme Corporation', amount: 79, date: '2026-01-01', status: 'completed', method: 'Credit Card' },
        { id: 2, company: 'TechStart Inc', amount: 2388, date: '2026-01-01', status: 'completed', method: 'Bank Transfer' },
        { id: 3, company: 'ShopNow', amount: 79, date: '2025-12-28', status: 'completed', method: 'Credit Card' },
        { id: 4, company: 'BrandCo', amount: 0, date: '2025-12-25', status: 'trial', method: 'Trial' }
    ];

    const companies = [
        { id: 1, name: 'Acme Corporation' },
        { id: 2, name: 'TechStart Inc' },
        { id: 3, name: 'BrandCo' },
        { id: 4, name: 'ShopNow' },
        { id: 5, name: 'CloudTech' }
    ];

    const features = [
        { id: 1, name: 'Time Tracking', description: 'Track employee time and attendance', enabled: true },
        { id: 2, name: 'User Management', description: 'Manage users and permissions', enabled: true },
        { id: 3, name: 'Phone System', description: 'VoIP phone system integration', enabled: false },
        { id: 4, name: 'Payroll', description: 'Automated payroll processing', enabled: true },
        { id: 5, name: 'Project Management', description: 'Project tracking and management', enabled: true },
        { id: 6, name: 'Messaging', description: 'Internal messaging system', enabled: true },
        { id: 7, name: 'Billing', description: 'Invoice and payment management', enabled: true },
        { id: 8, name: 'Client Management', description: 'CRM and client database', enabled: true },
        { id: 9, name: 'Tickets', description: 'Support ticket system', enabled: false },
        { id: 10, name: 'Knowledge Base', description: 'Documentation and knowledge base', enabled: true },
        { id: 11, name: 'Integrations', description: 'Third-party integrations', enabled: false },
        { id: 12, name: 'AI Assistant', description: 'OpenAI integration', enabled: false }
    ];

    const roles = [
        { id: 1, name: 'Super Admin', users: 2, permissions: 'All' },
        { id: 2, name: 'Admin', users: 5, permissions: 'Most' },
        { id: 3, name: 'Manager', users: 12, permissions: 'Limited' },
        { id: 4, name: 'Employee', users: 45, permissions: 'Basic' }
    ];

    const accessLogs = [
        { id: 1, text: 'Company "Acme Corporation" accessed Time Tracking feature', time: '2 hours ago', type: 'feature' },
        { id: 2, text: 'User "john.doe@acme.com" logged in', time: '3 hours ago', type: 'login' },
        { id: 3, text: 'Permissions updated for "TechStart Inc"', time: '5 hours ago', type: 'permission' },
        { id: 4, text: 'Company "BrandCo" accessed Billing feature', time: '1 day ago', type: 'feature' },
        { id: 5, text: 'User "admin@techstart.com" logged in', time: '1 day ago', type: 'login' }
    ];

    const systemSettings = [
        { id: 1, name: 'Maintenance Mode', description: 'Enable maintenance mode for system updates', enabled: false },
        { id: 2, name: 'Email Notifications', description: 'Send email notifications for system events', enabled: true },
        { id: 3, name: 'Two-Factor Authentication', description: 'Require 2FA for all admin accounts', enabled: true },
        { id: 4, name: 'API Rate Limiting', description: 'Enable rate limiting for API requests', enabled: true },
        { id: 5, name: 'Auto Backup', description: 'Automatically backup database daily', enabled: true }
    ];

    const users = [
        { id: 1, name: 'John Doe', email: 'john@admin.com', role: 'Super Admin', company: 'System', status: 'active' },
        { id: 2, name: 'Jane Smith', email: 'jane@admin.com', role: 'Admin', company: 'System', status: 'active' },
        { id: 3, name: 'Bob Johnson', email: 'bob@acme.com', role: 'Manager', company: 'Acme Corporation', status: 'active' },
        { id: 4, name: 'Alice Brown', email: 'alice@techstart.com', role: 'Admin', company: 'TechStart Inc', status: 'active' }
    ];

    // Render Functions
    function renderPlans() {
        const grid = document.getElementById('plansGrid');
        grid.innerHTML = subscriptionPlans.map(plan => `
            <div class="plan-card ${plan.featured ? 'featured' : ''}">
                <div class="plan-header">
                    <h4 class="plan-name">${plan.name}</h4>
                    ${plan.featured ? '<span class="plan-badge">Popular</span>' : ''}
                </div>
                <div class="plan-price">
                    $${plan.price}<span>/${plan.period}</span>
                </div>
                <ul class="plan-features">
                    ${plan.features.map(f => `<li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>${f}</li>`).join('')}
                </ul>
                <div class="plan-actions">
                    <button class="btn-sm btn-secondary" onclick="editPlan(${plan.id})">Edit</button>
                    <button class="btn-sm btn-secondary" onclick="deletePlan(${plan.id})">Delete</button>
                </div>
            </div>
        `).join('');
    }

    function renderBillingTable() {
        const tbody = document.getElementById('billingTableBody');
        tbody.innerHTML = companyBilling.map(company => `
            <tr>
                <td><strong>${company.company}</strong></td>
                <td>${company.plan}</td>
                <td><span class="status-badge ${company.status}">${company.status.charAt(0).toUpperCase() + company.status.slice(1)}</span></td>
                <td>${company.cycle}</td>
                <td>$${company.amount.toLocaleString()}</td>
                <td>${company.nextBilling}</td>
                <td>
                    <button class="btn-sm btn-secondary" onclick="manageBilling(${company.id})">Manage</button>
                </td>
            </tr>
        `).join('');
    }

    function renderPayments() {
        const list = document.getElementById('paymentsList');
        list.innerHTML = recentPayments.map(payment => `
            <div class="payment-item">
                <div class="payment-info">
                    <div class="payment-company">${payment.company}</div>
                    <p class="payment-details">${payment.date} • ${payment.method}</p>
                </div>
                <div class="payment-amount">$${payment.amount.toLocaleString()}</div>
                <span class="status-badge ${payment.status}">${payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}</span>
            </div>
        `).join('');
    }

    function renderCompanySelector() {
        const selector = document.getElementById('companySelector');
        selector.innerHTML = '<option value="">Select a company...</option>' + 
            companies.map(company => `<option value="${company.id}">${company.name}</option>`).join('');
    }

    function renderFeatures() {
        const grid = document.getElementById('featuresGrid');
        grid.innerHTML = features.map(feature => `
            <div class="feature-card">
                <div class="feature-info">
                    <div class="feature-name">${feature.name}</div>
                    <p class="feature-desc">${feature.description}</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" ${feature.enabled ? 'checked' : ''} onchange="toggleFeature(${feature.id}, this.checked)">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        `).join('');
    }

    function renderRoles() {
        const list = document.getElementById('rolesList');
        list.innerHTML = roles.map(role => `
            <div class="role-card">
                <div class="role-info">
                    <div class="role-name">${role.name}</div>
                    <p class="role-users">${role.users} users • ${role.permissions} permissions</p>
                </div>
                <div>
                    <button class="btn-sm btn-secondary" onclick="editRole(${role.id})">Edit</button>
                    <button class="btn-sm btn-secondary" onclick="deleteRole(${role.id})">Delete</button>
                </div>
            </div>
        `).join('');
    }

    function renderAccessLogs() {
        const logs = document.getElementById('accessLogs');
        logs.innerHTML = accessLogs.map(log => `
            <div class="log-item">
                <div class="log-text">${log.text}</div>
                <p class="log-meta">${log.time}</p>
            </div>
        `).join('');
    }

    function renderSystemSettings() {
        const settings = document.getElementById('systemSettings');
        settings.innerHTML = systemSettings.map(setting => `
            <div class="setting-item">
                <div class="setting-info">
                    <div class="setting-name">${setting.name}</div>
                    <p class="setting-desc">${setting.description}</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" ${setting.enabled ? 'checked' : ''} onchange="toggleSetting(${setting.id}, this.checked)">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        `).join('');
    }

    function renderUsers() {
        const tbody = document.getElementById('usersTableBody');
        tbody.innerHTML = users.map(user => `
            <tr>
                <td><strong>${user.name}</strong></td>
                <td>${user.email}</td>
                <td>${user.role}</td>
                <td>${user.company}</td>
                <td><span class="status-badge active">${user.status.charAt(0).toUpperCase() + user.status.slice(1)}</span></td>
                <td>
                    <button class="btn-sm btn-secondary" onclick="editUser(${user.id})">Edit</button>
                    <button class="btn-sm btn-secondary" onclick="deleteUser(${user.id})">Delete</button>
                </td>
            </tr>
        `).join('');
    }

    function renderHealthMetrics() {
        const metrics = document.getElementById('healthMetrics');
        metrics.innerHTML = `
            <div class="health-metric good">
                <div class="health-metric-value">99.9%</div>
                <p class="health-metric-label">Uptime</p>
            </div>
            <div class="health-metric good">
                <div class="health-metric-value">245ms</div>
                <p class="health-metric-label">Avg Response</p>
            </div>
            <div class="health-metric warning">
                <div class="health-metric-value">78%</div>
                <p class="health-metric-label">Storage Used</p>
            </div>
            <div class="health-metric good">
                <div class="health-metric-value">1,247</div>
                <p class="health-metric-label">Active Users</p>
            </div>
        `;
    }

    // Event Handlers
    function loadCompanyAccess() {
        const companyId = document.getElementById('companySelector').value;
        if (companyId) {
            document.getElementById('featureAccessSection').style.display = 'block';
            document.getElementById('rolePermissionsSection').style.display = 'block';
            renderFeatures();
            renderRoles();
        } else {
            document.getElementById('featureAccessSection').style.display = 'none';
            document.getElementById('rolePermissionsSection').style.display = 'none';
        }
    }

    function toggleFeature(id, enabled) {
        console.log(`Feature ${id} ${enabled ? 'enabled' : 'disabled'}`);
        // Add API call here
    }

    function toggleSetting(id, enabled) {
        console.log(`Setting ${id} ${enabled ? 'enabled' : 'disabled'}`);
        // Add API call here
    }

    function saveAccessSettings() {
        alert('Access settings saved successfully!');
        // Add API call here
    }

    function saveSystemSettings() {
        alert('System settings saved successfully!');
        // Add API call here
    }

    function openPlanModal() {
        document.getElementById('planModal').classList.add('active');
    }

    function closePlanModal() {
        document.getElementById('planModal').classList.remove('active');
    }

    function savePlan() {
        alert('Plan saved successfully!');
        closePlanModal();
        // Add API call here
    }

    function editPlan(id) {
        console.log('Edit plan:', id);
        openPlanModal();
    }

    function deletePlan(id) {
        if (confirm('Are you sure you want to delete this plan?')) {
            console.log('Delete plan:', id);
            // Add API call here
        }
    }

    function manageBilling(id) {
        currentCompanyId = id;
        const company = companyBilling.find(c => c.id === id);
        if (!company) return;

        // Set company name in modal
        document.getElementById('companyModuleName').textContent = company.company;

        // Load company's current module access
        const companyModules = companyModuleAccess[id] || [];
        
        // Render modules
        renderCompanyModules(companyModules);
        
        // Update module count
        updateModuleCount();
        
        // Open modal
        document.getElementById('companyModuleModal').classList.add('active');
    }

    function renderCompanyModules(selectedModules = []) {
        const grid = document.getElementById('modulesGrid');
        grid.innerHTML = availableModules.map(module => {
            const isSelected = selectedModules.includes(module.id);
            return `
                <div class="module-card ${isSelected ? 'selected' : ''}" data-module-id="${module.id}" onclick="toggleModule(this)">
                    <div class="module-checkbox"></div>
                    <div class="module-info">
                        <div class="module-name">${module.name}</div>
                        <p class="module-desc">${module.description}</p>
                    </div>
                </div>
            `;
        }).join('');
    }

    function toggleModule(cardElement) {
        cardElement.classList.toggle('selected');
        updateModuleCount();
    }

    function selectAllModules() {
        const cards = document.querySelectorAll('.module-card');
        cards.forEach(card => card.classList.add('selected'));
        updateModuleCount();
    }

    function deselectAllModules() {
        const cards = document.querySelectorAll('.module-card');
        cards.forEach(card => card.classList.remove('selected'));
        updateModuleCount();
    }

    function updateModuleCount() {
        const selectedCount = document.querySelectorAll('.module-card.selected').length;
        const totalCount = availableModules.length;
        document.getElementById('moduleCount').textContent = `${selectedCount} of ${totalCount} modules selected`;
    }

    function saveCompanyModules() {
        if (!currentCompanyId) return;

        const selectedCards = document.querySelectorAll('.module-card.selected');
        const selectedModules = Array.from(selectedCards).map(card => {
            return card.getAttribute('data-module-id');
        }).filter(Boolean);

        // Update company module access
        companyModuleAccess[currentCompanyId] = selectedModules;

        const company = companyBilling.find(c => c.id === currentCompanyId);
        const moduleNames = selectedModules.map(id => {
            const module = availableModules.find(m => m.id === id);
            return module ? module.name : id;
        }).join(', ');

        // Show success message
        alert(`Module access updated successfully for ${company?.company}!\n\nSelected modules: ${selectedModules.length}\n\nModules: ${moduleNames}`);

        // Close modal
        closeCompanyModuleModal();

        // Here you would typically make an API call to save the changes
        // Example: 
        // fetch('/api/companies/' + currentCompanyId + '/modules', {
        //     method: 'POST',
        //     headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        //     body: JSON.stringify({ modules: selectedModules })
        // })
    }

    function closeCompanyModuleModal() {
        document.getElementById('companyModuleModal').classList.remove('active');
        currentCompanyId = null;
    }

    function openRoleModal() {
        alert('Role creation modal would open here');
    }

    function editRole(id) {
        console.log('Edit role:', id);
    }

    function deleteRole(id) {
        if (confirm('Are you sure you want to delete this role?')) {
            console.log('Delete role:', id);
        }
    }

    function openUserModal() {
        alert('User creation modal would open here');
    }

    function editUser(id) {
        console.log('Edit user:', id);
    }

    function deleteUser(id) {
        if (confirm('Are you sure you want to delete this user?')) {
            console.log('Delete user:', id);
        }
    }

    function refreshSystemHealth() {
        renderHealthMetrics();
        alert('System health refreshed!');
    }

    // Support & Override Functions
    const supportTickets = [
        { id: 1, company: 'Acme Corporation', subject: 'Cannot access Time Tracking module', status: 'open', priority: 'high', created: '2 hours ago', type: 'module-access' },
        { id: 2, company: 'BrandCo', subject: 'Billing module showing error', status: 'in-progress', priority: 'medium', created: '5 hours ago', type: 'technical' },
        { id: 3, company: 'ShopNow', subject: 'Need temporary access to Project Management', status: 'open', priority: 'low', created: '1 day ago', type: 'access-request' },
        { id: 4, company: 'CloudTech', subject: 'User Management permissions issue', status: 'resolved', priority: 'high', created: '2 days ago', type: 'permissions' }
    ];

    const supportActionsLog = [
        { id: 1, type: 'emergency', company: 'Acme Corporation', action: 'Granted emergency full access', admin: 'John Doe', time: '2 hours ago', duration: '24 hours' },
        { id: 2, type: 'grant', company: 'BrandCo', action: 'Granted access to Phone System module', admin: 'Jane Smith', time: '5 hours ago', notes: 'Support request for testing' },
        { id: 3, type: 'bypass', company: 'ShopNow', action: 'Bypassed module restrictions for troubleshooting', admin: 'John Doe', time: '1 day ago', notes: 'Troubleshooting billing issue' },
        { id: 4, type: 'grant', company: 'CloudTech', action: 'Granted access to AI Assistant module', admin: 'Jane Smith', time: '2 days ago', notes: 'Trial period extension' }
    ];

    const activeSupportSessions = [
        { id: 1, company: 'Acme Corporation', type: 'Emergency Access', started: '2 hours ago', expires: '22 hours remaining', admin: 'John Doe' },
        { id: 2, company: 'BrandCo', type: 'Module Grant', started: '5 hours ago', expires: '19 hours remaining', admin: 'Jane Smith' }
    ];

    let currentReviewCompanyId = null;

    function openCompanyModuleReview() {
        document.getElementById('companyModuleReviewModal').classList.add('active');
        // Populate company selector if needed
    }

    function closeCompanyModuleReviewModal() {
        document.getElementById('companyModuleReviewModal').classList.remove('active');
        currentReviewCompanyId = null;
    }

    function openEmergencyAccess() {
        const select = document.getElementById('emergencyCompanySelect');
        select.innerHTML = '<option value="">Choose a company...</option>' + 
            companyBilling.map(company => `<option value="${company.id}">${company.company}</option>`).join('');
        document.getElementById('emergencyAccessModal').classList.add('active');
    }

    function closeEmergencyAccessModal() {
        document.getElementById('emergencyAccessModal').classList.remove('active');
        document.getElementById('emergencyAccessForm').reset();
    }

    function grantEmergencyAccess() {
        const companyId = document.getElementById('emergencyCompanySelect').value;
        const duration = document.getElementById('emergencyDuration').value;
        const reason = document.getElementById('emergencyReason').value;
        const notify = document.getElementById('emergencyNotify').checked;

        if (!companyId || !reason) {
            alert('Please fill in all required fields');
            return;
        }

        const company = companyBilling.find(c => c.id === parseInt(companyId));
        const durationText = duration === '1' ? '1 Hour' : duration === '4' ? '4 Hours' : duration === '24' ? '24 Hours' : '7 Days';

        // Log the action
        supportActionsLog.unshift({
            id: supportActionsLog.length + 1,
            type: 'emergency',
            company: company.company,
            action: `Granted emergency full access for ${durationText}`,
            admin: 'Current Admin',
            time: 'Just now',
            duration: durationText,
            reason: reason
        });

        // Add to active sessions
        activeSupportSessions.push({
            id: activeSupportSessions.length + 1,
            company: company.company,
            type: 'Emergency Access',
            started: 'Just now',
            expires: `${durationText} remaining`,
            admin: 'Current Admin'
        });

        alert(`Emergency access granted to ${company.company} for ${durationText}.\nReason: ${reason}\n${notify ? 'Company admin has been notified.' : ''}`);
        
        closeEmergencyAccessModal();
        renderSupportData();
    }

    function openSupportTickets() {
        renderSupportTickets();
        document.getElementById('supportTicketsModal').classList.add('active');
    }

    function closeSupportTicketsModal() {
        document.getElementById('supportTicketsModal').classList.remove('active');
    }

    function openBypassLog() {
        renderSupportActionsLog();
        // Could open in a modal or scroll to the log section
        document.getElementById('supportActionsLog').scrollIntoView({ behavior: 'smooth' });
    }

    function grantAllModulesForSupport() {
        const cards = document.querySelectorAll('#reviewModulesGrid .module-card');
        cards.forEach(card => card.classList.add('selected'));
        updateReviewModuleCount();
    }

    function revokeAllModulesForSupport() {
        const cards = document.querySelectorAll('#reviewModulesGrid .module-card');
        cards.forEach(card => card.classList.remove('selected'));
        updateReviewModuleCount();
    }

    function updateReviewModuleCount() {
        const selectedCount = document.querySelectorAll('#reviewModulesGrid .module-card.selected').length;
        const totalCount = availableModules.length;
        document.getElementById('reviewModuleCount').textContent = `${selectedCount} of ${totalCount} modules selected`;
    }

    function saveSupportModuleChanges() {
        if (!currentReviewCompanyId) {
            alert('Please select a company first');
            return;
        }

        const selectedCards = document.querySelectorAll('#reviewModulesGrid .module-card.selected');
        const selectedModules = Array.from(selectedCards).map(card => {
            return card.getAttribute('data-module-id');
        }).filter(Boolean);

        const notes = document.getElementById('supportNotes').value;

        // Update company module access
        companyModuleAccess[currentReviewCompanyId] = selectedModules;

        // Log the action
        const company = companyBilling.find(c => c.id === currentReviewCompanyId);
        supportActionsLog.unshift({
            id: supportActionsLog.length + 1,
            type: 'grant',
            company: company.company,
            action: `Modified module access (${selectedModules.length} modules)`,
            admin: 'Current Admin',
            time: 'Just now',
            notes: notes || 'No notes provided'
        });

        alert(`Module access updated for ${company.company}!\n\nSelected modules: ${selectedModules.length}\n\nThis action has been logged.`);
        
        closeCompanyModuleReviewModal();
        renderSupportData();
    }

    function renderSupportCompanies() {
        const tbody = document.getElementById('supportCompaniesTableBody');
        tbody.innerHTML = companyBilling.map(company => {
            const modules = companyModuleAccess[company.id] || [];
            const moduleNames = modules.map(id => {
                const module = availableModules.find(m => m.id === id);
                return module ? module.name : id;
            }).slice(0, 3).join(', ');
            const moreCount = modules.length > 3 ? ` +${modules.length - 3} more` : '';
            
            return `
                <tr>
                    <td><strong>${company.company}</strong></td>
                    <td>${company.plan}</td>
                    <td><span class="status-badge ${company.status}">${company.status.charAt(0).toUpperCase() + company.status.slice(1)}</span></td>
                    <td>${moduleNames}${moreCount}</td>
                    <td>2 days ago</td>
                    <td>
                        <button class="btn-sm btn-secondary" onclick="reviewCompanyModules(${company.id})">Review</button>
                        <button class="btn-sm btn-secondary" onclick="manageBilling(${company.id})">Manage</button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function reviewCompanyModules(companyId) {
        currentReviewCompanyId = companyId;
        const company = companyBilling.find(c => c.id === companyId);
        document.getElementById('reviewCompanyName').textContent = company.company;
        
        const companyModules = companyModuleAccess[companyId] || [];
        renderReviewModules(companyModules);
        updateReviewModuleCount();
        
        document.getElementById('companyModuleReviewModal').classList.add('active');
    }

    function renderReviewModules(selectedModules = []) {
        const grid = document.getElementById('reviewModulesGrid');
        grid.innerHTML = availableModules.map(module => {
            const isSelected = selectedModules.includes(module.id);
            return `
                <div class="module-card ${isSelected ? 'selected' : ''}" data-module-id="${module.id}" onclick="toggleModule(this)">
                    <div class="module-checkbox"></div>
                    <div class="module-info">
                        <div class="module-name">${module.name}</div>
                        <p class="module-desc">${module.description}</p>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderSupportSessions() {
        const list = document.getElementById('supportSessionsList');
        document.getElementById('activeSessionsCount').textContent = `${activeSupportSessions.length} active`;
        
        if (activeSupportSessions.length === 0) {
            list.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 2rem;">No active support sessions</p>';
            return;
        }
        
        list.innerHTML = activeSupportSessions.map(session => `
            <div class="support-session-card">
                <div class="support-session-info">
                    <div class="support-session-company">${session.company}</div>
                    <p class="support-session-details">${session.type} • Started ${session.started} • Expires in ${session.expires}</p>
                </div>
                <div class="support-session-time">By ${session.admin}</div>
                <div class="support-session-actions">
                    <button class="btn-sm btn-secondary" onclick="endSupportSession(${session.id})">End Session</button>
                </div>
            </div>
        `).join('');
    }

    function endSupportSession(sessionId) {
        if (confirm('Are you sure you want to end this support session?')) {
            const index = activeSupportSessions.findIndex(s => s.id === sessionId);
            if (index > -1) {
                activeSupportSessions.splice(index, 1);
                renderSupportSessions();
            }
        }
    }

    function renderSupportActionsLog() {
        const log = document.getElementById('supportActionsLog');
        log.innerHTML = supportActionsLog.map(action => {
            const iconClass = action.type === 'emergency' ? 'emergency' : action.type === 'grant' ? 'grant' : 'bypass';
            const iconSvg = action.type === 'emergency' 
                ? '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4"/><path d="M12 16h.01"/>'
                : action.type === 'grant'
                ? '<polyline points="20 6 9 17 4 12"/>'
                : '<path d="M18 6L6 18M6 6l12 12"/>';
            
            return `
                <div class="support-action-log-item">
                    <div class="support-action-log-icon ${iconClass}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            ${iconSvg}
                        </svg>
                    </div>
                    <div class="support-action-log-content">
                        <div class="support-action-log-text">
                            <strong>${action.company}</strong>: ${action.action}
                        </div>
                        <p class="support-action-log-meta">
                            ${action.admin} • ${action.time}${action.notes ? ` • ${action.notes}` : ''}
                        </p>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderSupportTickets() {
        const list = document.getElementById('supportTicketsList');
        list.innerHTML = supportTickets.map(ticket => `
            <div class="support-ticket-card" onclick="viewSupportTicket(${ticket.id})">
                <div class="support-ticket-header">
                    <span class="support-ticket-id">#TKT-${ticket.id.toString().padStart(4, '0')}</span>
                    <span class="support-ticket-status ${ticket.status}">${ticket.status.replace('-', ' ').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')}</span>
                </div>
                <div class="support-ticket-subject">${ticket.subject}</div>
                <p class="support-ticket-meta">${ticket.company} • ${ticket.created} • Priority: ${ticket.priority}</p>
            </div>
        `).join('');
    }

    function viewSupportTicket(ticketId) {
        const ticket = supportTickets.find(t => t.id === ticketId);
        if (ticket) {
            alert(`Support Ticket #TKT-${ticket.id.toString().padStart(4, '0')}\n\nCompany: ${ticket.company}\nSubject: ${ticket.subject}\nStatus: ${ticket.status}\nPriority: ${ticket.priority}\nCreated: ${ticket.created}`);
        }
    }

    function filterSupportCompanies() {
        const search = document.getElementById('supportCompanySearch').value.toLowerCase();
        const moduleFilter = document.getElementById('supportModuleFilter').value;
        
        // Filter logic would go here
        renderSupportCompanies();
    }

    function filterSupportActions() {
        const filter = document.getElementById('supportActionFilter').value;
        // Filter logic would go here
        renderSupportActionsLog();
    }

    function filterTickets() {
        const statusFilter = document.getElementById('ticketStatusFilter').value;
        const search = document.getElementById('ticketSearch').value.toLowerCase();
        // Filter logic would go here
        renderSupportTickets();
    }

    function renderSupportData() {
        renderSupportCompanies();
        renderSupportSessions();
        renderSupportActionsLog();
        
        // Populate module filter
        const moduleFilter = document.getElementById('supportModuleFilter');
        moduleFilter.innerHTML = '<option value="all">All Modules</option>' + 
            availableModules.map(m => `<option value="${m.id}">${m.name}</option>`).join('');
    }

    // Filter handlers
    document.getElementById('billingFilter')?.addEventListener('change', function() {
        console.log('Filter billing by:', this.value);
        renderBillingTable();
    });

    document.getElementById('logFilter')?.addEventListener('change', function() {
        console.log('Filter logs by:', this.value);
        renderAccessLogs();
    });

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        renderPlans();
        renderBillingTable();
        renderPayments();
        renderCompanySelector();
        renderRoles();
        renderAccessLogs();
        renderSystemSettings();
        renderUsers();
        renderHealthMetrics();
        renderSupportData();
    });

    if (typeof closeCompanyModuleModal === 'function') window.closeCompanyModuleModal = closeCompanyModuleModal;
    if (typeof closeCompanyModuleReviewModal === 'function') window.closeCompanyModuleReviewModal = closeCompanyModuleReviewModal;
    if (typeof closeEmergencyAccessModal === 'function') window.closeEmergencyAccessModal = closeEmergencyAccessModal;
    if (typeof closePlanModal === 'function') window.closePlanModal = closePlanModal;
    if (typeof closeSupportTicketsModal === 'function') window.closeSupportTicketsModal = closeSupportTicketsModal;
    if (typeof deletePlan === 'function') window.deletePlan = deletePlan;
    if (typeof deleteRole === 'function') window.deleteRole = deleteRole;
    if (typeof deleteUser === 'function') window.deleteUser = deleteUser;
    if (typeof deselectAllModules === 'function') window.deselectAllModules = deselectAllModules;
    if (typeof editPlan === 'function') window.editPlan = editPlan;
    if (typeof editRole === 'function') window.editRole = editRole;
    if (typeof editUser === 'function') window.editUser = editUser;
    if (typeof endSupportSession === 'function') window.endSupportSession = endSupportSession;
    if (typeof filterSupportActions === 'function') window.filterSupportActions = filterSupportActions;
    if (typeof filterSupportCompanies === 'function') window.filterSupportCompanies = filterSupportCompanies;
    if (typeof filterTickets === 'function') window.filterTickets = filterTickets;
    if (typeof grantAllModulesForSupport === 'function') window.grantAllModulesForSupport = grantAllModulesForSupport;
    if (typeof grantEmergencyAccess === 'function') window.grantEmergencyAccess = grantEmergencyAccess;
    if (typeof loadCompanyAccess === 'function') window.loadCompanyAccess = loadCompanyAccess;
    if (typeof manageBilling === 'function') window.manageBilling = manageBilling;
    if (typeof openBypassLog === 'function') window.openBypassLog = openBypassLog;
    if (typeof openCompanyModuleReview === 'function') window.openCompanyModuleReview = openCompanyModuleReview;
    if (typeof openEmergencyAccess === 'function') window.openEmergencyAccess = openEmergencyAccess;
    if (typeof openPlanModal === 'function') window.openPlanModal = openPlanModal;
    if (typeof openRoleModal === 'function') window.openRoleModal = openRoleModal;
    if (typeof openSupportTickets === 'function') window.openSupportTickets = openSupportTickets;
    if (typeof openUserModal === 'function') window.openUserModal = openUserModal;
    if (typeof refreshSystemHealth === 'function') window.refreshSystemHealth = refreshSystemHealth;
    if (typeof reviewCompanyModules === 'function') window.reviewCompanyModules = reviewCompanyModules;
    if (typeof revokeAllModulesForSupport === 'function') window.revokeAllModulesForSupport = revokeAllModulesForSupport;
    if (typeof saveAccessSettings === 'function') window.saveAccessSettings = saveAccessSettings;
    if (typeof saveCompanyModules === 'function') window.saveCompanyModules = saveCompanyModules;
    if (typeof savePlan === 'function') window.savePlan = savePlan;
    if (typeof saveSupportModuleChanges === 'function') window.saveSupportModuleChanges = saveSupportModuleChanges;
    if (typeof saveSystemSettings === 'function') window.saveSystemSettings = saveSystemSettings;
    if (typeof selectAllModules === 'function') window.selectAllModules = selectAllModules;
    if (typeof toggleFeature === 'function') window.toggleFeature = toggleFeature;
    if (typeof toggleModule === 'function') window.toggleModule = toggleModule;
    if (typeof toggleSetting === 'function') window.toggleSetting = toggleSetting;
    if (typeof viewSupportTicket === 'function') window.viewSupportTicket = viewSupportTicket;
})();
