@extends('layouts.app')

@section('title', 'Knowledge Base')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Knowledge Base</h1>
        <p class="page-subtitle">FAQs, guides, and articles for internal use</p>
    </div>

    <div class="knowledge-container">
        <!-- Tabs Navigation -->
        <div class="knowledge-tabs">
            <button class="tab-btn" data-tab="articles">Articles</button>
            <button class="tab-btn" data-tab="faqs">FAQs</button>
            <button class="tab-btn active" data-tab="guides">Guides</button>
        </div>

        <!-- Articles Tab -->
        <div class="tab-content" id="articlesTab">
            <div class="section-header">
                <h2 class="section-title">Articles</h2>
                <div class="section-actions">
                    @if(! empty($articleCategories))
                    <select class="filter-select" id="articleCategoryFilter">
                        <option value="all">All Categories</option>
                        @foreach($articleCategories ?? [] as $cat)
                            <option value="{{ $cat['name'] }}">{{ $cat['name'] }}</option>
                        @endforeach
                    </select>
                    @endif
                    @if($canCreateKnowledgeBase ?? true)
                    <button class="btn-primary" onclick="createArticle()">
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
            <div class="articles-grid" id="articlesGrid">
                <!-- Articles will be populated by JavaScript -->
            </div>
        </div>

        <!-- FAQs Tab -->
        <div class="tab-content" id="faqsTab">
            <div class="section-header">
                <h2 class="section-title">Frequently Asked Questions</h2>
                @if($canCreateKnowledgeBase ?? true)
                <button class="btn-primary" onclick="createFAQ()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    New FAQ
                </button>
                @endif
            </div>

            <!-- FAQ Categories -->
            <div class="faq-categories" id="faqCategoriesContainer">
                <button type="button" class="faq-category-btn active" data-category="all">All</button>
                @foreach($faqCategories ?? [] as $cat)
                    <button type="button" class="faq-category-btn" data-category="{{ $cat['name'] }}">{{ $cat['name'] }}</button>
                @endforeach
            </div>

            <!-- FAQs List -->
            <div class="faqs-list" id="faqsList">
                <!-- FAQs will be populated by JavaScript -->
            </div>
        </div>

        <!-- Guides Tab (Default on first use) -->
        <div class="tab-content active" id="guidesTab">
            <div class="section-header">
                <h2 class="section-title">Guides & Tutorials</h2>
                @if($canCreateKnowledgeBase ?? true)
                <button class="btn-primary" onclick="createGuide()">
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
                                <li>Configure Calendar OAuth in <strong>Integrations</strong> (Google and/or Outlook) so users can connect their personal calendars.</li>
                                <li>Go to <strong>Calendar</strong> from the sidebar.</li>
                                <li>Click <strong>Google</strong> or <strong>Outlook</strong> in the toolbar to connect your personal account.</li>
                                <li>View events, leave, and project milestones in Month, Week, or Day view.</li>
                                <li>Use sidebar filters to show/hide calendar types.</li>
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

            
            <div class="guides-grid" id="guidesGrid">
                <!-- Guides will be populated by JavaScript -->
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
                                @foreach($articleCategories ?? [] as $cat)
                                    <option value="{{ $cat['slug'] }}">{{ $cat['name'] }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn-secondary btn-sm" onclick="openAddCategoryModal('article')" title="Add category">+</button>
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
                    <button type="button" class="btn-secondary" onclick="closeNewArticleModal()">Cancel</button>
                    <button type="submit" class="btn-primary">
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
                <button type="button" class="btn-secondary" onclick="closeArticlePreviewModal()">Back to Edit</button>
                <button type="button" class="btn-secondary" onclick="saveArticleWithStatus('draft')">Save as Draft</button>
                <button type="button" class="btn-secondary" onclick="saveArticleWithStatus('archived')">Archive</button>
                <button type="button" class="btn-primary" onclick="saveArticleWithStatus('published')">Publish</button>
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
                                @foreach($faqCategories ?? [] as $cat)
                                    <option value="{{ $cat['slug'] }}">{{ $cat['name'] }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn-secondary btn-sm" onclick="openAddCategoryModal('faq')" title="Add category">+</button>
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
                    <button type="button" class="btn-secondary" onclick="closeNewFAQModal()">Cancel</button>
                    <button type="submit" class="btn-primary">
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
                <button type="button" class="btn-secondary" onclick="closeFaqPreviewModal()">Back to Edit</button>
                <button type="button" class="btn-secondary" onclick="saveFaqWithStatus('draft')">Save as Draft</button>
                <button type="button" class="btn-secondary" onclick="saveFaqWithStatus('archived')">Archive</button>
                <button type="button" class="btn-primary" onclick="saveFaqWithStatus('published')">Publish</button>
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
                                @foreach($guideCategories ?? [] as $cat)
                                    <option value="{{ $cat['slug'] }}">{{ $cat['name'] }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="btn-secondary btn-sm" onclick="openAddCategoryModal('guide')" title="Add category">+</button>
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
                    <button type="button" class="btn-secondary" onclick="closeNewGuideModal()">Cancel</button>
                    <button type="submit" class="btn-primary">
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
                    <button type="button" class="btn-secondary" onclick="closeAddCategoryModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Add</button>
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
                    <button type="button" class="btn-secondary" onclick="editContent()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        Edit
                    </button>
                    @endif
                    @if($canDeleteKnowledgeBase ?? true)
                    <button type="button" class="btn-secondary knowledge-modal-delete" onclick="deleteContent()">
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
@endsection

@push('styles')
<style>
    .knowledge-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    /* Tabs */
    .knowledge-tabs {
        display: flex;
        gap: 0.5rem;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 0.5rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .tab-btn {
        flex: 1;
        min-width: 150px;
        padding: 0.75rem 1.25rem;
        border: none;
        background: transparent;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        white-space: nowrap;
        -webkit-tap-highlight-color: transparent;
    }

    .tab-btn:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .tab-btn.active {
        background: var(--accent);
        color: white;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    /* Section Header */
    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
    }

    .section-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    /* Buttons */
    .btn-primary, .btn-secondary {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
        border: none;
        -webkit-tap-highlight-color: transparent;
    }

    .btn-primary {
        background: var(--accent);
        color: white;
    }

    .btn-primary:hover {
        background: var(--accent-hover);
    }

    .btn-secondary {
        background: var(--bg-primary);
        color: var(--text-primary);
        border: 1px solid var(--border);
    }

    .btn-secondary:hover {
        background: var(--border);
    }

    .btn-primary svg, .btn-secondary svg {
        width: 18px;
        height: 18px;
    }

    /* Filters */
    .filter-select {
        padding: 0.625rem 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.15s;
    }

    .filter-select:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    /* Articles Grid */
    .articles-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
    }

    .article-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
        cursor: pointer;
        transition: all 0.15s;
        display: flex;
        flex-direction: column;
        min-height: 220px;
    }

    .article-card:hover {
        border-color: var(--accent);
        box-shadow: 0 4px 12px rgba(95, 97, 230, 0.1);
    }

    .article-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 1rem;
    }

    .article-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .article-badge.public {
        background: #d1fae5;
        color: #059669;
    }

    .article-badge.internal {
        background: #dbeafe;
        color: #2563eb;
    }

    .article-badge.draft {
        background: #f3f4f6;
        color: #6b7280;
    }

    .article-badge.published {
        background: #d1fae5;
        color: #059669;
    }

    .article-badge.archived {
        background: #fef3c7;
        color: #d97706;
    }

    .article-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.4;
    }

    .article-excerpt,
    .article-excerpt-html {
        font-size: 0.875rem;
        color: var(--text-secondary);
        line-height: 1.6;
        margin-bottom: 1rem;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 3.9em;
    }

    .article-excerpt-html h2 { font-size: 1rem; font-weight: 700; margin: 0.5rem 0 0.25rem 0; }
    .article-excerpt-html h3 { font-size: 0.9375rem; font-weight: 600; margin: 0.4rem 0 0.2rem 0; }
    .article-excerpt-html blockquote { margin: 0.35rem 0; padding-left: 0.75rem; border-left: 3px solid var(--accent); font-style: italic; }
    .article-excerpt-html ul, .article-excerpt-html ol { margin: 0.25rem 0; padding-left: 1.25rem; }
    .article-excerpt-html a { color: var(--accent); text-decoration: underline; }

    .article-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-top: 1rem;
        margin-top: auto;
        border-top: 1px solid var(--border);
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    .article-category {
        padding: 0.25rem 0.75rem;
        background: var(--bg-primary);
        border-radius: 6px;
        font-weight: 500;
    }

    /* FAQ Categories */
    .faq-categories {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 2rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 0.5rem;
    }

    .faq-category-btn {
        padding: 0.625rem 1.25rem;
        border: 1px solid var(--border);
        background: var(--bg-card);
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.15s;
        white-space: nowrap;
        -webkit-tap-highlight-color: transparent;
    }

    .faq-category-btn:hover {
        background: var(--bg-primary);
        color: var(--text-primary);
    }

    .faq-category-btn.active {
        background: var(--accent);
        border-color: var(--accent);
        color: white;
    }

    /* FAQs List */
    .faqs-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .faq-item {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
        cursor: pointer;
        transition: all 0.15s;
    }

    .faq-item:hover {
        border-color: var(--accent);
    }

    .faq-item.active {
        border-color: var(--accent);
        box-shadow: 0 4px 12px rgba(95, 97, 230, 0.1);
    }

    .faq-question {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .faq-question-main {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1;
        min-width: 0;
    }

    .faq-question-text {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        flex: 1;
    }

    .faq-icon {
        width: 24px;
        height: 24px;
        color: var(--accent);
        flex-shrink: 0;
        transition: transform 0.2s;
    }

    .faq-item.active .faq-icon {
        transform: rotate(180deg);
    }

    .faq-answer {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
        margin-top: 0;
    }

    .faq-item.active .faq-answer {
        max-height: 500px;
        margin-top: 1rem;
    }

    .faq-answer-text {
        font-size: 0.875rem;
        color: var(--text-secondary);
        line-height: 1.6;
        padding-top: 1rem;
        border-top: 1px solid var(--border);
    }

    .faq-answer-text h2 { font-size: 1rem; font-weight: 700; margin: 0.5rem 0 0.25rem 0; }
    .faq-answer-text h3 { font-size: 0.9375rem; font-weight: 600; margin: 0.4rem 0 0.2rem 0; }
    .faq-answer-text blockquote { margin: 0.35rem 0; padding-left: 0.75rem; border-left: 3px solid var(--accent); font-style: italic; }
    .faq-answer-text ul, .faq-answer-text ol { margin: 0.25rem 0; padding-left: 1.25rem; }
    .faq-answer-text a { color: var(--accent); text-decoration: underline; }

    .faq-meta {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-top: 1rem;
        font-size: 0.8125rem;
        color: var(--text-muted);
    }

    /* Default Application Guides */
    .default-guides-section {
        margin-bottom: 2rem;
    }

    .default-guides-heading {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .default-guides-intro {
        font-size: 0.875rem;
        color: var(--text-secondary);
        line-height: 1.6;
        margin-bottom: 1.5rem;
    }

    .default-guides-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .default-guide-item {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
        transition: border-color 0.15s;
    }

    .default-guide-item:hover {
        border-color: var(--accent);
    }

    .default-guide-item[open] {
        border-color: var(--accent);
        box-shadow: 0 2px 8px rgba(95, 97, 230, 0.08);
    }

    .default-guide-summary {
        padding: 1rem 1.25rem;
        font-weight: 600;
        font-size: 0.9375rem;
        color: var(--text-primary);
        cursor: pointer;
        list-style: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .default-guide-summary::-webkit-details-marker {
        display: none;
    }

    .default-guide-summary::before {
        content: '';
        width: 0;
        height: 0;
        border-left: 6px solid var(--accent);
        border-top: 5px solid transparent;
        border-bottom: 5px solid transparent;
        transition: transform 0.2s;
    }

    .default-guide-item[open] .default-guide-summary::before {
        transform: rotate(90deg);
    }

    .default-guide-steps {
        padding: 0 1.25rem 1.25rem 2rem;
        border-top: 1px solid var(--border);
    }

    .default-guide-steps ol {
        margin: 1rem 0 0.75rem 0;
        padding-left: 1.5rem;
    }

    .default-guide-steps li {
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        line-height: 1.6;
        color: var(--text-secondary);
    }

    /* Guides Grid */
    .guides-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .guide-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        cursor: pointer;
        transition: all 0.15s;
        display: flex;
        flex-direction: column;
        min-height: 320px;
    }

    .guide-card:hover {
        border-color: var(--accent);
        box-shadow: 0 4px 12px rgba(95, 97, 230, 0.1);
    }

    .guide-image {
        width: 100%;
        height: 180px;
        background: linear-gradient(135deg, var(--accent) 0%, var(--accent-hover) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3rem;
    }

    .guide-content {
        padding: 1.5rem;
        display: flex;
        flex-direction: column;
        flex: 1;
        min-height: 0;
    }

    .guide-category {
        font-size: 0.75rem;
        color: var(--accent);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.5rem;
    }

    .guide-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.4;
    }

    .guide-excerpt,
    .guide-excerpt-html {
        font-size: 0.875rem;
        color: var(--text-secondary);
        line-height: 1.6;
        margin-bottom: 1rem;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
        min-height: 3.9em;
    }

    .guide-excerpt-html h2 { font-size: 1rem; font-weight: 700; margin: 0.5rem 0 0.25rem 0; }
    .guide-excerpt-html h3 { font-size: 0.9375rem; font-weight: 600; margin: 0.4rem 0 0.2rem 0; }
    .guide-excerpt-html blockquote { margin: 0.35rem 0; padding-left: 0.75rem; border-left: 3px solid var(--accent); font-style: italic; }
    .guide-excerpt-html ul, .guide-excerpt-html ol { margin: 0.25rem 0; padding-left: 1.25rem; }
    .guide-excerpt-html a { color: var(--accent); text-decoration: underline; }

    .guide-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 0.8125rem;
        color: var(--text-muted);
        margin-top: auto;
    }

    /* Knowledge Modal */
    .knowledge-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.75);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        opacity: 0;
        transition: opacity 0.2s;
    }

    .knowledge-modal.active {
        display: flex;
        opacity: 1;
    }

    .knowledge-modal-content {
        background: var(--bg-card);
        border-radius: 16px;
        max-width: 900px;
        width: 100%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        position: relative;
        transform: scale(0.95);
        transition: transform 0.2s;
        overflow: hidden;
    }

    .knowledge-modal.active .knowledge-modal-content {
        transform: scale(1);
    }

    .modal-close {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 40px;
        height: 40px;
        background: rgba(0, 0, 0, 0.5);
        border: none;
        border-radius: 50%;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
        transition: background 0.15s;
    }

    .modal-close:hover {
        background: rgba(0, 0, 0, 0.7);
    }

    .modal-close svg {
        width: 20px;
        height: 20px;
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }

    .modal-header-info {
        flex: 1;
    }

    .modal-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 500;
        margin-bottom: 0.5rem;
    }

    .modal-badge.public {
        background: #d1fae5;
        color: #059669;
    }

    .modal-badge.internal {
        background: #dbeafe;
        color: #2563eb;
    }

    .modal-badge.draft {
        background: #f3f4f6;
        color: #6b7280;
    }

    .modal-badge.published {
        background: #d1fae5;
        color: #059669;
    }

    .modal-badge.archived {
        background: #fef3c7;
        color: #d97706;
    }

    .article-preview-body {
        padding: 0 1.5rem 1rem;
    }

    .article-preview-excerpt {
        font-size: 1rem;
        color: var(--text-secondary);
        line-height: 1.7;
        margin-bottom: 1.25rem;
        padding-bottom: 1.25rem;
        border-bottom: 1px solid var(--border);
        display: block;
        -webkit-line-clamp: unset;
        -webkit-box-orient: unset;
        overflow: visible;
        min-height: 0;
    }

    .article-preview-content:empty::before {
        content: 'No content added yet.';
        color: var(--text-muted);
        font-style: italic;
    }

    .article-preview-actions {
        padding: 0 1.5rem 1.5rem;
        flex-wrap: wrap;
    }

    .modal-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 0.5rem 0;
    }

    .modal-meta {
        display: flex;
        gap: 0.5rem;
        font-size: 0.875rem;
        color: var(--text-secondary);
        flex-wrap: wrap;
    }

    .modal-actions {
        display: flex;
        gap: 0.75rem;
    }

    .knowledge-modal-delete:hover {
        background: #fef2f2;
        border-color: #ef4444;
        color: #ef4444;
    }

    .faq-item-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }

    .faq-item-actions button {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }

    .modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 1.5rem;
    }

    .content-body {
        font-size: 0.9375rem;
        color: var(--text-primary);
        line-height: 1.8;
    }

    .content-body h2 {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 2rem 0 1rem 0;
    }

    .content-body h2:first-child {
        margin-top: 0;
    }

    .content-body h3 {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 1.5rem 0 0.75rem 0;
    }

    .content-body p {
        margin-bottom: 1rem;
    }

    .content-body ul, .content-body ol {
        margin: 1rem 0;
        padding-left: 2rem;
    }

    .content-body li {
        margin-bottom: 0.5rem;
    }

    .content-body code {
        background: var(--bg-primary);
        padding: 0.125rem 0.375rem;
        border-radius: 4px;
        font-family: 'Courier New', monospace;
        font-size: 0.875em;
    }

    .content-body pre {
        background: var(--bg-primary);
        padding: 1rem;
        border-radius: 8px;
        overflow-x: auto;
        margin: 1rem 0;
    }

    /* New Article Modal Form */
    .knowledge-modal-form .modal-header {
        padding-bottom: 0.5rem;
    }

    .knowledge-modal-form .modal-title {
        margin-bottom: 0;
    }

    .modal-form {
        padding: 0 1.5rem 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 1rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-input-group {
        display: flex;
        gap: 0.5rem;
        align-items: stretch;
    }

    .form-input-group .form-input {
        flex: 1;
        min-width: 0;
    }

    .btn-sm {
        padding: 0.5rem 0.75rem;
        font-size: 0.8125rem;
        flex-shrink: 0;
    }

    .form-group-flex {
        min-width: 0;
    }

    .form-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .form-label .required {
        color: #ef4444;
    }

    .form-input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: var(--bg-card);
        color: var(--text-primary);
        font-family: inherit;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .form-input::placeholder {
        color: var(--text-muted);
    }

    .form-textarea {
        resize: vertical;
        min-height: 100px;
    }

    .modal-form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        padding-top: 0.5rem;
        border-top: 1px solid var(--border);
    }

    .modal-form-actions .btn-primary svg {
        width: 18px;
        height: 18px;
    }

    .icon-picker {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .icon-picker-btn {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        border: 2px solid var(--border);
        border-radius: 10px;
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.15s;
        -webkit-tap-highlight-color: transparent;
    }

    .icon-picker-btn:hover {
        border-color: var(--accent);
        background: var(--bg-primary);
    }

    .icon-picker-btn.selected {
        border-color: var(--accent);
        background: rgba(95, 97, 230, 0.15);
        box-shadow: 0 0 0 1px var(--accent);
    }

    /* Rich text editor */
    .rich-editor {
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
        background: var(--bg-card);
    }

    .rich-editor-toolbar {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.5rem;
        border-bottom: 1px solid var(--border);
        background: var(--bg-primary);
        flex-wrap: wrap;
    }

    .rich-editor-btn {
        padding: 0.375rem 0.625rem;
        border: none;
        border-radius: 6px;
        background: transparent;
        color: var(--text-primary);
        font-size: 0.875rem;
        cursor: pointer;
        transition: background 0.15s;
    }

    .rich-editor-btn:hover {
        background: var(--border);
    }

    .rich-editor-btn b,
    .rich-editor-btn i,
    .rich-editor-btn u {
        font-weight: 700;
        font-style: italic;
        text-decoration: none;
    }

    .rich-editor-btn svg {
        display: block;
    }

    .rich-editor-sep {
        width: 1px;
        height: 1.25rem;
        background: var(--border);
        margin: 0 0.25rem;
    }

    .rich-editor-content {
        min-height: 120px;
        max-height: 240px;
        overflow-y: auto;
        padding: 0.75rem;
        font-size: 0.875rem;
        line-height: 1.6;
        color: var(--text-primary);
        outline: none;
    }

    .rich-editor-content:empty::before {
        content: attr(data-placeholder);
        color: var(--text-muted);
    }

    .rich-editor-content:focus {
        box-shadow: inset 0 0 0 1px var(--accent);
    }

    .rich-editor-content-tall {
        min-height: 200px;
        max-height: 360px;
    }

    .rich-editor-content ul,
    .rich-editor-content ol {
        margin: 0.5rem 0;
        padding-left: 1.5rem;
    }

    .rich-editor-content a {
        color: var(--accent);
        text-decoration: underline;
    }

    .rich-editor-content h2 {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0.75rem 0 0.35rem 0;
    }

    .rich-editor-content h3 {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0.6rem 0 0.3rem 0;
    }

    .rich-editor-content blockquote {
        margin: 0.5rem 0;
        padding: 0.5rem 0 0.5rem 1rem;
        border-left: 4px solid var(--accent);
        background: var(--bg-primary);
        color: var(--text-secondary);
        font-style: italic;
    }

    .rich-editor-content[style*="text-align: left"],
    .rich-editor-content [style*="text-align: left"] { text-align: left; }
    .rich-editor-content[style*="text-align: center"],
    .rich-editor-content [style*="text-align: center"] { text-align: center; }
    .rich-editor-content[style*="text-align: right"],
    .rich-editor-content [style*="text-align: right"] { text-align: right; }
    .rich-editor-content[style*="text-align: justify"],
    .rich-editor-content [style*="text-align: justify"] { text-align: justify; }

    .rich-editor-select {
        padding: 0.35rem 0.5rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.8125rem;
        background: var(--bg-card);
        color: var(--text-primary);
        cursor: pointer;
        min-width: 7rem;
    }

    .rich-editor-select:focus {
        outline: none;
        border-color: var(--accent);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .knowledge-tabs {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .tab-btn {
            min-width: 120px;
            font-size: 0.8125rem;
            padding: 0.625rem 1rem;
        }

        .section-header {
            flex-direction: column;
            align-items: stretch;
        }

        .section-actions {
            width: 100%;
        }

        .filter-select {
            flex: 1;
            min-width: 0;
        }

        .articles-grid,
        .guides-grid {
            grid-template-columns: 1fr;
        }

        .faq-categories {
            flex-wrap: nowrap;
        }

        .knowledge-modal-content {
            max-width: 100%;
            max-height: 100vh;
            border-radius: 0;
        }

        .modal-header {
            flex-direction: column;
        }

        .modal-actions {
            width: 100%;
        }

        .modal-actions .btn-secondary {
            width: 100%;
            justify-content: center;
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .modal-form-actions {
            flex-direction: column;
        }

        .modal-form-actions .btn-secondary,
        .modal-form-actions .btn-primary {
            width: 100%;
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .tab-btn {
            min-width: 100px;
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    // Rich text editor: sync contenteditable to hidden input
    function richEditorSync(editorEl) {
        const hiddenId = editorEl.dataset.hidden;
        if (hiddenId) {
            const hidden = document.getElementById(hiddenId);
            if (hidden) hidden.value = editorEl.innerHTML;
        }
    }

    function stripHtml(html) {
        const div = document.createElement('div');
        div.innerHTML = html || '';
        return (div.textContent || div.innerText || '').trim();
    }

    document.querySelectorAll('.rich-editor-content').forEach(editor => {
        editor.addEventListener('input', function() { richEditorSync(this); });
        editor.addEventListener('paste', function() { setTimeout(() => richEditorSync(this), 0); });
    });

    document.querySelectorAll('.rich-editor-toolbar').forEach(toolbar => {
        toolbar.addEventListener('click', function(e) {
            const btn = e.target.closest('.rich-editor-btn');
            if (!btn) return;
            e.preventDefault();
            const editorId = this.dataset.editor;
            const cmd = btn.dataset.cmd;
            const value = btn.dataset.value;
            const editor = document.getElementById(editorId);
            if (!editor) return;
            editor.focus();
            if (cmd === 'createLink') {
                const url = prompt('Enter URL:', 'https://');
                if (url) {
                    document.execCommand('createLink', false, url);
                    richEditorSync(editor);
                }
            } else if (cmd === 'formatBlock' && value) {
                document.execCommand('formatBlock', false, value);
                richEditorSync(editor);
            } else {
                document.execCommand(cmd, false, null);
                richEditorSync(editor);
            }
        });
    });

    document.querySelectorAll('.rich-editor-select').forEach(select => {
        select.addEventListener('change', function() {
            const editorId = this.dataset.editor;
            const editor = document.getElementById(editorId);
            if (!editor) return;
            editor.focus();
            document.execCommand('formatBlock', false, this.value);
            richEditorSync(editor);
        });
    });

    // Tab Switching
    function kebabToCamel(str) {
        return str.replace(/-([a-z])/g, (g) => g[1].toUpperCase());
    }

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            const camelTabId = kebabToCamel(tabId);
            
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            const tabContent = document.getElementById(camelTabId + 'Tab');
            if (tabContent) {
                tabContent.classList.add('active');
            }
        });
    });

    // Articles, FAQs, Guides (from backend, per company)
    const articlesData = @json($articles ?? []);
    const faqsData = @json($faqs ?? []);
    const guidesData = @json($guides ?? []);
    const canCreateKnowledgeBase = @json($canCreateKnowledgeBase ?? true);
    const canEditKnowledgeBase = @json($canEditKnowledgeBase ?? true);
    const canDeleteKnowledgeBase = @json($canDeleteKnowledgeBase ?? true);

    const articleCategoriesData = @json($articleCategories ?? []);
    const faqCategoriesData = @json($faqCategories ?? []);
    const guideCategoriesData = @json($guideCategories ?? []);

    const knowledgeBaseApi = {
        baseUrl: '{{ \Illuminate\Support\Str::replaceLast("/articles", "", route("api.knowledge-base.articles.store")) }}',
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        async postCategories(data) {
            return this._post('/categories', data, 'Failed to add category.');
        },
        async _post(path, data, errorLabel) {
            const res = await fetch(this.baseUrl + path, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify(data)
            });
            const json = await res.json();
            if (!res.ok) {
                const msg = json.errors ? Object.values(json.errors).flat().join(' ') : (json.message || errorLabel);
                throw new Error(msg);
            }
            return json;
        },
        async postArticles(data) {
            return this._post('/articles', data, 'Failed to create article.');
        },
        async postFaqs(data) {
            return this._post('/faqs', data, 'Failed to create FAQ.');
        },
        async postGuides(data) {
            return this._post('/guides', data, 'Failed to create guide.');
        },
        async _put(path, data, errorLabel) {
            const res = await fetch(this.baseUrl + path, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify(data)
            });
            const json = await res.json();
            if (!res.ok) {
                const msg = json.errors ? Object.values(json.errors).flat().join(' ') : (json.message || errorLabel);
                throw new Error(msg);
            }
            return json;
        },
        async _delete(path, errorLabel) {
            const res = await fetch(this.baseUrl + path, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' }
            });
            const json = res.status === 204 ? {} : await res.json();
            if (!res.ok) {
                const msg = json.message || errorLabel;
                throw new Error(msg);
            }
            return json;
        },
        async putArticle(id, data) {
            return this._put('/articles/' + id, data, 'Failed to update article.');
        },
        async deleteArticle(id) {
            return this._delete('/articles/' + id, 'Failed to delete article.');
        },
        async putFaq(id, data) {
            return this._put('/faqs/' + id, data, 'Failed to update FAQ.');
        },
        async deleteFaq(id) {
            return this._delete('/faqs/' + id, 'Failed to delete FAQ.');
        },
        async putGuide(id, data) {
            return this._put('/guides/' + id, data, 'Failed to update guide.');
        },
        async deleteGuide(id) {
            return this._delete('/guides/' + id, 'Failed to delete guide.');
        }
    };

    function formatArticleStatus(status) {
        const labels = {
            draft: 'Draft',
            published: 'Published',
            archived: 'Archived',
            internal: 'Published',
            public: 'Published',
        };
        return labels[status] || (status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Draft');
    }

    function normalizeArticleStatus(status) {
        if (status === 'internal' || status === 'public') {
            return 'published';
        }
        return status || 'draft';
    }

    function getArticleCategoryFilter() {
        return document.getElementById('articleCategoryFilter')?.value || 'all';
    }

    // Render Articles
    function renderArticles(categoryFilter = 'all') {
        const grid = document.getElementById('articlesGrid');
        const filtered = categoryFilter === 'all'
            ? articlesData
            : articlesData.filter(article => article.category === categoryFilter);
        grid.innerHTML = filtered.map(article => {
            const status = normalizeArticleStatus(article.visibility);
            return `
            <div class="article-card" onclick="openArticle(${article.id})">
                <div class="article-header">
                    <span class="article-badge ${status}">${formatArticleStatus(article.visibility)}</span>
                </div>
                <h3 class="article-title">${article.title}</h3>
                <div class="article-excerpt article-excerpt-html">${article.excerpt}</div>
                <div class="article-footer">
                    ${article.category ? `<span class="article-category">${article.category}</span>` : ''}
                    <span>${article.views} views</span>
                </div>
            </div>
        `;
        }).join('');
    }

    const articleCategoryFilter = document.getElementById('articleCategoryFilter');
    if (articleCategoryFilter) {
        articleCategoryFilter.addEventListener('change', function() {
            renderArticles(this.value);
        });
    }

    // Render FAQs
    function getFaqCategoryFilter() {
        return document.querySelector('.faq-category-btn.active')?.dataset.category || 'all';
    }

    function renderFAQs(category = getFaqCategoryFilter()) {
        const list = document.getElementById('faqsList');
        const filtered = category === 'all' ? faqsData : faqsData.filter(faq => faq.category === category);

        list.innerHTML = filtered.map(faq => {
            const status = normalizeArticleStatus(faq.visibility);
            return `
            <div class="faq-item" onclick="toggleFAQ(${faq.id})">
                <div class="faq-question">
                    <div class="faq-question-main">
                        <span class="article-badge ${status}">${formatArticleStatus(faq.visibility)}</span>
                        <div class="faq-question-text">${faq.question}</div>
                    </div>
                    <svg class="faq-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-text">${faq.answer}</div>
                    <div class="faq-meta">
                        ${faq.category ? `<span>${faq.category}</span>` : ''}
                        <span>${faq.views} views</span>
                        <div class="faq-item-actions" onclick="event.stopPropagation()">
                            ${canEditKnowledgeBase ? `<button type="button" class="btn-secondary" onclick="editFaq(${faq.id})">Edit</button>` : ''}
                            ${canDeleteKnowledgeBase ? `<button type="button" class="knowledge-modal-delete btn-secondary" onclick="deleteFaqConfirm(${faq.id})">Delete</button>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
        }).join('');
    }

    // Render Guides
    function renderGuides() {
        const grid = document.getElementById('guidesGrid');
        grid.innerHTML = guidesData.map(guide => `
            <div class="guide-card" onclick="openGuide(${guide.id})">
                <div class="guide-image">${guide.icon}</div>
                <div class="guide-content">
                    <div class="guide-category">${guide.category}</div>
                    <h3 class="guide-title">${guide.title}</h3>
                    <div class="guide-excerpt guide-excerpt-html">${guide.excerpt}</div>
                    <div class="guide-footer">
                        <span>${guide.duration} read</span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    // FAQ Category Switching (delegated so dynamic buttons work)
    document.getElementById('faqCategoriesContainer').addEventListener('click', function(e) {
        const btn = e.target.closest('.faq-category-btn');
        if (!btn) return;
        this.querySelectorAll('.faq-category-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderFAQs(btn.dataset.category);
    });

    // Toggle FAQ
    function toggleFAQ(faqId) {
        const faqItem = document.querySelector(`.faq-item[onclick="toggleFAQ(${faqId})"]`);
        const isActive = faqItem.classList.contains('active');
        
        // Close all FAQs
        document.querySelectorAll('.faq-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Open clicked FAQ if it wasn't active
        if (!isActive) {
            faqItem.classList.add('active');
        }
    }

    // Open Article/Guide
    function openArticle(articleId) {
        const article = articlesData.find(a => a.id === articleId);
        if (!article) return;
        
        openKnowledgeModal(article, 'article');
    }

    function openGuide(guideId) {
        const guide = guidesData.find(g => g.id === guideId);
        if (!guide) return;
        
        openKnowledgeModal(guide, 'guide');
    }

    let currentKnowledgeItem = null;
    let currentKnowledgeType = null;

    function openKnowledgeModal(item, type) {
        currentKnowledgeItem = item;
        currentKnowledgeType = type;
        document.getElementById('modalBadge').textContent = formatArticleStatus(item.visibility);
        document.getElementById('modalBadge').className = `modal-badge ${normalizeArticleStatus(item.visibility)}`;
        document.getElementById('modalTitle').textContent = item.title;
        document.getElementById('modalCategory').textContent = item.category || 'No category';
        document.getElementById('modalCategory').style.display = type === 'article' && !item.category ? 'none' : '';
        document.getElementById('modalDate').textContent = item.date || 'Dec 31, 2025';
        document.getElementById('modalAuthor').textContent = `By ${item.author || 'Admin'}`;
        
        const contentBody = document.getElementById('contentBody');
        if (type === 'article') {
            contentBody.innerHTML = (item.content && item.content.trim()) ? item.content : `<p>${item.excerpt}</p>`;
        } else {
            contentBody.innerHTML = `<h2>${item.title}</h2><p>${item.excerpt}</p>`;
        }
        
        document.getElementById('knowledgeModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function editContent() {
        if (!currentKnowledgeItem || !currentKnowledgeType) return;
        if (currentKnowledgeType === 'article') {
            startEditArticle(currentKnowledgeItem);
        } else {
            startEditGuide(currentKnowledgeItem);
        }
        closeKnowledgeModal();
    }

    async function deleteContent() {
        if (!currentKnowledgeItem || !currentKnowledgeType) return;
        if (!confirm('Are you sure you want to delete this?')) return;
        try {
            if (currentKnowledgeType === 'article') {
                await knowledgeBaseApi.deleteArticle(currentKnowledgeItem.id);
                const idx = articlesData.findIndex(a => a.id === currentKnowledgeItem.id);
                if (idx !== -1) articlesData.splice(idx, 1);
                renderArticles(getArticleCategoryFilter());
            } else {
                await knowledgeBaseApi.deleteGuide(currentKnowledgeItem.id);
                const idx = guidesData.findIndex(g => g.id === currentKnowledgeItem.id);
                if (idx !== -1) guidesData.splice(idx, 1);
                renderGuides();
            }
            closeKnowledgeModal();
        } catch (err) {
            alert(err.message || 'Failed to delete.');
        }
    }

    function closeKnowledgeModal() {
        document.getElementById('knowledgeModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.getElementById('knowledgeModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeKnowledgeModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (document.getElementById('addCategoryModal').classList.contains('active')) {
                closeAddCategoryModal();
            } else if (document.getElementById('articlePreviewModal').classList.contains('active')) {
                closeArticlePreviewModal();
            } else if (document.getElementById('newArticleModal').classList.contains('active')) {
                closeNewArticleModal();
            } else if (document.getElementById('faqPreviewModal').classList.contains('active')) {
                closeFaqPreviewModal();
            } else if (document.getElementById('newFAQModal').classList.contains('active')) {
                closeNewFAQModal();
            } else if (document.getElementById('newGuideModal').classList.contains('active')) {
                closeNewGuideModal();
            } else {
                closeKnowledgeModal();
            }
        }
    });

    // Add Category Modal
    const addCategoryTypeLabels = { article: 'Article', faq: 'FAQ', guide: 'Guide' };
    function openAddCategoryModal(type) {
        document.getElementById('addCategoryType').value = type;
        document.getElementById('addCategoryModalTitle').textContent = 'Add ' + addCategoryTypeLabels[type] + ' category';
        document.getElementById('addCategoryName').value = '';
        document.getElementById('addCategoryModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeAddCategoryModal() {
        document.getElementById('addCategoryModal').classList.remove('active');
        document.body.style.overflow = '';
    }
    async function submitAddCategory(e) {
        e.preventDefault();
        const type = document.getElementById('addCategoryType').value;
        const name = document.getElementById('addCategoryName').value.trim();
        if (!name) return;
        try {
            const { category } = await knowledgeBaseApi.postCategories({ type, name });
            if (type === 'article') {
                articleCategoriesData.push(category);
                const sel = document.getElementById('newArticleCategory');
                const opt = document.createElement('option');
                opt.value = category.slug;
                opt.textContent = category.name;
                sel.appendChild(opt);
                opt.selected = true;
                const filterSel = document.getElementById('articleCategoryFilter');
                if (filterSel) {
                    const filterOpt = document.createElement('option');
                    filterOpt.value = category.name;
                    filterOpt.textContent = category.name;
                    filterSel.appendChild(filterOpt);
                } else {
                    const sectionActions = document.querySelector('#articlesTab .section-actions');
                    if (sectionActions) {
                        const select = document.createElement('select');
                        select.className = 'filter-select';
                        select.id = 'articleCategoryFilter';
                        select.innerHTML = '<option value="all">All Categories</option>';
                        const filterOpt = document.createElement('option');
                        filterOpt.value = category.name;
                        filterOpt.textContent = category.name;
                        select.appendChild(filterOpt);
                        select.addEventListener('change', function() {
                            renderArticles(this.value);
                        });
                        sectionActions.insertBefore(select, sectionActions.firstChild);
                    }
                }
            } else if (type === 'faq') {
                faqCategoriesData.push(category);
                const sel = document.getElementById('newFAQCategory');
                const opt = document.createElement('option');
                opt.value = category.slug;
                opt.textContent = category.name;
                sel.appendChild(opt);
                opt.selected = true;
                const container = document.getElementById('faqCategoriesContainer');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'faq-category-btn';
                btn.dataset.category = category.name;
                btn.textContent = category.name;
                container.appendChild(btn);
            } else {
                guideCategoriesData.push(category);
                const sel = document.getElementById('newGuideCategory');
                const opt = document.createElement('option');
                opt.value = category.slug;
                opt.textContent = category.name;
                sel.appendChild(opt);
                opt.selected = true;
            }
            closeAddCategoryModal();
        } catch (err) {
            alert(err.message || 'Failed to add category.');
        }
    }
    document.getElementById('addCategoryModal').addEventListener('click', function(e) {
        if (e.target === this) closeAddCategoryModal();
    });

    let editArticleId = null;
    let editFaqId = null;
    let editGuideId = null;

    function setArticleCategoryByDisplayName(displayName) {
        const sel = document.getElementById('newArticleCategory');
        if (!displayName) {
            sel.value = '';
            return;
        }
        for (const opt of sel.options) {
            if (opt.textContent === displayName) { sel.value = opt.value; return; }
        }
        sel.value = '';
    }

    function setFaqCategoryByDisplayName(displayName) {
        const sel = document.getElementById('newFAQCategory');
        if (!displayName) {
            sel.value = '';
            return;
        }
        for (const opt of sel.options) {
            if (opt.textContent === displayName) { sel.value = opt.value; return; }
        }
        sel.value = '';
    }

    function setGuideCategoryByDisplayName(displayName) {
        const sel = document.getElementById('newGuideCategory');
        for (const opt of sel.options) {
            if (opt.textContent === displayName) { sel.value = opt.value; return; }
        }
    }

    function startEditArticle(article) {
        editArticleId = article.id;
        document.getElementById('newArticleTitle').value = article.title;
        const excerptEditor = document.getElementById('newArticleExcerptEditor');
        const excerptHidden = document.getElementById('newArticleExcerpt');
        excerptEditor.innerHTML = article.excerpt || '';
        excerptHidden.value = article.excerpt || '';
        const contentEditor = document.getElementById('newArticleContentEditor');
        const contentHidden = document.getElementById('newArticleContent');
        contentEditor.innerHTML = article.content || '';
        contentHidden.value = article.content || '';
        setArticleCategoryByDisplayName(article.category);
        document.getElementById('newArticleModal').classList.add('active');
        document.querySelector('#newArticleModal .modal-title').textContent = 'Edit Article';
        document.body.style.overflow = 'hidden';
    }

    function startEditGuide(guide) {
        editGuideId = guide.id;
        document.getElementById('newGuideTitle').value = guide.title;
        const excerptEditor = document.getElementById('newGuideExcerptEditor');
        const excerptHidden = document.getElementById('newGuideExcerpt');
        excerptEditor.innerHTML = guide.excerpt || '';
        excerptHidden.value = guide.excerpt || '';
        setGuideCategoryByDisplayName(guide.category);
        document.getElementById('newGuideDuration').value = guide.duration || '';
        document.getElementById('newGuideIcon').value = guide.icon || '📖';
        document.querySelectorAll('#newGuideIconPicker .icon-picker-btn').forEach(btn => {
            btn.classList.toggle('selected', btn.dataset.icon === (guide.icon || '📖'));
        });
        document.getElementById('newGuideModal').classList.add('active');
        document.querySelector('#newGuideModal .modal-title').textContent = 'Edit Guide';
        document.body.style.overflow = 'hidden';
    }

    function editFaq(id) {
        const faq = faqsData.find(f => f.id === id);
        if (!faq) return;
        editFaqId = id;
        document.getElementById('newFAQQuestion').value = faq.question;
        const answerEditor = document.getElementById('newFAQAnswerEditor');
        const answerHidden = document.getElementById('newFAQAnswer');
        answerEditor.innerHTML = faq.answer || '';
        answerHidden.value = faq.answer || '';
        setFaqCategoryByDisplayName(faq.category);
        document.getElementById('newFAQModal').classList.add('active');
        document.querySelector('#newFAQModal .modal-title').textContent = 'Edit FAQ';
        document.body.style.overflow = 'hidden';
    }

    async function deleteFaqConfirm(id) {
        if (!confirm('Are you sure you want to delete this FAQ?')) return;
        try {
            await knowledgeBaseApi.deleteFaq(id);
            const idx = faqsData.findIndex(f => f.id === id);
            if (idx !== -1) faqsData.splice(idx, 1);
            renderFAQs(getFaqCategoryFilter());
        } catch (err) {
            alert(err.message || 'Failed to delete.');
        }
    }

    function syncArticleEditors() {
        ['newArticleExcerptEditor', 'newArticleContentEditor'].forEach(id => {
            const editor = document.getElementById(id);
            if (editor) richEditorSync(editor);
        });
    }

    function collectArticleFormData() {
        syncArticleEditors();
        const title = document.getElementById('newArticleTitle').value.trim();
        const excerptHtml = document.getElementById('newArticleExcerpt').value;
        const excerpt = stripHtml(excerptHtml).length ? excerptHtml : (document.getElementById('newArticleExcerptEditor').innerText || '').trim();
        const category = document.getElementById('newArticleCategory').value;
        const content = document.getElementById('newArticleContent').value;
        const categorySelect = document.getElementById('newArticleCategory');
        const categoryLabel = category
            ? categorySelect.options[categorySelect.selectedIndex]?.textContent || 'No category'
            : 'No category';
        return { title, excerpt, category, categoryLabel, content };
    }

    function validateArticleForm(data) {
        if (!data.title) {
            document.getElementById('newArticleTitle').focus();
            return false;
        }
        if (!data.excerpt) {
            document.getElementById('newArticleExcerptEditor').focus();
            return false;
        }
        return true;
    }

    // New Article Modal
    function createArticle() {
        editArticleId = null;
        document.getElementById('newArticleForm').reset();
        document.querySelector('#newArticleModal .modal-title').textContent = 'New Article';
        const excerptEditor = document.getElementById('newArticleExcerptEditor');
        const excerptHidden = document.getElementById('newArticleExcerpt');
        const contentEditor = document.getElementById('newArticleContentEditor');
        const contentHidden = document.getElementById('newArticleContent');
        if (excerptEditor) { excerptEditor.innerHTML = ''; }
        if (excerptHidden) { excerptHidden.value = ''; }
        if (contentEditor) { contentEditor.innerHTML = ''; }
        if (contentHidden) { contentHidden.value = ''; }
        document.getElementById('newArticleCategory').value = '';
        document.getElementById('newArticleModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeNewArticleModal() {
        document.getElementById('newArticleModal').classList.remove('active');
        if (!document.getElementById('articlePreviewModal').classList.contains('active')) {
            document.body.style.overflow = '';
        }
    }

    function previewArticle(e) {
        e.preventDefault();
        const data = collectArticleFormData();
        if (!validateArticleForm(data)) return;

        document.getElementById('articlePreviewTitle').textContent = data.title;
        document.getElementById('articlePreviewCategory').textContent = data.categoryLabel;
        document.getElementById('articlePreviewMeta').style.display = data.category ? '' : 'none';
        document.getElementById('articlePreviewExcerpt').innerHTML = data.excerpt;
        document.getElementById('articlePreviewContent').innerHTML = data.content || '';
        document.getElementById('articlePreviewBadge').textContent = 'Preview';
        document.getElementById('articlePreviewBadge').className = 'modal-badge draft';

        document.getElementById('articlePreviewModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeArticlePreviewModal() {
        document.getElementById('articlePreviewModal').classList.remove('active');
        if (document.getElementById('newArticleModal').classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    async function saveArticleWithStatus(status) {
        const data = collectArticleFormData();
        if (!validateArticleForm(data)) {
            closeArticlePreviewModal();
            return;
        }

        const payload = {
            title: data.title,
            excerpt: data.excerpt || data.title,
            content: data.content,
            category: data.category || null,
            visibility: status,
        };

        try {
            if (editArticleId) {
                const { article } = await knowledgeBaseApi.putArticle(editArticleId, payload);
                const idx = articlesData.findIndex(a => a.id === editArticleId);
                if (idx !== -1) articlesData[idx] = article;
                editArticleId = null;
            } else {
                const { article } = await knowledgeBaseApi.postArticles(payload);
                articlesData.unshift(article);
            }
            renderArticles(getArticleCategoryFilter());
            closeArticlePreviewModal();
            closeNewArticleModal();
        } catch (err) {
            alert(err.message || 'Failed to save article.');
        }
    }

    document.getElementById('newArticleModal').addEventListener('click', function(e) {
        if (e.target === this) closeNewArticleModal();
    });

    document.getElementById('articlePreviewModal').addEventListener('click', function(e) {
        if (e.target === this) closeArticlePreviewModal();
    });

    // New FAQ Modal
    function syncFaqEditors() {
        const editor = document.getElementById('newFAQAnswerEditor');
        if (editor) richEditorSync(editor);
    }

    function collectFaqFormData() {
        syncFaqEditors();
        const question = document.getElementById('newFAQQuestion').value.trim();
        const answerHtml = document.getElementById('newFAQAnswer').value;
        const answer = stripHtml(answerHtml).length ? answerHtml : (document.getElementById('newFAQAnswerEditor').innerText || '').trim();
        const category = document.getElementById('newFAQCategory').value;
        const categorySelect = document.getElementById('newFAQCategory');
        const categoryLabel = category
            ? categorySelect.options[categorySelect.selectedIndex]?.textContent || 'No category'
            : 'No category';
        return { question, answer, answerHtml, category, categoryLabel };
    }

    function validateFaqForm(data) {
        if (!data.question) {
            document.getElementById('newFAQQuestion').focus();
            return false;
        }
        if (!data.answer) {
            document.getElementById('newFAQAnswerEditor').focus();
            return false;
        }
        return true;
    }

    function createFAQ() {
        editFaqId = null;
        document.getElementById('newFAQForm').reset();
        document.querySelector('#newFAQModal .modal-title').textContent = 'New FAQ';
        const answerEditor = document.getElementById('newFAQAnswerEditor');
        const answerHidden = document.getElementById('newFAQAnswer');
        if (answerEditor) { answerEditor.innerHTML = ''; }
        if (answerHidden) { answerHidden.value = ''; }
        document.getElementById('newFAQCategory').value = '';
        document.getElementById('newFAQModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeNewFAQModal() {
        document.getElementById('newFAQModal').classList.remove('active');
        if (!document.getElementById('faqPreviewModal').classList.contains('active')) {
            document.body.style.overflow = '';
        }
    }

    function previewFaq(e) {
        e.preventDefault();
        const data = collectFaqFormData();
        if (!validateFaqForm(data)) return;

        document.getElementById('faqPreviewQuestion').textContent = data.question;
        document.getElementById('faqPreviewCategory').textContent = data.categoryLabel;
        document.getElementById('faqPreviewMeta').style.display = data.category ? '' : 'none';
        document.getElementById('faqPreviewAnswer').innerHTML = data.answer;
        document.getElementById('faqPreviewBadge').textContent = 'Preview';
        document.getElementById('faqPreviewBadge').className = 'modal-badge draft';

        document.getElementById('faqPreviewModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeFaqPreviewModal() {
        document.getElementById('faqPreviewModal').classList.remove('active');
        if (document.getElementById('newFAQModal').classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }

    async function saveFaqWithStatus(status) {
        const data = collectFaqFormData();
        if (!validateFaqForm(data)) {
            closeFaqPreviewModal();
            return;
        }

        const payload = {
            question: data.question,
            answer: data.answerHtml.length ? data.answerHtml : data.answer,
            category: data.category || null,
            visibility: status,
        };

        try {
            if (editFaqId) {
                const { faq } = await knowledgeBaseApi.putFaq(editFaqId, payload);
                const idx = faqsData.findIndex(f => f.id === editFaqId);
                if (idx !== -1) faqsData[idx] = faq;
                editFaqId = null;
            } else {
                const { faq } = await knowledgeBaseApi.postFaqs(payload);
                faqsData.unshift(faq);
            }
            renderFAQs(getFaqCategoryFilter());
            closeFaqPreviewModal();
            closeNewFAQModal();
        } catch (err) {
            alert(err.message || 'Failed to save FAQ.');
        }
    }

    document.getElementById('newFAQModal').addEventListener('click', function(e) {
        if (e.target === this) closeNewFAQModal();
    });

    document.getElementById('faqPreviewModal').addEventListener('click', function(e) {
        if (e.target === this) closeFaqPreviewModal();
    });

    // New Guide Modal
    function createGuide() {
        editGuideId = null;
        document.getElementById('newGuideForm').reset();
        document.querySelector('#newGuideModal .modal-title').textContent = 'New Guide';
        document.getElementById('newGuideIcon').value = '📖';
        document.querySelectorAll('#newGuideIconPicker .icon-picker-btn').forEach((btn) => {
            btn.classList.toggle('selected', btn.dataset.icon === '📖');
        });
        const guideExcerptEditor = document.getElementById('newGuideExcerptEditor');
        const guideExcerptHidden = document.getElementById('newGuideExcerpt');
        if (guideExcerptEditor) { guideExcerptEditor.innerHTML = ''; }
        if (guideExcerptHidden) { guideExcerptHidden.value = ''; }
        document.getElementById('newGuideModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeNewGuideModal() {
        document.getElementById('newGuideModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    async function submitNewGuide(e) {
        e.preventDefault();
        const title = document.getElementById('newGuideTitle').value.trim();
        const excerptHtml = document.getElementById('newGuideExcerpt').value;
        const excerpt = stripHtml(excerptHtml).length ? excerptHtml : (document.getElementById('newGuideExcerptEditor').innerText || '').trim();
        if (!excerpt) {
            document.getElementById('newGuideExcerptEditor').focus();
            return;
        }
        const category = document.getElementById('newGuideCategory').value;
        const duration = document.getElementById('newGuideDuration').value.trim() || '10 min';
        const icon = document.getElementById('newGuideIcon').value.trim() || '📖';
        if (!title || !category) return;
        try {
            if (editGuideId) {
                const { guide } = await knowledgeBaseApi.putGuide(editGuideId, { title, excerpt: excerpt || title, category, duration, icon });
                const idx = guidesData.findIndex(g => g.id === editGuideId);
                if (idx !== -1) guidesData[idx] = guide;
                editGuideId = null;
            } else {
                const { guide } = await knowledgeBaseApi.postGuides({ title, excerpt: excerpt || title, category, duration, icon });
                guidesData.unshift(guide);
            }
            renderGuides();
            closeNewGuideModal();
        } catch (err) {
            alert(err.message || 'Failed to save guide.');
        }
    }

    document.getElementById('newGuideModal').addEventListener('click', function(e) {
        if (e.target === this) closeNewGuideModal();
    });

    document.getElementById('newGuideIconPicker').addEventListener('click', function(e) {
        const btn = e.target.closest('.icon-picker-btn');
        if (!btn) return;
        document.getElementById('newGuideIcon').value = btn.dataset.icon;
        this.querySelectorAll('.icon-picker-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
    });

    // Initialize
    renderArticles(getArticleCategoryFilter());
    renderFAQs(getFaqCategoryFilter());
    renderGuides();
</script>
@endpush

