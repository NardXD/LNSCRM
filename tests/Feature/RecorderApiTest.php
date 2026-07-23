<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ScreenRecording;
use App\Models\TimeTracking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecorderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_recorder_can_login_and_receive_token(): void
    {
        $company = Company::query()->create([
            'name' => 'Recorder Co',
            'subdomain' => 'recordco',
            'status' => 'active',
            'email' => 'admin@recordco.test',
        ]);

        User::query()->create([
            'name' => 'Recorder User',
            'email' => 'recorder@test.com',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/recorder/login', [
            'email' => 'recorder@test.com',
            'password' => 'password123',
            'company_subdomain' => 'recordco',
            'device_id' => 'device-1',
            'platform' => 'windows',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'token',
                'expires_at',
                'user' => ['id', 'name', 'email'],
                'company' => ['id', 'name', 'subdomain', 'timezone'],
            ]);
    }

    public function test_recorder_upload_lifecycle_works_with_token(): void
    {
        Storage::fake('private');

        $company = Company::query()->create([
            'name' => 'Recorder Co',
            'subdomain' => 'recordco',
            'status' => 'active',
            'email' => 'admin@recordco.test',
        ]);

        User::query()->create([
            'name' => 'Recorder User',
            'email' => 'recorder@test.com',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $login = $this->postJson('/api/recorder/login', [
            'email' => 'recorder@test.com',
            'password' => 'password123',
            'company_subdomain' => 'recordco',
            'device_id' => 'device-1',
            'platform' => 'windows',
        ]);

        $token = $login->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/recorder/uploads/start', [
                'upload_id' => 'upl-123',
                'date' => now()->format('Y-m-d'),
                'duration' => 10,
                'file_size' => 1024,
                'upload_checksum' => 'abc123',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $file = UploadedFile::fake()->create('recording.webm', 500, 'video/webm');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/recorder/uploads/chunk', [
                'upload_id' => 'upl-123',
                'recording' => $file,
            ])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/recorder/uploads/finalize', [
                'upload_id' => 'upl-123',
                'duration' => 20,
                'upload_checksum' => 'abc123',
                'file_size' => 2048,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_recorder_can_list_todays_recordings(): void
    {
        $company = Company::query()->create([
            'name' => 'Recorder Co',
            'subdomain' => 'recordco',
            'status' => 'active',
            'email' => 'admin@recordco.test',
            'timezone' => 'UTC',
        ]);

        $user = User::query()->create([
            'name' => 'Recorder User',
            'email' => 'recorder@test.com',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $login = $this->postJson('/api/recorder/login', [
            'email' => 'recorder@test.com',
            'password' => 'password123',
            'company_subdomain' => 'recordco',
            'device_id' => 'device-1',
            'platform' => 'windows',
        ]);

        $token = $login->json('token');

        $today = Carbon::now('UTC')->toDateString();
        $yesterday = Carbon::parse($today, 'UTC')->subDay()->toDateString();

        ScreenRecording::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'date' => $yesterday,
            'screen_recording_path' => null,
            'screen_recording_duration' => 10,
            'status' => 'completed',
            'upload_id' => 'upl-yesterday',
            'device_id' => 'device-1',
            'device_platform' => 'windows',
            'sync_status' => 'uploaded',
        ]);

        ScreenRecording::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'date' => $today,
            'screen_recording_path' => null,
            'screen_recording_duration' => 30,
            'status' => 'completed',
            'upload_id' => 'upl-today',
            'device_id' => 'device-1',
            'device_platform' => 'windows',
            'sync_status' => 'uploaded',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/recorder/recordings/today');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'recordings')
            ->assertJsonPath('recordings.0.upload_id', 'upl-today');
    }

    public function test_recorder_can_clock_in_and_out_with_token(): void
    {
        $company = Company::query()->create([
            'name' => 'Recorder Co',
            'subdomain' => 'recordco',
            'status' => 'active',
            'email' => 'admin@recordco.test',
            'timezone' => 'America/Chicago',
        ]);

        $user = User::query()->create([
            'name' => 'Recorder User',
            'email' => 'recorder@test.com',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $login = $this->postJson('/api/recorder/login', [
            'email' => 'recorder@test.com',
            'password' => 'password123',
            'company_subdomain' => 'recordco',
            'device_id' => 'device-1',
            'platform' => 'windows',
        ]);

        $token = $login->json('token');
        $today = Carbon::now('America/Chicago')->format('Y-m-d');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/recorder/time-tracking/time-in', [
                'date' => $today,
                'time' => '09:00:01',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('time_tracking_records', [
            'user_id' => $user->id,
            'date' => $today,
            'time_in' => '09:00:01',
            'status' => 'active',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/recorder/time-tracking/time-out', [
                'date' => $today,
                'time' => '17:00:00',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('time_tracking_records', [
            'user_id' => $user->id,
            'date' => $today,
            'time_out' => '17:00:00',
            'status' => 'completed',
        ]);

        $record = TimeTracking::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($record);
        $this->assertGreaterThan(0, (int) $record->hours_worked);
    }

    public function test_recorder_time_tracking_status_reflects_clock_in(): void
    {
        $company = Company::query()->create([
            'name' => 'Recorder Co',
            'subdomain' => 'recordco',
            'status' => 'active',
            'email' => 'admin@recordco.test',
            'timezone' => 'UTC',
        ]);

        $user = User::query()->create([
            'name' => 'Recorder User',
            'email' => 'recorder@test.com',
            'password' => Hash::make('password123'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $login = $this->postJson('/api/recorder/login', [
            'email' => 'recorder@test.com',
            'password' => 'password123',
            'company_subdomain' => 'recordco',
            'device_id' => 'device-1',
            'platform' => 'windows',
        ]);

        $token = $login->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/recorder/time-tracking/status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('clocked_in', false);

        TimeTracking::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'date' => now()->toDateString(),
            'time_in' => '09:00:00',
            'time_out' => null,
            'hours_worked' => null,
            'status' => 'active',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/recorder/time-tracking/status')
            ->assertOk()
            ->assertJsonPath('clocked_in', true)
            ->assertJsonPath('last_time_in.display', now()->format('Y-m-d').' 09:00:00');
    }
}
