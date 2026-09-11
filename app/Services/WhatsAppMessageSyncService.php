<?php

namespace App\Services;

use App\Models\WhatsAppIntegration;
use App\Models\WhatsAppMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WhatsAppMessageSyncService
{
    public function __construct(
        protected TwilioCompanyService $twilioCompany,
        protected WhatsAppConversationService $conversations
    ) {}

    /**
     * Full catch-up scan over the last N days (manual "Sync" button).
     *
     * @return array{scanned: int, imported: int, skipped: int}
     */
    public function sync(WhatsAppIntegration $integration, TwilioService $twilio, int $days = 30, int $limit = 500): array
    {
        $messages = $twilio->listChannelMessages(
            'whatsapp:'.$integration->from_number,
            now()->subDays($days),
            $limit
        );

        return $this->importMessages($integration, $twilio, $messages);
    }

    /**
     * Quick catch-up over the last N minutes (auto-sync / scheduled command).
     */
    public function ingestRecent(WhatsAppIntegration $integration, TwilioService $twilio, int $minutes = 45, int $limit = 150): int
    {
        $messages = $twilio->listChannelMessages(
            'whatsapp:'.$integration->from_number,
            now()->subMinutes(max(5, $minutes)),
            $limit
        );

        return $this->importMessages($integration, $twilio, $messages)['imported'];
    }

    /**
     * @param  array<int, object>  $messages
     * @return array{scanned: int, imported: int, skipped: int}
     */
    protected function importMessages(WhatsAppIntegration $integration, TwilioService $twilio, array $messages): array
    {
        usort($messages, fn ($a, $b) => $this->messageTimestamp($a) <=> $this->messageTimestamp($b));

        $ownNumber = $this->twilioCompany->normalizePhone((string) $integration->from_number);
        $scanned = count($messages);
        $imported = 0;
        $skipped = 0;

        foreach ($messages as $message) {
            $sid = (string) ($message->sid ?? '');
            if ($sid === '' || WhatsAppMessage::where('wamid', $sid)->exists()) {
                $skipped++;

                continue;
            }

            try {
                if ($this->importMessage($integration, $twilio, $ownNumber, $message)) {
                    $imported++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                Log::warning('WhatsApp sync import failed', ['sid' => $sid, 'error' => $e->getMessage()]);
                $skipped++;
            }
        }

        return compact('scanned', 'imported', 'skipped');
    }

    protected function importMessage(WhatsAppIntegration $integration, TwilioService $twilio, string $ownNumber, object $message): bool
    {
        $from = $this->twilioCompany->normalizePhone((string) ($message->from ?? ''));
        $to = $this->twilioCompany->normalizePhone((string) ($message->to ?? ''));
        if ($from === '' || $to === '') {
            return false;
        }

        $direction = $from === $ownNumber ? 'outbound' : 'inbound';
        $peer = $direction === 'inbound' ? $from : $to;

        if ($peer === '' || $peer === $ownNumber) {
            return false;
        }

        $conversation = $this->conversations->upsert($integration->company_id, $peer, null);

        $type = 'text';
        $mediaUrl = null;
        $mimeType = null;
        $numMedia = (int) ($message->numMedia ?? 0);

        if ($numMedia > 0) {
            try {
                $media = $twilio->firstMessageMedia((string) $message->sid);
                if ($media) {
                    $mimeType = $media['content_type'];
                    $type = $this->guessMediaType($mimeType);
                    $mediaUrl = $this->storeInboundMedia($integration, $twilio, $media['url'], $mimeType, (string) $message->sid);
                }
            } catch (\Throwable $e) {
                Log::warning('WhatsApp sync media import failed', ['error' => $e->getMessage()]);
            }
        }

        $record = WhatsAppMessage::create([
            'company_id' => $integration->company_id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => $direction,
            'wamid' => (string) $message->sid,
            'type' => $type,
            'text' => (string) ($message->body ?? '') ?: null,
            'media_url' => $mediaUrl,
            'mime_type' => $mimeType,
            'status' => (string) ($message->status ?? ($direction === 'inbound' ? 'received' : 'sent')),
            'raw_payload' => ['sid' => $message->sid, 'status' => $message->status ?? null, 'source' => 'sync'],
            'sent_at' => $this->messageDate($message) ?: now(),
        ]);

        $this->conversations->touch($conversation, $record, $direction === 'inbound');

        return true;
    }

    protected function guessMediaType(?string $mimeType): string
    {
        $mime = strtolower((string) $mimeType);

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'document',
        };
    }

    protected function storeInboundMedia(
        WhatsAppIntegration $integration,
        TwilioService $twilio,
        string $remoteUrl,
        ?string $mimeType,
        string $messageSid
    ): string {
        $binary = $twilio->downloadMedia($remoteUrl);

        $ext = match (true) {
            str_contains((string) $mimeType, 'jpeg') => 'jpg',
            str_contains((string) $mimeType, 'png') => 'png',
            str_contains((string) $mimeType, 'webp') => 'webp',
            str_contains((string) $mimeType, 'mp4') => 'mp4',
            str_contains((string) $mimeType, 'ogg') => 'ogg',
            str_contains((string) $mimeType, 'pdf') => 'pdf',
            default => 'bin',
        };

        $path = 'whatsapp/'.$integration->company_id.'/inbound/'.date('Y/m').'/'.$messageSid.'-'.Str::random(6).'.'.$ext;
        Storage::disk('public')->put($path, $binary);

        return public_media_url($path);
    }

    protected function messageDate(object $message): ?Carbon
    {
        foreach ([$message->dateSent ?? null, $message->dateCreated ?? null] as $candidate) {
            if (! $candidate) {
                continue;
            }

            try {
                if ($candidate instanceof \DateTimeInterface) {
                    return Carbon::instance($candidate)->timezone(config('app.timezone'));
                }

                return Carbon::parse((string) $candidate)->timezone(config('app.timezone'));
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    protected function messageTimestamp(object $message): int
    {
        return $this->messageDate($message)?->getTimestamp() ?? 0;
    }
}
