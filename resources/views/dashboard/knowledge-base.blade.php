@extends('layouts.app')

@section('title', 'Knowledge Base')

@push('styles')
    @include('partials.leads-page-base-styles')
    @vite(['resources/css/pages/knowledge-base.css'])
@endpush

@push('scripts')
<script>
    window.__knowledgeBaseConfig = {
        canCreate: @json($canCreateKnowledgeBase ?? true),
        canEdit: @json($canEditKnowledgeBase ?? true),
        canDelete: @json($canDeleteKnowledgeBase ?? true),
        baseUrl: @json(\Illuminate\Support\Str::replaceLast('/articles', '', route('api.knowledge-base.articles.store'))),
        bootstrapUrl: @json(route('api.knowledge-base.bootstrap')),
    };
</script>
    @vite(['resources/js/pages/knowledge-base.js'])
@endpush

@section('content')
    <div class="ld-page-wrapper ld-page-wrapper--scroll">
    <div class="ld-page">
    <div class="ld-top">
        <div class="ld-top-main">
            <h1 class="ld-title">Knowledge Base</h1>
            <p class="ld-subtitle">FAQs, guides, and articles for internal use.</p>
        </div>
    </div>

    <div class="knowledge-container">
        <div class="leads-tabs knowledge-tabs" role="tablist">
            <button type="button" class="leads-tab tab-btn" data-tab="articles">Articles</button>
            <button type="button" class="leads-tab tab-btn" data-tab="faqs">FAQs</button>
            <button type="button" class="leads-tab tab-btn active" data-tab="guides">Guides</button>
        </div>

        <!-- Articles Tab -->
        <div class="tab-content" id="articlesTab">
            <div class="section-header">
                <h2 class="section-title">Articles</h2>
                <div class="section-actions">
                    <select class="leads-source-filter" id="articleCategoryFilter">
                        <option value="all">All Categories</option>
                    </select>
                    @if($canCreateKnowledgeBase ?? true)
                    <button type="button" class="btn btn-primary btn-sm" onclick="createArticle()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        New Article
                    </button>
                    @endif
                </div>
            </div>

            <!-- Articles Grid -->
            <div class="articles-grid" id="articlesGrid" aria-busy="true">
                @include('partials.skeleton-cards', ['count' => 6])
            </div>
        </div>

        <!-- FAQs Tab -->
        <div class="tab-content" id="faqsTab">
            <div class="section-header">
                <h2 class="section-title">Frequently Asked Questions</h2>
                @if($canCreateKnowledgeBase ?? true)
                <button type="button" class="btn btn-primary btn-sm" onclick="createFAQ()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    New FAQ
                </button>
                @endif
            </div>

            <!-- FAQ Categories -->
            <div class="leads-tabs faq-categories" id="faqCategoriesContainer">
                <button type="button" class="leads-tab faq-category-btn active" data-category="all">All</button>
            </div>

            <!-- FAQs List -->
            <div class="faqs-list" id="faqsList" aria-busy="true">
                @for ($i = 0; $i < 5; $i++)
                    <div class="page-skel-role-card" style="min-height:64px;"></div>
                @endfor
            </div>
        </div>

        <!-- Guides Tab (Default on first use) -->
        <div class="tab-content active" id="guidesTab">
            <div class="section-header">
                <h2 class="section-title">Guides & Tutorials</h2>
                @if($canCreateKnowledgeBase ?? true)
                <button type="button" class="btn btn-primary btn-sm" onclick="createGuide()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    New Guide
                </button>
                @endif
            </div>

            @php
                use App\Helpers\SidebarHelper;
                $canGuide = fn ($permission, $moduleSlug = null) => SidebarHelper::canAccessModule($userPermissions ?? [], $companyModuleSlugs ?? null, $permission, $moduleSlug);
            @endphp

            <!-- Default Application Guides (step-by-step, first use) -->
            <div class="default-guides-section">
                <h3 class="default-guides-heading">Default Application Guides</h3>
                <p class="default-guides-intro">Step-by-step instructions for navigating and using each module you have access to. Click a module to expand.</p>

                <div class="default-guides-list">
                    @if($canGuide('view_dashboard', 'dashboard'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Dashboard</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Click <strong>Dashboard</strong> in the sidebar under Main.</li>
                                <li>View your key metrics, recent activity, and quick links.</li>
                                <li>Use the widgets to jump to other modules.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_time_tracking', 'time-tracking'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Time Tracking</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Go to <strong>Time Tracking</strong> from the sidebar.</li>
                                <li>For Time In/Out: Click <strong>Time In</strong> when starting work, <strong>Time Out</strong> when ending.</li>
                                <li>For project tracking: Select a project and task, then click <strong>Start</strong> to begin recording.</li>
                                <li>Click <strong>Stop</strong> when done. View your entries in the records table.</li>
                                <li>Use filters by date range and employee to review history.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_user_management', 'user-management'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">User Management</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Open <strong>User Management</strong> from the sidebar.</li>
                                <li><strong>Employees</strong>: Add new employees via the + button; edit/delete from the table.</li>
                                <li><strong>Departments</strong>: Create departments, then assign employees to them.</li>
                                <li><strong>Roles</strong>: Create roles and assign permissions. Assign roles to employees.</li>
                                <li>Use <strong>Company Settings</strong> to configure company-wide options.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_employee_monitoring', 'employee-monitoring'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Employee Monitoring</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Go to <strong>Employee Monitoring</strong> from the sidebar.</li>
                                <li>Select an employee from the list to view their activity.</li>
                                <li>Browse time logs and screen recordings (when enabled).</li>
                                <li>Click a recording to play or download it.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide(['view_payroll', 'view_wise_recipients', 'view_pnl', 'view_payroll_report', 'view_payroll_sales_rep_report', 'generate_payroll_report'], ['payroll', 'pnl']))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Payroll</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Go to <strong>Payroll</strong> from the sidebar (or under Payroll submenu).</li>
                                <li><strong>Time In/Out</strong>: Review and edit time records as needed.</li>
                                <li><strong>Salary Computation</strong>: Set employee rates and compute salaries for the period.</li>
                                <li><strong>Payroll Report</strong>: Generate a report for the selected period and employees.</li>
                                <li>Save the report, then export to Excel/PDF or send to Wise for disbursement.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_wise_recipients', 'payroll'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Wise Recipients</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Ensure <strong>Wise</strong> is connected under Integrations first.</li>
                                <li>Go to <strong>Payroll</strong> → <strong>Wise Recipients</strong>.</li>
                                <li>Assign a Wise recipient ID to each employee for payroll.</li>
                                <li>Each ID can only be used once. Save after assigning.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_project_management', 'project-management'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Project Management</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Go to <strong>Project Management</strong> from the sidebar.</li>
                                <li>Click <strong>Create Project</strong> to add a new project. Assign a client and team.</li>
                                <li>Add tasks to the project; set due dates and assignees.</li>
                                <li>Use the task view to track status (To Do, In Progress, Done).</li>
                                <li>Log time against tasks from the time tracking section or from the task detail.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_team_management', 'team-management'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Team Management</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Go to <strong>Team Management</strong> from the sidebar.</li>
                                <li>Create a team with the <strong>Add Team</strong> button.</li>
                                <li>Add members to the team and assign roles (e.g. Lead, Member).</li>
                                <li>View team tasks, time tracking, and recordings from the team detail view.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_leave_management', 'leave-management'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Leave Management</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Go to <strong>Leave Management</strong> from the sidebar.</li>
                                <li><strong>Request leave</strong>: Click <strong>Request Leave</strong>, choose type and dates, submit.</li>
                                <li><strong>Approvers</strong>: Go to pending requests and approve or reject them.</li>
                                <li>View the leave calendar to see who is off.</li>
                                <li>Manage leave credits for employees in the credits section.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_client_management', 'client-management'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Client Management</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Go to <strong>Client Management</strong> from the sidebar.</li>
                                <li>Add a client with the <strong>Add Client</strong> button; fill name, email, and details.</li>
                                <li>Assign employees to the client for project work.</li>
                                <li>Add notes and link projects from the client profile.</li>
                                <li>Create client portal users so clients can log in and view their projects.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_quotation_builder', 'quotation-builder'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Quotation Builder</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Go to <strong>Quotation Builder</strong> from the sidebar.</li>
                                <li>Click <strong>Create Quotation</strong>. Select a client.</li>
                                <li>Add line items (or use templates from Item Templates). Set quantities and rates.</li>
                                <li>Review totals and save. Send via email or download PDF.</li>
                                <li>Track status: Draft, Sent, Accepted, Rejected.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_quotation_builder', 'quotation-builder'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Quotation Item Templates</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Go to <strong>Quotation Builder</strong> → <strong>Item Templates</strong>.</li>
                                <li>Create reusable items with description, unit, and rate.</li>
                                <li>When creating a quotation, search and add these templates for faster entry.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_phone_system', 'phone-system'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Phone System</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Connect your company <strong>Twilio</strong> account in <strong>Integrations</strong> (Account SID, Auth Token, App SID, API Key, API Secret).</li>
                                <li>Company admins: buy or sync phone numbers in <strong>Phone System → Numbers</strong>, then assign a phone system number and/or SMS number to each employee. These can be the same number or two different numbers.</li>
                                <li>In the Twilio Console, set each number’s <strong>Voice</strong> and <strong>SMS</strong> webhooks to your app URLs (shown on the Phone System setup checklist).</li>
                                <li>Go to <strong>Phone System</strong> — use the dial pad, call history, contacts, and SMS tabs.</li>
                                <li>Employees need an assigned phone system number for outbound calls and an assigned SMS number for texting.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_messaging', 'messaging'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Messaging</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Connect <strong>Gmail</strong> in Integrations to sync email.</li>
                                <li>Go to <strong>Messaging</strong> from the sidebar.</li>
                                <li>Compose, send, and view internal messages and synced emails.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_billing', 'billing'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Billing & Payments</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Connect <strong>Stripe</strong> in Integrations for payment processing.</li>
                                <li>Go to <strong>Billing</strong> from the sidebar.</li>
                                <li>Create invoices: select client, add line items, set due date.</li>
                                <li>Send invoice via email or generate a Stripe payment link.</li>
                                <li>Track payments and subscriptions from the dashboard.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_tickets', 'tickets'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Tickets & Helpdesk</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Go to <strong>Tickets & Helpdesk</strong> from the sidebar.</li>
                                <li>Create a ticket with subject, description, priority, and category.</li>
                                <li>Assign to yourself or another agent. Filter by All / Assigned to me.</li>
                                <li>Open a ticket to update status, add comments, and track SLA.</li>
                                <li>Resolve or close when done.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_knowledge_base', 'knowledge-base'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Knowledge Base</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>You are here! Use <strong>Articles</strong> for long-form content, <strong>FAQs</strong> for Q&A, <strong>Guides</strong> for tutorials.</li>
                                <li>Create articles, FAQs, or guides with the New buttons (if you have permission).</li>
                                <li>Organize content with categories. Filter and search to find what you need.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_integrations', 'integrations'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Integrations</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Go to <strong>Integrations</strong> from the sidebar.</li>
                                <li><strong>Twilio</strong>: Add Account SID, Auth Token, and phone number for the Phone System.</li>
                                <li><strong>Gmail</strong>: Connect for email sync in Messaging.</li>
                                <li><strong>Stripe</strong>: Add API keys for Billing payments. Configure webhooks.</li>
                                <li><strong>Wise</strong>: Connect for Payroll disbursement.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_calendar', 'calendar'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Calendar</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Connect your personal Microsoft 365 account in <strong>Inbox</strong> (Connect Personal MS365).</li>
                                <li>Go to <strong>Calendar</strong> from the sidebar.</li>
                                <li>Your Outlook calendars and events load from that same personal Inbox account.</li>
                                <li>If events do not appear, or create/edit fails, reconnect Personal MS365 in Inbox so calendar access (Calendars.ReadWrite) is granted.</li>
                                <li>Use <strong>Create</strong> to add events, edit an event to update it, and Share with emails to send Outlook invitations.</li>
                                <li>Use Month, Week, or Day view, and the sidebar to show or hide individual Outlook calendars.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_email_tracking', 'email-tracking'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Email Tracking</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Go to <strong>Email Tracking</strong> from the sidebar.</li>
                                <li>View sent emails, open rates, and click tracking.</li>
                                <li>Use data to measure client engagement.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_ai_assistant', 'openai'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">AI Assistant</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Configure the OpenAI API key in <strong>Integrations</strong> → OpenAI.</li>
                                <li>Go to <strong>AI Assistant</strong> from the sidebar.</li>
                                <li>Type your prompt and use AI for tasks, summaries, or suggestions.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_contracts', 'contracts'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Contracts & E-Sign</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Go to <strong>Contracts & E-Sign</strong> from the sidebar.</li>
                                <li>Upload a document and add signer fields.</li>
                                <li>Send for signature and track when it's completed.</li>
                            </ol>
                        </div>
                    </details>
                    @endif

                    @if($canGuide('view_change_password', 'change-password'))
                    <details class="default-guide-item">
                        <summary class="default-guide-summary">Change Password</summary>
                        <div class="default-guide-steps">
                            <ol>
                                <li>Go to <strong>Change Password</strong> from the sidebar.</li>
                                <li>Enter your current password and the new password twice.</li>
                                <li>Click Update to save.</li>
                            </ol>
                        </div>
                    </details>
                    @endif
                </div>
            </div>

            
            <div class="guides-grid" id="guidesGrid" aria-busy="true">
                @include('partials.skeleton-cards', ['count' => 6])
            </div>
        </div>
    </div>

    <!-- New Article Modal -->
    <div class="knowledge-modal" id="newArticleModal">
        <div class="knowledge-modal-content knowledge-modal-form">
            <button type="button" class="modal-close" onclick="closeNewArticleModal()" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <div class="modal-header">
                <h2 class="modal-title">New Article</h2>
            </div>
            <form id="newArticleForm" class="modal-form" onsubmit="previewArticle(event)">
                <div class="form-row">
                    <div class="form-group form-group-flex">
                        <label for="newArticleTitle" class="form-label">Title <span class="required">*</span></label>
                        <input type="text" id="newArticleTitle" name="title" class="form-input" required placeholder="Enter article title">
                    </div>
                    <div class="form-group">
                        <label for="newArticleCategory" class="form-label">Category</label>
                        <div class="form-input-group">
                            <select id="newArticleCategory" name="category" class="form-input">
                                <option value="">No category</option>
                            </select>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="openAddCategoryModal('article')" title="Add category">+</button>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="newArticleExcerptEditor" class="form-label">Excerpt / Summary <span class="required">*</span></label>
                    <div class="rich-editor">
                        <div class="rich-editor-toolbar" data-editor="newArticleExcerptEditor">
                            <button type="button" class="rich-editor-btn" data-cmd="bold" title="Bold"><b>B</b></button>
                            <button type="button" class="rich-editor-btn" data-cmd="italic" title="Italic"><i>I</i></button>
                            <button type="button" class="rich-editor-btn" data-cmd="underline" title="Underline"><u>U</u></button>
                            <button type="button" class="rich-editor-btn" data-cmd="strikeThrough" title="Strikethrough"><s>S</s></button>
                            <span class="rich-editor-sep"></span>
                            <select class="rich-editor-select" data-editor="newArticleExcerptEditor" data-cmd="formatBlock" title="Text size">
                                <option value="p">Paragraph</option>
                                <option value="h2">Heading 2</option>
                                <option value="h3">Heading 3</option>
                            </select>
                            <span class="rich-editor-sep"></span>
                            <button type="button" class="rich-editor-btn" data-cmd="justifyLeft" title="Align left">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            </button>
                            <button type="button" class="rich-editor-btn" data-cmd="justifyCenter" title="Align center">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            </button>
                            <button type="button" class="rich-editor-btn" data-cmd="justifyRight" title="Align right">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            </button>
                            <button type="button" class="rich-editor-btn" data-cmd="justifyFull" title="Justify">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            </button>
                            <span class="rich-editor-sep"></span>
                            <button type="button" class="rich-editor-btn" data-cmd="insertUnorderedList" title="Bullet list">• List</button>
                            <button type="button" class="rich-editor-btn" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
                            <button type="button" class="rich-editor-btn" data-cmd="formatBlock" data-value="blockquote" title="Quote">" Quote</button>
                            <span class="rich-editor-sep"></span>
                            <button type="button" class="rich-editor-btn" data-cmd="createLink" title="Insert link">Link</button>
                            <button type="button" class="rich-editor-btn" data-cmd="removeFormat" title="Clear formatting">Clear</button>
                        </div>
                        <div id="newArticleExcerptEditor" class="rich-editor-content" contenteditable="true" data-hidden="newArticleExcerpt" role="textbox" aria-label="Excerpt / Summary" data-placeholder="Brief summary of the article (shown in cards and previews)"></div>
                        <input type="hidden" id="newArticleExcerpt" name="excerpt" value="">
                    </div>
                </div>
                <div class="form-group">
                    <label for="newArticleContentEditor" class="form-label">Content</label>
                    <div class="rich-editor">
                        <div class="rich-editor-toolbar" data-editor="newArticleContentEditor">
                            <button type="button" class="rich-editor-btn" data-cmd="bold" title="Bold"><b>B</b></button>
                            <button type="button" class="rich-editor-btn" data-cmd="italic" title="Italic"><i>I</i></button>
                            <button type="button" class="rich-editor-btn" data-cmd="underline" title="Underline"><u>U</u></button>
                            <button type="button" class="rich-editor-btn" data-cmd="strikeThrough" title="Strikethrough"><s>S</s></button>
                            <span class="rich-editor-sep"></span>
                            <select class="rich-editor-select" data-editor="newArticleContentEditor" data-cmd="formatBlock" title="Text size">
                                <option value="p">Paragraph</option>
                                <option value="h2">Heading 2</option>
                                <option value="h3">Heading 3</option>
                            </select>
                            <span class="rich-editor-sep"></span>
                            <button type="button" class="rich-editor-btn" data-cmd="justifyLeft" title="Align left">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            </button>
                            <button type="button" class="rich-editor-btn" data-cmd="justifyCenter" title="Align center">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            </button>
                            <button type="button" class="rich-editor-btn" data-cmd="justifyRight" title="Align right">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            </button>
                            <button type="button" class="rich-editor-btn" data-cmd="justifyFull" title="Justify">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            </button>
                            <span class="rich-editor-sep"></span>
                            <button type="button" class="rich-editor-btn" data-cmd="insertUnorderedList" title="Bullet list">• List</button>
                            <button type="button" class="rich-editor-btn" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
                            <button type="button" class="rich-editor-btn" data-cmd="formatBlock" data-value="blockquote" title="Quote">" Quote</button>
                            <span class="rich-editor-sep"></span>
                            <button type="button" class="rich-editor-btn" data-cmd="createLink" title="Insert link">Link</button>
                            <button type="button" class="rich-editor-btn" data-cmd="removeFormat" title="Clear formatting">Clear</button>
                        </div>
                        <div id="newArticleContentEditor" class="rich-editor-content rich-editor-content-tall" contenteditable="true" data-hidden="newArticleContent" role="textbox" aria-label="Content" data-placeholder="Full article content (supports plain text or HTML)"></div>
                        <input type="hidden" id="newArticleContent" name="content" value="">
                    </div>
                </div>
                <div class="modal-form-actions">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeNewArticleModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        Preview
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Article Preview Modal -->
    <div class="knowledge-modal" id="articlePreviewModal">
        <div class="knowledge-modal-content knowledge-modal-form">
            <button type="button" class="modal-close" onclick="closeArticlePreviewModal()" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <div class="modal-header">
                <div class="modal-header-info">
                    <div class="modal-badge draft" id="articlePreviewBadge">Draft</div>
                    <h2 class="modal-title" id="articlePreviewTitle">Article title</h2>
                    <div class="modal-meta" id="articlePreviewMeta">
                        <span id="articlePreviewCategory">No category</span>
                    </div>
                </div>
            </div>
            <div class="modal-body article-preview-body">
                <div class="article-preview-excerpt article-excerpt-html" id="articlePreviewExcerpt"></div>
                <div class="content-body article-preview-content" id="articlePreviewContent"></div>
            </div>
            <div class="modal-form-actions article-preview-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeArticlePreviewModal()">Back to Edit</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="saveArticleWithStatus('draft')">Save as Draft</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="saveArticleWithStatus('archived')">Archive</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveArticleWithStatus('published')">Publish</button>
            </div>
        </div>
    </div>

    <!-- New FAQ Modal -->
    <div class="knowledge-modal" id="newFAQModal">
        <div class="knowledge-modal-content knowledge-modal-form">
            <button type="button" class="modal-close" onclick="closeNewFAQModal()" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <div class="modal-header">
                <h2 class="modal-title">New FAQ</h2>
            </div>
            <form id="newFAQForm" class="modal-form" onsubmit="previewFaq(event)">
                <div class="form-row">
                    <div class="form-group form-group-flex">
                        <label for="newFAQQuestion" class="form-label">Question <span class="required">*</span></label>
                        <input type="text" id="newFAQQuestion" name="question" class="form-input" required placeholder="Enter the question">
                    </div>
                    <div class="form-group">
                        <label for="newFAQCategory" class="form-label">Category</label>
                        <div class="form-input-group">
                            <select id="newFAQCategory" name="category" class="form-input">
                                <option value="">No category</option>
                            </select>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="openAddCategoryModal('faq')" title="Add category">+</button>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="newFAQAnswerEditor" class="form-label">Answer <span class="required">*</span></label>
                    <div class="rich-editor">
                        <div class="rich-editor-toolbar" data-editor="newFAQAnswerEditor">
                            <button type="button" class="rich-editor-btn" data-cmd="bold" title="Bold"><b>B</b></button>
                            <button type="button" class="rich-editor-btn" data-cmd="italic" title="Italic"><i>I</i></button>
                            <button type="button" class="rich-editor-btn" data-cmd="underline" title="Underline"><u>U</u></button>
                            <button type="button" class="rich-editor-btn" data-cmd="strikeThrough" title="Strikethrough"><s>S</s></button>
                            <span class="rich-editor-sep"></span>
                            <select class="rich-editor-select" data-editor="newFAQAnswerEditor" data-cmd="formatBlock" title="Text size">
                                <option value="p">Paragraph</option>
                                <option value="h2">Heading 2</option>
                                <option value="h3">Heading 3</option>
                            </select>
                            <span class="rich-editor-sep"></span>
                            <button type="button" class="rich-editor-btn" data-cmd="justifyLeft" title="Align left">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            </button>
                            <button type="button" class="rich-editor-btn" data-cmd="justifyCenter" title="Align center">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            </button>
                            <button type="button" class="rich-editor-btn" data-cmd="justifyRight" title="Align right">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            </button>
                            <button type="button" class="rich-editor-btn" data-cmd="justifyFull" title="Justify">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            </button>
                            <span class="rich-editor-sep"></span>
                            <button type="button" class="rich-editor-btn" data-cmd="insertUnorderedList" title="Bullet list">• List</button>
                            <button type="button" class="rich-editor-btn" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
                            <button type="button" class="rich-editor-btn" data-cmd="formatBlock" data-value="blockquote" title="Quote">" Quote</button>
                            <span class="rich-editor-sep"></span>
                            <button type="button" class="rich-editor-btn" data-cmd="createLink" title="Insert link">Link</button>
                            <button type="button" class="rich-editor-btn" data-cmd="removeFormat" title="Clear formatting">Clear</button>
                        </div>
                        <div id="newFAQAnswerEditor" class="rich-editor-content rich-editor-content-tall" contenteditable="true" data-hidden="newFAQAnswer" role="textbox" aria-label="Answer" data-placeholder="Enter the answer"></div>
                        <input type="hidden" id="newFAQAnswer" name="answer" value="">
                    </div>
                </div>
                <div class="modal-form-actions">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeNewFAQModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        Preview
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- FAQ Preview Modal -->
    <div class="knowledge-modal" id="faqPreviewModal">
        <div class="knowledge-modal-content knowledge-modal-form">
            <button type="button" class="modal-close" onclick="closeFaqPreviewModal()" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <div class="modal-header">
                <div class="modal-header-info">
                    <div class="modal-badge draft" id="faqPreviewBadge">Preview</div>
                    <h2 class="modal-title" id="faqPreviewQuestion">Question</h2>
                    <div class="modal-meta" id="faqPreviewMeta">
                        <span id="faqPreviewCategory">No category</span>
                    </div>
                </div>
            </div>
            <div class="modal-body article-preview-body">
                <div class="faq-answer-text content-body" id="faqPreviewAnswer"></div>
            </div>
            <div class="modal-form-actions article-preview-actions">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeFaqPreviewModal()">Back to Edit</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="saveFaqWithStatus('draft')">Save as Draft</button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="saveFaqWithStatus('archived')">Archive</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveFaqWithStatus('published')">Publish</button>
            </div>
        </div>
    </div>

    <!-- New Guide Modal -->
    <div class="knowledge-modal" id="newGuideModal">
        <div class="knowledge-modal-content knowledge-modal-form">
            <button type="button" class="modal-close" onclick="closeNewGuideModal()" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <div class="modal-header">
                <h2 class="modal-title">New Guide</h2>
            </div>
            <form id="newGuideForm" class="modal-form" onsubmit="submitNewGuide(event)">
                <div class="form-row">
                    <div class="form-group form-group-flex">
                        <label for="newGuideTitle" class="form-label">Title <span class="required">*</span></label>
                        <input type="text" id="newGuideTitle" name="title" class="form-input" required placeholder="Enter guide title">
                    </div>
                    <div class="form-group">
                        <label for="newGuideCategory" class="form-label">Category <span class="required">*</span></label>
                        <div class="form-input-group">
                            <select id="newGuideCategory" name="category" class="form-input" required>
                                <option value="">Select category</option>
                            </select>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="openAddCategoryModal('guide')" title="Add category">+</button>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="newGuideDuration" class="form-label">Duration</label>
                    <input type="text" id="newGuideDuration" name="duration" class="form-input" placeholder="e.g. 15 min">
                </div>
                <div class="form-group">
                    <span class="form-label">Icon</span>
                    <input type="hidden" id="newGuideIcon" name="icon" value="📖">
                    <div class="icon-picker" id="newGuideIconPicker" role="group" aria-label="Choose guide icon">
                        <button type="button" class="icon-picker-btn selected" data-icon="📖" title="Guide">📖</button>
                        <button type="button" class="icon-picker-btn" data-icon="🚀" title="Rocket">🚀</button>
                        <button type="button" class="icon-picker-btn" data-icon="📊" title="Chart">📊</button>
                        <button type="button" class="icon-picker-btn" data-icon="📈" title="Trending">📈</button>
                        <button type="button" class="icon-picker-btn" data-icon="⚡" title="Quick">⚡</button>
                        <button type="button" class="icon-picker-btn" data-icon="👥" title="Team">👥</button>
                        <button type="button" class="icon-picker-btn" data-icon="🔒" title="Security">🔒</button>
                        <button type="button" class="icon-picker-btn" data-icon="🛠️" title="Tools">🛠️</button>
                        <button type="button" class="icon-picker-btn" data-icon="📋" title="Checklist">📋</button>
                        <button type="button" class="icon-picker-btn" data-icon="💡" title="Idea">💡</button>
                        <button type="button" class="icon-picker-btn" data-icon="🔧" title="Settings">🔧</button>
                        <button type="button" class="icon-picker-btn" data-icon="📱" title="Mobile">📱</button>
                        <button type="button" class="icon-picker-btn" data-icon="✅" title="Complete">✅</button>
                        <button type="button" class="icon-picker-btn" data-icon="🎯" title="Target">🎯</button>
                        <button type="button" class="icon-picker-btn" data-icon="🌟" title="Star">🌟</button>
                        <button type="button" class="icon-picker-btn" data-icon="📝" title="Document">📝</button>
                        <button type="button" class="icon-picker-btn" data-icon="📌" title="Pin">📌</button>
                        <button type="button" class="icon-picker-btn" data-icon="🔔" title="Notification">🔔</button>
                        <button type="button" class="icon-picker-btn" data-icon="🏠" title="Home">🏠</button>
                        <button type="button" class="icon-picker-btn" data-icon="💼" title="Business">💼</button>
                        <button type="button" class="icon-picker-btn" data-icon="🎓" title="Learning">🎓</button>
                        <button type="button" class="icon-picker-btn" data-icon="📦" title="Package">📦</button>
                        <button type="button" class="icon-picker-btn" data-icon="🗂️" title="Folder">🗂️</button>
                        <button type="button" class="icon-picker-btn" data-icon="🧩" title="Puzzle">🧩</button>
                        <button type="button" class="icon-picker-btn" data-icon="🎨" title="Design">🎨</button>
                        <button type="button" class="icon-picker-btn" data-icon="🔍" title="Search">🔍</button>
                        <button type="button" class="icon-picker-btn" data-icon="📧" title="Email">📧</button>
                        <button type="button" class="icon-picker-btn" data-icon="🌐" title="Web">🌐</button>
                        <button type="button" class="icon-picker-btn" data-icon="⏱️" title="Time">⏱️</button>
                        <button type="button" class="icon-picker-btn" data-icon="🏆" title="Trophy">🏆</button>
                        <button type="button" class="icon-picker-btn" data-icon="🎉" title="Celebrate">🎉</button>
                        <button type="button" class="icon-picker-btn" data-icon="🔐" title="Key">🔐</button>
                        <button type="button" class="icon-picker-btn" data-icon="📂" title="Folder open">📂</button>
                        <button type="button" class="icon-picker-btn" data-icon="🧪" title="Lab">🧪</button>
                        <button type="button" class="icon-picker-btn" data-icon="📐" title="Blueprint">📐</button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="newGuideExcerptEditor" class="form-label">Excerpt / Summary <span class="required">*</span></label>
                    <div class="rich-editor">
                        <div class="rich-editor-toolbar" data-editor="newGuideExcerptEditor">
                            <button type="button" class="rich-editor-btn" data-cmd="bold" title="Bold"><b>B</b></button>
                            <button type="button" class="rich-editor-btn" data-cmd="italic" title="Italic"><i>I</i></button>
                            <button type="button" class="rich-editor-btn" data-cmd="underline" title="Underline"><u>U</u></button>
                            <button type="button" class="rich-editor-btn" data-cmd="strikeThrough" title="Strikethrough"><s>S</s></button>
                            <span class="rich-editor-sep"></span>
                            <select class="rich-editor-select" data-editor="newGuideExcerptEditor" data-cmd="formatBlock" title="Text size">
                                <option value="p">Paragraph</option>
                                <option value="h2">Heading 2</option>
                                <option value="h3">Heading 3</option>
                            </select>
                            <span class="rich-editor-sep"></span>
                            <button type="button" class="rich-editor-btn" data-cmd="justifyLeft" title="Align left">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            </button>
                            <button type="button" class="rich-editor-btn" data-cmd="justifyCenter" title="Align center">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            </button>
                            <button type="button" class="rich-editor-btn" data-cmd="justifyRight" title="Align right">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            </button>
                            <button type="button" class="rich-editor-btn" data-cmd="justifyFull" title="Justify">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                            </button>
                            <span class="rich-editor-sep"></span>
                            <button type="button" class="rich-editor-btn" data-cmd="insertUnorderedList" title="Bullet list">• List</button>
                            <button type="button" class="rich-editor-btn" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
                            <button type="button" class="rich-editor-btn" data-cmd="formatBlock" data-value="blockquote" title="Quote">" Quote</button>
                            <span class="rich-editor-sep"></span>
                            <button type="button" class="rich-editor-btn" data-cmd="createLink" title="Insert link">Link</button>
                            <button type="button" class="rich-editor-btn" data-cmd="removeFormat" title="Clear formatting">Clear</button>
                        </div>
                        <div id="newGuideExcerptEditor" class="rich-editor-content" contenteditable="true" data-hidden="newGuideExcerpt" role="textbox" aria-label="Excerpt / Summary" data-placeholder="Brief description of the guide"></div>
                        <input type="hidden" id="newGuideExcerpt" name="excerpt" value="">
                    </div>
                </div>
                <div class="modal-form-actions">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeNewGuideModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/>
                            <polyline points="7 3 7 8 15 8 15 3"/>
                        </svg>
                        Create Guide
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="knowledge-modal" id="addCategoryModal">
        <div class="knowledge-modal-content knowledge-modal-form">
            <button type="button" class="modal-close" onclick="closeAddCategoryModal()" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <div class="modal-header">
                <h2 class="modal-title" id="addCategoryModalTitle">Add Category</h2>
            </div>
            <form id="addCategoryForm" class="modal-form" onsubmit="submitAddCategory(event)">
                <input type="hidden" id="addCategoryType" name="type" value="article">
                <div class="form-group">
                    <label for="addCategoryName" class="form-label">Category name <span class="required">*</span></label>
                    <input type="text" id="addCategoryName" name="name" class="form-input" required placeholder="e.g. Getting Started" maxlength="100">
                </div>
                <div class="modal-form-actions">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="closeAddCategoryModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Add</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Article/FAQ/Guide Detail Modal -->
    <div class="knowledge-modal" id="knowledgeModal">
        <div class="knowledge-modal-content">
            <button class="modal-close" onclick="closeKnowledgeModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>

            <div class="modal-header">
                <div class="modal-header-info">
                    <div class="modal-badge" id="modalBadge">Public</div>
                    <h2 class="modal-title" id="modalTitle">Getting Started with the Dashboard</h2>
                    <div class="modal-meta">
                        <span id="modalCategory">Getting Started</span>
                        <span>•</span>
                        <span id="modalDate">Dec 31, 2025</span>
                        <span>•</span>
                        <span id="modalAuthor">By John Doe</span>
                    </div>
                </div>
                <div class="modal-actions">
                    @if($canEditKnowledgeBase ?? true)
                    <button type="button" class="btn btn-secondary btn-sm" onclick="editContent()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        Edit
                    </button>
                    @endif
                    @if($canDeleteKnowledgeBase ?? true)
                    <button type="button" class="btn btn-secondary btn-sm knowledge-modal-delete" onclick="deleteContent()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            <line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>
                        </svg>
                        Delete
                    </button>
                    @endif
                </div>
            </div>

            <div class="modal-body">
                <div class="content-body" id="contentBody">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>
    </div>
    </div>
@endsection
