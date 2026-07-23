<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hiring_queue_item_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hiring_queue_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();

            $table->index('hiring_queue_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hiring_queue_item_comments');
    }
};
