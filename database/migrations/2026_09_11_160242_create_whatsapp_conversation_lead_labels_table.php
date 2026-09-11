<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversation_lead_label', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('whatsapp_conversation_id');
            $table->unsignedBigInteger('lead_label_id');
            $table->timestamps();

            $table->foreign('whatsapp_conversation_id', 'wa_conv_lead_label_conv_fk')
                ->references('id')->on('whatsapp_conversations')->cascadeOnDelete();
            $table->foreign('lead_label_id', 'wa_conv_lead_label_label_fk')
                ->references('id')->on('lead_labels')->cascadeOnDelete();

            $table->unique(['whatsapp_conversation_id', 'lead_label_id'], 'whatsapp_conv_lead_label_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversation_lead_label');
    }
};
