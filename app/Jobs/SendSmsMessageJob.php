<?php

namespace App\Jobs;

use App\Models\SmsMessage;
use App\Services\TwilioCompanyService;
use App\Services\TwilioService;
use App\Support\MessagingQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendSmsMessageJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(public int $smsMessageId)
    {
        $this->onQueue(MessagingQueue::SEND);
    }

    public function uniqueId(): string
    {
        return 'sms-message:'.$this->smsMessageId;
    }

    public function handle(TwilioCompanyService $twilioCompany): void
    {
        $message = SmsMessage::query()->find($this->smsMessageId);
        if (! $message || $message->status !== 'queued') {
            // Already sent (or failed) by a previous attempt — never re-send.
            return;
        }

        $company = $message->company;
        $integration = $company ? $twilioCompany->getActiveIntegration($company) : null;
        $credentials = $integration ? $twilioCompany->getCredentials($integration) : null;

        if (! $credentials) {
            $message->update(['status' => 'failed']);
            Log::warning('Queued SMS send failed: Twilio not connected', ['sms_message_id' => $message->id]);

            return;
        }

        try {
            $twilio = new TwilioService($credentials['sid'], $credentials['token']);
            $sent = $twilio->sendSms(
                (string) $message->from_number,
                (string) $message->to_number,
                (string) $message->body,
                route('twilio.sms-status')
            );
        } catch (Throwable $e) {
            // The Twilio call itself failed (network/API error) — safe to retry,
            // nothing was sent.
            Log::warning('Queued SMS send failed', [
                'sms_message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        // Twilio confirmed the send — never rethrow past this point, or a retry
        // would send a duplicate message to the recipient. The conversation's
        // preview/last_message_at were already updated synchronously when the
        // message row was created, so only the message itself needs updating here.
        try {
            $message->update([
                'message_sid' => $sent->sid,
                'status' => $sent->status,
            ]);
        } catch (Throwable $e) {
            Log::critical('SMS sent via Twilio but failed to persist the result', [
                'sms_message_id' => $message->id,
                'twilio_sid' => $sent->sid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('SMS send job exhausted all retries', [
            'sms_message_id' => $this->smsMessageId,
            'error' => $e->getMessage(),
        ]);

        SmsMessage::query()->where('id', $this->smsMessageId)->where('status', 'queued')->update([
            'status' => 'failed',
        ]);
    }
}
