<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            \DB::statement("ALTER TABLE quotations MODIFY COLUMN status ENUM('draft', 'sent', 'accepted', 'rejected', 'expired', 'paid') DEFAULT 'draft'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            // First set any 'paid' quotations to 'accepted' before removing the value
            \DB::table('quotations')->where('status', 'paid')->update(['status' => 'accepted']);
            \DB::statement("ALTER TABLE quotations MODIFY COLUMN status ENUM('draft', 'sent', 'accepted', 'rejected', 'expired') DEFAULT 'draft'");
        }
    }
};
