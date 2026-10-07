# LNSCRM Feature Catalogue and Business Workflows

**Document type:** Business analysis / current-state functional overview  
**Basis of review:** Application routes, controllers, services, models, views, permissions, background jobs, integrations, and automated tests  
**Review date:** 7 October 2026

## 1. Executive summary

LNSCRM is a multi-company customer relationship and business operations platform. It combines sales lead management, omnichannel communications, client and project administration, workforce management, payroll, billing, reporting, and platform administration in one permission-controlled system.

The principal business flow is:

> Customer enquiry arrives -> conversation/contact is identified -> lead is created and assigned -> team follows up -> quotation is issued -> contract is signed -> lead becomes a client -> project and employees are managed -> time is recorded -> payroll and client invoices are produced -> payment and profitability are monitored.

The system supports separate employee, platform administrator, and client-portal experiences. Company data is tenant-scoped, modules can be enabled per company, and roles/permissions restrict what each user can see or change.

## 2. Main user groups

| User group | Primary use |
|---|---|
| Sales and customer service agents | Manage enquiries, conversations, leads, quotations, calls, and follow-ups |
| Team leaders and managers | Assign work, manage rules, monitor queues, review reports, and approve operational records |
| Employees and contractors | Time in/out, work on projects, communicate internally, and submit leave requests |
| HR and payroll staff | Manage employees, teams, leave, salary calculations, payroll reports, and Wise recipients |
| Finance staff | Create invoices, manage subscriptions, issue payment links, reconcile payments, and review P&L |
| Company administrators | Manage users, roles, permissions, integrations, modules, and company settings |
| Platform administrators | Manage tenant companies, plans, access, system settings, support access, and API keys |
| Client portal users | View projects, time summaries, invoices, assigned employees, recordings, and live screens where allowed |

## 3. Feature catalogue

### 3.1 Dashboard and notifications

**Purpose:** Give users an operational summary and direct attention to pending work.

**Features**

- Company dashboard with lead overview and business metrics.
- Notifications with unread totals, channel-specific totals, individual read state, and mark-all-read.
- Real-time browser updates for supported events.
- Company-specific timezone handling for dates and times.

**Workflow**

1. The authenticated user opens the dashboard.
2. The system loads metrics that belong to the user's company and permitted scope.
3. Notifications highlight new assignments, messages, mentions, and other activities.
4. The user opens the relevant module and marks notifications as read.

### 3.2 Leads and sales pipeline

**Purpose:** Maintain prospective customers from first enquiry through conversion.

**Features**

- Create, view, update, search, filter, and delete leads.
- Configurable lead statuses and labels.
- Lead ownership and reassignment.
- Notes, identities/contact details, activity log, and consolidated contact history.
- Attach or detach email conversations and view connected communication threads.
- Send one-to-one or bulk messages using available channels.
- Automated rules, including assignment, round-robin distribution, labels, status changes, follow-up messages, lead-age actions, and scheduled status changes.
- Automatic lead creation or matching from supported communication channels.
- Lead reporting, status totals, funnel analysis, and report export.
- Push/update leads in Storeganise and check possible duplicates.
- Map a lead into a storage quotation and convert a lead into a client as part of downstream processing.

**Workflow**

1. A lead is entered manually or created/matched from an inbound email, SMS, WhatsApp, Facebook, or Instagram interaction.
2. The system stores the contact identities and applies active lead rules.
3. The lead is assigned directly or through a round-robin pool; status and labels classify the opportunity.
4. An agent reviews the full interaction history, adds notes, and follows up through an available channel.
5. Scheduled rules handle ageing, future status changes, and follow-up emails.
6. The agent prepares a quotation and, when successful, proceeds to contract/client fulfilment.
7. Managers monitor pipeline results through lead reports and exports.

### 3.3 Contact history

**Purpose:** Provide one cross-channel view of customer interactions.

**Features**

- Search by telephone number and/or email address.
- Match interactions across CRM communication sources.
- Normalize phone identities, including WhatsApp matches.
- Show related communication history without requiring staff to search each channel separately.

**Workflow:** A user searches for a contact identity -> the system normalizes it -> related conversations/messages are gathered -> the user reviews the chronological customer history.

### 3.4 Outlook inbox and shared email operations

**Purpose:** Provide a Front-style personal and shared Outlook inbox inside the CRM.

**Features**

- Microsoft Outlook connection and mail synchronization.
- Personal and shared inboxes with controlled membership.
- Inbox views, search (including message ID), sent mail, unread counts, and conversation sorting.
- Assign conversations, add participants/followers, subscribe or unsubscribe, and set status.
- Tags, CRM lead labels, pinned tags, configurable sidebar labels, and custom time format.
- Reply, compose, forward/resend, attach files, save drafts, share drafts, and schedule replies.
- Conversation snoozing, reopening, merging, and unmerging.
- Internal comments with attachments and editing/deletion.
- Link a conversation to a lead or detach it.
- Reusable reply templates and email signatures, including imports and default signatures.
- Inbox automation rules and background queue processing.
- Imports from Front for tags, comments, teammate discussions, teammates/templates, and knowledge-base content.

**Workflow**

1. An administrator connects Outlook and configures shared inbox membership.
2. Mail synchronization imports messages and groups them into contact-based threads.
3. A new thread is assigned manually or by a rule; tags and lead matching are applied.
4. An agent collaborates through internal comments or shared drafts.
5. The agent replies immediately or schedules a response using a template/signature.
6. The conversation is snoozed, archived/closed, reopened, or merged as needed.
7. Relevant conversations remain linked to the lead and appear in its history.

### 3.5 Channel messaging: WhatsApp, Viber, Facebook/Instagram, and SMS

**Purpose:** Handle external customer messaging from dedicated channel workspaces.

**Common features**

- Conversation list and message history.
- Receive inbound webhook events and synchronize history where supported.
- Send outbound messages; SMS and WhatsApp sending can be queued.
- Media upload/attachments where supported.
- Reusable channel-specific message templates.
- Contact extraction/matching and lead naming.

**Channel-specific capabilities**

- **WhatsApp:** Twilio/WhatsApp Business conversations, media, read state, lead labels, and call links.
- **Viber:** Twilio-backed business conversations, media, and customer messaging.
- **Facebook and Instagram:** Graph/Twilio messaging, read state, lead labels, history synchronization, attachments, and channel reports.
- **SMS:** Start a text conversation, view threads, send messages, use call links, and use assigned Twilio numbers.

**Workflow:** An inbound message reaches the channel webhook -> the message and conversation are stored -> the customer is matched to a lead where possible -> an agent reads and replies -> labels/templates support consistent handling -> activity remains available to CRM history and reporting.

### 3.6 Broadcast messaging

**Purpose:** Send controlled bulk SMS or email campaigns.

**Features**

- Create and list campaigns.
- Select recipients from leads, clients, and contacts, or paste addresses/numbers.
- Compose plain or HTML email and SMS content.
- Background campaign processing.
- Per-recipient delivery/result tracking.
- Retry failed recipients.
- Company-level messaging limits.

**Workflow:** User creates a campaign -> selects channel and recipients -> writes content -> system validates limits and queues delivery -> recipient results are recorded -> failed deliveries can be retried.

### 3.7 Phone system

**Purpose:** Provide Twilio-based inbound/outbound calling, SMS, agent presence, and CRM screen-pop.

**Features**

- Browser calling with Twilio capability tokens.
- Inbound call queue and round-robin routing among available agents.
- Agent presence/availability heartbeat.
- Call status, call history, recording callbacks, and call logs.
- Phone contacts and SMS threads.
- Search, purchase/manage, release, and assign Twilio phone numbers.
- Twilio Flex CRM lookup and customer screen-pop.

**Workflow:** A call arrives -> the system identifies the company/number -> an available agent is selected -> CRM lookup opens the matching record -> the call status and recording are captured -> staff can review the call in history. Outbound calls begin from a CRM call link or phone workspace and follow the same logging process.

### 3.8 Internal messaging and discussions

**Purpose:** Support internal collaboration without placing internal notes in customer-facing channels.

**Internal messaging features**

- Direct/group conversations, unread counts, attachments, edited messages, reactions, mentions, and seen state.
- Add/remove members, transfer ownership, update, or delete a conversation.

**Discussion features**

- Create internal discussion threads and comments.
- Assign, tag, snooze, archive, move, and manage participants.
- Subscribe/unsubscribe and attach files.
- Apply discussion automation rules.
- Import Front discussions.

**Workflow:** A user starts a conversation/discussion -> participants collaborate and receive mention notifications -> ownership/assignment controls responsibility -> the thread is moved, snoozed, or archived when resolved.

### 3.9 Client management and client portal

**Purpose:** Maintain customer accounts and expose selected operational information to clients.

**CRM features**

- Client company profile, contacts, commercial details, address, status, and notes.
- Search, statistics, export, and record maintenance.
- Assign/unassign employees.
- Create projects associated with a client.
- Create and manage client portal users.

**Client portal features**

- Separate client authentication.
- Dashboard, project details, project time tracking, and time summaries.
- Invoice list and billing statistics.
- Assigned employee list and accessible employee recordings.
- Start/end authorized live-view sessions.

**Workflow:** Staff create the client -> add contacts and notes -> assign employees/projects -> provision a portal user -> client signs in -> client reviews projects, time, invoices, and permitted monitoring information.

### 3.10 Quotation builder and storage quotes

**Purpose:** Create commercial proposals and progress accepted work into contracts.

**Features**

- Create, search, view, edit, delete, and status-track quotations.
- Automatic quotation numbering and line items.
- Client/lead selection and client filters.
- PDF quote and contract-document generation.
- Email quotations using customizable templates and Microsoft 365 mail.
- Status history/audit trail.
- Create a contract directly from a quotation.
- Storage-specific unit lookup, unit search, quote mapping, print, download, email, and save.

**Workflow:** User selects a lead/client -> adds quote items or maps storage requirements -> system calculates and numbers the quote -> user previews/downloads or emails it -> status changes are recorded -> accepted quote is converted into a contract.

### 3.11 Contracts and electronic signatures

**Purpose:** Create agreements and collect signatures remotely.

**Features**

- Create, edit, view, delete, and cancel contracts.
- Automatic contract numbering, rich contract content, signer records, and status history.
- Generate PDF documents.
- Send signing requests through a configurable email template/Microsoft 365 mail.
- Public token-based signing page that does not require a CRM account.
- Capture submitted electronic signatures and update contract state.

**Workflow:** Staff create a contract manually or from an accepted quote -> add contract terms and signer -> send a secure signing link -> signer opens the public page and signs -> the system records signer/status history -> signed PDF remains available in CRM.

### 3.12 Project management

**Purpose:** Plan client work and track delivery.

**Features**

- Create, edit, list, and delete projects.
- Create, edit, list, and delete project tasks.
- Assign users and associate projects with clients.
- Track time against projects/tasks.
- Project statistics, time summary, active timer, and progress dashboard.

**Workflow:** Manager creates a client project -> adds tasks and assignees -> employees record time -> task/project progress updates -> managers and authorized clients review progress and time summaries.

### 3.13 Time tracking and desktop/mobile recorder

**Purpose:** Record attendance and working time, with optional screen evidence.

**Features**

- Time in/out and active-record status.
- Work records and edit history.
- Start/stop recording and view current-day recordings.
- Recorder applications for macOS and iOS with token authentication, chunked uploads, offline upload queue, and upload status.
- Company timezone-aware time records.
- Inputs into project reporting, payroll, monitoring, billing, and client portal summaries.

**Workflow:** Employee signs in to the recorder/CRM -> clocks in and optionally records the screen -> chunks upload and finalize in the background -> employee clocks out -> authorized staff review time and recordings -> approved data supports payroll and client reporting.

### 3.14 Employee monitoring and live view

**Purpose:** Let authorized managers review employee activity and, where allowed, observe a live screen.

**Features**

- Employee activity/recording list and sync-health monitoring.
- Recording playback and controlled media delivery.
- WebRTC live-view sessions with signalling and ICE configuration.
- Start/end sessions, session list, chat, audio support, and notifications.
- Cleanup of stale live-view sessions.
- Client live view for specifically assigned employees.

**Workflow:** Authorized viewer selects an employee -> reviews historical recordings or requests a live session -> employee/client endpoints exchange WebRTC signals -> the session is monitored and can include chat/audio -> session is explicitly ended or cleaned up when stale.

### 3.15 User, role, department, and team management

**Purpose:** Control the workforce structure and system access.

**Features**

- Employee/user creation, update, pagination, and management.
- Departments, sales representatives, and client assignment.
- Roles and granular permissions.
- Twilio number assignment options.
- Team creation, update, deletion, member assignment, team leaders, and available-user lookup.
- Company module access controls.

**Workflow:** Administrator creates the employee -> assigns department/team/client and optional phone number -> assigns a role -> role permissions determine visible screens/actions -> later changes take effect within the same company scope.

### 3.16 Leave management

**Purpose:** Manage employee leave requests and balances.

**Features**

- Submit, list, update, approve/reject, and delete leave requests according to access.
- Maintain leave credits.
- Personal leave-balance view.
- Leave calendar, employees-on-leave list, and summary statistics.

**Workflow:** Employee submits a leave request -> manager reviews availability and balance -> approves/rejects -> credit and calendar views reflect the decision -> management sees who is on leave.

### 3.17 Payroll and Wise payments

**Purpose:** Calculate pay from time records and create auditable payroll outputs.

**Features**

- Review employee time-in/out records.
- Salary computation and saved calculation history.
- Generate, save, view, print, and export payroll reports.
- Sales-representative payroll summary.
- Convert payroll periods/results into invoice-related records.
- Manage Wise recipients/accounts for employees.
- Maintain payroll report and computation history.

**Workflow:** Payroll selects a period and employees -> reviews recorded time -> calculates compensation -> corrects/saves the computation with history -> generates the payroll report -> prepares Wise recipient/payment data -> marks the payroll cycle complete.

### 3.18 Billing, payments, and subscriptions

**Purpose:** Bill clients and monitor collection.

**Features**

- Invoice creation, numbering, client/employee-derived line items, edit, delete, view, and statistics.
- PDF generation and email delivery, including bulk sending.
- Payment tracking and invoice status.
- Stripe payment links and bulk payment-link generation.
- Wise payment links, incoming-payment view, webhook control/status, and manual Wise-paid action.
- Subscription create, view, update, cancel, delete, trial period, and payment link.
- Stripe and Wise dashboards/webhooks.

**Workflow:** Finance selects a client and billable employees/items -> creates a numbered invoice -> produces PDF and sends email/payment link -> Stripe/Wise webhook or staff action records payment -> billing statistics and client portal update.

### 3.19 Profit and loss reporting

**Purpose:** Compare client invoice income with payroll and other expenses.

**Features**

- Invoice-basis revenue view.
- Payroll conversion details.
- Manual expense maintenance.
- Period-based profitability reporting.

**Workflow:** System gathers invoice revenue and converted payroll cost -> finance adds other expenses -> P&L calculates the period result -> management reviews profitability.

### 3.20 Tickets and helpdesk

**Purpose:** Track support or operational issues through resolution.

**Features**

- Create, list, view, and update tickets.
- Use reference/form data for categorization and assignment.
- Add ticket comments to preserve the resolution history.

**Workflow:** User logs a ticket -> responsible staff review and update it -> collaborators add comments -> status is updated until resolution.

### 3.21 Knowledge base

**Purpose:** Maintain structured internal help content.

**Features**

- Hierarchical categories, including category movement.
- Articles, FAQs, and guides.
- Separate create, edit, and delete permissions.
- Import knowledge content from Front.

**Workflow:** Authorized editor creates/categories content -> users browse it in the knowledge-base workspace -> editors revise or reorganize content -> obsolete content is removed under permission control.

### 3.22 Hiring assistant and hiring queue

**Purpose:** Capture hiring requirements and track candidates.

**Features**

- Public AI-assisted hiring intake.
- Save assistant output into the internal hiring queue.
- Create, view, edit, search, and status-track hiring requests.
- Candidate list and candidate status management.
- Internal comments and PDF output.

**Workflow:** Requester explains a hiring need to the assistant -> structured request enters the hiring queue -> recruiters review and update it -> candidates are added and moved through statuses -> comments and PDF provide an auditable hiring brief.

### 3.23 Calendar and scheduling

**Purpose:** Manage events and synchronize external calendars.

**Features**

- Google Calendar and Outlook Calendar OAuth connections.
- View, create, edit, and delete calendar events.
- Event title, dates/times, location, attendees, and description.
- Connection status, disconnect, and OAuth settings management.
- Calendar events generated from relevant inbox activity where supported.

**Workflow:** User connects Google or Outlook -> CRM loads external events -> user creates/updates/deletes an event -> provider service synchronizes the change -> all dates display in the configured timezone.

### 3.24 AI assistant and MCP/API access

**Purpose:** Provide AI assistance using company context and controlled machine access to CRM capabilities.

**Features**

- OpenAI-backed chat with CRM context.
- Company OpenAI integration settings.
- Platform-level AI model/settings and per-company token limits.
- Hiring assistant use case.
- MCP endpoint secured by company API key for exposed CRM tools.

**Workflow:** Administrator configures credentials/limits -> authorized user submits a request -> system builds permitted company context -> AI returns a response -> usage remains bounded by company settings. External MCP clients authenticate using a company API key before invoking supported tools.

### 3.25 Integration management

**Purpose:** Connect external communication, payment, storage, and productivity services.

| Integration | Business use |
|---|---|
| Microsoft 365 / Outlook | Shared inbox, outbound quote/contract mail, and calendar |
| Google / Gmail | Calendar and configured email integration |
| Twilio | Voice, SMS, WhatsApp, Viber, phone numbers, and Flex |
| Facebook / Instagram | Social messaging and conversation history |
| Stripe | Payment links, subscriptions, and payment webhooks |
| Wise | Payment links, incoming-payment reconciliation, and payroll recipients |
| Storeganise | Storage sites, unit/customer operations, and lead synchronization |
| Front | Migration of tags, comments, discussions, templates/teammates, and knowledge content |
| OpenAI | AI assistant and hiring intake |

**Workflow:** Company administrator enters credentials/settings -> CRM validates and stores the company-scoped integration -> users perform supported operations -> webhooks or scheduled/background syncs bring external changes back into CRM -> administrator can disconnect the integration.

### 3.26 Platform administration and multi-company controls

**Purpose:** Operate LNSCRM as a multi-tenant platform.

**Features**

- Separate administrator authentication and control dashboard.
- Company creation, editing, activation/deactivation, and history.
- Plans, company billing, payments, and plan features.
- Enable/disable modules per company.
- Administrative users, roles, and permissions.
- SMTP settings and test email.
- AI configuration and tenant token limits.
- Support override and time-bound company-admin impersonation with restoration.
- MCP API-key management.
- Screen-recording management, preview counts, and synchronization overview.

**Workflow:** Platform admin creates a company and selects a plan -> enables modules -> provisions company administration -> monitors billing/status -> uses audited support impersonation only when needed -> returns to the platform-admin session.

### 3.27 Security and account controls

**Purpose:** Protect tenant data and restrict actions by responsibility.

**Features**

- Employee, administrator, and client authentication areas.
- Company-active checks that prevent access for inactive tenants.
- Company-scoped records and integrations.
- Role-based and action-level permissions.
- Password change and forgotten-password entry point.
- Token authentication for recorder clients, signing links, webhooks, Flex API, and MCP clients.
- Private media delivery and controlled recording access.

## 4. End-to-end business workflows

### 4.1 Enquiry-to-client workflow

1. Enquiry arrives via email, call, SMS, WhatsApp, Viber, Facebook, Instagram, or manual entry.
2. CRM matches the contact to an existing lead or creates a new lead.
3. Lead rules label, prioritize, assign, and schedule follow-ups.
4. Agent communicates and records notes/activity.
5. Agent creates and emails a quotation.
6. Accepted quotation becomes a contract.
7. Customer signs through the secure public signing page.
8. Lead/customer information supports creation or update of the client account.
9. Client receives projects, employees, billing records, and optional portal access.

### 4.2 Omnichannel service workflow

1. Customer contacts the company through a supported channel.
2. Webhook or synchronization stores the interaction.
3. Contact identity links the interaction to a lead/client and related history.
4. Assignment/routing gives an agent ownership.
5. Agent collaborates internally, uses a template, and responds.
6. The conversation is labelled, snoozed, resolved, or reopened.
7. Managers use channel and lead reports to monitor volume and outcomes.

### 4.3 Work-to-payroll workflow

1. Employee clocks in and records project/task time.
2. Recorder optionally captures and uploads screen evidence.
3. Manager reviews attendance, activity, and exceptions.
4. Approved time enters salary computation.
5. Payroll saves the calculation and generates a report.
6. Wise recipient data supports payment preparation.
7. Payroll cost becomes an input into P&L reporting.

### 4.4 Project-to-invoice workflow

1. Client and project are created; employees/tasks are assigned.
2. Employees log time to the project.
3. Managers review progress and time summaries.
4. Finance selects billable employee/time information for invoice line items.
5. Invoice is generated and emailed with Stripe or Wise payment options.
6. Payment status is updated by webhook or reconciliation.
7. Client sees invoice status in the portal; revenue feeds the P&L view.

### 4.5 Leave workflow

1. Administrator establishes employee leave credits.
2. Employee submits dates and leave details.
3. Manager reviews the request and team calendar.
4. Manager approves or rejects it.
5. Leave balance, calendar, and employees-on-leave views update.

## 5. Current implementation status and boundaries

### Operational modules evidenced by application code

The repository contains routes, controllers/services, persistent models, UI, and/or tests for the principal modules described above. Particularly deep implementations are evident for Leads, Inbox, channel messaging, Billing, Quotations, Contracts, Employee Monitoring, Payroll, Client Management, and integrations.

### Areas that should be treated as partial or confirmed before committing externally

| Area | Current-state observation |
|---|---|
| Email Tracking | A detailed UI exists, but the route is a view-only route and chart placeholders are present; no dedicated persistence/controller workflow is evident. Treat it as a prototype or incomplete module. |
| Standalone E-Signature page | A menu/page surface exists, but electronic signing is operationally implemented in the Contracts module. Avoid describing the standalone page as an independent completed product. |
| Forgot password | The route/page exists, but the route contains a logic placeholder. Password recovery should be verified before production claims. |
| Billing Plan employee page | A view is present; plan administration is primarily implemented in the platform-admin billing area. Confirm the intended employee-facing behavior. |
| Project authorizations | The module has view/edit/delete controls; a full approval or stage-gate process is not evident. |
| Lead-to-client conversion | Supporting service code exists, but the exact user-triggered conversion path should be confirmed through acceptance testing. |

## 6. Business rules visible in the system

- Users may only access active companies and data within their company scope.
- Module availability is controlled per company, then actions are further controlled by role permissions.
- Communication integrations and external credentials are company-specific.
- Lead and discussion rules automate repeatable routing and follow-up actions.
- Round-robin state is persisted for lead and inbound-call assignment.
- Important commercial records maintain status history (quotations and contracts); payroll calculations and time edits also retain history.
- Long-running sends, synchronization, and scheduled actions use background jobs/commands.
- External events enter through signed/keyed webhook or callback routes; recorder and MCP clients use tokens/API keys.
- Time and date presentation observes the configured company timezone.

## 7. Recommended next business-analysis artefacts

1. Confirm module owners and mark every module as Production, Pilot, Prototype, or Planned.
2. Define canonical status values and transition rules for leads, quotes, contracts, projects, tickets, candidates, and invoices.
3. Create a role-permission matrix for platform admin, company admin, manager, agent, employee, finance, HR, and client user.
4. Document field-level requirements and mandatory data for the enquiry-to-client journey.
5. Define service-level targets for first response, lead follow-up, ticket resolution, invoice collection, and hiring stages.
6. Establish data-retention and consent rules for messages, call recordings, screen recordings, electronic signatures, and employee monitoring.
7. Run user-acceptance testing on the partial areas identified above before including them in sales or operational commitments.

