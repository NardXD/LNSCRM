<?php

namespace App\Notifications;

use App\Models\ViberConversation;
use App\Models\ViberMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ViberMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ViberConversation $conversation,
        public ViberMessage $message
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
            ?: ($this->conversation->phone ?: 'Viber contact');

        $snippet = match ($this->message->type) {
            'text' => (string) ($this->message->text ?? ''),
            'picture', 'sticker' => '[Image]',
            'video' => '[Video]',
            'file' => '[File] '.($this->message->file_name ?: ''),
            'url' => (string) ($this->message->media_url ?: '[Link]'),
            'location' => '[Location]',
            'contact' => '[Contact] '.($this->message->contact_name ?: ''),
            default => '['.ucfirst((string) $this->message->type).']',
        };

        return [
            'type' => 'viber_message',
            'channel' => 'viber',
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message->id,
            'contact_name' => $name,
            'phone' => $this->conversation->phone ?: $this->conversation->viber_user_id,
            'summary' => 'New Viber message from '.$name,
            'snippet' => Str::limit(trim($snippet), 140),
            'url' => url('/viber?conversation='.$this->conversation->id),
        ];
    }
}
