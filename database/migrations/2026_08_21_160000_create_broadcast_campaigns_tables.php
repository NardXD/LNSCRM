<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('type', 16);
            $table->string('status', 24)->default('draft');
            $table->string('sender_label')->nullable();
            $table->string('from_number')->nullable();
            $table->foreignId('shared_inbox_id')->nullable()->constrained('shared_inboxes')->nullOnDelete();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('broadcast_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_campaign_id')->constrained('broadcast_campaigns')->cascadeOnDelete();
            $table->string('source', 32)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('name')->nullable();
            $table->string('address');
            $table->string('status', 24)->default('pending');
            $table->string('provider_sid')->nullable();
            $table->string('error_message', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['broadcast_campaign_id', 'status']);
            $table->index('provider_sid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_campaign_recipients');
        Schema::dropIfExists('broadcast_campaigns');
    }
};
