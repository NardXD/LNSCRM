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
        Schema::table('screen_recordings', function (Blueprint $table) {
            $table->string('upload_id')->nullable()->after('status');
            $table->string('device_id')->nullable()->after('upload_id');
            $table->string('device_platform', 20)->nullable()->after('device_id');
            $table->string('sync_status', 20)->default('queued')->after('device_platform');
            $table->string('upload_checksum', 128)->nullable()->after('sync_status');
            $table->unsignedInteger('retry_count')->default(0)->after('upload_checksum');
            $table->unsignedBigInteger('file_size')->nullable()->after('retry_count');
            $table->timestamp('queued_at')->nullable()->after('file_size');
            $table->timestamp('uploaded_at')->nullable()->after('queued_at');
            $table->timestamp('last_retry_at')->nullable()->after('uploaded_at');
            $table->text('last_error')->nullable()->after('last_retry_at');

            $table->index(['company_id', 'sync_status']);
            $table->index(['user_id', 'upload_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screen_recordings', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'sync_status']);
            $table->dropIndex(['user_id', 'upload_id']);
            $table->dropColumn([
                'upload_id',
                'device_id',
                'device_platform',
                'sync_status',
                'upload_checksum',
                'retry_count',
                'file_size',
                'queued_at',
                'uploaded_at',
                'last_retry_at',
                'last_error',
            ]);
        });
    }
};
