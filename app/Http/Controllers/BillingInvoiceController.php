<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use App\Models\GmailIntegration;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PayrollPeriodInvoice;
use App\Models\StripeIntegration;
use App\Models\User;
use App\Services\InvoiceItemHoursSyncService;
use App\Services\InvoicePdfPresentationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class BillingInvoiceController extends Controller
{
    /**
     * Show the billing page with company clients from database.
     */
    public function page()
    {
        $user = Auth::user();
        $clients = [];
        $stripeConnected = false;
        $wiseDefaultLink = null;
        if ($user?->company_id) {
            $clients = Client::where('company_id', $user->company_id)
                ->orderBy('name')
                ->get(['id', 'name']);
            $stripeConnected = StripeIntegration::where('company_id', $user->company_id)
                ->where('is_active', true)
                ->whereNotNull('secret_key')
                ->where('secret_key', '!=', '')
                ->exists();
            $wiseDefaultLink = Company::find($user->company_id)?->default_wise_payment_url;
        }

        return view('dashboard.billing', [
            'billingClients' => $clients,
            'stripeConnected' => $stripeConnected,
            'wiseDefaultLink' => $wiseDefaultLink,
        ]);
    }

    /**
     * Get all invoices for the authenticated user's company.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $query = Invoice::where('company_id', $companyId)
            ->with(['client'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('month')) {
            $month = $request->input('month');
            if (preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) {
                $query->whereYear('invoice_date', (int) $m[1])
                    ->whereMonth('invoice_date', (int) $m[2]);
            }
        }

        $perPage = $request->get('per_page', 15);
        $invoices = $query->paginate($perPage);

        $payrollMap = $this->payrollPeriodDatesByInvoiceIds(
            $companyId,
            $invoices->pluck('id')->all()
        );

        $data = $invoices->map(fn ($inv) => $this->formatInvoice($inv, $payrollMap[$inv->id] ?? null));

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    /**
     * Get invoice statistics, optionally filtered by month (YYYY-MM).
     */
    public function stats(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $baseQuery = Invoice::where('company_id', $companyId);

        if ($request->filled('month') && preg_match('/^(\d{4})-(\d{2})$/', $request->input('month'), $m)) {
            $baseQuery->whereYear('invoice_date', (int) $m[1])
                ->whereMonth('invoice_date', (int) $m[2]);
        }

        $total = (clone $baseQuery)->count();
        $pending = (clone $baseQuery)->whereIn('status', ['sent', 'draft'])->sum('total');
        $paidThisMonth = (clone $baseQuery)->where('status', 'paid')->sum('total');
        $overdue = (clone $baseQuery)->where('status', 'overdue')->sum('total');

        return response()->json([
            'success' => true,
            'data' => [
                'total_invoices' => $total,
                'pending_amount' => (float) $pending,
                'paid_this_month' => (float) $paidThisMonth,
                'overdue_amount' => (float) $overdue,
            ],
        ]);
    }

    /**
     * Get payment tracking data (from invoices with status paid, and pending from sent/draft).
     */
    public function paymentTracking(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user?->company_id;

        if (! $companyId) {
            return response()->json([
                'success' => true,
                'data' => [
                    'payments' => [],
                    'total_received' => 0,
                    'pending_amount' => 0,
                    'pending_count' => 0,
                ],
            ]);
        }

        $month = $request->input('month');
        $baseQuery = Invoice::where('company_id', $companyId)->with('client');

        if ($month && preg_match('/^(\d{4})-(\d{2})$/', $month, $m)) {
            $baseQuery->whereYear('invoice_date', (int) $m[1])
                ->whereMonth('invoice_date', (int) $m[2]);
        }

        $paidInvoices = (clone $baseQuery)->where('status', 'paid')->orderBy('updated_at', 'desc')->get();
        $pendingInvoices = (clone $baseQuery)->where('status', 'sent')->orderBy('invoice_date', 'desc')->get();

        $totalReceived = $paidInvoices->sum('total');
        $pendingAmount = $pendingInvoices->sum('total');
        $pendingCount = $pendingInvoices->count();

        $paidPayments = $paidInvoices->map(function ($inv) {
            return [
                'id' => $inv->id,
                'payment_id' => 'PAY-'.$inv->invoice_number,
                'invoice_number' => $inv->invoice_number,
                'client' => $inv->client?->name ?? '-',
                'date' => $inv->updated_at->format('M d, Y'),
                'amount' => (float) $inv->total,
                'method' => $inv->wise_paid_at ? 'Wise' : ($inv->stripe_payment_url ? 'Stripe' : 'Manual'),
                'status' => 'completed',
                'invoice_id' => $inv->id,
            ];
        });

        $pendingPayments = $pendingInvoices->map(function ($inv) {
            return [
                'id' => $inv->id,
                'payment_id' => 'PENDING-'.$inv->invoice_number,
                'invoice_number' => $inv->invoice_number,
                'client' => $inv->client?->name ?? '-',
                'date' => $inv->invoice_date?->format('M d, Y') ?? '-',
                'amount' => (float) $inv->total,
                'method' => $inv->stripe_payment_url ? 'Stripe' : '-',
                'status' => $inv->status,
                'invoice_id' => $inv->id,
            ];
        });

        $payments = $paidPayments->concat($pendingPayments)->values()->all();

        return response()->json([
            'success' => true,
            'data' => [
                'payments' => $payments,
                'total_received' => (float) $totalReceived,
                'pending_amount' => (float) $pendingAmount,
                'pending_count' => $pendingCount,
            ],
        ]);
    }

    /**
     * Get clients for dropdown, or employees for one client when ?employees_for={id} is passed.
     */
    public function getClients(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user?->company_id;

        if (! $companyId) {
            return response()->json(['success' => true, 'data' => []]);
        }

        if ($request->filled('employees_for')) {
            return $this->clientEmployeesJsonResponse((int) $request->get('employees_for'), $companyId);
        }

        $clients = Client::where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]);

        return response()->json([
            'success' => true,
            'data' => $clients->values()->all(),
        ]);
    }

    /**
     * Employees assigned to a client (for invoice line description suggestions).
     */
    public function getClientEmployees(int $client): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user?->company_id;

        if (! $companyId) {
            return response()->json(['success' => true, 'data' => []]);
        }

        return $this->clientEmployeesJsonResponse($client, $companyId);
    }

    private function clientEmployeesJsonResponse(int $clientId, int $companyId): JsonResponse
    {
        if ($clientId < 1) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $client = Client::query()
            ->where('company_id', $companyId)
            ->where('id', $clientId)
            ->first();

        if (! $client) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found.',
            ], 404);
        }

        $employees = $client->employees()
            ->where('users.company_id', $companyId)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name'])
            ->map(fn (User $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
            ]);

        return response()->json([
            'success' => true,
            'data' => $employees->values()->all(),
        ]);
    }

    /**
     * Get next invoice number.
     */
    public function getNextInvoiceNumber(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'invoice_number' => Invoice::generateInvoiceNumber(),
            ],
        ]);
    }

    /**
     * Create a Stripe Payment Link for an invoice amount.
     */
    public function createStripePaymentLink(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'invoice_id' => ['nullable', 'integer', Rule::exists('invoices', 'id')],
        ]);

        $user = Auth::user();
        $companyId = $user->company_id ?? null;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 400);
        }

        $integration = StripeIntegration::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('secret_key')
            ->where('secret_key', '!=', '')
            ->first();

        if (! $integration) {
            return response()->json(['success' => false, 'message' => 'Stripe integration not configured. Configure it in Integrations.'], 400);
        }

        try {
            $secretKey = Crypt::decryptString($integration->secret_key);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Stripe credentials are invalid. Please reconfigure in Integrations.'], 400);
        }

        $amount = (float) $request->input('amount');
        $amountCents = (int) round($amount * 100);
        if ($amountCents < 1) {
            return response()->json(['success' => false, 'message' => 'Amount must be at least $0.01.'], 422);
        }

        $invoiceNumber = $request->input('invoice_number', 'Invoice');
        $currency = strtolower($request->input('currency', 'usd'));

        try {
            \Stripe\Stripe::setApiKey($secretKey);

            $invoiceId = $request->input('invoice_id');
            if ($invoiceId) {
                $inv = Invoice::where('company_id', $companyId)->find($invoiceId);
                if ($inv) {
                    $url = $this->generatePaymentLinkForInvoice($inv, $currency);

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'url' => $url,
                        ],
                    ]);
                }
            }

            $paymentLink = \Stripe\PaymentLink::create([
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => $currency,
                            'unit_amount' => $amountCents,
                            'product_data' => [
                                'name' => $invoiceNumber,
                                'description' => 'Payment for '.$invoiceNumber,
                            ],
                        ],
                        'quantity' => 1,
                    ],
                ],
                'restrictions' => [
                    'completed_sessions' => [
                        'limit' => 1,
                    ],
                ],
                'inactive_message' => 'This payment link has already been used.',
                'metadata' => ['invoice_number' => $invoiceNumber],
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $paymentLink->url,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            \Illuminate\Support\Facades\Log::error('Stripe Payment Link creation failed', [
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Stripe error: '.$e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Stripe Payment Link creation failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment link: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get or update the company's default Wise payment link used to pre-fill new invoices.
     */
    public function wiseDefaultLink(Request $request): JsonResponse
    {
        $user = Auth::user();
        $company = $user?->company_id ? Company::find($user->company_id) : null;

        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 400);
        }

        if ($request->isMethod('get')) {
            return response()->json([
                'success' => true,
                'data' => ['wise_payment_url' => $company->default_wise_payment_url],
            ]);
        }

        $validated = $request->validate([
            'wise_payment_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $company->update(['default_wise_payment_url' => $validated['wise_payment_url'] ?? null]);

        return response()->json([
            'success' => true,
            'message' => 'Default Wise payment link saved.',
            'data' => ['wise_payment_url' => $company->default_wise_payment_url],
        ]);
    }

    /**
     * Report whether Wise auto-reconciliation (incoming-payment webhook) is active.
     */
    public function wiseWebhookStatus(): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user?->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 400);
        }

        $integration = \App\Models\WiseIntegration::where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        if (! $integration) {
            return response()->json([
                'success' => true,
                'data' => ['wise_configured' => false, 'webhook_active' => false],
            ]);
        }

        $service = new \App\Services\WiseService($companyId);
        $callbackUrl = $this->wiseCallbackUrl($companyId);
        $active = false;
        $result = $service->listWebhookSubscriptions();
        if ($result['success'] ?? false) {
            foreach ($result['subscriptions'] ?? [] as $sub) {
                $url = $sub['delivery']['url'] ?? ($sub['url'] ?? '');
                if ($url === $callbackUrl) {
                    $active = true;
                    break;
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'wise_configured' => true,
                'webhook_active' => $active,
                'callback_url' => $callbackUrl,
            ],
        ]);
    }

    /**
     * Enable Wise auto-reconciliation by creating the incoming-payment webhook subscription.
     */
    public function enableWiseWebhook(): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user?->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 400);
        }

        $service = new \App\Services\WiseService($companyId);
        if (! $service->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Connect Wise (API token & profile) in Integrations first.',
            ], 400);
        }

        $callbackUrl = $this->wiseCallbackUrl($companyId);
        if (! str_starts_with($callbackUrl, 'https://')) {
            return response()->json([
                'success' => false,
                'message' => 'Wise requires an HTTPS callback URL. Your app must be served over HTTPS to enable this.',
            ], 422);
        }

        $result = $service->createWebhookSubscription($callbackUrl);
        if (! ($result['success'] ?? false)) {
            return response()->json(['success' => false, 'message' => $result['error'] ?? 'Failed to enable.'], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Auto-reconciliation enabled. Wise will notify us of incoming payments.',
        ]);
    }

    /**
     * Disable Wise auto-reconciliation by removing matching webhook subscriptions.
     */
    public function disableWiseWebhook(): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user?->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 400);
        }

        $service = new \App\Services\WiseService($companyId);
        if (! $service->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'Wise is not configured.'], 400);
        }

        $callbackUrl = $this->wiseCallbackUrl($companyId);
        $list = $service->listWebhookSubscriptions();
        $deleted = 0;
        if ($list['success'] ?? false) {
            foreach ($list['subscriptions'] ?? [] as $sub) {
                $url = $sub['delivery']['url'] ?? ($sub['url'] ?? '');
                if ($url === $callbackUrl && ! empty($sub['id'])) {
                    $service->deleteWebhookSubscription((string) $sub['id']);
                    $deleted++;
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => $deleted > 0 ? 'Auto-reconciliation disabled.' : 'No active subscription found.',
        ]);
    }

    private function wiseCallbackUrl(int $companyId): string
    {
        return route('webhooks.wise', ['company' => $companyId]);
    }

    /**
     * List recent incoming Wise payments (balance credits) for manual reconciliation.
     */
    public function wiseIncomingPayments(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user?->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 400);
        }

        $service = new \App\Services\WiseService($companyId);
        if (! $service->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Connect Wise (API token & profile) in Integrations to view incoming payments.',
            ], 400);
        }

        $days = (int) $request->input('days', 90);
        $result = $service->getIncomingPayments($days);
        if (! ($result['success'] ?? false)) {
            return response()->json(['success' => false, 'message' => $result['error'] ?? 'Failed to load payments.'], 400);
        }

        $matchedIds = Invoice::where('company_id', $companyId)
            ->whereNotNull('wise_transaction_id')
            ->pluck('wise_transaction_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $payments = collect($result['payments'] ?? [])
            ->reject(fn ($p) => in_array((string) ($p['id'] ?? ''), $matchedIds, true))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => ['payments' => $payments],
        ]);
    }

    /**
     * Mark an invoice as paid via Wise (manual reconciliation from the incoming-payments panel).
     */
    public function markWisePaid(Request $request, Invoice $invoice): JsonResponse
    {
        $user = Auth::user();

        if ($invoice->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        if (strtolower($invoice->status ?? '') === 'rejected') {
            return response()->json(['success' => false, 'message' => 'Cannot mark a rejected invoice as paid.'], 422);
        }

        $transactionId = $request->input('wise_transaction_id');

        if (strtolower($invoice->status ?? '') === 'paid') {
            if ($transactionId && ! $invoice->wise_transaction_id) {
                $invoice->update(['wise_transaction_id' => $transactionId]);
            }

            return response()->json(['success' => true, 'message' => 'Invoice is already paid.']);
        }

        $invoice->update([
            'status' => 'paid',
            'wise_paid_at' => now(),
            'wise_transaction_id' => $transactionId,
        ]);

        if ($invoice->quotation_id) {
            $quotation = $invoice->quotation;
            if ($quotation) {
                $previousStatus = $quotation->status;
                $quotation->update(['status' => 'paid']);
                \App\Models\QuotationStatusHistory::create([
                    'quotation_id' => $quotation->id,
                    'user_id' => $user->id,
                    'status' => 'paid',
                    'previous_status' => $previousStatus,
                    'notes' => 'Payment received via Wise',
                ]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Invoice marked as paid.']);
    }

    /**
     * Generate PDF for a specific invoice.
     */
    public function pdf(Invoice $invoice)
    {
        $user = Auth::user();

        if ($invoice->company_id !== $user->company_id) {
            abort(404, 'Invoice not found.');
        }

        $invoice->load(['client', 'company', 'items']);

        $presentation = app(InvoicePdfPresentationService::class)->prepare($invoice);

        $pdf = Pdf::loadView('invoice.pdf', [
            'invoice' => $invoice,
            'presentation' => $presentation,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('enable-local-file-access', true);

        return $pdf->download('invoice-'.$invoice->invoice_number.'.pdf');
    }

    /**
     * Send invoice via email using Gmail integration.
     */
    public function sendEmail(Request $request, Invoice $invoice): JsonResponse
    {
        try {
            $user = Auth::user();

            if ($invoice->company_id !== $user->company_id) {
                return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
            }

            if (in_array(strtolower($invoice->status ?? ''), ['rejected', 'sent'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot send a '.$invoice->status.' invoice.',
                ], 400);
            }

            if ($invoice->email_sent_at !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'This invoice has already been emailed and cannot be sent again.',
                ], 422);
            }

            $validated = $request->validate([
                'email_subject' => ['nullable', 'string', 'max:255'],
                'cutoff_date' => ['nullable', 'string', 'max:100'],
            ]);

            $gmailIntegration = GmailIntegration::where('company_id', $user->company_id)
                ->where('is_active', true)
                ->first();

            if (! $gmailIntegration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gmail integration is not configured. Please configure it in Integrations.',
                ], 400);
            }

            try {
                $appPassword = Crypt::decryptString($gmailIntegration->app_password);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gmail credentials are invalid. Please reconfigure your Gmail app password in Integrations.',
                ], 400);
            }

            $invoice->loadMissing(['client', 'company', 'items']);
            $emailSubject = filled($validated['email_subject'] ?? null)
                ? $validated['email_subject']
                : $this->buildDefaultInvoiceEmailSubject($invoice, $validated['cutoff_date'] ?? null);

            $this->deliverInvoiceEmail($invoice, $gmailIntegration, $appPassword, $emailSubject);

            return response()->json(['success' => true, 'message' => 'Invoice sent successfully!']);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Invoice send email failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send invoice: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send the same invoice to multiple recipients in one request using Gmail integration.
     */
    public function bulkSendEmail(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $validated = $request->validate([
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['integer'],
            'cutoff_date' => ['nullable', 'string', 'max:100'],
        ]);

        $gmailIntegration = GmailIntegration::where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        if (! $gmailIntegration) {
            return response()->json([
                'success' => false,
                'message' => 'Gmail integration is not configured. Please configure it in Integrations.',
            ], 400);
        }

        try {
            $appPassword = Crypt::decryptString($gmailIntegration->app_password);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gmail credentials are invalid. Please reconfigure in Integrations.',
            ], 400);
        }

        $invoices = Invoice::where('company_id', $companyId)
            ->whereIn('id', $validated['invoice_ids'])
            ->with(['client', 'company', 'items'])
            ->get();

        $sent = 0;
        $failures = [];

        foreach ($invoices as $invoice) {
            $label = $invoice->invoice_number;

            if (in_array(strtolower($invoice->status ?? ''), ['rejected', 'sent'], true)) {
                $failures[] = ['invoice' => $label, 'reason' => 'Already '.$invoice->status];

                continue;
            }

            if ($invoice->email_sent_at !== null) {
                $failures[] = ['invoice' => $label, 'reason' => 'Email already sent'];

                continue;
            }

            try {
                $emailSubject = $this->buildDefaultInvoiceEmailSubject(
                    $invoice,
                    $validated['cutoff_date'] ?? null
                );
                $this->deliverInvoiceEmail($invoice, $gmailIntegration, $appPassword, $emailSubject);
                $sent++;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Bulk invoice send failed', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
                $failures[] = ['invoice' => $label, 'reason' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => $sent > 0,
            'message' => $this->buildBulkMessage($sent, count($failures), 'sent'),
            'data' => [
                'sent' => $sent,
                'failed' => count($failures),
                'failures' => $failures,
            ],
        ]);
    }

    /**
     * Generate a Stripe payment link for multiple invoices in one request.
     */
    public function bulkStripePaymentLink(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $validated = $request->validate([
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['integer'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $integration = StripeIntegration::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('secret_key')
            ->where('secret_key', '!=', '')
            ->first();

        if (! $integration) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe integration not configured. Configure it in Integrations.',
            ], 400);
        }

        try {
            $secretKey = Crypt::decryptString($integration->secret_key);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe credentials are invalid. Please reconfigure in Integrations.',
            ], 400);
        }

        $currency = strtolower($validated['currency'] ?? 'usd');

        $invoices = Invoice::where('company_id', $companyId)
            ->whereIn('id', $validated['invoice_ids'])
            ->get();

        \Stripe\Stripe::setApiKey($secretKey);

        $generated = 0;
        $failures = [];
        $links = [];

        foreach ($invoices as $invoice) {
            try {
                $url = $this->generatePaymentLinkForInvoice($invoice, $currency);
                $generated++;
                $links[] = [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'url' => $url,
                ];
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Bulk Stripe payment link failed', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
                $failures[] = ['invoice' => $invoice->invoice_number, 'reason' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => $generated > 0,
            'message' => $this->buildBulkMessage($generated, count($failures), 'generated'),
            'data' => [
                'generated' => $generated,
                'failed' => count($failures),
                'links' => $links,
                'failures' => $failures,
            ],
        ]);
    }

    /**
     * Build the default invoice email subject line.
     */
    private function buildDefaultInvoiceEmailSubject(Invoice $invoice, ?string $cutoffDate = null): string
    {
        $clientName = $invoice->client?->name ?? 'Client';
        $amount = '$'.number_format((float) $invoice->total, 2);

        if (! filled($cutoffDate)) {
            $payrollPeriod = $this->resolvePayrollPeriodDates($invoice);
            if ($payrollPeriod !== null) {
                $cutoffDate = $this->formatCutoffDateRange($payrollPeriod['start'], $payrollPeriod['end']);
            } else {
                $cutoffDate = $this->formatCutoffDateRange(
                    $invoice->invoice_date?->format('Y-m-d'),
                    $invoice->due_date?->format('Y-m-d')
                );
            }
        }

        $cutoffPart = filled($cutoffDate) ? " Cutoff {$cutoffDate}" : '';

        return "Itsworkplace: {$clientName}{$cutoffPart} / Invoice #{$invoice->invoice_number} ({$amount})";
    }

    /**
     * Format a date range for the email subject cutoff (e.g. "June 22-29").
     */
    private function formatCutoffDateRange(?string $startRaw, ?string $endRaw): string
    {
        if (! filled($startRaw) || ! filled($endRaw)) {
            return '';
        }

        $start = Carbon::parse($startRaw);
        $end = Carbon::parse($endRaw);

        if ($start->format('F') === $end->format('F')) {
            return $start->format('F').' '.$start->format('j').'-'.$end->format('j');
        }

        return $start->format('F j').'-'.$end->format('F j');
    }

    /**
     * @return array<int, array{start: string, end: string}>
     */
    private function payrollPeriodDatesByInvoiceIds(int $companyId, array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        $map = [];
        $invoiceIdSet = array_flip(array_map('intval', $invoiceIds));

        $periodInvoices = PayrollPeriodInvoice::query()
            ->where('company_id', $companyId)
            ->get(['period_start_date', 'period_end_date', 'invoice_ids']);

        foreach ($periodInvoices as $period) {
            $start = $period->period_start_date?->format('Y-m-d');
            $end = $period->period_end_date?->format('Y-m-d');

            if ($start === null || $end === null) {
                continue;
            }

            foreach ((array) ($period->invoice_ids ?? []) as $invoiceId) {
                $invoiceId = (int) $invoiceId;
                if (isset($invoiceIdSet[$invoiceId]) && ! isset($map[$invoiceId])) {
                    $map[$invoiceId] = ['start' => $start, 'end' => $end];
                }
            }
        }

        return $map;
    }

    /**
     * @return array{start: string, end: string}|null
     */
    private function resolvePayrollPeriodDates(Invoice $invoice, ?array $preloadedMap = null): ?array
    {
        if ($preloadedMap !== null && isset($preloadedMap[$invoice->id])) {
            return $preloadedMap[$invoice->id];
        }

        $fromNotes = $this->parsePayrollPeriodFromNotes($invoice->notes);
        if ($fromNotes !== null) {
            return $fromNotes;
        }

        $period = PayrollPeriodInvoice::query()
            ->where('company_id', $invoice->company_id)
            ->whereJsonContains('invoice_ids', $invoice->id)
            ->first(['period_start_date', 'period_end_date']);

        if ($period?->period_start_date && $period->period_end_date) {
            return [
                'start' => $period->period_start_date->format('Y-m-d'),
                'end' => $period->period_end_date->format('Y-m-d'),
            ];
        }

        return null;
    }

    /**
     * @return array{start: string, end: string}|null
     */
    private function parsePayrollPeriodFromNotes(?string $notes): ?array
    {
        if ($notes && preg_match('/Generated from payroll report (\d{4}-\d{2}-\d{2}) to (\d{4}-\d{2}-\d{2})/', $notes, $matches)) {
            return ['start' => $matches[1], 'end' => $matches[2]];
        }

        return null;
    }

    /**
     * Build and send the invoice email through the configured Gmail SMTP account.
     */
    private function deliverInvoiceEmail(
        Invoice $invoice,
        GmailIntegration $gmailIntegration,
        string $appPassword,
        ?string $emailSubject = null
    ): void {
        if ($invoice->email_sent_at !== null) {
            throw new \RuntimeException('Already emailed');
        }

        $invoice->loadMissing(['client', 'company', 'items']);

        if (! $invoice->client || ! $invoice->client->email) {
            throw new \RuntimeException('Client email address is not available.');
        }

        $presentation = app(InvoicePdfPresentationService::class)->prepare($invoice);

        $pdf = Pdf::loadView('invoice.pdf', [
            'invoice' => $invoice,
            'presentation' => $presentation,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('enable-local-file-access', true);
        $pdfContent = $pdf->output();
        $filename = 'invoice-'.$invoice->invoice_number.'.pdf';

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.gmail.com');
        Config::set('mail.mailers.smtp.port', 587);
        Config::set('mail.mailers.smtp.encryption', 'tls');
        Config::set('mail.mailers.smtp.username', $gmailIntegration->email);
        Config::set('mail.mailers.smtp.password', $appPassword);
        Config::set('mail.from.address', $gmailIntegration->email);
        Config::set('mail.from.name', $invoice->company?->name ?? 'Company');

        $emailHtml = view('emails.invoice', [
            'invoice' => $invoice,
            'client' => $invoice->client,
            'company' => $invoice->company,
        ])->render();

        $subject = filled($emailSubject)
            ? $emailSubject
            : $this->buildDefaultInvoiceEmailSubject($invoice);

        Mail::html($emailHtml, function ($message) use ($invoice, $gmailIntegration, $pdfContent, $filename, $subject) {
            $message->from($gmailIntegration->email, $invoice->company?->name ?? 'Company')
                ->to($invoice->client->email, $invoice->client->name)
                ->subject($subject)
                ->attachData($pdfContent, $filename, ['mime' => 'application/pdf']);
        });

        $invoice->update([
            'email_sent_at' => now(),
            'status' => $invoice->status === 'draft' ? 'sent' : $invoice->status,
        ]);
    }

    /**
     * Create a single-use Stripe payment link for an existing invoice and persist the URL.
     */
    private function generatePaymentLinkForInvoice(Invoice $invoice, string $currency = 'usd'): string
    {
        if (strtolower($invoice->status ?? '') === 'rejected') {
            throw new \RuntimeException('Cannot generate payment link for a rejected invoice.');
        }

        if ($invoice->stripe_link_generated_at !== null || ! empty($invoice->stripe_payment_url)) {
            throw new \RuntimeException('A payment link has already been generated for this invoice.');
        }

        $amountCents = (int) round(((float) $invoice->total) * 100);
        if ($amountCents < 1) {
            throw new \RuntimeException('Amount must be at least $0.01.');
        }

        $paymentLink = \Stripe\PaymentLink::create([
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $amountCents,
                        'product_data' => [
                            'name' => $invoice->invoice_number,
                            'description' => 'Payment for '.$invoice->invoice_number,
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
            'restrictions' => [
                'completed_sessions' => [
                    'limit' => 1,
                ],
            ],
            'inactive_message' => 'This payment link has already been used.',
            'metadata' => [
                'invoice_number' => $invoice->invoice_number,
                'invoice_id' => (string) $invoice->id,
            ],
        ]);

        $invoice->update([
            'stripe_payment_url' => $paymentLink->url,
            'stripe_payment_link_id' => $paymentLink->id,
            'stripe_link_generated_at' => now(),
        ]);

        return $paymentLink->url;
    }

    /**
     * Build a human-friendly summary message for a bulk operation.
     */
    private function buildBulkMessage(int $succeeded, int $failed, string $verb): string
    {
        if ($succeeded > 0 && $failed === 0) {
            return $succeeded.' invoice'.($succeeded === 1 ? '' : 's').' '.$verb.' successfully.';
        }

        if ($succeeded > 0 && $failed > 0) {
            return $succeeded.' '.$verb.', '.$failed.' failed.';
        }

        return 'No invoices were '.$verb.'. '.$failed.' failed.';
    }

    /**
     * Store a new invoice.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $validated = $request->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'status' => ['nullable', Rule::in(['draft', 'sent', 'paid', 'overdue'])],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'stripe_payment_url' => ['nullable', 'string'],
            'wise_payment_url' => ['nullable', 'url', 'max:2048'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.hours_worked' => ['nullable', 'numeric', 'min:0'],
            'items.*.net_pay' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            DB::beginTransaction();

            $subtotal = 0;
            foreach ($validated['items'] as $item) {
                $quantity = (float) ($item['quantity'] ?? 1);
                $subtotal += $quantity * $item['unit_price'];
            }

            $taxRate = (float) ($validated['tax_rate'] ?? 0);
            $taxAmount = $subtotal * ($taxRate / 100);
            $total = $subtotal + $taxAmount;

            $invoice = Invoice::create([
                'company_id' => $companyId,
                'client_id' => $validated['client_id'],
                'user_id' => $user->id,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'status' => $validated['status'] ?? 'draft',
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'notes' => $validated['notes'] ?? null,
                'stripe_payment_url' => $validated['stripe_payment_url'] ?? null,
                'wise_payment_url' => $validated['wise_payment_url'] ?? null,
            ]);

            foreach ($validated['items'] as $index => $item) {
                $quantity = (float) ($item['quantity'] ?? 1);
                $itemTotal = $quantity * $item['unit_price'];
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $item['description'] ?? null,
                    'hours_worked' => $this->normalizeHoursWorked($item['hours_worked'] ?? null),
                    'net_pay' => $this->normalizeNetPay($item['net_pay'] ?? null),
                    'quantity' => $quantity,
                    'unit_price' => $item['unit_price'],
                    'total' => $itemTotal,
                    'sort_order' => $index,
                ]);
            }

            app(InvoiceItemHoursSyncService::class)->syncFromInvoice($invoice->fresh(['items']));

            DB::commit();

            if (empty($validated['stripe_payment_url'])) {
                app(\App\Services\StripePaymentLinkService::class)->generateForInvoice($invoice);
            }

            $invoice->load(['client', 'items']);

            return response()->json([
                'success' => true,
                'message' => 'Invoice created successfully.',
                'data' => $this->formatInvoice($invoice),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create invoice: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single invoice.
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $user = Auth::user();

        if ($invoice->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        $invoice->load(['client', 'items']);

        return response()->json([
            'success' => true,
            'data' => $this->formatInvoiceFull($invoice),
        ]);
    }

    /**
     * Update an invoice.
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        $user = Auth::user();

        if ($invoice->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        if (strtolower($invoice->status ?? '') === 'paid') {
            return response()->json(['success' => false, 'message' => 'Cannot update a paid invoice.'], 422);
        }

        if (strtolower($invoice->status ?? '') === 'rejected') {
            return response()->json(['success' => false, 'message' => 'Cannot update a rejected invoice.'], 422);
        }

        $isSent = strtolower($invoice->status ?? '') === 'sent';

        $itemsRules = $isSent
            ? [
                'items' => ['sometimes', 'array'],
                'items.*.id' => ['required', 'integer'],
                'items.*.hours_worked' => ['nullable', 'numeric', 'min:0'],
                'items.*.net_pay' => ['nullable', 'numeric', 'min:0'],
            ]
            : [
                'items' => ['required', 'array', 'min:1'],
                'items.*.description' => ['nullable', 'string'],
                'items.*.hours_worked' => ['nullable', 'numeric', 'min:0'],
                'items.*.net_pay' => ['nullable', 'numeric', 'min:0'],
                'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
                'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            ];

        $validated = $request->validate(array_merge([
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $user->company_id)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'status' => ['required', Rule::in(['draft', 'sent', 'paid', 'overdue'])],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'stripe_payment_url' => ['nullable', 'string'],
            'wise_payment_url' => ['nullable', 'url', 'max:2048'],
        ], $itemsRules));

        try {
            DB::beginTransaction();

            if ($isSent) {
                // Keep existing items and totals — line items are locked for sent invoices
                $subtotal = (float) $invoice->subtotal;
                $taxRate = (float) $invoice->tax_rate;
                $taxAmount = (float) $invoice->tax_amount;
                $total = (float) $invoice->total;
            } else {
                $subtotal = 0;
                foreach ($validated['items'] as $item) {
                    $quantity = (float) ($item['quantity'] ?? 1);
                    $subtotal += $quantity * $item['unit_price'];
                }
                $taxRate = (float) ($validated['tax_rate'] ?? 0);
                $taxAmount = $subtotal * ($taxRate / 100);
                $total = $subtotal + $taxAmount;
            }

            $invoice->update([
                'client_id' => $validated['client_id'],
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'status' => $validated['status'],
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'notes' => $validated['notes'] ?? null,
                'stripe_payment_url' => $validated['stripe_payment_url'] ?? $invoice->stripe_payment_url,
                'wise_payment_url' => array_key_exists('wise_payment_url', $validated) ? $validated['wise_payment_url'] : $invoice->wise_payment_url,
            ]);

            if ($isSent) {
                if (isset($validated['items'])) {
                    foreach ($validated['items'] as $itemData) {
                        $lineItem = $invoice->items()->where('id', $itemData['id'])->first();
                        if (! $lineItem) {
                            continue;
                        }

                        $lineItem->update([
                            'hours_worked' => $this->normalizeHoursWorked($itemData['hours_worked'] ?? null),
                            'net_pay' => $this->normalizeNetPay($itemData['net_pay'] ?? null),
                        ]);
                    }

                    app(InvoiceItemHoursSyncService::class)->syncFromInvoice($invoice->fresh(['items']));
                }
            } else {
                $invoice->items()->delete();

                foreach ($validated['items'] as $index => $item) {
                    $quantity = (float) ($item['quantity'] ?? 1);
                    $itemTotal = $quantity * $item['unit_price'];
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'description' => $item['description'] ?? null,
                        'hours_worked' => $this->normalizeHoursWorked($item['hours_worked'] ?? null),
                        'net_pay' => $this->normalizeNetPay($item['net_pay'] ?? null),
                        'quantity' => $quantity,
                        'unit_price' => $item['unit_price'],
                        'total' => $itemTotal,
                        'sort_order' => $index,
                    ]);
                }

                app(InvoiceItemHoursSyncService::class)->syncFromInvoice($invoice->fresh(['items']));
            }

            DB::commit();

            // When invoice is marked paid, update linked quotation to paid and record in status history
            if ($validated['status'] === 'paid' && $invoice->quotation_id) {
                $quotation = $invoice->quotation;
                if ($quotation) {
                    $previousStatus = $quotation->status;
                    $quotation->update(['status' => 'paid']);
                    \App\Models\QuotationStatusHistory::create([
                        'quotation_id' => $quotation->id,
                        'user_id' => $user->id,
                        'status' => 'paid',
                        'previous_status' => $previousStatus,
                        'notes' => 'Invoice marked as paid',
                    ]);
                }
            }

            $invoice->load(['client', 'items']);

            return response()->json([
                'success' => true,
                'message' => 'Invoice updated successfully.',
                'data' => $this->formatInvoiceFull($invoice),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update invoice: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an invoice.
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        $user = Auth::user();

        if ($invoice->company_id !== $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
        }

        if (strtolower($invoice->status ?? '') === 'paid') {
            return response()->json(['success' => false, 'message' => 'Cannot delete a paid invoice.'], 422);
        }

        if (strtolower($invoice->status ?? '') === 'rejected') {
            return response()->json(['success' => false, 'message' => 'Cannot delete a rejected invoice.'], 422);
        }

        try {
            DB::beginTransaction();

            app(InvoiceItemHoursSyncService::class)->detachInvoiceFromPayrollPeriod($invoice);
            $invoice->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete invoice: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invoice deleted successfully.',
        ]);
    }

    /**
     * @param  array{start: string, end: string}|null  $payrollPeriod
     */
    private function formatInvoice(Invoice $invoice, ?array $payrollPeriod = null): array
    {
        if ($payrollPeriod === null) {
            $payrollPeriod = $this->resolvePayrollPeriodDates($invoice);
        }

        return [
            'id' => $invoice->id,
            'invoiceNumber' => $invoice->invoice_number,
            'client' => $invoice->client?->name,
            'client_id' => $invoice->client_id,
            'date' => $invoice->invoice_date->format('M d, Y'),
            'date_raw' => $invoice->invoice_date->format('Y-m-d'),
            'dueDate' => $invoice->due_date->format('M d, Y'),
            'due_date_raw' => $invoice->due_date->format('Y-m-d'),
            'payroll_period_start' => $payrollPeriod['start'] ?? null,
            'payroll_period_end' => $payrollPeriod['end'] ?? null,
            'amount' => (float) $invoice->total,
            'status' => $invoice->status,
            'notes' => $invoice->notes,
            'taxRate' => (float) $invoice->tax_rate,
            'stripe_payment_url' => $invoice->stripe_payment_url,
            'wise_payment_url' => $invoice->wise_payment_url,
            'email_sent' => $invoice->email_sent_at !== null,
            'stripe_link_generated' => $invoice->stripe_link_generated_at !== null || ! empty($invoice->stripe_payment_url),
        ];
    }

    private function formatInvoiceFull(Invoice $invoice): array
    {
        $data = $this->formatInvoice($invoice);
        $presentation = app(InvoicePdfPresentationService::class)->prepare($invoice);

        $data['is_payroll_invoice'] = $presentation['is_payroll_invoice'];
        $data['items'] = collect($presentation['lines'])->map(fn (array $line) => [
            'id' => $line['item']->id,
            'description' => $line['item']->description,
            'hours_worked' => $line['item']->hours_worked !== null ? (float) $line['item']->hours_worked : null,
            'net_pay' => $line['net_pay'],
            'commission' => $presentation['is_payroll_invoice'] ? $line['commission'] : null,
            'quantity' => (float) $line['item']->quantity,
            'unit_price' => (float) $line['item']->unit_price,
            'total' => (float) $line['item']->total,
        ])->values()->all();

        return $data;
    }

    private function normalizeHoursWorked(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function normalizeNetPay(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $netPay = round((float) $value, 2);

        return $netPay > 0 ? $netPay : null;
    }

    /**
     * Get Payment Dashboard data from Stripe (revenue, payment methods, invoice status breakdown, recent activity).
     */
    public function stripeDashboard(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user?->company_id;
        if (! $companyId) {
            return response()->json(['success' => true, 'data' => $this->emptyDashboardData()]);
        }

        $integration = StripeIntegration::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('secret_key')
            ->where('secret_key', '!=', '')
            ->first();

        if (! $integration) {
            return response()->json([
                'success' => true,
                'data' => $this->emptyDashboardData(),
                'message' => 'Stripe integration not configured. Connect Stripe in Integrations to see payment data.',
            ]);
        }

        try {
            $secretKey = Crypt::decryptString($integration->secret_key);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => $this->emptyDashboardData(),
                'message' => 'Stripe credentials are invalid. Reconfigure in Integrations.',
            ]);
        }

        $period = $request->input('period', 'this-month');
        [$start, $end, $periodLabel] = $this->parseDashboardPeriod($period);

        try {
            \Stripe\Stripe::setApiKey($secretKey);

            $created = ['gte' => $start, 'lte' => $end];

            $revenueChart = [];
            $paymentMethodTotals = [];
            $paidAmount = 0;
            $paidCount = 0;
            $pendingAmount = 0;
            $pendingCount = 0;
            $overdueAmount = 0;
            $overdueCount = 0;
            $recentActivity = [];

            $hasMore = true;
            $after = null;
            $allCharges = [];
            while ($hasMore) {
                $params = [
                    'limit' => 100,
                    'created' => $created,
                    'expand' => ['data.invoice.lines.data', 'data.payment_intent'],
                ];
                if ($after) {
                    $params['starting_after'] = $after;
                }
                $charges = \Stripe\Charge::all($params);
                foreach ($charges->data as $ch) {
                    if (($ch->status ?? '') === 'succeeded' && ($ch->refunded ?? false) === false) {
                        $allCharges[] = $ch;
                        $amt = ($ch->amount ?? 0) / 100;
                        $pmDetails = $ch->payment_method_details ?? null;
                        $pmType = $pmDetails->type ?? 'other';
                        $paymentMethodTotals[$pmType] = ($paymentMethodTotals[$pmType] ?? 0) + $amt;

                        $ts = $ch->created ?? 0;
                        $day = date('Y-m-d', $ts);
                        $revenueChart[$day] = ($revenueChart[$day] ?? 0) + $amt;
                    }
                }
                $hasMore = $charges->has_more ?? false;
                if ($hasMore && count($charges->data) > 0) {
                    $after = $charges->data[array_key_last($charges->data)]->id;
                } else {
                    $hasMore = false;
                }
            }

            $invParams = ['limit' => 100, 'created' => $created];
            $invAfter = null;
            do {
                if ($invAfter) {
                    $invParams['starting_after'] = $invAfter;
                }
                $invoices = \Stripe\Invoice::all($invParams);
                foreach ($invoices->data as $inv) {
                    $amt = ($inv->amount_paid ?? 0) / 100;
                    $totalAmt = ($inv->total ?? 0) / 100;
                    $status = $inv->status ?? '';
                    $dueDate = $inv->due_date ?? null;
                    $isOverdue = $dueDate && $dueDate < time();
                    if ($status === 'paid') {
                        $paidAmount += $amt;
                        $paidCount++;
                    } elseif ($status === 'open' && $isOverdue) {
                        $overdueAmount += $totalAmt;
                        $overdueCount++;
                    } elseif (in_array($status, ['open', 'draft'], true)) {
                        $pendingAmount += $totalAmt;
                        $pendingCount++;
                    } elseif ($status === 'uncollectible') {
                        $overdueAmount += $totalAmt;
                        $overdueCount++;
                    }
                }
                $invHasMore = $invoices->has_more ?? false;
                if ($invHasMore && count($invoices->data) > 0) {
                    $invAfter = $invoices->data[array_key_last($invoices->data)]->id;
                } else {
                    $invHasMore = false;
                }
            } while ($invHasMore);

            usort($allCharges, fn ($a, $b) => ($b->created ?? 0) - ($a->created ?? 0));
            foreach (array_slice($allCharges, 0, 15) as $ch) {
                $productName = $this->getProductNameFromCharge($ch);
                $description = $ch->description ?? ($ch->billing_details->name ?? 'Payment received');
                $recentActivity[] = [
                    'id' => $ch->id,
                    'type' => 'payment',
                    'amount' => ($ch->amount ?? 0) / 100,
                    'currency' => strtoupper($ch->currency ?? 'usd'),
                    'description' => $description,
                    'product_name' => $productName,
                    'created' => $ch->created,
                    'created_human' => $this->timeAgo($ch->created ?? 0),
                ];
            }

            $balanceTx = \Stripe\BalanceTransaction::all(['limit' => 20, 'type' => 'charge']);
            if (empty($recentActivity) && count($balanceTx->data) > 0) {
                foreach (array_slice($balanceTx->data, 0, 15) as $bt) {
                    $recentActivity[] = [
                        'id' => $bt->id,
                        'type' => 'payment',
                        'amount' => ($bt->amount ?? 0) / 100,
                        'currency' => strtoupper($bt->currency ?? 'usd'),
                        'description' => $bt->description ?? 'Payment',
                        'product_name' => null,
                        'created' => $bt->created ?? 0,
                        'created_human' => $this->timeAgo($bt->created ?? 0),
                    ];
                }
            }

            $totalRevenue = array_sum($revenueChart);
            ksort($revenueChart);
            $chartBars = array_values($revenueChart);
            $maxBar = max($chartBars ?: [1]);

            $pmLabels = [
                'card' => 'Credit/Debit Card',
                'us_bank_account' => 'Bank Transfer',
                'sepa_debit' => 'SEPA Direct Debit',
                'other' => 'Other',
            ];
            $paymentMethods = [];
            $pmTotal = array_sum($paymentMethodTotals);
            foreach ($paymentMethodTotals as $type => $amt) {
                $paymentMethods[] = [
                    'name' => $pmLabels[$type] ?? ucfirst(str_replace('_', ' ', $type)),
                    'amount' => round($amt, 2),
                    'percentage' => $pmTotal > 0 ? round(100 * $amt / $pmTotal, 1) : 0,
                ];
            }
            usort($paymentMethods, fn ($a, $b) => $b['amount'] <=> $a['amount']);

            $totalAll = $paidAmount + $pendingAmount + $overdueAmount ?: 1;
            $paidPct = round(100 * $paidAmount / $totalAll);
            $pendingPct = round(100 * $pendingAmount / $totalAll);
            $overduePct = round(100 * $overdueAmount / $totalAll);

            $pendingPaymentLinksCount = 0;
            $paidPaymentLinksCount = 0;
            $pendingPaymentLinksAmount = 0;
            $paidPaymentLinksAmount = 0;
            try {
                $stripe = new \Stripe\StripeClient($secretKey);
                $plList = $stripe->paymentLinks->all(['limit' => 100]);
                foreach ($plList->data ?? [] as $pl) {
                    $amount = 0;
                    try {
                        $lineItems = $stripe->paymentLinks->allLineItems($pl->id, ['limit' => 10]);
                        foreach ($lineItems->data ?? [] as $li) {
                            $amt = (int) ($li->amount_total ?? $li->amount_subtotal ?? 0);
                            $amount += $amt / 100;
                        }
                    } catch (\Exception $lineEx) {
                        \Illuminate\Support\Facades\Log::debug('Payment link line items fetch failed for '.$pl->id, ['error' => $lineEx->getMessage()]);
                    }
                    if ($pl->active ?? true) {
                        $pendingPaymentLinksCount++;
                        $pendingPaymentLinksAmount += $amount;
                    } else {
                        $paidPaymentLinksCount++;
                        $paidPaymentLinksAmount += $amount;
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to fetch Stripe payment links for dashboard', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $period,
                    'period_label' => $periodLabel,
                    'revenue_total' => round($totalRevenue, 2),
                    'revenue_chart' => $chartBars,
                    'chart_max' => $maxBar,
                    'payment_methods' => $paymentMethods,
                    'paid_amount' => round($paidAmount, 2),
                    'paid_count' => $paidCount,
                    'paid_percentage' => $paidPct,
                    'pending_amount' => round($pendingAmount, 2),
                    'pending_count' => $pendingCount,
                    'pending_percentage' => $pendingPct,
                    'overdue_amount' => round($overdueAmount, 2),
                    'overdue_count' => $overdueCount,
                    'overdue_percentage' => $overduePct,
                    'pending_payment_links_count' => $pendingPaymentLinksCount,
                    'pending_payment_links_amount' => round($pendingPaymentLinksAmount, 2),
                    'paid_payment_links_count' => $paidPaymentLinksCount,
                    'paid_payment_links_amount' => round($paidPaymentLinksAmount, 2),
                    'recent_activity' => $recentActivity,
                ],
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            \Illuminate\Support\Facades\Log::error('Stripe dashboard failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Stripe error: '.$e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get Payment Dashboard data for Wise, derived from local invoices (paid via Wise + outstanding Wise links).
     */
    public function wiseDashboard(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user?->company_id;
        if (! $companyId) {
            return response()->json(['success' => true, 'data' => $this->emptyDashboardData()]);
        }

        $period = $request->input('period', 'this-month');
        [$start, $end, $periodLabel] = $this->parseDashboardPeriod($period);
        $startDateTime = date('Y-m-d H:i:s', $start);
        $endDateTime = date('Y-m-d H:i:s', $end);
        $startDate = date('Y-m-d', $start);
        $endDate = date('Y-m-d', $end);
        $today = now()->startOfDay();

        $revenueChart = [];
        $paidAmount = 0;
        $paidCount = 0;
        $paidInvoices = Invoice::where('company_id', $companyId)
            ->whereNotNull('wise_paid_at')
            ->whereBetween('wise_paid_at', [$startDateTime, $endDateTime])
            ->get();
        foreach ($paidInvoices as $inv) {
            $amt = (float) $inv->total;
            $paidAmount += $amt;
            $paidCount++;
            $day = $inv->wise_paid_at->format('Y-m-d');
            $revenueChart[$day] = ($revenueChart[$day] ?? 0) + $amt;
        }

        $pendingAmount = 0;
        $pendingCount = 0;
        $overdueAmount = 0;
        $overdueCount = 0;
        $pendingLinksCount = 0;
        $pendingLinksAmount = 0;
        $paidLinksCount = 0;
        $paidLinksAmount = 0;

        $wiseLinkInvoices = Invoice::where('company_id', $companyId)
            ->whereNotNull('wise_payment_url')
            ->where('wise_payment_url', '!=', '')
            ->whereBetween('invoice_date', [$startDate, $endDate])
            ->get();
        foreach ($wiseLinkInvoices as $inv) {
            $amt = (float) $inv->total;
            $status = strtolower($inv->status ?? '');
            if ($status === 'paid') {
                $paidLinksCount++;
                $paidLinksAmount += $amt;
            } elseif ($status !== 'rejected') {
                $pendingLinksCount++;
                $pendingLinksAmount += $amt;
                $isOverdue = $inv->due_date && \Carbon\Carbon::parse($inv->due_date)->lt($today);
                if ($isOverdue) {
                    $overdueAmount += $amt;
                    $overdueCount++;
                } else {
                    $pendingAmount += $amt;
                    $pendingCount++;
                }
            }
        }

        $recentActivity = [];
        $recent = Invoice::where('company_id', $companyId)
            ->whereNotNull('wise_paid_at')
            ->with('client:id,name')
            ->orderByDesc('wise_paid_at')
            ->limit(15)
            ->get();
        foreach ($recent as $inv) {
            $ts = $inv->wise_paid_at->timestamp;
            $recentActivity[] = [
                'id' => $inv->id,
                'type' => 'payment',
                'amount' => (float) $inv->total,
                'currency' => 'USD',
                'description' => 'Invoice '.$inv->invoice_number.' paid',
                'product_name' => optional($inv->client)->name,
                'created' => $ts,
                'created_human' => $this->timeAgo($ts),
            ];
        }

        $totalRevenue = array_sum($revenueChart);
        ksort($revenueChart);
        $chartBars = array_values($revenueChart);
        $maxBar = max($chartBars ?: [1]);

        $totalAll = ($paidAmount + $pendingAmount + $overdueAmount) ?: 1;

        $paymentMethods = $paidAmount > 0
            ? [['name' => 'Wise Transfer', 'amount' => round($paidAmount, 2), 'percentage' => 100.0]]
            : [];

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'period_label' => $periodLabel,
                'revenue_total' => round($totalRevenue, 2),
                'revenue_chart' => $chartBars,
                'chart_max' => $maxBar,
                'payment_methods' => $paymentMethods,
                'paid_amount' => round($paidAmount, 2),
                'paid_count' => $paidCount,
                'paid_percentage' => (int) round(100 * $paidAmount / $totalAll),
                'pending_amount' => round($pendingAmount, 2),
                'pending_count' => $pendingCount,
                'pending_percentage' => (int) round(100 * $pendingAmount / $totalAll),
                'overdue_amount' => round($overdueAmount, 2),
                'overdue_count' => $overdueCount,
                'overdue_percentage' => (int) round(100 * $overdueAmount / $totalAll),
                'pending_payment_links_count' => $pendingLinksCount,
                'pending_payment_links_amount' => round($pendingLinksAmount, 2),
                'paid_payment_links_count' => $paidLinksCount,
                'paid_payment_links_amount' => round($paidLinksAmount, 2),
                'recent_activity' => $recentActivity,
            ],
        ]);
    }

    private function emptyDashboardData(): array
    {
        return [
            'period' => 'this-month',
            'period_label' => date('F Y'),
            'revenue_total' => 0,
            'revenue_chart' => [],
            'chart_max' => 1,
            'payment_methods' => [],
            'paid_amount' => 0,
            'paid_count' => 0,
            'paid_percentage' => 0,
            'pending_amount' => 0,
            'pending_count' => 0,
            'pending_percentage' => 0,
            'overdue_amount' => 0,
            'overdue_count' => 0,
            'overdue_percentage' => 0,
            'pending_payment_links_count' => 0,
            'pending_payment_links_amount' => 0,
            'paid_payment_links_count' => 0,
            'paid_payment_links_amount' => 0,
            'recent_activity' => [],
        ];
    }

    private function parseDashboardPeriod(string $period): array
    {
        $now = time();
        $start = 0;
        $end = $now;
        $label = date('F Y', $now);

        if ($period === 'this-month') {
            $start = strtotime(date('Y-m-01 00:00:00'));
            $label = date('F Y', $now);
        } elseif ($period === 'last-month') {
            $start = strtotime(date('Y-m-01 00:00:00', strtotime('-1 month')));
            $end = strtotime(date('Y-m-t 23:59:59', strtotime('-1 month')));
            $label = date('F Y', strtotime('-1 month'));
        } elseif ($period === 'this-quarter') {
            $m = (int) date('n');
            $qStartMonth = floor(($m - 1) / 3) * 3 + 1;
            $start = strtotime(date('Y').'-'.str_pad($qStartMonth, 2, '0').'-01 00:00:00');
            $label = 'Q'.ceil($m / 3).' '.date('Y');
        } elseif ($period === 'this-year') {
            $start = strtotime(date('Y').'-01-01 00:00:00');
            $label = date('Y');
        }

        return [$start, $end, $label];
    }

    private function timeAgo(int $timestamp): string
    {
        $diff = time() - $timestamp;
        if ($diff < 3600) {
            return (int) ($diff / 60).' min ago';
        }
        if ($diff < 86400) {
            return (int) ($diff / 3600).' hours ago';
        }
        if ($diff < 604800) {
            return (int) ($diff / 86400).' days ago';
        }

        return date('M j, Y', $timestamp);
    }

    private function getProductNameFromCharge($charge): ?string
    {
        $metadata = $charge->metadata ?? null;
        if ($metadata !== null) {
            $invNum = $metadata['invoice_number'] ?? $metadata->invoice_number ?? null;
            if ($invNum && trim((string) $invNum) !== '') {
                return trim((string) $invNum);
            }
        }

        $paymentIntent = $charge->payment_intent ?? null;
        if ($paymentIntent && is_object($paymentIntent)) {
            $piMeta = $paymentIntent->metadata ?? null;
            if ($piMeta !== null) {
                $piInvNum = $piMeta['invoice_number'] ?? $piMeta->invoice_number ?? null;
                if ($piInvNum && trim((string) $piInvNum) !== '') {
                    return trim((string) $piInvNum);
                }
            }
        }

        $invoice = $charge->invoice ?? null;
        if (! $invoice || ! is_object($invoice)) {
            return null;
        }
        $linesObj = $invoice->lines ?? null;
        $lines = ($linesObj && isset($linesObj->data)) ? $linesObj->data : [];
        foreach ($lines as $line) {
            $desc = $line->description ?? null;
            if ($desc && trim((string) $desc) !== '') {
                return trim((string) $desc);
            }
        }

        return null;
    }

    private function getCompany(Request $request): ?Company
    {
        $company = $request->get('company');
        if ($company instanceof Company) {
            return $company;
        }
        if (app()->bound('company')) {
            try {
                return app('company');
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }
}
