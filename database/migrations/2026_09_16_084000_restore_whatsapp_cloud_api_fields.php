<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_integrations', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_integrations', 'phone_number_id')) {
                $table->string('phone_number_id', 64)->nullable()->after('company_id');
            }
            if (! Schema::hasColumn('whatsapp_integrations', 'waba_id')) {
                $table->string('waba_id', 64)->nullable()->after('phone_number_id');
            }
            if (! Schema::hasColumn('whatsapp_integrations', 'access_token')) {
                $table->text('access_token')->nullable()->after('waba_id');
            }
            if (! Schema::hasColumn('whatsapp_integrations', 'app_secret')) {
                $table->text('app_secret')->nullable()->after('access_token');
            }
            if (! Schema::hasColumn('whatsapp_integrations', 'webhook_verify_token')) {
                $table->string('webhook_verify_token', 128)->nullable()->after('webhook_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_integrations', function (Blueprint $table) {
            foreach (['phone_number_id', 'waba_id', 'access_token', 'app_secret', 'webhook_verify_token'] as $column) {
                if (Schema::hasColumn('whatsapp_integrations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
