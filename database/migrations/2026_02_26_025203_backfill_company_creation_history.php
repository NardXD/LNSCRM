<?php

use App\Models\Company;
use App\Models\CompanyHistory;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $companies = Company::whereDoesntHave('histories', fn ($q) => $q->where('action', CompanyHistory::ACTION_CREATED))
            ->get();

        foreach ($companies as $company) {
            CompanyHistory::create([
                'company_id' => $company->id,
                'action' => CompanyHistory::ACTION_CREATED,
                'old_value' => null,
                'new_value' => [
                    'status' => $company->status,
                    'modules' => $company->modules()->pluck('slug')->toArray(),
                ],
                'description' => 'Company created (history backfill)',
                'changed_by' => null,
                'created_at' => $company->created_at,
                'updated_at' => $company->created_at,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        CompanyHistory::where('description', 'Company created (history backfill)')->delete();
    }
};
