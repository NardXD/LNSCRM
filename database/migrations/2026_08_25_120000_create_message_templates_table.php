<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 32);
            $table->string('name', 160);
            $table->text('body_text');
            $table->timestamps();

            $table->index(['company_id', 'channel', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
