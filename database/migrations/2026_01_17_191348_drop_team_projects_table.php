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
        Schema::dropIfExists('team_projects');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('team_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Ensure a project can only be assigned to a team once
            $table->unique(['team_id', 'project_id']);
            $table->index('team_id');
            $table->index('project_id');
        });
    }
};
