<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_scheduled_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('lead_rule_id')->nullable()->constrained('lead_rules')->nullOnDelete();
            $table->foreignId('inbox_template_id')->constrained('inbox_templates')->cascadeOnDelete();
            $table->foreignId('shared_inbox_id')->nullable()->constrained('shared_inboxes')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('send_at');
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'send_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_scheduled_emails');
    }
};
