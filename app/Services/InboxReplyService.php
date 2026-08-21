<?php

namespace App\Services;

use App\Models\InboxConversation;
use App\Models\InboxConversationActivity;
use App\Models\InboxMessage;
use App\Models\ScheduledInboxReply;
use App\Models\SharedInbox;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InboxReplyService
{
    public function __construct(
        protected OutlookMailService $mailService,
        protected LeadAutoCreateService $leadAutoCreate,
    ) {}

    /**
     * @param  array{
     *     body: string,
     *     to: string,
     *     cc?: ?string,
     *     attachments?: array<int, array{name: string, contentType: string, contentBytes: string}>,
     *     archive?: bool,
     *     reply_to_message_id?: ?string,
     * }  $payload
     * @return array{message: InboxMessage, conversation: InboxConversation}
     */
    public function send(InboxConversation $conversation, SharedInbox $inbox, User $actor, array $payload): array
    {
        $attachments = $payload['attachments'] ?? [];
        $to = (string) $payload['to'];
        $cc = $payload['cc'] ?? null;
        $body = (string) $payload['body'];
        $archive = (bool) ($payload['archive'] ?? false);

        $result = $this->mailService->sendMail($inbox, [
            'to' => $to,
            'cc' => $cc,
            'subject' => (str_starts_with(strtolower((string) $conversation->subject), 're:') ? '' : 'Re: ').$conversation->subject,
            'body' => $body,
            'reply_to_message_id' => $payload['reply_to_message_id'] ?? null,
            'attachments' => $attachments,
            'honor_recipients' => true,
        ]);

        if (! $result) {
            throw new \RuntimeException('Failed to send via Outlook.');
        }

        $message = InboxMessage::create([
            'inbox_conversation_id' => $conversation->id,
            'external_message_id' => 'local-'.uniqid(),
            'direction' => 'outbound',
            'from_name' => $actor->name,
            'from_email' => $inbox->email ?? $inbox->account?->email,
            'to_emails' => $to,
            'cc_emails' => $cc,
            'subject' => $conversation->subject,
            'body_html' => $body,
            'body_text' => strip_tags($body),
            'is_read' => true,
            'sent_at' => now(),
        ]);

        $conversation->update([
            'last_message_at' => now(),
            'snippet' => mb_substr(strip_tags($body), 0, 500),
            'message_count' => $conversation->messages()->count(),
            'status' => $archive ? 'archived' : 'open',
            'reopen_at' => null,
        ]);

        return [
            'message' => $message,
            'conversation' => $conversation->fresh(['assignee', 'tags', 'inbox']) ?? $conversation,
        ];
    }

    /**
     * @param  array<int, array{name: string, contentType: string, contentBytes: string}>  $attachments
     * @return array<int, array{name: string, contentType: string, path: string}>
     */
    public function storeScheduledAttachments(ScheduledInboxReply $scheduled, array $attachments): array
    {
        $stored = [];
        foreach ($attachments as $index => $attachment) {
            $binary = base64_decode($attachment['contentBytes'], true);
            if ($binary === false) {
                continue;
            }
            $safeName = Str::slug(pathinfo($attachment['name'], PATHINFO_FILENAME)) ?: 'file';
            $ext = pathinfo($attachment['name'], PATHINFO_EXTENSION);
            $filename = $index.'_'.$safeName.($ext !== '' ? '.'.$ext : '');
            $path = 'scheduled-inbox-replies/'.$scheduled->id.'/'.$filename;
            Storage::disk('local')->put($path, $binary);
            $stored[] = [
                'name' => $attachment['name'],
                'contentType' => $attachment['contentType'] ?? 'application/octet-stream',
                'path' => $path,
            ];
        }

        return $stored;
    }

    /**
     * @return array<int, array{name: string, contentType: string, contentBytes: string}>
     */
    public function loadScheduledAttachments(ScheduledInboxReply $scheduled): array
    {
        $loaded = [];
        foreach ($scheduled->attachments ?? [] as $attachment) {
            $path = (string) ($attachment['path'] ?? '');
            if ($path === '' || ! Storage::disk('local')->exists($path)) {
                continue;
            }
            $binary = Storage::disk('local')->get($path);
            $loaded[] = [
                'name' => (string) ($attachment['name'] ?? 'attachment'),
                'contentType' => (string) ($attachment['contentType'] ?? 'application/octet-stream'),
                'contentBytes' => base64_encode($binary),
            ];
        }

        return $loaded;
    }

    public function deleteScheduledAttachmentFiles(ScheduledInboxReply $scheduled): void
    {
        foreach ($scheduled->attachments ?? [] as $attachment) {
            $path = (string) ($attachment['path'] ?? '');
            if ($path !== '' && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }
        $dir = 'scheduled-inbox-replies/'.$scheduled->id;
        if (Storage::disk('local')->exists($dir)) {
            Storage::disk('local')->deleteDirectory($dir);
        }
    }

    /**
     * Send a brand-new outbound message (compose), optionally updating an existing draft conversation.
     *
     * @param  array{
     *     body: string,
     *     to: string,
     *     cc?: ?string,
     *     subject: string,
     *     attachments?: array<int, array{name: string, contentType: string, contentBytes: string}>,
     * }  $payload
     * @return array{message: InboxMessage, conversation: InboxConversation}
     */
    public function sendCompose(
        SharedInbox $inbox,
        User $actor,
        array $payload,
        ?InboxConversation $draft = null
    ): array {
        $attachments = $payload['attachments'] ?? [];
        $to = (string) $payload['to'];
        $cc = $payload['cc'] ?? null;
        $subject = (string) $payload['subject'];
        $body = (string) $payload['body'];

        $result = $this->mailService->sendMail($inbox, [
            'to' => $to,
            'cc' => $cc,
            'subject' => $subject,
            'body' => $body,
            'attachments' => $attachments,
        ]);

        if (! $result) {
            throw new \RuntimeException('Failed to send via Outlook.');
        }

        $fromEmail = $inbox->email ?? $inbox->account?->email;
        $localId = 'local-compose-'.uniqid();
        $snippet = mb_substr(strip_tags($body), 0, 500);

        if ($draft) {
            $draft->update([
                'folder' => 'sent',
                'external_conversation_id' => $draft->external_conversation_id ?: $localId,
                'subject' => $subject,
                'snippet' => $snippet,
                'from_name' => $actor->name,
                'from_email' => $fromEmail,
                'status' => 'sent',
                'is_read' => true,
                'message_count' => $draft->messages()->count() + 1,
                'last_message_at' => now(),
                'reopen_at' => null,
            ]);
            $conversation = $draft;
        } else {
            $conversation = InboxConversation::create([
                'company_id' => $actor->company_id,
                'shared_inbox_id' => $inbox->id,
                'folder' => 'sent',
                'external_conversation_id' => $localId,
                'subject' => $subject,
                'snippet' => $snippet,
                'from_name' => $actor->name,
                'from_email' => $fromEmail,
                'status' => 'sent',
                'assigned_to' => $actor->id,
                'is_read' => true,
                'message_count' => 1,
                'last_message_at' => now(),
            ]);
        }

        $message = InboxMessage::create([
            'inbox_conversation_id' => $conversation->id,
            'external_message_id' => $localId,
            'direction' => 'outbound',
            'from_name' => $actor->name,
            'from_email' => $fromEmail,
            'to_emails' => $to,
            'cc_emails' => $cc,
            'subject' => $subject,
            'body_html' => $body,
            'body_text' => strip_tags($body),
            'is_read' => true,
            'sent_at' => now(),
        ]);

        if ($draft) {
            $conversation->update(['message_count' => $conversation->messages()->count()]);
        }

        return [
            'message' => $message,
            'conversation' => $conversation->fresh(['assignee', 'tags', 'inbox', 'messages']) ?? $conversation,
        ];
    }

    /**
     * Send any pending scheduled replies/composes whose send_at has passed.
     *
     * @return array{sent: int, failed: int}
     */
    public function processDue(int $limit = 50): array
    {
        $sent = 0;
        $failed = 0;

        ScheduledInboxReply::query()
            ->where('status', ScheduledInboxReply::STATUS_SENDING)
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update([
                'status' => ScheduledInboxReply::STATUS_PENDING,
                'error_message' => null,
            ]);

        $due = ScheduledInboxReply::query()
            ->where('status', ScheduledInboxReply::STATUS_PENDING)
            ->where('send_at', '<=', now())
            ->orderBy('send_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        foreach ($due as $scheduled) {
            $fresh = $scheduled->fresh(['conversation', 'user', 'inbox.account']);
            if (! $fresh) {
                $failed++;
                continue;
            }

            $result = $this->dispatchScheduled($fresh);
            if ($result) {
                $sent++;
            } else {
                $failed++;
                Log::warning('Scheduled inbox send did not complete', [
                    'scheduled_id' => $fresh->id,
                    'status' => $fresh->fresh()?->status,
                    'error' => $fresh->fresh()?->error_message,
                ]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * @return array{message: InboxMessage, conversation: InboxConversation}|null
     */
    public function dispatchScheduled(ScheduledInboxReply $scheduled): ?array
    {
        if ($scheduled->status !== ScheduledInboxReply::STATUS_PENDING) {
            return null;
        }

        $scheduled->status = ScheduledInboxReply::STATUS_SENDING;
        $scheduled->save();

        $conversation = $scheduled->conversation;
        $actor = $scheduled->user;
        if (! $actor) {
            $scheduled->update([
                'status' => ScheduledInboxReply::STATUS_FAILED,
                'error_message' => 'Sender missing.',
            ]);

            return null;
        }

        if ($scheduled->isCompose()) {
            return $this->dispatchScheduledCompose($scheduled, $actor, $conversation);
        }

        if (! $conversation) {
            $scheduled->update([
                'status' => ScheduledInboxReply::STATUS_FAILED,
                'error_message' => 'Conversation missing.',
            ]);

            return null;
        }

        $inbox = $scheduled->shared_inbox_id
            ? SharedInbox::with('account')->find($scheduled->shared_inbox_id)
            : $conversation->inbox()->with('account')->first();

        if (! $inbox?->account) {
            $scheduled->update([
                'status' => ScheduledInboxReply::STATUS_FAILED,
                'error_message' => 'Inbox is not connected to Outlook.',
            ]);

            return null;
        }

        $lastInbound = $conversation->messages()->where('direction', 'inbound')->orderByDesc('sent_at')->first();
        $archive = (bool) $scheduled->archive_after;

        try {
            $result = $this->send($conversation, $inbox, $actor, [
                'body' => (string) $scheduled->body_html,
                'to' => (string) $scheduled->to_emails,
                'cc' => $scheduled->cc_emails,
                'attachments' => $this->loadScheduledAttachments($scheduled),
                'archive' => $archive,
                'reply_to_message_id' => $lastInbound?->external_message_id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Scheduled inbox reply failed', [
                'scheduled_id' => $scheduled->id,
                'error' => $e->getMessage(),
            ]);
            $scheduled->update([
                'status' => ScheduledInboxReply::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            return null;
        }

        InboxConversationActivity::create([
            'inbox_conversation_id' => $conversation->id,
            'user_id' => $actor->id,
            'action' => 'replied',
            'summary' => mb_substr($actor->name.' sent a scheduled reply', 0, 500),
            'meta' => [
                'message_id' => $result['message']->id,
                'scheduled_reply_id' => $scheduled->id,
                'source' => 'scheduled',
            ],
        ]);

        if ($archive) {
            InboxConversationActivity::create([
                'inbox_conversation_id' => $conversation->id,
                'user_id' => $actor->id,
                'action' => 'archived',
                'summary' => mb_substr($actor->name.' archived this conversation', 0, 500),
                'meta' => ['source' => 'send_and_archive', 'scheduled_reply_id' => $scheduled->id],
            ]);
        }

        $this->applyLeadRules($result['conversation'], LeadRuleEngine::TRIGGER_OUTBOUND_REPLY);

        $this->finalizeScheduledSuccess($scheduled, $result['message']->id);

        return $result;
    }

    /**
     * @return array{message: InboxMessage, conversation: InboxConversation}|null
     */
    private function dispatchScheduledCompose(
        ScheduledInboxReply $scheduled,
        User $actor,
        ?InboxConversation $draft
    ): ?array {
        $inbox = $scheduled->shared_inbox_id
            ? SharedInbox::with('account')->find($scheduled->shared_inbox_id)
            : ($draft?->inbox()->with('account')->first());

        if (! $inbox?->account) {
            $scheduled->update([
                'status' => ScheduledInboxReply::STATUS_FAILED,
                'error_message' => 'Inbox is not connected to Outlook.',
            ]);

            return null;
        }

        $subject = (string) ($scheduled->subject ?: $draft?->subject ?: '(No subject)');

        try {
            $result = $this->sendCompose($inbox, $actor, [
                'body' => (string) $scheduled->body_html,
                'to' => (string) $scheduled->to_emails,
                'cc' => $scheduled->cc_emails,
                'subject' => $subject,
                'attachments' => $this->loadScheduledAttachments($scheduled),
            ], $draft);
        } catch (\Throwable $e) {
            Log::warning('Scheduled inbox compose failed', [
                'scheduled_id' => $scheduled->id,
                'error' => $e->getMessage(),
            ]);
            $scheduled->update([
                'status' => ScheduledInboxReply::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            return null;
        }

        InboxConversationActivity::create([
            'inbox_conversation_id' => $result['conversation']->id,
            'user_id' => $actor->id,
            'action' => 'composed',
            'summary' => mb_substr($actor->name.' sent a scheduled message', 0, 500),
            'meta' => [
                'message_id' => $result['message']->id,
                'scheduled_reply_id' => $scheduled->id,
                'source' => 'scheduled',
            ],
        ]);

        $this->applyLeadRules($result['conversation'], LeadRuleEngine::TRIGGER_OUTBOUND_MESSAGE_NEW);
        $this->finalizeScheduledSuccess($scheduled, $result['message']->id);

        return $result;
    }

    private function finalizeScheduledSuccess(ScheduledInboxReply $scheduled, int $messageId): void
    {
        $this->deleteScheduledAttachmentFiles($scheduled);
        $scheduled->update([
            'status' => ScheduledInboxReply::STATUS_SENT,
            'sent_message_id' => $messageId,
            'sent_at' => now(),
            'attachments' => [],
            'error_message' => null,
        ]);
    }

    /**
     * @param  string|array<int, string>  $triggers
     */
    private function applyLeadRules(InboxConversation $conversation, string|array $triggers): void
    {
        $fresh = $conversation->fresh(['inbox']) ?? $conversation;
        $latest = $fresh->messages()->orderByDesc('sent_at')->orderByDesc('id')->first();

        $this->leadAutoCreate->applyRules(
            $this->leadAutoCreate->fromInboxConversation($fresh),
            'inbox',
            $triggers,
            [
                'company_id' => (int) $fresh->company_id,
                'contact_name' => $fresh->from_name,
                'email' => $fresh->from_email,
                'subject' => $fresh->subject,
                'message' => (string) ($latest?->body_text ?: $fresh->snippet),
                'inbox_id' => $fresh->shared_inbox_id,
                'shared_inbox_id' => $fresh->shared_inbox_id,
                'inbox_conversation_id' => $fresh->id,
            ]
        );
    }
}
