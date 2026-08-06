<?php

namespace App\Services;

use Infobip\Api\CallsApi;
use Infobip\Api\SmsApi;
use Infobip\Api\ViberApi;
use Infobip\Api\WebRtcApi;
use Infobip\Api\WhatsAppApi;
use Infobip\ApiException;
use Infobip\Configuration;
use Infobip\Model\CallRequest;
use Infobip\Model\CallsActionCallRequest;
use Infobip\Model\CallsConnectWithNewCallRequest;
use Infobip\Model\CallsDtmfSendRequest;
use Infobip\Model\CallsHangupRequest;
use Infobip\Model\CallsPhoneEndpoint;
use Infobip\Model\CallsWebRtcEndpoint;
use Infobip\Model\MessageResponse;
use Infobip\Model\SmsDestination;
use Infobip\Model\SmsMessage;
use Infobip\Model\SmsMessageDeliveryReporting;
use Infobip\Model\SmsRequest;
use Infobip\Model\SmsResponse;
use Infobip\Model\SmsTextContent;
use Infobip\Model\SmsWebhooks;
use Infobip\Model\ViberMessage;
use Infobip\Model\ViberMessageDeliveryReporting;
use Infobip\Model\ViberOttWebhooks;
use Infobip\Model\ViberOutboundFileContent;
use Infobip\Model\ViberOutboundImageContent;
use Infobip\Model\ViberOutboundTextContent;
use Infobip\Model\ViberRequest;
use Infobip\Model\ViberToDestination;
use Infobip\Model\WebRtcTokenRequestModel;
use Infobip\Model\WhatsAppAudioContent;
use Infobip\Model\WhatsAppAudioMessage;
use Infobip\Model\WhatsAppDocumentContent;
use Infobip\Model\WhatsAppDocumentMessage;
use Infobip\Model\WhatsAppImageContent;
use Infobip\Model\WhatsAppImageMessage;
use Infobip\Model\WhatsAppSingleMessageInfo;
use Infobip\Model\WhatsAppTextContent;
use Infobip\Model\WhatsAppTextMessage;
use Infobip\Model\WhatsAppVideoContent;
use Infobip\Model\WhatsAppVideoMessage;
use Infobip\ObjectSerializer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class InfobipService
{
    protected string $baseUrl;

    protected string $apiKey;

    protected Configuration $configuration;

    protected ObjectSerializer $serializer;

    protected ?SmsApi $smsApi = null;

    protected ?WhatsAppApi $whatsAppApi = null;

    protected ?ViberApi $viberApi = null;

    protected ?WebRtcApi $webRtcApi = null;

    protected ?CallsApi $callsApi = null;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = $this->normalizeHost($baseUrl);
        $this->apiKey = $apiKey;
        $this->configuration = new Configuration(
            host: $this->baseUrl,
            apiKey: $this->apiKey,
        );
        $this->serializer = new ObjectSerializer;
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
        $webhooks = null;
        if ($notifyUrl) {
            $webhooks = new SmsWebhooks(
                delivery: new SmsMessageDeliveryReporting(url: $notifyUrl),
            );
        }

        $message = new SmsMessage(
            destinations: [
                new SmsDestination(to: $this->stripPlus($to)),
            ],
            content: new SmsTextContent(text: $body),
            sender: $this->stripPlus($from),
            webhooks: $webhooks,
        );

        try {
            $response = $this->smsApi()->sendSmsMessages(new SmsRequest(messages: [$message]));
        } catch (ApiException $e) {
            throw $this->wrapApiException($e, 'SMS');
        }

        return $this->parseSdkMessageResponse($response, 'SMS');
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
        $message = new WhatsAppTextMessage(
            from: $this->stripPlus($from),
            to: $this->stripPlus($to),
            content: new WhatsAppTextContent(text: $text),
            notifyUrl: $notifyUrl,
        );

        try {
            $response = $this->whatsAppApi()->sendWhatsAppTextMessage($message);
        } catch (ApiException $e) {
            throw $this->wrapApiException($e, 'WhatsApp');
        }

        return $this->parseSdkMessageResponse($response, 'WhatsApp');
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
        $fromNumber = $this->stripPlus($from);
        $toNumber = $this->stripPlus($to);

        try {
            $response = match ($type) {
                'image' => $this->whatsAppApi()->sendWhatsAppImageMessage(new WhatsAppImageMessage(
                    from: $fromNumber,
                    to: $toNumber,
                    content: new WhatsAppImageContent(
                        mediaUrl: $mediaUrl,
                        caption: ($caption !== null && $caption !== '') ? $caption : null,
                    ),
                    notifyUrl: $notifyUrl,
                )),
                'video' => $this->whatsAppApi()->sendWhatsAppVideoMessage(new WhatsAppVideoMessage(
                    from: $fromNumber,
                    to: $toNumber,
                    content: new WhatsAppVideoContent(
                        mediaUrl: $mediaUrl,
                        caption: ($caption !== null && $caption !== '') ? $caption : null,
                    ),
                    notifyUrl: $notifyUrl,
                )),
                'document' => $this->whatsAppApi()->sendWhatsAppDocumentMessage(new WhatsAppDocumentMessage(
                    from: $fromNumber,
                    to: $toNumber,
                    content: new WhatsAppDocumentContent(
                        mediaUrl: $mediaUrl,
                        caption: ($caption !== null && $caption !== '') ? $caption : null,
                        filename: $filename,
                    ),
                    notifyUrl: $notifyUrl,
                )),
                'audio' => $this->whatsAppApi()->sendWhatsAppAudioMessage(new WhatsAppAudioMessage(
                    from: $fromNumber,
                    to: $toNumber,
                    content: new WhatsAppAudioContent(mediaUrl: $mediaUrl),
                    notifyUrl: $notifyUrl,
                )),
                default => throw new RuntimeException('Unsupported WhatsApp media type: '.$type),
            };
        } catch (ApiException $e) {
            throw $this->wrapApiException($e, 'WhatsApp');
        }

        return $this->parseSdkMessageResponse($response, 'WhatsApp');
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
        $webhooks = null;
        if ($notifyUrl) {
            $webhooks = new ViberOttWebhooks(
                delivery: new ViberMessageDeliveryReporting(url: $notifyUrl),
            );
        }

        $message = new ViberMessage(
            sender: $from,
            destinations: [
                new ViberToDestination(to: $this->stripPlus($to)),
            ],
            content: new ViberOutboundTextContent(text: $text),
            webhooks: $webhooks,
        );

        try {
            $response = $this->viberApi()->sendViberMessages(new ViberRequest(messages: [$message]));
        } catch (ApiException $e) {
            throw $this->wrapApiException($e, 'Viber');
        }

        return $this->parseSdkMessageResponse($response, 'Viber');
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
        $normalizedType = match ($type) {
            'picture', 'image' => 'image',
            'video' => 'video',
            'file', 'document' => 'file',
            default => 'file',
        };

        // SDK video content requires mediaDuration + thumbnailUrl; keep HTTP for video parity.
        if ($normalizedType === 'video') {
            $content = [
                'type' => 'VIDEO',
                'mediaUrl' => $mediaUrl,
            ];

            if ($text) {
                $content['text'] = $text;
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

            return $this->parseHttpMessageResponse($response, 'Viber');
        }

        $webhooks = null;
        if ($notifyUrl) {
            $webhooks = new ViberOttWebhooks(
                delivery: new ViberMessageDeliveryReporting(url: $notifyUrl),
            );
        }

        $content = match ($normalizedType) {
            'image' => new ViberOutboundImageContent(
                mediaUrl: $mediaUrl,
                text: $text,
            ),
            default => new ViberOutboundFileContent(
                fileName: $filename ?: basename(parse_url($mediaUrl, PHP_URL_PATH) ?: 'file.pdf') ?: 'file.pdf',
                mediaUrl: $mediaUrl,
            ),
        };

        $message = new ViberMessage(
            sender: $from,
            destinations: [
                new ViberToDestination(to: $this->stripPlus($to)),
            ],
            content: $content,
            webhooks: $webhooks,
        );

        try {
            $response = $this->viberApi()->sendViberMessages(new ViberRequest(messages: [$message]));
        } catch (ApiException $e) {
            throw $this->wrapApiException($e, 'Viber');
        }

        return $this->parseSdkMessageResponse($response, 'Viber');
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

        $data = $response->json() ?? [];
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
        $request = new WebRtcTokenRequestModel(
            identity: $identity,
            displayName: $displayName,
            timeToLive: $ttlSeconds,
        );

        try {
            $response = $this->webRtcApi()->generateWebRtcToken($request);
        } catch (ApiException $e) {
            throw $this->wrapApiException($e, 'create WebRTC token');
        }

        $token = $response?->getToken();
        if (! $token) {
            throw new RuntimeException('Infobip WebRTC token response missing token.');
        }

        return [
            'token' => $token,
            'expirationTime' => $response->getExpirationTime(),
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
        unset($webhookUrl);

        $request = new CallRequest(
            endpoint: new CallsPhoneEndpoint(phoneNumber: $this->normalizeE164($to)),
            callsConfigurationId: $callsConfigurationId,
            from: $this->normalizeE164($from),
        );

        try {
            $call = $this->callsApi()->createCall($request);
        } catch (ApiException $e) {
            throw $this->wrapApiException($e, 'place call');
        }

        $raw = $this->toArray($call);

        return [
            'callId' => $call?->getId() ?? ($raw['id'] ?? $raw['callId'] ?? null),
            'raw' => $raw,
        ];
    }

    public function hangupCall(string $callId): void
    {
        try {
            $this->callsApi()->hangupCall($callId, new CallsHangupRequest(errorCode: 'NORMAL_HANGUP'));
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                return;
            }

            throw $this->wrapApiException($e, 'hang up call');
        }
    }

    public function sendDtmf(string $callId, string $digits): void
    {
        try {
            $this->callsApi()->callSendDtmf($callId, new CallsDtmfSendRequest(dtmf: $digits));
        } catch (ApiException $e) {
            throw $this->wrapApiException($e, 'send DTMF');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getCall(string $callId): array
    {
        try {
            $call = $this->callsApi()->getCall($callId);
        } catch (ApiException $e) {
            throw $this->wrapApiException($e, 'fetch Infobip call');
        }

        return $this->toArray($call);
    }

    /**
     * Connect an inbound PSTN call to a WebRTC identity (answer + dial webrtc).
     */
    public function connectCallToWebrtc(string $callId, string $identity): void
    {
        $request = new CallsConnectWithNewCallRequest(
            callRequest: new CallsActionCallRequest(
                endpoint: new CallsWebRtcEndpoint(identity: $identity),
            ),
        );

        try {
            $this->callsApi()->connectWithNewCall($callId, $request);
        } catch (ApiException $e) {
            Log::warning('Infobip connect call to WebRTC failed', [
                'call_id' => $callId,
                'identity' => $identity,
                'status' => $e->getCode(),
                'body' => $e->getResponseBody(),
            ]);

            throw new RuntimeException('Failed to connect call to WebRTC agent (HTTP '.$e->getCode().').', 0, $e);
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

    protected function smsApi(): SmsApi
    {
        return $this->smsApi ??= new SmsApi(config: $this->configuration);
    }

    protected function whatsAppApi(): WhatsAppApi
    {
        return $this->whatsAppApi ??= new WhatsAppApi(config: $this->configuration);
    }

    protected function viberApi(): ViberApi
    {
        return $this->viberApi ??= new ViberApi(config: $this->configuration);
    }

    protected function webRtcApi(): WebRtcApi
    {
        return $this->webRtcApi ??= new WebRtcApi(config: $this->configuration);
    }

    protected function callsApi(): CallsApi
    {
        return $this->callsApi ??= new CallsApi(config: $this->configuration);
    }

    protected function normalizeHost(string $baseUrl): string
    {
        $host = rtrim(trim($baseUrl), '/');

        if ($host === '') {
            return $host;
        }

        if (! preg_match('#^https?://#i', $host)) {
            $host = 'https://'.$host;
        }

        return $host;
    }

    protected function wrapApiException(ApiException $e, string $channel): RuntimeException
    {
        $body = $e->getResponseBody();
        $detail = is_string($body) ? $body : (is_object($body) || is_array($body) ? json_encode($body) : $e->getMessage());

        $decoded = is_string($body) ? json_decode($body, true) : null;
        if (is_array($decoded)) {
            $text = $decoded['requestError']['serviceException']['text']
                ?? $decoded['error']['description']
                ?? $decoded['message']
                ?? null;
            if (is_string($text) && $text !== '') {
                $detail = $text;
            }
        }

        $action = preg_match('/^(send|create|place|hang|fetch)/i', $channel)
            ? $channel
            : "send {$channel} message";

        return new RuntimeException(
            "Failed to {$action} (HTTP {$e->getCode()}): {$detail}",
            0,
            $e
        );
    }

    /**
     * @return array{messageId: ?string, status: ?string, raw: array}
     */
    protected function parseSdkMessageResponse(mixed $response, string $channel): array
    {
        $raw = $this->toArray($response);

        if ($response instanceof SmsResponse) {
            $messages = $response->getMessages() ?? [];
            $first = $messages[0] ?? null;
            $messageId = $first?->getMessageId();
            $status = $first?->getStatus()?->getName()
                ?? $first?->getStatus()?->getGroupName()
                ?? 'pending';

            return [
                'messageId' => $messageId ? (string) $messageId : null,
                'status' => $status ? strtolower((string) $status) : 'pending',
                'raw' => $raw,
            ];
        }

        if ($response instanceof WhatsAppSingleMessageInfo) {
            $messageId = $response->getMessageId();
            $status = $response->getStatus()?->getName()
                ?? $response->getStatus()?->getGroupName()
                ?? 'pending';

            return [
                'messageId' => $messageId ? (string) $messageId : null,
                'status' => $status ? strtolower((string) $status) : 'pending',
                'raw' => $raw,
            ];
        }

        if ($response instanceof MessageResponse) {
            $messages = $response->getMessages() ?? [];
            $first = $messages[0] ?? null;
            $messageId = $first?->getMessageId();
            $status = $first?->getStatus()?->getName()
                ?? $first?->getStatus()?->getGroupName()
                ?? 'pending';

            return [
                'messageId' => $messageId ? (string) $messageId : null,
                'status' => $status ? strtolower((string) $status) : 'pending',
                'raw' => $raw,
            ];
        }

        $messageId = $raw['messages'][0]['messageId']
            ?? $raw['messageId']
            ?? $raw['messages'][0]['to']['messageId']
            ?? null;

        $status = $raw['messages'][0]['status']['name']
            ?? $raw['messages'][0]['status']['groupName']
            ?? $raw['status']['name']
            ?? 'pending';

        if ($messageId === null && $status === 'pending' && $raw === []) {
            throw new RuntimeException("Failed to send {$channel} message: empty response.");
        }

        return [
            'messageId' => $messageId ? (string) $messageId : null,
            'status' => $status ? strtolower((string) $status) : 'pending',
            'raw' => $raw,
        ];
    }

    /**
     * @return array{messageId: ?string, status: ?string, raw: array}
     */
    protected function parseHttpMessageResponse(Response $response, string $channel): array
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

    /**
     * @return array<string, mixed>
     */
    protected function toArray(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $value;
        }

        try {
            $normalized = $this->serializer->normalize($value);
            if (is_array($normalized)) {
                return $normalized;
            }
        } catch (\Throwable) {
            // Fall through to JSON encode.
        }

        $encoded = json_encode($value);
        if (! is_string($encoded)) {
            return [];
        }

        $decoded = json_decode($encoded, true);

        return is_array($decoded) ? $decoded : [];
    }
}
