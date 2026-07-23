<?php

namespace App\Services;

use App\Models\CalendarIntegration;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OutlookCalendarService
{
    public function __construct(
        protected CalendarOauthSettingsService $oauthSettings
    ) {}

    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';

    /**
     * @param  array{client_id: string, client_secret: string, redirect: string}|null  $credentials
     * @return array<int, array<string, mixed>>
     */
    public function getEvents(CalendarIntegration $integration, Carbon $start, Carbon $end, ?array $credentials = null): array
    {
        $creds = $credentials ?? $this->oauthSettings->getCredentials('outlook', $integration->user?->company_id);
        $integration = $this->refreshTokenIfNeeded($integration, $creds);

        $response = Http::withToken($integration->access_token)
            ->get(self::GRAPH_BASE.'/me/calendar/calendarView', [
                'startDateTime' => $start->toIso8601String(),
                'endDateTime' => $end->toIso8601String(),
                '$orderby' => 'start/dateTime',
                '$select' => 'id,subject,bodyPreview,start,end,location,isAllDay',
            ]);

        if (! $response->successful()) {
            Log::warning('Outlook Calendar API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $result = [];
        foreach ($response->json('value') ?? [] as $event) {
            $result[] = $this->mapEvent($event, 'outlook');
        }

        return $result;
    }

    /**
     * @param  array{client_id: string, client_secret: string, redirect: string}  $creds
     */
    public function refreshTokenIfNeeded(CalendarIntegration $integration, array $creds): CalendarIntegration
    {
        if (! $integration->needsRefresh() || ! $integration->refresh_token) {
            return $integration;
        }

        $tenant = config('services.microsoft.tenant', 'common');
        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
            [
                'client_id' => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'refresh_token' => $integration->refresh_token,
                'grant_type' => 'refresh_token',
            ]
        );

        if (! $response->successful()) {
            Log::warning('Outlook token refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $integration;
        }

        $data = $response->json();
        $integration->access_token = $data['access_token'];
        $integration->token_expires_at = now()->addSeconds($data['expires_in'] ?? 3600);
        if (! empty($data['refresh_token'])) {
            $integration->refresh_token = $data['refresh_token'];
        }
        $integration->save();

        return $integration;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function mapEvent(array $event, string $calendar): array
    {
        $start = $event['start'] ?? [];
        $end = $event['end'] ?? [];
        $isAllDay = ($event['isAllDay'] ?? false) || isset($start['date']);

        $startVal = $start['dateTime'] ?? $start['date'] ?? null;
        $endVal = $end['dateTime'] ?? $end['date'] ?? null;

        return [
            'id' => $event['id'] ?? null,
            'title' => $event['subject'] ?? '(No title)',
            'start' => $startVal,
            'end' => $endVal,
            'allDay' => $isAllDay,
            'calendar' => $calendar,
            'description' => $event['bodyPreview'] ?? null,
            'location' => $event['location']['displayName'] ?? null,
            'external' => true,
        ];
    }
}
