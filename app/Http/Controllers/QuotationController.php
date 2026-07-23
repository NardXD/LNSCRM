<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuotationRequest;
use App\Http\Requests\UpdateQuotationRequest;
use App\Models\Client;
use App\Models\GmailIntegration;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationStatusHistory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class QuotationController extends Controller
{
    /**
     * Display the quotation builder page.
     */
    public function index()
    {
        return view('dashboard.quotation-builder');
    }

    /**
     * Get all quotations for the authenticated user's company.
     */
    public function getQuotations(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $query = Quotation::where('company_id', $companyId)
            ->with(['client', 'user'])
            ->orderBy('created_at', 'desc');

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('quotation_number', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Status filter
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Month filter
        if ($request->has('month') && $request->month) {
            $query->whereYear('quotation_date', substr($request->month, 0, 4))
                ->whereMonth('quotation_date', substr($request->month, 5, 2));
        } else {
            // Default to current month if no month specified
            $query->whereYear('quotation_date', now()->year)
                ->whereMonth('quotation_date', now()->month);
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $quotations = $query->paginate($perPage);

        $data = $quotations->map(function ($quotation) {
            return [
                'id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'client' => $quotation->client->name,
                'client_id' => $quotation->client_id,
                'date' => $quotation->quotation_date->format('M d, Y'),
                'date_raw' => $quotation->quotation_date->format('Y-m-d'),
                'valid_until' => $quotation->valid_until->format('M d, Y'),
                'valid_until_raw' => $quotation->valid_until->format('Y-m-d'),
                'amount' => (float) $quotation->total,
                'status' => $quotation->status,
                'created_by' => $quotation->user->name,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $quotations->currentPage(),
                'last_page' => $quotations->lastPage(),
                'per_page' => $quotations->perPage(),
                'total' => $quotations->total(),
            ],
        ]);
    }

    /**
     * Get quotation statistics.
     */
    public function getStats(): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $totalQuotations = Quotation::where('company_id', $companyId)->count();
        $pendingQuotations = Quotation::where('company_id', $companyId)
            ->whereIn('status', ['sent', 'draft'])
            ->count();
        $acceptedQuotations = Quotation::where('company_id', $companyId)
            ->where('status', 'accepted')
            ->count();

        $thisMonth = Quotation::where('company_id', $companyId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $lastMonth = Quotation::where('company_id', $companyId)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        $growth = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : ($thisMonth > 0 ? 100 : 0);

        $totalValue = Quotation::where('company_id', $companyId)
            ->sum('total');

        $lastMonthValue = Quotation::where('company_id', $companyId)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('total');

        $valueGrowth = $lastMonthValue > 0 ? round((($totalValue - $lastMonthValue) / $lastMonthValue) * 100, 1) : ($totalValue > 0 ? 100 : 0);

        $acceptanceRate = $totalQuotations > 0 ? round(($acceptedQuotations / $totalQuotations) * 100, 1) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_quotations' => $totalQuotations,
                'pending' => $pendingQuotations,
                'accepted' => $acceptedQuotations,
                'acceptance_rate' => $acceptanceRate,
                'total_value' => (float) $totalValue,
                'new_this_month' => $thisMonth,
                'growth_percentage' => $growth,
                'value_growth_percentage' => $valueGrowth,
            ],
        ]);
    }

    /**
     * Get all clients for dropdown.
     */
    public function getClients(): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $clients = Client::where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    /**
     * Get the next quotation number (preview).
     */
    public function getNextQuotationNumber(): JsonResponse
    {
        $user = Auth::user();
        $company = $user->company;

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        $prefix = $company->quotation_prefix;
        $year = now()->year;
        $lastQuotation = Quotation::where('company_id', $company->id)
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastQuotation) {
            // Extract number from last quotation (format: PREFIX-YYYY-###)
            $parts = explode('-', $lastQuotation->quotation_number);
            $lastNumber = (int) end($parts);
            $nextNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '001';
        }

        $quotationNumber = "{$prefix}-{$year}-{$nextNumber}";

        return response()->json([
            'success' => true,
            'data' => [
                'quotation_number' => $quotationNumber,
            ],
        ]);
    }

    /**
     * Store a newly created quotation.
     */
    public function store(StoreQuotationRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $company = $user->company;

            if (! $company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found.',
                ], 404);
            }

            // Generate quotation number with company prefix
            $prefix = $company->quotation_prefix;
            $year = now()->year;
            $lastQuotation = Quotation::where('company_id', $company->id)
                ->whereYear('created_at', $year)
                ->orderBy('id', 'desc')
                ->first();

            if ($lastQuotation) {
                // Extract number from last quotation (format: PREFIX-YYYY-###)
                $parts = explode('-', $lastQuotation->quotation_number);
                $lastNumber = (int) end($parts);
                $nextNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
            } else {
                $nextNumber = '001';
            }

            $quotationNumber = "{$prefix}-{$year}-{$nextNumber}";

            // Calculate totals
            $subtotal = 0;
            $taxAmount = 0;

            foreach ($request->items as $itemData) {
                $itemSubtotal = $itemData['quantity'] * $itemData['unit_price'];
                $subtotal += $itemSubtotal;
                $itemTax = $itemSubtotal * (($itemData['tax_percentage'] ?? 0) / 100);
                $taxAmount += $itemTax;
            }

            // Calculate discount
            $discountAmount = $request->discount_amount ?? 0;
            if ($request->discount_type === 'percent') {
                $discountAmount = $subtotal * ($discountAmount / 100);
            }

            $total = $subtotal + $taxAmount - $discountAmount;

            // Create quotation
            $quotation = Quotation::create([
                'company_id' => $user->company_id,
                'client_id' => $request->client_id,
                'user_id' => $user->id,
                'quotation_number' => $quotationNumber,
                'quotation_date' => $request->quotation_date,
                'valid_until' => $request->valid_until,
                'status' => $request->status ?? 'draft',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'discount_type' => $request->discount_type,
                'total' => $total,
                'internal_notes' => $request->internal_notes,
                'terms_conditions' => $request->terms_conditions,
                'sent_at' => $request->status === 'sent' ? now() : null,
            ]);

            // Record initial status
            $this->recordStatusChange($quotation, $request->status ?? 'draft', null, $user->id, 'Quotation created');

            // Create quotation items
            foreach ($request->items as $index => $itemData) {
                $itemSubtotal = $itemData['quantity'] * $itemData['unit_price'];
                $itemTax = $itemSubtotal * (($itemData['tax_percentage'] ?? 0) / 100);
                $itemTotal = $itemSubtotal + $itemTax;

                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'item_name' => $itemData['item_name'],
                    'description' => $itemData['description'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'tax_percentage' => $itemData['tax_percentage'] ?? 0,
                    'tax_amount' => $itemTax,
                    'total' => $itemTotal,
                    'sort_order' => $index,
                ]);
            }

            DB::commit();

            $quotation->load(['client', 'user', 'items']);

            return response()->json([
                'success' => true,
                'message' => 'Quotation created successfully.',
                'data' => $this->formatQuotation($quotation),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create quotation: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific quotation.
     */
    public function show(Quotation $quotation): JsonResponse
    {
        $user = Auth::user();

        // Ensure the quotation belongs to the user's company
        if ($quotation->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation not found.',
            ], 404);
        }

        $quotation->load(['client', 'user', 'items']);

        return response()->json([
            'success' => true,
            'data' => $this->formatQuotation($quotation),
        ]);
    }

    /**
     * Generate PDF for a specific quotation.
     */
    public function pdf(Quotation $quotation)
    {
        $user = Auth::user();

        // Ensure the quotation belongs to the user's company
        if ($quotation->company_id !== $user->company_id) {
            abort(404, 'Quotation not found.');
        }

        $quotation->load(['client', 'company', 'items']);

        $pdf = Pdf::loadView('quotation.pdf', ['quotation' => $quotation])
            ->setPaper('a4', 'portrait')
            ->setOption('enable-local-file-access', true);

        return $pdf->stream('quotation-'.$quotation->quotation_number.'.pdf');
    }

    /**
     * Update the specified quotation.
     */
    public function update(UpdateQuotationRequest $request, Quotation $quotation): JsonResponse
    {
        try {
            $user = Auth::user();

            // Ensure the quotation belongs to the user's company
            if ($quotation->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quotation not found.',
                ], 404);
            }

            if (in_array($quotation->status, ['paid', 'accepted', 'rejected'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot edit a '.$quotation->status.' quotation.',
                ], 422);
            }

            DB::beginTransaction();

            // Update quotation fields
            $updateData = [];

            if ($request->has('client_id')) {
                $updateData['client_id'] = $request->client_id;
            }
            if ($request->has('quotation_date')) {
                $updateData['quotation_date'] = $request->quotation_date;
            }
            if ($request->has('valid_until')) {
                $updateData['valid_until'] = $request->valid_until;
            }
            $previousStatus = $quotation->status;
            if ($request->has('status')) {
                $updateData['status'] = $request->status;
                if ($request->status === 'sent' && ! $quotation->sent_at) {
                    $updateData['sent_at'] = now();
                }
            }
            if ($request->has('internal_notes')) {
                $updateData['internal_notes'] = $request->internal_notes;
            }
            if ($request->has('terms_conditions')) {
                $updateData['terms_conditions'] = $request->terms_conditions;
            }

            // Update items if provided
            if ($request->has('items')) {
                // Delete existing items
                $quotation->items()->delete();

                // Recalculate totals
                $subtotal = 0;
                $taxAmount = 0;

                foreach ($request->items as $index => $itemData) {
                    $itemSubtotal = $itemData['quantity'] * $itemData['unit_price'];
                    $subtotal += $itemSubtotal;
                    $itemTax = $itemSubtotal * (($itemData['tax_percentage'] ?? 0) / 100);
                    $taxAmount += $itemTax;

                    QuotationItem::create([
                        'quotation_id' => $quotation->id,
                        'item_name' => $itemData['item_name'],
                        'description' => $itemData['description'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'tax_percentage' => $itemData['tax_percentage'] ?? 0,
                        'tax_amount' => $itemTax,
                        'total' => $itemSubtotal + $itemTax,
                        'sort_order' => $index,
                    ]);
                }

                // Calculate discount
                $discountAmount = $request->discount_amount ?? $quotation->discount_amount;
                $discountType = $request->discount_type ?? $quotation->discount_type;

                if ($discountType === 'percent') {
                    $discountAmount = $subtotal * (($request->discount_amount ?? 0) / 100);
                }

                $total = $subtotal + $taxAmount - $discountAmount;

                $updateData['subtotal'] = $subtotal;
                $updateData['tax_amount'] = $taxAmount;
                $updateData['discount_amount'] = $discountAmount;
                $updateData['discount_type'] = $discountType;
                $updateData['total'] = $total;
            } else {
                // Update discount only if provided
                if ($request->has('discount_amount') || $request->has('discount_type')) {
                    $subtotal = $quotation->subtotal;
                    $discountAmount = $request->discount_amount ?? $quotation->discount_amount;
                    $discountType = $request->discount_type ?? $quotation->discount_type;

                    if ($discountType === 'percent') {
                        $discountAmount = $subtotal * ($discountAmount / 100);
                    }

                    $updateData['discount_amount'] = $discountAmount;
                    $updateData['discount_type'] = $discountType;
                    $updateData['total'] = $subtotal + $quotation->tax_amount - $discountAmount;
                }
            }

            $quotation->update($updateData);

            // Record status change if status was updated
            if (isset($updateData['status']) && $previousStatus !== $updateData['status']) {
                $this->recordStatusChange($quotation, $updateData['status'], $previousStatus, $user->id, 'Quotation updated');
            }

            DB::commit();

            $quotation->load(['client', 'user', 'items']);

            return response()->json([
                'success' => true,
                'message' => 'Quotation updated successfully.',
                'data' => $this->formatQuotation($quotation),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update quotation: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send quotation via email using Gmail integration.
     */
    public function sendEmail(Quotation $quotation): JsonResponse
    {
        try {
            $user = Auth::user();

            // Ensure the quotation belongs to the user's company
            if ($quotation->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quotation not found.',
                ], 404);
            }

            if (in_array($quotation->status, ['paid', 'accepted', 'rejected'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot send a '.$quotation->status.' quotation.',
                ], 400);
            }

            // Get Gmail integration
            $gmailIntegration = GmailIntegration::where('company_id', $user->company_id)
                ->where('is_active', true)
                ->first();

            if (! $gmailIntegration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gmail integration is not configured. Please configure it in Integrations.',
                ], 400);
            }

            // Load quotation with relationships
            $quotation->load(['client', 'company', 'items']);

            if (! $quotation->client || ! $quotation->client->email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client email address is not available.',
                ], 400);
            }

            // Decrypt Gmail app password
            $appPassword = Crypt::decryptString($gmailIntegration->app_password);

            // Generate PDF
            $pdf = Pdf::loadView('quotation.pdf', ['quotation' => $quotation])
                ->setPaper('a4', 'portrait')
                ->setOption('enable-local-file-access', true);

            $pdfContent = $pdf->output();
            $filename = 'quotation-'.$quotation->quotation_number.'.pdf';

            // Configure mail to use Gmail SMTP dynamically
            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.host', 'smtp.gmail.com');
            Config::set('mail.mailers.smtp.port', 587);
            Config::set('mail.mailers.smtp.encryption', 'tls');
            Config::set('mail.mailers.smtp.username', $gmailIntegration->email);
            Config::set('mail.mailers.smtp.password', $appPassword);
            Config::set('mail.from.address', $gmailIntegration->email);
            Config::set('mail.from.name', $quotation->company->name ?? 'Company');

            // Create a simple mailable or send directly
            $emailHtml = view('emails.quotation', [
                'quotation' => $quotation,
                'client' => $quotation->client,
                'company' => $quotation->company,
            ])->render();

            Mail::html($emailHtml, function ($message) use ($quotation, $gmailIntegration, $pdfContent, $filename) {
                $message->from($gmailIntegration->email, $quotation->company->name ?? 'Company')
                    ->to($quotation->client->email, $quotation->client->name)
                    ->subject('Quotation #'.$quotation->quotation_number)
                    ->attachData($pdfContent, $filename, [
                        'mime' => 'application/pdf',
                    ]);
            });

            // Update quotation status to 'sent' if it's currently 'draft'
            $previousStatus = $quotation->status;
            if ($quotation->status === 'draft') {
                $quotation->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
                // Record status change
                $this->recordStatusChange($quotation, 'sent', $previousStatus, $user->id, 'Quotation sent via email');
            } else {
                $quotation->update([
                    'sent_at' => now(),
                ]);
                // Record email sent event
                $this->recordStatusChange($quotation, $quotation->status, $previousStatus, $user->id, 'Quotation sent via email');
            }

            return response()->json([
                'success' => true,
                'message' => 'Quotation sent successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send quotation: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update quotation status.
     */
    public function updateStatus(Request $request, Quotation $quotation): JsonResponse
    {
        $user = Auth::user();

        // Ensure the quotation belongs to the user's company
        if ($quotation->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation not found.',
            ], 404);
        }

        if ($quotation->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change status of a paid quotation.',
            ], 422);
        }

        if ($quotation->status === 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change status of a rejected quotation.',
            ], 422);
        }

        $status = $request->input('status');
        $validStatuses = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

        // Once accepted, cannot revert to draft or sent
        if ($quotation->status === 'accepted' && in_array($status, ['draft', 'sent'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change status from accepted back to draft or sent.',
            ], 422);
        }

        if (! in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status.',
            ], 422);
        }

        $updateData = ['status' => $status];

        // Update sent_at if status is changed to 'sent'
        if ($status === 'sent' && ! $quotation->sent_at) {
            $updateData['sent_at'] = now();
        }

        $previousStatus = $quotation->status;
        $quotation->update($updateData);

        // When quotation is rejected, update linked invoice to rejected and clear payment link
        if ($status === 'rejected') {
            $linkedInvoice = Invoice::where('quotation_id', $quotation->id)->first();
            if ($linkedInvoice) {
                $linkedInvoice->update(['status' => 'rejected', 'stripe_payment_url' => null]);
            }
        }

        // Record status change in history
        $notes = 'Status changed to '.$status;
        $invoice = null;
        if ($status === 'accepted') {
            $invoice = $this->createInvoiceFromQuotation($quotation->fresh(), $user);
            $notes .= $invoice ? '. Invoice '.$invoice->invoice_number.' created.' : '';
        }
        $this->recordStatusChange($quotation, $status, $previousStatus, $user->id, $notes);

        $responseData = [
            'id' => $quotation->id,
            'status' => $quotation->status,
        ];
        if ($invoice) {
            $responseData['invoice_id'] = $invoice->id;
            $responseData['invoice_number'] = $invoice->invoice_number;
        }

        return response()->json([
            'success' => true,
            'message' => 'Quotation status updated successfully.'
                .($invoice ? ' Invoice '.$invoice->invoice_number.' has been created.' : ''),
            'data' => $responseData,
        ]);
    }

    /**
     * Create an invoice from an accepted quotation.
     */
    protected function createInvoiceFromQuotation(Quotation $quotation, $user): ?Invoice
    {
        // Avoid duplicate invoices if status is changed to accepted multiple times
        $existing = Invoice::where('quotation_id', $quotation->id)->first();
        if ($existing) {
            return null;
        }

        $quotation->load(['client', 'items']);

        return DB::transaction(function () use ($quotation, $user) {
            $invoiceDate = $quotation->quotation_date;
            $dueDate = $invoiceDate->copy()->addDays(30);

            $invoice = Invoice::create([
                'company_id' => $quotation->company_id,
                'client_id' => $quotation->client_id,
                'user_id' => $user->id,
                'quotation_id' => $quotation->id,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'invoice_date' => $invoiceDate,
                'due_date' => $dueDate,
                'status' => 'sent',
                'subtotal' => $quotation->subtotal,
                'tax_rate' => $quotation->subtotal > 0
                    ? round(($quotation->tax_amount / (float) $quotation->subtotal) * 100, 2)
                    : 0,
                'tax_amount' => $quotation->tax_amount,
                'total' => $quotation->total,
                'notes' => 'Created from quotation '.$quotation->quotation_number,
            ]);

            foreach ($quotation->items as $index => $quotationItem) {
                $lineTotal = (float) $quotationItem->quantity * (float) $quotationItem->unit_price;
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => $quotationItem->item_name ?: $quotationItem->description,
                    'quantity' => $quotationItem->quantity,
                    'unit_price' => $quotationItem->unit_price,
                    'total' => $lineTotal,
                    'sort_order' => $index,
                ]);
            }

            return $invoice;
        });
    }

    /**
     * Remove the specified quotation.
     */
    public function destroy(Quotation $quotation): JsonResponse
    {
        try {
            $user = Auth::user();

            // Ensure the quotation belongs to the user's company
            if ($quotation->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quotation not found.',
                ], 404);
            }

            if (in_array($quotation->status, ['paid', 'accepted', 'rejected'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete a '.$quotation->status.' quotation.',
                ], 422);
            }

            $quotation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Quotation deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete quotation: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Record status change in history.
     */
    private function recordStatusChange(Quotation $quotation, string $status, ?string $previousStatus, ?int $userId, ?string $notes = null): void
    {
        QuotationStatusHistory::create([
            'quotation_id' => $quotation->id,
            'user_id' => $userId,
            'status' => $status,
            'previous_status' => $previousStatus,
            'notes' => $notes,
        ]);
    }

    /**
     * Get status history for a quotation.
     */
    public function getStatusHistory(Quotation $quotation): JsonResponse
    {
        $user = Auth::user();

        // Ensure the quotation belongs to the user's company
        if ($quotation->company_id !== $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation not found.',
            ], 404);
        }

        $history = QuotationStatusHistory::where('quotation_id', $quotation->id)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'status' => $item->status,
                    'previous_status' => $item->previous_status,
                    'notes' => $item->notes,
                    'changed_by' => $item->user ? $item->user->name : 'System',
                    'changed_at' => $item->created_at->format('Y-m-d H:i:s'),
                    'changed_at_formatted' => $item->created_at->format('M d, Y h:i A'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    /**
     * Format quotation data for response.
     */
    private function formatQuotation(Quotation $quotation): array
    {
        return [
            'id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'client_id' => $quotation->client_id,
            'client' => $quotation->client->name,
            'quotation_date' => $quotation->quotation_date->format('Y-m-d'),
            'valid_until' => $quotation->valid_until->format('Y-m-d'),
            'status' => $quotation->status,
            'subtotal' => (float) $quotation->subtotal,
            'tax_amount' => (float) $quotation->tax_amount,
            'discount_amount' => (float) $quotation->discount_amount,
            'discount_type' => $quotation->discount_type,
            'total' => (float) $quotation->total,
            'internal_notes' => $quotation->internal_notes,
            'terms_conditions' => $quotation->terms_conditions,
            'items' => $quotation->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'tax_percentage' => (float) $item->tax_percentage,
                    'tax_amount' => (float) $item->tax_amount,
                    'total' => (float) $item->total,
                ];
            })->toArray(),
        ];
    }
}
