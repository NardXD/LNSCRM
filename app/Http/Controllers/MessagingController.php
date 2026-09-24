<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use App\Notifications\MessagingMentionNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MessagingController extends Controller
{
    /**
     * Display the messaging page.
     */
    public function index()
    {
        return view('dashboard.messaging');
    }

    /**
     * Get total unread message count across all conversations (group and direct).
     */
    public function getUnreadCount()
    {
        $companyId = $this->requireCompany();
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();

        // Single join instead of N+1 (one COUNT per conversation).
        $total = (int) DB::table('conversation_participants as cp')
            ->join('conversations as c', 'c.id', '=', 'cp.conversation_id')
            ->join('messages as m', 'm.conversation_id', '=', 'c.id')
            ->where('cp.user_id', $user->id)
            ->where('c.company_id', $companyId)
            ->where(function ($q) {
                $q->where('c.kind', Conversation::KIND_CHAT)->orWhereNull('c.kind');
            })
            ->where('m.user_id', '!=', $user->id)
            ->where(function ($q) {
                $q->whereNull('cp.last_read_at')
                    ->orWhereColumn('m.created_at', '>', 'cp.last_read_at');
            })
            ->count();

        return response()->json(['success' => true, 'data' => ['total' => $total]]);
    }

    /**
     * Ensure user has company and get company ID.
     */
    private function requireCompany(): ?int
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            return null;
        }

        return $user->company_id;
    }

    private function rejectIfDiscussion(Conversation $conversation)
    {
        if ($conversation->isDiscussion()) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        return null;
    }

    /**
     * Get all conversations for the authenticated user within their company.
     */
    public function getConversations(Request $request)
    {
        $companyId = $this->requireCompany();
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        $search = $request->get('search', '');
        $limit = min(max((int) $request->get('limit', 15), 5), 50);
        $offset = max((int) $request->get('offset', 0), 0);

        $query = $user->conversations()
            ->where('conversations.company_id', $companyId)
            ->where(function ($q) {
                $q->where('conversations.kind', Conversation::KIND_CHAT)
                    ->orWhereNull('conversations.kind');
            })
            ->with(['participants' => function ($q) {
                $q->where('users.id', '!=', Auth::id());
            }])
            ->with(['latestMessage' => fn ($q) => $q->with('user')]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('conversations.name', 'like', "%{$search}%")
                    ->orWhereHas('participants', function ($p) use ($search) {
                        $p->where('users.name', 'like', "%{$search}%")
                            ->orWhere('users.email', 'like', "%{$search}%");
                    });
            });
        }

        $conversations = $query->orderByRaw(
            '(SELECT MAX(created_at) FROM messages WHERE messages.conversation_id = conversations.id) DESC'
        )->offset($offset)->limit($limit + 1)->get();

        $hasMore = $conversations->count() > $limit;
        if ($hasMore) {
            $conversations = $conversations->take($limit);
        }

        $data = $conversations->map(function ($conv) use ($user) {
            $lastMessage = $conv->latestMessage;
            $displayName = $conv->type === 'group'
                ? $conv->name
                : ($conv->participants->first()?->name ?? 'Unknown');
            $preview = $lastMessage
                ? ($lastMessage->user_id === $user->id ? 'You: ' : $lastMessage->user->name.': ').$this->messagePreview($lastMessage)
                : 'No messages yet';

            $pivot = $conv->participants()->where('users.id', $user->id)->first()?->pivot;
            $lastRead = $pivot?->last_read_at;
            // Count messages from others after last_read_at (works for both direct and group chats)
            $unread = $conv->messages()
                ->where('user_id', '!=', $user->id)
                ->where('created_at', '>', $lastRead ?? '1970-01-01')
                ->count();

            $otherParticipant = $conv->participants->first();

            return [
                'id' => $conv->id,
                'type' => $conv->type,
                'name' => $displayName,
                'preview' => $preview,
                'last_message_at' => $lastMessage?->created_at?->toIso8601String(),
                'unread_count' => max($unread, 0),
                'participants_count' => $conv->participants->count() + 1, // +1 for current user in group
                'avatar_initials' => $conv->type === 'group' ? $this->getInitials($conv->name ?? '') : $this->getInitials($otherParticipant?->name ?? ''),
                'avatar_photo' => $conv->type === 'group' ? ($conv->photo ? public_media_url($conv->photo) : null) : ($otherParticipant?->photo ? public_media_url($otherParticipant->photo) : null),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'has_more' => $hasMore,
        ]);
    }

    /**
     * Get company users (for new chat / add members).
     */
    public function getUsers(Request $request)
    {
        $companyId = $this->requireCompany();
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $search = $request->get('search', '');
        $excludeIds = $request->get('exclude', []);

        $query = User::where('company_id', $companyId)
            ->where('status', 'active')
            ->where('id', '!=', Auth::id());

        if (is_array($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->limit(50)->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'initials' => $this->getInitials($u->name),
                'photo' => $u->photo ? public_media_url($u->photo) : null,
            ];
        });

        return response()->json(['success' => true, 'data' => $users]);
    }

    /**
     * Create a new conversation (direct or group).
     */
    public function createConversation(Request $request)
    {
        $companyId = $this->requireCompany();
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(['direct', 'group'])],
            'name' => ['required_if:type,group', 'nullable', 'string', 'max:255'],
            'photo_path' => [
                'nullable',
                'string',
                'max:500',
                Rule::when(filled($request->photo_path), ['regex:/^messaging\/.+/']),
            ],
            'participant_ids' => ['required', 'array'],
            'participant_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $user = Auth::user();
        $participantIds = array_unique($validated['participant_ids']);

        // Ensure all participants belong to the same company
        $companyUsers = User::where('company_id', $companyId)->whereIn('id', $participantIds)->pluck('id')->toArray();
        if (count($participantIds) !== count(array_intersect($participantIds, $companyUsers))) {
            return response()->json(['success' => false, 'message' => 'Invalid participants'], 422);
        }

        if ($validated['type'] === 'direct') {
            if (count($participantIds) !== 1) {
                return response()->json(['success' => false, 'message' => 'Direct chat requires exactly one participant'], 422);
            }
            $otherId = $participantIds[0];
            $existing = Conversation::where('company_id', $companyId)
                ->where('type', 'direct')
                ->where(function ($q) {
                    $q->where('kind', Conversation::KIND_CHAT)->orWhereNull('kind');
                })
                ->whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
                ->whereHas('participants', fn ($q) => $q->where('users.id', $otherId))
                ->first();
            if ($existing) {
                return response()->json(['success' => true, 'data' => $this->formatConversation($existing)]);
            }
        }

        return DB::transaction(function () use ($companyId, $user, $validated, $participantIds) {
            $conv = Conversation::create([
                'company_id' => $companyId,
                'type' => $validated['type'],
                'kind' => Conversation::KIND_CHAT,
                'status' => Conversation::STATUS_OPEN,
                'name' => $validated['type'] === 'group' ? $validated['name'] : null,
                'photo' => $validated['type'] === 'group' && ! empty($validated['photo_path']) ? $validated['photo_path'] : null,
                'created_by' => $user->id,
            ]);

            $allParticipantIds = array_merge([$user->id], $participantIds);
            foreach ($allParticipantIds as $pid) {
                $conv->participants()->attach($pid);
            }

            return response()->json(['success' => true, 'data' => $this->formatConversation($conv->fresh())]);
        });
    }

    /**
     * Get messages for a conversation.
     */
    public function getMessages(Request $request, Conversation $conversation)
    {
        if ($reject = $this->rejectIfDiscussion($conversation)) {
            return $reject;
        }

        $companyId = $this->requireCompany();
        if (! $companyId || $conversation->company_id !== $companyId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $user = Auth::user();
        if (! $conversation->participants()->where('users.id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Not a participant'], 403);
        }

        $conversation->participants()->updateExistingPivot($user->id, [
            'last_read_at' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
        ]);

        $limit = min(max((int) $request->get('limit', 25), 5), 100);
        $beforeId = $request->get('before_id');

        $query = $conversation->messages()->with(['user', 'replyTo.user', 'reactions.user']);

        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }
        // Fetched newest-first so a plain take($limit) below always drops the
        // oldest (extra probe) row, regardless of branch; reversed to
        // oldest-first afterwards for display.
        $messages = $query->reorder('id', 'desc')
            ->limit($limit + 1)
            ->get();

        $hasMore = $messages->count() > $limit;
        if ($hasMore) {
            $messages = $messages->take($limit);
        }
        $messages = $messages->reverse()->values();
        $receipts = $this->formatReceipts($conversation, $user);
        $mentionUsers = $this->mentionUsersById($messages);
        $messages = $messages->map(fn ($m) => $this->formatMessage($m, $user, $receipts, $mentionUsers));

        $conversation->load('participants');
        $otherParticipant = $conversation->participants->firstWhere('id', '!=', $user->id);
        $displayName = $conversation->type === 'group'
            ? $conversation->name
            : ($otherParticipant?->name ?? 'Unknown');
        $avatarPhoto = $conversation->type === 'group'
            ? ($conversation->photo ? public_media_url($conversation->photo) : null)
            : ($otherParticipant?->photo ? public_media_url($otherParticipant->photo) : null);

        $isCreator = $conversation->type === 'group' && $conversation->created_by === $user->id;

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => [
                    'id' => $conversation->id,
                    'type' => $conversation->type,
                    'name' => $displayName,
                    'avatar_photo' => $avatarPhoto,
                    'avatar_initials' => $this->getInitials($displayName),
                    'is_creator' => $isCreator,
                    'members' => $conversation->type === 'group'
                        ? $this->formatConversationMembers($conversation, $user)
                        : [],
                ],
                'messages' => $messages,
                'has_more' => $hasMore,
                'receipts' => collect($receipts)->map(fn (array $r) => Arr::except($r, ['last_read_raw']))->values()->all(),
            ],
        ]);
    }

    /**
     * Send a message.
     */
    public function sendMessage(Request $request, Conversation $conversation)
    {
        if ($reject = $this->rejectIfDiscussion($conversation)) {
            return $reject;
        }

        $companyId = $this->requireCompany();
        if (! $companyId || $conversation->company_id !== $companyId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $user = Auth::user();
        if (! $conversation->participants()->where('users.id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Not a participant'], 403);
        }

        $validated = $request->validate([
            'body' => ['nullable', 'string'],
            'attachment_path' => ['nullable', 'string'],
            'attachment_name' => ['nullable', 'string'],
            'attachment_type' => ['nullable', 'string', 'in:file,image'],
            'reply_to_id' => ['nullable', 'integer'],
            'mentioned_user_ids' => ['nullable', 'array', 'max:20'],
            'mentioned_user_ids.*' => ['integer'],
        ]);

        if (empty($validated['body']) && empty($validated['attachment_path'])) {
            return response()->json(['success' => false, 'message' => 'Message body or attachment required'], 422);
        }

        $replyToId = $validated['reply_to_id'] ?? null;
        if ($replyToId) {
            $replyTarget = $conversation->messages()->whereKey($replyToId)->first();
            if (! $replyTarget) {
                return response()->json(['success' => false, 'message' => 'Message to reply to was not found'], 422);
            }
        }

        $mentionIds = $this->resolveMentionIds(
            $conversation,
            $user,
            $validated['body'] ?? null,
            $validated['mentioned_user_ids'] ?? []
        );

        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'reply_to_id' => $replyToId,
            'body' => $validated['body'] ?? null,
            'mentioned_user_ids' => $mentionIds ?: null,
            'attachment_path' => $validated['attachment_path'] ?? null,
            'attachment_name' => $validated['attachment_name'] ?? null,
            'attachment_type' => $validated['attachment_type'] ?? null,
        ]);

        $this->notifyMentions($conversation, $message, $user, $mentionIds);

        return response()->json([
            'success' => true,
            'data' => $this->formatMessage(
                $message->load(['user', 'replyTo.user', 'reactions.user']),
                $user,
                [],
                $this->mentionUsersById(collect([$message]))
            ),
        ]);
    }

    /**
     * Edit a previously sent message (author only). Attachments stay as-is.
     */
    public function updateMessage(Request $request, Conversation $conversation, Message $message)
    {
        if ($reject = $this->rejectIfDiscussion($conversation)) {
            return $reject;
        }

        $companyId = $this->requireCompany();
        if (! $companyId || $conversation->company_id !== $companyId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $user = Auth::user();
        if (! $conversation->participants()->where('users.id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Not a participant'], 403);
        }

        if ($message->conversation_id !== $conversation->id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        if ($message->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'You can only edit your own messages'], 403);
        }

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:10000'],
            'mentioned_user_ids' => ['nullable', 'array', 'max:20'],
            'mentioned_user_ids.*' => ['integer'],
        ]);

        $body = isset($validated['body']) ? trim($validated['body']) : '';
        $body = $body === '' ? null : $body;

        if ($body === null && empty($message->attachment_path)) {
            return response()->json(['success' => false, 'message' => 'Message body or attachment required'], 422);
        }

        $previousMentionIds = collect($message->mentioned_user_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();
        $mentionIds = $this->resolveMentionIds(
            $conversation,
            $user,
            $body,
            $validated['mentioned_user_ids'] ?? []
        );
        $sortedMentionIds = collect($mentionIds)->sort()->values()->all();

        if (($message->body ?? null) !== $body || $previousMentionIds !== $sortedMentionIds) {
            $message->body = $body;
            $message->mentioned_user_ids = $mentionIds ?: null;
            $message->edited_at = now();
            $message->save();
            $this->notifyMentions($conversation, $message, $user, $mentionIds, $previousMentionIds);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatMessage(
                $message->load(['user', 'replyTo.user', 'reactions.user']),
                $user,
                $this->formatReceipts($conversation, $user),
                $this->mentionUsersById(collect([$message]))
            ),
        ]);
    }

    /**
     * Add, change, or remove a Facebook-style reaction (one per user per message).
     */
    public function reactToMessage(Request $request, Conversation $conversation, Message $message)
    {
        if ($reject = $this->rejectIfDiscussion($conversation)) {
            return $reject;
        }

        $companyId = $this->requireCompany();
        if (! $companyId || $conversation->company_id !== $companyId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $user = Auth::user();
        if (! $conversation->participants()->where('users.id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Not a participant'], 403);
        }

        if ($message->conversation_id !== $conversation->id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(MessageReaction::TYPES))],
        ]);

        $existing = $message->reactions()->where('user_id', $user->id)->first();

        if ($existing && $existing->type === $validated['type']) {
            $existing->delete();
        } elseif ($existing) {
            $existing->update(['type' => $validated['type']]);
        } else {
            $message->reactions()->create([
                'user_id' => $user->id,
                'type' => $validated['type'],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatMessage(
                $message->fresh()->load(['user', 'replyTo.user', 'reactions.user']),
                $user,
                $this->formatReceipts($conversation, $user)
            ),
        ]);
    }

    /**
     * Delete a conversation and its messages. Removes attachment files from storage.
     */
    public function destroyConversation(Conversation $conversation)
    {
        if ($reject = $this->rejectIfDiscussion($conversation)) {
            return $reject;
        }

        $companyId = $this->requireCompany();
        if (! $companyId || $conversation->company_id !== $companyId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $user = Auth::user();
        if (! $conversation->participants()->where('users.id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Not a participant'], 403);
        }

        // Only the creator can delete a group chat
        if ($conversation->type === 'group' && $conversation->created_by !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Only the group creator can delete this chat'], 403);
        }

        // Message model's deleting event removes attachment files from storage
        $conversation->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Get conversation details (for chat info).
     */
    public function getConversation(Conversation $conversation)
    {
        $companyId = $this->requireCompany();
        if (! $companyId || $conversation->company_id !== $companyId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $user = Auth::user();
        if (! $conversation->participants()->where('users.id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Not a participant'], 403);
        }

        $conversation->load('participants');

        if ($conversation->type === 'direct') {
            $other = $conversation->participants()->where('users.id', '!=', $user->id)->first();
            $data = [
                'type' => 'direct',
                'user' => $other ? [
                    'id' => $other->id,
                    'name' => $other->name,
                    'email' => $other->email,
                    'phone' => $other->phone ?? null,
                    'photo' => $other->photo ? public_media_url($other->photo) : null,
                    'initials' => $this->getInitials($other->name),
                ] : null,
            ];
        } else {
            $creatorId = $conversation->created_by;
            $members = $conversation->participants->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'email' => $p->email,
                'photo' => $p->photo ? public_media_url($p->photo) : null,
                'initials' => $this->getInitials($p->name),
                'is_me' => $p->id === $user->id,
                'is_creator' => $p->id === $creatorId,
            ]);
            $data = [
                'type' => 'group',
                'name' => $conversation->name,
                'photo' => $conversation->photo ? public_media_url($conversation->photo) : null,
                'is_creator' => $creatorId === $user->id,
                'creator_id' => $creatorId,
                'members' => $members,
            ];
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Update conversation (group name, photo).
     */
    public function updateConversation(Request $request, Conversation $conversation)
    {
        if ($reject = $this->rejectIfDiscussion($conversation)) {
            return $reject;
        }

        $companyId = $this->requireCompany();
        if (! $companyId || $conversation->company_id !== $companyId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $user = Auth::user();
        if (! $conversation->participants()->where('users.id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Not a participant'], 403);
        }

        if ($conversation->type !== 'group') {
            return response()->json(['success' => false, 'message' => 'Only group chats can be updated'], 422);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'photo_path' => [
                'nullable',
                'string',
                'max:500',
                Rule::when(filled($request->photo_path), ['regex:/^messaging\/.+/']),
            ],
        ]);

        if (isset($validated['name'])) {
            $conversation->name = $validated['name'];
        }
        if (array_key_exists('photo_path', $validated)) {
            $conversation->photo = $validated['photo_path'] ?: null;
        }
        $conversation->save();

        return response()->json(['success' => true, 'data' => $this->formatConversation($conversation->fresh())]);
    }

    /**
     * Add member to group conversation.
     */
    public function addMember(Request $request, Conversation $conversation)
    {
        if ($reject = $this->rejectIfDiscussion($conversation)) {
            return $reject;
        }

        $companyId = $this->requireCompany();
        if (! $companyId || $conversation->company_id !== $companyId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $user = Auth::user();
        if (! $conversation->participants()->where('users.id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Not a participant'], 403);
        }

        if ($conversation->type !== 'group') {
            return response()->json(['success' => false, 'message' => 'Only group chats support adding members'], 422);
        }

        if ($conversation->created_by !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Only the group creator can add members'], 403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $newUserId = $validated['user_id'];
        $companyUsers = User::where('company_id', $companyId)->where('id', $newUserId)->exists();
        if (! $companyUsers) {
            return response()->json(['success' => false, 'message' => 'User not in company'], 422);
        }

        if ($conversation->participants()->where('users.id', $newUserId)->exists()) {
            return response()->json(['success' => false, 'message' => 'User is already a member'], 422);
        }

        $conversation->participants()->attach($newUserId);

        return response()->json(['success' => true]);
    }

    /**
     * Remove member from group conversation.
     */
    public function removeMember(Conversation $conversation, User $userToRemove)
    {
        if ($reject = $this->rejectIfDiscussion($conversation)) {
            return $reject;
        }

        $companyId = $this->requireCompany();
        if (! $companyId || $conversation->company_id !== $companyId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $user = Auth::user();
        if (! $conversation->participants()->where('users.id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Not a participant'], 403);
        }

        if ($conversation->type !== 'group') {
            return response()->json(['success' => false, 'message' => 'Only group chats support removing members'], 422);
        }

        if ($conversation->created_by !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Only the group creator can remove members'], 403);
        }

        if (! $conversation->participants()->where('users.id', $userToRemove->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'User is not a member'], 422);
        }

        $conversation->participants()->detach($userToRemove->id);

        return response()->json(['success' => true]);
    }

    /**
     * Transfer group ownership to another member.
     */
    public function transferOwnership(Request $request, Conversation $conversation)
    {
        if ($reject = $this->rejectIfDiscussion($conversation)) {
            return $reject;
        }

        $companyId = $this->requireCompany();
        if (! $companyId || $conversation->company_id !== $companyId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $user = Auth::user();
        if (! $conversation->participants()->where('users.id', $user->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Not a participant'], 403);
        }

        if ($conversation->type !== 'group') {
            return response()->json(['success' => false, 'message' => 'Only group chats support ownership transfer'], 422);
        }

        if ($conversation->created_by !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Only the group creator can transfer ownership'], 403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $newOwnerId = $validated['user_id'];
        if (! $conversation->participants()->where('users.id', $newOwnerId)->exists()) {
            return response()->json(['success' => false, 'message' => 'New owner must be a group member'], 422);
        }

        if ($newOwnerId === $user->id) {
            return response()->json(['success' => false, 'message' => 'You are already the owner'], 422);
        }

        $conversation->created_by = $newOwnerId;
        $conversation->save();

        return response()->json(['success' => true]);
    }

    /**
     * Upload an attachment for a message.
     */
    public function uploadAttachment(Request $request)
    {
        $companyId = $this->requireCompany();
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB
        ]);

        $file = $request->file('file');
        $path = $file->store('messaging/'.$companyId, 'public');

        return response()->json([
            'success' => true,
            'data' => [
                'path' => $path,
                'url' => public_media_url($path),
                'name' => $file->getClientOriginalName(),
                'type' => str_starts_with($file->getMimeType(), 'image/') ? 'image' : 'file',
            ],
        ]);
    }

    /**
     * Discard an uploaded attachment that was never sent (e.g. user cancelled).
     */
    public function discardAttachment(Request $request)
    {
        $companyId = $this->requireCompany();
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'path' => ['required', 'string', 'max:500'],
        ]);

        $path = $validated['path'];

        if (str_contains($path, '..')) {
            return response()->json(['success' => false, 'message' => 'Invalid path'], 422);
        }

        $prefix = 'messaging/'.$companyId.'/';
        if (! str_starts_with($path, $prefix)) {
            return response()->json(['success' => false, 'message' => 'Invalid path'], 422);
        }

        $fullPath = storage_path('app/public/'.$path);
        if (file_exists($fullPath) && is_file($fullPath)) {
            unlink($fullPath);
        }

        return response()->json(['success' => true]);
    }

    /**
     * @param  list<int>  $requestedIds
     * @return list<int>
     */
    private function resolveMentionIds(Conversation $conversation, User $sender, ?string $body, array $requestedIds): array
    {
        if ($conversation->type !== 'group') {
            return [];
        }

        if (! $conversation->relationLoaded('participants')) {
            $conversation->load('participants');
        }

        $others = $conversation->participants->where('id', '!=', $sender->id)->values();
        $validIds = $others->pluck('id')->map(fn ($id) => (int) $id)->all();

        $ids = collect($requestedIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => in_array($id, $validIds, true));

        if (is_string($body) && $body !== '') {
            foreach ($others->sortByDesc(fn ($participant) => mb_strlen((string) $participant->name)) as $participant) {
                if ($this->bodyMentionsName($body, (string) $participant->name)) {
                    $ids->push((int) $participant->id);
                }
            }
        }

        return $ids->unique()->values()->take(20)->all();
    }

    private function bodyMentionsName(string $body, string $name): bool
    {
        $name = trim($name);
        if ($name === '') {
            return false;
        }

        $needle = '@'.$name;
        $offset = 0;
        $length = mb_strlen($needle);

        while (($pos = mb_stripos($body, $needle, $offset)) !== false) {
            $after = mb_substr($body, $pos + $length, 1);
            if ($after === '' || ! preg_match('/[\p{L}\p{N}_]/u', $after)) {
                return true;
            }
            $offset = $pos + 1;
        }

        return false;
    }

    /**
     * @param  list<int>  $mentionIds
     * @param  list<int>  $previousIds
     */
    private function notifyMentions(
        Conversation $conversation,
        Message $message,
        User $actor,
        array $mentionIds,
        array $previousIds = []
    ): void {
        $newIds = array_values(array_diff($mentionIds, $previousIds));
        if ($newIds === []) {
            return;
        }

        User::query()
            ->whereIn('id', $newIds)
            ->get()
            ->each(fn (User $recipient) => $recipient->notify(
                new MessagingMentionNotification($conversation, $message, $actor)
            ));
    }

    /**
     * @param  Collection<int, Message>|iterable<Message>  $messages
     * @return array<int, User>
     */
    private function mentionUsersById(iterable $messages): array
    {
        $ids = collect($messages)
            ->flatMap(fn (Message $message) => $message->mentioned_user_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name'])
            ->keyBy('id')
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formatConversationMembers(Conversation $conversation, User $currentUser): array
    {
        $creatorId = $conversation->created_by;

        return $conversation->participants
            ->map(fn ($participant) => [
                'id' => $participant->id,
                'name' => $participant->name,
                'email' => $participant->email,
                'photo' => $participant->photo ? public_media_url($participant->photo) : null,
                'initials' => $this->getInitials($participant->name),
                'is_me' => $participant->id === $currentUser->id,
                'is_creator' => $participant->id === $creatorId,
            ])
            ->values()
            ->all();
    }

    private function formatConversation(Conversation $conv): array
    {
        $user = Auth::user();
        $other = $conv->participants()->where('users.id', '!=', $user->id)->first();
        $name = $conv->type === 'group' ? $conv->name : ($other?->name ?? 'Unknown');

        return [
            'id' => $conv->id,
            'type' => $conv->type,
            'name' => $name,
        ];
    }

    private function formatReceipts(Conversation $conversation, $currentUser): array
    {
        return $conversation->participants()
            ->where('users.id', '!=', $currentUser->id)
            ->get()
            ->map(function ($p) {
                $readAt = $p->pivot->getRawOriginal('last_read_at')
                    ?? $p->pivot->getAttributes()['last_read_at']
                    ?? $p->pivot->last_read_at;

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'initials' => $this->getInitials($p->name),
                    'photo' => $p->photo ? public_media_url($p->photo) : null,
                    'last_read_at' => $this->isoTimestamp($readAt),
                    'last_read_raw' => $this->wallClock($readAt),
                ];
            })
            ->values()
            ->all();
    }

    private function formatMessage(Message $m, $currentUser, array $receipts = [], array $mentionUsersById = []): array
    {
        $isMe = $m->user_id === $currentUser->id;
        $mentionIds = collect($m->mentioned_user_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $mentions = $mentionIds
            ->map(function ($id) use ($mentionUsersById) {
                $user = $mentionUsersById[$id] ?? null;

                return $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                ] : null;
            })
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $m->id,
            'user_id' => $m->user_id,
            'is_mine' => $isMe,
            'author' => $isMe ? 'You' : $m->user->name,
            'author_initials' => $this->getInitials($m->user->name),
            'author_photo' => $m->user->photo ? public_media_url($m->user->photo) : null,
            'body' => $m->body,
            'mentioned_user_ids' => $mentionIds->all(),
            'mentions' => $mentions,
            'mentions_me' => $mentionIds->contains((int) $currentUser->id),
            'attachment_path' => $m->attachment_path ? public_media_url($m->attachment_path) : null,
            'attachment_name' => $m->attachment_name,
            'attachment_type' => $m->attachment_type,
            'created_at' => $m->created_at->toIso8601String(),
            'edited_at' => $m->edited_at?->toIso8601String(),
            'reply_to' => $this->formatReplyTo($m, $currentUser),
            'seen_by' => $isMe ? $this->whoSaw($m, $receipts) : [],
            'reactions' => $this->formatReactions($m, $currentUser),
            'my_reaction' => $this->myReactionType($m, $currentUser),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formatReactions(Message $m, $currentUser): array
    {
        $reactions = $m->relationLoaded('reactions')
            ? $m->reactions
            : $m->reactions()->with('user')->get();

        $grouped = [];
        foreach (MessageReaction::TYPES as $type => $emoji) {
            $items = $reactions->where('type', $type)->values();
            if ($items->isEmpty()) {
                continue;
            }

            $grouped[] = [
                'type' => $type,
                'emoji' => $emoji,
                'count' => $items->count(),
                'reacted' => $items->contains(fn ($r) => (int) $r->user_id === (int) $currentUser->id),
                'users' => $items->map(function ($r) {
                    return [
                        'id' => $r->user_id,
                        'name' => $r->user->name ?? 'Unknown',
                        'initials' => $this->getInitials($r->user->name ?? ''),
                        'photo' => $r->user?->photo ? public_media_url($r->user->photo) : null,
                    ];
                })->values()->all(),
            ];
        }

        return $grouped;
    }

    private function myReactionType(Message $m, $currentUser): ?string
    {
        $reactions = $m->relationLoaded('reactions')
            ? $m->reactions
            : $m->reactions()->get();

        return $reactions->firstWhere('user_id', $currentUser->id)?->type;
    }

    /**
     * @param  list<array<string, mixed>>  $receipts
     * @return list<array<string, mixed>>
     */
    private function whoSaw(Message $message, array $receipts): array
    {
        $createdRaw = $this->wallClock($message->getRawOriginal('created_at') ?? $message->created_at);

        return collect($receipts)
            ->filter(function (array $receipt) use ($createdRaw) {
                $readRaw = $receipt['last_read_raw'] ?? null;

                return $createdRaw && $readRaw && $readRaw >= $createdRaw;
            })
            ->map(function (array $receipt) {
                return Arr::except($receipt, ['last_read_raw']);
            })
            ->values()
            ->all();
    }

    private function wallClock(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            // Keep the stored wall clock. Shifting UTC↔app TZ made last_read look
            // hours later than created_at and tagged messages as seen.
            return Carbon::instance($value)->format('Y-m-d H:i:s');
        }

        $normalized = substr(str_replace('T', ' ', (string) $value), 0, 19);

        return $normalized !== '' ? $normalized : null;
    }

    private function formatReplyTo(Message $m, $currentUser): ?array
    {
        $target = $m->relationLoaded('replyTo') ? $m->replyTo : $m->replyTo()->with('user')->first();
        if (! $target) {
            return null;
        }

        $isMe = $target->user_id === $currentUser->id;
        $preview = $this->messagePreview($target);

        return [
            'id' => $target->id,
            'author' => $isMe ? 'You' : ($target->user->name ?? 'Unknown'),
            'body' => $preview,
            'attachment_type' => $target->attachment_type,
        ];
    }

    private function isoTimestamp(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toIso8601String();
        }

        return Carbon::parse((string) $value, config('app.timezone'))->toIso8601String();
    }

    private function messagePreview(Message $m): string
    {
        if ($m->attachment_path) {
            return $m->attachment_name ?: '[Attachment]';
        }

        return strlen($m->body ?? '') > 80 ? substr($m->body, 0, 80).'...' : ($m->body ?? '');
    }

    private function getInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return strtoupper(
            substr($parts[0] ?? '', 0, 1).
            substr($parts[1] ?? $parts[0] ?? '', 0, 1)
        ) ?: '?';
    }
}
