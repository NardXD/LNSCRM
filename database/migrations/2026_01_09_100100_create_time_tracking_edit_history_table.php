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
        Schema::create('time_tracking_edit_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_tracking_record_id')->constrained('time_tracking_records')->onDelete('cascade');
            $table->foreignId('edited_by')->constrained('users')->onDelete('cascade');
            $table->time('old_time_in')->nullable();
            $table->time('new_time_in')->nullable();
            $table->time('old_time_out')->nullable();
            $table->time('new_time_out')->nullable();
            $table->integer('old_hours_worked')->nullable();
            $table->integer('new_hours_worked')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            
            $table->index('time_tracking_record_id');
            $table->index('edited_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_tracking_edit_history');
    }
};
