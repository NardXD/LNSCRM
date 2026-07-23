<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            // Drop existing unique constraints
            $table->dropUnique(['name']);
            $table->dropUnique(['slug']);
            
            // Add company_id
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            
            // Add unique constraints per company
            $table->unique(['company_id', 'name']);
            $table->unique(['company_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            // Drop company-scoped unique constraints
            $table->dropUnique(['company_id', 'name']);
            $table->dropUnique(['company_id', 'slug']);
            
            // Drop company_id
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
            
            // Restore original unique constraints
            $table->unique('name');
            $table->unique('slug');
        });
    }
};
