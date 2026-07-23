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
            \DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('draft', 'sent', 'paid', 'overdue', 'rejected') DEFAULT 'draft'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            \DB::table('invoices')->where('status', 'rejected')->update(['status' => 'sent']);
            \DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('draft', 'sent', 'paid', 'overdue') DEFAULT 'draft'");
        }
    }
};
