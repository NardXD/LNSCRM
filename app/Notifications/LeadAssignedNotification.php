<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class LeadAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Lead $lead,
        public bool $isNew = false,
        public ?string $assignedByName = null,
        public ?string $reason = null
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
        $name = trim((string) $this->lead->name) ?: 'a lead';

        return [
            'type' => 'lead_assigned',
            'event' => $this->isNew ? 'created' : 'assigned',
            'lead_id' => $this->lead->id,
            'contact_name' => $this->lead->name,
            'summary' => $this->isNew
                ? 'New lead assigned to you: '.$name
                : $name.' was assigned to you',
            'snippet' => $this->snippet(),
            'url' => url('/leads?lead='.$this->lead->id),
            'reason' => $this->reason,
        ];
    }

    protected function snippet(): ?string
    {
        $snippet = match ($this->reason) {
            'rule' => 'Assigned by a lead rule',
            'inbox' => 'Assigned from inbox',
            'inbound call' => 'Assigned from an inbound call',
            'outbound call' => 'Assigned from an outbound call',
            'created' => $this->assignedByName ? 'Created by '.$this->assignedByName : 'New lead',
            default => $this->assignedByName ? 'Assigned by '.$this->assignedByName : null,
        };

        return $snippet ? Str::limit($snippet, 140) : null;
    }
}
