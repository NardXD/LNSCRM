<?php

namespace App\Services;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Str;

class WhatsAppConversationService
{
    public function __construct(
        protected TwilioCompanyService $twilioCompany,
        protected LeadAutoCreateService $leadAutoCreate
    ) {}

    public function upsert(int $companyId, string $waId, ?string $profileName): WhatsAppConversation
    {
        $normalized = $this->twilioCompany->normalizePhone($waId);

        $conversation = WhatsAppConversation::firstOrNew([
            'company_id' => $companyId,
            'wa_id' => $normalized,
        ]);

        $conversation->fill([
            'name' => $profileName ?: ($conversation->name ?: $normalized),
            'profile_name' => $profileName ?: $conversation->profile_name,
            'phone' => $conversation->phone ?: preg_replace('/\D+/', '', $normalized),
            'is_subscribed' => true,
        ]);
        $conversation->save();

        $this->leadAutoCreate->fromPhoneChannel($companyId, 'whatsapp', $normalized, $profileName ?: $conversation->name);

        return $conversation;
    }

    public function touch(WhatsAppConversation $conversation, WhatsAppMessage $message, bool $incrementUnread = false): void
    {
        $preview = match ($message->type) {
            'text' => (string) $message->text,
            'image', 'sticker' => '[Image]',
            'video' => '[Video]',
            'audio' => '[Audio]',
            'document' => '[File] '.($message->file_name ?: ''),
            'location' => '[Location]',
            'contact' => '[Contact] '.($message->contact_name ?: ''),
            default => '['.ucfirst($message->type).']',
        };

        $conversation->last_message_preview = Str::limit(trim($preview), 480);
        $conversation->last_message_at = $message->sent_at ?: now();

        if ($incrementUnread) {
            $conversation->unread_count = (int) $conversation->unread_count + 1;
        }

        $conversation->save();
    }
}
