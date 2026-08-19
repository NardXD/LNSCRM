<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use Twilio\Security\RequestValidator;

class TwilioService
{
    protected Client $twilio;

    protected ?string $accountSid;

    protected ?string $authToken;

    public function __construct(?string $accountSid = null, ?string $authToken = null)
    {
        $this->accountSid = $accountSid ?? config('services.twilio.sid');
        $this->authToken = $authToken ?? config('services.twilio.token');

        $this->twilio = new Client($this->accountSid, $this->authToken);

        Log::info('Twilio device ready', [
            'account_sid' => $this->accountSid ? substr($this->accountSid, 0, 10).'...' : 'not configured',
        ]);
    }

    public function makeCall($to, $url, $statusCallbackUrl = null, $from = null)
    {
        $fromNumber = $from ?? config('services.twilio.voice_from');

        Log::info('Twilio device registered');
        Log::info('Calling '.$to, [
            'to' => $to,
            'from' => $fromNumber,
            'url' => $url,
        ]);

        return $this->twilio->calls->create(
            $to,
            $fromNumber,
            [
                'url' => $url,
                'statusCallback' => $statusCallbackUrl ?? route('twilio.status-callback'),
                'statusCallbackEvent' => ['initiated', 'ringing', 'answered', 'completed'],
                'statusCallbackMethod' => 'POST',
            ]
        );
    }

    public function getTwilioClient(): Client
    {
        return $this->twilio;
    }

    /**
     * Create a TwiML App used by the Voice SDK for browser calling.
     */
    public function createVoiceApplication(string $friendlyName, string $voiceUrl, ?string $statusCallback = null): string
    {
        $params = [
            'friendlyName' => $friendlyName,
            'voiceUrl' => $voiceUrl,
            'voiceMethod' => 'POST',
        ];

        if ($statusCallback) {
            $params['statusCallback'] = $statusCallback;
            $params['statusCallbackMethod'] = 'POST';
        }

        $application = $this->twilio->applications->create($params);

        return $application->sid;
    }

    /**
     * @return array{sid: string, secret: string}
     */
    public function createApiKey(string $friendlyName): array
    {
        $key = $this->twilio->newKeys->create([
            'friendlyName' => $friendlyName,
        ]);

        return [
            'sid' => $key->sid,
            'secret' => $key->secret,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchAvailableNumbers(string $country = 'US', ?string $areaCode = null, int $limit = 10): array
    {
        $params = ['limit' => $limit];
        if ($areaCode) {
            $params['areaCode'] = $areaCode;
        }

        $numbers = $this->twilio->availablePhoneNumbers($country)
            ->local
            ->read($params);

        return array_map(fn ($n) => [
            'phone_number' => $n->phoneNumber,
            'friendly_name' => $n->friendlyName,
            'locality' => $n->locality ?? null,
            'region' => $n->region ?? null,
            'capabilities' => [
                'voice' => (bool) ($n->capabilities->voice ?? false),
                'sms' => (bool) ($n->capabilities->SMS ?? false),
                'mms' => (bool) ($n->capabilities->MMS ?? false),
            ],
        ], $numbers);
    }

    public function purchaseNumber(string $phoneNumber, string $voiceUrl, ?string $smsUrl = null): \Twilio\Rest\Api\V2010\Account\IncomingPhoneNumberInstance
    {
        $params = [
            'phoneNumber' => $phoneNumber,
            'voiceUrl' => $voiceUrl,
            'voiceMethod' => 'POST',
        ];

        if ($smsUrl) {
            $params['smsUrl'] = $smsUrl;
            $params['smsMethod'] = 'POST';
        }

        return $this->twilio->incomingPhoneNumbers->create($params);
    }

    public function updateNumberWebhooks(string $incomingPhoneNumberSid, string $voiceUrl, ?string $smsUrl = null): void
    {
        $params = [
            'voiceUrl' => $voiceUrl,
            'voiceMethod' => 'POST',
        ];

        if ($smsUrl) {
            $params['smsUrl'] = $smsUrl;
            $params['smsMethod'] = 'POST';
        }

        $this->twilio->incomingPhoneNumbers($incomingPhoneNumberSid)->update($params);
    }

    public function sendSms(string $from, string $to, string $body, ?string $statusCallback = null): \Twilio\Rest\Api\V2010\Account\MessageInstance
    {
        return $this->sendMessage($from, $to, $body, $statusCallback);
    }

    public function sendMessage(
        string $from,
        string $to,
        ?string $body = null,
        ?string $statusCallback = null,
        ?string $mediaUrl = null
    ): \Twilio\Rest\Api\V2010\Account\MessageInstance {
        $params = [
            'from' => $from,
            'to' => $to,
        ];

        if ($body !== null && $body !== '') {
            $params['body'] = $body;
        }

        if ($mediaUrl) {
            $params['mediaUrl'] = [$mediaUrl];
        }

        if ($statusCallback) {
            $params['statusCallback'] = $statusCallback;
        }

        return $this->twilio->messages->create($to, $params);
    }

    public function sendWhatsApp(
        string $fromE164,
        string $toE164,
        ?string $body = null,
        ?string $statusCallback = null,
        ?string $mediaUrl = null
    ): \Twilio\Rest\Api\V2010\Account\MessageInstance {
        return $this->sendMessage(
            $this->whatsappAddress($fromE164),
            $this->whatsappAddress($toE164),
            $body,
            $statusCallback,
            $mediaUrl
        );
    }

    public function sendViber(
        string $senderId,
        string $toE164,
        ?string $body = null,
        ?string $statusCallback = null,
        ?string $mediaUrl = null
    ): \Twilio\Rest\Api\V2010\Account\MessageInstance {
        return $this->sendMessage(
            $senderId,
            $this->normalizeE164($toE164),
            $body,
            $statusCallback,
            $mediaUrl
        );
    }

    public function sendMessenger(
        string $fromPageId,
        string $toPeerId,
        string $channel = 'messenger',
        ?string $body = null,
        ?string $statusCallback = null,
        ?string $mediaUrl = null
    ): \Twilio\Rest\Api\V2010\Account\MessageInstance {
        return $this->sendMessage(
            $this->messengerAddress($fromPageId, $channel),
            $this->messengerAddress($toPeerId, $channel),
            $body,
            $statusCallback,
            $mediaUrl
        );
    }

    public function whatsappAddress(string $number): string
    {
        $normalized = $this->normalizeE164($number);

        return 'whatsapp:'.$normalized;
    }

    public function messengerAddress(string $id, string $channel = 'messenger'): string
    {
        $id = self::stripChannelPrefix($id);
        $prefix = $channel === 'instagram' ? 'instagram:' : 'messenger:';

        return $prefix.$id;
    }

    /**
     * @return array{channel: string, id: string}
     */
    public static function parseMessengerAddress(string $address): array
    {
        $trimmed = trim($address);
        $lower = strtolower($trimmed);

        if (str_starts_with($lower, 'messenger:')) {
            return ['channel' => 'messenger', 'id' => substr($trimmed, strlen('messenger:'))];
        }

        if (str_starts_with($lower, 'instagram:')) {
            return ['channel' => 'instagram', 'id' => substr($trimmed, strlen('instagram:'))];
        }

        return ['channel' => 'messenger', 'id' => $trimmed];
    }

    public static function stripChannelPrefix(string $value): string
    {
        $trimmed = trim($value);
        $lower = strtolower($trimmed);

        foreach (['messenger:', 'instagram:', 'whatsapp:'] as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return trim(substr($trimmed, strlen($prefix)));
            }
        }

        return $trimmed;
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

    public function validateRequest(string $signature, string $url, array $params): bool
    {
        if (! $this->authToken || $signature === '') {
            return false;
        }

        $validator = new RequestValidator($this->authToken);

        if ($validator->validate($signature, $url, $params)) {
            return true;
        }

        // ngrok / proxies sometimes leave the app seeing http while Twilio signed https
        if (str_starts_with($url, 'http://')) {
            $httpsUrl = 'https://'.substr($url, strlen('http://'));

            return $validator->validate($signature, $httpsUrl, $params);
        }

        return false;
    }

    public function downloadMedia(string $mediaUrl): string
    {
        $response = Http::withBasicAuth((string) $this->accountSid, (string) $this->authToken)
            ->timeout(60)
            ->get($mediaUrl);

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to download Twilio media (HTTP '.$response->status().').');
        }

        return $response->body();
    }

    /**
     * @return array<int, \Twilio\Rest\Api\V2010\Account\MessageInstance>
     */
    public function listChannelMessages(string $address, ?\DateTimeInterface $after = null, int $limit = 2000): array
    {
        $params = [];
        if ($after) {
            $params['dateSentAfter'] = $after;
        }

        $bySid = [];
        $candidates = array_values(array_unique(array_filter([
            $address,
            self::stripChannelPrefix($address),
        ])));

        foreach ($candidates as $candidate) {
            foreach (['to', 'from'] as $field) {
                $options = $params;
                $options[$field] = $candidate;
                foreach ($this->twilio->messages->stream($options, $limit, 100) as $message) {
                    $bySid[$message->sid] = $message;
                }
            }
        }

        foreach ($this->twilio->messages->stream($params, $limit, 100) as $message) {
            if ($this->isSocialChannelMessage($message)) {
                $bySid[$message->sid] = $message;
            }
        }

        return array_values($bySid);
    }

    public function isSocialChannelMessage(object $message, ?string $pageAddress = null): bool
    {
        $from = strtolower((string) ($message->from ?? ''));
        $to = strtolower((string) ($message->to ?? ''));
        $haystack = $from.' '.$to;

        if (! str_contains($haystack, 'messenger:') && ! str_contains($haystack, 'instagram:')) {
            return false;
        }

        if (! $pageAddress) {
            return true;
        }

        $pageId = strtolower(self::stripChannelPrefix($pageAddress));

        return $pageId === '' || str_contains($haystack, $pageId);
    }

    /**
     * @return array{url: string, content_type: ?string}|null
     */
    public function firstMessageMedia(string $messageSid): ?array
    {
        $media = $this->twilio->messages($messageSid)->media->read([], 1);
        if ($media === []) {
            return null;
        }

        $item = $media[0];
        $uri = preg_replace('/\.json$/', '', (string) $item->uri) ?: (string) $item->uri;

        return [
            'url' => 'https://api.twilio.com'.$uri,
            'content_type' => $item->contentType ?? null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listOwnedNumbers(): array
    {
        $numbers = $this->twilio->incomingPhoneNumbers->read([], 100);

        return array_map(fn ($n) => [
            'sid' => $n->sid,
            'phone_number' => $n->phoneNumber,
            'friendly_name' => $n->friendlyName,
            'capabilities' => [
                'voice' => (bool) ($n->capabilities->voice ?? false),
                'sms' => (bool) ($n->capabilities->sms ?? false),
                'mms' => (bool) ($n->capabilities->mms ?? false),
            ],
        ], $numbers);
    }
}
