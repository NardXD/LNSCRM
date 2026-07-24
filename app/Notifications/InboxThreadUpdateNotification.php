<?php

namespace App\Notifications;

use App\Models\InboxConversation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class InboxThreadUpdateNotification extends Notification
{
    use Queueable;

    public function __construct(
        public InboxConversation $conversation,
        public string $action,
        public string $summary,
        public ?User $actor = null,
        public ?string $snippet = null,
        public bool $isMention = false
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $author = $this->actor?->name ?: 'A teammate';
        $subject = $this->conversation->subject ?: '(No subject)';
        $mail = (new MailMessage)
            ->greeting('Hi '.$notifiable->name.',');

        if ($this->isMention) {
            $mail->subject("{$author} mentioned you in Inbox")
                ->line("{$author} mentioned you in an internal comment on \"{$subject}\".");
        } else {
            $mail->subject("Inbox update: {$subject}")
                ->line($this->summary);
        }

        if ($this->snippet) {
            $mail->line(Str::limit(trim($this->snippet), 200));
        }

        return $mail
            ->action('Open conversation', $this->conversationUrl())
            ->line('Open Inbox to review the latest updates.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->isMention ? 'inbox_comment_mention' : 'inbox_thread_update',
            'action' => $this->action,
            'is_mention' => $this->isMention,
            'conversation_id' => $this->conversation->id,
            'subject' => $this->conversation->subject ?: '(No subject)',
            'summary' => $this->summary,
            'author_id' => $this->actor?->id,
            'author_name' => $this->actor?->name ?: 'Teammate',
            'snippet' => $this->snippet ? Str::limit(trim($this->snippet), 140) : null,
            'url' => $this->conversationUrl(),
        ];
    }

    private function conversationUrl(): string
    {
        return url('/inbox?conversation='.$this->conversation->id);
    }
}
