<script>
    // API Configuration
    const API_BASE = '/admin/api';
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // Helper function for API calls
    async function apiCall(url, method = 'GET', data = null) {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
            },
        };

        if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(API_BASE + url, options);
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.message || 'An error occurred');
            }
            
            return result;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    // Global data storage
    let subscriptionPlans = [];
    let companyBilling = [];
    let recentPayments = [];
    let companies = [];
    let availableModules = [];
    let systemSettings = [];
    let users = [];
    let stats = {};

    // Sample Data (fallback data - will be replaced by API data)
    const sampleSubscriptionPlans = [
        { id: 1, name: 'Basic', price: 29, period: 'month', features: ['5 Users', '10GB Storage', 'Email Support'], active: true, featured: false },
        { id: 2, name: 'Professional', price: 79, period: 'month', features: ['20 Users', '100GB Storage', 'Priority Support', 'API Access'], active: true, featured: true },
        { id: 3, name: 'Enterprise', price: 199, period: 'month', features: ['Unlimited Users', '1TB Storage', '24/7 Support', 'API Access', 'Custom Integrations'], active: true, featured: false }
    ];

    // Company module access (which modules each company can access) - populated from API
    let companyModuleAccess = {};

    let currentCompanyId = null;

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


    // Render Functions
    function renderPlans() {
        const grid = document.getElementById('plansGrid');
        if (! grid) return;
        
        grid.innerHTML = subscriptionPlans.map(plan => {
            const period = plan.billing_cycle === 'yearly' ? 'year' : 'month';
            const features = Array.isArray(plan.features) ? plan.features : [];
            return `
                <div class="plan-card ${plan.is_featured ? 'featured' : ''}">
                    <div class="plan-header">
                        <h4 class="plan-name">${plan.name}</h4>
                        ${plan.is_featured ? '<span class="plan-badge">Popular</span>' : ''}
                    </div>
                    <div class="plan-price">
                        $${plan.price}<span>/${period}</span>
                    </div>
                    <ul class="plan-features">
                        ${features.map(f => `<li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>${f}</li>`).join('')}
                    </ul>
                    <div class="plan-actions">
                        <button class="btn-sm btn-secondary" onclick="editPlan(${plan.id})">Edit</button>
                        <button class="btn-sm btn-secondary" onclick="deletePlan(${plan.id})">Delete</button>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderBillingTable() {
        const tbody = document.getElementById('billingTableBody');
        if (! tbody) return;
        
        tbody.innerHTML = companyBilling.map(company => `
            <tr>
                <td><strong>${company.name}</strong></td>
                <td>${company.plan || 'N/A'}</td>
                <td><span class="status-badge ${company.status}">${company.status.charAt(0).toUpperCase() + company.status.slice(1)}</span></td>
                <td>${company.billing_cycle ? company.billing_cycle.charAt(0).toUpperCase() + company.billing_cycle.slice(1) : 'N/A'}</td>
                <td>$${company.amount.toLocaleString()}</td>
                <td>${company.next_billing || '-'}</td>
                <td>
                    <button class="btn-sm btn-secondary" onclick="manageBilling(${company.id})">Manage</button>
                </td>
            </tr>
        `).join('');
    }

    function renderPayments() {
        const list = document.getElementById('paymentsList');
        if (! list) return;
        list.innerHTML = recentPayments.map(payment => `
            <div class="payment-item">
                <div class="payment-info">
                    <div class="payment-company">${payment.company}</div>
                    <p class="payment-details">${payment.date} â€¢ ${payment.method}</p>
                </div>
                <div class="payment-amount">$${payment.amount.toLocaleString()}</div>
                <span class="status-badge ${payment.status}">${payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}</span>
            </div>
        `).join('');
    }

    function renderCompanySelector() {
        const selector = document.getElementById('companySelector');
        if (! selector) return;
        selector.innerHTML = '<option value="">Select a company...</option>' + 
            companies.map(company => `<option value="${company.id}">${company.name}</option>`).join('');
    }

    async function renderFeatures() {
        const grid = document.getElementById('featuresGrid');
        if (!grid) return;
        
        // Use modules from database if available, otherwise use sample data
        const featuresToRender = availableModules.length > 0 ? availableModules : [];
        
        if (featuresToRender.length === 0) {
            grid.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 2rem;">No modules available</p>';
            return;
        }
        
        grid.innerHTML = featuresToRender.map(module => {
            // Check if company has this module (when a company is selected)
            const companyId = document.getElementById('companySelector')?.value;
            let isEnabled = true;
            if (companyId && companyModuleAccess[companyId]) {
                isEnabled = companyModuleAccess[companyId].includes(module.slug || module.id);
            }
            
            return `
                <div class="feature-card">
                    <div class="feature-info">
                        <div class="feature-name">${module.name}</div>
                        <p class="feature-desc">${module.description || ''}</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" ${isEnabled ? 'checked' : ''} onchange="toggleFeature('${module.slug || module.id}', this.checked, ${companyId || 'null'})">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            `;
        }).join('');
    }

    function renderRoles() {
        const list = document.getElementById('rolesList');
        if (! list) return;
        list.innerHTML = roles.map(role => `
            <div class="role-card">
                <div class="role-info">
                    <div class="role-name">${role.name}</div>
                    <p class="role-users">${role.users} users â€¢ ${role.permissions} permissions</p>
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
        if (! logs) return;
        logs.innerHTML = accessLogs.map(log => `
            <div class="log-item">
                <div class="log-text">${log.text}</div>
                <p class="log-meta">${log.time}</p>
            </div>
        `).join('');
    }

    function renderSystemSettings() {
        const settings = document.getElementById('systemSettings');
        if (!settings) return;
        
        if (systemSettings.length === 0) {
            settings.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 2rem;">No settings available</p>';
            return;
        }
        
        settings.innerHTML = systemSettings.map(setting => {
            const checked = setting.type === 'boolean' ? (setting.enabled !== false && setting.value !== '0' && setting.value !== 'false') : false;
            return `
                <div class="setting-item" data-key="${setting.key}">
                    <div class="setting-info">
                        <div class="setting-name">${setting.name}</div>
                        <p class="setting-desc">${setting.description || ''}</p>
                    </div>
                    ${setting.type === 'boolean' ? `
                        <label class="toggle-switch">
                            <input type="checkbox" ${checked ? 'checked' : ''} onchange="toggleSetting('${setting.key}', this.checked)">
                            <span class="toggle-slider"></span>
                        </label>
                    ` : `<span class="setting-value">${setting.value}</span>`}
                </div>
            `;
        }).join('');
    }

    function renderUsers() {
        const tbody = document.getElementById('usersTableBody');
        if (!tbody) return;
        
        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 2rem;">No users available</td></tr>';
            return;
        }
        
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
        if (! metrics) return;
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
    async function loadCompanyAccess() {
        const companyId = document.getElementById('companySelector').value;
        if (companyId) {
            document.getElementById('featureAccessSection').style.display = 'block';
            document.getElementById('rolePermissionsSection').style.display = 'block';
            
            // Load company modules if not already loaded
            if (!companyModuleAccess[companyId]) {
                const modules = await loadCompanyModules(companyId);
                companyModuleAccess[companyId] = modules;
            }
            
            await renderFeatures();
            renderRoles();
        } else {
            document.getElementById('featureAccessSection').style.display = 'none';
            document.getElementById('rolePermissionsSection').style.display = 'none';
        }
    }

    async function toggleFeature(moduleSlug, enabled, companyId) {
        if (!companyId) {
            console.log('No company selected');
            return;
        }
        
        try {
            // Find module ID from slug
            const module = availableModules.find(m => (m.slug || m.id) === moduleSlug);
            if (!module) {
                console.error('Module not found');
                return;
            }
            
            // Update company module access
            if (!companyModuleAccess[companyId]) {
                companyModuleAccess[companyId] = [];
            }
            
            if (enabled && !companyModuleAccess[companyId].includes(moduleSlug)) {
                companyModuleAccess[companyId].push(moduleSlug);
            } else if (!enabled) {
                companyModuleAccess[companyId] = companyModuleAccess[companyId].filter(m => m !== moduleSlug);
            }
            
            // Save to backend
            await apiCall(`/companies/${companyId}/modules`, 'PUT', { 
                modules: companyModuleAccess[companyId] 
            });
        } catch (error) {
            console.error('Error toggling feature:', error);
            alert('Error updating module access: ' + error.message);
        }
    }

    async function toggleSetting(key, enabled) {
        try {
            await apiCall(`/settings/${key}`, 'PUT', { value: enabled, type: 'boolean' });
            // Optionally show a success message or update UI
        } catch (error) {
            alert('Error updating setting: ' + error.message);
            // Revert the checkbox
            const checkbox = document.querySelector(`.setting-item[data-key="${key}"] input[type="checkbox"]`);
            if (checkbox) checkbox.checked = !enabled;
        }
    }

    function saveAccessSettings() {
        alert('Access settings saved successfully!');
        // Add API call here
    }

    async function saveSystemSettings() {
        const settings = {};
        const settingInputs = document.querySelectorAll('#systemSettings input[type="checkbox"]');
        
        settingInputs.forEach(input => {
            const key = input.closest('.setting-item').dataset.key;
            if (key) {
                settings[key] = input.checked;
            }
        });

        try {
            await apiCall('/settings', 'PUT', { settings });
            alert('System settings saved successfully!');
        } catch (error) {
            alert('Error saving settings: ' + error.message);
        }
    }

    function openAddPlanModal() {
        document.getElementById('addPlanModal').classList.add('active');
    }

    function closeAddPlanModal() {
        document.getElementById('addPlanModal').classList.remove('active');
        document.getElementById('addPlanForm').reset();
    }

    async function saveAddPlan(event) {
        if (event) event.preventDefault();
        
        const form = document.getElementById('addPlanForm');
        const formData = new FormData(form);
        
        const planData = {
            name: formData.get('name'),
            description: formData.get('description'),
            price: parseFloat(formData.get('price')),
            billing_cycle: formData.get('billing_cycle'),
            features: formData.get('features') ? formData.get('features').split(',').map(f => f.trim()) : [],
            is_featured: document.getElementById('add_plan_is_featured').checked,
            is_active: document.getElementById('add_plan_is_active').checked,
        };
        
        try {
            await apiCall('/plans', 'POST', planData);
            alert('Plan added successfully!');
            closeAddPlanModal();
            await loadPlans();
            renderPlans();
        } catch (error) {
            alert('Error adding plan: ' + error.message);
        }
    }

    function openEditPlanModal() {
        document.getElementById('editPlanModal').classList.add('active');
    }

    function closeEditPlanModal() {
        document.getElementById('editPlanModal').classList.remove('active');
        document.getElementById('editPlanForm').reset();
    }

    async function saveEditPlan(event) {
        if (event) event.preventDefault();
        
        const form = document.getElementById('editPlanForm');
        const formData = new FormData(form);
        const planId = formData.get('plan_id');
        
        const planData = {
            name: formData.get('name'),
            description: formData.get('description'),
            price: parseFloat(formData.get('price')),
            billing_cycle: formData.get('billing_cycle'),
            features: formData.get('features') ? formData.get('features').split(',').map(f => f.trim()) : [],
            is_featured: document.getElementById('edit_plan_is_featured').checked,
            is_active: document.getElementById('edit_plan_is_active').checked,
        };
        
        try {
            await apiCall(`/plans/${planId}`, 'PUT', planData);
            alert('Plan updated successfully!');
            closeEditPlanModal();
            await loadPlans();
            renderPlans();
        } catch (error) {
            alert('Error updating plan: ' + error.message);
        }
    }

    async function editPlan(id) {
        try {
            const result = await apiCall(`/plans/${id}`);
            const plan = Array.isArray(result) ? result.find(p => p.id === id) : result;
            
            document.getElementById('edit_plan_id').value = plan.id;
            document.getElementById('edit_plan_name').value = plan.name;
            document.getElementById('edit_plan_description').value = plan.description || '';
            document.getElementById('edit_plan_price').value = plan.price;
            document.getElementById('edit_plan_billing_cycle').value = plan.billing_cycle;
            document.getElementById('edit_plan_features').value = Array.isArray(plan.features) ? plan.features.join(', ') : '';
            document.getElementById('edit_plan_is_featured').checked = plan.is_featured || false;
            document.getElementById('edit_plan_is_active').checked = plan.is_active !== false;
            
            openEditPlanModal();
        } catch (error) {
            alert('Error loading plan: ' + error.message);
        }
    }

    async function deletePlan(id) {
        if (confirm('Are you sure you want to delete this plan?')) {
            try {
                await apiCall(`/plans/${id}`, 'DELETE');
                alert('Plan deleted successfully!');
                await loadPlans();
                renderPlans();
            } catch (error) {
                alert('Error deleting plan: ' + error.message);
            }
        }
    }

    async function manageBilling(id) {
        currentCompanyId = id;
        const company = companyBilling.find(c => c.id === id);
        if (!company) return;

        // Set company name in modal
        document.getElementById('companyModuleName').textContent = company.name;

        // Ensure modules are loaded
        if (availableModules.length === 0) {
            await loadModules();
        }

        // Load company's current module access
        const moduleSlugs = await loadCompanyModules(id);
        
        // Render all available modules with company's selected modules marked
        renderCompanyModules(moduleSlugs);
        
        // Update module count
        updateModuleCount();
        
        // Open modal
        document.getElementById('companyModuleModal').classList.add('active');
    }

    function renderCompanyModules(selectedModules = []) {
        const grid = document.getElementById('modulesGrid');
        if (!grid) return;
        
        if (!availableModules || availableModules.length === 0) {
            grid.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 2rem;">No modules available. Please refresh the page.</p>';
            return;
        }
        
        grid.innerHTML = availableModules.map(module => {
            const moduleIdentifier = module.slug || module.id;
            const isSelected = selectedModules.includes(moduleIdentifier);
            return `
                <div class="module-card ${isSelected ? 'selected' : ''}" data-module-id="${moduleIdentifier}" onclick="toggleModule(this)">
                    <div class="module-checkbox"></div>
                    <div class="module-info">
                        <div class="module-name">${module.name}</div>
                        <p class="module-desc">${module.description || ''}</p>
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

    async function saveCompanyModules() {
        if (!currentCompanyId) return;

        const selectedCards = document.querySelectorAll('.module-card.selected');
        const selectedModules = Array.from(selectedCards).map(card => {
            return card.getAttribute('data-module-id');
        }).filter(Boolean);

        try {
            await apiCall(`/companies/${currentCompanyId}/modules`, 'PUT', { modules: selectedModules });
            
            const company = companyBilling.find(c => c.id === currentCompanyId);
            const moduleNames = selectedModules.map(id => {
                const module = availableModules.find(m => m.id === id);
                return module ? module.name : id;
            }).join(', ');

            alert(`Module access updated successfully for ${company?.name || 'company'}!\n\nSelected modules: ${selectedModules.length}`);
            closeCompanyModuleModal();
            
            // Reload data
            await loadCompanies();
            renderBillingTable();
        } catch (error) {
            alert('Error updating modules: ' + error.message);
        }
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
            companyBilling.map(company => `<option value="${company.id}">${company.name}</option>`).join('');
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
            company: company.name,
            action: `Granted emergency full access for ${durationText}`,
            admin: 'Current Admin',
            time: 'Just now',
            duration: durationText,
            reason: reason
        });

        // Add to active sessions
        activeSupportSessions.push({
            id: activeSupportSessions.length + 1,
            company: company.name,
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
            company: company.name,
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
        if (! tbody) return;
        tbody.innerHTML = companyBilling.map(company => {
            const modules = companyModuleAccess[company.id] || [];
            const moduleNames = modules.map(id => {
                const module = availableModules.find(m => m.id === id);
                return module ? module.name : id;
            }).slice(0, 3).join(', ');
            const moreCount = modules.length > 3 ? ` +${modules.length - 3} more` : '';
            
            return `
                <tr>
                    <td><strong>${company.name}</strong></td>
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
        document.getElementById('reviewCompanyName').textContent = company.name;
        
        const companyModules = companyModuleAccess[companyId] || [];
        renderReviewModules(companyModules);
        updateReviewModuleCount();
        
        document.getElementById('companyModuleReviewModal').classList.add('active');
    }

    function renderReviewModules(selectedModules = []) {
        const grid = document.getElementById('reviewModulesGrid');
        if (! grid) return;
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
        if (! list) return;
        const countEl = document.getElementById('activeSessionsCount');
        if (countEl) countEl.textContent = `${activeSupportSessions.length} active`;
        
        if (activeSupportSessions.length === 0) {
            list.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 2rem;">No active support sessions</p>';
            return;
        }
        
        list.innerHTML = activeSupportSessions.map(session => `
            <div class="support-session-card">
                <div class="support-session-info">
                    <div class="support-session-company">${session.company}</div>
                    <p class="support-session-details">${session.type} â€¢ Started ${session.started} â€¢ Expires in ${session.expires}</p>
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
        if (! log) return;
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
                            ${action.admin} â€¢ ${action.time}${action.notes ? ` â€¢ ${action.notes}` : ''}
                        </p>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderSupportTickets() {
        const list = document.getElementById('supportTicketsList');
        if (! list) return;
        list.innerHTML = supportTickets.map(ticket => `
            <div class="support-ticket-card" onclick="viewSupportTicket(${ticket.id})">
                <div class="support-ticket-header">
                    <span class="support-ticket-id">#TKT-${ticket.id.toString().padStart(4, '0')}</span>
                    <span class="support-ticket-status ${ticket.status}">${ticket.status.replace('-', ' ').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')}</span>
                </div>
                <div class="support-ticket-subject">${ticket.subject}</div>
                <p class="support-ticket-meta">${ticket.company} â€¢ ${ticket.created} â€¢ Priority: ${ticket.priority}</p>
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
        if (! moduleFilter) return;
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

    // Data Loading Functions
    async function loadPlans() {
        try {
            subscriptionPlans = await apiCall('/plans');
        } catch (error) {
            console.error('Error loading plans:', error);
            subscriptionPlans = sampleSubscriptionPlans;
        }
    }

    async function loadCompanies() {
        try {
            const data = await apiCall('/companies');
            companyBilling = data;
            companies = data.map(c => ({ id: c.id, name: c.name }));
            
            // Also load module access for each company
            for (const company of data) {
                try {
                    const result = await apiCall(`/companies/${company.id}/modules`);
                    if (result.modules) {
                        companyModuleAccess[company.id] = result.modules;
                    }
                } catch (e) {
                    console.error(`Error loading modules for company ${company.id}:`, e);
                }
            }
        } catch (error) {
            console.error('Error loading companies:', error);
        }
    }

    async function loadPayments() {
        try {
            recentPayments = await apiCall('/payments');
        } catch (error) {
            console.error('Error loading payments:', error);
        }
    }

    async function loadModules() {
        try {
            availableModules = await apiCall('/modules');
        } catch (error) {
            console.error('Error loading modules:', error);
            availableModules = [];
        }
    }

    async function loadSettings() {
        try {
            systemSettings = await apiCall('/settings');
        } catch (error) {
            console.error('Error loading settings:', error);
        }
    }

    async function loadUsers() {
        try {
            users = await apiCall('/users');
        } catch (error) {
            console.error('Error loading users:', error);
        }
    }

    async function loadStats() {
        try {
            stats = await apiCall('/stats');
            updateStatsDisplay();
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    function updateStatsDisplay() {
        if (stats.total_companies !== undefined) {
            const el = document.getElementById('statTotalCompanies');
            if (el) el.textContent = stats.total_companies;
        }
        if (stats.active_subscriptions !== undefined) {
            const el = document.getElementById('statActiveSubscriptions');
            if (el) el.textContent = stats.active_subscriptions;
        }
        if (stats.monthly_revenue !== undefined) {
            const el = document.getElementById('statMonthlyRevenue');
            if (el) el.textContent = '$' + stats.monthly_revenue.toLocaleString();
        }
        if (stats.pending_approvals !== undefined) {
            const el = document.getElementById('statPendingApprovals');
            if (el) el.textContent = stats.pending_approvals;
        }
    }

    async function loadCompanyModules(companyId) {
        try {
            const result = await apiCall(`/companies/${companyId}/modules`);
            return result.modules || [];
        } catch (error) {
            console.error('Error loading company modules:', error);
            return [];
        }
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', async function() {
        // If we have initial data from server-side rendering, use it first
        if (window.initialBillingData) {
            subscriptionPlans = window.initialBillingData.plans || [];
            companyBilling = window.initialBillingData.companies || [];
            recentPayments = window.initialBillingData.payments || [];
            companies = (window.initialBillingData.companies || []).map(c => ({ id: c.id, name: c.name }));
            
            // Render immediately with server-side data
            renderPlans();
            renderBillingTable();
            renderPayments();
            renderCompanySelector();
        }
        
        // Load data from API in background to refresh/update
        await Promise.all([
            loadPlans(),
            loadCompanies(),
            loadPayments(),
            loadModules(),
            loadStats()
        ]);

        // Re-render everything with fresh API data
        renderPlans();
        renderBillingTable();
        renderPayments();
        renderCompanySelector();
        renderRoles();
        renderAccessLogs();
        renderSupportData();
    });
</script>

