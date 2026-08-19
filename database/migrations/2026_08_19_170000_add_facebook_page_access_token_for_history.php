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
            if (! Schema::hasColumn('facebook_integrations', 'page_access_token')) {
                $table->text('page_access_token')->nullable()->after('page_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('facebook_integrations')) {
            return;
        }

        Schema::table('facebook_integrations', function (Blueprint $table) {
            if (Schema::hasColumn('facebook_integrations', 'page_access_token')) {
                $table->dropColumn('page_access_token');
            }
        });
    }
};
