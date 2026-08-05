<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('infobip_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('base_url');
            $table->text('api_key');
            $table->string('application_id')->nullable();
            $table->string('default_from_number')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (Schema::hasTable('twilio_phone_numbers') && ! Schema::hasTable('infobip_phone_numbers')) {
            Schema::rename('twilio_phone_numbers', 'infobip_phone_numbers');
        }

        if (Schema::hasTable('infobip_phone_numbers')) {
            Schema::table('infobip_phone_numbers', function (Blueprint $table) {
                if (Schema::hasColumn('infobip_phone_numbers', 'twilio_sid') && ! Schema::hasColumn('infobip_phone_numbers', 'infobip_number_id')) {
                    $table->string('infobip_number_id')->nullable()->after('phone_number');
                }
            });

            if (Schema::hasColumn('infobip_phone_numbers', 'twilio_sid')) {
                DB::table('infobip_phone_numbers')
                    ->whereNotNull('twilio_sid')
                    ->whereNull('infobip_number_id')
                    ->update(['infobip_number_id' => DB::raw('twilio_sid')]);

                Schema::table('infobip_phone_numbers', function (Blueprint $table) {
                    $table->dropColumn('twilio_sid');
                });
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'twilio_number') && ! Schema::hasColumn('users', 'phone_system_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone_system_number')->nullable()->after('twilio_number');
            });

            DB::table('users')
                ->whereNotNull('twilio_number')
                ->update(['phone_system_number' => DB::raw('twilio_number')]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('twilio_number');
            });
        }

        Schema::dropIfExists('twilio_integrations');
    }

    public function down(): void
    {
        Schema::create('twilio_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('account_sid')->nullable();
            $table->text('auth_token')->nullable();
            $table->string('app_sid')->nullable();
            $table->string('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'phone_system_number') && ! Schema::hasColumn('users', 'twilio_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('twilio_number')->nullable()->after('phone_system_number');
            });

            DB::table('users')
                ->whereNotNull('phone_system_number')
                ->update(['twilio_number' => DB::raw('phone_system_number')]);

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('phone_system_number');
            });
        }

        if (Schema::hasTable('infobip_phone_numbers') && ! Schema::hasTable('twilio_phone_numbers')) {
            Schema::table('infobip_phone_numbers', function (Blueprint $table) {
                if (! Schema::hasColumn('infobip_phone_numbers', 'twilio_sid')) {
                    $table->string('twilio_sid')->nullable()->after('phone_number');
                }
            });

            if (Schema::hasColumn('infobip_phone_numbers', 'infobip_number_id')) {
                DB::table('infobip_phone_numbers')
                    ->whereNotNull('infobip_number_id')
                    ->update(['twilio_sid' => DB::raw('infobip_number_id')]);

                Schema::table('infobip_phone_numbers', function (Blueprint $table) {
                    $table->dropColumn('infobip_number_id');
                });
            }

            Schema::rename('infobip_phone_numbers', 'twilio_phone_numbers');
        }

        Schema::dropIfExists('infobip_integrations');
    }
};
