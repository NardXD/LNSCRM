<?php

namespace App\Http\Controllers;

use App\Mail\StorageQuoteMail;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationStatusHistory;
use App\Services\CompanyOutboundMailService;
use App\Services\LeadQuoteMapper;
use App\Services\Quote\QuotationBuilderEmailTemplateService;
use App\Services\Quote\QuotationNumberGenerator;
use App\Services\Quote\QuoteDocumentData;
use App\Services\StoreganiseService;
use App\Support\Facilities;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StorageQuoteController extends Controller
{
    public function __construct(
        protected LeadQuoteMapper $leadMapper,
    ) {}

    public function show(Lead $lead): View
    {
        $this->authorizeLead($lead);

        $tenant = $this->leadMapper->toLegacy($lead);
        $locode = Facilities::localCodeForSite($lead->storeganise_site_id);
        $facilityOptions = $this->facilityOptions();

        if ($locode === '' && count($facilityOptions) === 1) {
            $locode = array_key_first($facilityOptions);
        }

        return view('dashboard.storage-quote', [
            'lead' => $lead,
            'tenant' => $tenant,
            'locode' => $locode,
            'facilityOptions' => $facilityOptions,
            'facilityLabel' => Facilities::label($locode),
            'discountOptions' => Facilities::discountOptions($locode ?: 'default'),
            'storeganiseConfigured' => (new StoreganiseService(Auth::user()->company_id))->isConfigured(),
        ]);
    }

    public function unit(Request $request): Response
    {
        $locode = $request->string('lo_code')->toString();
        if ($locode === '') {
            $locode = $request->string('facility')->toString();
        }

        $action = $request->string('action')->toString();
        $index = match ($action) {
            'unit2' => '2',
            'unit3' => '3',
            'unit4' => '4',
            default => '1',
        };

        $unitName = $request->string('unit'.$index)->toString();

        $storeganise = new StoreganiseService(Auth::user()->company_id);
        $line = $storeganise->isConfigured()
            ? $storeganise->unitQuoteLine($locode, $unitName)
            : '';

        return response($line)->header('Content-Type', 'text/plain');
    }

    public function searchUnits(Request $request): JsonResponse
    {
        $locode = $request->string('lo_code')->toString();
        if ($locode === '') {
            $locode = $request->string('facility')->toString();
        }

        $term = $request->string('term')->toString();
        $storeganise = new StoreganiseService(Auth::user()->company_id);

        $units = $storeganise->isConfigured()
            ? $storeganise->searchUnits($locode, $term)
            : [];

        return response()->json($units);
    }

    public function print(Request $request): Response
    {
        $data = QuoteDocumentData::fromArray($this->quoteFormPayload($request), $this->signatureBase64($request));

        return $this->renderContract($data)->stream('storage-contract.pdf');
    }

    public function download(Request $request): Response
    {
        $data = QuoteDocumentData::fromArray($this->quoteFormPayload($request), $this->signatureBase64($request));

        $filename = Str::slug(trim($data['tenant']['first_name'].'-'.$data['tenant']['last_name']), '-') ?: 'lead';

        return $this->renderContract($data)->download("storage-contract-{$filename}.pdf");
    }

    public function email(Request $request): JsonResponse
    {
        $data = QuoteDocumentData::fromArray($this->quoteFormPayload($request));
        $email = trim((string) $data['tenant']['email']);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Enter a valid lead email before sending the quote.'], 422);
        }

        $user = Auth::user();
        $mailService = app(CompanyOutboundMailService::class);
        $mailbox = $mailService->quotationMailbox((int) $user->company_id);

        $pdf = Pdf::loadView('quotes.quote-pdf', ['data' => $data])->output();
        $emailTemplate = app(QuotationBuilderEmailTemplateService::class)->renderForQuote(
            (int) $user->company_id,
            $data,
            $user->company->name ?? 'Company'
        );
        $subject = $emailTemplate['subject'];
        $html = $emailTemplate['body'];

        try {
            if ($mailbox) {
                $sent = $mailService->sendViaOutlook($mailbox, $email, $subject, $html, [[
                    'name' => 'storage-quote.pdf',
                    'content' => $pdf,
                    'contentType' => 'application/pdf',
                ]]);

                if (! $sent) {
                    return response()->json(['message' => 'Could not send the email. Please try again.'], 500);
                }
            } else {
                $from = $mailService->configureMailer(
                    (int) $user->company_id,
                    $user->company->name ?? 'Company'
                );

                if (! $from) {
                    return response()->json([
                        'message' => CompanyOutboundMailService::configurationHelpMessage(),
                    ], 400);
                }

                Mail::to($email)->send(new StorageQuoteMail($subject, $html, $pdf, $from['email']));
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not send the email. Please try again.'], 500);
        }

        return response()->json(['message' => "Quote emailed to {$email}."]);
    }

    public function save(Request $request): JsonResponse
    {
        $lead = Lead::findOrFail($request->integer('lead_id'));
        $this->authorizeLead($lead);

        $data = QuoteDocumentData::fromArray($this->quoteFormPayload($request), $this->signatureBase64($request));

        $email = trim((string) $data['tenant']['email']);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Enter a valid lead email before saving the quote.'], 422);
        }

        $user = Auth::user();
        $company = $user->company;

        if (! $company) {
            return response()->json(['message' => 'Company not found.'], 404);
        }

        $quotation = DB::transaction(function () use ($lead, $data, $user, $company, $request) {
            $terms = $data['terms'];
            $terms['unit_size'] = $data['unit_size'];

            $items = $this->buildStorageQuoteItems($data);
            $subtotal = array_sum(array_map(
                fn (array $item) => $item['quantity'] * $item['unit_price'],
                $items
            ));

            $locode = $request->string('lo_code')->toString();

            $quotation = Quotation::create([
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'quotation_number' => app(QuotationNumberGenerator::class)->next($company),
                'quotation_date' => now(),
                'valid_until' => now()->addDays(30),
                'status' => 'draft',
                'subtotal' => $subtotal,
                'tax_amount' => $data['totals']['vat_amount'],
                'discount_amount' => $data['totals']['reduction'],
                'discount_type' => 'amount',
                'total' => $data['totals']['total_due'],
                'quote_type' => 'storage',
                'storage_tenant' => $data['tenant'],
                'storage_alt_contact' => $data['alt_contact'],
                'storage_units' => $data['all_units'],
                'storage_terms' => $terms,
                'storage_totals' => $data['totals'],
                'facility_code' => $locode !== '' ? $locode : null,
            ]);

            foreach ($items as $index => $item) {
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'item_name' => $item['item_name'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_percentage' => 0,
                    'tax_amount' => 0,
                    'total' => $item['quantity'] * $item['unit_price'],
                    'sort_order' => $index,
                ]);
            }

            if ($data['signature_base64']) {
                $path = "quotes/{$quotation->id}/signature.png";
                Storage::disk('local')->put($path, base64_decode($data['signature_base64']));
                $quotation->update(['signature_path' => $path]);
            }

            QuotationStatusHistory::create([
                'quotation_id' => $quotation->id,
                'user_id' => $user->id,
                'status' => 'draft',
                'previous_status' => null,
                'notes' => 'Storage quote saved from quotation builder',
            ]);

            return $quotation;
        });

        return response()->json([
            'success' => true,
            'message' => 'Quote saved.',
            'data' => [
                'id' => $quotation->id,
                'quotation_number' => $quotation->quotation_number,
                'total' => (float) $quotation->total,
            ],
        ]);
    }

    /**
     * Decompose the normalized quote-document totals into invoice-style line items.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{item_name: string, description: ?string, quantity: float, unit_price: float}>
     */
    protected function buildStorageQuoteItems(array $data): array
    {
        $items = [];

        $items[] = [
            'item_name' => 'Storage Service Fee',
            'description' => trim('Initial period: '.$data['terms']['initial_period'].' month(s), '
                .$data['terms']['start_date_display'].' to '.$data['terms']['end_date_display']),
            'quantity' => 1,
            'unit_price' => (float) $data['totals']['final_storage_fee'],
        ];

        if ((float) $data['totals']['insurance_computation'] > 0) {
            $items[] = [
                'item_name' => 'Insurance Fee',
                'description' => null,
                'quantity' => 1,
                'unit_price' => (float) $data['totals']['insurance_computation'],
            ];
        }

        $items[] = [
            'item_name' => 'Security Deposit (non-VAT)',
            'description' => '1 month standard storage fee, net of VAT',
            'quantity' => 1,
            'unit_price' => (float) $data['totals']['deposit_notax'],
        ];

        $items[] = [
            'item_name' => 'Admin Fee',
            'description' => 'Documentation and processing fee',
            'quantity' => 1,
            'unit_price' => (float) $data['totals']['admin_fee'],
        ];

        foreach ($data['terms']['adjustments'] as $index => $amount) {
            if ((float) $amount === 0.0) {
                continue;
            }

            $items[] = [
                'item_name' => 'Other Adjustment '.($index + 1),
                'description' => $data['terms']['adjustment_remarks'][$index] ?: null,
                'quantity' => 1,
                'unit_price' => (float) $amount,
            ];
        }

        if ((float) $data['terms']['adjustments_nonvat'] !== 0.0) {
            $items[] = [
                'item_name' => 'Other Adjustment (non-VAT)',
                'description' => $data['terms']['adjustments_nonvat_remarks'] ?: null,
                'quantity' => 1,
                'unit_price' => (float) $data['terms']['adjustments_nonvat'],
            ];
        }

        return $items;
    }

    /**
     * @return array<string, string>
     */
    protected function facilityOptions(): array
    {
        $storeganise = new StoreganiseService(Auth::user()->company_id);

        if ($storeganise->isConfigured()) {
            try {
                $sites = $storeganise->listSitesDirectory();
                if ($sites !== []) {
                    return collect($sites)
                        ->mapWithKeys(fn (array $site, string $code) => [$code => $site['name'].' ('.$code.')'])
                        ->all();
                }
            } catch (\Throwable) {
                // Fall back to configured sites.
            }
        }

        return collect(Facilities::configured())
            ->mapWithKeys(fn (array $site, string $code) => [$code => ($site['name'] ?? $code).' ('.$code.')'])
            ->all();
    }

    protected function authorizeLead(Lead $lead): void
    {
        if ((int) $lead->company_id !== (int) Auth::user()->company_id) {
            abort(404);
        }
    }

    protected function renderContract(array $data): \Barryvdh\DomPDF\PDF
    {
        $pdf = Pdf::loadView('quotes.contract-pdf', ['data' => $data])->setPaper('a4');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $canvas->page_text($canvas->get_width() / 2 - 20, $canvas->get_height() - 35, 'Page {PAGE_NUM}/{PAGE_COUNT}', null, 9);

        return $pdf;
    }

    protected function signatureBase64(Request $request): ?string
    {
        $file = $request->file('fileToUpload');

        if (! $file || ! $file->isValid() || strtolower($file->getClientOriginalExtension()) !== 'png') {
            return null;
        }

        return base64_encode($file->get());
    }

    /**
     * @return array<string, mixed>
     */
    protected function quoteFormPayload(Request $request): array
    {
        $payload = $request->except('fileToUpload');

        if (isset($payload['company']) && ! is_string($payload['company'])) {
            unset($payload['company']);
        }

        if (
            (! isset($payload['tenant_company']) || $payload['tenant_company'] === '')
            && $request->request->has('tenant_company')
        ) {
            $payload['tenant_company'] = $request->request->get('tenant_company');
        }

        return $payload;
    }
}
