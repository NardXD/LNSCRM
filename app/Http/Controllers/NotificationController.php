<?php

namespace App\Http\Controllers;

use App\Models\FacebookConversation;
use App\Models\SmsConversation;
use App\Models\User;
use App\Models\ViberConversation;
use App\Models\WhatsAppConversation;
use App\Notifications\FacebookMessageNotification;
use App\Notifications\InboxMessageNotification;
use App\Notifications\SmsMessageNotification;
use App\Notifications\ViberMessageNotification;
use App\Notifications\WhatsAppMessageNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * @var list<class-string>
     */
    private const CHANNEL_NOTIFICATION_TYPES = [
        WhatsAppMessageNotification::class,
        FacebookMessageNotification::class,
        ViberMessageNotification::class,
        SmsMessageNotification::class,
        InboxMessageNotification::class,
    ];

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => ['total' => $this->totalUnreadCount($user)],
        ]);
    }

    /**
     * Per-channel unread message totals for sidebar badges.
     */
    public function channelUnreadCounts(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;
        $counts = [
            'messaging' => 0,
            'viber' => 0,
            'whatsapp' => 0,
            'facebook' => 0,
            'sms' => 0,
        ];

        if ($user->hasPermission('view_messaging') && $companyId) {
            $counts['messaging'] = $this->messagingUnreadCount($user, $companyId);
        }

        if ($user->hasPermission('view_viber') && $companyId) {
            $counts['viber'] = (int) ViberConversation::query()
                ->where('company_id', $companyId)
                ->sum('unread_count');
        }

        if ($user->hasPermission('view_whatsapp') && $companyId) {
            $counts['whatsapp'] = (int) WhatsAppConversation::query()
                ->where('company_id', $companyId)
                ->sum('unread_count');
        }

        if ($user->hasPermission('view_facebook') && $companyId) {
            $counts['facebook'] = $this->facebookUnreadCount($user, $companyId);
        }

        if ($user->hasPermission('view_sms') && $companyId) {
            $counts['sms'] = (int) SmsConversation::query()
                ->where('company_id', $companyId)
                ->sum('unread_count');
        }

        return response()->json([
            'success' => true,
            'data' => $counts,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = $this->personalNotifications($user)
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (DatabaseNotification $n) => $this->formatNotification($n))
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications,
                'unread_count' => $this->totalUnreadCount($user),
            ],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'data' => ['notification' => $this->formatNotification($notification->fresh())],
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->personalNotifications($user)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => ['unread_count' => $this->totalUnreadCount($user)],
        ]);
    }

    private function totalUnreadCount(User $user): int
    {
        return $this->personalNotifications($user)->whereNull('read_at')->count();
    }

    /**
     * Bell items that are about this user. Channel message rows stay out; those use sidebar badges.
     */
    private function personalNotifications(User $user)
    {
        return $user->notifications()
            ->whereNotIn('type', self::CHANNEL_NOTIFICATION_TYPES)
            ->where(function ($query) {
                $query->whereIn('data->involves', ['assignee', 'mention', 'draft_share', 'reopen', 'lead', 'reply'])
                    ->orWhereIn('data->type', ['lead_assigned', 'lead_rule', 'messaging_mention', 'inbox_comment_mention'])
                    ->orWhere('data->is_mention', true);
            });
    }

    private function messagingUnreadCount(User $user, int $companyId): int
    {
        $total = 0;

        foreach ($user->conversations()->where('conversations.company_id', $companyId)->get() as $conv) {
            $pivot = $conv->participants()->where('users.id', $user->id)->first()?->pivot;
            $lastRead = $pivot?->last_read_at;
            $total += $conv->messages()
                ->where('user_id', '!=', $user->id)
                ->where('created_at', '>', $lastRead ?? '1970-01-01')
                ->count();
        }

        return $total;
    }

    /**
     * Per-agent unread conversation count — a conversation only counts as read
     * for this user once they have their own read row for it.
     */
    private function facebookUnreadCount(User $user, int $companyId): int
    {
        return FacebookConversation::query()
            ->where('facebook_conversations.company_id', $companyId)
            ->leftJoin('facebook_conversation_user_reads as ur', function ($join) use ($user) {
                $join->on('ur.facebook_conversation_id', '=', 'facebook_conversations.id')
                    ->where('ur.user_id', '=', $user->id);
            })
            ->whereRaw('COALESCE(ur.is_read, 0) = 0')
            ->count();
    }

    private function formatNotification(DatabaseNotification $notification): array
    {
        $data = $notification->data ?? [];

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? class_basename($notification->type),
            'data' => $data,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_at_human' => optional($notification->created_at)->diffForHumans(),
        ];
    }
}
