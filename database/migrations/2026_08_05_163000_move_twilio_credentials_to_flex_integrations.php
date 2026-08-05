<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('twilio_flex_integrations', function (Blueprint $table) {
            if (! Schema::hasColumn('twilio_flex_integrations', 'account_sid')) {
                $table->string('account_sid')->nullable()->after('company_id');
            }
            if (! Schema::hasColumn('twilio_flex_integrations', 'auth_token')) {
                $table->text('auth_token')->nullable()->after('account_sid');
            }
            if (! Schema::hasColumn('twilio_flex_integrations', 'app_sid')) {
                $table->string('app_sid')->nullable()->after('auth_token');
            }
            if (! Schema::hasColumn('twilio_flex_integrations', 'api_key')) {
                $table->string('api_key')->nullable()->after('app_sid');
            }
            if (! Schema::hasColumn('twilio_flex_integrations', 'api_secret')) {
                $table->text('api_secret')->nullable()->after('api_key');
            }
        });

        if (Schema::hasTable('twilio_integrations')) {
            $rows = DB::table('twilio_integrations')->get();
            foreach ($rows as $row) {
                $existing = DB::table('twilio_flex_integrations')
                    ->where('company_id', $row->company_id)
                    ->first();

                $payload = [
                    'account_sid' => $row->account_sid,
                    'auth_token' => $row->auth_token,
                    'app_sid' => $row->app_sid,
                    'api_key' => $row->api_key,
                    'api_secret' => $row->api_secret,
                    'is_active' => (bool) $row->is_active,
                    'updated_at' => now(),
                ];

                if ($existing) {
                    DB::table('twilio_flex_integrations')
                        ->where('id', $existing->id)
                        ->update($payload);
                } else {
                    DB::table('twilio_flex_integrations')->insert(array_merge($payload, [
                        'company_id' => $row->company_id,
                        'webhook_key' => Str::random(40),
                        'created_at' => now(),
                    ]));
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('twilio_flex_integrations', function (Blueprint $table) {
            foreach (['account_sid', 'auth_token', 'app_sid', 'api_key', 'api_secret'] as $col) {
                if (Schema::hasColumn('twilio_flex_integrations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
