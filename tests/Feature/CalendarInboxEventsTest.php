<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\OutlookMailAccount;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SharedInbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarInboxEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_is_disconnected_without_personal_inbox_account(): void
    {
        [$user] = $this->userWithCalendarPermission();

        $this->actingAs($user)
            ->getJson('/api/calendar/status')
            ->assertOk()
            ->assertJson([
                'connected' => false,
                'email' => null,
                'needs_reconnect' => false,
            ]);
    }

    public function test_status_uses_personal_inbox_account_not_shared_mailbox(): void
    {
        [$user, $company] = $this->userWithCalendarPermission();

        $sharedAccount = OutlookMailAccount::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'email' => 'shared@example.com',
            'access_token' => 'shared-token',
            'is_active' => true,
        ]);
        SharedInbox::query()->create([
            'company_id' => $company->id,
            'outlook_mail_account_id' => $sharedAccount->id,
            'created_by' => $user->id,
            'name' => 'Talk2Us',
            'email' => 'shared@example.com',
            'type' => SharedInbox::TYPE_SHARED,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/api/calendar/status')
            ->assertOk()
            ->assertJson(['connected' => false]);

        $personalAccount = OutlookMailAccount::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'email' => 'me@example.com',
            'access_token' => 'personal-token',
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
        SharedInbox::query()->create([
            'company_id' => $company->id,
            'outlook_mail_account_id' => $personalAccount->id,
            'created_by' => $user->id,
            'name' => 'Personal',
            'email' => 'me@example.com',
            'type' => SharedInbox::TYPE_PERSONAL,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/api/calendar/status')
            ->assertOk()
            ->assertJson([
                'connected' => true,
                'email' => 'me@example.com',
            ]);
    }

    public function test_events_come_from_personal_outlook_account(): void
    {
        [$user, $company] = $this->userWithCalendarPermission();
        $this->connectPersonalInbox($user, $company);

        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, '/calendarView')) {
                return Http::response([
                    'value' => [[
                        'id' => 'evt-1',
                        'subject' => 'Team standup',
                        'bodyPreview' => 'Daily sync',
                        'start' => ['dateTime' => '2026-09-15T01:00:00', 'timeZone' => 'UTC'],
                        'end' => ['dateTime' => '2026-09-15T01:30:00', 'timeZone' => 'UTC'],
                        'location' => ['displayName' => 'Zoom'],
                        'isAllDay' => false,
                    ]],
                ], 200);
            }

            if (str_contains($url, '/me/calendars')) {
                return Http::response([
                    'value' => [[
                        'id' => 'cal-1',
                        'name' => 'Calendar',
                        'hexColor' => '#0078D4',
                        'isDefaultCalendar' => true,
                    ]],
                ], 200);
            }

            return Http::response(['value' => []], 200);
        });

        $payload = $this->actingAs($user)
            ->getJson('/api/calendar/events?start=2026-09-01&end=2026-09-30')
            ->assertOk()
            ->json();

        $this->assertFalse($payload['needs_reconnect']);
        $this->assertTrue($payload['connected']);
        $this->assertSame('Calendar', $payload['calendars'][0]['name']);
        $this->assertSame('Team standup', $payload['events'][0]['title']);
        $this->assertSame('outlook', $payload['events'][0]['calendar']);
        $this->assertSame('cal-1', $payload['events'][0]['calendarId']);
        $this->assertSame('Zoom', $payload['events'][0]['location']);
    }

    public function test_events_ask_to_reconnect_when_calendar_scope_is_missing(): void
    {
        [$user, $company] = $this->userWithCalendarPermission();
        $this->connectPersonalInbox($user, $company);

        Http::fake([
            'graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ErrorAccessDenied']], 403),
        ]);

        $this->actingAs($user)
            ->getJson('/api/calendar/events?start=2026-09-01&end=2026-09-30')
            ->assertOk()
            ->assertJson([
                'connected' => true,
                'needs_reconnect' => true,
                'events' => [],
            ]);
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function userWithCalendarPermission(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-calendar',
            'status' => 'active',
            'email' => 'admin-calendar@lns.test',
            'timezone' => 'UTC',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-calendar',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $permission = Permission::query()->create([
            'name' => 'view_calendar',
            'slug' => 'view_calendar',
            'display_name' => 'Calendar',
            'company_id' => $company->id,
        ]);
        $role->permissions()->attach($permission->id);

        $user = User::query()->create([
            'name' => 'Calendar User',
            'email' => 'calendar-user@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$user, $company];
    }

    private function connectPersonalInbox(User $user, Company $company): OutlookMailAccount
    {
        $account = OutlookMailAccount::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'email' => 'me@example.com',
            'access_token' => 'personal-token',
            'refresh_token' => 'refresh',
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        SharedInbox::query()->create([
            'company_id' => $company->id,
            'outlook_mail_account_id' => $account->id,
            'created_by' => $user->id,
            'name' => 'Personal',
            'email' => 'me@example.com',
            'type' => SharedInbox::TYPE_PERSONAL,
            'is_active' => true,
        ]);

        return $account;
    }
}
