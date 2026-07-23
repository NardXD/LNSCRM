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
        Schema::create('screen_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->string('screen_recording_path')->nullable(); // Path to stored recording file
            $table->integer('screen_recording_duration')->nullable(); // Duration in seconds
            $table->enum('status', ['recording', 'completed', 'failed'])->default('recording');
            $table->json('metadata')->nullable(); // Additional data (session info, etc.)
            $table->timestamps();
            
            $table->index(['user_id', 'date']);
            $table->index('company_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screen_recordings');
    }
};
