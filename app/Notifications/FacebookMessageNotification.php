<?php

namespace App\Notifications;

use App\Models\FacebookConversation;
use App\Models\FacebookMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class FacebookMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public FacebookConversation $conversation,
        public FacebookMessage $message
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
        $channelLabel = $this->conversation->channel === 'instagram' ? 'Instagram' : 'Messenger';
        $name = $this->conversation->name
            ?: ($this->conversation->username ?: ($channelLabel.' contact'));

        $snippet = match ($this->message->type) {
            'text' => (string) ($this->message->text ?? ''),
            'image' => '[Image]',
            'video' => '[Video]',
            'audio' => '[Audio]',
            'file' => '[File] '.($this->message->file_name ?: ''),
            default => '['.ucfirst((string) $this->message->type).']',
        };

        return [
            'type' => 'facebook_message',
            'channel' => $this->conversation->channel,
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message->id,
            'contact_name' => $name,
            'summary' => 'New '.$channelLabel.' message from '.$name,
            'snippet' => Str::limit(trim($snippet), 140),
            'url' => url('/facebook?conversation='.$this->conversation->id),
        ];
    }
}
