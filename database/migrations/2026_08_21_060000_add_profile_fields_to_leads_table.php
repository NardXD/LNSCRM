<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('title', 10)->nullable()->after('name');
            $table->string('first_name')->nullable()->after('title');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('address', 500)->nullable()->after('last_name');
            $table->string('city')->nullable()->after('address');
            $table->string('postal_code', 20)->nullable()->after('city');
            $table->date('date_of_birth')->nullable()->after('postal_code');

            $table->string('alt_title', 10)->nullable()->after('company_name');
            $table->string('alt_first_name')->nullable()->after('alt_title');
            $table->string('alt_last_name')->nullable()->after('alt_first_name');
            $table->string('alt_address', 500)->nullable()->after('alt_last_name');
            $table->string('alt_city')->nullable()->after('alt_address');
            $table->string('alt_postal_code', 20)->nullable()->after('alt_city');

            $table->string('customer_type', 20)->nullable()->after('source');
            $table->string('residential_type', 30)->nullable()->after('customer_type');
            $table->string('business_industry', 100)->nullable()->after('residential_type');
            $table->string('business_industry_other')->nullable()->after('business_industry');
            $table->string('storage_reason', 50)->nullable()->after('business_industry_other');
            $table->string('storage_reason_other')->nullable()->after('storage_reason');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'title',
                'first_name',
                'last_name',
                'address',
                'city',
                'postal_code',
                'date_of_birth',
                'alt_title',
                'alt_first_name',
                'alt_last_name',
                'alt_address',
                'alt_city',
                'alt_postal_code',
                'customer_type',
                'residential_type',
                'business_industry',
                'business_industry_other',
                'storage_reason',
                'storage_reason_other',
            ]);
        });
    }
};
