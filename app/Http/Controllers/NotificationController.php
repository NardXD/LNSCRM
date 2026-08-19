<?php

namespace App\Http\Controllers;

use App\Models\FacebookConversation;
use App\Models\InboxConversation;
use App\Models\SharedInbox;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $channelItems = $this->channelUnreadItems($user);
        $other = $user->notifications()
            ->whereNotIn('type', self::CHANNEL_NOTIFICATION_TYPES)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (DatabaseNotification $n) => $this->formatNotification($n));

        $notifications = $channelItems
            ->concat($other)
            ->sortByDesc(fn (array $item) => $item['created_at'] ?? '')
            ->values()
            ->take(30);

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
        if (str_starts_with($id, 'channel-')) {
            return response()->json(['success' => true, 'data' => ['notification' => null]]);
        }

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
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'data' => ['unread_count' => $this->channelUnreadCount($user)],
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function channelUnreadItems(User $user): Collection
    {
        $companyId = (int) $user->company_id;
        $items = collect();

        if ($user->hasPermission('view_whatsapp')) {
            WhatsAppConversation::query()
                ->where('company_id', $companyId)
                ->where('unread_count', '>', 0)
                ->orderByDesc('last_message_at')
                ->limit(10)
                ->get()
                ->each(function (WhatsAppConversation $c) use ($items) {
                    $name = $c->name ?: ($c->profile_name ?: ($c->phone ?: 'WhatsApp contact'));
                    $items->push($this->channelItem(
                        'whatsapp',
                        (int) $c->id,
                        $name,
                        'New WhatsApp message from '.$name,
                        (string) ($c->last_message_preview ?: ''),
                        url('/whatsapp?conversation='.$c->id),
                        $c->last_message_at
                    ));
                });
        }

        if ($user->hasPermission('view_viber')) {
            ViberConversation::query()
                ->where('company_id', $companyId)
                ->where('unread_count', '>', 0)
                ->orderByDesc('last_message_at')
                ->limit(10)
                ->get()
                ->each(function (ViberConversation $c) use ($items) {
                    $name = $c->name ?: ($c->phone ?: 'Viber contact');
                    $items->push($this->channelItem(
                        'viber',
                        (int) $c->id,
                        $name,
                        'New Viber message from '.$name,
                        (string) ($c->last_message_preview ?: ''),
                        url('/viber?conversation='.$c->id),
                        $c->last_message_at
                    ));
                });
        }

        if ($user->hasPermission('view_sms')) {
            SmsConversation::query()
                ->where('company_id', $companyId)
                ->where('unread_count', '>', 0)
                ->orderByDesc('last_message_at')
                ->limit(10)
                ->get()
                ->each(function (SmsConversation $c) use ($items) {
                    $name = $c->name ?: ($c->peer_phone ?: 'SMS contact');
                    $items->push($this->channelItem(
                        'sms',
                        (int) $c->id,
                        $name,
                        'New SMS from '.$name,
                        (string) ($c->last_message_preview ?: ''),
                        url('/sms?conversation='.$c->id),
                        $c->last_message_at
                    ));
                });
        }

        if ($user->hasPermission('view_facebook')) {
            FacebookConversation::query()
                ->where('company_id', $companyId)
                ->where('unread_count', '>', 0)
                ->orderByDesc('last_message_at')
                ->limit(10)
                ->get()
                ->each(function (FacebookConversation $c) use ($items) {
                    $channelLabel = $c->channel === 'instagram' ? 'Instagram' : 'Messenger';
                    $name = $c->name ?: ($c->username ?: ($channelLabel.' contact'));
                    $items->push($this->channelItem(
                        $c->channel === 'instagram' ? 'instagram' : 'facebook',
                        (int) $c->id,
                        $name,
                        'New '.$channelLabel.' message from '.$name,
                        (string) ($c->last_message_preview ?: ''),
                        url('/facebook?conversation='.$c->id),
                        $c->last_message_at
                    ));
                });
        }

        $inboxIds = $this->accessibleInboxIds($user);
        if ($inboxIds->isNotEmpty()) {
            InboxConversation::query()
                ->whereIn('shared_inbox_id', $inboxIds)
                ->where('folder', 'inbox')
                ->where('status', 'open')
                ->where('is_read', false)
                ->orderByDesc('last_message_at')
                ->limit(10)
                ->get()
                ->each(function (InboxConversation $c) use ($items) {
                    $from = $c->from_name ?: ($c->from_email ?: 'Unknown sender');
                    $items->push($this->channelItem(
                        'inbox',
                        (int) $c->id,
                        $from,
                        'New email from '.$from,
                        (string) ($c->snippet ?: ($c->subject ?: '')),
                        url('/inbox?conversation='.$c->id),
                        $c->last_message_at
                    ));
                });
        }

        return $items->sortByDesc(fn (array $item) => $item['created_at'] ?? '')->values();
    }

    private function totalUnreadCount(User $user): int
    {
        return $this->channelUnreadCount($user) + $user->unreadNotifications()
            ->whereNotIn('type', self::CHANNEL_NOTIFICATION_TYPES)
            ->count();
    }

    private function channelUnreadCount(User $user): int
    {
        $companyId = (int) $user->company_id;
        $total = 0;

        if ($user->hasPermission('view_whatsapp')) {
            $total += WhatsAppConversation::query()
                ->where('company_id', $companyId)
                ->where('unread_count', '>', 0)
                ->count();
        }

        if ($user->hasPermission('view_viber')) {
            $total += ViberConversation::query()
                ->where('company_id', $companyId)
                ->where('unread_count', '>', 0)
                ->count();
        }

        if ($user->hasPermission('view_sms')) {
            $total += SmsConversation::query()
                ->where('company_id', $companyId)
                ->where('unread_count', '>', 0)
                ->count();
        }

        if ($user->hasPermission('view_facebook')) {
            $total += FacebookConversation::query()
                ->where('company_id', $companyId)
                ->where('unread_count', '>', 0)
                ->count();
        }

        $inboxIds = $this->accessibleInboxIds($user);
        if ($inboxIds->isNotEmpty()) {
            $total += InboxConversation::query()
                ->whereIn('shared_inbox_id', $inboxIds)
                ->where('folder', 'inbox')
                ->where('status', 'open')
                ->where('is_read', false)
                ->count();
        }

        return $total;
    }

    /**
     * @return Collection<int, int>
     */
    private function accessibleInboxIds(User $user): Collection
    {
        if (! $user->hasPermission('view_inbox')) {
            return collect();
        }

        return SharedInbox::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where(function ($q) use ($user) {
                $q->where(function ($personal) use ($user) {
                    $personal->where('type', SharedInbox::TYPE_PERSONAL)
                        ->where('created_by', $user->id);
                })->orWhere(function ($shared) use ($user) {
                    $shared->where('type', SharedInbox::TYPE_SHARED)
                        ->whereHas('members', fn ($m) => $m->where('users.id', $user->id));
                });
            })
            ->pluck('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function channelItem(
        string $channel,
        int $conversationId,
        string $contactName,
        string $summary,
        string $snippet,
        string $url,
        mixed $at
    ): array {
        $time = $at instanceof Carbon ? $at : ($at ? Carbon::parse($at) : now());

        return [
            'id' => 'channel-'.$channel.'-'.$conversationId,
            'type' => $channel.'_message',
            'data' => [
                'type' => $channel.'_message',
                'channel' => $channel,
                'conversation_id' => $conversationId,
                'contact_name' => $contactName,
                'summary' => $summary,
                'snippet' => Str::limit(trim($snippet), 140),
                'url' => $url,
            ],
            'read_at' => null,
            'created_at' => $time->toIso8601String(),
            'created_at_human' => $time->diffForHumans(),
        ];
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
