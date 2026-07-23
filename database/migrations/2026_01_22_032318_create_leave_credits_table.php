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
        Schema::create('leave_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('leave_type', ['vacation', 'sick', 'personal', 'emergency', 'other'])->default('vacation');
            $table->decimal('credits', 8, 2)->default(0);
            $table->integer('year');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'leave_type', 'year']);
            $table->index(['company_id', 'user_id']);
            $table->index('year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_credits');
    }
};
