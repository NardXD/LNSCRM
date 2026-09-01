<?php

namespace App\Services\Quote;

use App\Models\Company;
use App\Support\Facilities;

class QuoteDocumentData
{
    /**
     * Normalize the quote form's raw POST payload (produced client-side in
     * storage-quote.js) into a clean structure for contract/quote documents.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromArray(array $data, ?string $signatureBase64 = null): array
    {
        $units = [];
        $allUnits = [];
        for ($i = 1; $i <= 4; $i++) {
            $code = trim((string) ($data["unit{$i}_print_hdn"] ?? ''));

            $discounted = self::numeric($data["unit{$i}_discount_hdn"] ?? null);
            $insuranceValue = (string) ($data["unit{$i}_insurance_hdn"] ?? '');

            $unit = [
                'code' => $code,
                'sqm' => (string) ($data["sqm{$i}_print_hdn"] ?? ''),
                'price' => $discounted > 0 ? $discounted : self::numeric($data["unit{$i}_price_hdn"] ?? null),
                'insurance_coverage' => $insuranceValue !== '' ? number_format((float) $insuranceValue).'K' : '',
                'insurance_fee' => self::numeric($data["unit{$i}_ins_hdn"] ?? null),
            ];

            $allUnits[] = $unit;
            if ($code !== '') {
                $units[] = $unit;
            }
        }

        $totalStorageDiscount = self::numeric($data['total_storage_discount_final'] ?? null);
        $totalStorageFee = $totalStorageDiscount > 0
            ? $totalStorageDiscount
            : self::numeric($data['total_storage_fee_final'] ?? null);

        $locode = (string) ($data['lo_code'] ?? '');
        $initialPeriod = (int) ($data['initial_period_hdn'] ?? 0);
        $startDate = (string) ($data['start_date'] ?? '');
        $endDate = null;
        $startDateDisplay = null;
        $endDateDisplay = null;
        if ($startDate !== '' && $initialPeriod > 0) {
            $to = date('Y-m-d', strtotime($startDate.' +'.$initialPeriod.' months'));
            $endDate = date('Y-m-d', strtotime($to.' -1 day'));
            $startDateDisplay = date('M-d-Y', strtotime($startDate));
            $endDateDisplay = date('M-d-Y', strtotime($endDate));
        }

        $reduction = self::numeric($data['reduction_hdn'] ?? null);
        if ($reduction <= 0) {
            $reduction = self::numeric($data['reduction_hdn1'] ?? null);
        }

        $reserved = ($data['reserved'] ?? '') === 'Yes'
            ? 'The above unit is reserved to you for 5 days at no cost.'
            : 'The above unit is not yet reserved, until confirmation is received by sales staff and a move-in date has been determined.';

        return [
            'tenant' => [
                'company' => self::tenantCompanyName($data),
                'mr_mrs' => (string) ($data['mr_mrs'] ?? ''),
                'first_name' => (string) ($data['fname'] ?? ''),
                'last_name' => (string) ($data['lname'] ?? ''),
                'address' => (string) ($data['address'] ?? ''),
                'postal' => (string) ($data['postal'] ?? ''),
                'phone' => (string) ($data['number'] ?? ''),
                'email' => (string) ($data['email'] ?? ''),
                'tin' => (string) ($data['tin'] ?? ''),
            ],
            'alt_contact' => [
                'mr_mrs' => (string) ($data['mr_mrs_alt'] ?? ''),
                'first_name' => (string) ($data['fname_alt'] ?? ''),
                'last_name' => (string) ($data['lname_alt'] ?? ''),
                'address' => (string) ($data['address_alt'] ?? ''),
                'postal' => (string) ($data['postal_alt'] ?? ''),
                'phone' => (string) ($data['number_alt'] ?? ''),
                'email' => (string) ($data['email_alt'] ?? ''),
            ],
            'units' => $units,
            'all_units' => $allUnits,
            'unit_size' => (string) ($data['unit_size'] ?? ''),
            'terms' => [
                'initial_period' => $initialPeriod,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_date_display' => $startDateDisplay,
                'end_date_display' => $endDateDisplay,
                'anniversary' => (string) ($data['anniversary'] ?? ''),
                'reserved' => $reserved,
                'withholding_tax' => ($data['withholding_tax'] ?? 'No') === 'Yes',
                'tax_exempt' => ($data['tax_exempt'] ?? 'No') === 'Yes',
                'renewal' => ($data['renewal'] ?? 'No') === 'Yes',
                'adjustments' => [
                    self::numeric($data['adjustment1_hdn'] ?? null),
                    self::numeric($data['adjustment2_hdn'] ?? null),
                    self::numeric($data['adjustment3_hdn'] ?? null),
                    self::numeric($data['adjustment4_hdn'] ?? null),
                ],
                'adjustment_remarks' => [
                    (string) ($data['adjustment1_remarks'] ?? ''),
                    (string) ($data['adjustment2_remarks'] ?? ''),
                    (string) ($data['adjustment3_remarks'] ?? ''),
                    (string) ($data['adjustment4_remarks'] ?? ''),
                ],
                'adjustments_nonvat' => self::numeric($data['adjustments_nonvat_print_hdn'] ?? null),
                'adjustments_nonvat_remarks' => (string) ($data['adjustments_nonvat_remarks'] ?? ''),
            ],
            'totals' => [
                'storage_fee' => $totalStorageFee,
                'insurance_total' => self::numeric($data['total_ins_final'] ?? null),
                'insurance_computation' => self::numeric($data['ins_total_hdn'] ?? null),
                'deposit_notax' => self::numeric($data['deposit_notax_hdn'] ?? null),
                'final_storage_fee' => self::numeric($data['final_storage_fee_hdn'] ?? null),
                'admin_fee' => self::numeric($data['admin_fee_hdn'] ?? null) ?: 750.0,
                'withholding_tax_amount' => self::numeric($data['withholding_tax_hdn'] ?? null),
                'total_due' => self::numeric($data['total_final_hdn'] ?? null),
                'vat_amount' => self::numeric($data['memo_vat_hdn'] ?? null),
                'late_fee' => self::numeric($data['late_fee'] ?? null),
                'reduction' => $reduction,
            ],
            'facility_label' => Facilities::label($locode),
            'banking' => Facilities::banking($locode),
            'signature_base64' => $signatureBase64,
            'generated_at' => now()->format('m-d-Y'),
        ];
    }

    protected static function numeric(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function tenantCompanyName(array $data): string
    {
        foreach (['tenant_company', 'company'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $value = $data[$key];

            if (is_string($value)) {
                return $value;
            }

            if ($value instanceof Company) {
                continue;
            }
        }

        return '';
    }
}
