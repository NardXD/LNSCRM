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
        Schema::create('mcp_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('key_hash', 64)->unique();
            $table->string('key_prefix', 12);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'key_prefix']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_api_keys');
    }
};
