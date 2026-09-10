<?php

namespace App\Services\Quote;

use App\Models\Quotation;
use App\Support\Facilities;
use Illuminate\Support\Facades\Storage;

class QuotationDocumentMapper
{
    /**
     * Reconstruct the canonical quote-document shape (the same shape
     * QuoteDocumentData::fromArray() produces from a live form submission)
     * from a persisted storage Quotation.
     *
     * @return array<string, mixed>
     */
    public static function fromQuotation(Quotation $quotation): array
    {
        $terms = $quotation->storage_terms ?? [];
        $unitSize = (string) ($terms['unit_size'] ?? '');
        unset($terms['unit_size']);

        $allUnits = $quotation->storage_units ?? [];
        $units = array_values(array_filter(
            $allUnits,
            fn (array $unit) => trim((string) ($unit['code'] ?? '')) !== ''
        ));

        return [
            'tenant' => $quotation->storage_tenant ?? [],
            'alt_contact' => $quotation->storage_alt_contact ?? [],
            'units' => $units,
            'all_units' => $allUnits,
            'unit_size' => $unitSize,
            'terms' => $terms,
            'totals' => $quotation->storage_totals ?? [],
            'facility_label' => Facilities::label($quotation->facility_code),
            'banking' => Facilities::banking((string) $quotation->facility_code),
            'signature_base64' => self::signatureBase64($quotation),
            'generated_at' => $quotation->created_at?->format('m-d-Y') ?? now()->format('m-d-Y'),
        ];
    }

    protected static function signatureBase64(Quotation $quotation): ?string
    {
        if (! $quotation->signature_path || ! Storage::disk('local')->exists($quotation->signature_path)) {
            return null;
        }

        return base64_encode(Storage::disk('local')->get($quotation->signature_path));
    }
}
