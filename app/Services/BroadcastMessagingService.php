<?php

namespace App\Services;

use App\Jobs\ProcessBroadcastJob;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastCampaignRecipient;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Models\PhoneContact;
use App\Models\SharedInbox;
use App\Models\SmsMessage;
use App\Models\TwilioPhoneNumber;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BroadcastMessagingService
{
    public const MAX_RECIPIENTS = 1000;

    public const BATCH_SIZE = 12;

    public function __construct(
        protected TwilioCompanyService $twilioCompany,
        protected OutlookMailService $mailService,
        protected SmsConversationService $smsConversations
    ) {}

    /**
     * @return list<array{id: int, phone_number: string, friendly_name: ?string, assigned: bool}>
     */
    public function smsSenders(User $user): array
    {
        if (! $user->company_id) {
            return [];
        }

        $numbers = TwilioPhoneNumber::query()
            ->where('company_id', $user->company_id)
            ->orderBy('phone_number')
            ->get();

        $senders = [];
        $seen = [];

        foreach ($numbers as $number) {
            $phone = $this->twilioCompany->normalizePhone((string) $number->phone_number);
            if ($phone === '' || isset($seen[$phone])) {
                continue;
            }

            $capabilities = is_array($number->capabilities) ? $number->capabilities : [];
            $smsCapable = ! array_key_exists('sms', $capabilities) || (bool) $capabilities['sms'];
            if (! $smsCapable) {
                continue;
            }

            $seen[$phone] = true;
            $senders[] = [
                'id' => (int) $number->id,
                'phone_number' => $phone,
                'friendly_name' => $number->friendly_name,
                'assigned' => (int) $number->sms_assigned_user_id === (int) $user->id
                    || $phone === $this->twilioCompany->normalizePhone((string) ($user->twilio_sms_number ?? '')),
            ];
        }

        $userNumber = $user->twilio_sms_number
            ? $this->twilioCompany->normalizePhone((string) $user->twilio_sms_number)
            : '';

        if ($userNumber !== '' && ! isset($seen[$userNumber])) {
            array_unshift($senders, [
                'id' => 0,
                'phone_number' => $userNumber,
                'friendly_name' => 'Assigned to you',
                'assigned' => true,
            ]);
        }

        return $senders;
    }

    /**
     * @return list<array{id: int, name: string, email: string, type: string, connected: bool, account_email: ?string}>
     */
    public function emailSenders(User $user): array
    {
        if (! $user->company_id) {
            return [];
        }

        return SharedInbox::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where('type', SharedInbox::TYPE_SHARED)
            ->whereHas('members', fn ($m) => $m->where('users.id', $user->id))
            ->with('account')
            ->orderBy('name')
            ->get()
            ->map(function (SharedInbox $inbox) {
                $email = $inbox->external_mailbox ?: $inbox->email ?: $inbox->account?->email;

                return [
                    'id' => (int) $inbox->id,
                    'name' => $inbox->name,
                    'email' => (string) $email,
                    'type' => $inbox->type,
                    'connected' => (bool) $inbox->outlook_mail_account_id && (bool) $inbox->account,
                    'account_email' => $inbox->account?->email,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{data: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function searchRecipients(User $user, string $channel, string $query, string $source, int $page = 1): array
    {
        $companyId = (int) $user->company_id;
        $query = trim($query);
        $page = max(1, $page);
        $perPage = 40;
        $results = collect();

        if ($source === '' || $source === 'all' || $source === 'leads') {
            $results = $results->concat($this->searchLeads($companyId, $channel, $query));
        }
        if ($source === '' || $source === 'all' || $source === 'clients') {
            $results = $results->concat($this->searchClients($companyId, $channel, $query));
        }
        if ($source === '' || $source === 'all' || $source === 'contacts') {
            $results = $results->concat($this->searchClientContacts($companyId, $channel, $query));
            $results = $results->concat($this->searchPhoneContacts($companyId, $channel, $query));
        }

        $results = $results
            ->unique(fn ($row) => $row['source'].':'.$row['source_id'].':'.strtolower($row['address']))
            ->values();

        $total = $results->count();
        $items = $results->slice(($page - 1) * $perPage, $perPage)->values()->all();

        return [
            'data' => $items,
            'pagination' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createAndSend(User $user, array $payload): BroadcastCampaign
    {
        $type = $payload['type'] === BroadcastCampaign::TYPE_EMAIL
            ? BroadcastCampaign::TYPE_EMAIL
            : BroadcastCampaign::TYPE_SMS;

        $sender = $type === BroadcastCampaign::TYPE_SMS
            ? $this->resolveSmsSender($user, (string) ($payload['from_number'] ?? ''))
            : $this->resolveEmailSender($user, (int) ($payload['shared_inbox_id'] ?? 0));

        $recipients = $this->normalizeRecipientInput(
            $user,
            $type,
            $payload['recipients'] ?? []
        );

        if ($recipients === []) {
            throw new \InvalidArgumentException('Add at least one valid recipient.');
        }

        if (count($recipients) > self::MAX_RECIPIENTS) {
            throw new \InvalidArgumentException('A broadcast can include at most '.self::MAX_RECIPIENTS.' recipients.');
        }

        $campaign = DB::transaction(function () use ($user, $payload, $type, $sender, $recipients) {
            $campaign = BroadcastCampaign::create([
                'company_id' => $user->company_id,
                'created_by' => $user->id,
                'name' => trim((string) $payload['name']),
                'type' => $type,
                'status' => BroadcastCampaign::STATUS_SENDING,
                'sender_label' => $sender['label'],
                'from_number' => $sender['from_number'],
                'shared_inbox_id' => $sender['shared_inbox_id'],
                'subject' => $type === BroadcastCampaign::TYPE_EMAIL
                    ? trim((string) ($payload['subject'] ?? ''))
                    : null,
                'body' => (string) $payload['body'],
                'recipient_count' => count($recipients),
                'sent_at' => now(),
            ]);

            foreach ($recipients as $recipient) {
                $campaign->recipients()->create($recipient);
            }

            return $campaign;
        });

        // Process as many batches as we can in this request, then queue the rest.
        $started = microtime(true);
        $processed = 0;
        do {
            $batchCount = $this->processBatch($campaign, dispatchRemainder: false);
            $processed += $batchCount;
            $campaign->refresh();
            $hasPending = $campaign->recipients()
                ->where('status', BroadcastCampaignRecipient::STATUS_PENDING)
                ->exists();
        } while (
            $batchCount > 0
            && $hasPending
            && $processed < 60
            && (microtime(true) - $started) < 45
        );

        if ($campaign->recipients()->where('status', BroadcastCampaignRecipient::STATUS_PENDING)->exists()) {
            ProcessBroadcastJob::dispatch($campaign->id)->delay(now()->addSeconds(2));
        }

        return $campaign->fresh(['creator', 'recipients']);
    }

    public function processBatch(BroadcastCampaign $campaign, bool $dispatchRemainder = true): int
    {
        $recipients = DB::transaction(function () use ($campaign) {
            $rows = BroadcastCampaignRecipient::query()
                ->where('broadcast_campaign_id', $campaign->id)
                ->where('status', BroadcastCampaignRecipient::STATUS_PENDING)
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $row->update(['status' => BroadcastCampaignRecipient::STATUS_SENDING]);
            }

            return $rows;
        });

        if ($recipients->isEmpty()) {
            $campaign->refreshCounts();

            return 0;
        }

        foreach ($recipients as $recipient) {
            if ($campaign->isSms()) {
                $this->sendSmsRecipient($campaign, $recipient);
            } else {
                $this->sendEmailRecipient($campaign, $recipient);
            }
        }

        $campaign->refreshCounts();

        if (
            $dispatchRemainder
            && $campaign->recipients()->where('status', BroadcastCampaignRecipient::STATUS_PENDING)->exists()
        ) {
            ProcessBroadcastJob::dispatch($campaign->id)->delay(now()->addSeconds(2));
        }

        return $recipients->count();
    }

    public function applyTwilioStatus(string $messageSid, string $status): void
    {
        $recipient = BroadcastCampaignRecipient::query()
            ->where('provider_sid', $messageSid)
            ->first();

        if (! $recipient) {
            return;
        }

        $mapped = match (strtolower($status)) {
            'delivered' => BroadcastCampaignRecipient::STATUS_DELIVERED,
            'undelivered' => BroadcastCampaignRecipient::STATUS_UNDELIVERED,
            'failed' => BroadcastCampaignRecipient::STATUS_FAILED,
            'sent' => BroadcastCampaignRecipient::STATUS_SENT,
            default => null,
        };

        if (! $mapped) {
            return;
        }

        $updates = ['status' => $mapped];
        if ($mapped === BroadcastCampaignRecipient::STATUS_DELIVERED) {
            $updates['delivered_at'] = now();
        }
        if (in_array($mapped, [
            BroadcastCampaignRecipient::STATUS_FAILED,
            BroadcastCampaignRecipient::STATUS_UNDELIVERED,
        ], true) && ! $recipient->error_message) {
            $updates['error_message'] = 'Delivery '.$status;
        }

        $recipient->update($updates);
        $recipient->campaign?->refreshCounts();
    }

    /**
     * @return array{from_number: ?string, shared_inbox_id: ?int, label: string}
     */
    protected function resolveSmsSender(User $user, string $fromNumber): array
    {
        $fromNumber = $this->twilioCompany->normalizePhone($fromNumber);
        if ($fromNumber === '') {
            throw new \InvalidArgumentException('Select a Twilio phone number to send from.');
        }

        $allowed = collect($this->smsSenders($user))->pluck('phone_number')->all();
        if (! in_array($fromNumber, $allowed, true)) {
            throw new \InvalidArgumentException('The selected Twilio number is not available for this company.');
        }

        $integration = $user->company
            ? $this->twilioCompany->getActiveIntegration($user->company)
            : null;
        if (! $integration) {
            throw new \InvalidArgumentException('Connect Twilio in Integrations before sending SMS broadcasts.');
        }

        $row = TwilioPhoneNumber::query()
            ->where('company_id', $user->company_id)
            ->where('phone_number', $fromNumber)
            ->first();

        $label = $row?->friendly_name
            ? $row->friendly_name.' ('.$fromNumber.')'
            : $fromNumber;

        return [
            'from_number' => $fromNumber,
            'shared_inbox_id' => null,
            'label' => $label,
        ];
    }

    /**
     * @return array{from_number: ?string, shared_inbox_id: ?int, label: string}
     */
    protected function resolveEmailSender(User $user, int $inboxId): array
    {
        $inbox = collect($this->emailSenders($user))->firstWhere('id', $inboxId);
        if (! $inbox) {
            throw new \InvalidArgumentException('Select a connected shared Microsoft 365 mailbox to send from.');
        }
        if (empty($inbox['connected'])) {
            throw new \InvalidArgumentException('Connect this shared Microsoft 365 mailbox in Inbox before sending.');
        }

        $label = trim($inbox['name'].' <'.$inbox['email'].'>');

        return [
            'from_number' => null,
            'shared_inbox_id' => $inboxId,
            'label' => $label,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $input
     * @return list<array{source: string, source_id: ?int, name: ?string, address: string, status: string}>
     */
    protected function normalizeRecipientInput(User $user, string $type, array $input): array
    {
        $normalized = [];
        $seen = [];

        foreach ($input as $row) {
            $address = trim((string) ($row['address'] ?? ''));
            $name = trim((string) ($row['name'] ?? '')) ?: null;
            $source = trim((string) ($row['source'] ?? 'manual')) ?: 'manual';
            $sourceId = isset($row['source_id']) && $row['source_id'] !== '' ? (int) $row['source_id'] : null;

            if ($type === BroadcastCampaign::TYPE_SMS) {
                $address = $this->twilioCompany->normalizePhone($address);
                if ($address === '' || ! preg_match('/^\+\d{8,15}$/', $address)) {
                    continue;
                }
            } else {
                $address = strtolower($address);
                if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
            }

            $key = strtolower($address);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $normalized[] = [
                'source' => $source,
                'source_id' => $sourceId,
                'name' => $name,
                'address' => $address,
                'status' => BroadcastCampaignRecipient::STATUS_PENDING,
            ];
        }

        return $normalized;
    }

    protected function sendSmsRecipient(BroadcastCampaign $campaign, BroadcastCampaignRecipient $recipient): void
    {
        try {
            $company = $campaign->company;
            $integration = $company ? $this->twilioCompany->getActiveIntegration($company) : null;
            $credentials = $integration ? $this->twilioCompany->getCredentials($integration) : null;
            if (! $credentials || ! $campaign->from_number) {
                throw new \RuntimeException('Twilio is not connected.');
            }

            $twilio = new TwilioService($credentials['sid'], $credentials['token']);
            $sent = $twilio->sendSms(
                $campaign->from_number,
                $recipient->address,
                $campaign->body,
                route('twilio.broadcast-sms-status')
            );

            $recipient->update([
                'status' => BroadcastCampaignRecipient::STATUS_SENT,
                'provider_sid' => $sent->sid,
                'sent_at' => now(),
                'error_message' => null,
            ]);

            try {
                $conversation = $this->smsConversations->upsert(
                    (int) $campaign->company_id,
                    $recipient->address,
                    $campaign->from_number,
                    $recipient->name
                );

                $message = SmsMessage::query()->create([
                    'company_id' => $campaign->company_id,
                    'sms_conversation_id' => $conversation->id,
                    'user_id' => $campaign->created_by,
                    'message_sid' => $sent->sid,
                    'direction' => 'outbound',
                    'from_number' => $campaign->from_number,
                    'to_number' => $recipient->address,
                    'body' => $campaign->body,
                    'status' => $sent->status,
                    'sent_at' => now(),
                ]);

                $this->smsConversations->touch($conversation, $message);
            } catch (\Throwable $e) {
                Log::warning('Broadcast SMS conversation log failed', [
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Broadcast SMS send failed', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);
            $recipient->update([
                'status' => BroadcastCampaignRecipient::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 500),
                'sent_at' => now(),
            ]);
        }
    }

    protected function sendEmailRecipient(BroadcastCampaign $campaign, BroadcastCampaignRecipient $recipient): void
    {
        try {
            $inbox = SharedInbox::query()
                ->where('id', $campaign->shared_inbox_id)
                ->where('company_id', $campaign->company_id)
                ->with('account')
                ->first();

            if (! $inbox?->account) {
                throw new \RuntimeException('The Microsoft 365 mailbox is no longer connected.');
            }

            $body = $campaign->body;
            if (! str_contains($body, '<')) {
                $body = nl2br(e($body));
            }

            $result = $this->mailService->sendMail($inbox, [
                'to' => $recipient->address,
                'subject' => (string) $campaign->subject,
                'body' => $body,
            ]);

            if (! $result) {
                throw new \RuntimeException('Microsoft 365 rejected the message.');
            }

            $recipient->update([
                'status' => BroadcastCampaignRecipient::STATUS_DELIVERED,
                'sent_at' => now(),
                'delivered_at' => now(),
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Broadcast email send failed', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);
            $recipient->update([
                'status' => BroadcastCampaignRecipient::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 500),
                'sent_at' => now(),
            ]);
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchLeads(int $companyId, string $channel, string $query)
    {
        $identityType = $channel === BroadcastCampaign::TYPE_SMS
            ? LeadIdentity::TYPE_PHONE
            : LeadIdentity::TYPE_EMAIL;

        $leads = Lead::query()
            ->where('company_id', $companyId)
            ->where('status', '!=', Lead::STATUS_ARCHIVED)
            ->whereHas('identities', fn ($identity) => $identity->where('type', $identityType))
            ->when($query !== '', function ($q) use ($query, $identityType) {
                $q->where(function ($inner) use ($query, $identityType) {
                    $inner->where('name', 'like', '%'.$query.'%')
                        ->orWhere('first_name', 'like', '%'.$query.'%')
                        ->orWhere('last_name', 'like', '%'.$query.'%')
                        ->orWhere('company_name', 'like', '%'.$query.'%')
                        ->orWhereHas('identities', function ($identity) use ($query, $identityType) {
                            $identity->where('type', $identityType)
                                ->where(function ($value) use ($query) {
                                    $value->where('value', 'like', '%'.$query.'%')
                                        ->orWhere('normalized_value', 'like', '%'.$query.'%');
                                });
                        });
                });
            })
            ->with(['identities' => fn ($q) => $q->where('type', $identityType)])
            ->orderByDesc('updated_at')
            ->limit(80)
            ->get();

        $rows = collect();
        foreach ($leads as $lead) {
            $name = $this->leadDisplayName($lead);
            foreach ($lead->identities as $identity) {
                $address = $identity->normalized_value ?: $identity->value;
                if (! $address) {
                    continue;
                }
                $rows->push([
                    'source' => 'lead',
                    'source_id' => $lead->id,
                    'name' => $name,
                    'address' => $address,
                    'meta' => 'Lead',
                ]);
            }
        }

        return $rows;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchClients(int $companyId, string $channel, string $query)
    {
        $column = $channel === BroadcastCampaign::TYPE_SMS ? 'phone' : 'email';

        $clients = Client::query()
            ->where('company_id', $companyId)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->when($query !== '', function ($q) use ($query, $column) {
                $q->where(function ($inner) use ($query, $column) {
                    $inner->where('name', 'like', '%'.$query.'%')
                        ->orWhere('contact_person', 'like', '%'.$query.'%')
                        ->orWhere($column, 'like', '%'.$query.'%');
                });
            })
            ->orderBy('name')
            ->limit(80)
            ->get();

        return $clients->map(function (Client $client) use ($column) {
            return [
                'source' => 'client',
                'source_id' => $client->id,
                'name' => $client->name,
                'address' => (string) $client->{$column},
                'meta' => 'Client',
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchClientContacts(int $companyId, string $channel, string $query)
    {
        $column = $channel === BroadcastCampaign::TYPE_SMS ? 'phone' : 'email';

        $contacts = ClientContact::query()
            ->whereHas('client', fn ($q) => $q->where('company_id', $companyId))
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->when($query !== '', function ($q) use ($query, $column) {
                $q->where(function ($inner) use ($query, $column) {
                    $inner->where('name', 'like', '%'.$query.'%')
                        ->orWhere($column, 'like', '%'.$query.'%');
                });
            })
            ->with('client:id,name')
            ->orderBy('name')
            ->limit(80)
            ->get();

        return $contacts->map(function (ClientContact $contact) use ($column) {
            return [
                'source' => 'client_contact',
                'source_id' => $contact->id,
                'name' => $contact->name,
                'address' => (string) $contact->{$column},
                'meta' => $contact->client?->name ? 'Contact · '.$contact->client->name : 'Client contact',
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchPhoneContacts(int $companyId, string $channel, string $query)
    {
        $column = $channel === BroadcastCampaign::TYPE_SMS ? 'phone' : 'email';

        $contacts = PhoneContact::query()
            ->where('company_id', $companyId)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->when($query !== '', function ($q) use ($query, $column) {
                $q->where(function ($inner) use ($query, $column) {
                    $inner->where('name', 'like', '%'.$query.'%')
                        ->orWhere($column, 'like', '%'.$query.'%');
                });
            })
            ->orderBy('name')
            ->limit(80)
            ->get();

        return $contacts->map(function (PhoneContact $contact) use ($column) {
            return [
                'source' => 'phone_contact',
                'source_id' => $contact->id,
                'name' => $contact->name,
                'address' => (string) $contact->{$column},
                'meta' => 'Phone contact',
            ];
        });
    }

    protected function leadDisplayName(Lead $lead): string
    {
        $display = trim(($lead->first_name ?? '').' '.($lead->last_name ?? ''));
        if ($display !== '') {
            return $display;
        }

        return trim((string) $lead->name) !== ''
            ? (string) $lead->name
            : (string) ($lead->company_name ?: 'Lead #'.$lead->id);
    }
}
