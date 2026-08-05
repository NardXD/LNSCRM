<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class InfobipService
{
    protected string $baseUrl;

    protected string $apiKey;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    public function client(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'App '.$this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->baseUrl($this->baseUrl)->timeout(60);
    }

    /**
     * @return array{messageId: ?string, status: ?string, raw: array}
     */
    public function sendSms(
        string $from,
        string $to,
        string $body,
        ?string $notifyUrl = null
    ): array {
        $message = [
            'sender' => $this->stripPlus($from),
            'destinations' => [
                ['to' => $this->stripPlus($to)],
            ],
            'content' => [
                'text' => $body,
            ],
        ];

        if ($notifyUrl) {
            $message['webhooks'] = [
                'delivery' => [
                    'url' => $notifyUrl,
                ],
            ];
        }

        $response = $this->client()->post('/sms/3/messages', [
            'messages' => [$message],
        ]);

        return $this->parseMessageResponse($response, 'SMS');
    }

    /**
     * @return array{messageId: ?string, status: ?string, raw: array}
     */
    public function sendWhatsAppText(
        string $from,
        string $to,
        string $text,
        ?string $notifyUrl = null
    ): array {
        $payload = [
            'from' => $this->stripPlus($from),
            'to' => $this->stripPlus($to),
            'content' => [
                'text' => $text,
            ],
        ];

        if ($notifyUrl) {
            $payload['notifyUrl'] = $notifyUrl;
        }

        $response = $this->client()->post('/whatsapp/1/message/text', $payload);

        return $this->parseMessageResponse($response, 'WhatsApp');
    }

    /**
     * @return array{messageId: ?string, status: ?string, raw: array}
     */
    public function sendWhatsAppMedia(
        string $from,
        string $to,
        string $type,
        string $mediaUrl,
        ?string $caption = null,
        ?string $filename = null,
        ?string $notifyUrl = null
    ): array {
        $endpointMap = [
            'image' => '/whatsapp/1/message/image',
            'video' => '/whatsapp/1/message/video',
            'document' => '/whatsapp/1/message/document',
            'audio' => '/whatsapp/1/message/audio',
        ];

        $endpoint = $endpointMap[$type] ?? null;
        if (! $endpoint) {
            throw new RuntimeException('Unsupported WhatsApp media type: '.$type);
        }

        $content = [
            'mediaUrl' => $mediaUrl,
        ];

        if ($caption !== null && $caption !== '' && in_array($type, ['image', 'video', 'document'], true)) {
            $content['caption'] = $caption;
        }

        if ($filename && $type === 'document') {
            $content['filename'] = $filename;
        }

        $payload = [
            'from' => $this->stripPlus($from),
            'to' => $this->stripPlus($to),
            'content' => $content,
        ];

        if ($notifyUrl) {
            $payload['notifyUrl'] = $notifyUrl;
        }

        $response = $this->client()->post($endpoint, $payload);

        return $this->parseMessageResponse($response, 'WhatsApp');
    }

    /**
     * @return array{messageId: ?string, status: ?string, raw: array}
     */
    public function sendViberText(
        string $from,
        string $to,
        string $text,
        ?string $notifyUrl = null
    ): array {
        $payload = [
            'from' => $from,
            'to' => $this->stripPlus($to),
            'content' => [
                'text' => $text,
                'type' => 'TEXT',
            ],
        ];

        if ($notifyUrl) {
            $payload['notifyUrl'] = $notifyUrl;
        }

        $response = $this->client()->post('/viber/2/messages', $payload);

        return $this->parseMessageResponse($response, 'Viber');
    }

    /**
     * @return array{messageId: ?string, status: ?string, raw: array}
     */
    public function sendViberMedia(
        string $from,
        string $to,
        string $type,
        string $mediaUrl,
        ?string $text = null,
        ?string $filename = null,
        ?string $notifyUrl = null
    ): array {
        $viberType = match ($type) {
            'picture', 'image' => 'IMAGE',
            'video' => 'VIDEO',
            'file', 'document' => 'FILE',
            default => 'FILE',
        };

        $content = [
            'type' => $viberType,
            'mediaUrl' => $mediaUrl,
        ];

        if ($text) {
            $content['text'] = $text;
        }

        if ($filename) {
            $content['fileName'] = $filename;
        }

        $payload = [
            'from' => $from,
            'to' => $this->stripPlus($to),
            'content' => $content,
        ];

        if ($notifyUrl) {
            $payload['notifyUrl'] = $notifyUrl;
        }

        $response = $this->client()->post('/viber/2/messages', $payload);

        return $this->parseMessageResponse($response, 'Viber');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listOwnedNumbers(): array
    {
        $response = $this->client()->get('/numbers/2/numbers');

        if (! $response->successful()) {
            // Fallback to older endpoint shape
            $response = $this->client()->get('/numbers/1/numbers');
        }

        if (! $response->successful()) {
            Log::warning('Infobip list numbers failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $data = $response->json();
        $numbers = $data['numbers'] ?? $data['results'] ?? (is_array($data) ? $data : []);

        return array_values(array_filter(array_map(function ($n) {
            if (! is_array($n)) {
                return null;
            }

            $phone = $n['number'] ?? $n['phoneNumber'] ?? $n['msisdn'] ?? null;
            if (! $phone) {
                return null;
            }

            $normalized = $this->normalizeE164((string) $phone);

            return [
                'sid' => (string) ($n['numberKey'] ?? $n['id'] ?? $n['numberId'] ?? $normalized),
                'phone_number' => $normalized,
                'friendly_name' => $n['name'] ?? $n['numberName'] ?? $normalized,
                'capabilities' => [
                    'voice' => (bool) ($n['voice'] ?? $n['capabilities']['voice'] ?? true),
                    'sms' => (bool) ($n['sms'] ?? $n['capabilities']['sms'] ?? true),
                    'mms' => (bool) ($n['mms'] ?? $n['capabilities']['mms'] ?? false),
                ],
            ];
        }, $numbers)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchAvailableNumbers(string $country = 'US', ?string $areaCode = null, int $limit = 10): array
    {
        $query = [
            'country' => strtoupper($country),
            'limit' => $limit,
        ];

        if ($areaCode) {
            $query['number'] = $areaCode;
        }

        $response = $this->client()->get('/numbers/2/numbers/available', $query);

        if (! $response->successful()) {
            Log::info('Infobip available numbers not accessible; portal-provisioned numbers only', [
                'status' => $response->status(),
            ]);

            return [];
        }

        $data = $response->json();
        $numbers = $data['numbers'] ?? $data['results'] ?? [];

        return array_values(array_filter(array_map(function ($n) {
            if (! is_array($n)) {
                return null;
            }

            $phone = $n['number'] ?? $n['phoneNumber'] ?? null;
            if (! $phone) {
                return null;
            }

            return [
                'phone_number' => $this->normalizeE164((string) $phone),
                'friendly_name' => $n['name'] ?? $this->normalizeE164((string) $phone),
                'locality' => $n['city'] ?? $n['locality'] ?? null,
                'region' => $n['region'] ?? $n['state'] ?? null,
                'capabilities' => [
                    'voice' => true,
                    'sms' => true,
                    'mms' => false,
                ],
            ];
        }, $numbers)));
    }

    /**
     * @return array{sid: string, phone_number: string, friendly_name: ?string, capabilities: array}
     */
    public function purchaseNumber(string $phoneNumber): array
    {
        $response = $this->client()->post('/numbers/2/numbers', [
            'number' => $this->stripPlus($phoneNumber),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to purchase Infobip number (HTTP '.$response->status().'): '.$response->body());
        }

        $data = $response->json() ?? [];
        $normalized = $this->normalizeE164((string) ($data['number'] ?? $phoneNumber));

        return [
            'sid' => (string) ($data['numberKey'] ?? $data['id'] ?? $normalized),
            'phone_number' => $normalized,
            'friendly_name' => $data['name'] ?? $normalized,
            'capabilities' => [
                'voice' => true,
                'sms' => true,
                'mms' => false,
            ],
        ];
    }

    /**
     * Create a WebRTC token for the browser softphone.
     *
     * @return array{token: string, expirationTime?: mixed}
     */
    public function createWebrtcToken(string $identity, ?string $displayName = null, int $ttlSeconds = 43200): array
    {
        $payload = [
            'identity' => $identity,
            'timeToLive' => $ttlSeconds,
        ];

        if ($displayName) {
            $payload['displayName'] = $displayName;
        }

        $response = $this->client()->post('/webrtc/1/token', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to create Infobip WebRTC token (HTTP '.$response->status().'): '.$response->body());
        }

        $data = $response->json() ?? [];
        $token = $data['token'] ?? null;

        if (! $token) {
            throw new RuntimeException('Infobip WebRTC token response missing token.');
        }

        return [
            'token' => $token,
            'expirationTime' => $data['expirationTime'] ?? null,
        ];
    }

    /**
     * Place an outbound PSTN call via Calls API.
     *
     * @return array{callId: ?string, raw: array}
     */
    public function makeCall(
        string $from,
        string $to,
        string $callsConfigurationId,
        ?string $webhookUrl = null
    ): array {
        $payload = [
            'endpoint' => [
                'type' => 'PHONE',
                'phoneNumber' => $this->normalizeE164($to),
            ],
            'from' => $this->normalizeE164($from),
            'callsConfigurationId' => $callsConfigurationId,
        ];

        if ($webhookUrl) {
            $payload['callRouting'] = [
                'type' => 'WEBHOOK',
            ];
        }

        $response = $this->client()->post('/calls/1/calls', $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to place Infobip call (HTTP '.$response->status().'): '.$response->body());
        }

        $data = $response->json() ?? [];

        return [
            'callId' => $data['id'] ?? $data['callId'] ?? null,
            'raw' => $data,
        ];
    }

    public function hangupCall(string $callId): void
    {
        $response = $this->client()->post('/calls/1/calls/'.$callId.'/hangup', [
            'errorCode' => 'NORMAL_HANGUP',
        ]);

        if (! $response->successful() && $response->status() !== 404) {
            throw new RuntimeException('Failed to hang up Infobip call (HTTP '.$response->status().'): '.$response->body());
        }
    }

    public function sendDtmf(string $callId, string $digits): void
    {
        $response = $this->client()->post('/calls/1/calls/'.$callId.'/send-dtmf', [
            'dtmf' => $digits,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to send DTMF (HTTP '.$response->status().'): '.$response->body());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getCall(string $callId): array
    {
        $response = $this->client()->get('/calls/1/calls/'.$callId);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to fetch Infobip call (HTTP '.$response->status().'): '.$response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Connect an inbound PSTN call to a WebRTC identity (answer + dial webrtc).
     */
    public function connectCallToWebrtc(string $callId, string $identity): void
    {
        $response = $this->client()->post('/calls/1/calls/'.$callId.'/connect', [
            'endpoint' => [
                'type' => 'WEBRTC',
                'identity' => $identity,
            ],
        ]);

        if (! $response->successful()) {
            // Older/newer connect shapes
            $response = $this->client()->post('/calls/1/connect', [
                'callId' => $callId,
                'endpoint' => [
                    'type' => 'WEBRTC',
                    'identity' => $identity,
                ],
            ]);
        }

        if (! $response->successful()) {
            Log::warning('Infobip connect call to WebRTC failed', [
                'call_id' => $callId,
                'identity' => $identity,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('Failed to connect call to WebRTC agent (HTTP '.$response->status().').');
        }
    }

    public function downloadMedia(string $mediaUrl): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'App '.$this->apiKey,
        ])
            ->timeout(60)
            ->get($mediaUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to download Infobip media (HTTP '.$response->status().').');
        }

        return $response->body();
    }

    public function normalizeE164(string $number): string
    {
        $trimmed = trim($number);
        if (str_starts_with(strtolower($trimmed), 'whatsapp:')) {
            $trimmed = substr($trimmed, strlen('whatsapp:'));
        }

        $digits = preg_replace('/[^\d+]/', '', $trimmed) ?? $trimmed;
        if ($digits !== '' && ! str_starts_with($digits, '+')) {
            $digits = '+'.$digits;
        }

        return $digits;
    }

    public function stripPlus(string $number): string
    {
        $normalized = $this->normalizeE164($number);

        return ltrim($normalized, '+');
    }

    /**
     * @return array{messageId: ?string, status: ?string, raw: array}
     */
    protected function parseMessageResponse(Response $response, string $channel): array
    {
        $data = $response->json() ?? [];

        if (! $response->successful()) {
            $detail = is_string($data['requestError']['serviceException']['text'] ?? null)
                ? $data['requestError']['serviceException']['text']
                : $response->body();

            throw new RuntimeException("Failed to send {$channel} message (HTTP {$response->status()}): {$detail}");
        }

        $messageId = $data['messages'][0]['messageId']
            ?? $data['messageId']
            ?? $data['messages'][0]['to']['messageId']
            ?? null;

        $status = $data['messages'][0]['status']['name']
            ?? $data['messages'][0]['status']['groupName']
            ?? $data['status']['name']
            ?? 'pending';

        return [
            'messageId' => $messageId ? (string) $messageId : null,
            'status' => $status ? strtolower((string) $status) : 'pending',
            'raw' => $data,
        ];
    }
}
