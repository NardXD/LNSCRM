<?php

use App\Models\Lead;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 50);
            $table->string('name', 50);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
            $table->unique(['company_id', 'name']);
        });

        $now = now();
        $defaults = [
            ['slug' => 'new', 'name' => 'New', 'is_locked' => true],
            ['slug' => 'contacted', 'name' => 'Contacted', 'is_locked' => false],
            ['slug' => 'qualified', 'name' => 'Qualified', 'is_locked' => false],
            ['slug' => 'converted', 'name' => 'Converted', 'is_locked' => false],
            ['slug' => 'lost', 'name' => 'Lost', 'is_locked' => false],
            ['slug' => Lead::STATUS_SNOOZED, 'name' => 'Snoozed', 'is_locked' => false],
            ['slug' => Lead::STATUS_ARCHIVED, 'name' => 'Archived', 'is_locked' => false],
        ];

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach ($defaults as $index => $row) {
                DB::table('lead_statuses')->insert([
                    'company_id' => $companyId,
                    'slug' => $row['slug'],
                    'name' => $row['name'],
                    'sort_order' => $index + 1,
                    'is_locked' => $row['is_locked'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_statuses');
    }
};
