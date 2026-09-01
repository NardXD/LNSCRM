<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_conversation_lead_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbox_conversation_id')->constrained('inbox_conversations')->cascadeOnDelete();
            $table->foreignId('lead_label_id')->constrained('lead_labels')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['inbox_conversation_id', 'lead_label_id'], 'inbox_conv_lead_label_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_conversation_lead_label');
    }
};
