<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->index(['company_id', 'created_at'], 'leads_company_id_created_at_index');
            $table->index(['company_id', 'updated_at'], 'leads_company_id_updated_at_index');
            $table->index(['company_id', 'assigned_to'], 'leads_company_id_assigned_to_index');
            $table->index(['company_id', 'source'], 'leads_company_id_source_index');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_company_id_created_at_index');
            $table->dropIndex('leads_company_id_updated_at_index');
            $table->dropIndex('leads_company_id_assigned_to_index');
            $table->dropIndex('leads_company_id_source_index');
        });
    }
};
