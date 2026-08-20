<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookGraphMessagingService
{
    protected string $baseUrl = 'https://graph.facebook.com/v21.0';

    /**
     * @return array{id?: string, name?: string, instagram_business_account?: array{id?: string, username?: string}}
     */
    public function pageInfo(string $pageId, string $accessToken): array
    {
        $response = Http::timeout(30)->get($this->baseUrl.'/'.$pageId, [
            'fields' => 'id,name,instagram_business_account{id,username}',
            'access_token' => $accessToken,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorMessage($response, 'Could not verify the Facebook Page access token.'));
        }

        return $response->json() ?: [];
    }

    /**
     * @return array{valid: bool, type: ?string, expires_at: ?int, never_expires: bool, error: ?string}
     */
    public function inspectToken(string $accessToken): array
    {
        $response = Http::timeout(20)->get($this->baseUrl.'/debug_token', [
            'input_token' => $accessToken,
            'access_token' => $accessToken,
        ]);
        $payload = $response->json() ?: [];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $expiresAt = isset($data['expires_at']) ? (int) $data['expires_at'] : 0;
        $valid = (bool) ($data['is_valid'] ?? false);
        $error = $data['error']['message'] ?? $payload['error']['message'] ?? null;

        return [
            'valid' => $valid,
            'type' => isset($data['type']) && is_string($data['type']) ? strtoupper($data['type']) : null,
            'expires_at' => $expiresAt > 0 ? $expiresAt : null,
            'never_expires' => $valid && $expiresAt === 0,
            'error' => is_string($error) && $error !== '' ? $error : null,
        ];
    }

    public function expiredTokenMessage(): string
    {
        return 'Your Facebook Page Access Token expired. Open Integrations → Facebook, paste a new long-lived Page token (Graph API Explorer → User token with pages_messaging → switch the token dropdown to your Page), then Save and Sync again. A Page token from a long-lived User token does not expire.';
    }

    public function isMailboxPermissionError(?string $message): bool
    {
        $haystack = strtolower((string) $message);

        return $haystack !== '' && (
            str_contains($haystack, 'read_mailbox')
            || str_contains($haystack, '(#298)')
        );
    }

    public function mailboxPermissionMessage(): string
    {
        return 'Facebook treated this as a personal mailbox, not the Page inbox. The saved token is a User token. In Graph API Explorer, open the token dropdown, select your Page (not User Token), copy that Page token, paste it under Integrations → Facebook, Save, then Sync again.';
    }

    /**
     * @return array{id?: string, name?: string, username?: string, profile_pic?: string}
     */
    public function instagramUser(string $igsid, string $accessToken): array
    {
        $response = Http::timeout(20)->get($this->baseUrl.'/'.$igsid, [
            'fields' => 'id,name,username,profile_pic',
            'access_token' => $accessToken,
        ]);

        if (! $response->successful()) {
            Log::info('Instagram user lookup failed', ['igsid' => $igsid, 'error' => $this->errorMessage($response, 'lookup failed')]);

            return [];
        }

        return $response->json() ?: [];
    }

    /**
     * @return array{message_id: string, raw: array<string, mixed>}
     */
    public function send(
        string $pageId,
        string $accessToken,
        string $recipientId,
        string $type,
        ?string $text = null,
        ?string $mediaUrl = null
    ): array {
        $payload = [
            'recipient' => ['id' => $recipientId],
            'messaging_type' => 'RESPONSE',
            'access_token' => $accessToken,
        ];

        if ($type === 'text') {
            $payload['message'] = ['text' => (string) $text];
        } else {
            $attachmentType = match ($type) {
                'image' => 'image',
                'video' => 'video',
                'audio' => 'audio',
                default => 'file',
            };
            $payload['message'] = [
                'attachment' => [
                    'type' => $attachmentType,
                    'payload' => [
                        'url' => (string) $mediaUrl,
                        'is_reusable' => true,
                    ],
                ],
            ];
        }

        $response = Http::timeout(45)->asJson()->post($this->baseUrl.'/'.$pageId.'/messages', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorMessage($response, 'Could not send the Instagram message.'));
        }

        $json = $response->json() ?: [];
        $messageId = (string) ($json['message_id'] ?? $json['id'] ?? '');
        if ($messageId === '') {
            throw new \RuntimeException('Meta did not return a message id.');
        }

        return ['message_id' => $messageId, 'raw' => $json];
    }

    public function subscribePage(string $pageId, string $accessToken): void
    {
        $response = Http::timeout(30)->asForm()->post($this->baseUrl.'/'.$pageId.'/subscribed_apps', [
            'subscribed_fields' => 'messages,message_echoes,messaging_postbacks,messaging_optins,message_deliveries,messaging_referrals',
            'access_token' => $accessToken,
        ]);

        if (! $response->successful()) {
            Log::warning('Facebook Page webhook subscription failed', [
                'page_id' => $pageId,
                'error' => $this->errorMessage($response, 'subscribe failed'),
            ]);
        }
    }

    public function download(string $url, ?string $accessToken = null): string
    {
        $response = Http::timeout(60)->get($url);
        if (! $response->successful() && $accessToken) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $response = Http::timeout(60)->get($url.$separator.'access_token='.urlencode($accessToken));
        }

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to download Instagram media (HTTP '.$response->status().').');
        }

        return $response->body();
    }

    public function errorMessage(Response $response, string $fallback): string
    {
        $payload = $response->json();
        $message = is_array($payload) ? ($payload['error']['message'] ?? null) : null;

        return is_string($message) && $message !== '' ? $message : $fallback;
    }
}
