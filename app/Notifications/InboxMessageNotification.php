<?php

namespace App\Notifications;

use App\Models\InboxConversation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class InboxMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public InboxConversation $conversation
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
        $from = $this->conversation->from_name
            ?: ($this->conversation->from_email ?: 'Unknown sender');
        $subject = $this->conversation->subject ?: '(No subject)';

        return [
            'type' => 'inbox_message',
            'channel' => 'inbox',
            'conversation_id' => $this->conversation->id,
            'contact_name' => $from,
            'subject' => $subject,
            'summary' => 'New email from '.$from,
            'snippet' => Str::limit(trim((string) ($this->conversation->snippet ?: $subject)), 140),
            'url' => url('/inbox?conversation='.$this->conversation->id),
        ];
    }
}
