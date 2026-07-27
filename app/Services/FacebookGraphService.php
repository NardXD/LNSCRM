<?php

namespace App\Services;

use App\Models\FacebookIntegration;
use Illuminate\Support\Facades\Http;

class FacebookGraphService
{
    protected string $baseUrl = 'https://graph.facebook.com/v21.0';

    public function __construct(
        protected string $pageAccessToken,
        protected string $pageId,
        protected ?string $appSecret = null
    ) {}

    public static function forIntegration(FacebookIntegration $integration): self
    {
        $token = $integration->getDecryptedPageAccessToken();
        if (! $token) {
            throw new \RuntimeException('Facebook page access token is missing.');
        }

        return new self($token, $integration->page_id, $integration->getDecryptedAppSecret());
    }

    /**
     * @return array<string, mixed>
     */
    public function getPageInfo(): array
    {
        $response = Http::get($this->baseUrl.'/'.$this->pageId, [
            'fields' => 'id,name,instagram_business_account{id,username}',
            'access_token' => $this->pageAccessToken,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorMessage($response->json(), 'Could not verify Facebook Page credentials.'));
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function sendText(string $recipientId, string $text): array
    {
        return $this->sendMessage($recipientId, [
            'text' => $text,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function sendAttachment(string $recipientId, string $type, string $url): array
    {
        return $this->sendMessage($recipientId, [
            'attachment' => [
                'type' => $type, // image | video | audio | file
                'payload' => [
                    'url' => $url,
                    'is_reusable' => true,
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    public function sendMessage(string $recipientId, array $message): array
    {
        $response = Http::post($this->baseUrl.'/me/messages', [
            'recipient' => ['id' => $recipientId],
            'messaging_type' => 'RESPONSE',
            'message' => $message,
            'access_token' => $this->pageAccessToken,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException($this->errorMessage($response->json(), 'Failed to send Facebook/Instagram message.'));
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserProfile(string $peerId, string $channel = 'messenger'): array
    {
        $fields = $channel === 'instagram'
            ? 'name,username,profile_pic'
            : 'first_name,last_name,profile_pic,name';

        $response = Http::get($this->baseUrl.'/'.$peerId, [
            'fields' => $fields,
            'access_token' => $this->pageAccessToken,
        ]);

        if (! $response->successful()) {
            return [];
        }

        return $response->json() ?: [];
    }

    public function verifySignature(string $rawBody, ?string $signatureHeader): bool
    {
        if (! $this->appSecret) {
            return true;
        }

        if (! $signatureHeader || ! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawBody, $this->appSecret);

        return hash_equals($expected, $signatureHeader);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    protected function errorMessage(?array $payload, string $fallback): string
    {
        $message = $payload['error']['message'] ?? null;

        return is_string($message) && $message !== '' ? $message : $fallback;
    }
}
