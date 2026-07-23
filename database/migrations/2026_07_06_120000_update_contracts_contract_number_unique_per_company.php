<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('contracts'))->pluck('name');

        Schema::table('contracts', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('contracts_contract_number_unique')) {
                $table->dropUnique('contracts_contract_number_unique');
            }

            if (! $indexes->contains('contracts_company_id_contract_number_unique')) {
                $table->unique(['company_id', 'contract_number'], 'contracts_company_id_contract_number_unique');
            }
        });
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('contracts'))->pluck('name');

        Schema::table('contracts', function (Blueprint $table) use ($indexes) {
            if ($indexes->contains('contracts_company_id_contract_number_unique')) {
                $table->dropUnique('contracts_company_id_contract_number_unique');
            }

            if (! $indexes->contains('contracts_contract_number_unique')) {
                $table->unique('contract_number');
            }
        });
    }
};
