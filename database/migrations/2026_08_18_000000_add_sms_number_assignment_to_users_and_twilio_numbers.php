<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'twilio_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('twilio_number')->nullable()->after('salary');
            });
        }

        if (! Schema::hasColumn('users', 'twilio_sms_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('twilio_sms_number')->nullable()->after('twilio_number');
            });
        }

        if (Schema::hasColumn('users', 'twilio_number') && Schema::hasColumn('users', 'twilio_sms_number')) {
            DB::table('users')
                ->whereNotNull('twilio_number')
                ->update(['twilio_sms_number' => DB::raw('twilio_number')]);
        }

        if (Schema::hasTable('twilio_phone_numbers') && ! Schema::hasColumn('twilio_phone_numbers', 'sms_assigned_user_id')) {
            Schema::table('twilio_phone_numbers', function (Blueprint $table) {
                $table->foreignId('sms_assigned_user_id')
                    ->nullable()
                    ->after('assigned_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
                $table->index(['company_id', 'sms_assigned_user_id']);
            });

            DB::table('twilio_phone_numbers')
                ->whereNotNull('assigned_user_id')
                ->update(['sms_assigned_user_id' => DB::raw('assigned_user_id')]);
        }
    }

    public function down(): void
    {
        Schema::table('twilio_phone_numbers', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'sms_assigned_user_id']);
            $table->dropConstrainedForeignId('sms_assigned_user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('twilio_sms_number');
        });
    }
};
