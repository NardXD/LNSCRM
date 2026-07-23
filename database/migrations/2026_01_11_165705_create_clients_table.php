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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('contact_person');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('industry')->nullable();
            $table->enum('status', ['active', 'inactive', 'prospect', 'lead'])->default('active');
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->decimal('revenue', 15, 2)->default(0);
            $table->timestamps();

            $table->index('company_id');
            $table->index('status');
            $table->index('industry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
