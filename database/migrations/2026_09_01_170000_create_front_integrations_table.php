<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('front_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->text('api_token')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('last_import_stats')->nullable();
            $table->timestamp('last_import_at')->nullable();
            $table->boolean('last_import_dry_run')->default(false);
            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('front_integrations');
    }
};
