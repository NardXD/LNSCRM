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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->nullable()->constrained()->onDelete('set null');
            $table->string('client_name');
            $table->string('ticket_number')->index();
            $table->string('subject');
            $table->text('description');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['open', 'in-progress', 'pending', 'resolved', 'closed'])->default('open');
            $table->string('category')->nullable();
            $table->string('sla')->default('compliant');
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->unique(['company_id', 'ticket_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
