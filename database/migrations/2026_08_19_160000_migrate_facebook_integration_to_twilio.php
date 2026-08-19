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

        $columns = [];
        foreach (['page_access_token', 'app_id', 'app_secret', 'webhook_verify_token'] as $column) {
            if (Schema::hasColumn('facebook_integrations', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns === []) {
            return;
        }

        Schema::table('facebook_integrations', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        Schema::table('facebook_integrations', function (Blueprint $table) {
            if (! Schema::hasColumn('facebook_integrations', 'page_access_token')) {
                $table->text('page_access_token')->nullable();
            }
            if (! Schema::hasColumn('facebook_integrations', 'app_id')) {
                $table->string('app_id', 64)->nullable();
            }
            if (! Schema::hasColumn('facebook_integrations', 'app_secret')) {
                $table->text('app_secret')->nullable();
            }
            if (! Schema::hasColumn('facebook_integrations', 'webhook_verify_token')) {
                $table->string('webhook_verify_token', 128)->nullable();
            }
        });
    }
};
