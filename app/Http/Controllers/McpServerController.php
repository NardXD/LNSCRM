<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientNote;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseFaq;
use App\Models\KnowledgeBaseGuide;
use App\Models\LeaveCredit;
use App\Models\LeaveRequest;
use App\Models\McpApiKey;
use App\Models\PayrollReport;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SalaryComputation;
use App\Models\Task;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TimeTracking;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class McpServerController extends Controller
{
    /**
     * Handle MCP endpoint - POST for JSON-RPC, GET for SSE (optional).
     * Requires McpApiKeyAuth middleware to set mcp_company_id and mcp_api_key.
     */
    public function handle(Request $request): Response|JsonResponse
    {
        if ($request->isMethod('GET')) {
            return $this->handleGet($request);
        }

        if ($request->isMethod('POST')) {
            return $this->handlePost($request);
        }

        return response()->json(['error' => 'Method not allowed'], 405);
    }

    private function handleGet(Request $request): Response|JsonResponse
    {
        $accept = $request->header('Accept', '');
        if (! str_contains($accept, 'text/event-stream')) {
            return response()->json(['error' => 'Method Not Allowed'], 405);
        }

        return response('', 405)
            ->header('Content-Type', 'application/json');
    }

    private function handlePost(Request $request): Response|JsonResponse
    {
        $this->touchLastUsed($request);

        $body = $request->getContent();
        if (empty($body)) {
            return response()->json($this->jsonRpcError(null, -32700, 'Parse error'));
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json($this->jsonRpcError(null, -32700, 'Parse error'));
        }

        $responses = $this->isBatch($data) ? $this->handleBatch($request, $data) : [$this->handleMessage($request, $data)];

        $filtered = array_filter($responses, fn ($r) => $r !== null);
        if (empty($filtered)) {
            return response('', 202);
        }

        $result = count($filtered) === 1 ? $filtered[0] : $filtered;

        return response()->json($result, 200, [
            'Content-Type' => 'application/json',
        ]);
    }

    private function getCompanyId(Request $request): int
    {
        return (int) $request->attributes->get('mcp_company_id', 0);
    }

    private function touchLastUsed(Request $request): void
    {
        $apiKey = $request->attributes->get('mcp_api_key');
        if ($apiKey instanceof McpApiKey) {
            $apiKey->update(['last_used_at' => now()]);
        }
    }

    private function keyCanWrite(Request $request): bool
    {
        $apiKey = $request->attributes->get('mcp_api_key');

        return $apiKey instanceof McpApiKey && $apiKey->can_write === true;
    }

    private function isWriteTool(string $name): bool
    {
        return in_array($name, [
            'create_client', 'update_client',
            'create_deal', 'update_deal',
            'create_task', 'update_task',
            'create_ticket', 'update_ticket', 'add_ticket_comment',
            'add_client_note',
            'create_quotation', 'update_quotation',
            'create_invoice', 'update_invoice',
            'create_employee', 'update_employee',
            'create_leave_request', 'update_leave_request',
            'create_salary_computation', 'update_salary_computation',
        ], true);
    }

    /**
     * Validate arguments. Returns validated data, or a JSON error string when validation fails.
     *
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>|string
     */
    private function validateArgs(array $args, array $rules): array|string
    {
        $validator = Validator::make($args, $rules);

        if ($validator->fails()) {
            return json_encode([
                'error' => 'Validation failed',
                'details' => $validator->errors()->toArray(),
            ]);
        }

        return $validator->validated();
    }

    /**
     * Confirm a related record exists within the same company.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function existsForCompany(string $model, int $companyId, ?int $id): bool
    {
        if (! $id) {
            return false;
        }

        return $model::query()->where('company_id', $companyId)->whereKey($id)->exists();
    }

    /**
     * Build a JSON-RPC tool result that signals an error to the caller.
     *
     * @return array<string, mixed>
     */
    private function toolError(mixed $id, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'content' => [
                    ['type' => 'text', 'text' => json_encode(['error' => $message])],
                ],
                'isError' => true,
            ],
        ];
    }

    /**
     * Catalog of every tool the API exposes, grouped for display and for
     * building per-key allow-lists in the admin dashboard.
     *
     * @return array<string, array<int, array{name: string, label: string, write: bool}>>
     */
    public static function availableTools(): array
    {
        return [
            'CRM & Sales' => [
                ['name' => 'get_clients', 'label' => 'List clients', 'write' => false],
                ['name' => 'get_client', 'label' => 'View client', 'write' => false],
                ['name' => 'get_deals', 'label' => 'List deals/projects', 'write' => false],
                ['name' => 'get_deal', 'label' => 'View deal/project', 'write' => false],
                ['name' => 'get_tasks', 'label' => 'List tasks', 'write' => false],
                ['name' => 'get_tickets', 'label' => 'List tickets', 'write' => false],
                ['name' => 'get_ticket', 'label' => 'View ticket', 'write' => false],
                ['name' => 'get_activity', 'label' => 'View client activity', 'write' => false],
                ['name' => 'create_client', 'label' => 'Create client', 'write' => true],
                ['name' => 'update_client', 'label' => 'Update client', 'write' => true],
                ['name' => 'create_deal', 'label' => 'Create deal/project', 'write' => true],
                ['name' => 'update_deal', 'label' => 'Update deal/project', 'write' => true],
                ['name' => 'create_task', 'label' => 'Create task', 'write' => true],
                ['name' => 'update_task', 'label' => 'Update task', 'write' => true],
                ['name' => 'create_ticket', 'label' => 'Create ticket', 'write' => true],
                ['name' => 'update_ticket', 'label' => 'Update ticket', 'write' => true],
                ['name' => 'add_ticket_comment', 'label' => 'Add ticket comment', 'write' => true],
                ['name' => 'add_client_note', 'label' => 'Add client note', 'write' => true],
            ],
            'Billing' => [
                ['name' => 'get_invoices', 'label' => 'List invoices', 'write' => false],
                ['name' => 'get_invoices_by_client', 'label' => 'List invoices by client', 'write' => false],
                ['name' => 'get_quotations', 'label' => 'List quotations', 'write' => false],
                ['name' => 'get_quotation', 'label' => 'View quotation', 'write' => false],
                ['name' => 'create_quotation', 'label' => 'Create quotation', 'write' => true],
                ['name' => 'update_quotation', 'label' => 'Update quotation', 'write' => true],
                ['name' => 'create_invoice', 'label' => 'Create invoice', 'write' => true],
                ['name' => 'update_invoice', 'label' => 'Update invoice', 'write' => true],
            ],
            'HR & People' => [
                ['name' => 'get_employees', 'label' => 'List employees', 'write' => false],
                ['name' => 'get_teams', 'label' => 'List teams', 'write' => false],
                ['name' => 'get_departments', 'label' => 'List departments', 'write' => false],
                ['name' => 'get_time_tracking', 'label' => 'List time tracking', 'write' => false],
                ['name' => 'get_leave_requests', 'label' => 'List leave requests', 'write' => false],
                ['name' => 'get_leave_credits', 'label' => 'List leave credits', 'write' => false],
                ['name' => 'create_employee', 'label' => 'Create employee', 'write' => true],
                ['name' => 'update_employee', 'label' => 'Update employee', 'write' => true],
                ['name' => 'create_leave_request', 'label' => 'Create leave request', 'write' => true],
                ['name' => 'update_leave_request', 'label' => 'Update leave request', 'write' => true],
            ],
            'Payroll' => [
                ['name' => 'get_payroll_reports', 'label' => 'List payroll reports', 'write' => false],
                ['name' => 'get_payroll_report', 'label' => 'View payroll report', 'write' => false],
                ['name' => 'get_salary_computations', 'label' => 'List salary computations', 'write' => false],
                ['name' => 'create_salary_computation', 'label' => 'Create salary computation', 'write' => true],
                ['name' => 'update_salary_computation', 'label' => 'Update salary computation', 'write' => true],
            ],
            'Knowledge Base' => [
                ['name' => 'get_knowledge_base', 'label' => 'Read knowledge base', 'write' => false],
            ],
        ];
    }

    /**
     * Flat list of every valid tool name.
     *
     * @return array<int, string>
     */
    public static function toolNames(): array
    {
        $names = [];
        foreach (self::availableTools() as $tools) {
            foreach ($tools as $tool) {
                $names[] = $tool['name'];
            }
        }

        return $names;
    }

    private function isBatch(mixed $data): bool
    {
        return is_array($data) && isset($data[0]) && ! isset($data['jsonrpc']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $batch
     * @return array<int, array<string, mixed>|null>
     */
    private function handleBatch(Request $request, array $batch): array
    {
        $results = [];
        foreach ($batch as $msg) {
            $results[] = $this->handleMessage($request, $msg);
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $msg
     * @return array<string, mixed>|null
     */
    private function handleMessage(Request $request, array $msg): ?array
    {
        $id = $msg['id'] ?? null;
        $method = $msg['method'] ?? null;

        try {
            if ($method === 'initialize') {
                return $this->respondInitialize($msg);
            }
            if ($method === 'notifications/initialized') {
                return null;
            }
            if ($method === 'tools/list') {
                return $this->respondToolsList($msg);
            }
            if ($method === 'tools/call') {
                return $this->respondToolsCall($request, $msg);
            }
            if ($method === 'ping') {
                return $this->respondPing($msg);
            }

            return $this->jsonRpcError($id, -32601, 'Method not found');
        } catch (\Throwable $e) {
            Log::error('MCP server error', [
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->jsonRpcError($id, -32603, 'Internal error: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $msg
     * @return array<string, mixed>
     */
    private function respondInitialize(array $msg): array
    {
        $id = $msg['id'] ?? null;

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => [
                    'tools' => ['listChanged' => true],
                ],
                'serverInfo' => [
                    'name' => 'ItsWorkPlace CRM',
                    'version' => '1.0.0',
                ],
                'instructions' => 'CRM MCP server exposing clients, deals (projects), tickets, invoices, and activity logs.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $msg
     * @return array<string, mixed>
     */
    private function respondToolsList(array $msg): array
    {
        $id = $msg['id'] ?? null;

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'tools' => [
                    [
                        'name' => 'get_clients',
                        'description' => 'Get full client list with contacts',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => ['type' => 'string', 'description' => 'Filter by status (active, inactive, all)'],
                                'search' => ['type' => 'string', 'description' => 'Search by name, contact person, or email'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_client',
                        'description' => 'Get single client profile by ID',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'integer', 'description' => 'Client ID']],
                            'required' => ['id'],
                        ],
                    ],
                    [
                        'name' => 'get_deals',
                        'description' => 'Get all deals/projects with status',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => ['type' => 'string', 'description' => 'Filter by status (all, active, etc.)'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_deal',
                        'description' => 'Get single deal/project detail by ID',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'integer', 'description' => 'Deal/Project ID']],
                            'required' => ['id'],
                        ],
                    ],
                    [
                        'name' => 'get_tickets',
                        'description' => 'Get support tickets (open, closed, pending)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => ['type' => 'string', 'description' => 'Filter by status (all, open, closed, pending, resolved)'],
                                'priority' => ['type' => 'string', 'description' => 'Filter by priority'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_ticket',
                        'description' => 'Get single ticket detail by ID',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'integer', 'description' => 'Ticket ID']],
                            'required' => ['id'],
                        ],
                    ],
                    [
                        'name' => 'get_invoices',
                        'description' => 'Get all invoices with status',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => ['type' => 'string', 'description' => 'Filter by status (all, paid, pending, overdue)'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_invoices_by_client',
                        'description' => 'Get invoices for a specific client',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => ['client_id' => ['type' => 'integer', 'description' => 'Client ID']],
                            'required' => ['client_id'],
                        ],
                    ],
                    [
                        'name' => 'get_activity',
                        'description' => 'Get notes and activity log for a client',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => ['client_id' => ['type' => 'integer', 'description' => 'Client ID']],
                            'required' => ['client_id'],
                        ],
                    ],
                    [
                        'name' => 'get_tasks',
                        'description' => 'Get tasks, optionally filtered by deal/project, status, or assignee',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'project_id' => ['type' => 'integer', 'description' => 'Filter by deal/project ID'],
                                'status' => ['type' => 'string', 'description' => 'Filter by status (todo, in_progress, done, etc.)'],
                                'assigned_to' => ['type' => 'integer', 'description' => 'Filter by assigned user ID'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_employees',
                        'description' => 'Get employees/users in the company',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => ['type' => 'string', 'description' => 'Filter by status (active, inactive, all)'],
                                'department_id' => ['type' => 'integer', 'description' => 'Filter by department ID'],
                                'search' => ['type' => 'string', 'description' => 'Search by name or email'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_time_tracking',
                        'description' => 'Get time tracking records (clock in/out, hours worked)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'user_id' => ['type' => 'integer', 'description' => 'Filter by user ID'],
                                'date_from' => ['type' => 'string', 'description' => 'Start date (YYYY-MM-DD)'],
                                'date_to' => ['type' => 'string', 'description' => 'End date (YYYY-MM-DD)'],
                                'status' => ['type' => 'string', 'description' => 'Filter by status (active, completed)'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_leave_requests',
                        'description' => 'Get leave requests',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => ['type' => 'string', 'description' => 'Filter by status (pending, approved, rejected)'],
                                'user_id' => ['type' => 'integer', 'description' => 'Filter by user ID'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_leave_credits',
                        'description' => 'Get leave credit balances',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'user_id' => ['type' => 'integer', 'description' => 'Filter by user ID'],
                                'year' => ['type' => 'integer', 'description' => 'Filter by year'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_quotations',
                        'description' => 'Get all quotations with status',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => ['type' => 'string', 'description' => 'Filter by status (all, draft, sent, accepted, rejected)'],
                                'client_id' => ['type' => 'integer', 'description' => 'Filter by client ID'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_quotation',
                        'description' => 'Get single quotation detail with line items',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'integer', 'description' => 'Quotation ID']],
                            'required' => ['id'],
                        ],
                    ],
                    [
                        'name' => 'get_payroll_reports',
                        'description' => 'Get payroll reports for the company',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'status' => ['type' => 'string', 'description' => 'Filter by status'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_payroll_report',
                        'description' => 'Get single payroll report with per-employee line items',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'integer', 'description' => 'Payroll report ID']],
                            'required' => ['id'],
                        ],
                    ],
                    [
                        'name' => 'get_salary_computations',
                        'description' => 'Get salary computations per employee',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'user_id' => ['type' => 'integer', 'description' => 'Filter by user ID'],
                                'status' => ['type' => 'string', 'description' => 'Filter by status'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_knowledge_base',
                        'description' => 'Get knowledge base entries (articles, guides, FAQs)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'type' => ['type' => 'string', 'description' => 'Entry type (article, guide, faq). Defaults to article.'],
                                'category' => ['type' => 'string', 'description' => 'Filter by category'],
                                'search' => ['type' => 'string', 'description' => 'Search by title/question/content'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_teams',
                        'description' => 'Get teams with leaders and members',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'active' => ['type' => 'boolean', 'description' => 'Only active teams when true'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'get_departments',
                        'description' => 'Get departments with user counts',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'active' => ['type' => 'boolean', 'description' => 'Only active departments when true'],
                            ],
                        ],
                    ],
                    [
                        'name' => 'create_client',
                        'description' => 'Create a client (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'contact_person' => ['type' => 'string'],
                                'email' => ['type' => 'string'],
                                'phone' => ['type' => 'string'],
                                'industry' => ['type' => 'string'],
                                'status' => ['type' => 'string'],
                                'website' => ['type' => 'string'],
                                'address' => ['type' => 'string'],
                                'revenue' => ['type' => 'number'],
                            ],
                            'required' => ['name'],
                        ],
                    ],
                    [
                        'name' => 'update_client',
                        'description' => 'Update a client (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'integer']],
                            'required' => ['id'],
                        ],
                    ],
                    [
                        'name' => 'create_deal',
                        'description' => 'Create a deal/project (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string'],
                                'client_id' => ['type' => 'integer'],
                                'status' => ['type' => 'string'],
                                'deadline' => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                                'progress' => ['type' => 'integer'],
                            ],
                            'required' => ['title'],
                        ],
                    ],
                    [
                        'name' => 'update_deal',
                        'description' => 'Update a deal/project (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'integer']],
                            'required' => ['id'],
                        ],
                    ],
                    [
                        'name' => 'create_task',
                        'description' => 'Create a task under a deal/project (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'project_id' => ['type' => 'integer'],
                                'title' => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                                'priority' => ['type' => 'string'],
                                'deadline' => ['type' => 'string'],
                                'status' => ['type' => 'string'],
                                'progress' => ['type' => 'integer'],
                                'assigned_to' => ['type' => 'integer'],
                            ],
                            'required' => ['project_id', 'title'],
                        ],
                    ],
                    [
                        'name' => 'update_task',
                        'description' => 'Update a task (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'integer']],
                            'required' => ['id'],
                        ],
                    ],
                    [
                        'name' => 'create_ticket',
                        'description' => 'Create a support ticket (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'subject' => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                                'client_id' => ['type' => 'integer'],
                                'client_name' => ['type' => 'string'],
                                'priority' => ['type' => 'string'],
                                'status' => ['type' => 'string'],
                                'category' => ['type' => 'string'],
                                'assigned_to' => ['type' => 'integer'],
                            ],
                            'required' => ['subject'],
                        ],
                    ],
                    [
                        'name' => 'update_ticket',
                        'description' => 'Update a ticket (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'integer']],
                            'required' => ['id'],
                        ],
                    ],
                    [
                        'name' => 'add_ticket_comment',
                        'description' => 'Add a comment to a ticket (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'ticket_id' => ['type' => 'integer'],
                                'content' => ['type' => 'string'],
                                'user_id' => ['type' => 'integer'],
                            ],
                            'required' => ['ticket_id', 'content'],
                        ],
                    ],
                    [
                        'name' => 'add_client_note',
                        'description' => 'Add a note to a client activity log (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'client_id' => ['type' => 'integer'],
                                'note' => ['type' => 'string'],
                                'user_id' => ['type' => 'integer'],
                            ],
                            'required' => ['client_id', 'note'],
                        ],
                    ],
                    [
                        'name' => 'create_quotation',
                        'description' => 'Create a quotation with optional line items (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'client_id' => ['type' => 'integer'],
                                'quotation_date' => ['type' => 'string'],
                                'valid_until' => ['type' => 'string'],
                                'status' => ['type' => 'string'],
                                'discount_amount' => ['type' => 'number'],
                                'terms_conditions' => ['type' => 'string'],
                                'items' => ['type' => 'array', 'description' => 'Items: item_name, description, quantity, unit_price, tax_percentage'],
                            ],
                            'required' => ['client_id'],
                        ],
                    ],
                    [
                        'name' => 'update_quotation',
                        'description' => 'Update a quotation; pass items to replace lines (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'integer']],
                            'required' => ['id'],
                        ],
                    ],
                    [
                        'name' => 'create_invoice',
                        'description' => 'Create an invoice with optional line items (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'client_id' => ['type' => 'integer'],
                                'invoice_date' => ['type' => 'string'],
                                'due_date' => ['type' => 'string'],
                                'status' => ['type' => 'string'],
                                'tax_rate' => ['type' => 'number'],
                                'notes' => ['type' => 'string'],
                                'items' => ['type' => 'array', 'description' => 'Items: description, quantity, unit_price'],
                            ],
                            'required' => ['client_id'],
                        ],
                    ],
                    [
                        'name' => 'update_invoice',
                        'description' => 'Update invoice scalar fields (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'integer']],
                            'required' => ['id'],
                        ],
                    ],
                    [
                        'name' => 'create_employee',
                        'description' => 'Create an employee/user (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'email' => ['type' => 'string'],
                                'password' => ['type' => 'string'],
                                'role_id' => ['type' => 'integer'],
                                'department_id' => ['type' => 'integer'],
                                'status' => ['type' => 'string'],
                                'phone' => ['type' => 'string'],
                                'address' => ['type' => 'string'],
                                'salary' => ['type' => 'number'],
                                'required_work_hours' => ['type' => 'number'],
                            ],
                            'required' => ['name', 'email', 'password'],
                        ],
                    ],
                    [
                        'name' => 'update_employee',
                        'description' => 'Update an employee/user (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'integer']],
                            'required' => ['id'],
                        ],
                    ],
                    [
                        'name' => 'create_leave_request',
                        'description' => 'Create a leave request (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'user_id' => ['type' => 'integer'],
                                'leave_type' => ['type' => 'string'],
                                'start_date' => ['type' => 'string'],
                                'end_date' => ['type' => 'string'],
                                'days_requested' => ['type' => 'integer'],
                                'reason' => ['type' => 'string'],
                            ],
                            'required' => ['user_id', 'leave_type', 'start_date', 'end_date'],
                        ],
                    ],
                    [
                        'name' => 'update_leave_request',
                        'description' => 'Approve, reject, or update a leave request (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'status' => ['type' => 'string'],
                                'rejection_reason' => ['type' => 'string'],
                                'approved_by' => ['type' => 'integer'],
                            ],
                            'required' => ['id'],
                        ],
                    ],
                    [
                        'name' => 'create_salary_computation',
                        'description' => 'Create a salary computation for an employee (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [
                                'user_id' => ['type' => 'integer'],
                                'period_start_date' => ['type' => 'string'],
                                'period_end_date' => ['type' => 'string'],
                                'base_salary' => ['type' => 'number'],
                                'hours_worked' => ['type' => 'number'],
                                'required_hours' => ['type' => 'number'],
                                'overtime_hours' => ['type' => 'number'],
                                'allowances' => ['type' => 'number'],
                                'deductions' => ['type' => 'number'],
                                'gross_pay' => ['type' => 'number'],
                                'net_pay' => ['type' => 'number'],
                                'status' => ['type' => 'string'],
                            ],
                            'required' => ['user_id', 'period_start_date', 'period_end_date'],
                        ],
                    ],
                    [
                        'name' => 'update_salary_computation',
                        'description' => 'Update a salary computation (requires write access)',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'integer']],
                            'required' => ['id'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $msg
     * @return array<string, mixed>
     */
    private function respondToolsCall(Request $request, array $msg): array
    {
        $id = $msg['id'] ?? null;
        $params = $msg['params'] ?? [];
        $name = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];
        $companyId = $this->getCompanyId($request);

        $apiKey = $request->attributes->get('mcp_api_key');
        if ($apiKey instanceof McpApiKey && ! $apiKey->allowsTool($name)) {
            return $this->toolError($id, 'This API key is not permitted to use the "'.$name.'" endpoint.');
        }

        if ($this->isWriteTool($name) && ! $this->keyCanWrite($request)) {
            return $this->toolError($id, 'This API key is read-only. Write access is required for this tool.');
        }

        $content = match ($name) {
            'get_clients' => $this->toolGetClients($companyId, $arguments),
            'get_client' => $this->toolGetClient($companyId, $arguments),
            'get_deals' => $this->toolGetDeals($companyId, $arguments),
            'get_deal' => $this->toolGetDeal($companyId, $arguments),
            'get_tickets' => $this->toolGetTickets($companyId, $arguments),
            'get_ticket' => $this->toolGetTicket($companyId, $arguments),
            'get_invoices' => $this->toolGetInvoices($companyId, $arguments),
            'get_invoices_by_client' => $this->toolGetInvoicesByClient($companyId, $arguments),
            'get_activity' => $this->toolGetActivity($companyId, $arguments),
            'get_tasks' => $this->toolGetTasks($companyId, $arguments),
            'get_employees' => $this->toolGetEmployees($companyId, $arguments),
            'get_time_tracking' => $this->toolGetTimeTracking($companyId, $arguments),
            'get_leave_requests' => $this->toolGetLeaveRequests($companyId, $arguments),
            'get_leave_credits' => $this->toolGetLeaveCredits($companyId, $arguments),
            'get_quotations' => $this->toolGetQuotations($companyId, $arguments),
            'get_quotation' => $this->toolGetQuotation($companyId, $arguments),
            'get_payroll_reports' => $this->toolGetPayrollReports($companyId, $arguments),
            'get_payroll_report' => $this->toolGetPayrollReport($companyId, $arguments),
            'get_salary_computations' => $this->toolGetSalaryComputations($companyId, $arguments),
            'get_knowledge_base' => $this->toolGetKnowledgeBase($companyId, $arguments),
            'get_teams' => $this->toolGetTeams($companyId, $arguments),
            'get_departments' => $this->toolGetDepartments($companyId, $arguments),
            'create_client' => $this->toolCreateClient($companyId, $arguments),
            'update_client' => $this->toolUpdateClient($companyId, $arguments),
            'create_deal' => $this->toolCreateDeal($companyId, $arguments),
            'update_deal' => $this->toolUpdateDeal($companyId, $arguments),
            'create_task' => $this->toolCreateTask($companyId, $arguments),
            'update_task' => $this->toolUpdateTask($companyId, $arguments),
            'create_ticket' => $this->toolCreateTicket($companyId, $arguments),
            'update_ticket' => $this->toolUpdateTicket($companyId, $arguments),
            'add_ticket_comment' => $this->toolAddTicketComment($companyId, $arguments),
            'add_client_note' => $this->toolAddClientNote($companyId, $arguments),
            'create_quotation' => $this->toolCreateQuotation($companyId, $arguments),
            'update_quotation' => $this->toolUpdateQuotation($companyId, $arguments),
            'create_invoice' => $this->toolCreateInvoice($companyId, $arguments),
            'update_invoice' => $this->toolUpdateInvoice($companyId, $arguments),
            'create_employee' => $this->toolCreateEmployee($companyId, $arguments),
            'update_employee' => $this->toolUpdateEmployee($companyId, $arguments),
            'create_leave_request' => $this->toolCreateLeaveRequest($companyId, $arguments),
            'update_leave_request' => $this->toolUpdateLeaveRequest($companyId, $arguments),
            'create_salary_computation' => $this->toolCreateSalaryComputation($companyId, $arguments),
            'update_salary_computation' => $this->toolUpdateSalaryComputation($companyId, $arguments),
            default => json_encode(['error' => "Unknown tool: {$name}"])
        };

        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'content' => [
                    ['type' => 'text', 'text' => is_string($content) ? $content : json_encode($content)],
                ],
                'isError' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $msg
     * @return array<string, mixed>
     */
    private function respondPing(array $msg): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $msg['id'] ?? null,
            'result' => [],
        ];
    }

    private function jsonRpcError(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

    private function toolGetClients(int $companyId, array $args): string
    {
        $query = Client::where('company_id', $companyId)
            ->with('contacts')
            ->orderBy('name');

        if (! empty($args['status']) && ($args['status'] ?? '') !== 'all') {
            $query->where('status', $args['status']);
        }
        if (! empty($args['search'])) {
            $s = $args['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('contact_person', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            });
        }

        $clients = $query->get()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'contact_person' => $c->contact_person,
            'email' => $c->email,
            'phone' => $c->phone,
            'industry' => $c->industry,
            'status' => $c->status,
            'website' => $c->website,
            'address' => $c->address,
            'revenue' => (float) $c->revenue,
            'contacts' => $c->contacts->map(fn ($con) => [
                'name' => $con->name,
                'role' => $con->role,
                'email' => $con->email,
                'phone' => $con->phone,
            ])->values()->all(),
        ])->values()->all();

        return json_encode(['clients' => $clients], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetClient(int $companyId, array $args): string
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return json_encode(['error' => 'Client ID is required']);
        }

        $client = Client::where('company_id', $companyId)
            ->with(['contacts', 'notes.user'])
            ->find($id);

        if (! $client) {
            return json_encode(['error' => 'Client not found']);
        }

        $data = [
            'id' => $client->id,
            'name' => $client->name,
            'contact_person' => $client->contact_person,
            'email' => $client->email,
            'phone' => $client->phone,
            'industry' => $client->industry,
            'status' => $client->status,
            'website' => $client->website,
            'address' => $client->address,
            'revenue' => (float) $client->revenue,
            'contacts' => $client->contacts->map(fn ($c) => [
                'name' => $c->name,
                'role' => $c->role,
                'email' => $c->email,
                'phone' => $c->phone,
            ])->values()->all(),
            'notes' => $client->notes->take(20)->map(fn ($n) => [
                'note' => $n->note,
                'author' => $n->user?->name,
                'created_at' => $n->created_at->toIso8601String(),
            ])->values()->all(),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetDeals(int $companyId, array $args): string
    {
        $query = Project::where('company_id', $companyId)
            ->with(['client', 'teamMembers', 'tasks'])
            ->orderBy('created_at', 'desc');

        if (! empty($args['status']) && ($args['status'] ?? '') !== 'all') {
            $query->where('status', $args['status']);
        }

        $deals = $query->get()->map(function ($p) {
            $clientName = $p->client?->name ?? (is_string($p->client) ? $p->client : null);

            return [
                'id' => $p->id,
                'title' => $p->title,
                'client_id' => $p->client_id,
                'client_name' => $clientName,
                'status' => $p->status,
                'progress' => $p->calculateProgress(),
                'deadline' => $p->deadline?->format('Y-m-d'),
                'description' => $p->description,
                'tasks_total' => $p->tasks()->count(),
                'tasks_completed' => $p->tasks()->where('status', 'done')->count(),
            ];
        })->values()->all();

        return json_encode(['deals' => $deals], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetDeal(int $companyId, array $args): string
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return json_encode(['error' => 'Deal ID is required']);
        }

        $project = Project::where('company_id', $companyId)
            ->with(['client', 'teamMembers', 'tasks'])
            ->find($id);

        if (! $project) {
            return json_encode(['error' => 'Deal not found']);
        }

        $data = [
            'id' => $project->id,
            'title' => $project->title,
            'client_id' => $project->client_id,
            'client_name' => $project->client?->name,
            'status' => $project->status,
            'progress' => $project->calculateProgress(),
            'deadline' => $project->deadline?->format('Y-m-d'),
            'description' => $project->description,
            'tasks' => $project->tasks->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'status' => $t->status,
                'progress' => $t->progress,
            ])->values()->all(),
            'team_members' => $project->teamMembers->pluck('name')->values()->all(),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetTickets(int $companyId, array $args): string
    {
        $query = Ticket::where('company_id', $companyId)
            ->with(['assignedUser', 'client'])
            ->orderBy('created_at', 'desc');

        if (! empty($args['status']) && ($args['status'] ?? '') !== 'all') {
            $query->where('status', $args['status']);
        }
        if (! empty($args['priority'])) {
            $query->where('priority', $args['priority']);
        }

        $tickets = $query->get()->map(function ($t) {
            $clientName = $t->client?->name ?? $t->client_name;

            return [
                'id' => $t->id,
                'ticket_number' => $t->ticket_number,
                'subject' => $t->subject,
                'client_id' => $t->client_id,
                'client_name' => $clientName,
                'status' => $t->status,
                'priority' => $t->priority,
                'category' => $t->category,
                'assigned_to' => $t->assignedUser?->name,
                'created_at' => $t->created_at->toIso8601String(),
            ];
        })->values()->all();

        return json_encode(['tickets' => $tickets], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetTicket(int $companyId, array $args): string
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return json_encode(['error' => 'Ticket ID is required']);
        }

        $ticket = Ticket::where('company_id', $companyId)
            ->with(['assignedUser', 'client', 'comments.user'])
            ->find($id);

        if (! $ticket) {
            return json_encode(['error' => 'Ticket not found']);
        }

        $data = [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'client_id' => $ticket->client_id,
            'client_name' => $ticket->client?->name ?? $ticket->client_name,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'category' => $ticket->category,
            'assigned_to' => $ticket->assignedUser?->name,
            'created_at' => $ticket->created_at->toIso8601String(),
            'first_response_at' => $ticket->first_response_at?->toIso8601String(),
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'comments' => $ticket->comments->map(fn ($c) => [
                'author' => $c->user?->name,
                'content' => $c->content,
                'created_at' => $c->created_at->toIso8601String(),
            ])->values()->all(),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetInvoices(int $companyId, array $args): string
    {
        $query = Invoice::where('company_id', $companyId)
            ->with('client')
            ->orderBy('created_at', 'desc');

        if (! empty($args['status']) && ($args['status'] ?? '') !== 'all') {
            $query->where('status', $args['status']);
        }

        $invoices = $query->get()->map(fn ($inv) => [
            'id' => $inv->id,
            'invoice_number' => $inv->invoice_number,
            'client_id' => $inv->client_id,
            'client_name' => $inv->client?->name,
            'invoice_date' => $inv->invoice_date?->format('Y-m-d'),
            'due_date' => $inv->due_date?->format('Y-m-d'),
            'total' => (float) $inv->total,
            'status' => $inv->status,
            'notes' => $inv->notes,
        ])->values()->all();

        return json_encode(['invoices' => $invoices], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetInvoicesByClient(int $companyId, array $args): string
    {
        $clientId = (int) ($args['client_id'] ?? 0);
        if ($clientId <= 0) {
            return json_encode(['error' => 'Client ID is required']);
        }

        $client = Client::where('company_id', $companyId)->find($clientId);
        if (! $client) {
            return json_encode(['error' => 'Client not found']);
        }

        $invoices = Invoice::where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->with('client')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'client_id' => $inv->client_id,
                'client_name' => $inv->client?->name,
                'invoice_date' => $inv->invoice_date?->format('Y-m-d'),
                'due_date' => $inv->due_date?->format('Y-m-d'),
                'total' => (float) $inv->total,
                'status' => $inv->status,
                'notes' => $inv->notes,
            ])->values()->all();

        return json_encode([
            'client' => ['id' => $client->id, 'name' => $client->name],
            'invoices' => $invoices,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetActivity(int $companyId, array $args): string
    {
        $clientId = (int) ($args['client_id'] ?? 0);
        if ($clientId <= 0) {
            return json_encode(['error' => 'Client ID is required']);
        }

        $client = Client::where('company_id', $companyId)->find($clientId);
        if (! $client) {
            return json_encode(['error' => 'Client not found']);
        }

        $notes = ClientNote::where('client_id', $clientId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'note' => $n->note,
                'author' => $n->user?->name,
                'created_at' => $n->created_at->toIso8601String(),
            ])->values()->all();

        return json_encode([
            'client' => ['id' => $client->id, 'name' => $client->name],
            'activity' => $notes,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetTasks(int $companyId, array $args): string
    {
        $query = Task::query()
            ->with(['project', 'assignedUser'])
            ->whereHas('project', fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('order')
            ->orderByDesc('created_at');

        if (! empty($args['project_id'])) {
            $query->where('project_id', (int) $args['project_id']);
        }
        if (! empty($args['status']) && ($args['status'] ?? '') !== 'all') {
            $query->where('status', $args['status']);
        }
        if (! empty($args['assigned_to'])) {
            $query->where('assigned_to', (int) $args['assigned_to']);
        }

        $tasks = $query->get()->map(fn ($t) => [
            'id' => $t->id,
            'title' => $t->title,
            'project_id' => $t->project_id,
            'project_title' => $t->project?->title,
            'assigned_to' => $t->assignedUser?->name,
            'priority' => $t->priority,
            'status' => $t->status,
            'progress' => $t->progress,
            'deadline' => $t->deadline?->format('Y-m-d'),
            'description' => $t->description,
        ])->values()->all();

        return json_encode(['tasks' => $tasks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetEmployees(int $companyId, array $args): string
    {
        $query = User::where('company_id', $companyId)
            ->with(['role', 'department'])
            ->orderBy('name');

        if (! empty($args['status']) && ($args['status'] ?? '') !== 'all') {
            $query->where('status', $args['status']);
        }
        if (! empty($args['department_id'])) {
            $query->where('department_id', (int) $args['department_id']);
        }
        if (! empty($args['search'])) {
            $s = $args['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            });
        }

        $employees = $query->get()->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'status' => $u->status,
            'is_admin' => (bool) $u->is_admin,
            'role' => $u->role?->name,
            'department' => $u->department?->name,
            'employment_date' => $u->employment_date?->format('Y-m-d'),
            'required_work_hours' => $u->required_work_hours !== null ? (float) $u->required_work_hours : null,
        ])->values()->all();

        return json_encode(['employees' => $employees], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetTimeTracking(int $companyId, array $args): string
    {
        $query = TimeTracking::where('company_id', $companyId)
            ->with('user')
            ->orderByDesc('date')
            ->orderByDesc('created_at');

        if (! empty($args['user_id'])) {
            $query->where('user_id', (int) $args['user_id']);
        }
        if (! empty($args['status'])) {
            $query->where('status', $args['status']);
        }
        if (! empty($args['date_from'])) {
            $query->whereDate('date', '>=', $args['date_from']);
        }
        if (! empty($args['date_to'])) {
            $query->whereDate('date', '<=', $args['date_to']);
        }

        $records = $query->limit(500)->get()->map(fn ($r) => [
            'id' => $r->id,
            'user_id' => $r->user_id,
            'employee' => $r->user?->name,
            'date' => $r->date instanceof \Carbon\Carbon ? $r->date->format('Y-m-d') : (string) $r->date,
            'time_in' => $r->time_in,
            'time_out' => $r->time_out,
            'hours_worked' => $r->hours_worked_formatted,
            'status' => $r->status,
        ])->values()->all();

        return json_encode(['time_tracking' => $records], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetLeaveRequests(int $companyId, array $args): string
    {
        $query = LeaveRequest::where('company_id', $companyId)
            ->with(['user', 'approver'])
            ->orderByDesc('created_at');

        if (! empty($args['status']) && ($args['status'] ?? '') !== 'all') {
            $query->where('status', $args['status']);
        }
        if (! empty($args['user_id'])) {
            $query->where('user_id', (int) $args['user_id']);
        }

        $requests = $query->get()->map(fn ($l) => [
            'id' => $l->id,
            'employee' => $l->user?->name,
            'leave_type' => $l->leave_type,
            'start_date' => $l->start_date?->format('Y-m-d'),
            'end_date' => $l->end_date?->format('Y-m-d'),
            'days_requested' => $l->days_requested,
            'status' => $l->status,
            'reason' => $l->reason,
            'approved_by' => $l->approver?->name,
            'approved_at' => $l->approved_at?->toIso8601String(),
        ])->values()->all();

        return json_encode(['leave_requests' => $requests], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetLeaveCredits(int $companyId, array $args): string
    {
        $query = LeaveCredit::where('company_id', $companyId)
            ->with('user')
            ->orderByDesc('year');

        if (! empty($args['user_id'])) {
            $query->where('user_id', (int) $args['user_id']);
        }
        if (! empty($args['year'])) {
            $query->where('year', (int) $args['year']);
        }

        $credits = $query->get()->map(fn ($c) => [
            'id' => $c->id,
            'employee' => $c->user?->name,
            'leave_type' => $c->leave_type,
            'credits' => (float) $c->credits,
            'year' => $c->year,
            'notes' => $c->notes,
        ])->values()->all();

        return json_encode(['leave_credits' => $credits], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetQuotations(int $companyId, array $args): string
    {
        $query = Quotation::where('company_id', $companyId)
            ->with('client')
            ->orderByDesc('created_at');

        if (! empty($args['status']) && ($args['status'] ?? '') !== 'all') {
            $query->where('status', $args['status']);
        }
        if (! empty($args['client_id'])) {
            $query->where('client_id', (int) $args['client_id']);
        }

        $quotations = $query->get()->map(fn ($q) => [
            'id' => $q->id,
            'quotation_number' => $q->quotation_number,
            'client_id' => $q->client_id,
            'client_name' => $q->client?->name,
            'quotation_date' => $q->quotation_date?->format('Y-m-d'),
            'valid_until' => $q->valid_until?->format('Y-m-d'),
            'status' => $q->status,
            'subtotal' => (float) $q->subtotal,
            'tax_amount' => (float) $q->tax_amount,
            'discount_amount' => (float) $q->discount_amount,
            'total' => (float) $q->total,
        ])->values()->all();

        return json_encode(['quotations' => $quotations], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetQuotation(int $companyId, array $args): string
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return json_encode(['error' => 'Quotation ID is required']);
        }

        $quotation = Quotation::where('company_id', $companyId)
            ->with(['client', 'items'])
            ->find($id);

        if (! $quotation) {
            return json_encode(['error' => 'Quotation not found']);
        }

        $data = [
            'id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'client_id' => $quotation->client_id,
            'client_name' => $quotation->client?->name,
            'quotation_date' => $quotation->quotation_date?->format('Y-m-d'),
            'valid_until' => $quotation->valid_until?->format('Y-m-d'),
            'status' => $quotation->status,
            'subtotal' => (float) $quotation->subtotal,
            'tax_amount' => (float) $quotation->tax_amount,
            'discount_amount' => (float) $quotation->discount_amount,
            'total' => (float) $quotation->total,
            'terms_conditions' => $quotation->terms_conditions,
            'items' => $quotation->items->map(fn ($i) => [
                'item_name' => $i->item_name,
                'description' => $i->description,
                'quantity' => (float) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'tax_percentage' => (float) $i->tax_percentage,
                'total' => (float) $i->total,
            ])->values()->all(),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetPayrollReports(int $companyId, array $args): string
    {
        $query = PayrollReport::where('company_id', $companyId)
            ->withCount('items')
            ->orderByDesc('period_start_date');

        if (! empty($args['status']) && ($args['status'] ?? '') !== 'all') {
            $query->where('status', $args['status']);
        }

        $reports = $query->get()->map(fn ($r) => [
            'id' => $r->id,
            'period_start_date' => $r->period_start_date?->format('Y-m-d'),
            'period_end_date' => $r->period_end_date?->format('Y-m-d'),
            'total_amount' => (float) $r->total_amount,
            'currency' => $r->currency,
            'status' => $r->status,
            'employees' => $r->items_count,
        ])->values()->all();

        return json_encode(['payroll_reports' => $reports], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetPayrollReport(int $companyId, array $args): string
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            return json_encode(['error' => 'Payroll report ID is required']);
        }

        $report = PayrollReport::where('company_id', $companyId)
            ->with('items')
            ->find($id);

        if (! $report) {
            return json_encode(['error' => 'Payroll report not found']);
        }

        $data = [
            'id' => $report->id,
            'period_start_date' => $report->period_start_date?->format('Y-m-d'),
            'period_end_date' => $report->period_end_date?->format('Y-m-d'),
            'total_amount' => (float) $report->total_amount,
            'currency' => $report->currency,
            'status' => $report->status,
            'items' => $report->items->map(fn ($i) => [
                'employee_name' => $i->employee_name,
                'base_salary' => (float) $i->base_salary,
                'gross_pay' => (float) $i->gross_pay,
                'net_pay' => (float) $i->net_pay,
                'hours_worked' => (float) $i->hours_worked,
                'overtime_hours' => (float) $i->overtime_hours,
                'allowances' => (float) $i->allowances,
                'deductions' => (float) $i->deductions,
                'currency' => $i->currency,
                'wise_status' => $i->wise_status,
            ])->values()->all(),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetSalaryComputations(int $companyId, array $args): string
    {
        $query = SalaryComputation::where('company_id', $companyId)
            ->with('user')
            ->orderByDesc('period_start_date');

        if (! empty($args['user_id'])) {
            $query->where('user_id', (int) $args['user_id']);
        }
        if (! empty($args['status']) && ($args['status'] ?? '') !== 'all') {
            $query->where('status', $args['status']);
        }

        $computations = $query->get()->map(fn ($c) => [
            'id' => $c->id,
            'employee' => $c->user?->name,
            'period_start_date' => $c->period_start_date?->format('Y-m-d'),
            'period_end_date' => $c->period_end_date?->format('Y-m-d'),
            'base_salary' => (float) $c->base_salary,
            'hours_worked' => (float) $c->hours_worked,
            'overtime_hours' => (float) $c->overtime_hours,
            'allowances' => (float) $c->allowances,
            'deductions' => (float) $c->deductions,
            'gross_pay' => (float) $c->gross_pay,
            'net_pay' => (float) $c->net_pay,
            'status' => $c->status,
        ])->values()->all();

        return json_encode(['salary_computations' => $computations], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetKnowledgeBase(int $companyId, array $args): string
    {
        $type = $args['type'] ?? 'article';
        $search = $args['search'] ?? null;
        $category = $args['category'] ?? null;

        if ($type === 'faq') {
            $query = KnowledgeBaseFaq::where('company_id', $companyId)->orderByDesc('created_at');
            if ($category) {
                $query->where('category', $category);
            }
            if ($search) {
                $query->where(fn ($q) => $q->where('question', 'like', "%{$search}%")->orWhere('answer', 'like', "%{$search}%"));
            }
            $entries = $query->get()->map(fn ($f) => [
                'id' => $f->id,
                'question' => $f->question,
                'answer' => $f->answer,
                'category' => $f->category,
                'views' => $f->views,
            ])->values()->all();

            return json_encode(['type' => 'faq', 'entries' => $entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if ($type === 'guide') {
            $query = KnowledgeBaseGuide::where('company_id', $companyId)->orderByDesc('created_at');
            if ($category) {
                $query->where('category', $category);
            }
            if ($search) {
                $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('excerpt', 'like', "%{$search}%"));
            }
            $entries = $query->get()->map(fn ($g) => [
                'id' => $g->id,
                'title' => $g->title,
                'excerpt' => $g->excerpt,
                'category' => $g->category,
                'duration' => $g->duration,
            ])->values()->all();

            return json_encode(['type' => 'guide', 'entries' => $entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $query = KnowledgeBaseArticle::where('company_id', $companyId)->orderByDesc('created_at');
        if ($category) {
            $query->where('category', $category);
        }
        if ($search) {
            $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('content', 'like', "%{$search}%"));
        }
        $entries = $query->get()->map(fn ($a) => [
            'id' => $a->id,
            'title' => $a->title,
            'excerpt' => $a->excerpt,
            'category' => $a->category,
            'visibility' => $a->visibility,
            'views' => $a->views,
        ])->values()->all();

        return json_encode(['type' => 'article', 'entries' => $entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetTeams(int $companyId, array $args): string
    {
        $query = Team::where('company_id', $companyId)
            ->with(['leader', 'members'])
            ->orderBy('name');

        if (! empty($args['active'])) {
            $query->where('is_active', true);
        }

        $teams = $query->get()->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'description' => $t->description,
            'is_active' => (bool) $t->is_active,
            'leader' => $t->leader?->name,
            'members' => $t->members->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'role' => $m->pivot->role ?? null,
            ])->values()->all(),
        ])->values()->all();

        return json_encode(['teams' => $teams], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolGetDepartments(int $companyId, array $args): string
    {
        $query = Department::where('company_id', $companyId)
            ->withCount('users')
            ->orderBy('name');

        if (! empty($args['active'])) {
            $query->where('is_active', true);
        }

        $departments = $query->get()->map(fn ($d) => [
            'id' => $d->id,
            'name' => $d->name,
            'description' => $d->description,
            'is_active' => (bool) $d->is_active,
            'users_count' => $d->users_count,
        ])->values()->all();

        return json_encode(['departments' => $departments], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function toolCreateClient(int $companyId, array $args): string
    {
        $data = $this->validateArgs($args, [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'industry' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'revenue' => ['nullable', 'numeric'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        $data['company_id'] = $companyId;
        $data['contact_person'] = $data['contact_person'] ?? '';
        $data['email'] = $data['email'] ?? '';
        $client = Client::create($data);

        return json_encode(['success' => true, 'client' => ['id' => $client->id, 'name' => $client->name]], JSON_UNESCAPED_SLASHES);
    }

    private function toolUpdateClient(int $companyId, array $args): string
    {
        $client = Client::where('company_id', $companyId)->find((int) ($args['id'] ?? 0));
        if (! $client) {
            return json_encode(['error' => 'Client not found']);
        }

        $data = $this->validateArgs($args, [
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_person' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'revenue' => ['sometimes', 'nullable', 'numeric'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        $client->update($data);

        return json_encode(['success' => true, 'client' => ['id' => $client->id, 'name' => $client->name]], JSON_UNESCAPED_SLASHES);
    }

    private function toolCreateDeal(int $companyId, array $args): string
    {
        $data = $this->validateArgs($args, [
            'title' => ['required', 'string', 'max:255'],
            'client_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:50'],
            'deadline' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        $clientName = '';
        if (! empty($data['client_id'])) {
            $client = Client::where('company_id', $companyId)->find((int) $data['client_id']);
            if (! $client) {
                return json_encode(['error' => 'Client not found for this company']);
            }
            $clientName = $client->name;
        }

        $deal = Project::create([
            'company_id' => $companyId,
            'title' => $data['title'],
            'client' => $clientName,
            'client_id' => $data['client_id'] ?? null,
            'status' => $data['status'] ?? 'active',
            'deadline' => $data['deadline'] ?? now()->toDateString(),
            'description' => $data['description'] ?? null,
            'progress' => $data['progress'] ?? 0,
        ]);

        return json_encode(['success' => true, 'deal' => ['id' => $deal->id, 'title' => $deal->title]], JSON_UNESCAPED_SLASHES);
    }

    private function toolUpdateDeal(int $companyId, array $args): string
    {
        $deal = Project::where('company_id', $companyId)->find((int) ($args['id'] ?? 0));
        if (! $deal) {
            return json_encode(['error' => 'Deal not found']);
        }

        $data = $this->validateArgs($args, [
            'title' => ['sometimes', 'string', 'max:255'],
            'client_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'description' => ['sometimes', 'nullable', 'string'],
            'progress' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        if (! empty($data['client_id']) && ! $this->existsForCompany(Client::class, $companyId, (int) $data['client_id'])) {
            return json_encode(['error' => 'Client not found for this company']);
        }

        $deal->update($data);

        return json_encode(['success' => true, 'deal' => ['id' => $deal->id, 'title' => $deal->title]], JSON_UNESCAPED_SLASHES);
    }

    private function toolCreateTask(int $companyId, array $args): string
    {
        $data = $this->validateArgs($args, [
            'project_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', 'max:50'],
            'deadline' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'assigned_to' => ['nullable', 'integer'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        if (! $this->existsForCompany(Project::class, $companyId, (int) $data['project_id'])) {
            return json_encode(['error' => 'Deal/project not found for this company']);
        }
        if (! empty($data['assigned_to']) && ! $this->existsForCompany(User::class, $companyId, (int) $data['assigned_to'])) {
            return json_encode(['error' => 'Assigned user not found for this company']);
        }

        $task = Task::create($data);

        return json_encode(['success' => true, 'task' => ['id' => $task->id, 'title' => $task->title]], JSON_UNESCAPED_SLASHES);
    }

    private function toolUpdateTask(int $companyId, array $args): string
    {
        $task = Task::whereHas('project', fn ($q) => $q->where('company_id', $companyId))
            ->find((int) ($args['id'] ?? 0));
        if (! $task) {
            return json_encode(['error' => 'Task not found']);
        }

        $data = $this->validateArgs($args, [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', 'nullable', 'string', 'max:50'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'progress' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'assigned_to' => ['sometimes', 'nullable', 'integer'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        if (! empty($data['assigned_to']) && ! $this->existsForCompany(User::class, $companyId, (int) $data['assigned_to'])) {
            return json_encode(['error' => 'Assigned user not found for this company']);
        }

        $task->update($data);

        return json_encode(['success' => true, 'task' => ['id' => $task->id, 'title' => $task->title]], JSON_UNESCAPED_SLASHES);
    }

    private function toolCreateTicket(int $companyId, array $args): string
    {
        $data = $this->validateArgs($args, [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'client_id' => ['nullable', 'integer'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'string', 'max:100'],
            'assigned_to' => ['nullable', 'integer'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        if (! empty($data['client_id']) && ! $this->existsForCompany(Client::class, $companyId, (int) $data['client_id'])) {
            return json_encode(['error' => 'Client not found for this company']);
        }
        if (! empty($data['assigned_to']) && ! $this->existsForCompany(User::class, $companyId, (int) $data['assigned_to'])) {
            return json_encode(['error' => 'Assigned user not found for this company']);
        }

        $next = Ticket::where('company_id', $companyId)->count() + 1;
        $data['company_id'] = $companyId;
        $data['ticket_number'] = 'TKT-'.now()->year.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        $data['status'] = $data['status'] ?? 'open';
        $data['client_name'] = $data['client_name'] ?? '';
        $data['description'] = $data['description'] ?? '';

        $ticket = Ticket::create($data);

        return json_encode(['success' => true, 'ticket' => ['id' => $ticket->id, 'ticket_number' => $ticket->ticket_number]], JSON_UNESCAPED_SLASHES);
    }

    private function toolUpdateTicket(int $companyId, array $args): string
    {
        $ticket = Ticket::where('company_id', $companyId)->find((int) ($args['id'] ?? 0));
        if (! $ticket) {
            return json_encode(['error' => 'Ticket not found']);
        }

        $data = $this->validateArgs($args, [
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'client_id' => ['sometimes', 'nullable', 'integer'],
            'client_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'priority' => ['sometimes', 'nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'assigned_to' => ['sometimes', 'nullable', 'integer'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        if (! empty($data['assigned_to']) && ! $this->existsForCompany(User::class, $companyId, (int) $data['assigned_to'])) {
            return json_encode(['error' => 'Assigned user not found for this company']);
        }

        if (($data['status'] ?? null) === 'resolved' && ! $ticket->resolved_at) {
            $data['resolved_at'] = now();
        }

        $ticket->update($data);

        return json_encode(['success' => true, 'ticket' => ['id' => $ticket->id, 'status' => $ticket->status]], JSON_UNESCAPED_SLASHES);
    }

    private function toolAddTicketComment(int $companyId, array $args): string
    {
        $data = $this->validateArgs($args, [
            'ticket_id' => ['required', 'integer'],
            'content' => ['required', 'string'],
            'user_id' => ['nullable', 'integer'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        if (! $this->existsForCompany(Ticket::class, $companyId, (int) $data['ticket_id'])) {
            return json_encode(['error' => 'Ticket not found for this company']);
        }
        if (! empty($data['user_id']) && ! $this->existsForCompany(User::class, $companyId, (int) $data['user_id'])) {
            return json_encode(['error' => 'User not found for this company']);
        }

        $comment = TicketComment::create($data);

        return json_encode(['success' => true, 'comment' => ['id' => $comment->id, 'ticket_id' => $comment->ticket_id]], JSON_UNESCAPED_SLASHES);
    }

    private function toolAddClientNote(int $companyId, array $args): string
    {
        $data = $this->validateArgs($args, [
            'client_id' => ['required', 'integer'],
            'note' => ['required', 'string'],
            'user_id' => ['nullable', 'integer'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        if (! $this->existsForCompany(Client::class, $companyId, (int) $data['client_id'])) {
            return json_encode(['error' => 'Client not found for this company']);
        }
        if (! empty($data['user_id']) && ! $this->existsForCompany(User::class, $companyId, (int) $data['user_id'])) {
            return json_encode(['error' => 'User not found for this company']);
        }

        $note = ClientNote::create($data);

        return json_encode(['success' => true, 'note' => ['id' => $note->id, 'client_id' => $note->client_id]], JSON_UNESCAPED_SLASHES);
    }

    private function toolCreateQuotation(int $companyId, array $args): string
    {
        $data = $this->validateArgs($args, [
            'client_id' => ['required', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'quotation_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'terms_conditions' => ['nullable', 'string'],
            'discount_amount' => ['nullable', 'numeric'],
            'items' => ['nullable', 'array'],
            'items.*.item_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['nullable', 'numeric'],
            'items.*.unit_price' => ['nullable', 'numeric'],
            'items.*.tax_percentage' => ['nullable', 'numeric'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        if (! $this->existsForCompany(Client::class, $companyId, (int) $data['client_id'])) {
            return json_encode(['error' => 'Client not found for this company']);
        }

        $userId = ! empty($data['user_id']) ? (int) $data['user_id'] : User::where('company_id', $companyId)->value('id');
        if (! $userId) {
            return json_encode(['error' => 'No user available to attribute the quotation. Provide user_id.']);
        }
        if (! $this->existsForCompany(User::class, $companyId, $userId)) {
            return json_encode(['error' => 'User not found for this company']);
        }

        $items = $data['items'] ?? [];
        [$subtotal, $taxAmount] = $this->computeLineTotals($items, true);
        $discount = (float) ($data['discount_amount'] ?? 0);
        $quotationDate = $data['quotation_date'] ?? now()->toDateString();

        $quotation = Quotation::create([
            'company_id' => $companyId,
            'client_id' => (int) $data['client_id'],
            'user_id' => $userId,
            'quotation_number' => Quotation::generateQuotationNumber(),
            'quotation_date' => $quotationDate,
            'valid_until' => $data['valid_until'] ?? date('Y-m-d', strtotime($quotationDate.' +30 days')),
            'status' => $data['status'] ?? 'draft',
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discount,
            'total' => $subtotal + $taxAmount - $discount,
            'terms_conditions' => $data['terms_conditions'] ?? null,
        ]);

        $this->syncQuotationItems($quotation, $items);

        return json_encode(['success' => true, 'quotation' => ['id' => $quotation->id, 'quotation_number' => $quotation->quotation_number, 'total' => (float) $quotation->total]], JSON_UNESCAPED_SLASHES);
    }

    private function toolUpdateQuotation(int $companyId, array $args): string
    {
        $quotation = Quotation::where('company_id', $companyId)->find((int) ($args['id'] ?? 0));
        if (! $quotation) {
            return json_encode(['error' => 'Quotation not found']);
        }

        $data = $this->validateArgs($args, [
            'quotation_date' => ['sometimes', 'nullable', 'date'],
            'valid_until' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'terms_conditions' => ['sometimes', 'nullable', 'string'],
            'discount_amount' => ['sometimes', 'nullable', 'numeric'],
            'items' => ['sometimes', 'array'],
            'items.*.item_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['nullable', 'numeric'],
            'items.*.unit_price' => ['nullable', 'numeric'],
            'items.*.tax_percentage' => ['nullable', 'numeric'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        $scalars = collect($data)->only(['quotation_date', 'valid_until', 'status', 'terms_conditions', 'discount_amount'])->all();
        $quotation->fill($scalars);

        if (array_key_exists('items', $data)) {
            $items = $data['items'];
            $quotation->items()->delete();
            $this->syncQuotationItems($quotation, $items);
            [$subtotal, $taxAmount] = $this->computeLineTotals($items, true);
            $quotation->subtotal = $subtotal;
            $quotation->tax_amount = $taxAmount;
            $quotation->total = $subtotal + $taxAmount - (float) ($quotation->discount_amount ?? 0);
        }

        $quotation->save();

        return json_encode(['success' => true, 'quotation' => ['id' => $quotation->id, 'total' => (float) $quotation->total]], JSON_UNESCAPED_SLASHES);
    }

    private function toolCreateInvoice(int $companyId, array $args): string
    {
        $data = $this->validateArgs($args, [
            'client_id' => ['required', 'integer'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'tax_rate' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric'],
            'items.*.unit_price' => ['nullable', 'numeric'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        if (! $this->existsForCompany(Client::class, $companyId, (int) $data['client_id'])) {
            return json_encode(['error' => 'Client not found for this company']);
        }

        $items = $data['items'] ?? [];
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) ($item['quantity'] ?? 1) * (float) ($item['unit_price'] ?? 0);
        }
        $taxRate = (float) ($data['tax_rate'] ?? 0);
        $taxAmount = $subtotal * ($taxRate / 100);
        $invoiceDate = $data['invoice_date'] ?? now()->toDateString();

        $invoice = Invoice::create([
            'company_id' => $companyId,
            'client_id' => (int) $data['client_id'],
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'invoice_date' => $invoiceDate,
            'due_date' => $data['due_date'] ?? date('Y-m-d', strtotime($invoiceDate.' +30 days')),
            'status' => $data['status'] ?? 'draft',
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
            'notes' => $data['notes'] ?? null,
        ]);

        $sort = 0;
        foreach ($items as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $item['description'] ?? '',
                'quantity' => (float) ($item['quantity'] ?? 1),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'total' => (float) ($item['quantity'] ?? 1) * (float) ($item['unit_price'] ?? 0),
                'sort_order' => $sort++,
            ]);
        }

        return json_encode(['success' => true, 'invoice' => ['id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'total' => (float) $invoice->total]], JSON_UNESCAPED_SLASHES);
    }

    private function toolUpdateInvoice(int $companyId, array $args): string
    {
        $invoice = Invoice::where('company_id', $companyId)->find((int) ($args['id'] ?? 0));
        if (! $invoice) {
            return json_encode(['error' => 'Invoice not found']);
        }

        $data = $this->validateArgs($args, [
            'invoice_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        $invoice->update($data);

        return json_encode(['success' => true, 'invoice' => ['id' => $invoice->id, 'status' => $invoice->status]], JSON_UNESCAPED_SLASHES);
    }

    private function toolCreateEmployee(int $companyId, array $args): string
    {
        $data = $this->validateArgs($args, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'salary' => ['nullable', 'numeric'],
            'required_work_hours' => ['nullable', 'numeric'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        if (! empty($data['department_id']) && ! $this->existsForCompany(Department::class, $companyId, (int) $data['department_id'])) {
            return json_encode(['error' => 'Department not found for this company']);
        }

        $data['company_id'] = $companyId;
        $data['password'] = Hash::make($data['password']);
        $data['status'] = $data['status'] ?? 'active';

        $user = User::create($data);

        return json_encode(['success' => true, 'employee' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]], JSON_UNESCAPED_SLASHES);
    }

    private function toolUpdateEmployee(int $companyId, array $args): string
    {
        $user = User::where('company_id', $companyId)->find((int) ($args['id'] ?? 0));
        if (! $user) {
            return json_encode(['error' => 'Employee not found']);
        }

        $data = $this->validateArgs($args, [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['sometimes', 'string', 'min:8'],
            'role_id' => ['sometimes', 'nullable', 'integer'],
            'department_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'nullable', 'string'],
            'salary' => ['sometimes', 'nullable', 'numeric'],
            'required_work_hours' => ['sometimes', 'nullable', 'numeric'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        if (! empty($data['department_id']) && ! $this->existsForCompany(Department::class, $companyId, (int) $data['department_id'])) {
            return json_encode(['error' => 'Department not found for this company']);
        }
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return json_encode(['success' => true, 'employee' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email]], JSON_UNESCAPED_SLASHES);
    }

    private function toolCreateLeaveRequest(int $companyId, array $args): string
    {
        $data = $this->validateArgs($args, [
            'user_id' => ['required', 'integer'],
            'leave_type' => ['required', 'string', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'days_requested' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        if (! $this->existsForCompany(User::class, $companyId, (int) $data['user_id'])) {
            return json_encode(['error' => 'User not found for this company']);
        }

        $data['company_id'] = $companyId;
        $data['status'] = 'pending';
        if (empty($data['days_requested'])) {
            $data['days_requested'] = max(1, (int) (strtotime($data['end_date']) - strtotime($data['start_date'])) / 86400 + 1);
        }

        $leave = LeaveRequest::create($data);

        return json_encode(['success' => true, 'leave_request' => ['id' => $leave->id, 'status' => $leave->status]], JSON_UNESCAPED_SLASHES);
    }

    private function toolUpdateLeaveRequest(int $companyId, array $args): string
    {
        $leave = LeaveRequest::where('company_id', $companyId)->find((int) ($args['id'] ?? 0));
        if (! $leave) {
            return json_encode(['error' => 'Leave request not found']);
        }

        $data = $this->validateArgs($args, [
            'status' => ['sometimes', 'string', 'in:pending,approved,rejected,cancelled'],
            'rejection_reason' => ['sometimes', 'nullable', 'string'],
            'approved_by' => ['sometimes', 'nullable', 'integer'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        if (! empty($data['approved_by']) && ! $this->existsForCompany(User::class, $companyId, (int) $data['approved_by'])) {
            return json_encode(['error' => 'Approver not found for this company']);
        }

        if (($data['status'] ?? null) === 'approved') {
            $data['approved_at'] = now();
        }

        $leave->update($data);

        return json_encode(['success' => true, 'leave_request' => ['id' => $leave->id, 'status' => $leave->status]], JSON_UNESCAPED_SLASHES);
    }

    private function toolCreateSalaryComputation(int $companyId, array $args): string
    {
        $data = $this->validateArgs($args, [
            'user_id' => ['required', 'integer'],
            'period_start_date' => ['required', 'date'],
            'period_end_date' => ['required', 'date', 'after_or_equal:period_start_date'],
            'base_salary' => ['nullable', 'numeric'],
            'hours_worked' => ['nullable', 'numeric'],
            'required_hours' => ['nullable', 'numeric'],
            'overtime_hours' => ['nullable', 'numeric'],
            'allowances' => ['nullable', 'numeric'],
            'deductions' => ['nullable', 'numeric'],
            'gross_pay' => ['nullable', 'numeric'],
            'net_pay' => ['nullable', 'numeric'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        if (! $this->existsForCompany(User::class, $companyId, (int) $data['user_id'])) {
            return json_encode(['error' => 'User not found for this company']);
        }

        $data['company_id'] = $companyId;
        $data['status'] = $data['status'] ?? 'draft';

        $computation = SalaryComputation::create($data);

        return json_encode(['success' => true, 'salary_computation' => ['id' => $computation->id, 'status' => $computation->status]], JSON_UNESCAPED_SLASHES);
    }

    private function toolUpdateSalaryComputation(int $companyId, array $args): string
    {
        $computation = SalaryComputation::where('company_id', $companyId)->find((int) ($args['id'] ?? 0));
        if (! $computation) {
            return json_encode(['error' => 'Salary computation not found']);
        }

        $data = $this->validateArgs($args, [
            'base_salary' => ['sometimes', 'nullable', 'numeric'],
            'hours_worked' => ['sometimes', 'nullable', 'numeric'],
            'required_hours' => ['sometimes', 'nullable', 'numeric'],
            'overtime_hours' => ['sometimes', 'nullable', 'numeric'],
            'allowances' => ['sometimes', 'nullable', 'numeric'],
            'deductions' => ['sometimes', 'nullable', 'numeric'],
            'gross_pay' => ['sometimes', 'nullable', 'numeric'],
            'net_pay' => ['sometimes', 'nullable', 'numeric'],
            'status' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);
        if (is_string($data)) {
            return $data;
        }

        $computation->update($data);

        return json_encode(['success' => true, 'salary_computation' => ['id' => $computation->id, 'status' => $computation->status]], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Sum subtotal and tax for an array of line items.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{0: float, 1: float}
     */
    private function computeLineTotals(array $items, bool $withTax): array
    {
        $subtotal = 0.0;
        $taxAmount = 0.0;

        foreach ($items as $item) {
            $lineSubtotal = (float) ($item['quantity'] ?? 1) * (float) ($item['unit_price'] ?? 0);
            $subtotal += $lineSubtotal;
            if ($withTax) {
                $taxAmount += $lineSubtotal * ((float) ($item['tax_percentage'] ?? 0) / 100);
            }
        }

        return [$subtotal, $taxAmount];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncQuotationItems(Quotation $quotation, array $items): void
    {
        $sort = 0;
        foreach ($items as $item) {
            $lineSubtotal = (float) ($item['quantity'] ?? 1) * (float) ($item['unit_price'] ?? 0);
            $taxAmount = $lineSubtotal * ((float) ($item['tax_percentage'] ?? 0) / 100);

            QuotationItem::create([
                'quotation_id' => $quotation->id,
                'item_name' => $item['item_name'] ?? '',
                'description' => $item['description'] ?? null,
                'quantity' => (float) ($item['quantity'] ?? 1),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'tax_percentage' => (float) ($item['tax_percentage'] ?? 0),
                'tax_amount' => $taxAmount,
                'total' => $lineSubtotal + $taxAmount,
                'sort_order' => $sort++,
            ]);
        }
    }
}
