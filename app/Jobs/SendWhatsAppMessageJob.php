<?php

namespace App\Jobs;

use App\Models\WhatsAppIntegration;
use App\Models\WhatsAppMessage;
use App\Services\TwilioCompanyService;
use App\Services\TwilioService;
use App\Support\MessagingQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWhatsAppMessageJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    /**
     * $body/$mediaUrl are the exact values the controller already computed for
     * Twilio (they don't always match the message row's stored `text`/`media_url`
     * verbatim — e.g. a location message's body folds coordinates into the text
     * differently than what's stored for display), so they're passed through
     * explicitly rather than re-derived here.
     */
    public function __construct(
        public int $whatsAppMessageId,
        public ?string $body = null,
        public ?string $mediaUrl = null
    ) {
        $this->onQueue(MessagingQueue::SEND);
    }

    public function uniqueId(): string
    {
        return 'whatsapp-message:'.$this->whatsAppMessageId;
    }

    public function handle(TwilioCompanyService $twilioCompany): void
    {
        $message = WhatsAppMessage::query()->find($this->whatsAppMessageId);
        if (! $message || $message->status !== 'queued') {
            // Already sent (or failed) by a previous attempt — never re-send.
            return;
        }

        $conversation = $message->conversation;
        $company = $message->company;
        $integration = $company ? $twilioCompany->getActiveIntegration($company) : null;
        $credentials = $integration ? $twilioCompany->getCredentials($integration) : null;
        $channel = $company
            ? WhatsAppIntegration::query()->where('company_id', $company->id)->where('is_active', true)->first()
            : null;

        if (! $credentials || ! $channel || ! $conversation) {
            $message->update(['status' => 'failed']);
            Log::warning('Queued WhatsApp send failed: not connected', ['whatsapp_message_id' => $message->id]);

            return;
        }

        $to = $conversation->wa_id ?: $conversation->phone;

        try {
            $twilio = new TwilioService($credentials['sid'], $credentials['token']);
            $sent = $twilio->sendWhatsApp(
                (string) $channel->from_number,
                (string) $to,
                $this->body,
                $channel->statusCallbackUrl(),
                $this->mediaUrl
            );
        } catch (Throwable $e) {
            // The Twilio call itself failed (network/API error) — safe to retry,
            // nothing was sent.
            Log::warning('Queued WhatsApp send failed', [
                'whatsapp_message_id' => $message->id,
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
                'wamid' => $sent->sid,
                'status' => $sent->status,
                'raw_payload' => ['sid' => $sent->sid, 'status' => $sent->status],
            ]);
        } catch (Throwable $e) {
            Log::critical('WhatsApp message sent via Twilio but failed to persist the result', [
                'whatsapp_message_id' => $message->id,
                'twilio_sid' => $sent->sid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('WhatsApp send job exhausted all retries', [
            'whatsapp_message_id' => $this->whatsAppMessageId,
            'error' => $e->getMessage(),
        ]);

        WhatsAppMessage::query()->where('id', $this->whatsAppMessageId)->where('status', 'queued')->update([
            'status' => 'failed',
        ]);
    }
}
