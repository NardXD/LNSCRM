<?php

namespace App\Notifications;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class WhatsAppMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public WhatsAppConversation $conversation,
        public WhatsAppMessage $message
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $name = $this->conversation->name
            ?: ($this->conversation->profile_name ?: ($this->conversation->phone ?: 'WhatsApp contact'));

        $snippet = match ($this->message->type) {
            'text' => (string) ($this->message->text ?? ''),
            'image', 'sticker' => '[Image]',
            'video' => '[Video]',
            'audio' => '[Audio]',
            'document' => '[File] '.($this->message->file_name ?: ''),
            'location' => '[Location]',
            'contact' => '[Contact] '.($this->message->contact_name ?: ''),
            default => '['.ucfirst((string) $this->message->type).']',
        };

        return [
            'type' => 'whatsapp_message',
            'channel' => 'whatsapp',
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message->id,
            'contact_name' => $name,
            'phone' => $this->conversation->phone ?: $this->conversation->wa_id,
            'summary' => 'New WhatsApp message from '.$name,
            'snippet' => Str::limit(trim($snippet), 140),
            'url' => url('/whatsapp?conversation='.$this->conversation->id),
        ];
    }
}
