<?php

namespace App\Services;

use App\Models\BroadcastCampaign;
use App\Models\Company;
use App\Models\FacebookConversation;
use App\Models\InboxConversation;
use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseFaq;
use App\Models\KnowledgeBaseGuide;
use App\Models\Lead;
use App\Models\SharedInbox;
use App\Models\SmsConversation;
use App\Models\User;
use App\Models\ViberConversation;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Facades\DB;

class OpenAiContextService
{
    /**
     * Permission slug required to view each context type's data.
     */
    private const CONTEXT_PERMISSIONS = [
        'leads' => 'view_leads',
        'shared-inbox' => 'view_inbox',
        'viber' => 'view_viber',
        'whatsapp' => 'view_whatsapp',
        'facebook' => 'view_facebook',
        'sms' => 'view_sms',
        'broadcast' => 'view_broadcast_messaging',
        'knowledge-base' => 'view_knowledge_base',
    ];

    /**
     * Company module slug that must be enabled for each context type.
     */
    private const CONTEXT_MODULES = [
        'leads' => 'client-management',
        'shared-inbox' => 'inbox',
        'viber' => 'viber',
        'whatsapp' => 'whatsapp',
        'facebook' => 'facebook',
        'sms' => 'sms',
        'broadcast' => 'broadcast-messaging',
        'knowledge-base' => 'knowledge-base',
    ];

    /**
     * Whether the given user (and their company) is allowed to see a context type's data.
     */
    public static function isAuthorized(string $contextType, User $user, Company $company): bool
    {
        $permission = self::CONTEXT_PERMISSIONS[$contextType] ?? null;
        if ($permission === null) {
            return true;
        }

        if (! $user->hasPermission($permission)) {
            return false;
        }

        return $company->hasModuleAccess(self::CONTEXT_MODULES[$contextType]);
    }

    /**
     * Pull an email address or phone number out of a user's message, for targeted conversation lookup.
     *
     * @return array{type: 'email'|'phone', value: string}|null
     */
    public static function extractSearchIdentifier(string $message): ?array
    {
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $message, $matches)) {
            return ['type' => 'email', 'value' => $matches[0]];
        }

        if (preg_match('/\+?\d[\d\s().-]{6,}\d/', $message, $matches)) {
            $digits = preg_replace('/\D/', '', $matches[0]);
            if (strlen($digits) >= 7) {
                return ['type' => 'phone', 'value' => $digits];
            }
        }

        return null;
    }

    /**
     * Infer context type from user message keywords. Returns context_type or null if no match.
     */
    public static function inferContextTypeFromMessage(string $message): ?string
    {
        $text = strtolower(trim($message));
        $mappings = [
            'leads' => ['lead', 'leads', 'prospect', 'prospects', 'pipeline', 'sales rep'],
            'shared-inbox' => ['shared inbox', 'shared-inbox', 'shared inboxes', 'inbox', 'inboxes', 'mailbox', 'mailboxes', 'email thread'],
            'viber' => ['viber'],
            'whatsapp' => ['whatsapp', 'whats app', 'whats-app'],
            'facebook' => ['facebook', 'messenger', 'instagram', 'fb page'],
            'sms' => ['sms', 'text message', 'texting', 'sms conversation'],
            'broadcast' => ['broadcast', 'campaign', 'campaigns', 'bulk message', 'mass message', 'blast'],
            'knowledge-base' => ['knowledge base', 'knowledge-base', 'kb', 'article', 'articles', 'faq', 'faqs', 'guide', 'guides', 'documentation'],
        ];

        foreach ($mappings as $contextType => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $contextType;
                }
            }
        }

        return null;
    }

    /**
     * Load CRM context data for the given type and company, scoped to what the user is allowed to see.
     * When the user's message names a specific email or phone number, the relevant channel will search
     * for that conversation (with its actual message content) instead of listing recent conversations.
     */
    public function getContextForCompany(int $companyId, ?string $contextType, User $user, Company $company, string $message = ''): string
    {
        if ($contextType !== null && ! self::isAuthorized($contextType, $user, $company)) {
            return "=== ACCESS DENIED ===\n".
                "The current user does not have permission to view {$contextType} data. ".
                "Do not fabricate or guess this data. Tell the user they don't have access and to contact their administrator.";
        }

        return match ($contextType) {
            'leads' => $this->getLeadsContext($companyId),
            'shared-inbox' => $this->getSharedInboxContext($companyId, $user, $message),
            'viber' => $this->getViberContext($companyId, $message),
            'whatsapp' => $this->getWhatsAppContext($companyId, $message),
            'facebook' => $this->getFacebookContext($companyId),
            'sms' => $this->getSmsContext($companyId, $message),
            'broadcast' => $this->getBroadcastContext($companyId),
            'knowledge-base' => $this->getKnowledgeBaseContext($companyId),
            default => $this->getGeneralContext($companyId, $user, $company),
        };
    }

    private function getLeadsContext(int $companyId): string
    {
        $leads = Lead::where('company_id', $companyId)
            ->with(['assignedUser:id,name', 'labels:id,name'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($l) => [
                'name' => $l->name,
                'status' => $l->status,
                'source' => $l->source,
                'customer_type' => $l->customer_type,
                'assigned_to' => $l->assignedUser?->name,
                'labels' => $l->labels->pluck('name')->all(),
                'created_at' => $l->created_at?->format('Y-m-d'),
            ]);

        $byStatus = Lead::where('company_id', $companyId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $bySource = Lead::where('company_id', $companyId)
            ->select('source', DB::raw('count(*) as count'))
            ->groupBy('source')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('count', 'source')
            ->toArray();

        $totalLeads = Lead::where('company_id', $companyId)->count();
        $unassigned = Lead::where('company_id', $companyId)->whereNull('assigned_to')->count();

        $lines = [
            "=== LEADS DATA (Company ID: {$companyId}) ===",
            "Total leads: {$totalLeads}",
            "Unassigned leads: {$unassigned}",
            'Leads by status: '.json_encode($byStatus),
            'Leads by source (top 10): '.json_encode($bySource),
            '',
            'Recent leads:',
            json_encode($leads->toArray(), JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    /**
     * Shared inbox data scoped to only the inboxes this user can access (personal/broadcast inboxes
     * they own, or shared inboxes they're a member of) — mirrors SharedInbox::userCanAccess().
     */
    private function getSharedInboxContext(int $companyId, User $user, string $message = ''): string
    {
        $accessibleInboxes = SharedInbox::where('company_id', $companyId)
            ->withCount('conversations')
            ->with('members:id,name')
            ->get()
            ->filter(fn (SharedInbox $inbox) => $inbox->userCanAccess($user))
            ->values();

        if ($accessibleInboxes->isEmpty()) {
            return "=== SHARED INBOX DATA (Company ID: {$companyId}) ===\n".
                'This user is not a member of any shared inbox.';
        }

        $accessibleIds = $accessibleInboxes->pluck('id');

        $identifier = self::extractSearchIdentifier($message);
        if ($identifier !== null && $identifier['type'] === 'email') {
            return $this->searchSharedInboxByEmail($companyId, $accessibleIds, $identifier['value']);
        }

        $inboxes = $accessibleInboxes->map(fn ($i) => [
            'name' => $i->name,
            'type' => $i->type,
            'is_active' => (bool) $i->is_active,
            'members' => $i->members->pluck('name')->all(),
            'conversations_count' => $i->conversations_count,
        ]);

        $conversations = InboxConversation::where('company_id', $companyId)
            ->whereIn('shared_inbox_id', $accessibleIds)
            ->notMerged()
            ->with(['inbox:id,name', 'assignee:id,name'])
            ->latest('last_message_at')
            ->limit(20)
            ->get()
            ->map(fn ($c) => [
                'inbox' => $c->inbox?->name,
                'subject' => $c->subject,
                'from' => $c->from_name ?? $c->from_email,
                'status' => $c->status,
                'assigned_to' => $c->assignee?->name,
                'is_read' => (bool) $c->is_read,
                'last_message_at' => $c->last_message_at?->format('Y-m-d H:i'),
            ]);

        $unreadCount = InboxConversation::where('company_id', $companyId)
            ->whereIn('shared_inbox_id', $accessibleIds)
            ->notMerged()
            ->where('is_read', false)
            ->count();

        $byStatus = InboxConversation::where('company_id', $companyId)
            ->whereIn('shared_inbox_id', $accessibleIds)
            ->notMerged()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $lines = [
            "=== SHARED INBOX DATA (Company ID: {$companyId}) ===",
            'Scoped to inboxes this user can access.',
            "Unread conversations: {$unreadCount}",
            'Conversations by status: '.json_encode($byStatus),
            '',
            'Accessible shared inboxes:',
            json_encode($inboxes->toArray(), JSON_PRETTY_PRINT),
            '',
            'Recent conversations:',
            json_encode($conversations->toArray(), JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    /**
     * Find shared-inbox conversation(s) involving a specific email address, with actual message
     * content — used when the user asks about a named person/email rather than a general summary.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $accessibleInboxIds
     */
    private function searchSharedInboxByEmail(int $companyId, $accessibleInboxIds, string $email): string
    {
        $conversations = InboxConversation::where('company_id', $companyId)
            ->whereIn('shared_inbox_id', $accessibleInboxIds)
            ->notMerged()
            ->with(['inbox:id,name', 'assignee:id,name'])
            ->where(function ($query) use ($email) {
                $query->where('from_email', $email)
                    ->orWhereHas('messages', function ($messageQuery) use ($email) {
                        $messageQuery->where('from_email', $email)
                            ->orWhere('to_emails', 'like', "%{$email}%")
                            ->orWhere('cc_emails', 'like', "%{$email}%");
                    });
            })
            ->orderByDesc('last_message_at')
            ->limit(5)
            ->get();

        if ($conversations->isEmpty()) {
            return "=== SHARED INBOX SEARCH: \"{$email}\" ===\n".
                "No conversation from or involving {$email} was found in the shared inboxes this user can access. ".
                'Tell the user no matching conversation was found. Do not fabricate one.';
        }

        $results = $conversations->map(function (InboxConversation $c) {
            $messages = $c->messages()
                ->orderByDesc('sent_at')
                ->limit(10)
                ->get()
                ->map(fn ($m) => [
                    'direction' => $m->direction,
                    'from' => $m->from_name ?? $m->from_email,
                    'sent_at' => $m->sent_at?->format('Y-m-d H:i'),
                    'body' => mb_substr(strip_tags($m->body_text ?? $m->body_html ?? ''), 0, 600),
                ])
                ->reverse()
                ->values();

            return [
                'inbox' => $c->inbox?->name,
                'subject' => $c->subject,
                'from' => $c->from_name ?? $c->from_email,
                'status' => $c->status,
                'assigned_to' => $c->assignee?->name,
                'is_read' => (bool) $c->is_read,
                'last_message_at' => $c->last_message_at?->format('Y-m-d H:i'),
                'messages' => $messages->toArray(),
            ];
        });

        $lines = [
            "=== SHARED INBOX SEARCH: \"{$email}\" ===",
            'Matching conversation(s) with full message content:',
            json_encode($results->toArray(), JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    private function getViberContext(int $companyId, string $message = ''): string
    {
        $identifier = self::extractSearchIdentifier($message);
        if ($identifier !== null && $identifier['type'] === 'phone') {
            return $this->searchConversationByPhone(
                'VIBER',
                $identifier['value'],
                ViberConversation::where('company_id', $companyId)
                    ->where('phone', 'like', "%{$identifier['value']}%")
                    ->first(),
                'text'
            );
        }

        $conversations = ViberConversation::where('company_id', $companyId)
            ->latest('last_message_at')
            ->limit(20)
            ->get()
            ->map(fn ($c) => [
                'name' => $c->name,
                'phone' => $c->phone,
                'is_subscribed' => (bool) $c->is_subscribed,
                'unread_count' => $c->unread_count,
                'last_message_preview' => $c->last_message_preview,
                'last_message_at' => $c->last_message_at?->format('Y-m-d H:i'),
            ]);

        $totalConversations = ViberConversation::where('company_id', $companyId)->count();
        $totalUnread = ViberConversation::where('company_id', $companyId)->sum('unread_count');

        $lines = [
            "=== VIBER DATA (Company ID: {$companyId}) ===",
            "Total conversations: {$totalConversations}",
            "Total unread messages: {$totalUnread}",
            '',
            'Recent conversations:',
            json_encode($conversations->toArray(), JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    private function getWhatsAppContext(int $companyId, string $message = ''): string
    {
        $identifier = self::extractSearchIdentifier($message);
        if ($identifier !== null && $identifier['type'] === 'phone') {
            return $this->searchConversationByPhone(
                'WHATSAPP',
                $identifier['value'],
                WhatsAppConversation::where('company_id', $companyId)
                    ->where(function ($query) use ($identifier) {
                        $query->where('phone', 'like', "%{$identifier['value']}%")
                            ->orWhere('wa_id', 'like', "%{$identifier['value']}%");
                    })
                    ->first(),
                'text'
            );
        }

        $conversations = WhatsAppConversation::where('company_id', $companyId)
            ->latest('last_message_at')
            ->limit(20)
            ->get()
            ->map(fn ($c) => [
                'name' => $c->profile_name ?? $c->name,
                'phone' => $c->phone,
                'is_subscribed' => (bool) $c->is_subscribed,
                'unread_count' => $c->unread_count,
                'within_messaging_window' => $c->isWithinMessagingWindow(),
                'last_message_preview' => $c->last_message_preview,
                'last_message_at' => $c->last_message_at?->format('Y-m-d H:i'),
            ]);

        $totalConversations = WhatsAppConversation::where('company_id', $companyId)->count();
        $totalUnread = WhatsAppConversation::where('company_id', $companyId)->sum('unread_count');

        $lines = [
            "=== WHATSAPP DATA (Company ID: {$companyId}) ===",
            "Total conversations: {$totalConversations}",
            "Total unread messages: {$totalUnread}",
            '',
            'Recent conversations:',
            json_encode($conversations->toArray(), JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    /**
     * Build a search-result block for a Viber/WhatsApp/SMS conversation matched by phone number,
     * including its actual message content — shared by getViberContext/getWhatsAppContext/getSmsContext.
     */
    private function searchConversationByPhone(string $label, string $phone, mixed $conversation, string $textField): string
    {
        if ($conversation === null) {
            return "=== {$label} SEARCH: \"{$phone}\" ===\n".
                "No {$label} conversation was found for that phone number. ".
                'Tell the user no matching conversation was found. Do not fabricate one.';
        }

        $messages = $conversation->messages()
            ->orderByDesc('sent_at')
            ->limit(15)
            ->get()
            ->map(fn ($m) => [
                'direction' => $m->direction,
                'sent_at' => $m->sent_at?->format('Y-m-d H:i'),
                'text' => mb_substr((string) ($m->{$textField} ?? ''), 0, 600),
            ])
            ->reverse()
            ->values();

        $result = [
            'name' => $conversation->name ?? $conversation->profile_name ?? null,
            'phone' => $conversation->phone ?? $conversation->peer_phone ?? null,
            'unread_count' => $conversation->unread_count,
            'last_message_at' => $conversation->last_message_at?->format('Y-m-d H:i'),
            'messages' => $messages->toArray(),
        ];

        $lines = [
            "=== {$label} SEARCH: \"{$phone}\" ===",
            'Matching conversation with full message content:',
            json_encode($result, JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    private function getFacebookContext(int $companyId): string
    {
        $conversations = FacebookConversation::where('company_id', $companyId)
            ->latest('last_message_at')
            ->limit(20)
            ->get()
            ->map(fn ($c) => [
                'channel' => $c->channel,
                'name' => $c->name,
                'username' => $c->username,
                'unread_count' => $c->unread_count,
                'last_message_preview' => $c->last_message_preview,
                'last_message_at' => $c->last_message_at?->format('Y-m-d H:i'),
            ]);

        $byChannel = FacebookConversation::where('company_id', $companyId)
            ->select('channel', DB::raw('count(*) as count'))
            ->groupBy('channel')
            ->pluck('count', 'channel')
            ->toArray();

        $totalUnread = FacebookConversation::where('company_id', $companyId)->sum('unread_count');

        $lines = [
            "=== FACEBOOK / INSTAGRAM DATA (Company ID: {$companyId}) ===",
            "Total unread messages: {$totalUnread}",
            'Conversations by channel (messenger, instagram): '.json_encode($byChannel),
            '',
            'Recent conversations:',
            json_encode($conversations->toArray(), JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    private function getSmsContext(int $companyId, string $message = ''): string
    {
        $identifier = self::extractSearchIdentifier($message);
        if ($identifier !== null && $identifier['type'] === 'phone') {
            return $this->searchConversationByPhone(
                'SMS',
                $identifier['value'],
                SmsConversation::where('company_id', $companyId)
                    ->where('peer_phone', 'like', "%{$identifier['value']}%")
                    ->first(),
                'body'
            );
        }

        $conversations = SmsConversation::where('company_id', $companyId)
            ->latest('last_message_at')
            ->limit(20)
            ->get()
            ->map(fn ($c) => [
                'name' => $c->name,
                'peer_phone' => $c->peer_phone,
                'our_number' => $c->our_number,
                'unread_count' => $c->unread_count,
                'last_message_preview' => $c->last_message_preview,
                'last_message_at' => $c->last_message_at?->format('Y-m-d H:i'),
            ]);

        $totalConversations = SmsConversation::where('company_id', $companyId)->count();
        $totalUnread = SmsConversation::where('company_id', $companyId)->sum('unread_count');

        $lines = [
            "=== SMS DATA (Company ID: {$companyId}) ===",
            "Total conversations: {$totalConversations}",
            "Total unread messages: {$totalUnread}",
            '',
            'Recent conversations:',
            json_encode($conversations->toArray(), JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    private function getBroadcastContext(int $companyId): string
    {
        $campaigns = BroadcastCampaign::where('company_id', $companyId)
            ->with('inbox:id,name')
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn ($c) => [
                'name' => $c->name,
                'type' => $c->type,
                'status' => $c->status,
                'inbox' => $c->inbox?->name,
                'recipient_count' => $c->recipient_count,
                'sent_count' => $c->sent_count,
                'delivered_count' => $c->delivered_count,
                'failed_count' => $c->failed_count,
                'sent_at' => $c->sent_at?->format('Y-m-d H:i'),
            ]);

        $byStatus = BroadcastCampaign::where('company_id', $companyId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byType = BroadcastCampaign::where('company_id', $companyId)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $lines = [
            "=== BROADCAST MESSAGING DATA (Company ID: {$companyId}) ===",
            'Campaigns by status: '.json_encode($byStatus),
            'Campaigns by type: '.json_encode($byType),
            '',
            'Recent campaigns:',
            json_encode($campaigns->toArray(), JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    private function getKnowledgeBaseContext(int $companyId): string
    {
        $articles = KnowledgeBaseArticle::where('company_id', $companyId)
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn ($a) => [
                'title' => $a->title,
                'category' => $a->category,
                'views' => $a->views,
                'excerpt' => mb_substr(strip_tags($a->excerpt ?? $a->content ?? ''), 0, 150),
            ]);

        $faqs = KnowledgeBaseFaq::where('company_id', $companyId)
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn ($f) => [
                'question' => $f->question,
                'category' => $f->category,
                'views' => $f->views,
            ]);

        $guides = KnowledgeBaseGuide::where('company_id', $companyId)
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn ($g) => [
                'title' => $g->title,
                'category' => $g->category,
                'duration' => $g->duration,
            ]);

        $byCategory = KnowledgeBaseArticle::where('company_id', $companyId)
            ->select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        $lines = [
            "=== KNOWLEDGE BASE DATA (Company ID: {$companyId}) ===",
            'Articles by category: '.json_encode($byCategory),
            '',
            'Recent articles:',
            json_encode($articles->toArray(), JSON_PRETTY_PRINT),
            '',
            'Recent FAQs:',
            json_encode($faqs->toArray(), JSON_PRETTY_PRINT),
            '',
            'Recent guides:',
            json_encode($guides->toArray(), JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }

    /**
     * Overview counts, limited to the context types this user and company are authorized for.
     */
    private function getGeneralContext(int $companyId, User $user, Company $company): string
    {
        $counters = [
            'leads' => fn () => Lead::where('company_id', $companyId)->count(),
            'shared_inboxes' => fn () => SharedInbox::where('company_id', $companyId)->count(),
            'viber_conversations' => fn () => ViberConversation::where('company_id', $companyId)->count(),
            'whatsapp_conversations' => fn () => WhatsAppConversation::where('company_id', $companyId)->count(),
            'facebook_conversations' => fn () => FacebookConversation::where('company_id', $companyId)->count(),
            'sms_conversations' => fn () => SmsConversation::where('company_id', $companyId)->count(),
            'broadcast_campaigns' => fn () => BroadcastCampaign::where('company_id', $companyId)->count(),
            'knowledge_base_articles' => fn () => KnowledgeBaseArticle::where('company_id', $companyId)->count(),
        ];

        $contextTypeForCounter = [
            'leads' => 'leads',
            'shared_inboxes' => 'shared-inbox',
            'viber_conversations' => 'viber',
            'whatsapp_conversations' => 'whatsapp',
            'facebook_conversations' => 'facebook',
            'sms_conversations' => 'sms',
            'broadcast_campaigns' => 'broadcast',
            'knowledge_base_articles' => 'knowledge-base',
        ];

        $counts = [];
        foreach ($counters as $key => $resolve) {
            if (self::isAuthorized($contextTypeForCounter[$key], $user, $company)) {
                $counts[$key] = $resolve();
            }
        }

        if ($counts === []) {
            return "=== CRM OVERVIEW (Company ID: {$companyId}) ===\n".
                "This user's role does not have access to any CRM modules covered by the AI assistant.";
        }

        return "=== CRM OVERVIEW (Company ID: {$companyId}) ===\n".
            'Key counts: '.json_encode($counts, JSON_PRETTY_PRINT)."\n".
            'Use a specific summary action (Leads, Shared Inbox, Viber, WhatsApp, Facebook, SMS, Broadcast, Knowledge Base) for detailed data.';
    }
}
