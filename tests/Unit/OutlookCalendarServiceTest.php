<?php

namespace Tests\Unit;

use App\Models\OutlookMailAccount;
use App\Services\OutlookCalendarService;
use App\Services\OutlookMailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class OutlookCalendarServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_maps_events_from_the_personal_outlook_account(): void
    {
        $account = new OutlookMailAccount([
            'id' => 1,
            'access_token' => 'personal-token',
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

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

        $mail = Mockery::mock(OutlookMailService::class);
        $mail->shouldReceive('refreshTokenIfNeeded')->once()->andReturn($account);

        $result = (new OutlookCalendarService($mail))->getEventsForMailAccount(
            $account,
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30')
        );

        $this->assertFalse($result['needs_reconnect']);
        $this->assertSame('Calendar', $result['calendars'][0]['name']);
        $this->assertSame('Team standup', $result['events'][0]['title']);
        $this->assertSame('outlook', $result['events'][0]['calendar']);
        $this->assertSame('cal-1', $result['events'][0]['calendarId']);
        $this->assertSame('Zoom', $result['events'][0]['location']);
        $this->assertTrue($result['events'][0]['external']);
    }

    public function test_asks_to_reconnect_when_calendar_scope_is_missing(): void
    {
        $account = new OutlookMailAccount([
            'id' => 2,
            'access_token' => 'personal-token',
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        Http::fake([
            'graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ErrorAccessDenied']], 403),
        ]);

        $mail = Mockery::mock(OutlookMailService::class);
        $mail->shouldReceive('refreshTokenIfNeeded')->once()->andReturn($account);

        $result = (new OutlookCalendarService($mail))->getEventsForMailAccount(
            $account,
            Carbon::parse('2026-09-01'),
            Carbon::parse('2026-09-30')
        );

        $this->assertTrue($result['needs_reconnect']);
        $this->assertSame([], $result['events']);
        $this->assertSame([], $result['calendars']);
    }

    public function test_creates_an_event_and_invites_attendees(): void
    {
        $account = $this->mailAccount(3);
        $recorded = null;

        Http::fake(function ($request) use (&$recorded) {
            if ($request->method() === 'POST' && str_contains($request->url(), '/events')) {
                $recorded = $request->data();

                return Http::response([
                    'id' => 'evt-created',
                    'subject' => 'Planning',
                    'bodyPreview' => 'Agenda',
                    'start' => ['dateTime' => '2026-09-16T01:00:00', 'timeZone' => 'UTC'],
                    'end' => ['dateTime' => '2026-09-16T02:00:00', 'timeZone' => 'UTC'],
                    'location' => ['displayName' => 'Room A'],
                    'isAllDay' => false,
                    'attendees' => [[
                        'emailAddress' => ['address' => 'teammate@example.com'],
                    ]],
                    'isReminderOn' => true,
                    'reminderMinutesBeforeStart' => 15,
                ], 201);
            }

            return Http::response(['error' => ['message' => 'unexpected']], 500);
        });

        $result = (new OutlookCalendarService($this->mailMock($account)))->createEvent($account, [
            'title' => 'Planning',
            'start' => '2026-09-16T01:00:00Z',
            'end' => '2026-09-16T02:00:00Z',
            'all_day' => false,
            'description' => 'Agenda',
            'location' => 'Room A',
            'calendar_id' => 'cal-1',
            'calendar_name' => 'Calendar',
            'attendees' => ['teammate@example.com', 'not-an-email'],
            'reminder' => '15',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('evt-created', $result['event']['id']);
        $this->assertSame(['teammate@example.com'], $result['event']['attendees']);
        $this->assertSame([[
            'emailAddress' => ['address' => 'teammate@example.com'],
            'type' => 'required',
        ]], $recorded['attendees']);
        $this->assertTrue($recorded['isReminderOn']);
        $this->assertSame(15, $recorded['reminderMinutesBeforeStart']);
    }

    public function test_updates_an_event(): void
    {
        $account = $this->mailAccount(4);

        Http::fake([
            'graph.microsoft.com/*' => Http::response([
                'id' => 'evt-1',
                'subject' => 'Updated standup',
                'start' => ['dateTime' => '2026-09-15T02:00:00', 'timeZone' => 'UTC'],
                'end' => ['dateTime' => '2026-09-15T02:30:00', 'timeZone' => 'UTC'],
                'isAllDay' => false,
            ], 200),
        ]);

        $result = (new OutlookCalendarService($this->mailMock($account)))->updateEvent($account, 'evt-1', [
            'title' => 'Updated standup',
            'start' => '2026-09-15T02:00:00Z',
            'end' => '2026-09-15T02:30:00Z',
            'calendar_id' => 'cal-1',
            'calendar_name' => 'Calendar',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('Updated standup', $result['event']['title']);
        Http::assertSent(fn ($request) => $request->method() === 'PATCH' && str_contains($request->url(), '/me/events/evt-1'));
    }

    public function test_deletes_an_event(): void
    {
        $account = $this->mailAccount(5);

        Http::fake([
            'graph.microsoft.com/*' => Http::response('', 204),
        ]);

        $result = (new OutlookCalendarService($this->mailMock($account)))->deleteEvent($account, 'evt-1');

        $this->assertTrue($result['ok']);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/me/events/evt-1'));
    }

    private function mailAccount(int $id): OutlookMailAccount
    {
        return new OutlookMailAccount([
            'id' => $id,
            'access_token' => 'personal-token',
            'token_expires_at' => now()->addHour(),
            'is_active' => true,
        ]);
    }

    private function mailMock(OutlookMailAccount $account)
    {
        $mail = Mockery::mock(OutlookMailService::class);
        $mail->shouldReceive('refreshTokenIfNeeded')->once()->andReturn($account);

        return $mail;
    }
}
