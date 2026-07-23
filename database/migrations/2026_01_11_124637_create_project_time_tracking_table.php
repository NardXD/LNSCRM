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
        Schema::create('project_time_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('task_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->integer('hours_worked')->nullable(); // Duration in seconds
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['project_id', 'date']);
            $table->index(['task_id']);
            $table->index('company_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_time_tracking');
    }
};
