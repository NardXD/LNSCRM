<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\QuotationStatusHistory;
use App\Models\WiseIntegration;
use App\Services\WiseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WiseWebhookController extends Controller
{
    /**
     * Handle Wise balance webhook events (incoming credits) and reconcile invoices.
     * Excluded from CSRF - authenticity is verified via the X-Signature-SHA256 header.
     * Webhook URL format: /webhooks/wise/company/{company}
     */
    public function handle(Request $request, int $company): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Signature-SHA256', '');

        // Test notifications are sent by Wise to verify the callback URL during setup.
        if ($request->header('X-Test-Notification') === 'true') {
            return response()->json(['status' => 'ok']);
        }

        if (! $signature || ! WiseService::verifyWebhookSignature($rawBody, $signature)) {
            Log::warning('Wise webhook signature verification failed', ['company_id' => $company]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $eventType = $payload['event_type'] ?? '';
        $data = $payload['data'] ?? [];

        if (! in_array($eventType, ['balances#update', 'balances#credit'], true)) {
            return response()->json(['status' => 'ignored']);
        }

        if (strtolower($data['transaction_type'] ?? '') !== 'credit') {
            return response()->json(['status' => 'ignored']);
        }

        $reference = trim((string) ($data['transfer_reference'] ?? ''));
        $amount = (float) ($data['amount'] ?? 0);
        $currency = strtoupper((string) ($data['currency'] ?? ''));
        $profileId = $data['resource']['profile_id'] ?? null;

        if ($reference === '') {
            return response()->json(['status' => 'no_reference']);
        }

        // Confirm the credited profile belongs to this company's Wise integration.
        $integration = WiseIntegration::where('company_id', $company)->where('is_active', true)->first();
        if ($integration && $profileId && (string) $integration->profile_id !== (string) $profileId) {
            Log::warning('Wise webhook profile mismatch', [
                'company_id' => $company,
                'event_profile' => $profileId,
                'integration_profile' => $integration->profile_id,
            ]);

            return response()->json(['status' => 'profile_mismatch']);
        }

        $invoice = $this->matchInvoice($company, $reference);

        if (! $invoice) {
            Log::info('Wise webhook: no matching invoice', ['company_id' => $company, 'reference' => $reference]);

            return response()->json(['status' => 'no_match']);
        }

        if (strtolower($invoice->status ?? '') === 'paid') {
            return response()->json(['status' => 'already_paid']);
        }

        // Guard against partial payments: require the credit to cover (almost) the full total.
        if ($amount > 0 && $amount < ((float) $invoice->total) * 0.99) {
            Log::warning('Wise webhook: amount below invoice total, not reconciled', [
                'invoice_id' => $invoice->id,
                'received' => $amount,
                'currency' => $currency,
                'total' => $invoice->total,
            ]);

            return response()->json(['status' => 'amount_mismatch']);
        }

        $invoice->update([
            'status' => 'paid',
            'wise_paid_at' => now(),
        ]);

        if ($invoice->quotation_id) {
            $quotation = $invoice->quotation;
            if ($quotation) {
                $previousStatus = $quotation->status;
                $quotation->update(['status' => 'paid']);
                QuotationStatusHistory::create([
                    'quotation_id' => $quotation->id,
                    'user_id' => null,
                    'status' => 'paid',
                    'previous_status' => $previousStatus,
                    'notes' => 'Payment received via Wise',
                ]);
            }
        }

        Log::info('Wise webhook: invoice marked paid', [
            'invoice_id' => $invoice->id,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Match an unpaid invoice by payment reference: exact (normalised) first, then contains.
     */
    private function matchInvoice(int $companyId, string $reference): ?Invoice
    {
        $normalized = strtolower(preg_replace('/\s+/', '', $reference));

        $candidates = Invoice::where('company_id', $companyId)
            ->whereNotIn('status', ['paid', 'rejected'])
            ->get();

        foreach ($candidates as $invoice) {
            $invNum = strtolower(preg_replace('/\s+/', '', (string) $invoice->invoice_number));
            if ($invNum !== '' && $invNum === $normalized) {
                return $invoice;
            }
        }

        foreach ($candidates as $invoice) {
            $invNum = strtolower(preg_replace('/\s+/', '', (string) $invoice->invoice_number));
            if ($invNum !== '' && str_contains($normalized, $invNum)) {
                return $invoice;
            }
        }

        return null;
    }
}
