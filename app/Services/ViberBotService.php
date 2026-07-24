<?php

namespace App\Services;

use App\Models\ViberIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ViberBotService
{
    public const API_BASE = 'https://chatapi.viber.com/pa';

    public function __construct(
        protected string $authToken
    ) {}

    public static function forIntegration(ViberIntegration $integration): self
    {
        $token = $integration->getDecryptedAuthToken();
        if (! $token) {
            throw new RuntimeException('Viber auth token is missing.');
        }

        return new self($token);
    }

    public function getAccountInfo(): array
    {
        return $this->post('get_account_info');
    }

    public function setWebhook(string $url, ?array $eventTypes = null): array
    {
        $payload = [
            'url' => $url,
            'send_name' => true,
            'send_photo' => true,
        ];

        if ($eventTypes !== null) {
            $payload['event_types'] = $eventTypes;
        }

        return $this->post('set_webhook', $payload);
    }

    public function removeWebhook(): array
    {
        return $this->post('set_webhook', ['url' => '']);
    }

    public function getOnlineStatus(array $userIds): array
    {
        return $this->post('get_online', ['ids' => array_values($userIds)]);
    }

    public function getUserDetails(string $userId): array
    {
        return $this->post('get_user_details', ['id' => $userId]);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public function sendMessage(string $receiverId, array $message, ?string $senderName = null, ?string $senderAvatar = null): array
    {
        $payload = array_merge($message, [
            'receiver' => $receiverId,
            'sender' => array_filter([
                'name' => $senderName ?: 'Support',
                'avatar' => $senderAvatar,
            ]),
            'min_api_version' => 7,
        ]);

        return $this->post('send_message', $payload);
    }

    public function sendText(string $receiverId, string $text, ?string $senderName = null, ?string $senderAvatar = null): array
    {
        return $this->sendMessage($receiverId, [
            'type' => 'text',
            'text' => $text,
        ], $senderName, $senderAvatar);
    }

    public function sendPicture(string $receiverId, string $mediaUrl, ?string $text = null, ?string $thumbnail = null, ?string $senderName = null, ?string $senderAvatar = null): array
    {
        return $this->sendMessage($receiverId, array_filter([
            'type' => 'picture',
            'media' => $mediaUrl,
            'text' => $text,
            'thumbnail' => $thumbnail,
        ]), $senderName, $senderAvatar);
    }

    public function sendVideo(string $receiverId, string $mediaUrl, int $size, ?int $duration = null, ?string $thumbnail = null, ?string $senderName = null, ?string $senderAvatar = null): array
    {
        return $this->sendMessage($receiverId, array_filter([
            'type' => 'video',
            'media' => $mediaUrl,
            'size' => $size,
            'duration' => $duration,
            'thumbnail' => $thumbnail,
        ], fn ($v) => $v !== null), $senderName, $senderAvatar);
    }

    public function sendFile(string $receiverId, string $mediaUrl, int $size, string $fileName, ?string $senderName = null, ?string $senderAvatar = null): array
    {
        return $this->sendMessage($receiverId, [
            'type' => 'file',
            'media' => $mediaUrl,
            'size' => $size,
            'file_name' => $fileName,
        ], $senderName, $senderAvatar);
    }

    public function sendUrl(string $receiverId, string $mediaUrl, ?string $senderName = null, ?string $senderAvatar = null): array
    {
        return $this->sendMessage($receiverId, [
            'type' => 'url',
            'media' => $mediaUrl,
        ], $senderName, $senderAvatar);
    }

    public function sendLocation(string $receiverId, float $lat, float $lon, ?string $senderName = null, ?string $senderAvatar = null): array
    {
        return $this->sendMessage($receiverId, [
            'type' => 'location',
            'location' => [
                'lat' => $lat,
                'lon' => $lon,
            ],
        ], $senderName, $senderAvatar);
    }

    public function sendContact(string $receiverId, string $name, string $phoneNumber, ?string $senderName = null, ?string $senderAvatar = null): array
    {
        return $this->sendMessage($receiverId, [
            'type' => 'contact',
            'contact' => [
                'name' => $name,
                'phone_number' => $phoneNumber,
            ],
        ], $senderName, $senderAvatar);
    }

    public function verifySignature(string $rawBody, ?string $signatureHeader): bool
    {
        if (! $signatureHeader) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $this->authToken);

        return hash_equals($expected, strtolower($signatureHeader));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function post(string $endpoint, array $payload = []): array
    {
        $response = Http::withHeaders([
            'X-Viber-Auth-Token' => $this->authToken,
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(self::API_BASE.'/'.$endpoint, $payload);

        $data = $response->json() ?? [];

        if (! $response->successful()) {
            Log::warning('Viber API HTTP error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Viber API request failed (HTTP '.$response->status().').');
        }

        if (($data['status'] ?? 1) !== 0) {
            $message = $data['status_message'] ?? 'Unknown Viber API error';
            Log::warning('Viber API status error', [
                'endpoint' => $endpoint,
                'status' => $data['status'] ?? null,
                'message' => $message,
            ]);
            throw new RuntimeException('Viber API error: '.$message);
        }

        return $data;
    }
}
