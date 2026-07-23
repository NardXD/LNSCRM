<!-- Modals -->
<!-- Add Plan Modal -->
<div class="modal" id="addPlanModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add Subscription Plan</h3>
            <button class="modal-close" onclick="closeAddPlanModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addPlanForm">
                <div class="form-group">
                    <label>Plan Name</label>
                    <input type="text" class="form-control" name="name" id="add_plan_name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" name="description" id="add_plan_description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Billing Cycle</label>
                    <select class="form-control" name="billing_cycle" id="add_plan_billing_cycle" required>
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Price</label>
                    <input type="number" class="form-control" name="price" id="add_plan_price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Features (comma-separated)</label>
                    <input type="text" class="form-control" name="features" id="add_plan_features" placeholder="Feature 1, Feature 2, ...">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_featured" id="add_plan_is_featured"> Featured Plan
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="add_plan_is_active" checked> Active Plan
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" type="button" onclick="closeAddPlanModal()">Cancel</button>
            <button class="btn-primary" type="button" onclick="saveAddPlan(event)">Add Plan</button>
        </div>
    </div>
</div>

<!-- Edit Plan Modal -->
<div class="modal" id="editPlanModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Subscription Plan</h3>
            <button class="modal-close" onclick="closeEditPlanModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="editPlanForm">
                <input type="hidden" name="plan_id" id="edit_plan_id">
                <div class="form-group">
                    <label>Plan Name</label>
                    <input type="text" class="form-control" name="name" id="edit_plan_name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea class="form-control" name="description" id="edit_plan_description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Billing Cycle</label>
                    <select class="form-control" name="billing_cycle" id="edit_plan_billing_cycle" required>
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Price</label>
                    <input type="number" class="form-control" name="price" id="edit_plan_price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Features (comma-separated)</label>
                    <input type="text" class="form-control" name="features" id="edit_plan_features" placeholder="Feature 1, Feature 2, ...">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_featured" id="edit_plan_is_featured"> Featured Plan
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="edit_plan_is_active" checked> Active Plan
                    </label>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" type="button" onclick="closeEditPlanModal()">Cancel</button>
            <button class="btn-primary" type="button" onclick="saveEditPlan(event)">Update Plan</button>
        </div>
    </div>
</div>

    <!-- Company Module Access Modal -->
    <div class="modal" id="companyModuleModal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3 class="modal-title">Manage Company Module Access</h3>
                <button class="modal-close" onclick="closeCompanyModuleModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="company-info-header">
                    <h4 id="companyModuleName">Company Name</h4>
                    <p class="company-module-subtitle">Select which modules this company can access</p>
                </div>
                
                <div class="modules-selection-container">
                    <div class="modules-grid" id="modulesGrid">
                        <!-- Modules will be populated by JavaScript -->
                    </div>
                </div>

                <div class="module-actions-bar">
                    <button class="btn-sm btn-secondary" onclick="selectAllModules()">Select All</button>
                    <button class="btn-sm btn-secondary" onclick="deselectAllModules()">Deselect All</button>
                    <span class="module-count" id="moduleCount">0 modules selected</span>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeCompanyModuleModal()">Cancel</button>
                <button class="btn-primary" onclick="saveCompanyModules()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Emergency Access Modal -->
    <div class="modal" id="emergencyAccessModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Emergency Access Grant</h3>
                <button class="modal-close" onclick="closeEmergencyAccessModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert-warning">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <path d="M12 8v4"/>
                        <path d="M12 16h.01"/>
                    </svg>
                    <div>
                        <strong>Warning:</strong> This will grant temporary full access to all modules. This action will be logged.
                    </div>
                </div>
                <form id="emergencyAccessForm">
                    <div class="form-group">
                        <label>Select Company</label>
                        <select class="form-control" id="emergencyCompanySelect" required>
                            <option value="">Choose a company...</option>
                            <!-- Options will be populated by JavaScript -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Duration</label>
                        <select class="form-control" id="emergencyDuration" required>
                            <option value="1">1 Hour</option>
                            <option value="4">4 Hours</option>
                            <option value="24">24 Hours</option>
                            <option value="168">7 Days</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Reason for Emergency Access</label>
                        <textarea class="form-control" id="emergencyReason" rows="3" placeholder="Describe why emergency access is needed..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="emergencyNotify" checked> Notify company admin via email
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeEmergencyAccessModal()">Cancel</button>
                <button class="btn-primary btn-danger" onclick="grantEmergencyAccess()">Grant Emergency Access</button>
            </div>
        </div>
    </div>

    <!-- Company Module Review Modal -->
    <div class="modal" id="companyModuleReviewModal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3 class="modal-title">Review Company Module Access</h3>
                <button class="modal-close" onclick="closeCompanyModuleReviewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="company-info-header">
                    <h4 id="reviewCompanyName">Company Name</h4>
                    <p class="company-module-subtitle">Review and modify module access for support purposes</p>
                </div>
                
                <div class="review-actions-bar">
                    <button class="btn-sm btn-secondary" onclick="grantAllModulesForSupport()">Grant All (Support)</button>
                    <button class="btn-sm btn-secondary" onclick="revokeAllModulesForSupport()">Revoke All</button>
                    <span class="module-count" id="reviewModuleCount">0 modules selected</span>
                </div>

                <div class="modules-selection-container">
                    <div class="modules-grid" id="reviewModulesGrid">
                        <!-- Modules will be populated by JavaScript -->
                    </div>
                </div>

                <div class="form-group" style="margin-top: 1.5rem;">
                    <label>Support Notes</label>
                    <textarea class="form-control" id="supportNotes" rows="3" placeholder="Add notes about this support action..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeCompanyModuleReviewModal()">Cancel</button>
                <button class="btn-primary" onclick="saveSupportModuleChanges()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Support Tickets Modal -->
    <div class="modal" id="supportTicketsModal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3 class="modal-title">Support Tickets</h3>
                <button class="modal-close" onclick="closeSupportTicketsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="filter-group" style="margin-bottom: 1rem;">
                    <select class="filter-select" id="ticketStatusFilter" onchange="filterTickets()">
                        <option value="all">All Status</option>
                        <option value="open">Open</option>
                        <option value="in-progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                    <input type="text" class="search-input" placeholder="Search tickets..." id="ticketSearch" onkeyup="filterTickets()">
                </div>
                <div class="support-tickets-list" id="supportTicketsList">
                    <!-- Tickets will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeSupportTicketsModal()">Close</button>
            </div>
        </div>
    </div>
