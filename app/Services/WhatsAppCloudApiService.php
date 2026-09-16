<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    public function subscribeWaba(string $wabaId, string $accessToken): void
    {
        $response = Http::timeout(30)
            ->withToken($accessToken)
            ->asForm()
            ->post($this->baseUrl.'/'.$wabaId.'/subscribed_apps');

        if (! $response->successful()) {
            Log::warning('WhatsApp WABA webhook subscription failed', [
                'waba_id' => $wabaId,
                'error' => $this->errorMessage($response, 'subscribe failed'),
            ]);
        }
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
