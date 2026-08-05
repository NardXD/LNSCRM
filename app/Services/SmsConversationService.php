<?php

namespace App\Services;

use App\Models\PhoneContact;
use App\Models\SmsConversation;
use App\Models\SmsMessage;
use Illuminate\Support\Str;

class SmsConversationService
{
    public function __construct(
        protected InfobipCompanyService $infobipCompany
    ) {}

    public function upsert(
        int $companyId,
        string $peerPhone,
        ?string $ourNumber = null,
        ?string $name = null
    ): SmsConversation {
        $peer = $this->infobipCompany->normalizePhone($peerPhone);
        $our = $ourNumber ? $this->infobipCompany->normalizePhone($ourNumber) : null;

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
    }

    public function resolveContactName(int $companyId, string $peerPhone): ?string
    {
        $normalized = $this->infobipCompany->normalizePhone($peerPhone);

        $contact = PhoneContact::query()
            ->where('company_id', $companyId)
            ->where('phone', $normalized)
            ->first();

        return $contact?->name;
    }
}
