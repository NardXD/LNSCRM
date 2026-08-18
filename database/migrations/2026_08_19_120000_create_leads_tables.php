<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('status')->default('new');
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'name']);
        });

        Schema::create('lead_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('type'); // phone, email, facebook, instagram
            $table->string('value');
            $table->string('normalized_value')->nullable();
            $table->string('label')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['lead_id', 'type', 'normalized_value']);
            $table->index(['type', 'normalized_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_identities');
        Schema::dropIfExists('leads');
    }
};
