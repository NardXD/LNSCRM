<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class MessagingMentionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Conversation $conversation,
        public Message $message,
        public User $actor
    ) {}

    /**
     * @return list<string>
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
        $author = $this->actor->name ?: 'Someone';
        $chatName = $this->conversation->type === 'group'
            ? ($this->conversation->name ?: 'a group chat')
            : $author;

        return [
            'type' => 'messaging_mention',
            'is_mention' => true,
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message->id,
            'author_id' => $this->actor->id,
            'author_name' => $author,
            'summary' => $author.' mentioned you in '.$chatName,
            'snippet' => Str::limit(trim((string) ($this->message->body ?: '')), 140),
            'url' => url('/messaging?conversation='.$this->conversation->id),
        ];
    }
};
