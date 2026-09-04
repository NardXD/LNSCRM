<?php

namespace App\Services;

use App\Models\FacebookConversation;
use App\Models\FacebookIntegration;
use App\Models\FacebookMessage;
use App\Models\InboxConversation;
use App\Models\InboxTemplate;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadScheduledEmail;
use App\Models\MessageTemplate;
use App\Models\SharedInbox;
use App\Models\SmsConversation;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\ViberConversation;
use App\Models\ViberIntegration;
use App\Models\ViberMessage;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppIntegration;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LeadChannelMessageService
{
    /** @var array<string, string> */
    public const CHANNEL_PERMISSIONS = [
        'sms' => 'view_sms',
        'facebook' => 'view_facebook',
        'viber' => 'view_viber',
        'whatsapp' => 'view_whatsapp',
        'inbox' => 'view_inbox',
    ];

    /** @var array<string, string> */
    public const CHANNEL_LABELS = [
        'sms' => 'SMS',
        'facebook' => 'Facebook',
        'viber' => 'Viber',
        'whatsapp' => 'WhatsApp',
        'inbox' => 'Mail',
    ];

    public function __construct(
        protected LeadConnectedThreadService $connectedThreads,
        protected LeadFollowUpDayService $followUpDays,
        protected LeadActivityService $leadActivity,
        protected TwilioCompanyService $twilioCompany,
        protected SmsConversationService $smsConversations,
        protected LeadAutoCreateService $leadAutoCreate,
        protected InboxReplyService $inboxReplies,
        protected FacebookGraphMessagingService $graphMessaging
    ) {}

    /**
     * @return array{follow_up_day: int, channels: list<array<string, mixed>>}
     */
    public function describe(Lead $lead, User $user): array
    {
        $lead->loadMissing('identities');
        $threads = $this->threadsByChannel($lead);
        $channels = [];

        foreach (self::CHANNEL_LABELS as $channel => $label) {
            $perm = self::CHANNEL_PERMISSIONS[$channel];
            $thread = $threads[$channel] ?? null;
            $templates = $this->templatesFor($lead, $user, $channel);
            $available = false;
            $reason = null;

            if (! $user->hasPermission($perm)) {
                $reason = 'You do not have access to '.$label.'.';
            } elseif ($channel === 'sms' && ! $user->hasPermission('send_sms')) {
                $reason = 'You do not have permission to send SMS.';
            } elseif ($thread) {
                $available = true;
            } elseif ($channel === 'sms' && $this->primaryPhone($lead) !== '') {
                $available = true;
            } elseif ($channel === 'sms') {
                $reason = 'No SMS thread or phone number yet.';
            } elseif ($channel === 'inbox' && $this->primaryEmail($lead) !== '') {
                $available = true;
            } elseif ($channel === 'inbox') {
                $reason = 'No email address yet.';
            } else {
                $reason = 'No '.$label.' thread yet.';
            }

            $channels[] = [
                'id' => $channel,
                'label' => $label,
                'available' => $available,
                'reason' => $reason,
                'conversation_id' => $thread['conversation_id'] ?? null,
                'url' => $thread['deep_link'] ?? $thread['url'] ?? null,
                'templates' => $templates,
                'mailboxes' => $channel === 'inbox' ? $this->mailboxesFor($user) : [],
                'emails' => $channel === 'inbox' ? $this->emailsFor($lead) : [],
            ];
        }

        return [
            'follow_up_day' => $this->followUpDays->dayFor($lead),
            'channels' => $channels,
        ];
    }

    /**
     * @return array{channel: string, template: ?string}
     */
    public function send(
        Lead $lead,
        User $user,
        string $channel,
        ?int $templateId,
        ?string $body,
        ?string $subject = null,
        ?int $inboxId = null,
        ?array $toEmails = null
    ): array {
        $lead->loadMissing(['identities', 'company']);
        $channel = $channel === 'mail' ? 'inbox' : $channel;
        if (! isset(self::CHANNEL_LABELS[$channel])) {
            throw new \RuntimeException('Unknown channel.');
        }
        if (! $user->hasPermission(self::CHANNEL_PERMISSIONS[$channel])) {
            throw new \RuntimeException('You do not have access to '.self::CHANNEL_LABELS[$channel].'.');
        }
        if ($channel === 'sms' && ! $user->hasPermission('send_sms')) {
            throw new \RuntimeException('You do not have permission to send SMS.');
        }

        $resolved = $this->resolveBody($lead, $user, $channel, $templateId, $body, $subject);
        $text = $this->merge($resolved['body'], $lead);
        if ($this->isBlankBody($text, $channel)) {
            throw new \RuntimeException('Choose a template or enter a message.');
        }

        match ($channel) {
            'sms' => $this->sendSms($lead, $user, $text),
            'whatsapp' => $this->sendWhatsApp($lead, $user, $text),
            'viber' => $this->sendViber($lead, $user, $text),
            'facebook' => $this->sendFacebook($lead, $user, $text),
            'inbox' => $this->sendMail(
                $lead,
                $user,
                $text,
                $this->merge((string) $resolved['subject'], $lead),
                $inboxId,
                $toEmails
            ),
            default => throw new \RuntimeException('Unknown channel.'),
        };

        $this->leadActivity->record(
            $lead,
            LeadActivity::TEMPLATE_SENT,
            'Sent '.self::CHANNEL_LABELS[$channel].' template'.($resolved['name'] ? ': '.$resolved['name'] : ''),
            [
                'channel' => $channel,
                'template' => $resolved['name'],
                'template_id' => $templateId,
            ],
            (int) $user->id
        );

        return [
            'channel' => $channel,
            'template' => $resolved['name'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function threadsByChannel(Lead $lead): array
    {
        $all = $this->connectedThreads->allThreadsForLeads((int) $lead->company_id, [$lead]);
        $byChannel = [];
        foreach ($all[(int) $lead->id] ?? [] as $thread) {
            $channel = (string) ($thread['channel'] ?? '');
            if ($channel === 'instagram' || $channel === 'messenger') {
                $channel = 'facebook';
            }
            if ($channel === '' || isset($byChannel[$channel])) {
                continue;
            }
            $byChannel[$channel] = $thread;
        }

        return $byChannel;
    }

    /**
     * @return list<array{id: int, name: string, body: string, subject?: ?string}>
     */
    protected function templatesFor(Lead $lead, User $user, string $channel): array
    {
        $companyId = (int) $lead->company_id;
        if ($channel === 'inbox') {
            return InboxTemplate::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get()
                ->map(fn (InboxTemplate $template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'body' => (string) ($template->body_html ?: $template->body_text),
                    'subject' => $template->subject,
                ])
                ->values()
                ->all();
        }

        return MessageTemplate::query()
            ->where('company_id', $companyId)
            ->where('channel', $channel)
            ->orderBy('name')
            ->get()
            ->map(fn (MessageTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'body' => (string) $template->body_text,
                'subject' => null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{body: string, subject: ?string, name: ?string}
     */
    protected function resolveBody(
        Lead $lead,
        User $user,
        string $channel,
        ?int $templateId,
        ?string $body,
        ?string $subject
    ): array {
        $name = null;
        $resolvedBody = trim((string) $body);
        $resolvedSubject = $subject;

        if ($templateId) {
            if ($channel === 'inbox') {
                $template = InboxTemplate::query()
                    ->where('company_id', $lead->company_id)
                    ->whereKey($templateId)
                    ->first();
                if (! $template) {
                    throw new \RuntimeException('Template not found.');
                }
                $name = $template->name;
                if ($resolvedBody === '') {
                    $resolvedBody = (string) ($template->body_html ?: $template->body_text);
                }
                if ($resolvedSubject === null || trim($resolvedSubject) === '') {
                    $resolvedSubject = $template->subject;
                }
            } else {
                $template = MessageTemplate::query()
                    ->where('company_id', $lead->company_id)
                    ->where('channel', $channel)
                    ->whereKey($templateId)
                    ->first();
                if (! $template) {
                    throw new \RuntimeException('Template not found.');
                }
                $name = $template->name;
                if ($resolvedBody === '') {
                    $resolvedBody = (string) $template->body_text;
                }
            }
        }

        return [
            'body' => $resolvedBody,
            'subject' => $resolvedSubject,
            'name' => $name,
        ];
    }

    public function merge(string $text, Lead $lead): string
    {
        $day = $this->followUpDays->dayFor($lead);
        $map = [
            '{{first_name}}' => (string) ($lead->first_name ?: ''),
            '{{last_name}}' => (string) ($lead->last_name ?: ''),
            '{{name}}' => (string) $lead->name,
            '{{follow_up_day}}' => (string) $day,
            '{{company}}' => (string) ($lead->company_name ?: ''),
        ];

        return str_ireplace(array_keys($map), array_values($map), $text);
    }

    protected function primaryPhone(Lead $lead): string
    {
        $phones = $lead->phoneValues();

        return $phones[0] ?? '';
    }

    protected function primaryEmail(Lead $lead): string
    {
        $emails = $this->emailsFor($lead);

        return $emails[0] ?? '';
    }

    /**
     * @return list<string>
     */
    protected function emailsFor(Lead $lead): array
    {
        $known = [];
        foreach ($lead->emailValues() as $email) {
            $trim = trim((string) $email);
            if ($trim !== '') {
                $known[strtolower($trim)] = $trim;
            }
        }

        return array_values($known);
    }

    /**
     * @param  list<string>|null  $requested
     * @return list<string>
     */
    protected function recipientEmails(Lead $lead, ?array $requested): array
    {
        $known = [];
        foreach ($this->emailsFor($lead) as $email) {
            $known[strtolower($email)] = $email;
        }
        if ($known === []) {
            throw new \RuntimeException('No email address yet.');
        }
        if ($requested === null || $requested === []) {
            return array_values($known);
        }

        $selected = [];
        foreach ($requested as $email) {
            $key = strtolower(trim((string) $email));
            if ($key !== '' && isset($known[$key])) {
                $selected[$key] = $known[$key];
            }
        }
        if ($selected === []) {
            return array_values($known);
        }

        return array_values($selected);
    }

    protected function mailboxesFor(User $user): array
    {
        return $this->accessibleMailboxes($user)
            ->map(fn (SharedInbox $inbox) => [
                'id' => (int) $inbox->id,
                'name' => (string) $inbox->name,
                'email' => (string) ($inbox->email ?: $inbox->account?->email ?: ''),
                'type' => (string) $inbox->type,
            ])
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, SharedInbox>
     */
    protected function accessibleMailboxes(User $user)
    {
        return SharedInbox::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->whereHas('account')
            ->where(function ($q) use ($user) {
                $q->where(function ($personal) use ($user) {
                    $personal->where('type', SharedInbox::TYPE_PERSONAL)
                        ->where('created_by', $user->id);
                })->orWhere(function ($shared) use ($user) {
                    $shared->where('type', SharedInbox::TYPE_SHARED)
                        ->whereHas('members', fn ($m) => $m->where('users.id', $user->id));
                });
            })
            ->with('account')
            ->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [SharedInbox::TYPE_SHARED])
            ->orderBy('name')
            ->get();
    }

    protected function mailboxFor(User $user, ?int $inboxId = null): SharedInbox
    {
        $mailboxes = $this->accessibleMailboxes($user);
        if ($inboxId) {
            $inbox = $mailboxes->firstWhere('id', $inboxId);
            if (! $inbox) {
                throw new \RuntimeException('Choose a mailbox you can send from.');
            }

            return $inbox;
        }

        $inbox = $mailboxes->firstWhere('type', SharedInbox::TYPE_PERSONAL)
            ?: $mailboxes->first();
        if (! $inbox) {
            throw new \RuntimeException('No connected mailbox to send from. Open Inbox or connect Outlook under Integrations.');
        }

        return $inbox;
    }

    protected function conversationId(Lead $lead, string $channel): ?int
    {
        $thread = $this->threadsByChannel($lead)[$channel] ?? null;
        $id = (int) ($thread['conversation_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    protected function twilioFor(User $user): TwilioService
    {
        $company = $user->company;
        $integration = $company ? $this->twilioCompany->getActiveIntegration($company) : null;
        $credentials = $integration ? $this->twilioCompany->getCredentials($integration) : null;
        if (! $credentials) {
            throw new \RuntimeException('Twilio is not connected. Configure it under Integrations.');
        }

        return new TwilioService($credentials['sid'], $credentials['token']);
    }

    protected function sendSms(Lead $lead, User $user, string $body): void
    {
        if (! $user->twilio_sms_number) {
            throw new \RuntimeException('You need an assigned SMS number to send SMS.');
        }

        $conversationId = $this->conversationId($lead, 'sms');
        $conversation = $conversationId
            ? SmsConversation::query()->where('company_id', $lead->company_id)->whereKey($conversationId)->first()
            : null;

        if (! $conversation) {
            $phone = $this->primaryPhone($lead);
            if ($phone === '') {
                throw new \RuntimeException('No SMS thread or phone number yet.');
            }
            $from = $this->twilioCompany->normalizePhone((string) $user->twilio_sms_number);
            $conversation = $this->smsConversations->upsert(
                (int) $lead->company_id,
                $phone,
                $from,
                $lead->name
            );
        }

        $to = $conversation->peer_phone;
        $from = $this->twilioCompany->normalizePhone((string) $user->twilio_sms_number);
        $twilio = $this->twilioFor($user);

        try {
            $sent = $twilio->sendSms($from, $to, $body, route('twilio.sms-status'));
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage());
        }

        $conversation->our_number = $from;
        $conversation->save();

        $message = SmsMessage::create([
            'company_id' => $conversation->company_id,
            'sms_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'message_sid' => $sent->sid,
            'direction' => 'outbound',
            'from_number' => $from,
            'to_number' => $to,
            'body' => $body,
            'status' => $sent->status,
            'sent_at' => now(),
        ]);
        $this->smsConversations->touch($conversation, $message);

        $isNew = SmsMessage::query()->where('sms_conversation_id', $conversation->id)->count() <= 1;
        $this->leadAutoCreate->applyRules($lead, 'sms', LeadRuleEngine::outboundTriggers($isNew), [
            'contact_name' => $conversation->name,
            'phone' => $conversation->peer_phone,
            'message' => $body,
        ]);
    }

    protected function sendWhatsApp(Lead $lead, User $user, string $body): void
    {
        $conversationId = $this->conversationId($lead, 'whatsapp');
        $conversation = $conversationId
            ? WhatsAppConversation::query()->where('company_id', $lead->company_id)->whereKey($conversationId)->first()
            : null;
        if (! $conversation) {
            throw new \RuntimeException('No WhatsApp thread yet.');
        }

        $channel = WhatsAppIntegration::query()
            ->where('company_id', $lead->company_id)
            ->where('is_active', true)
            ->first();
        if (! $channel || ! $channel->from_number) {
            throw new \RuntimeException('WhatsApp is not connected. Configure it under Integrations.');
        }

        $to = $conversation->wa_id ?: $conversation->phone;
        $twilio = $this->twilioFor($user);

        try {
            $sent = $twilio->sendWhatsApp(
                (string) $channel->from_number,
                (string) $to,
                $body,
                $channel->statusCallbackUrl()
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage());
        }

        $message = WhatsAppMessage::create([
            'company_id' => $conversation->company_id,
            'whatsapp_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'direction' => 'outbound',
            'wamid' => $sent->sid,
            'type' => 'text',
            'text' => $body,
            'status' => $sent->status ?? 'sent',
            'raw_payload' => ['sid' => $sent->sid, 'status' => $sent->status],
            'sent_at' => now(),
        ]);
        $conversation->last_message_preview = Str::limit(trim($body), 480);
        $conversation->last_message_at = $message->sent_at ?: now();
        $conversation->save();

        $isNew = WhatsAppMessage::query()->where('whatsapp_conversation_id', $conversation->id)->count() <= 1;
        $this->leadAutoCreate->applyRules($lead, 'whatsapp', LeadRuleEngine::outboundTriggers($isNew), [
            'contact_name' => $conversation->name,
            'phone' => $conversation->wa_id ?: $conversation->phone,
            'message' => $body,
        ]);
    }

    protected function sendViber(Lead $lead, User $user, string $body): void
    {
        $conversationId = $this->conversationId($lead, 'viber');
        $conversation = $conversationId
            ? ViberConversation::query()->where('company_id', $lead->company_id)->whereKey($conversationId)->first()
            : null;
        if (! $conversation) {
            throw new \RuntimeException('No Viber thread yet.');
        }

        $channel = ViberIntegration::query()
            ->where('company_id', $lead->company_id)
            ->where('is_active', true)
            ->first();
        if (! $channel || ! $channel->sender_id) {
            throw new \RuntimeException('Viber is not connected. Configure it under Integrations.');
        }

        $to = $conversation->phone ?: $conversation->viber_user_id;
        $twilio = $this->twilioFor($user);

        try {
            $sent = $twilio->sendViber(
                (string) $channel->sender_id,
                (string) $to,
                $body,
                $channel->statusCallbackUrl()
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage());
        }

        $message = ViberMessage::create([
            'company_id' => $conversation->company_id,
            'viber_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'direction' => 'outbound',
            'message_token' => $sent->sid,
            'type' => 'text',
            'text' => $body,
            'status' => $sent->status ?? 'sent',
            'raw_payload' => ['sid' => $sent->sid, 'status' => $sent->status],
            'sent_at' => now(),
        ]);
        $conversation->last_message_preview = Str::limit(trim($body), 480);
        $conversation->last_message_at = $message->sent_at ?: now();
        $conversation->save();

        $isNew = ViberMessage::query()->where('viber_conversation_id', $conversation->id)->count() <= 1;
        $this->leadAutoCreate->applyRules($lead, 'viber', LeadRuleEngine::outboundTriggers($isNew), [
            'contact_name' => $conversation->name,
            'phone' => $conversation->phone ?: $conversation->viber_user_id,
            'message' => $body,
        ]);
    }

    protected function sendFacebook(Lead $lead, User $user, string $body): void
    {
        $conversationId = $this->conversationId($lead, 'facebook');
        $conversation = $conversationId
            ? FacebookConversation::query()->where('company_id', $lead->company_id)->whereKey($conversationId)->first()
            : null;
        if (! $conversation) {
            throw new \RuntimeException('No Facebook thread yet.');
        }

        $channel = FacebookIntegration::query()
            ->where('company_id', $lead->company_id)
            ->where('is_active', true)
            ->first();
        if (! $channel || ! $channel->page_id) {
            throw new \RuntimeException('Facebook is not connected. Configure it under Integrations.');
        }

        $mid = null;
        $status = 'sent';
        $raw = [];

        try {
            if ((string) $conversation->channel === 'instagram') {
                $token = $channel->getDecryptedPageAccessToken();
                if (! $token) {
                    throw new \RuntimeException('Add a Facebook Page Access Token under Integrations to send Instagram Direct messages.');
                }
                $sent = $this->graphMessaging->send(
                    (string) $channel->page_id,
                    $token,
                    (string) $conversation->peer_id,
                    'text',
                    $body,
                    null
                );
                $mid = $sent['message_id'] ?? null;
                $raw = $sent['raw'] ?? [];
            } else {
                $twilio = $this->twilioFor($user);
                $sent = $twilio->sendMessenger(
                    $channel->senderIdForChannel((string) $conversation->channel),
                    (string) $conversation->peer_id,
                    (string) $conversation->channel,
                    $body,
                    $channel->statusCallbackUrl()
                );
                $mid = $sent->sid;
                $status = $sent->status ?? 'sent';
                $raw = ['sid' => $sent->sid, 'status' => $sent->status];
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage());
        }

        $message = FacebookMessage::create([
            'company_id' => $conversation->company_id,
            'facebook_conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'direction' => 'outbound',
            'mid' => $mid,
            'type' => 'text',
            'text' => $body,
            'status' => $status,
            'raw_payload' => $raw,
            'sent_at' => now(),
        ]);
        $conversation->last_message_preview = Str::limit(trim($body), 480);
        $conversation->last_message_at = $message->sent_at ?: now();
        $conversation->save();

        $isNew = FacebookMessage::query()->where('facebook_conversation_id', $conversation->id)->count() <= 1;
        $this->leadAutoCreate->applyRules($lead, 'facebook', LeadRuleEngine::outboundTriggers($isNew), [
            'contact_name' => $conversation->name,
            'message' => $body,
        ]);
    }

    protected function sendMail(Lead $lead, User $user, string $body, ?string $subject, ?int $inboxId = null, ?array $toEmails = null): void
    {
        $html = $this->mailBodyHtml($body);
        $to = implode(', ', $this->recipientEmails($lead, $toEmails));
        $conversationId = $this->conversationId($lead, 'inbox');
        $conversation = $conversationId
            ? InboxConversation::query()
                ->where('company_id', $lead->company_id)
                ->with(['inbox.account', 'messages'])
                ->whereKey($conversationId)
                ->first()
            : null;

        $selectedInbox = $inboxId ? $this->mailboxFor($user, $inboxId) : null;
        $threadInboxId = (int) ($conversation?->shared_inbox_id ?? 0);
        $useThread = $conversation
            && ($selectedInbox === null || (int) $selectedInbox->id === $threadInboxId);

        if ($useThread) {
            $inbox = $conversation->inbox;
            if (! $inbox || ! $inbox->userCanAccess($user)) {
                throw new \RuntimeException('You do not have access to this mailbox.');
            }
            if (! $inbox->account) {
                throw new \RuntimeException('This inbox is not connected to Outlook.');
            }

            try {
                $this->inboxReplies->send($conversation, $inbox, $user, [
                    'body' => $html,
                    'to' => $to,
                ]);
            } catch (\Throwable $e) {
                throw new \RuntimeException($e->getMessage());
            }

            return;
        }

        $inbox = $selectedInbox ?: $this->mailboxFor($user);
        $composedSubject = trim((string) $subject);
        if ($composedSubject === '') {
            $composedSubject = 'Follow-up';
        }

        try {
            $result = $this->inboxReplies->sendCompose($inbox, $user, [
                'body' => $html,
                'to' => $to,
                'subject' => $composedSubject,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage());
        }

        $created = $result['conversation'] ?? null;
        if ($created instanceof InboxConversation && ! $created->lead_id) {
            $created->lead_id = $lead->id;
            $created->save();
        }
    }

    protected function isBlankBody(string $text, string $channel): bool
    {
        if ($channel !== 'inbox') {
            return trim($text) === '';
        }

        $plain = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($plain !== '') {
            return false;
        }

        return ! preg_match('/<(img|table|hr|video)\b/i', $text);
    }

    protected function mailBodyHtml(string $body): string
    {
        if ($this->looksLikeHtml($body)) {
            return $body;
        }

        return nl2br(e($body), false);
    }

    protected function looksLikeHtml(string $body): bool
    {
        return (bool) preg_match('/<\s*[a-zA-Z][^>]*>/', $body);
    }

    /**
     * @return array{sent: int, failed: int}
     */
    public function processDueScheduledEmails(int $limit = 50): array
    {
        $sent = 0;
        $failed = 0;

        LeadScheduledEmail::query()
            ->where('status', LeadScheduledEmail::STATUS_SENDING)
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update(['status' => LeadScheduledEmail::STATUS_PENDING, 'error_message' => null]);

        $due = LeadScheduledEmail::query()
            ->where('status', LeadScheduledEmail::STATUS_PENDING)
            ->where('send_at', '<=', now())
            ->orderBy('send_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        foreach ($due as $scheduled) {
            $claimed = LeadScheduledEmail::query()
                ->whereKey($scheduled->id)
                ->where('status', LeadScheduledEmail::STATUS_PENDING)
                ->update(['status' => LeadScheduledEmail::STATUS_SENDING]);
            if ($claimed < 1) {
                continue;
            }

            $fresh = $scheduled->fresh(['lead', 'user']);
            if (! $fresh || ! $fresh->lead || ! $fresh->user) {
                $scheduled->update([
                    'status' => LeadScheduledEmail::STATUS_FAILED,
                    'error_message' => 'Lead or sender missing.',
                ]);
                $failed++;

                continue;
            }

            try {
                $this->send($fresh->lead, $fresh->user, 'inbox', $fresh->inbox_template_id, null, null, $fresh->shared_inbox_id, null);
                $fresh->update(['status' => LeadScheduledEmail::STATUS_SENT, 'error_message' => null]);
                $sent++;
            } catch (\Throwable $e) {
                $fresh->update(['status' => LeadScheduledEmail::STATUS_FAILED, 'error_message' => $e->getMessage()]);
                $failed++;
                Log::warning('Scheduled lead email failed', [
                    'scheduled_id' => $fresh->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
