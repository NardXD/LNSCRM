<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class TwilioService
{
    protected Client $twilio;

    public function __construct(?string $accountSid = null, ?string $authToken = null)
    {
        $sid = $accountSid ?? config('services.twilio.sid');
        $token = $authToken ?? config('services.twilio.token');

        $this->twilio = new Client($sid, $token);

        Log::info('Twilio device ready', [
            'account_sid' => $sid ? substr($sid, 0, 10).'...' : 'not configured',
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
        $params = [
            'from' => $from,
            'to' => $to,
            'body' => $body,
        ];

        if ($statusCallback) {
            $params['statusCallback'] = $statusCallback;
        }

        return $this->twilio->messages->create($to, $params);
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
