<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_integrations', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_integrations', 'access_token')) {
                $table->dropColumn('access_token');
            }
            if (Schema::hasColumn('whatsapp_integrations', 'phone_number_id')) {
                $table->dropColumn('phone_number_id');
            }
            if (Schema::hasColumn('whatsapp_integrations', 'waba_id')) {
                $table->dropColumn('waba_id');
            }
            if (Schema::hasColumn('whatsapp_integrations', 'app_secret')) {
                $table->dropColumn('app_secret');
            }
            if (Schema::hasColumn('whatsapp_integrations', 'webhook_verify_token')) {
                $table->dropColumn('webhook_verify_token');
            }
            if (! Schema::hasColumn('whatsapp_integrations', 'from_number')) {
                $table->string('from_number', 32)->nullable()->after('company_id');
            }
        });

        Schema::table('viber_integrations', function (Blueprint $table) {
            if (Schema::hasColumn('viber_integrations', 'auth_token')) {
                $table->dropColumn('auth_token');
            }
            if (Schema::hasColumn('viber_integrations', 'bot_uri')) {
                $table->dropColumn('bot_uri');
            }
            if (Schema::hasColumn('viber_integrations', 'bot_avatar')) {
                $table->dropColumn('bot_avatar');
            }
            if (! Schema::hasColumn('viber_integrations', 'sender_id')) {
                $table->string('sender_id', 128)->nullable()->after('company_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_integrations', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_integrations', 'from_number')) {
                $table->dropColumn('from_number');
            }
            if (! Schema::hasColumn('whatsapp_integrations', 'access_token')) {
                $table->text('access_token')->nullable();
            }
            if (! Schema::hasColumn('whatsapp_integrations', 'phone_number_id')) {
                $table->string('phone_number_id', 64)->nullable();
            }
            if (! Schema::hasColumn('whatsapp_integrations', 'waba_id')) {
                $table->string('waba_id', 64)->nullable();
            }
            if (! Schema::hasColumn('whatsapp_integrations', 'app_secret')) {
                $table->text('app_secret')->nullable();
            }
            if (! Schema::hasColumn('whatsapp_integrations', 'webhook_verify_token')) {
                $table->string('webhook_verify_token', 128)->nullable();
            }
        });

        Schema::table('viber_integrations', function (Blueprint $table) {
            if (Schema::hasColumn('viber_integrations', 'sender_id')) {
                $table->dropColumn('sender_id');
            }
            if (! Schema::hasColumn('viber_integrations', 'auth_token')) {
                $table->text('auth_token')->nullable();
            }
            if (! Schema::hasColumn('viber_integrations', 'bot_uri')) {
                $table->string('bot_uri')->nullable();
            }
            if (! Schema::hasColumn('viber_integrations', 'bot_avatar')) {
                $table->string('bot_avatar', 1024)->nullable();
            }
        });
    }
};
