<?php

namespace App\Services\Quote;

use App\Models\Company;
use App\Models\Quotation;

class QuotationNumberGenerator
{
    /**
     * Generate the next quotation number for a company (format: PREFIX-YYYY-###).
     */
    public function next(Company $company): string
    {
        $prefix = $company->quotation_prefix;
        $year = now()->year;

        $lastQuotation = Quotation::where('company_id', $company->id)
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastQuotation) {
            $parts = explode('-', $lastQuotation->quotation_number);
            $lastNumber = (int) end($parts);
            $nextNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '001';
        }

        return "{$prefix}-{$year}-{$nextNumber}";
    }
}
