<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twilio_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number', 20);
            $table->string('twilio_sid', 64)->nullable();
            $table->string('friendly_name')->nullable();
            $table->json('capabilities')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'phone_number']);
            $table->index(['company_id', 'assigned_user_id']);
        });

        Schema::create('phone_call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('call_sid', 64)->unique();
            $table->string('direction', 32)->nullable();
            $table->string('from_number', 32)->nullable();
            $table->string('to_number', 32)->nullable();
            $table->string('status', 32)->nullable();
            $table->unsignedInteger('duration')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'user_id']);
        });

        Schema::create('phone_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 32);
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'name']);
        });

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message_sid', 64)->unique();
            $table->string('direction', 16);
            $table->string('from_number', 32);
            $table->string('to_number', 32);
            $table->text('body');
            $table->string('status', 32)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'from_number', 'to_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('phone_contacts');
        Schema::dropIfExists('phone_call_logs');
        Schema::dropIfExists('twilio_phone_numbers');
    }
};
