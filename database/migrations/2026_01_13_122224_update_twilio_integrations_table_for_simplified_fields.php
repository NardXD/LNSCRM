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
        if (!Schema::hasTable('twilio_integrations')) {
            Schema::create('twilio_integrations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->onDelete('cascade');
                $table->string('account_sid'); // TWILIO_SID - Required
                $table->text('auth_token')->nullable(); // TWILIO_AUTH_TOKEN - Required for new, nullable for updates
                $table->string('app_sid')->nullable(); // Optional field
                $table->string('api_key')->nullable(); // Optional field
                $table->text('api_secret')->nullable(); // Optional field
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('company_id');
                $table->unique(['company_id']);
            });
        }
        // Note: If table already exists, ensure app_sid, api_key, and api_secret are nullable
        // Run: ALTER TABLE twilio_integrations MODIFY app_sid VARCHAR(255) NULL, MODIFY api_key VARCHAR(255) NULL, MODIFY api_secret TEXT NULL;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('twilio_integrations');
    }
};
