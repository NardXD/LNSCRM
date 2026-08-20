<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('facebook_integrations')) {
            return;
        }

        Schema::table('facebook_integrations', function (Blueprint $table) {
            if (! Schema::hasColumn('facebook_integrations', 'app_secret')) {
                $table->text('app_secret')->nullable()->after('page_access_token');
            }
            if (! Schema::hasColumn('facebook_integrations', 'webhook_verify_token')) {
                $table->string('webhook_verify_token', 128)->nullable()->after('webhook_key');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('facebook_integrations')) {
            return;
        }

        Schema::table('facebook_integrations', function (Blueprint $table) {
            if (Schema::hasColumn('facebook_integrations', 'app_secret')) {
                $table->dropColumn('app_secret');
            }
            if (Schema::hasColumn('facebook_integrations', 'webhook_verify_token')) {
                $table->dropColumn('webhook_verify_token');
            }
        });
    }
};
