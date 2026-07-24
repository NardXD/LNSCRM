<?php

namespace App\Services;

use App\Models\WhatsAppIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppCloudService
{
    public const API_VERSION = 'v21.0';

    public const API_BASE = 'https://graph.facebook.com/'.self::API_VERSION;

    public function __construct(
        protected string $accessToken,
        protected string $phoneNumberId,
        protected ?string $appSecret = null
    ) {}

    public static function forIntegration(WhatsAppIntegration $integration): self
    {
        $token = $integration->getDecryptedAccessToken();
        if (! $token) {
            throw new RuntimeException('WhatsApp access token is missing.');
        }

        if (! $integration->phone_number_id) {
            throw new RuntimeException('WhatsApp phone number ID is missing.');
        }

        return new self(
            $token,
            $integration->phone_number_id,
            $integration->getDecryptedAppSecret()
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getPhoneNumberInfo(): array
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(30)
            ->get(self::API_BASE.'/'.$this->phoneNumberId, [
                'fields' => 'display_phone_number,verified_name,quality_rating,code_verification_status',
            ]);

        $data = $response->json() ?? [];

        if (! $response->successful()) {
            $message = $data['error']['message'] ?? ('HTTP '.$response->status());
            Log::warning('WhatsApp phone number lookup failed', [
                'phone_number_id' => $this->phoneNumberId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('WhatsApp credentials could not be verified: '.$message);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendMessage(array $payload): array
    {
        $payload['messaging_product'] = 'whatsapp';

        return $this->post($this->phoneNumberId.'/messages', $payload);
    }

    public function sendText(string $to, string $text, bool $previewUrl = false): array
    {
        return $this->sendMessage([
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => $previewUrl,
                'body' => $text,
            ],
        ]);
    }

    public function sendImage(string $to, string $link, ?string $caption = null): array
    {
        return $this->sendMessage([
            'to' => $to,
            'type' => 'image',
            'image' => array_filter([
                'link' => $link,
                'caption' => $caption,
            ]),
        ]);
    }

    public function sendVideo(string $to, string $link, ?string $caption = null): array
    {
        return $this->sendMessage([
            'to' => $to,
            'type' => 'video',
            'video' => array_filter([
                'link' => $link,
                'caption' => $caption,
            ]),
        ]);
    }

    public function sendDocument(string $to, string $link, ?string $filename = null, ?string $caption = null): array
    {
        return $this->sendMessage([
            'to' => $to,
            'type' => 'document',
            'document' => array_filter([
                'link' => $link,
                'filename' => $filename,
                'caption' => $caption,
            ]),
        ]);
    }

    public function sendAudio(string $to, string $link): array
    {
        return $this->sendMessage([
            'to' => $to,
            'type' => 'audio',
            'audio' => ['link' => $link],
        ]);
    }

    public function sendLocation(string $to, float $lat, float $lon, ?string $name = null, ?string $address = null): array
    {
        return $this->sendMessage([
            'to' => $to,
            'type' => 'location',
            'location' => array_filter([
                'latitude' => $lat,
                'longitude' => $lon,
                'name' => $name,
                'address' => $address,
            ], fn ($v) => $v !== null && $v !== ''),
        ]);
    }

    /**
     * @return array{url: string, mime_type: ?string, sha256: ?string, file_size: ?int}
     */
    public function getMediaUrl(string $mediaId): array
    {
        $meta = $this->get(self::API_BASE.'/'.$mediaId);
        $url = $meta['url'] ?? null;
        if (! $url) {
            throw new RuntimeException('WhatsApp media URL missing.');
        }

        return [
            'url' => $url,
            'mime_type' => $meta['mime_type'] ?? null,
            'sha256' => $meta['sha256'] ?? null,
            'file_size' => isset($meta['file_size']) ? (int) $meta['file_size'] : null,
        ];
    }

    public function downloadMedia(string $mediaUrl): string
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(60)
            ->withHeaders(['Accept' => '*/*'])
            ->get($mediaUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to download WhatsApp media (HTTP '.$response->status().').');
        }

        return $response->body();
    }

    public function verifySignature(string $rawBody, ?string $signatureHeader): bool
    {
        if (! $this->appSecret || ! $signatureHeader) {
            // App secret optional — skip verification if not configured.
            return $this->appSecret === null || $this->appSecret === '';
        }

        if (! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $provided = substr($signatureHeader, 7);
        $expected = hash_hmac('sha256', $rawBody, $this->appSecret);

        return hash_equals($expected, $provided);
    }

    /**
     * @return array<string, mixed>
     */
    protected function get(string $url, array $query = []): array
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(30)
            ->get($url, $query);

        $data = $response->json() ?? [];

        if (! $response->successful()) {
            $message = $data['error']['message'] ?? ('HTTP '.$response->status());
            Log::warning('WhatsApp API GET error', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('WhatsApp API error: '.$message);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function post(string $endpoint, array $payload = []): array
    {
        $response = Http::withToken($this->accessToken)
            ->timeout(30)
            ->asJson()
            ->post(self::API_BASE.'/'.$endpoint, $payload);

        $data = $response->json() ?? [];

        if (! $response->successful()) {
            $message = $data['error']['message'] ?? ('HTTP '.$response->status());
            Log::warning('WhatsApp API POST error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('WhatsApp API error: '.$message);
        }

        return $data;
    }
}
