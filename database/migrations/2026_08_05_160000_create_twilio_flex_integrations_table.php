<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twilio_flex_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('workspace_sid')->nullable();
            $table->string('workflow_sid')->nullable();
            $table->string('webhook_key', 64)->unique();
            $table->string('api_key_prefix', 16)->nullable();
            $table->string('api_key_hash', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('company_id');
            $table->index('api_key_prefix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('twilio_flex_integrations');
    }
};
