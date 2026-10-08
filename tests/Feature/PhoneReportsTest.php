<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\PhoneCallLog;
use App\Models\Role;
use App\Models\TwilioFlexIntegration;
use App\Models\User;
use App\Services\TwilioCompanyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class PhoneReportsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private int $sid = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00', 'Asia/Manila'));

        $this->company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-phone-reports',
            'status' => 'active',
            'email' => 'phone-reports@lns.test',
            'timezone' => 'Asia/Manila',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_page_defaults_to_first_of_month_through_today(): void
    {
        $manager = $this->makeUser('Manager', ['view_phone_reports', 'manage_twilio_numbers']);

        $page = $this->actingAs($manager)->get('/phone-system/reports');

        $page->assertOk();
        $page->assertSee('Phone Reports', false);
        $page->assertSee('value="2026-10-01"', false);
        $page->assertSee('value="2026-10-15"', false);
        $page->assertSee('Total call duration', false);
        $page->assertSee('Per-user summary', false);

        $summary = $this->actingAs($manager)->getJson('/api/phone-system/reports');
        $summary->assertOk();
        $summary->assertJsonPath('data.date_from', '2026-10-01');
        $summary->assertJsonPath('data.date_to', '2026-10-15');
        $this->assertCount(15, $summary->json('data.daily'));
    }

    public function test_requires_phone_reports_permission(): void
    {
        $user = $this->makeUser('No Access', ['view_phone_system']);

        $this->actingAs($user)->get('/phone-system/reports')->assertRedirect();
        $this->actingAs($user)->getJson('/api/phone-system/reports')->assertForbidden();
        $this->actingAs($user)->getJson('/api/phone-system/reports/calls')->assertForbidden();
    }

    public function test_summary_totals_per_user_and_date_range(): void
    {
        $manager = $this->makeUser('Manager', ['view_phone_reports', 'manage_twilio_numbers']);
        $alice = $this->makeUser('Alice Agent', ['view_phone_reports']);
        $bob = $this->makeUser('Bob Agent', ['view_phone_reports']);

        $this->logCall($alice, '2026-10-02 09:15:00', ['duration' => 120, 'recording' => 110]);
        $this->logCall($alice, '2026-10-02 14:30:00', ['duration' => 600, 'direction' => 'outbound-api', 'recording' => 590]);
        $this->logCall($alice, '2026-10-05 09:00:00', ['duration' => 0, 'status' => 'no-answer']);
        $this->logCall($bob, '2026-10-03 11:00:00', ['duration' => 1900, 'recording' => 1890]);
        $this->logCall(null, '2026-10-04 08:00:00', ['duration' => 30]);
        // Outside the range, and another company: never counted.
        $this->logCall($alice, '2026-09-30 23:59:59', ['duration' => 999]);
        $this->logCall($alice, '2026-10-06 00:00:00', ['duration' => 999]);
        $otherCompany = Company::query()->create([
            'name' => 'Other', 'subdomain' => 'other-phone', 'status' => 'active', 'email' => 'o@lns.test',
        ]);
        PhoneCallLog::query()->create([
            'company_id' => $otherCompany->id, 'call_sid' => 'CA-other', 'direction' => 'inbound',
            'status' => 'completed', 'duration' => 5000,
        ]);

        $response = $this->actingAs($manager)->getJson('/api/phone-system/reports?date_from=2026-10-01&date_to=2026-10-05');
        $response->assertOk();

        $response->assertJsonPath('data.totals.calls', 5);
        $response->assertJsonPath('data.totals.total_duration', 120 + 600 + 1900 + 30);
        $response->assertJsonPath('data.totals.connected_calls', 4);
        $response->assertJsonPath('data.totals.avg_duration', (int) round((120 + 600 + 1900 + 30) / 4));
        $response->assertJsonPath('data.totals.longest_duration', 1900);
        $response->assertJsonPath('data.totals.inbound', 4);
        $response->assertJsonPath('data.totals.outbound', 1);
        $response->assertJsonPath('data.totals.completed', 4);
        $response->assertJsonPath('data.totals.missed', 1);
        $response->assertJsonPath('data.totals.recordings', 3);
        $response->assertJsonPath('data.totals.recorded_duration', 110 + 590 + 1890);

        $perUser = collect($response->json('data.per_user'))->keyBy('name');
        $this->assertSame(['Bob Agent', 'Alice Agent', 'Unassigned'], collect($response->json('data.per_user'))->pluck('name')->all());
        $this->assertSame(3, $perUser['Alice Agent']['calls']);
        $this->assertSame(720, $perUser['Alice Agent']['total_duration']);
        $this->assertSame(1, $perUser['Alice Agent']['missed']);
        $this->assertSame(2, $perUser['Alice Agent']['recordings']);
        $this->assertSame(1900, $perUser['Bob Agent']['total_duration']);
        $this->assertNull($perUser['Unassigned']['user_id']);

        $daily = collect($response->json('data.daily'))->keyBy('date');
        $this->assertCount(5, $daily);
        $this->assertSame(2, $daily['2026-10-02']['calls']);
        $this->assertSame(720, $daily['2026-10-02']['duration']);
        $this->assertSame(1, $daily['2026-10-05']['missed']);

        $hourly = collect($response->json('data.hourly'))->keyBy('hour');
        $this->assertSame(2, $hourly[9]['calls']);

        $lengths = collect($response->json('data.length_buckets'))->pluck('count', 'label');
        $this->assertSame(1, $lengths['Under 1 min']);
        $this->assertSame(1, $lengths['1–5 min']);
        $this->assertSame(1, $lengths['5–15 min']);
        $this->assertSame(1, $lengths['30+ min']);

        // A single-user view.
        $single = $this->actingAs($manager)->getJson('/api/phone-system/reports?date_from=2026-10-01&date_to=2026-10-05&user_id='.$bob->id);
        $single->assertJsonPath('data.totals.calls', 1);
        $single->assertJsonPath('data.totals.total_duration', 1900);

        // Reversed dates are swapped rather than returning nothing.
        $reversed = $this->actingAs($manager)->getJson('/api/phone-system/reports?date_from=2026-10-05&date_to=2026-10-01');
        $reversed->assertJsonPath('data.date_from', '2026-10-01');
        $reversed->assertJsonPath('data.totals.calls', 5);
    }

    public function test_agents_only_see_their_own_calls(): void
    {
        $alice = $this->makeUser('Alice Agent', ['view_phone_reports']);
        $bob = $this->makeUser('Bob Agent', ['view_phone_reports']);

        $this->logCall($alice, '2026-10-02 09:00:00', ['duration' => 100]);
        $bobCall = $this->logCall($bob, '2026-10-02 10:00:00', ['duration' => 200, 'recording' => 190]);

        $summary = $this->actingAs($alice)->getJson('/api/phone-system/reports?user_id='.$bob->id);
        $summary->assertJsonPath('data.can_view_all', false);
        $summary->assertJsonPath('data.totals.calls', 1);
        $summary->assertJsonPath('data.totals.total_duration', 100);

        $calls = $this->actingAs($alice)->getJson('/api/phone-system/reports/calls?user_id=all');
        $calls->assertJsonPath('data.pagination.total', 1);
        $calls->assertJsonMissing(['name' => 'Bob Agent']);

        $this->actingAs($alice)->get('/api/phone-system/reports/calls/'.$bobCall->id.'/recording')->assertForbidden();

        $page = $this->actingAs($alice)->get('/phone-system/reports');
        $page->assertOk();
        $page->assertSee('Your summary', false);
        $page->assertDontSee('Bob Agent', false);
    }

    public function test_calls_list_filters_and_totals(): void
    {
        $manager = $this->makeUser('Manager', ['view_phone_reports', 'manage_twilio_numbers']);
        $alice = $this->makeUser('Alice Agent', ['view_phone_reports']);

        $recorded = $this->logCall($alice, '2026-10-02 09:00:00', ['duration' => 300, 'recording' => 290, 'from' => '+15551230001']);
        $this->logCall($alice, '2026-10-03 09:00:00', ['duration' => 45]);
        $this->logCall($alice, '2026-10-04 09:00:00', ['duration' => 60, 'recording' => 55, 'recording_status' => 'absent']);
        $this->logCall($alice, '2026-10-05 09:00:00', ['duration' => 0, 'status' => 'busy']);

        $all = $this->actingAs($manager)->getJson('/api/phone-system/reports/calls');
        $all->assertOk();
        $all->assertJsonPath('data.totals.calls', 4);
        $all->assertJsonPath('data.totals.total_duration', 405);
        $all->assertJsonPath('data.totals.recordings', 1);
        $all->assertJsonPath('data.totals.recorded_duration', 290);
        $all->assertJsonPath('data.data.0.occurred_at_label', 'Oct 5, 2026 9:00 AM');

        $recordings = $this->actingAs($manager)->getJson('/api/phone-system/reports/calls?recordings_only=1');
        $recordings->assertJsonPath('data.pagination.total', 1);
        $recordings->assertJsonPath('data.data.0.id', $recorded->id);
        $recordings->assertJsonPath('data.data.0.has_recording', true);
        $recordings->assertJsonPath('data.data.0.recording_url', route('api.phone-reports.recording', $recorded));

        $missed = $this->actingAs($manager)->getJson('/api/phone-system/reports/calls?outcome=missed');
        $missed->assertJsonPath('data.pagination.total', 1);

        $longest = $this->actingAs($manager)->getJson('/api/phone-system/reports/calls?sort=longest&per_page=2&page=1');
        $longest->assertJsonPath('data.data.0.duration', 300);
        $longest->assertJsonPath('data.pagination.last_page', 2);
        $longest->assertJsonPath('data.totals.calls', 4);

        $search = $this->actingAs($manager)->getJson('/api/phone-system/reports/calls?search='.urlencode('(555) 123-0001'));
        $search->assertJsonPath('data.pagination.total', 1);

        $this->actingAs($manager)->getJson('/api/phone-system/reports/calls?date_from=2026-02-30')->assertStatus(422);
        $this->actingAs($manager)->getJson('/api/phone-system/reports/calls?direction=sideways')->assertStatus(422);
    }

    public function test_csv_export_respects_filters(): void
    {
        $manager = $this->makeUser('Manager', ['view_phone_reports', 'manage_twilio_numbers']);
        $alice = $this->makeUser('=Alice', ['view_phone_reports']);

        $this->logCall($alice, '2026-10-02 09:00:00', ['duration' => 3725, 'recording' => 3700, 'from' => '+15551230001']);
        $this->logCall($alice, '2026-10-03 09:00:00', ['duration' => 10]);

        $response = $this->actingAs($manager)->get('/api/phone-system/reports/export?recordings_only=1');
        $response->assertOk();
        $this->assertStringContainsString('phone-report-2026-10-01-to-2026-10-15.csv', (string) $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('Duration (h:mm:ss)', $lines[0]);
        $this->assertStringContainsString("'=Alice", $lines[1]);
        $this->assertStringContainsString('+15551230001', $lines[1]);
        $this->assertStringContainsString('3725,1:02:05,Yes,3700,1:01:40', $lines[1]);
    }

    public function test_recording_streams_from_twilio_for_permitted_users(): void
    {
        $manager = $this->makeUser('Manager', ['view_phone_reports', 'manage_twilio_numbers']);
        $alice = $this->makeUser('Alice Agent', ['view_phone_reports']);
        $call = $this->logCall($alice, '2026-10-02 09:00:00', ['duration' => 60, 'recording' => 55]);

        $this->partialMock(TwilioCompanyService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getActiveIntegration')->andReturn(new TwilioFlexIntegration);
            $mock->shouldReceive('getCredentials')->andReturn(['sid' => 'AC123', 'token' => 'secret']);
        });
        Http::fake([
            'api.twilio.com/2010-04-01/Accounts/AC123/Recordings/*' => Http::response('MP3DATA', 200, ['Content-Type' => 'audio/mpeg']),
        ]);

        $inline = $this->actingAs($manager)->get('/api/phone-system/reports/calls/'.$call->id.'/recording');
        $inline->assertOk();
        $this->assertSame('MP3DATA', $inline->getContent());
        $this->assertStringStartsWith('inline;', (string) $inline->headers->get('Content-Disposition'));

        $download = $this->actingAs($alice)->get('/api/phone-system/reports/calls/'.$call->id.'/recording?download=1');
        $download->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $download->headers->get('Content-Disposition'));

        $otherCompany = Company::query()->create([
            'name' => 'Other', 'subdomain' => 'other-rec', 'status' => 'active', 'email' => 'r@lns.test',
        ]);
        $foreign = PhoneCallLog::query()->create([
            'company_id' => $otherCompany->id, 'call_sid' => 'CA-foreign', 'status' => 'completed',
            'recording_sid' => 'RE-foreign',
        ]);
        $this->actingAs($manager)->get('/api/phone-system/reports/calls/'.$foreign->id.'/recording')->assertNotFound();
    }

    public function test_migration_grants_permission_to_call_history_roles_and_admin(): void
    {
        $agent = $this->makeUser('Agent', ['view_call_history']);
        $outsider = $this->makeUser('Outsider', ['view_dashboard']);
        $adminRole = Role::query()->create([
            'name' => 'Administrator', 'slug' => 'admin', 'company_id' => $this->company->id, 'is_active' => true,
        ]);

        $migration = require database_path('migrations/2026_10_08_120000_add_view_phone_reports_permission.php');
        $migration->up();
        $migration->up(); // idempotent

        $permissions = Permission::query()->where('company_id', $this->company->id)->where('slug', 'view_phone_reports')->get();
        $this->assertCount(1, $permissions);
        $grantedRoleIds = \DB::table('role_permission')->where('permission_id', $permissions->first()->id)->pluck('role_id')->sort()->values()->all();

        $this->assertSame(collect([$agent->role_id, $adminRole->id])->sort()->values()->all(), $grantedRoleIds);
        $this->assertNotContains($outsider->role_id, $grantedRoleIds);
    }

    /**
     * @param  list<string>  $slugs
     */
    private function makeUser(string $name, array $slugs): User
    {
        $role = Role::query()->create([
            'name' => $name.' Role',
            'slug' => 'role-'.md5($name),
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);

        foreach ($slugs as $slug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $slug, 'company_id' => $this->company->id],
                ['name' => $slug, 'display_name' => $slug]
            );
            $role->permissions()->attach($permission->id);
        }

        return User::query()->create([
            'name' => $name,
            'email' => md5($name).'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $this->company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function logCall(?User $user, string $createdAt, array $overrides = []): PhoneCallLog
    {
        $this->sid++;
        $hasRecording = isset($overrides['recording']);

        $log = new PhoneCallLog([
            'company_id' => $this->company->id,
            'user_id' => $user?->id,
            'call_sid' => 'CA-test-'.$this->sid,
            'direction' => $overrides['direction'] ?? 'inbound',
            'from_number' => $overrides['from'] ?? '+1555000'.str_pad((string) $this->sid, 4, '0', STR_PAD_LEFT),
            'to_number' => '+15559990000',
            'status' => $overrides['status'] ?? 'completed',
            'duration' => $overrides['duration'] ?? 0,
            'started_at' => $createdAt,
            'recording_sid' => $hasRecording ? 'RE-test-'.$this->sid : null,
            'recording_status' => $hasRecording ? ($overrides['recording_status'] ?? 'completed') : null,
            'recording_duration' => $hasRecording ? $overrides['recording'] : null,
        ]);
        $log->created_at = $createdAt;
        $log->updated_at = $createdAt;
        $log->save();

        return $log;
    }
}
