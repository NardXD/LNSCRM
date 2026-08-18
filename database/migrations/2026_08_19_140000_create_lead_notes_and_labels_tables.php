<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note');
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
        });

        Schema::create('lead_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 16)->default('#4338ca');
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('lead_lead_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('lead_label_id')->constrained('lead_labels')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['lead_id', 'lead_label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_lead_label');
        Schema::dropIfExists('lead_labels');
        Schema::dropIfExists('lead_notes');
    }
};
