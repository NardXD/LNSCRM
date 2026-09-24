<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class DiscussionUpdateNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Conversation $conversation,
        public string $summary,
        public ?string $snippet = null,
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
        return [
            'type' => 'discussion_update',
            'conversation_id' => $this->conversation->id,
            'summary' => $this->summary,
            'snippet' => $this->snippet ? Str::limit(trim($this->snippet), 140) : null,
            'url' => url('/discussions?conversation='.$this->conversation->id),
        ];
    }
}
