<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WhatsAppCloudApiService
{
    protected string $baseUrl = 'https://graph.facebook.com/v21.0';

    /**
     * @return array{id?: string, display_phone_number?: string, verified_name?: string, quality_rating?: string}
     */
    public function phoneNumberInfo(string $phoneNumberId, string $accessToken): array
    {
        $response = Http::timeout(30)
            ->withToken($accessToken)
            ->get($this->baseUrl.'/'.$phoneNumberId, [
                'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status,whatsapp_business_account{id}',
            ]);

        if (! $response->successful()) {
            $response = Http::timeout(30)
                ->withToken($accessToken)
                ->get($this->baseUrl.'/'.$phoneNumberId, [
                    'fields' => 'id,display_phone_number,verified_name,quality_rating,code_verification_status',
                ]);
        }

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorMessage($response, 'Could not verify the WhatsApp Cloud API token or Phone Number ID.'));
        }

        return $response->json() ?: [];
    }

    public function wabaIdForPhoneNumber(string $phoneNumberId, string $accessToken): ?string
    {
        $response = Http::timeout(30)
            ->withToken($accessToken)
            ->get($this->baseUrl.'/'.$phoneNumberId, [
                'fields' => 'whatsapp_business_account{id}',
            ]);

        $id = $response->json('whatsapp_business_account.id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Point Meta at this CRM so inbound customer messages are delivered.
     * Subscribe-only is not enough: without a callback URL, Cloud API never posts chats.
     */
    public function registerWebhooks(
        string $accessToken,
        string $callbackUrl,
        string $verifyToken,
        ?string $wabaId = null,
        ?string $phoneNumberId = null,
        ?string $appSecret = null
    ): void {
        $callbackUrl = trim($callbackUrl);
        $verifyToken = trim($verifyToken);
        $wabaId = $wabaId ? trim($wabaId) : '';
        $phoneNumberId = $phoneNumberId ? trim($phoneNumberId) : '';

        if ($callbackUrl === '' || $verifyToken === '') {
            throw new \RuntimeException('A WhatsApp webhook URL and verify token are required to receive inbound messages.');
        }

        $registeredCallback = false;
        $errors = [];

        if ($appSecret) {
            try {
                $this->subscribeAppCallback($accessToken, $appSecret, $callbackUrl, $verifyToken);
                $registeredCallback = true;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($wabaId !== '') {
            try {
                $this->subscribeWaba($wabaId, $accessToken);
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($phoneNumberId !== '') {
            try {
                $this->overridePhoneCallback($phoneNumberId, $accessToken, $callbackUrl, $verifyToken);
                $registeredCallback = true;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($wabaId !== '' && ! $registeredCallback) {
            try {
                $this->overrideWabaCallback($wabaId, $accessToken, $callbackUrl, $verifyToken);
                $registeredCallback = true;
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if ($registeredCallback) {
            return;
        }

        $detail = $errors !== [] ? implode(' ', array_unique($errors)) : 'Meta did not accept the webhook callback URL.';

        throw new \RuntimeException(
            $detail.' Paste the CRM Callback URL and Verify Token under Meta for Developers → WhatsApp → Configuration, then subscribe to messages.'
        );
    }

    public function subscribeWaba(string $wabaId, string $accessToken): void
    {
        $response = Http::timeout(30)
            ->withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl.'/'.$wabaId.'/subscribed_apps');

        if (! $response->successful()) {
            $response = Http::timeout(30)
                ->withToken($accessToken)
                ->asForm()
                ->post($this->baseUrl.'/'.$wabaId.'/subscribed_apps');
        }

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorMessage($response, 'Could not subscribe the WhatsApp Business Account to this app.'));
        }
    }

    protected function overrideWabaCallback(string $wabaId, string $accessToken, string $callbackUrl, string $verifyToken): void
    {
        $response = Http::timeout(30)
            ->withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl.'/'.$wabaId.'/subscribed_apps', [
                'override_callback_uri' => $callbackUrl,
                'verify_token' => $verifyToken,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorMessage($response, 'Could not set the WhatsApp webhook callback URL on the WABA.'));
        }
    }

    protected function overridePhoneCallback(string $phoneNumberId, string $accessToken, string $callbackUrl, string $verifyToken): void
    {
        $response = Http::timeout(30)
            ->withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl.'/'.$phoneNumberId, [
                'webhook_configuration' => [
                    'override_callback_uri' => $callbackUrl,
                    'verify_token' => $verifyToken,
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorMessage($response, 'Could not set the WhatsApp webhook callback URL on the phone number.'));
        }
    }

    protected function subscribeAppCallback(string $accessToken, string $appSecret, string $callbackUrl, string $verifyToken): void
    {
        $appId = $this->appIdFromToken($accessToken);
        if (! $appId) {
            throw new \RuntimeException('Could not resolve the Meta app ID from the access token.');
        }

        $response = Http::timeout(30)
            ->asForm()
            ->post($this->baseUrl.'/'.$appId.'/subscriptions', [
                'object' => 'whatsapp_business_account',
                'callback_url' => $callbackUrl,
                'verify_token' => $verifyToken,
                'fields' => 'messages',
                'include_values' => 'true',
                'access_token' => $appId.'|'.$appSecret,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorMessage($response, 'Could not register the WhatsApp webhook on the Meta app.'));
        }
    }

    protected function appIdFromToken(string $accessToken): ?string
    {
        $response = Http::timeout(20)
            ->get($this->baseUrl.'/debug_token', [
                'input_token' => $accessToken,
                'access_token' => $accessToken,
            ]);

        $appId = $response->json('data.app_id');
        if (is_string($appId) && $appId !== '') {
            return $appId;
        }
        if (is_numeric($appId)) {
            return (string) $appId;
        }

        return null;
    }

    /**
     * @return array{wamid: string, status: string, raw: array<string, mixed>}
     */
    public function send(
        string $phoneNumberId,
        string $accessToken,
        string $to,
        string $type,
        ?string $text = null,
        ?string $mediaUrl = null,
        ?string $fileName = null,
        ?float $latitude = null,
        ?float $longitude = null
    ): array {
        $toDigits = $this->recipientNumber($to);
        if ($toDigits === '') {
            throw new \RuntimeException('A WhatsApp recipient phone number is required.');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toDigits,
        ];

        if ($type === 'text') {
            $body = (string) $text;
            if ($body === '') {
                throw new \RuntimeException('Message text is required.');
            }
            $payload['type'] = 'text';
            $payload['text'] = ['preview_url' => true, 'body' => $body];
        } elseif ($type === 'location') {
            if ($latitude === null || $longitude === null) {
                throw new \RuntimeException('Latitude and longitude are required.');
            }
            $payload['type'] = 'location';
            $payload['location'] = [
                'latitude' => (string) $latitude,
                'longitude' => (string) $longitude,
            ];
            if (is_string($text) && $text !== '') {
                $payload['location']['name'] = $text;
            }
        } else {
            if (! $mediaUrl) {
                throw new \RuntimeException('A media URL is required.');
            }
            $mediaType = match ($type) {
                'image', 'sticker' => 'image',
                'video' => 'video',
                'audio' => 'audio',
                default => 'document',
            };
            $payload['type'] = $mediaType;
            $media = ['link' => $mediaUrl];
            if (is_string($text) && $text !== '' && in_array($mediaType, ['image', 'video', 'document'], true)) {
                $media['caption'] = $text;
            }
            if ($mediaType === 'document' && $fileName) {
                $media['filename'] = $fileName;
            }
            $payload[$mediaType] = $media;
        }

        $response = Http::timeout(45)
            ->withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl.'/'.$phoneNumberId.'/messages', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException($this->friendlySendError($response));
        }

        $json = $response->json() ?: [];
        $wamid = (string) ($json['messages'][0]['id'] ?? '');
        if ($wamid === '') {
            throw new \RuntimeException('Meta did not return a WhatsApp message id.');
        }

        return [
            'wamid' => $wamid,
            'status' => 'sent',
            'raw' => $json,
        ];
    }

    /**
     * @return array{binary: string, mime_type: ?string, file_size: ?int}
     */
    public function downloadMedia(string $mediaId, string $accessToken): array
    {
        $meta = Http::timeout(30)
            ->withToken($accessToken)
            ->get($this->baseUrl.'/'.$mediaId);

        if (! $meta->successful()) {
            throw new \RuntimeException($this->errorMessage($meta, 'Could not resolve WhatsApp media.'));
        }

        $json = $meta->json() ?: [];
        $url = is_string($json['url'] ?? null) ? $json['url'] : '';
        if ($url === '') {
            throw new \RuntimeException('Meta did not return a WhatsApp media URL.');
        }

        $file = Http::timeout(60)->withToken($accessToken)->get($url);
        if (! $file->successful()) {
            throw new \RuntimeException('Failed to download WhatsApp media (HTTP '.$file->status().').');
        }

        return [
            'binary' => $file->body(),
            'mime_type' => is_string($json['mime_type'] ?? null) ? $json['mime_type'] : $file->header('Content-Type'),
            'file_size' => isset($json['file_size']) ? (int) $json['file_size'] : strlen($file->body()),
        ];
    }

    public function recipientNumber(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    public function expiredTokenMessage(): string
    {
        return 'Your WhatsApp Cloud API access token expired. Open Integrations → WhatsApp Business, paste a permanent System User token from Meta for Developers → WhatsApp → API Setup (or Business Settings → System Users), then Save.';
    }

    public function isExpiredTokenError(?string $message): bool
    {
        $haystack = strtolower((string) $message);

        return $haystack !== '' && (
            str_contains($haystack, 'session has expired')
            || str_contains($haystack, 'access token has expired')
            || str_contains($haystack, 'error validating access token')
        );
    }

    public static function sanitizeGraphError(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return $message;
        }

        return preg_replace('/access_token=[^&\s"]+/i', 'access_token=REDACTED', $message) ?? $message;
    }

    public function errorMessage(Response $response, string $fallback): string
    {
        $payload = $response->json();
        $message = is_array($payload) ? ($payload['error']['message'] ?? null) : null;
        $code = is_array($payload) ? ($payload['error']['code'] ?? null) : null;
        $text = is_string($message) && $message !== '' ? $message : $fallback;

        if ((int) $code === 190 || $this->isExpiredTokenError($text)) {
            return $this->expiredTokenMessage();
        }

        return $text;
    }

    protected function friendlySendError(Response $response): string
    {
        $payload = $response->json();
        $code = is_array($payload) ? (int) ($payload['error']['code'] ?? 0) : 0;
        $subcode = is_array($payload) ? (int) ($payload['error']['error_subcode'] ?? ($payload['error']['error_data']['details'] ?? 0)) : 0;
        $raw = $this->errorMessage($response, 'Could not send the WhatsApp message.');

        if ($code === 131047 || $subcode === 131047 || str_contains(strtolower($raw), '24 hour')) {
            return 'WhatsApp only allows free-form replies within 24 hours of the customer’s last message. Wait for them to message again, or send an approved template from Meta.';
        }

        if ($code === 131026) {
            return 'That phone number is not on WhatsApp, or it cannot receive messages from this business number.';
        }

        return $raw;
    }
}
