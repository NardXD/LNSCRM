<?php

namespace App\Http\Controllers;

use App\Models\BillingSubscription;
use App\Models\Client;
use App\Models\StripeIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class BillingSubscriptionController extends Controller
{
    /**
     * List billing subscriptions for the authenticated company.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = Auth::user()?->company_id;
        if (! $companyId) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $query = BillingSubscription::where('company_id', $companyId)
            ->with('client:id,name')
            ->orderBy('created_at', 'desc');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $subscriptions = $query->get();

        $data = $subscriptions->map(fn ($s) => $this->formatSubscription($s));

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Store a new billing subscription. Creates in Stripe when integration is configured.
     */
    public function store(Request $request): JsonResponse
    {
        $companyId = Auth::user()?->company_id;
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Company not found.'], 403);
        }

        $valid = $request->validate([
            'client_id' => ['required', 'integer', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'product_name' => ['required', 'string', 'max:255'],
            'unit_amount' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'interval' => ['required', Rule::in(['day', 'week', 'month', 'year'])],
            'interval_count' => ['required', 'integer', 'min:1', 'max:12'],
            'status' => ['required', Rule::in([
                'incomplete', 'incomplete_expired', 'trialing', 'active',
                'past_due', 'canceled', 'unpaid', 'paused',
            ])],
            'current_period_start' => ['required', 'date'],
            'trial_end' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ]);

        $start = \Carbon\Carbon::parse($valid['current_period_start']);
        $end = $this->computePeriodEnd($start, $valid['interval'], $valid['interval_count']);
        $currency = strtolower($valid['currency'] ?? 'usd');
        $client = Client::find($valid['client_id']);

        $stripeSubscriptionId = null;
        $stripeCustomerId = null;
        $stripePriceId = null;
        $hostedInvoiceUrl = null;
        $status = $valid['status'];

        $integration = StripeIntegration::where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('secret_key')
            ->where('secret_key', '!=', '')
            ->first();

        if ($integration) {
            try {
                $secretKey = Crypt::decryptString($integration->secret_key);
                \Stripe\Stripe::setApiKey($secretKey);

                $stripeCustomerId = $client->stripe_customer_id;
                $needNewCustomer = ! $stripeCustomerId;
                if ($stripeCustomerId) {
                    try {
                        $existing = \Stripe\Customer::retrieve($stripeCustomerId);
                        if ($existing->deleted ?? false) {
                            $needNewCustomer = true;
                            $stripeCustomerId = null;
                            $client->update(['stripe_customer_id' => null]);
                        }
                    } catch (\Stripe\Exception\ApiErrorException $e) {
                        if ($e->getStripeCode() === 'resource_missing' || str_contains($e->getMessage(), 'No such customer')) {
                            $needNewCustomer = true;
                            $stripeCustomerId = null;
                            $client->update(['stripe_customer_id' => null]);
                        } else {
                            throw $e;
                        }
                    }
                }
                if ($needNewCustomer || ! $stripeCustomerId) {
                    $customer = \Stripe\Customer::create([
                        'name' => $client->name,
                        'email' => $client->email ?: null,
                        'metadata' => [
                            'client_id' => (string) $client->id,
                            'company_id' => (string) $companyId,
                        ],
                    ]);
                    $stripeCustomerId = $customer->id;
                    $client->update(['stripe_customer_id' => $stripeCustomerId]);
                }

                $product = \Stripe\Product::create([
                    'name' => $valid['product_name'],
                    'metadata' => ['company_id' => (string) $companyId],
                ]);

                $subscriptionParams = [
                    'customer' => $stripeCustomerId,
                    'collection_method' => 'send_invoice',
                    'days_until_due' => 30,
                    'items' => [
                        [
                            'price_data' => [
                                'currency' => $currency,
                                'product' => $product->id,
                                'recurring' => [
                                    'interval' => $valid['interval'],
                                    'interval_count' => $valid['interval_count'],
                                ],
                                'unit_amount' => $valid['unit_amount'],
                            ],
                            'quantity' => 1,
                        ],
                    ],
                    'metadata' => [
                        'client_id' => (string) $client->id,
                        'company_id' => (string) $companyId,
                    ],
                ];

                if (! empty($valid['trial_end'])) {
                    $subscriptionParams['trial_end'] = \Carbon\Carbon::parse($valid['trial_end'])->timestamp;
                }

                if (! empty($valid['notes'])) {
                    $subscriptionParams['description'] = $valid['notes'];
                }

                $subscriptionParams['expand'] = ['latest_invoice'];

                $stripeSubscription = null;
                $createAttempts = 0;
                $maxAttempts = 2;
                while ($createAttempts < $maxAttempts) {
                    try {
                        $stripeSubscription = \Stripe\Subscription::create($subscriptionParams);
                        break;
                    } catch (\Stripe\Exception\ApiErrorException $e) {
                        $isMissingCustomer = $e->getStripeCode() === 'resource_missing'
                            || str_contains($e->getMessage(), 'No such customer');
                        if ($isMissingCustomer && $createAttempts === 0) {
                            $customer = \Stripe\Customer::create([
                                'name' => $client->name,
                                'email' => $client->email ?: null,
                                'metadata' => [
                                    'client_id' => (string) $client->id,
                                    'company_id' => (string) $companyId,
                                ],
                            ]);
                            $stripeCustomerId = $customer->id;
                            $client->update(['stripe_customer_id' => $stripeCustomerId]);
                            $subscriptionParams['customer'] = $stripeCustomerId;
                            $createAttempts++;
                        } else {
                            throw $e;
                        }
                    }
                }

                $stripeSubscriptionId = $stripeSubscription->id;
                $status = $stripeSubscription->status;
                $stripePriceId = $stripeSubscription->items->data[0]->price->id ?? null;
                $hostedInvoiceUrl = $this->getHostedInvoiceUrlFromSubscription($stripeSubscription, $secretKey);

                if ($stripeSubscription->current_period_start !== null && $stripeSubscription->current_period_end !== null) {
                    $start = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_start);
                    $end = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
                }
            } catch (\Stripe\Exception\ApiErrorException $e) {
                Log::error('Stripe subscription creation failed', [
                    'error' => $e->getMessage(),
                    'code' => $e->getStripeCode(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Stripe error: ' . $e->getMessage(),
                ], 400);
            } catch (\Exception $e) {
                Log::error('Stripe subscription creation failed', ['error' => $e->getMessage()]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create subscription: ' . $e->getMessage(),
                ], 500);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Stripe integration not configured. Configure Stripe in Integrations to create subscriptions.',
            ], 400);
        }

        $subscription = BillingSubscription::create([
            'company_id' => $companyId,
            'client_id' => $valid['client_id'],
            'product_name' => $valid['product_name'],
            'unit_amount' => $valid['unit_amount'],
            'currency' => $currency,
            'interval' => $valid['interval'],
            'interval_count' => $valid['interval_count'],
            'status' => $status,
            'current_period_start' => $start,
            'current_period_end' => $end,
            'trial_end' => isset($valid['trial_end']) ? \Carbon\Carbon::parse($valid['trial_end']) : null,
            'notes' => $valid['notes'] ?? null,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_customer_id' => $stripeCustomerId,
            'stripe_price_id' => $stripePriceId,
            'hosted_invoice_url' => $hostedInvoiceUrl,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatSubscription($subscription->load('client:id,name')),
        ], 201);
    }

    /**
     * Get the payment link (hosted invoice URL) for a subscription.
     */
    public function paymentLink(BillingSubscription $subscription): JsonResponse
    {
        $this->authorizeForCompany($subscription);

        $url = $subscription->hosted_invoice_url;

        if (! $url && $subscription->stripe_subscription_id) {
            $integration = StripeIntegration::where('company_id', $subscription->company_id)
                ->where('is_active', true)
                ->whereNotNull('secret_key')
                ->first();
            if ($integration) {
                try {
                    $secretKey = Crypt::decryptString($integration->secret_key);
                    \Stripe\Stripe::setApiKey($secretKey);
                    $stripeSub = \Stripe\Subscription::retrieve(
                        $subscription->stripe_subscription_id,
                        ['expand' => ['latest_invoice']]
                    );
                    $url = $this->getHostedInvoiceUrlFromSubscription($stripeSub, $secretKey);
                    if ($url) {
                        $subscription->update(['hosted_invoice_url' => $url]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to fetch subscription payment link', ['error' => $e->getMessage()]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'url' => $url,
            'message' => $url ? 'Payment link ready' : 'No payment link available for this subscription',
        ]);
    }

    /**
     * Show a single billing subscription.
     */
    public function show(BillingSubscription $subscription): JsonResponse
    {
        $this->authorizeForCompany($subscription);

        return response()->json([
            'success' => true,
            'data' => $this->formatSubscription($subscription->load('client:id,name')),
        ]);
    }

    /**
     * Update a billing subscription.
     */
    public function update(Request $request, BillingSubscription $subscription): JsonResponse
    {
        $this->authorizeForCompany($subscription);

        $valid = $request->validate([
            'product_name' => ['sometimes', 'string', 'max:255'],
            'unit_amount' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'interval' => ['sometimes', Rule::in(['day', 'week', 'month', 'year'])],
            'interval_count' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'status' => ['sometimes', Rule::in([
                'incomplete', 'incomplete_expired', 'trialing', 'active',
                'past_due', 'canceled', 'unpaid', 'paused',
            ])],
            'current_period_start' => ['sometimes', 'date'],
            'trial_end' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ]);

        if (isset($valid['current_period_start'])) {
            $interval = $valid['interval'] ?? $subscription->interval;
            $count = $valid['interval_count'] ?? $subscription->interval_count;
            $start = \Carbon\Carbon::parse($valid['current_period_start']);
            $valid['current_period_end'] = $this->computePeriodEnd($start, $interval, $count);
        }

        if (isset($valid['status']) && $valid['status'] === 'canceled') {
            $valid['canceled_at'] = now();
            $this->cancelStripeSubscriptionIfExists($subscription);
        }

        $this->syncSubscriptionToStripe($subscription, $valid);

        $subscription->update($valid);

        return response()->json([
            'success' => true,
            'data' => $this->formatSubscription($subscription->fresh()->load('client:id,name')),
        ]);
    }

    /**
     * Sync subscription changes to Stripe when a stripe_subscription_id exists.
     * Updates Product name, Price (if amount/interval/currency changed), notes, trial_end, and pause state.
     */
    private function syncSubscriptionToStripe(BillingSubscription $subscription, array $updates): void
    {
        if (! $subscription->stripe_subscription_id) {
            return;
        }
        $integration = StripeIntegration::where('company_id', $subscription->company_id)
            ->where('is_active', true)
            ->whereNotNull('secret_key')
            ->first();
        if (! $integration) {
            return;
        }
        try {
            $secretKey = Crypt::decryptString($integration->secret_key);
            \Stripe\Stripe::setApiKey($secretKey);

            $stripeSub = \Stripe\Subscription::retrieve(
                $subscription->stripe_subscription_id,
                ['expand' => ['items.data.price', 'items.data.price.product']]
            );
            $item = $stripeSub->items->data[0] ?? null;
            if (! $item) {
                return;
            }
            $price = $item->price;
            $productId = is_object($price->product) ? $price->product->id : $price->product;

            $productName = $updates['product_name'] ?? $subscription->product_name;
            $unitAmount = $updates['unit_amount'] ?? $subscription->unit_amount;
            $currency = strtolower($updates['currency'] ?? $subscription->currency ?? 'usd');
            $interval = $updates['interval'] ?? $subscription->interval;
            $intervalCount = (int) ($updates['interval_count'] ?? $subscription->interval_count);
            $notes = $updates['notes'] ?? $subscription->notes;
            $status = $updates['status'] ?? $subscription->status;
            $trialEnd = isset($updates['trial_end']) ? $updates['trial_end'] : $subscription->trial_end?->format('Y-m-d');

            if (isset($updates['product_name'])) {
                \Stripe\Product::update($productId, ['name' => $productName]);
            }

            $priceChanged = isset($updates['unit_amount']) || isset($updates['currency'])
                || isset($updates['interval']) || isset($updates['interval_count']);
            $currentAmount = $price->unit_amount ?? 0;
            $currentInterval = $price->recurring->interval ?? 'month';
            $currentCount = (int) ($price->recurring->interval_count ?? 1);
            if ($priceChanged && ($unitAmount !== $currentAmount || $currency !== $price->currency
                || $interval !== $currentInterval || $intervalCount !== $currentCount)) {
                $newPrice = \Stripe\Price::create([
                    'currency' => $currency,
                    'product' => $productId,
                    'recurring' => ['interval' => $interval, 'interval_count' => $intervalCount],
                    'unit_amount' => $unitAmount,
                ]);
                \Stripe\Subscription::update($subscription->stripe_subscription_id, [
                    'items' => [
                        ['id' => $item->id, 'price' => $newPrice->id],
                    ],
                    'proration_behavior' => 'create_prorations',
                ]);
                $updates['stripe_price_id'] = $newPrice->id;
            }

            $subParams = [];
            if (array_key_exists('notes', $updates)) {
                $subParams['description'] = $notes;
            }
            if (array_key_exists('trial_end', $updates)) {
                $subParams['trial_end'] = $trialEnd ? \Carbon\Carbon::parse($trialEnd)->timestamp : 'now';
            }
            if ($status === 'paused') {
                $subParams['pause_collection'] = ['behavior' => 'void'];
            } elseif (($updates['status'] ?? null) === 'active' && $subscription->status === 'paused') {
                $subParams['pause_collection'] = '';
            }
            if (! empty($subParams)) {
                \Stripe\Subscription::update($subscription->stripe_subscription_id, $subParams);
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::warning('Stripe subscription sync failed', [
                'id' => $subscription->stripe_subscription_id,
                'error' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Stripe subscription sync failed', [
                'id' => $subscription->stripe_subscription_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cancel a billing subscription immediately (and in Stripe if connected).
     */
    public function cancel(BillingSubscription $subscription): JsonResponse
    {
        $this->authorizeForCompany($subscription);

        $this->cancelStripeSubscriptionIfExists($subscription);
        $subscription->update(['status' => 'canceled', 'canceled_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => $this->formatSubscription($subscription->fresh()->load('client:id,name')),
        ]);
    }

    private function cancelStripeSubscriptionIfExists(BillingSubscription $subscription): void
    {
        if (! $subscription->stripe_subscription_id) {
            return;
        }
        $integration = StripeIntegration::where('company_id', $subscription->company_id)
            ->where('is_active', true)
            ->whereNotNull('secret_key')
            ->first();
        if (! $integration) {
            return;
        }
        try {
            $secretKey = Crypt::decryptString($integration->secret_key);
            \Stripe\Stripe::setApiKey($secretKey);
            $stripeSub = \Stripe\Subscription::retrieve($subscription->stripe_subscription_id);
            $stripeSub->cancel();
        } catch (\Exception $e) {
            Log::warning('Could not cancel Stripe subscription', ['id' => $subscription->stripe_subscription_id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Delete a billing subscription.
     */
    public function destroy(BillingSubscription $subscription): JsonResponse
    {
        $this->authorizeForCompany($subscription);

        $subscription->delete();

        return response()->json(['success' => true]);
    }

    private function getHostedInvoiceUrlFromSubscription($stripeSubscription, string $secretKey): ?string
    {
        $invoice = $stripeSubscription->latest_invoice ?? null;
        if (! $invoice) {
            return null;
        }
        $invoiceId = is_object($invoice) ? $invoice->id : $invoice;
        if (empty($invoiceId)) {
            return null;
        }
        $url = null;
        if (is_object($invoice) && ! empty($invoice->hosted_invoice_url)) {
            $url = $invoice->hosted_invoice_url;
        } else {
            try {
                \Stripe\Stripe::setApiKey($secretKey);
                $inv = \Stripe\Invoice::retrieve($invoiceId);
                if ($inv->status === 'draft') {
                    $inv = $inv->finalizeInvoice();
                }
                $url = $inv->hosted_invoice_url ?? null;
            } catch (\Exception $e) {
                Log::warning('Could not get hosted invoice URL', ['invoice_id' => $invoiceId, 'error' => $e->getMessage()]);
            }
        }

        return ($url && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))) ? $url : null;
    }

    private function authorizeForCompany(BillingSubscription $subscription): void
    {
        $companyId = Auth::user()?->company_id;
        if (! $companyId || $subscription->company_id !== $companyId) {
            abort(404);
        }
    }

    private function computePeriodEnd(\Carbon\Carbon $start, string $interval, int $count): \Carbon\Carbon
    {
        return match ($interval) {
            'day' => $start->copy()->addDays($count),
            'week' => $start->copy()->addWeeks($count),
            'month' => $start->copy()->addMonths($count),
            'year' => $start->copy()->addYears($count),
            default => $start->copy()->addMonth(),
        };
    }

    private function formatSubscription(BillingSubscription $s): array
    {
        return [
            'id' => $s->id,
            'client_id' => $s->client_id,
            'client' => $s->client?->name ?? '-',
            'product_name' => $s->product_name,
            'unit_amount' => $s->unit_amount,
            'amount' => $s->unit_amount / 100,
            'currency' => $s->currency,
            'interval' => $s->interval,
            'interval_count' => $s->interval_count,
            'billing_cycle_display' => $s->billing_cycle_display,
            'status' => $s->status,
            'current_period_start' => $s->current_period_start?->toIso8601String(),
            'current_period_end' => $s->current_period_end?->toIso8601String(),
            'start_date' => $s->current_period_start?->format('M j, Y') ?? '-',
            'next_billing' => $s->current_period_end?->format('M j, Y') ?? '-',
            'trial_end' => $s->trial_end?->toIso8601String(),
            'canceled_at' => $s->canceled_at?->toIso8601String(),
            'notes' => $s->notes,
            'stripe_subscription_id' => $s->stripe_subscription_id,
            'stripe_customer_id' => $s->stripe_customer_id,
            'hosted_invoice_url' => $s->hosted_invoice_url,
            'created_at' => $s->created_at?->toIso8601String(),
        ];
    }
}
