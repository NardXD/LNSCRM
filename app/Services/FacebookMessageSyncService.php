<?php

namespace App\Services;

use App\Models\FacebookConversation;
use App\Models\FacebookIntegration;
use App\Models\FacebookMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Twilio\Rest\Api\V2010\Account\MessageInstance;

class FacebookMessageSyncService
{
    public function __construct(
        protected LeadAutoCreateService $leadAutoCreate
    ) {}

    /**
     * Import historical Twilio Messenger / Instagram messages into the CRM inbox.
     *
     * @return array{scanned: int, imported: int, skipped: int, conversations: int, days: int}
     */
    public function sync(FacebookIntegration $integration, TwilioService $twilio, int $days = 90, int $limit = 500): array
    {
        $after = Carbon::now()->subDays(max(1, $days))->startOfDay();
        $addresses = array_values(array_unique(array_filter([
            $twilio->messengerAddress((string) $integration->page_id, 'messenger'),
            $integration->instagram_business_account_id
                ? $twilio->messengerAddress((string) $integration->instagram_business_account_id, 'instagram')
                : null,
        ])));

        $bySid = [];
        foreach ($addresses as $address) {
            foreach ($twilio->listChannelMessages($address, $after, $limit) as $message) {
                $bySid[$message->sid] = $message;
            }
        }

        $scanned = count($bySid);
        $imported = 0;
        $skipped = 0;
        $touchedConversationIds = [];

        $existingMids = [];
        $sids = array_keys($bySid);
        if ($sids !== []) {
            $existingMids = FacebookMessage::query()
                ->where('company_id', $integration->company_id)
                ->whereIn('mid', $sids)
                ->pluck('mid')
                ->all();
        }
        $existingLookup = array_fill_keys($existingMids, true);

        foreach ($bySid as $message) {
            if (isset($existingLookup[$message->sid])) {
                $skipped++;

                continue;
            }

            try {
                $conversation = $this->ingest($integration, $twilio, $message);
                if ($conversation) {
                    $imported++;
                    $touchedConversationIds[$conversation->id] = $conversation;
                    $existingLookup[$message->sid] = true;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $skipped++;
                Log::warning('Facebook history sync skipped a message', [
                    'sid' => $message->sid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($touchedConversationIds as $conversation) {
            $this->refreshPreview($conversation);
        }

        return [
            'scanned' => $scanned,
            'imported' => $imported,
            'skipped' => $skipped,
            'conversations' => count($touchedConversationIds),
            'days' => $days,
        ];
    }

    protected function ingest(
        FacebookIntegration $integration,
        TwilioService $twilio,
        MessageInstance $message
    ): ?FacebookConversation {
        $from = TwilioService::parseMessengerAddress((string) $message->from);
        $to = TwilioService::parseMessengerAddress((string) $message->to);
        $ownIds = array_filter([
            (string) $integration->page_id,
            (string) $integration->instagram_business_account_id,
        ]);

        $fromIsOwn = in_array($from['id'], $ownIds, true);
        $toIsOwn = in_array($to['id'], $ownIds, true);

        if ($fromIsOwn && $toIsOwn) {
            return null;
        }

        if ($fromIsOwn) {
            $direction = 'outbound';
            $peer = $to;
        } else {
            $direction = 'inbound';
            $peer = $from;
        }

        if ($peer['id'] === '' || in_array($peer['id'], $ownIds, true)) {
            return null;
        }

        $channel = in_array($peer['channel'], ['messenger', 'instagram'], true)
            ? $peer['channel']
            : 'messenger';

        $conversation = $this->upsertConversation($integration, $channel, $peer['id']);
        $sentAt = $message->dateSent
            ? Carbon::instance($message->dateSent)
            : ($message->dateCreated ? Carbon::instance($message->dateCreated) : now());

        $type = 'text';
        $mediaUrl = null;
        $mimeType = null;
        $fileName = null;
        $text = is_string($message->body) ? $message->body : null;
        $numMedia = (int) ($message->numMedia ?? 0);

        if ($numMedia > 0) {
            try {
                $media = $twilio->firstMessageMedia((string) $message->sid);
                if ($media) {
                    $mimeType = $media['content_type'];
                    $type = $this->guessMediaType($mimeType);
                    $mediaUrl = $this->storeMedia(
                        $integration,
                        $twilio,
                        $media['url'],
                        $mimeType,
                        (string) $message->sid
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Facebook history media download failed', [
                    'sid' => $message->sid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $record = new FacebookMessage;
        $record->fill([
            'company_id' => $integration->company_id,
            'facebook_conversation_id' => $conversation->id,
            'direction' => $direction,
            'mid' => (string) $message->sid,
            'type' => $type,
            'text' => $text,
            'media_url' => $mediaUrl,
            'mime_type' => $mimeType,
            'file_name' => $fileName,
            'status' => $message->status ?: ($direction === 'inbound' ? 'received' : 'sent'),
            'raw_payload' => [
                'sid' => $message->sid,
                'from' => $message->from,
                'to' => $message->to,
                'status' => $message->status,
                'direction' => $message->direction,
                'synced' => true,
            ],
            'sent_at' => $sentAt,
        ]);
        $record->created_at = $sentAt;
        $record->updated_at = $sentAt;
        $record->save();

        return $conversation;
    }

    protected function upsertConversation(
        FacebookIntegration $integration,
        string $channel,
        string $peerId
    ): FacebookConversation {
        $conversation = FacebookConversation::firstOrNew([
            'company_id' => $integration->company_id,
            'channel' => $channel,
            'peer_id' => $peerId,
        ]);

        if (! $conversation->name) {
            $conversation->name = $channel === 'instagram' ? 'Instagram User' : 'Messenger User';
        }

        $conversation->save();

        $this->leadAutoCreate->fromFacebook(
            (int) $integration->company_id,
            $channel,
            $conversation->name,
            $conversation->username
        );

        return $conversation;
    }

    protected function storeMedia(
        FacebookIntegration $integration,
        TwilioService $twilio,
        string $remoteUrl,
        ?string $mimeType,
        string $messageSid
    ): string {
        $binary = $twilio->downloadMedia($remoteUrl);
        $ext = match (true) {
            $mimeType && str_contains($mimeType, 'jpeg') => 'jpg',
            $mimeType && str_contains($mimeType, 'png') => 'png',
            $mimeType && str_contains($mimeType, 'webp') => 'webp',
            $mimeType && str_contains($mimeType, 'gif') => 'gif',
            $mimeType && str_contains($mimeType, 'mp4') => 'mp4',
            $mimeType && str_contains($mimeType, 'ogg') => 'ogg',
            $mimeType && str_contains($mimeType, 'mpeg') => 'mp3',
            $mimeType && str_contains($mimeType, 'pdf') => 'pdf',
            default => 'bin',
        };

        $path = 'facebook/'.$integration->company_id.'/inbound/'.date('Y/m').'/'.$messageSid.'-'.Str::random(6).'.'.$ext;
        Storage::disk('public')->put($path, $binary);

        return public_media_url($path);
    }

    protected function guessMediaType(?string $mimeType): string
    {
        $mime = strtolower((string) $mimeType);

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'file',
        };
    }

    protected function refreshPreview(FacebookConversation $conversation): void
    {
        $latest = FacebookMessage::query()
            ->where('facebook_conversation_id', $conversation->id)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();

        if (! $latest) {
            return;
        }

        $preview = match ($latest->type) {
            'text' => (string) $latest->text,
            'image' => '[Image]',
            'video' => '[Video]',
            'audio' => '[Audio]',
            'file' => '[File] '.($latest->file_name ?: ''),
            default => '['.ucfirst($latest->type).']',
        };

        $conversation->last_message_preview = Str::limit(trim($preview), 480);
        $conversation->last_message_at = $latest->sent_at ?: now();
        $conversation->save();
    }
}
