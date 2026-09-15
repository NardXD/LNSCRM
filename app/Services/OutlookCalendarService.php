<?php

namespace App\Services;

use App\Models\OutlookMailAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OutlookCalendarService
{
    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    private const MAX_PAGES_PER_CALENDAR = 10;

    public function __construct(
        protected OutlookMailService $mailService
    ) {}

    /**
     * @return array{calendars: array<int, array<string, mixed>>, events: array<int, array<string, mixed>>, needs_reconnect: bool}
     */
    public function getEventsForMailAccount(OutlookMailAccount $account, Carbon $start, Carbon $end): array
    {
        $account = $this->mailService->refreshTokenIfNeeded($account);

        $calendarsResponse = Http::withToken($account->access_token)
            ->acceptJson()
            ->get(self::GRAPH_BASE.'/me/calendars', [
                '$select' => 'id,name,hexColor,color,isDefaultCalendar',
                '$top' => 50,
            ]);

        if ($this->isAuthFailure($calendarsResponse->status(), $calendarsResponse->json('error.code'), true)) {
            Log::warning('Outlook Calendar list forbidden or unauthorized', [
                'account_id' => $account->id,
                'status' => $calendarsResponse->status(),
                'body' => $calendarsResponse->body(),
            ]);

            return ['calendars' => [], 'events' => [], 'needs_reconnect' => true];
        }

        if (! $calendarsResponse->successful()) {
            Log::warning('Outlook Calendar list failed', [
                'account_id' => $account->id,
                'status' => $calendarsResponse->status(),
                'body' => $calendarsResponse->body(),
            ]);

            return ['calendars' => [], 'events' => [], 'needs_reconnect' => false];
        }

        $calendars = [];
        $events = [];

        foreach ($calendarsResponse->json('value') ?? [] as $calendar) {
            $calendarId = (string) ($calendar['id'] ?? '');
            if ($calendarId === '') {
                continue;
            }

            $mappedCalendar = [
                'id' => $calendarId,
                'name' => $calendar['name'] ?? 'Calendar',
                'color' => $this->calendarColor($calendar),
                'isDefault' => (bool) ($calendar['isDefaultCalendar'] ?? false),
            ];
            $calendars[] = $mappedCalendar;

            $page = $this->fetchCalendarView($account, $calendarId, $mappedCalendar, $start, $end, $account->email);
            if ($page['needs_reconnect']) {
                return ['calendars' => [], 'events' => [], 'needs_reconnect' => true];
            }

            $events = array_merge($events, $page['events']);
        }

        return [
            'calendars' => $calendars,
            'events' => $events,
            'needs_reconnect' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, event: ?array<string, mixed>, needs_reconnect: bool, error: ?string, status: int}
     */
    public function createEvent(OutlookMailAccount $account, array $data): array
    {
        $account = $this->mailService->refreshTokenIfNeeded($account);
        $calendarId = (string) ($data['calendar_id'] ?? '');
        if ($calendarId === '') {
            return $this->writeError('Choose a calendar.', 422);
        }

        $response = Http::withToken($account->access_token)
            ->acceptJson()
            ->withHeaders(['Prefer' => 'outlook.timezone="UTC"'])
            ->post(self::GRAPH_BASE.'/me/calendars/'.rawurlencode($calendarId).'/events', $this->graphEventBody($data));

        return $this->writeResponse($response, $account, $data, $calendarId);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, event: ?array<string, mixed>, needs_reconnect: bool, error: ?string, status: int}
     */
    public function updateEvent(OutlookMailAccount $account, string $eventId, array $data): array
    {
        $account = $this->mailService->refreshTokenIfNeeded($account);
        $eventId = trim($eventId);
        if ($eventId === '') {
            return $this->writeError('Missing event.', 422);
        }

        $guard = $this->requireOrganizer($account, $eventId);
        if (! $guard['ok']) {
            return $guard;
        }

        $response = Http::withToken($account->access_token)
            ->acceptJson()
            ->withHeaders(['Prefer' => 'outlook.timezone="UTC"'])
            ->patch(
                self::GRAPH_BASE.'/me/events/'.rawurlencode($eventId).'?sendUpdates=all',
                $this->graphEventBody($data)
            );

        return $this->writeResponse($response, $account, $data, (string) ($data['calendar_id'] ?? ''));
    }

    /**
     * @return array{ok: bool, event: ?array<string, mixed>, needs_reconnect: bool, error: ?string, status: int}
     */
    public function deleteEvent(OutlookMailAccount $account, string $eventId): array
    {
        $account = $this->mailService->refreshTokenIfNeeded($account);
        $eventId = trim($eventId);
        if ($eventId === '') {
            return $this->writeError('Missing event.', 422);
        }

        $guard = $this->requireOrganizer($account, $eventId);
        if (! $guard['ok']) {
            return $guard;
        }

        $response = Http::withToken($account->access_token)
            ->acceptJson()
            ->delete(self::GRAPH_BASE.'/me/events/'.rawurlencode($eventId).'?sendUpdates=all');

        if ($this->isAuthFailure($response->status(), $response->json('error.code'))) {
            return $this->writeError('Reconnect Personal MS365 in Inbox to grant calendar edit access.', 403, true);
        }

        if (! $response->successful() && $response->status() !== 204) {
            Log::warning('Outlook Calendar delete failed', [
                'account_id' => $account->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->writeError($this->graphErrorMessage($response) ?? 'Could not delete this event.', $response->status() ?: 502);
        }

        return ['ok' => true, 'event' => null, 'needs_reconnect' => false, 'error' => null, 'status' => 200];
    }

    /**
     * @param  array{id: string, name: string, color: string, isDefault: bool}  $mappedCalendar
     * @return array{events: array<int, array<string, mixed>>, needs_reconnect: bool}
     */
    private function fetchCalendarView(
        OutlookMailAccount $account,
        string $calendarId,
        array $mappedCalendar,
        Carbon $start,
        Carbon $end,
        ?string $accountEmail = null
    ): array {
        $url = self::GRAPH_BASE.'/me/calendars/'.rawurlencode($calendarId).'/calendarView';
        $query = [
            'startDateTime' => $start->utc()->toIso8601String(),
            'endDateTime' => $end->utc()->toIso8601String(),
            '$orderby' => 'start/dateTime',
            '$select' => 'id,subject,bodyPreview,start,end,location,isAllDay,attendees,isReminderOn,reminderMinutesBeforeStart,isOrganizer,organizer,isOnlineMeeting,onlineMeeting,onlineMeetingUrl',
            '$top' => 100,
        ];

        $events = [];

        for ($page = 0; $page < self::MAX_PAGES_PER_CALENDAR; $page++) {
            $response = Http::withToken($account->access_token)
                ->acceptJson()
                ->withHeaders(['Prefer' => 'outlook.timezone="UTC"'])
                ->get($url, $query);

            if ($this->isAuthFailure($response->status(), $response->json('error.code'), true)) {
                return ['events' => [], 'needs_reconnect' => true];
            }

            if (! $response->successful()) {
                Log::warning('Outlook Calendar API error', [
                    'account_id' => $account->id,
                    'calendar_id' => $calendarId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['events' => $events, 'needs_reconnect' => false];
            }

            foreach ($response->json('value') ?? [] as $event) {
                $events[] = $this->mapEvent($event, $mappedCalendar, $accountEmail);
            }

            $next = $response->json('@odata.nextLink');
            if (! is_string($next) || $next === '') {
                break;
            }

            $url = $next;
            $query = [];
        }

        return ['events' => $events, 'needs_reconnect' => false];
    }

    /**
     * @param  array<string, mixed>  $calendar
     */
    private function calendarColor(array $calendar): string
    {
        $hex = $calendar['hexColor'] ?? '';
        if (is_string($hex) && preg_match('/^#?[0-9A-Fa-f]{6}$/', $hex)) {
            return str_starts_with($hex, '#') ? $hex : '#'.$hex;
        }

        return '#0078D4';
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array{id: string, name: string, color: string, isDefault: bool}  $calendar
     * @return array<string, mixed>
     */
    private function mapEvent(array $event, array $calendar, ?string $accountEmail = null, bool $createdByUs = false): array
    {
        $start = $event['start'] ?? [];
        $end = $event['end'] ?? [];
        $isAllDay = (bool) ($event['isAllDay'] ?? false);

        $attendees = [];
        foreach ($event['attendees'] ?? [] as $attendee) {
            $email = $attendee['emailAddress']['address'] ?? null;
            if (is_string($email) && $email !== '') {
                $attendees[] = $email;
            }
        }

        $reminderOn = (bool) ($event['isReminderOn'] ?? false);
        $joinUrl = $this->joinUrl($event);

        return [
            'id' => $event['id'] ?? null,
            'title' => $event['subject'] ?? '(No title)',
            'start' => $this->normalizeDateTime($start),
            'end' => $this->normalizeDateTime($end),
            'allDay' => $isAllDay,
            'calendar' => 'outlook',
            'calendarId' => $calendar['id'],
            'calendarName' => $calendar['name'],
            'color' => $calendar['color'],
            'description' => $event['bodyPreview'] ?? null,
            'location' => $event['location']['displayName'] ?? null,
            'attendees' => $attendees,
            'reminder' => $reminderOn ? (string) ($event['reminderMinutesBeforeStart'] ?? 15) : 'none',
            'external' => true,
            'isOrganizer' => $createdByUs || $this->eventIsOrganizer($event, $accountEmail),
            'isOnlineMeeting' => (bool) ($event['isOnlineMeeting'] ?? false) || $joinUrl !== null,
            'joinUrl' => $joinUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function joinUrl(array $event): ?string
    {
        $candidates = [
            $event['onlineMeeting']['joinUrl'] ?? null,
            $event['onlineMeeting']['joinWebUrl'] ?? null,
            $event['onlineMeetingUrl'] ?? null,
        ];

        foreach ($candidates as $url) {
            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function eventIsOrganizer(array $event, ?string $accountEmail): bool
    {
        if (array_key_exists('isOrganizer', $event)) {
            return (bool) $event['isOrganizer'];
        }

        $organizer = strtolower((string) ($event['organizer']['emailAddress']['address'] ?? ''));
        $mine = strtolower(trim((string) $accountEmail));

        return $organizer !== '' && $mine !== '' && $organizer === $mine;
    }

    /**
     * @return array{ok: bool, event: ?array<string, mixed>, needs_reconnect: bool, error: ?string, status: int}
     */
    private function requireOrganizer(OutlookMailAccount $account, string $eventId): array
    {
        $response = Http::withToken($account->access_token)
            ->acceptJson()
            ->get(self::GRAPH_BASE.'/me/events/'.rawurlencode($eventId), [
                '$select' => 'id,isOrganizer,organizer',
            ]);

        if ($this->isAuthFailure($response->status(), $response->json('error.code'))) {
            return $this->writeError('Reconnect Personal MS365 in Inbox to grant calendar edit access.', 403, true);
        }

        if (! $response->successful()) {
            return $this->writeError($this->graphErrorMessage($response) ?? 'Could not load this event.', $response->status() ?: 502);
        }

        if (! $this->eventIsOrganizer($response->json() ?? [], $account->email)) {
            return $this->writeError('Only the organizer can change this meeting.', 403);
        }

        return ['ok' => true, 'event' => null, 'needs_reconnect' => false, 'error' => null, 'status' => 200];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function graphEventBody(array $data): array
    {
        $allDay = (bool) ($data['all_day'] ?? false);
        $timezone = $this->safeTimezone((string) ($data['timezone'] ?? 'UTC'));

        if ($allDay) {
            $start = Carbon::parse($data['start'], $timezone)->startOfDay();
            $end = Carbon::parse($data['end'], $timezone)->startOfDay();
            if ($end->lessThanOrEqualTo($start)) {
                $end = $start->copy()->addDay();
            } else {
                $end = $end->addDay();
            }
        } else {
            $start = Carbon::parse($data['start'])->utc();
            $end = Carbon::parse($data['end'])->utc();
            $timezone = 'UTC';
        }

        $attendees = $data['attendees'] ?? [];
        if (is_string($attendees)) {
            $attendees = preg_split('/[\s,;]+/', $attendees, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $body = [
            'subject' => $data['title'],
            'body' => [
                'contentType' => 'text',
                'content' => (string) ($data['description'] ?? ''),
            ],
            'start' => [
                'dateTime' => $allDay ? $start->toDateString().'T00:00:00' : $start->format('Y-m-d\TH:i:s'),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => $allDay ? $end->toDateString().'T00:00:00' : $end->format('Y-m-d\TH:i:s'),
                'timeZone' => $timezone,
            ],
            'isAllDay' => $allDay,
            'location' => [
                'displayName' => (string) ($data['location'] ?? ''),
            ],
            'attendees' => collect($attendees)
                ->map(fn ($email) => trim((string) $email))
                ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
                ->unique()
                ->values()
                ->map(fn ($email) => [
                    'emailAddress' => ['address' => $email],
                    'type' => 'required',
                ])
                ->all(),
        ];

        $reminder = $data['reminder'] ?? 'none';
        if ($reminder === null || $reminder === '' || $reminder === 'none') {
            $body['isReminderOn'] = false;
        } else {
            $body['isReminderOn'] = true;
            $body['reminderMinutesBeforeStart'] = (int) $reminder;
        }

        if (! $allDay && ($data['teams_meeting'] ?? false)) {
            $body['isOnlineMeeting'] = true;
            $body['onlineMeetingProvider'] = 'teamsForBusiness';
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, event: ?array<string, mixed>, needs_reconnect: bool, error: ?string, status: int}
     */
    private function writeResponse($response, OutlookMailAccount $account, array $data, string $calendarId): array
    {
        if ($this->isAuthFailure($response->status(), $response->json('error.code'))) {
            return $this->writeError('Reconnect Personal MS365 in Inbox to grant calendar edit access.', 403, true);
        }

        if (! $response->successful()) {
            Log::warning('Outlook Calendar write failed', [
                'account_id' => $account->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->writeError($this->graphErrorMessage($response) ?? 'Could not save this event.', $response->status() ?: 502);
        }

        $calendar = [
            'id' => $calendarId !== '' ? $calendarId : (string) ($response->json('id') ?? ''),
            'name' => (string) ($data['calendar_name'] ?? 'Calendar'),
            'color' => (string) ($data['calendar_color'] ?? '#0078D4'),
            'isDefault' => false,
        ];

        return [
            'ok' => true,
            'event' => $this->mapEvent($response->json() ?? [], $calendar, $account->email, true),
            'needs_reconnect' => false,
            'error' => null,
            'status' => 200,
        ];
    }

    /**
     * @return array{ok: bool, event: ?array<string, mixed>, needs_reconnect: bool, error: ?string, status: int}
     */
    private function writeError(string $message, int $status, bool $needsReconnect = false): array
    {
        return [
            'ok' => false,
            'event' => null,
            'needs_reconnect' => $needsReconnect,
            'error' => $message,
            'status' => $status,
        ];
    }

    private function graphErrorMessage($response): ?string
    {
        $message = $response->json('error.message');

        return is_string($message) && $message !== '' ? $message : null;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function normalizeDateTime(array $value): ?string
    {
        $raw = $value['dateTime'] ?? $value['date'] ?? null;
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw, 'UTC')->toIso8601String();
        } catch (\Throwable) {
            $trimmed = preg_replace('/\.\d+/', '', $raw) ?: $raw;
            try {
                return Carbon::parse($trimmed, 'UTC')->toIso8601String();
            } catch (\Throwable) {
                return $raw;
            }
        }
    }

    private function safeTimezone(string $timezone): string
    {
        $timezone = trim($timezone);
        if ($timezone === '') {
            return 'UTC';
        }

        try {
            new \DateTimeZone($timezone);

            return $timezone;
        } catch (\Throwable) {
            return 'UTC';
        }
    }

    private function isAuthFailure(int $status, mixed $errorCode = null, bool $anyForbidden = false): bool
    {
        if ($status === 401) {
            return true;
        }

        if ($status !== 403) {
            return false;
        }

        if ($anyForbidden) {
            return true;
        }

        return in_array($errorCode, ['ErrorAccessDenied', 'Authorization_RequestDenied'], true);
    }
}
