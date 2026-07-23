<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\StripeIntegration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class StripePaymentLinkService
{
    /**
     * Determine whether a company has an active, usable Stripe integration.
     */
    public function isConfigured(int $companyId): bool
    {
        return StripeIntegration::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('secret_key')
            ->where('secret_key', '!=', '')
            ->exists();
    }

    /**
     * Generate a single-use Stripe payment link for an invoice and persist it.
     * Returns the URL on success, or null if Stripe is not configured, the link
     * already exists, or generation fails. Never throws.
     */
    public function generateForInvoice(Invoice $invoice, string $currency = 'usd'): ?string
    {
        if (! $invoice->company_id) {
            return null;
        }

        if (strtolower($invoice->status ?? '') === 'rejected') {
            return null;
        }

        if ($invoice->stripe_link_generated_at !== null || ! empty($invoice->stripe_payment_url)) {
            return $invoice->stripe_payment_url;
        }

        $amountCents = (int) round(((float) $invoice->total) * 100);
        if ($amountCents < 1) {
            return null;
        }

        $integration = StripeIntegration::where('company_id', $invoice->company_id)
            ->where('is_active', true)
            ->whereNotNull('secret_key')
            ->where('secret_key', '!=', '')
            ->first();

        if (! $integration) {
            return null;
        }

        try {
            $secretKey = Crypt::decryptString($integration->secret_key);
        } catch (\Exception $e) {
            Log::warning('Stripe auto-link skipped: invalid credentials', ['company_id' => $invoice->company_id]);

            return null;
        }

        try {
            \Stripe\Stripe::setApiKey($secretKey);

            $paymentLink = \Stripe\PaymentLink::create([
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => strtolower($currency),
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
        } catch (\Exception $e) {
            Log::error('Stripe auto-link generation failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
