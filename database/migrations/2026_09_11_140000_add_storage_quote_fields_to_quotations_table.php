<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->enum('quote_type', ['standard', 'storage'])->default('standard')->after('status');
            $table->json('storage_tenant')->nullable()->after('quote_type');
            $table->json('storage_alt_contact')->nullable()->after('storage_tenant');
            $table->json('storage_units')->nullable()->after('storage_alt_contact');
            $table->json('storage_terms')->nullable()->after('storage_units');
            $table->json('storage_totals')->nullable()->after('storage_terms');
            $table->string('facility_code', 20)->nullable()->after('storage_totals');
            $table->string('signature_path')->nullable()->after('facility_code');

            $table->index(['company_id', 'quote_type']);
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'quote_type']);
            $table->dropColumn([
                'quote_type',
                'storage_tenant',
                'storage_alt_contact',
                'storage_units',
                'storage_terms',
                'storage_totals',
                'facility_code',
                'signature_path',
            ]);
        });
    }
};
