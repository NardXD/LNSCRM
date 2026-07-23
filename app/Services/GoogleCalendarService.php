<?php

namespace App\Services;

use App\Models\CalendarIntegration;
use Carbon\Carbon;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;

class GoogleCalendarService
{
    public function __construct(
        protected CalendarOauthSettingsService $oauthSettings
    ) {}

    /**
     * @param  array{client_id: string, client_secret: string, redirect: string}|null  $credentials
     * @return array<string, mixed>
     */
    public function getEvents(CalendarIntegration $integration, Carbon $start, Carbon $end, ?array $credentials = null): array
    {
        $creds = $credentials ?? $this->oauthSettings->getCredentials('google', $integration->user?->company_id);
        $integration = $this->refreshTokenIfNeeded($integration, $creds);
        $client = $this->createClient($integration, $creds);

        $service = new Calendar($client);
        $events = $service->events->listEvents('primary', [
            'timeMin' => $start->toRfc3339String(),
            'timeMax' => $end->toRfc3339String(),
            'singleEvents' => true,
            'orderBy' => 'startTime',
        ]);

        $result = [];
        /** @var Event $event */
        foreach ($events->getItems() as $event) {
            $result[] = $this->mapEvent($event, 'google');
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

        $client = new GoogleClient;
        $client->setClientId($creds['client_id']);
        $client->setClientSecret($creds['client_secret']);
        $client->setRedirectUri($creds['redirect']);
        $client->refreshToken($integration->refresh_token);

        $accessToken = $client->fetchAccessTokenWithRefreshToken($integration->refresh_token);

        if (isset($accessToken['error'])) {
            return $integration;
        }

        $integration->access_token = $accessToken['access_token'];
        $integration->token_expires_at = isset($accessToken['expires_in'])
            ? now()->addSeconds($accessToken['expires_in'])
            : null;
        $integration->save();

        return $integration;
    }

    /**
     * @param  array{client_id: string, client_secret: string, redirect: string}  $creds
     */
    public function createClient(CalendarIntegration $integration, array $creds): GoogleClient
    {
        $client = new GoogleClient;
        $client->setClientId($creds['client_id']);
        $client->setClientSecret($creds['client_secret']);
        $client->setRedirectUri($creds['redirect']);
        $client->setAccessToken([
            'access_token' => $integration->access_token,
            'expires_in' => $integration->token_expires_at
                ? max(0, $integration->token_expires_at->diffInSeconds(now()))
                : 3600,
        ]);

        if ($integration->refresh_token) {
            $client->setRefreshToken($integration->refresh_token);
        }

        $client->addScope(Calendar::CALENDAR);
        $client->addScope(Calendar::CALENDAR_EVENTS_READONLY);

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapEvent(Event $event, string $calendar): array
    {
        $start = $event->getStart();
        $end = $event->getEnd();
        $isAllDay = ! $start->getDateTime();

        return [
            'id' => $event->getId(),
            'title' => $event->getSummary() ?? '(No title)',
            'start' => $isAllDay
                ? $start->getDate()
                : Carbon::parse($start->getDateTime())->toIso8601String(),
            'end' => $isAllDay
                ? $end->getDate()
                : Carbon::parse($end->getDateTime())->toIso8601String(),
            'allDay' => $isAllDay,
            'calendar' => $calendar,
            'description' => $event->getDescription(),
            'location' => $event->getLocation(),
            'external' => true,
        ];
    }
}
