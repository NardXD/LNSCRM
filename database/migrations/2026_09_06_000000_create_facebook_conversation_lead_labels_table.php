<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_conversation_lead_label', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('facebook_conversation_id');
            $table->unsignedBigInteger('lead_label_id');
            $table->timestamps();

            $table->foreign('facebook_conversation_id', 'fb_conv_lead_label_conv_fk')
                ->references('id')->on('facebook_conversations')->cascadeOnDelete();
            $table->foreign('lead_label_id', 'fb_conv_lead_label_label_fk')
                ->references('id')->on('lead_labels')->cascadeOnDelete();

            $table->unique(['facebook_conversation_id', 'lead_label_id'], 'facebook_conv_lead_label_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_conversation_lead_label');
    }
};
