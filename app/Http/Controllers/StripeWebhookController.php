<?php

namespace App\Http\Controllers;

use App\Models\BillingSubscription;
use App\Models\Invoice;
use App\Models\QuotationStatusHistory;
use App\Models\StripeIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    /**
     * Handle Stripe webhook events (checkout.session.completed).
     * Excluded from CSRF - verify using Stripe signature.
     * Webhook URL format: /webhooks/stripe/company/{company_id}
     */
    public function handleWebhook(Request $request, int $company)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        $integration = StripeIntegration::where('company_id', $company)->first();

        if (! $integration || ! $integration->webhook_secret) {
            Log::warning('Stripe webhook secret not configured for company', ['company_id' => $company]);

            return response()->json(['error' => 'Webhook not configured'], 500);
        }

        try {
            $webhookSecret = Crypt::decryptString($integration->webhook_secret);
        } catch (\Exception $e) {
            Log::warning('Stripe webhook secret decryption failed', ['company_id' => $company]);

            return response()->json(['error' => 'Webhook not configured'], 500);
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook invalid payload', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $invoiceId = $session->metadata->invoice_id ?? ($session->metadata['invoice_id'] ?? null);
            if ($invoiceId) {
                $invoice = Invoice::find($invoiceId);
                if ($invoice) {
                    $invoice->update(['status' => 'paid']);
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
                                'notes' => 'Payment received via Stripe',
                            ]);
                        }
                    }
                    Log::info('Stripe webhook: invoice marked paid', ['invoice_id' => $invoiceId]);
                }
            }
        }

        if ($event->type === 'customer.subscription.updated' || $event->type === 'customer.subscription.deleted') {
            $stripeSubscription = $event->data->object;
            $subscription = BillingSubscription::where('stripe_subscription_id', $stripeSubscription->id)->first();
            if ($subscription && $subscription->company_id === $company) {
                if ($event->type === 'customer.subscription.deleted') {
                    $subscription->update([
                        'status' => 'canceled',
                        'canceled_at' => now(),
                    ]);
                    Log::info('Stripe webhook: subscription canceled', ['billing_subscription_id' => $subscription->id]);
                } else {
                    $subscription->update([
                        'status' => $stripeSubscription->status,
                        'current_period_start' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start),
                        'current_period_end' => \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end),
                        'trial_end' => $stripeSubscription->trial_end
                            ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end)
                            : null,
                    ]);
                    Log::info('Stripe webhook: subscription updated', ['billing_subscription_id' => $subscription->id]);
                }
            }
        }

        return response()->json(['received' => true]);
    }
}
