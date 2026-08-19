<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class LeadRuleNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Lead $lead,
        public string $summary,
        public ?string $snippet = null
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
            'type' => 'lead_rule',
            'lead_id' => $this->lead->id,
            'contact_name' => $this->lead->name,
            'summary' => $this->summary,
            'snippet' => $this->snippet ? Str::limit(trim($this->snippet), 140) : null,
            'url' => url('/leads?lead='.$this->lead->id),
        ];
    }
}
