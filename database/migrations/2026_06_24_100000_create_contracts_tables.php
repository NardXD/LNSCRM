<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('contract_number');
            $table->string('title');
            $table->longText('content');
            $table->enum('status', [
                'draft',
                'pending_signatures',
                'partially_signed',
                'signed',
                'cancelled',
                'expired',
            ])->default('draft');
            $table->date('effective_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['client_id', 'status']);
            $table->unique(['company_id', 'contract_number'], 'contracts_company_id_contract_number_unique');
        });

        Schema::create('contract_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->enum('role', ['client', 'company', 'witness'])->default('client');
            $table->unsignedSmallInteger('signing_order')->default(1);
            $table->string('token', 64)->unique()->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->enum('status', ['pending', 'signed', 'declined'])->default('pending');
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('signature_ip', 45)->nullable();
            $table->string('signature_method', 20)->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'status']);
            $table->index('token');
        });

        Schema::create('contract_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->string('previous_status')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_status_histories');
        Schema::dropIfExists('contract_signers');
        Schema::dropIfExists('contracts');
    }
};
