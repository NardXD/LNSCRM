<?php

namespace App\Services;

use App\Models\PhoneContact;
use App\Models\SmsConversation;
use App\Models\SmsMessage;
use App\Notifications\SmsMessageNotification;
use App\Services\LeadRuleEngine;
use Illuminate\Support\Str;

class SmsConversationService
{
    public function __construct(
        protected TwilioCompanyService $twilioCompany,
        protected LeadAutoCreateService $leadAutoCreate,
        protected ChannelUnreadNotifier $unreadNotifier
    ) {}

    public function upsert(
        int $companyId,
        string $peerPhone,
        ?string $ourNumber = null,
        ?string $name = null
    ): SmsConversation {
        $peer = $this->twilioCompany->normalizePhone($peerPhone);
        $our = $ourNumber ? $this->twilioCompany->normalizePhone($ourNumber) : null;

        $conversation = SmsConversation::firstOrNew([
            'company_id' => $companyId,
            'peer_phone' => $peer,
        ]);

        if (! $conversation->exists) {
            $conversation->name = $name ?: $this->resolveContactName($companyId, $peer) ?: $peer;
            $conversation->our_number = $our;
        } else {
            if ($our) {
                $conversation->our_number = $our;
            }
            if ($name && (! $conversation->name || $conversation->name === $conversation->peer_phone)) {
                $conversation->name = $name;
            }
        }

        $conversation->save();

        $this->leadAutoCreate->fromPhoneChannel($companyId, 'sms', $peer, $conversation->name);

        return $conversation;
    }

    public function touch(SmsConversation $conversation, SmsMessage $message, bool $incrementUnread = false): void
    {
        $conversation->last_message_preview = Str::limit(trim((string) $message->body), 480);
        $conversation->last_message_at = $message->sent_at ?: now();

        if ($incrementUnread) {
            $conversation->unread_count = (int) $conversation->unread_count + 1;
        }

        $conversation->save();

        if ($incrementUnread) {
            $this->unreadNotifier->notifyCompanyUsers(
                (int) $conversation->company_id,
                'view_sms',
                SmsMessageNotification::class,
                (int) $conversation->id,
                new SmsMessageNotification($conversation, $message)
            );
            $isNew = SmsMessage::query()->where('sms_conversation_id', $conversation->id)->count() <= 1;
            $this->leadAutoCreate->applyRules(
                $this->leadAutoCreate->fromPhoneChannel(
                    (int) $conversation->company_id,
                    'sms',
                    $conversation->peer_phone,
                    $conversation->name
                ),
                'sms',
                LeadRuleEngine::inboundTriggers($isNew),
                [
                    'contact_name' => $conversation->name,
                    'phone' => $conversation->peer_phone,
                    'message' => (string) $message->body,
                ]
            );
        }
    }

    public function resolveContactName(int $companyId, string $peerPhone): ?string
    {
        $normalized = $this->twilioCompany->normalizePhone($peerPhone);

        $contact = PhoneContact::query()
            ->where('company_id', $companyId)
            ->where('phone', $normalized)
            ->first();

        return $contact?->name;
    }
}
