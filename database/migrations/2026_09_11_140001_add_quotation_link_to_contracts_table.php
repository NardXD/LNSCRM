<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->enum('content_type', ['html', 'storage_quote'])->default('html')->after('content');

            $table->index('quotation_id');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
            $table->dropIndex(['quotation_id']);
            $table->dropColumn(['quotation_id', 'content_type']);
        });
    }
};
