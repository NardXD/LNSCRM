<?php

namespace App\Http\Controllers;

use App\Models\CalendarIntegration;
use App\Services\CalendarOauthSettingsService;
use App\Services\GoogleCalendarService;
use App\Services\OutlookCalendarService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class CalendarController extends Controller
{
    public function __construct(
        protected GoogleCalendarService $googleCalendar,
        protected OutlookCalendarService $outlookCalendar,
        protected CalendarOauthSettingsService $oauthSettings
    ) {}

    /**
     * Redirect user to Google OAuth for calendar access.
     */
    public function redirectGoogle(Request $request): RedirectResponse
    {
        $creds = $this->getGoogleCredentials();
        $this->ensureConfigured('google', $creds);

        config([
            'services.google.client_id' => $creds['client_id'],
            'services.google.client_secret' => $creds['client_secret'],
            'services.google.redirect' => $creds['redirect'],
        ]);

        return Socialite::driver('google')
            ->scopes([
                'https://www.googleapis.com/auth/calendar.readonly',
                'https://www.googleapis.com/auth/calendar.events.readonly',
                'https://www.googleapis.com/auth/userinfo.email',
            ])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    /**
     * Handle Google OAuth callback and store tokens.
     */
    public function callbackGoogle(Request $request): RedirectResponse
    {
        $creds = $this->getGoogleCredentials();
        $this->ensureConfigured('google', $creds);

        config([
            'services.google.client_id' => $creds['client_id'],
            'services.google.client_secret' => $creds['client_secret'],
            'services.google.redirect' => $creds['redirect'],
        ]);

        $user = Socialite::driver('google')->user();

        CalendarIntegration::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'provider' => CalendarIntegration::PROVIDER_GOOGLE,
            ],
            [
                'email' => $user->getEmail(),
                'access_token' => $user->token,
                'refresh_token' => $user->refreshToken ?? $this->getExistingRefreshToken(CalendarIntegration::PROVIDER_GOOGLE),
                'token_expires_at' => null,
                'is_active' => true,
            ]
        );

        return redirect()->route('calendar')->with('status', 'google-calendar-connected');
    }

    /**
     * Redirect user to Microsoft OAuth for Outlook calendar access.
     */
    public function redirectOutlook(Request $request): RedirectResponse
    {
        $creds = $this->getOutlookCredentials();
        $this->ensureConfigured('outlook', $creds);

        $tenant = $this->oauthSettings->getMicrosoftTenant(auth()->user()?->company_id);
        $clientId = $creds['client_id'];
        $redirectUri = $creds['redirect'];
        $scope = urlencode('openid profile email Calendars.Read User.Read offline_access');
        $state = encrypt(['user_id' => auth()->id()]);

        $url = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize?"
            .http_build_query([
                'client_id' => $clientId,
                'response_type' => 'code',
                'redirect_uri' => $redirectUri,
                'response_mode' => 'query',
                'scope' => $scope,
                'state' => $state,
                'prompt' => 'consent',
            ]);

        return redirect($url);
    }

    /**
     * Handle Microsoft OAuth callback and store tokens.
     */
    public function callbackOutlook(Request $request): RedirectResponse
    {
        $creds = $this->getOutlookCredentials();
        $this->ensureConfigured('outlook', $creds);

        if ($request->filled('error')) {
            Log::warning('Outlook OAuth error', ['error' => $request->input('error_description', $request->input('error'))]);

            return redirect()->route('calendar')->with('error', 'Could not connect Outlook calendar.');
        }

        $code = $request->input('code');
        if (! $code) {
            return redirect()->route('calendar')->with('error', 'Missing authorization code.');
        }

        $tenant = $this->oauthSettings->getMicrosoftTenant(auth()->user()?->company_id);
        $response = \Illuminate\Support\Facades\Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
            [
                'client_id' => $creds['client_id'],
                'client_secret' => $creds['client_secret'],
                'code' => $code,
                'redirect_uri' => $creds['redirect'],
                'grant_type' => 'authorization_code',
            ]
        );

        if (! $response->successful()) {
            Log::warning('Outlook token exchange failed', ['body' => $response->body()]);

            return redirect()->route('calendar')->with('error', 'Could not connect Outlook calendar.');
        }

        $data = $response->json();
        $accessToken = $data['access_token'];
        $refreshToken = $data['refresh_token'] ?? null;

        $userInfo = \Illuminate\Support\Facades\Http::withToken($accessToken)
            ->get('https://graph.microsoft.com/v1.0/me')
            ->json();
        $email = $userInfo['mail'] ?? $userInfo['userPrincipalName'] ?? null;

        CalendarIntegration::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'provider' => CalendarIntegration::PROVIDER_OUTLOOK,
            ],
            [
                'email' => $email,
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken ?? $this->getExistingRefreshToken(CalendarIntegration::PROVIDER_OUTLOOK),
                'token_expires_at' => isset($data['expires_in'])
                    ? now()->addSeconds($data['expires_in'])
                    : null,
                'is_active' => true,
            ]
        );

        return redirect()->route('calendar')->with('status', 'outlook-calendar-connected');
    }

    /**
     * Disconnect a calendar integration.
     */
    public function disconnect(Request $request): JsonResponse
    {
        $provider = $request->validate(['provider' => 'required|in:google,outlook'])['provider'];

        CalendarIntegration::where('user_id', auth()->id())
            ->where('provider', $provider)
            ->delete();

        return response()->json(['disconnected' => true]);
    }

    /**
     * Get calendar connection status and external events.
     */
    public function status(Request $request): JsonResponse
    {
        $integrations = CalendarIntegration::where('user_id', auth()->id())
            ->where('is_active', true)
            ->get()
            ->map(fn (CalendarIntegration $i) => [
                'provider' => $i->provider,
                'email' => $i->email,
            ]);

        return response()->json([
            'google' => $integrations->firstWhere('provider', 'google') ? true : false,
            'outlook' => $integrations->firstWhere('provider', 'outlook') ? true : false,
            'integrations' => $integrations->values(),
        ]);
    }

    /**
     * Get events from connected calendars (merged).
     */
    public function events(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
        ]);

        $start = Carbon::parse($validated['start']);
        $end = Carbon::parse($validated['end']);

        $events = [];

        $companyId = auth()->user()?->company_id;

        $integrations = CalendarIntegration::where('user_id', auth()->id())
            ->where('is_active', true)
            ->with('user')
            ->get();

        foreach ($integrations as $integration) {
            $creds = $this->oauthSettings->getCredentials($integration->provider, $companyId);
            if (empty($creds['client_id']) || empty($creds['client_secret'])) {
                continue;
            }

            if ($integration->provider === CalendarIntegration::PROVIDER_GOOGLE) {
                $events = array_merge(
                    $events,
                    $this->googleCalendar->getEvents($integration, $start, $end, $creds)
                );
            } elseif ($integration->provider === CalendarIntegration::PROVIDER_OUTLOOK) {
                $events = array_merge(
                    $events,
                    $this->outlookCalendar->getEvents($integration, $start, $end, $creds)
                );
            }
        }

        return response()->json(['events' => $events]);
    }

    /**
     * Get calendar OAuth settings (for configuration UI).
     */
    public function getOauthSettings(Request $request): JsonResponse
    {
        $companyId = auth()->user()?->company_id;

        return response()->json([
            'google_configured' => $this->oauthSettings->isConfigured('google', $companyId),
            'outlook_configured' => $this->oauthSettings->isConfigured('outlook', $companyId),
            'microsoft_tenant_id' => $this->oauthSettings->getMicrosoftTenant($companyId) === 'common'
                ? ''
                : $this->oauthSettings->getMicrosoftTenant($companyId),
            'redirect_url_google' => rtrim(config('app.url'), '/').'/calendar/connect/google/callback',
            'redirect_url_outlook' => rtrim(config('app.url'), '/').'/calendar/connect/outlook/callback',
            'redirect_url_outlook_mail' => rtrim(config('app.url'), '/').'/inbox/connect/outlook/callback',
        ]);
    }

    /**
     * Store calendar OAuth credentials (from UI).
     */
    public function storeOauthSettings(Request $request): JsonResponse
    {
        $companyId = auth()->user()?->company_id;

        $validated = $request->validate([
            'google_client_id' => ['nullable', 'string', 'max:500'],
            'google_client_secret' => ['nullable', 'string', 'max:500'],
            'microsoft_client_id' => ['nullable', 'string', 'max:500'],
            'microsoft_client_secret' => ['nullable', 'string', 'max:500'],
            'microsoft_tenant_id' => ['nullable', 'string', 'max:100'],
        ]);

        if (! empty($validated['google_client_id']) || ! empty($validated['google_client_secret'])) {
            $this->oauthSettings->storeCredentials('google', $companyId, $validated);
        }
        if (! empty($validated['microsoft_client_id']) || ! empty($validated['microsoft_client_secret']) || ! empty($validated['microsoft_tenant_id'])) {
            $this->oauthSettings->storeCredentials('outlook', $companyId, $validated);
        }

        return response()->json([
            'message' => 'OAuth settings saved.',
            'google_configured' => $this->oauthSettings->isConfigured('google', $companyId),
            'outlook_configured' => $this->oauthSettings->isConfigured('outlook', $companyId),
        ]);
    }

    private function getGoogleCredentials(): array
    {
        return $this->oauthSettings->getCredentials('google', auth()->user()?->company_id);
    }

    private function getOutlookCredentials(): array
    {
        return $this->oauthSettings->getCredentials('outlook', auth()->user()?->company_id);
    }

    private function ensureConfigured(string $provider, ?array $creds = null): void
    {
        $creds = $creds ?? ($provider === 'google' ? $this->getGoogleCredentials() : $this->getOutlookCredentials());

        if (empty($creds['client_id']) || empty($creds['client_secret'])) {
            abort(redirect()->route('calendar')->with('error', ucfirst($provider).' Calendar is not configured. Add OAuth credentials in Integrations.'));
        }
    }

    private function getExistingRefreshToken(string $provider): ?string
    {
        $existing = CalendarIntegration::where('user_id', auth()->id())
            ->where('provider', $provider)
            ->first();

        return $existing?->refresh_token;
    }
}
