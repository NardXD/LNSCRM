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
     */
    public function getContextForCompany(int $companyId, ?string $contextType, User $user, Company $company): string
    {
        if ($contextType !== null && ! self::isAuthorized($contextType, $user, $company)) {
            return "=== ACCESS DENIED ===\n".
                "The current user does not have permission to view {$contextType} data. ".
                "Do not fabricate or guess this data. Tell the user they don't have access and to contact their administrator.";
        }

        return match ($contextType) {
            'leads' => $this->getLeadsContext($companyId),
            'shared-inbox' => $this->getSharedInboxContext($companyId, $user),
            'viber' => $this->getViberContext($companyId),
            'whatsapp' => $this->getWhatsAppContext($companyId),
            'facebook' => $this->getFacebookContext($companyId),
            'sms' => $this->getSmsContext($companyId),
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
    private function getSharedInboxContext(int $companyId, User $user): string
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

    private function getViberContext(int $companyId): string
    {
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

    private function getWhatsAppContext(int $companyId): string
    {
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

    private function getSmsContext(int $companyId): string
    {
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
