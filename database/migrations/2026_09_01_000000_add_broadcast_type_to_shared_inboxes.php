<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE shared_inboxes MODIFY COLUMN type ENUM('personal', 'shared', 'broadcast') NOT NULL DEFAULT 'shared'");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE shared_inboxes MODIFY COLUMN type ENUM('personal', 'shared') NOT NULL DEFAULT 'shared'");
    }
};
