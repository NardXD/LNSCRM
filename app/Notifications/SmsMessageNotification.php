<?php

namespace App\Notifications;

use App\Models\SmsConversation;
use App\Models\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class SmsMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SmsConversation $conversation,
        public SmsMessage $message
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
            ?: ($this->conversation->peer_phone ?: 'SMS contact');

        return [
            'type' => 'sms_message',
            'channel' => 'sms',
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message->id,
            'contact_name' => $name,
            'phone' => $this->conversation->peer_phone,
            'summary' => 'New SMS from '.$name,
            'snippet' => Str::limit(trim((string) ($this->message->body ?? '')), 140),
            'url' => url('/sms?conversation='.$this->conversation->id),
        ];
    }
}
