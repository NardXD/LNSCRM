<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hiring_queue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('job_title');
            $table->text('full_description');
            $table->string('source', 50)->default('client'); // client, sales_rep
            $table->string('status', 50)->default('confirmed'); // draft, confirmed, hired, closed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hiring_queue_items');
    }
};
