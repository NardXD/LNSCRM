<?php

namespace App\Services;

use App\Models\FacebookConversation;
use App\Models\FacebookIntegration;
use App\Models\FacebookMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Twilio\Rest\Api\V2010\Account\MessageInstance;
use Twilio\Rest\Client;

class FacebookMessageSyncService
{
    protected bool $lastStoreCreated = true;

    /**
     * Import historical Messenger inbox (and Twilio) messages into the CRM.
     *
     * @return array{scanned: int, imported: int, skipped: int, conversations: int, days: int, hint: ?string, sources: array{graph: int, messages: int, conversations: int}}
     */
    public function sync(FacebookIntegration $integration, ?TwilioService $twilio, int $days = 90, int $limit = 2000): array
    {
        $this->correctNaiveUtcTimestamps($integration);

        $after = $days > 0 ? Carbon::now()->subDays($days)->startOfDay() : null;
        $bySid = [];
        if ($twilio) {
            $addresses = array_values(array_unique(array_filter([
                $twilio->messengerAddress((string) $integration->page_id, 'messenger'),
                $integration->instagram_business_account_id
                    ? $twilio->messengerAddress((string) $integration->instagram_business_account_id, 'instagram')
                    : null,
            ])));

            foreach ($addresses as $address) {
                foreach ($twilio->listChannelMessages($address, $after, $limit) as $message) {
                    $bySid[$message->sid] = $message;
                }
            }
        }

        $imported = 0;
        $skipped = 0;
        $touchedConversationIds = [];
        $graphCount = 0;
        $hint = null;
        $existingLookup = [];
        $graphError = null;

        $token = $integration->getDecryptedPageAccessToken();
        if ($token) {
            try {
                $graph = app(FacebookGraphHistoryService::class);
                $platforms = ['messenger'];
                if ($integration->instagram_business_account_id) {
                    $platforms[] = 'instagram';
                }
                $graphRows = $graph->history(
                    (string) $integration->page_id,
                    $token,
                    $after,
                    min(3000, max(200, $limit)),
                    55,
                    $this->ownIds($integration),
                    $platforms
                );
                $graphCount = count($graphRows);
                $graphError = $graph->lastError();
                $existingLookup = $this->existingMids($integration, array_column($graphRows, 'mid'));

                foreach ($graphRows as $row) {
                    $result = $this->importIfNew($existingLookup, (string) $row['mid'], function () use ($integration, $row) {
                        return $this->storeRecord(
                            $integration,
                            $row['channel'],
                            $row['peer_id'],
                            $row['name'],
                            $row['direction'],
                            $row['mid'],
                            $row['text'],
                            $row['type'],
                            $row['media_url'],
                            $row['mime_type'],
                            $row['status'],
                            $row['sent_at'],
                            $row['raw']
                        );
                    });
                    $imported += $result['imported'];
                    $skipped += $result['skipped'];
                    if ($result['conversation']) {
                        $touchedConversationIds[$result['conversation']->id] = $result['conversation'];
                    }
                }

                if ($graphCount === 0 && $graphError) {
                    $hint = $this->graphHint($graphError);
                }
            } catch (\Throwable $e) {
                Log::warning('Facebook Graph history sync failed', ['error' => $e->getMessage()]);
                $hint = $this->graphHint($e->getMessage());
                $graphError = $e->getMessage();
            }
        } else {
            $hint = 'Twilio only has messages that already passed through this Twilio account. Save a Facebook Page Access Token under Integrations to import replies sent from Messenger / Page Inbox.';
        }

        $conversationImported = 0;
        if ($twilio) {
            $existingLookup = $this->existingMids(
                $integration,
                array_merge(array_keys($existingLookup), array_keys($bySid))
            );

            foreach ($bySid as $message) {
                $result = $this->importIfNew($existingLookup, $message->sid, function () use ($integration, $twilio, $message) {
                    return $this->ingestProgrammableMessage($integration, $twilio, $message);
                });
                $imported += $result['imported'];
                $skipped += $result['skipped'];
                if ($result['conversation']) {
                    $touchedConversationIds[$result['conversation']->id] = $result['conversation'];
                }
            }

            try {
                $conversationRows = $this->collectConversationMessages($twilio, $integration, $after, min($limit, 200));
                $conversationImported = count($conversationRows);
                $existingLookup = $this->existingMids(
                    $integration,
                    array_merge(array_keys($existingLookup), array_column($conversationRows, 'mid'))
                );

                foreach ($conversationRows as $row) {
                    $result = $this->importIfNew($existingLookup, $row['mid'], function () use ($integration, $row) {
                        return $this->storeRecord(
                            $integration,
                            $row['channel'],
                            $row['peer_id'],
                            $row['name'],
                            $row['direction'],
                            $row['mid'],
                            $row['text'],
                            $row['type'],
                            $row['media_url'],
                            $row['mime_type'],
                            $row['status'],
                            $row['sent_at'],
                            $row['raw']
                        );
                    });
                    $imported += $result['imported'];
                    $skipped += $result['skipped'];
                    if ($result['conversation']) {
                        $touchedConversationIds[$result['conversation']->id] = $result['conversation'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Facebook Conversations history sync failed', ['error' => $e->getMessage()]);
            }
        }

        foreach ($touchedConversationIds as $conversation) {
            $this->refreshPreview($conversation);
        }

        return [
            'scanned' => count($bySid) + $conversationImported + $graphCount,
            'imported' => $imported,
            'skipped' => $skipped,
            'conversations' => count($touchedConversationIds),
            'days' => $days,
            'hint' => $hint,
            'graph_error' => $graphError,
            'sources' => [
                'graph' => $graphCount,
                'messages' => count($bySid),
                'conversations' => $conversationImported,
            ],
        ];
    }

    protected function graphHint(?string $error): string
    {
        $graph = app(FacebookGraphMessagingService::class);
        if ($graph->isExpiredTokenError($error)) {
            return $graph->expiredTokenMessage();
        }
        if ($graph->isMailboxPermissionError($error)) {
            return $graph->mailboxPermissionMessage();
        }

        return 'Facebook inbox import failed: '.($error ?: 'unknown error');
    }

    /**
     * @param  array<string, true>  $existingLookup
     * @return array{imported: int, skipped: int, conversation: ?FacebookConversation}
     */
    protected function importIfNew(array &$existingLookup, string $mid, callable $importer): array
    {
        if (isset($existingLookup[$mid])) {
            return ['imported' => 0, 'skipped' => 1, 'conversation' => null];
        }

        try {
            $conversation = $importer();
            if ($conversation) {
                $existingLookup[$mid] = true;
                if (! $this->lastStoreCreated) {
                    return ['imported' => 0, 'skipped' => 1, 'conversation' => $conversation];
                }

                return ['imported' => 1, 'skipped' => 0, 'conversation' => $conversation];
            }
        } catch (\Throwable $e) {
            Log::warning('Facebook history sync skipped a message', [
                'sid' => $mid,
                'error' => $e->getMessage(),
            ]);
        }

        return ['imported' => 0, 'skipped' => 1, 'conversation' => null];
    }

    /**
     * @param  array<int, string>  $sids
     * @return array<string, true>
     */
    protected function existingMids(FacebookIntegration $integration, array $sids): array
    {
        $sids = array_values(array_filter($sids));
        if ($sids === []) {
            return [];
        }

        $found = [];
        foreach (array_chunk($sids, 500) as $chunk) {
            $found = array_merge(
                $found,
                FacebookMessage::query()
                    ->where('company_id', $integration->company_id)
                    ->whereIn('mid', $chunk)
                    ->pluck('mid')
                    ->all()
            );
        }

        return array_fill_keys($found, true);
    }

    /**
     * Import recent Messenger / Instagram messages (Twilio + optional Page Inbox via Graph).
     * Used while /facebook is open and by background auto-sync so new chats and
     * replies sent from Messenger appear without a full history sync.
     *
     * @return array{imported: int, hint: ?string}
     */
    public function ingestRecent(
        FacebookIntegration $integration,
        ?TwilioService $twilio,
        int $minutes = 45,
        int $limit = 80,
        bool $includeGraph = true
    ): array {
        $after = Carbon::now()->subMinutes(max(5, $minutes));
        $imported = 0;
        $touched = [];
        $existingLookup = [];

        if ($twilio) {
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

            $existingLookup = $this->existingMids($integration, array_keys($bySid));

            foreach ($bySid as $message) {
                $result = $this->importIfNew($existingLookup, $message->sid, function () use ($integration, $twilio, $message) {
                    return $this->ingestProgrammableMessage($integration, $twilio, $message, true);
                });
                $imported += $result['imported'];
                if ($result['conversation']) {
                    $touched[$result['conversation']->id] = $result['conversation'];
                }
            }
        }

        foreach ($touched as $conversation) {
            $this->refreshPreview($conversation);
        }

        $hint = null;
        if ($includeGraph) {
            $token = $integration->getDecryptedPageAccessToken();
            if (! $token) {
                $hint = 'Twilio does not receive replies you send from the Messenger app. Save a Page Access Token under Integrations so the CRM can import Page Inbox messages.';
            } else {
                try {
                    $graph = app(FacebookGraphHistoryService::class);
                    $platforms = ['messenger'];
                    if ($integration->instagram_business_account_id) {
                        $platforms[] = 'instagram';
                    }
                    $rows = $graph->history(
                        (string) $integration->page_id,
                        $token,
                        $after,
                        min(400, max(60, $limit * 3)),
                        25,
                        $this->ownIds($integration),
                        $platforms
                    );
                    $imported += $this->importGraphRows($integration, $rows);
                    if ($imported === 0 && $graph->lastError()) {
                        $hint = $this->graphHint($graph->lastError());
                    }
                } catch (\Throwable $e) {
                    Log::warning('Facebook recent Graph pull failed', ['error' => $e->getMessage()]);
                    $hint = $this->graphHint($e->getMessage());
                }
            }
        }

        return ['imported' => $imported, 'hint' => $hint];
    }

    /**
     * Import Graph Page Inbox rows (used when opening a conversation).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function importGraphRows(FacebookIntegration $integration, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $existingLookup = $this->existingMids($integration, array_column($rows, 'mid'));
        $imported = 0;
        $touched = [];

        foreach ($rows as $row) {
            $mid = (string) ($row['mid'] ?? '');
            if ($mid === '') {
                continue;
            }
            $result = $this->importIfNew($existingLookup, $mid, function () use ($integration, $row) {
                return $this->storeRecord(
                    $integration,
                    $row['channel'],
                    $row['peer_id'],
                    $row['name'],
                    $row['direction'],
                    $row['mid'],
                    $row['text'],
                    $row['type'],
                    $row['media_url'],
                    $row['mime_type'],
                    $row['status'],
                    $row['sent_at'],
                    $row['raw']
                );
            });
            $imported += $result['imported'];
            if ($result['conversation']) {
                $touched[$result['conversation']->id] = $result['conversation'];
            }
        }

        foreach ($touched as $conversation) {
            $this->refreshPreview($conversation);
        }

        return $imported;
    }

    protected function ingestProgrammableMessage(
        FacebookIntegration $integration,
        TwilioService $twilio,
        MessageInstance $message,
        bool $countAsUnread = false
    ): ?FacebookConversation {
        $from = TwilioService::parseMessengerAddress((string) $message->from);
        $to = TwilioService::parseMessengerAddress((string) $message->to);
        $ownIds = $this->ownIds($integration);

        $fromIsSocial = $this->isSocialPrefix((string) $message->from);
        $toIsSocial = $this->isSocialPrefix((string) $message->to);
        if (! $fromIsSocial && ! $toIsSocial) {
            return null;
        }

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
            : (($fromIsSocial ? $from['channel'] : $to['channel']) ?: 'messenger');

        $type = 'text';
        $mediaUrl = null;
        $mimeType = null;
        $text = is_string($message->body) ? $message->body : null;
        $numMedia = (int) ($message->numMedia ?? 0);

        if ($numMedia > 0) {
            try {
                $media = $twilio->firstMessageMedia((string) $message->sid);
                if ($media) {
                    $mimeType = $media['content_type'];
                    $type = $this->guessMediaType($mimeType);
                    $mediaUrl = $this->storeMedia($integration, $twilio, $media['url'], $mimeType, (string) $message->sid);
                }
            } catch (\Throwable $e) {
                Log::warning('Facebook history media download failed', [
                    'sid' => $message->sid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $sentAt = TimezoneService::fromExternal($message->dateSent ?: $message->dateCreated);

        return $this->storeRecord(
            $integration,
            $channel,
            $peer['id'],
            null,
            $direction,
            (string) $message->sid,
            $text,
            $type,
            $mediaUrl,
            $mimeType,
            $message->status ?: ($direction === 'inbound' ? 'received' : 'sent'),
            $sentAt,
            [
                'sid' => $message->sid,
                'from' => $message->from,
                'to' => $message->to,
                'status' => $message->status,
                'direction' => $message->direction,
                'synced' => true,
                'source' => 'messages',
            ],
            $countAsUnread
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function collectConversationMessages(
        TwilioService $twilio,
        FacebookIntegration $integration,
        ?Carbon $after,
        int $limit
    ): array {
        $client = $twilio->getTwilioClient();
        $ownIds = $this->ownIds($integration);
        $rows = [];
        $seenConversationSids = [];

        // Default Conversations service only. Scanning every Flex service
        // walks WhatsApp/SMS threads and exceeds the HTTP timeout.
        foreach ($this->conversationSidsForPage($client, null, $integration, $limit) as $conversationSid) {
            if (isset($seenConversationSids[$conversationSid])) {
                continue;
            }
            $seenConversationSids[$conversationSid] = true;

            try {
                $context = $this->conversationContext($client, null, $conversationSid);
                $participants = $context->participants->read([], 50);
                $thread = $this->messengerThreadFromParticipants($participants, $ownIds);
                if (! $thread) {
                    continue;
                }

                $friendlyName = null;
                try {
                    $conversation = $context->fetch();
                    $friendlyName = $conversation->friendlyName ?: null;
                } catch (\Throwable) {
                    // optional
                }

                    foreach ($context->messages->stream(['order' => 'asc'], $limit, 100) as $message) {
                        $sentAt = TimezoneService::fromExternal($message->dateCreated);
                    if ($after && $sentAt->lt($after)) {
                        continue;
                    }

                    $author = (string) ($message->author ?? '');
                    $authorParsed = TwilioService::parseMessengerAddress($author);
                    $authorIsPeer = $authorParsed['id'] === $thread['peer_id']
                        || $author === $thread['peer_id']
                        || (str_contains(strtolower($author), 'messenger:') && $authorParsed['id'] === $thread['peer_id']);

                    $rows[] = [
                        'mid' => (string) $message->sid,
                        'channel' => $thread['channel'],
                        'peer_id' => $thread['peer_id'],
                        'name' => $friendlyName && ! str_starts_with((string) $friendlyName, 'CH') ? $friendlyName : null,
                        'direction' => $authorIsPeer ? 'inbound' : 'outbound',
                        'text' => is_string($message->body) ? $message->body : null,
                        'type' => ! empty($message->media) ? $this->guessMediaType($message->media[0]['content_type'] ?? null) : 'text',
                        'media_url' => null,
                        'mime_type' => ! empty($message->media) ? ($message->media[0]['content_type'] ?? null) : null,
                        'status' => 'received',
                        'sent_at' => $sentAt,
                        'raw' => [
                            'sid' => $message->sid,
                            'conversation_sid' => $conversationSid,
                            'author' => $author,
                            'synced' => true,
                            'source' => 'conversations',
                        ],
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Facebook conversation history skipped', [
                    'conversation_sid' => $conversationSid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    protected function conversationSidsForPage(
        Client $client,
        ?string $serviceSid,
        FacebookIntegration $integration,
        int $limit
    ): array {
        $sids = [];
        $addresses = array_values(array_unique(array_filter([
            'messenger:'.$integration->page_id,
            $integration->page_id,
            $integration->instagram_business_account_id ? 'instagram:'.$integration->instagram_business_account_id : null,
        ])));

        $participantList = $serviceSid
            ? $client->conversations->v1->services($serviceSid)->participantConversations
            : $client->conversations->v1->participantConversations;

        foreach ($addresses as $address) {
            try {
                foreach ($participantList->stream(['address' => $address], $limit, 50) as $row) {
                    if ($row->conversationSid) {
                        $sids[$row->conversationSid] = true;
                    }
                }
            } catch (\Throwable $e) {
                Log::info('Twilio ParticipantConversations lookup skipped', [
                    'address' => $address,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return array_keys($sids);
    }

    protected function conversationContext(Client $client, ?string $serviceSid, string $conversationSid)
    {
        if ($serviceSid) {
            return $client->conversations->v1->services($serviceSid)->conversations($conversationSid);
        }

        return $client->conversations->v1->conversations($conversationSid);
    }

    /**
     * @param  array<int, object>  $participants
     * @param  array<int, string>  $ownIds
     * @return array{channel: string, peer_id: string}|null
     */
    protected function messengerThreadFromParticipants(array $participants, array $ownIds): ?array
    {
        foreach ($participants as $participant) {
            $binding = $participant->messagingBinding ?? [];
            foreach (['address', 'proxy_address'] as $key) {
                $value = (string) ($binding[$key] ?? '');
                if (! $this->isSocialPrefix($value)) {
                    continue;
                }
                $parsed = TwilioService::parseMessengerAddress($value);
                if ($parsed['id'] === '' || in_array($parsed['id'], $ownIds, true)) {
                    continue;
                }

                return [
                    'channel' => in_array($parsed['channel'], ['messenger', 'instagram'], true) ? $parsed['channel'] : 'messenger',
                    'peer_id' => $parsed['id'],
                ];
            }
        }

        return null;
    }

    protected function storeRecord(
        FacebookIntegration $integration,
        string $channel,
        string $peerId,
        ?string $name,
        string $direction,
        string $mid,
        ?string $text,
        string $type,
        ?string $mediaUrl,
        ?string $mimeType,
        ?string $status,
        Carbon $sentAt,
        array $raw,
        bool $countAsUnread = false
    ): FacebookConversation {
        $this->lastStoreCreated = true;
        $conversation = $this->upsertConversation($integration, $channel, $peerId, $name);
        $storedAt = TimezoneService::fromExternal($sentAt);

        $duplicate = $this->findNearDuplicate($conversation->id, $direction, $type, $text, $storedAt);
        if ($duplicate) {
            $this->lastStoreCreated = false;
            $payload = is_array($duplicate->raw_payload) ? $duplicate->raw_payload : [];
            if ($mid !== '' && empty($payload['graph_mid']) && (string) $duplicate->mid !== $mid) {
                $payload['graph_mid'] = $mid;
                $duplicate->raw_payload = $payload;
                $duplicate->save();
            }

            return $conversation;
        }

        $record = new FacebookMessage;
        $record->fill([
            'company_id' => $integration->company_id,
            'facebook_conversation_id' => $conversation->id,
            'direction' => $direction,
            'mid' => $mid,
            'type' => $type,
            'text' => $text,
            'media_url' => $mediaUrl,
            'mime_type' => $mimeType,
            'status' => $status,
            'raw_payload' => array_merge($raw, ['tz' => 'app']),
            'sent_at' => $storedAt,
        ]);
        $record->created_at = $storedAt;
        $record->updated_at = $storedAt;
        $record->save();

        // Live poll/ingest only — bulk history sync must not flood unread badges.
        if ($countAsUnread && $direction === 'inbound') {
            $conversation->unread_count = (int) $conversation->unread_count + 1;
            $conversation->save();
        }

        return $conversation;
    }

    public function findNearDuplicate(
        int $conversationId,
        string $direction,
        string $type,
        ?string $text,
        Carbon $sentAt
    ): ?FacebookMessage {
        $from = $sentAt->copy()->subMinutes(3);
        $to = $sentAt->copy()->addMinutes(3);
        $normalized = trim((string) $text);

        $query = FacebookMessage::query()
            ->where('facebook_conversation_id', $conversationId)
            ->where('direction', $direction)
            ->where('type', $type)
            ->whereBetween('sent_at', [$from, $to]);

        if ($normalized !== '') {
            $query->where('text', $text);
        } else {
            $query->where(function ($builder) {
                $builder->whereNull('text')->orWhere('text', '');
            });
        }

        return $query->orderByDesc('id')->first();
    }

    protected function upsertConversation(
        FacebookIntegration $integration,
        string $channel,
        string $peerId,
        ?string $name = null
    ): FacebookConversation {
        $conversation = FacebookConversation::firstOrNew([
            'company_id' => $integration->company_id,
            'channel' => $channel,
            'peer_id' => $peerId,
        ]);

        $placeholder = $channel === 'instagram' ? 'Instagram User' : 'Messenger User';
        if ($name && $name !== $placeholder) {
            $conversation->name = $name;
        } elseif (! $conversation->name) {
            $conversation->name = $placeholder;
        }

        $conversation->save();

        return $conversation;
    }

    /**
     * @return array<int, string>
     */
    protected function ownIds(FacebookIntegration $integration): array
    {
        return array_values(array_filter([
            (string) $integration->page_id,
            (string) $integration->instagram_business_account_id,
        ]));
    }

    protected function isSocialPrefix(string $address): bool
    {
        $lower = strtolower(trim($address));

        return str_starts_with($lower, 'messenger:') || str_starts_with($lower, 'instagram:');
    }

    protected function storeRemoteMedia(
        FacebookIntegration $integration,
        string $remoteUrl,
        ?string $mimeType,
        string $messageSid
    ): string {
        $response = Http::timeout(60)->get($remoteUrl);
        if (! $response->successful()) {
            throw new \RuntimeException('Failed to download Facebook media (HTTP '.$response->status().').');
        }

        $contentType = strtolower((string) ($response->header('Content-Type') ?: $mimeType));
        $ext = match (true) {
            str_contains($contentType, 'jpeg') => 'jpg',
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'gif') => 'gif',
            str_contains($contentType, 'mp4') => 'mp4',
            str_contains($contentType, 'pdf') => 'pdf',
            default => 'bin',
        };

        $path = 'facebook/'.$integration->company_id.'/inbound/'.date('Y/m').'/'.$messageSid.'-'.Str::random(6).'.'.$ext;
        Storage::disk('public')->put($path, $response->body());

        return public_media_url($path);
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

    /**
     * Synced rows were saved with UTC wall-clock in a naive datetime column.
     * Re-read those clocks as UTC and persist in the app timezone once.
     */
    public function correctNaiveUtcTimestamps(FacebookIntegration $integration): int
    {
        $fixed = 0;
        $touched = [];

        FacebookMessage::query()
            ->where('company_id', $integration->company_id)
            ->orderBy('id')
            ->chunkById(200, function ($messages) use (&$fixed, &$touched) {
                foreach ($messages as $message) {
                    $payload = is_array($message->raw_payload) ? $message->raw_payload : [];
                    if (($payload['tz'] ?? null) === 'app') {
                        continue;
                    }

                    $source = (string) ($payload['source'] ?? '');
                    $synced = (bool) ($payload['synced'] ?? false);
                    if (! $synced && ! in_array($source, ['graph', 'messages', 'conversations'], true)) {
                        continue;
                    }

                    $raw = $message->getRawOriginal('sent_at');
                    if (! $raw) {
                        continue;
                    }

                    $message->sent_at = Carbon::parse($raw, 'UTC')->timezone(config('app.timezone'));
                    $payload['tz'] = 'app';
                    $message->raw_payload = $payload;
                    $message->save();
                    $fixed++;
                    $touched[$message->facebook_conversation_id] = true;
                }
            });

        if ($touched !== []) {
            FacebookConversation::query()
                ->whereIn('id', array_keys($touched))
                ->get()
                ->each(fn (FacebookConversation $conversation) => $this->refreshPreview($conversation));
        }

        return $fixed;
    }
}
