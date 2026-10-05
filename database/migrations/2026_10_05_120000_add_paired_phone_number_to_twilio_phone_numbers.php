<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('twilio_phone_numbers') || Schema::hasColumn('twilio_phone_numbers', 'paired_phone_number')) {
            return;
        }

        Schema::table('twilio_phone_numbers', function (Blueprint $table) {
            // On the local main row: the mobile number used as outbound caller ID.
            $table->string('paired_phone_number', 20)->nullable()->after('phone_number');
        });

        DB::table('twilio_phone_numbers')
            ->where('phone_number', '+63284647261')
            ->update(['paired_phone_number' => '+639190590291']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('twilio_phone_numbers', 'paired_phone_number')) {
            Schema::table('twilio_phone_numbers', function (Blueprint $table) {
                $table->dropColumn('paired_phone_number');
            });
        }
    }
};
