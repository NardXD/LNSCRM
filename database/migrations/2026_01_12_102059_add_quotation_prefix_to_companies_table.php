<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'quotation_prefix')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->string('quotation_prefix', 10)->nullable()->after('subdomain');
            });
        }

        // Generate prefixes for existing companies
        $companies = \Illuminate\Support\Facades\DB::table('companies')
            ->whereNull('quotation_prefix')
            ->get();

        foreach ($companies as $company) {
            $prefix = $this->generateQuotationPrefix($company->name);
            \Illuminate\Support\Facades\DB::table('companies')
                ->where('id', $company->id)
                ->update(['quotation_prefix' => $prefix]);
        }
    }

    /**
     * Generate a quotation prefix from company name.
     */
    private function generateQuotationPrefix(string $companyName): string
    {
        // Remove common words and get initials
        $words = preg_split('/\s+/', strtoupper($companyName));
        $words = array_filter($words, function ($word) {
            $commonWords = ['CORP', 'CORPORATION', 'INC', 'INCORPORATED', 'LLC', 'LTD', 'LIMITED', 'CO', 'COMPANY', 'GROUP', 'HOLDINGS', 'THE'];

            return ! in_array($word, $commonWords) && strlen($word) > 0;
        });

        // Take first 2-3 meaningful words and create initials
        $words = array_slice(array_values($words), 0, 3);
        $prefix = '';

        if (count($words) >= 2) {
            // Take first 2-3 letters from first word and 1-2 from second
            $prefix = substr($words[0], 0, min(3, strlen($words[0])));
            if (isset($words[1])) {
                $prefix .= substr($words[1], 0, min(2, strlen($words[1])));
            }
        } elseif (count($words) === 1) {
            // Single word: take first 3-4 letters
            $prefix = substr($words[0], 0, min(4, strlen($words[0])));
        } else {
            // Fallback: use first 3 letters of company name
            $prefix = strtoupper(substr(preg_replace('/\s+/', '', $companyName), 0, 3));
        }

        // Ensure minimum length of 2 and maximum of 5
        $prefix = strtoupper(preg_replace('/[^A-Z]/', '', $prefix));
        $prefix = substr($prefix, 0, 5);
        $prefix = str_pad($prefix, 2, 'X', STR_PAD_RIGHT);

        return $prefix;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('quotation_prefix');
        });
    }
};
