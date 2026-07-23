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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->text('address')->nullable()->after('phone');
            $table->date('date_of_birth')->nullable()->after('address');
            $table->date('employment_date')->nullable()->after('date_of_birth');
            $table->string('photo')->nullable()->after('employment_date');
            $table->decimal('salary', 10, 2)->nullable()->after('photo');
            $table->string('twilio_number')->nullable()->after('salary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'address',
                'date_of_birth',
                'employment_date',
                'photo',
                'salary',
                'twilio_number'
            ]);
        });
    }
};
