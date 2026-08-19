<?php

namespace App\Services;

use App\Models\InboxConversation;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ChannelUnreadNotifier
{
    public function notifyCompanyUsers(
        int $companyId,
        string $permissionSlug,
        string $notificationType,
        int $conversationId,
        Notification $notification
    ): void {
        $this->notifyUsers(
            $this->usersWithPermission($companyId, $permissionSlug),
            $notificationType,
            $conversationId,
            $notification
        );
    }

    /**
     * @param  Collection<int, User>  $users
     */
    public function notifyUsers(
        Collection $users,
        string $notificationType,
        int $conversationId,
        Notification $notification
    ): void {
        foreach ($users as $user) {
            try {
                $existing = $user->unreadNotifications()
                    ->where('type', $notificationType)
                    ->get()
                    ->first(fn ($item) => (int) ($item->data['conversation_id'] ?? 0) === $conversationId);

                if ($existing) {
                    $existing->delete();
                }

                $user->notify($notification);
            } catch (\Throwable $e) {
                Log::warning('Failed to notify channel unread', [
                    'type' => $notificationType,
                    'conversation_id' => $conversationId,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function markConversationRead(?User $user, string $notificationType, int $conversationId): void
    {
        if (! $user) {
            return;
        }

        $user->unreadNotifications()
            ->where('type', $notificationType)
            ->get()
            ->each(function ($notification) use ($conversationId) {
                if ((int) ($notification->data['conversation_id'] ?? 0) === $conversationId) {
                    $notification->markAsRead();
                }
            });
    }

    /**
     * @return Collection<int, User>
     */
    public function usersWithPermission(int $companyId, string $permissionSlug): Collection
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($permissionSlug) {
                $query->whereHas('role.permissions', fn ($q) => $q->where('slug', $permissionSlug))
                    ->orWhereHas('roles.permissions', fn ($q) => $q->where('slug', $permissionSlug));
            })
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function inboxRecipients(InboxConversation $conversation): Collection
    {
        $conversation->loadMissing('inbox');
        $inbox = $conversation->inbox;
        $ids = collect();

        if ($inbox) {
            if ($inbox->isPersonal()) {
                if ($inbox->created_by) {
                    $ids->push((int) $inbox->created_by);
                }
            } else {
                $ids = $ids->merge($inbox->members()->pluck('users.id'));
            }
        }

        if ($conversation->assigned_to) {
            $ids->push((int) $conversation->assigned_to);
        }

        $ids = $ids->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return User::query()
            ->where('company_id', $conversation->company_id)
            ->whereIn('id', $ids)
            ->get();
    }
}
