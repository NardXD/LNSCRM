<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\DiscussionRule;
use App\Models\InboxTag;
use App\Models\Message;
use App\Models\SharedInbox;
use App\Models\User;
use App\Notifications\DiscussionUpdateNotification;
use App\Notifications\MessagingMentionNotification;
use App\Services\DiscussionRuleEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DiscussionsController extends Controller
{
    public function index()
    {
        return view('dashboard.discussions');
    }

    public function bootstrap(Request $request)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $this->reopenDueSnoozes($companyId);

        $teammates = User::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'photo'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'photo' => $u->photo ? public_media_url($u->photo) : null,
                'initials' => $this->initials($u->name),
            ]);

        $sharedInboxes = SharedInbox::query()
            ->where('company_id', $companyId)
            ->where('type', SharedInbox::TYPE_SHARED)
            ->where('is_active', true)
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'email'])
            ->map(fn (SharedInbox $inbox) => [
                'id' => $inbox->id,
                'name' => $inbox->name,
                'color' => $inbox->color,
                'email' => $inbox->email,
            ]);

        $tags = InboxTag::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $canManageTags = $user->hasPermission('create_discussion_tags')
            || $user->hasPermission('create_inbox_tags')
            || $user->isAdmin();
        $canManageRules = $user->hasPermission('create_discussion_rules') || $user->isAdmin();

        $rules = DiscussionRule::query()
            ->where('company_id', $companyId)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->map(fn (DiscussionRule $rule) => $this->formatRule($rule));

        return response()->json([
            'success' => true,
            'data' => [
                'teammates' => $teammates,
                'shared_inboxes' => $sharedInboxes,
                'tags' => $tags,
                'rules' => $rules,
                'counts' => $this->viewCounts($companyId, $user),
                'permissions' => [
                    'create_tags' => $canManageTags,
                    'create_rules' => $canManageRules,
                ],
                'rule_meta' => [
                    'triggers' => DiscussionRuleEngine::triggerLabels(),
                    'actions' => DiscussionRuleEngine::actionLabels(),
                ],
                'current_user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'photo' => $user->photo ? public_media_url($user->photo) : null,
                    'initials' => $this->initials($user->name),
                ],
            ],
        ]);
    }

    public function listConversations(Request $request)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $this->reopenDueSnoozes($companyId);

        $view = (string) $request->get('view', 'discussions');
        $search = trim((string) $request->get('search', ''));
        $inboxId = $request->filled('shared_inbox_id') ? (int) $request->get('shared_inbox_id') : null;
        $tagId = $request->filled('tag_id') ? (int) $request->get('tag_id') : null;
        $limit = min(max((int) $request->get('limit', 40), 5), 100);
        $offset = max((int) $request->get('offset', 0), 0);

        $query = $this->accessibleQuery($companyId, $user);

        match ($view) {
            'subscribed' => $query->whereHas('participants', fn ($q) => $q->where('users.id', $user->id)),
            'open' => $query->where('status', Conversation::STATUS_OPEN)->whereNull('reopen_at'),
            'assigned' => $query->where('assigned_to', $user->id)->where('status', Conversation::STATUS_OPEN),
            'snoozed' => $query->whereNotNull('reopen_at')->where('reopen_at', '>', now()),
            'archived' => $query->where('status', Conversation::STATUS_ARCHIVED)->whereNull('reopen_at'),
            default => null, // discussions = all accessible
        };

        if ($inboxId) {
            $query->where('shared_inbox_id', $inboxId);
        }

        if ($tagId) {
            $query->whereHas('tags', fn ($q) => $q->where('inbox_tags.id', $tagId));
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('messages', fn ($m) => $m->where('body', 'like', "%{$search}%"));
            });
        }

        $rows = $query
            ->with([
                'assignee:id,name,photo',
                'sharedInbox:id,name,color',
                'tags:id,name,color',
                'latestMessage.user:id,name',
                'participants:id,name,photo',
            ])
            ->orderByRaw('(SELECT MAX(created_at) FROM messages WHERE messages.conversation_id = conversations.id) DESC')
            ->orderByDesc('conversations.id')
            ->offset($offset)
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        return response()->json([
            'success' => true,
            'data' => $rows->map(fn (Conversation $c) => $this->formatListItem($c, $user))->values(),
            'has_more' => $hasMore,
            'counts' => $this->viewCounts($companyId, $user),
        ]);
    }

    public function show(Conversation $conversation)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId || ! $this->authorizeDiscussion($conversation, $companyId, $user)) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $conversation->applySnoozeReopenIfDue();
        $conversation->load([
            'assignee:id,name,email,photo',
            'sharedInbox:id,name,color,type',
            'tags:id,name,color',
            'participants:id,name,email,photo',
            'creator:id,name',
        ]);

        if ($conversation->participants()->where('users.id', $user->id)->exists()) {
            $conversation->participants()->updateExistingPivot($user->id, [
                'last_read_at' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            ]);
        }

        $messages = $conversation->messages()
            ->with('user:id,name,email,photo')
            ->orderBy('created_at')
            ->limit(500)
            ->get()
            ->map(fn (Message $m) => $this->formatMessage($m));

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => $this->formatDetail($conversation, $user),
                'messages' => $messages,
            ],
        ]);
    }

    public function store(Request $request, DiscussionRuleEngine $rules)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'comment' => ['required', 'string', 'max:20000'],
            'teammate_ids' => ['nullable', 'array'],
            'teammate_ids.*' => ['integer', 'exists:users,id'],
            'shared_inbox_id' => ['nullable', 'integer', 'exists:shared_inboxes,id'],
            'attachment_path' => ['nullable', 'string', 'max:500'],
            'attachment_name' => ['nullable', 'string', 'max:255'],
            'attachment_type' => ['nullable', 'string', 'max:100'],
        ]);

        $teammateIds = array_values(array_unique(array_map('intval', $validated['teammate_ids'] ?? [])));
        $sharedInboxId = isset($validated['shared_inbox_id']) ? (int) $validated['shared_inbox_id'] : null;

        if (($teammateIds !== [] && $sharedInboxId) || ($teammateIds === [] && ! $sharedInboxId)) {
            throw ValidationException::withMessages([
                'teammate_ids' => 'Provide teammates or a shared inbox, not both.',
            ]);
        }

        $participantIds = [$user->id];
        $inbox = null;

        if ($sharedInboxId) {
            $inbox = SharedInbox::query()
                ->where('company_id', $companyId)
                ->where('id', $sharedInboxId)
                ->where('type', SharedInbox::TYPE_SHARED)
                ->where('is_active', true)
                ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
                ->first();
            if (! $inbox) {
                return response()->json(['success' => false, 'message' => 'Shared inbox not found'], 422);
            }
            $memberIds = $inbox->members()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
            $participantIds = array_values(array_unique(array_merge($participantIds, $memberIds)));
        } else {
            $validTeammates = User::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $teammateIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            if (count($validTeammates) !== count($teammateIds)) {
                return response()->json(['success' => false, 'message' => 'Invalid teammates'], 422);
            }
            $participantIds = array_values(array_unique(array_merge($participantIds, $validTeammates)));
        }

        $conversation = DB::transaction(function () use ($companyId, $user, $validated, $participantIds, $inbox) {
            $conversation = Conversation::create([
                'company_id' => $companyId,
                'type' => 'group',
                'kind' => Conversation::KIND_DISCUSSION,
                'status' => Conversation::STATUS_OPEN,
                'name' => trim($validated['subject']),
                'created_by' => $user->id,
                'shared_inbox_id' => $inbox?->id,
                'moved_to_shared_at' => $inbox ? now() : null,
            ]);

            foreach ($participantIds as $pid) {
                $conversation->participants()->attach($pid, ['last_read_at' => now()]);
            }

            Message::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'body' => trim($validated['comment']),
                'attachment_path' => $validated['attachment_path'] ?? null,
                'attachment_name' => $validated['attachment_name'] ?? null,
                'attachment_type' => $validated['attachment_type'] ?? null,
            ]);

            return $conversation;
        });

        $rules->apply($conversation, DiscussionRuleEngine::TRIGGER_DISCUSSION_CREATED);

        $conversation = $conversation->fresh([
            'assignee',
            'sharedInbox',
            'tags',
            'participants',
            'latestMessage.user',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatDetail($conversation, $user),
        ], 201);
    }

    public function update(Request $request, Conversation $conversation)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId || ! $this->authorizeDiscussion($conversation, $companyId, $user)) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
        ]);

        $conversation->name = trim($validated['subject']);
        $conversation->save();

        return response()->json([
            'success' => true,
            'data' => $this->formatDetail($conversation->fresh(['assignee', 'sharedInbox', 'tags', 'participants']), $user),
        ]);
    }

    public function storeMessage(Request $request, Conversation $conversation, DiscussionRuleEngine $rules)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId || ! $this->authorizeDiscussion($conversation, $companyId, $user)) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:20000'],
            'attachment_path' => ['nullable', 'string', 'max:500'],
            'attachment_name' => ['nullable', 'string', 'max:255'],
            'attachment_type' => ['nullable', 'string', 'max:100'],
            'mentioned_user_ids' => ['nullable', 'array', 'max:20'],
            'mentioned_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        if ($body === '' && empty($validated['attachment_path'])) {
            throw ValidationException::withMessages(['body' => 'Comment body or attachment is required.']);
        }

        if (! $conversation->participants()->where('users.id', $user->id)->exists()) {
            $conversation->participants()->attach($user->id, ['last_read_at' => now()]);
        }

        $mentionedIds = array_values(array_unique(array_map('intval', $validated['mentioned_user_ids'] ?? [])));
        $mentionedIds = User::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $mentionedIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($mentionedIds as $mid) {
            if (! $conversation->participants()->where('users.id', $mid)->exists()) {
                $conversation->participants()->attach($mid, ['last_read_at' => null]);
            }
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'body' => $body !== '' ? $body : null,
            'mentioned_user_ids' => $mentionedIds ?: null,
            'attachment_path' => $validated['attachment_path'] ?? null,
            'attachment_name' => $validated['attachment_name'] ?? null,
            'attachment_type' => $validated['attachment_type'] ?? null,
        ]);

        $conversation->participants()->updateExistingPivot($user->id, [
            'last_read_at' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
        ]);

        if ($conversation->status === Conversation::STATUS_ARCHIVED) {
            $conversation->status = Conversation::STATUS_OPEN;
            $conversation->reopen_at = null;
            $conversation->save();
        }

        foreach ($mentionedIds as $mid) {
            if ($mid === $user->id) {
                continue;
            }
            $mentionUser = User::find($mid);
            if ($mentionUser) {
                $mentionUser->notify(new MessagingMentionNotification($conversation, $message, $user));
            }
        }

        $rules->apply($conversation->fresh(), DiscussionRuleEngine::TRIGGER_COMMENT_ADDED);

        return response()->json([
            'success' => true,
            'data' => $this->formatMessage($message->load('user:id,name,email,photo')),
        ], 201);
    }

    public function assign(Request $request, Conversation $conversation, DiscussionRuleEngine $rules)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId || ! $this->authorizeDiscussion($conversation, $companyId, $user)) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $assigneeId = $validated['assigned_to'] ?? null;
        if ($assigneeId) {
            $assignee = User::query()->where('company_id', $companyId)->where('id', $assigneeId)->first();
            if (! $assignee) {
                return response()->json(['success' => false, 'message' => 'Invalid assignee'], 422);
            }
            $conversation->assigned_to = $assignee->id;
            if (! $conversation->participants()->where('users.id', $assignee->id)->exists()) {
                $conversation->participants()->attach($assignee->id, ['last_read_at' => null]);
            }
            $assignee->notify(new DiscussionUpdateNotification(
                $conversation,
                $user->name.' assigned you to "'.($conversation->name ?: 'Discussion').'"',
            ));
        } else {
            $conversation->assigned_to = null;
        }
        $conversation->save();

        $rules->apply($conversation->fresh(), DiscussionRuleEngine::TRIGGER_DISCUSSION_ASSIGNED);

        return response()->json([
            'success' => true,
            'data' => $this->formatDetail($conversation->fresh(['assignee', 'sharedInbox', 'tags', 'participants']), $user),
        ]);
    }

    public function snooze(Request $request, Conversation $conversation)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId || ! $this->authorizeDiscussion($conversation, $companyId, $user)) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'reopen_at' => ['required', 'date', 'after:now'],
        ]);

        $conversation->reopen_at = $validated['reopen_at'];
        $conversation->status = Conversation::STATUS_OPEN;
        $conversation->save();

        return response()->json([
            'success' => true,
            'data' => $this->formatDetail($conversation->fresh(['assignee', 'sharedInbox', 'tags', 'participants']), $user),
        ]);
    }

    public function archive(Request $request, Conversation $conversation, DiscussionRuleEngine $rules)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId || ! $this->authorizeDiscussion($conversation, $companyId, $user)) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'archived' => ['required', 'boolean'],
        ]);

        if ($validated['archived']) {
            $conversation->status = Conversation::STATUS_ARCHIVED;
            $conversation->reopen_at = null;
            $conversation->save();
            $rules->apply($conversation->fresh(), DiscussionRuleEngine::TRIGGER_DISCUSSION_ARCHIVED);
        } else {
            $conversation->status = Conversation::STATUS_OPEN;
            $conversation->reopen_at = null;
            $conversation->save();
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatDetail($conversation->fresh(['assignee', 'sharedInbox', 'tags', 'participants']), $user),
        ]);
    }

    public function unsubscribe(Conversation $conversation)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId || ! $this->authorizeDiscussion($conversation, $companyId, $user)) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $conversation->participants()->detach($user->id);

        return response()->json(['success' => true]);
    }

    public function addParticipants(Request $request, Conversation $conversation)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId || ! $this->authorizeDiscussion($conversation, $companyId, $user)) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $ids = User::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $validated['user_ids'])
            ->pluck('id')
            ->all();

        foreach ($ids as $id) {
            if (! $conversation->participants()->where('users.id', $id)->exists()) {
                $conversation->participants()->attach($id, ['last_read_at' => null]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatDetail($conversation->fresh(['assignee', 'sharedInbox', 'tags', 'participants']), $user),
        ]);
    }

    public function syncTags(Request $request, Conversation $conversation, DiscussionRuleEngine $rules)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId || ! $this->authorizeDiscussion($conversation, $companyId, $user)) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'tag_ids' => ['present', 'array'],
            'tag_ids.*' => ['integer', 'exists:inbox_tags,id'],
        ]);

        $tagIds = InboxTag::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $validated['tag_ids'])
            ->pluck('id')
            ->all();

        $conversation->tags()->sync($tagIds);
        $rules->apply($conversation->fresh(), DiscussionRuleEngine::TRIGGER_DISCUSSION_TAGGED);

        return response()->json([
            'success' => true,
            'data' => $this->formatDetail($conversation->fresh(['assignee', 'sharedInbox', 'tags', 'participants']), $user),
        ]);
    }

    public function move(Request $request, Conversation $conversation, DiscussionRuleEngine $rules)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId || ! $this->authorizeDiscussion($conversation, $companyId, $user)) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'shared_inbox_id' => ['nullable', 'integer', 'exists:shared_inboxes,id'],
        ]);

        $targetId = $validated['shared_inbox_id'] ?? null;

        if ($conversation->moved_to_shared_at && $conversation->shared_inbox_id && ! $targetId) {
            return response()->json([
                'success' => false,
                'message' => 'Discussions moved to a shared inbox can only be moved to another shared inbox.',
            ], 422);
        }

        if ($targetId) {
            $inbox = SharedInbox::query()
                ->where('company_id', $companyId)
                ->where('id', $targetId)
                ->where('type', SharedInbox::TYPE_SHARED)
                ->where('is_active', true)
                ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
                ->first();
            if (! $inbox) {
                return response()->json(['success' => false, 'message' => 'Shared inbox not found'], 422);
            }

            $conversation->shared_inbox_id = $inbox->id;
            $conversation->moved_to_shared_at = $conversation->moved_to_shared_at ?? now();

            foreach ($inbox->members()->pluck('users.id') as $memberId) {
                if (! $conversation->participants()->where('users.id', $memberId)->exists()) {
                    $conversation->participants()->attach($memberId, ['last_read_at' => null]);
                }
            }
        } else {
            $conversation->shared_inbox_id = null;
        }

        $conversation->save();
        $rules->apply($conversation->fresh(), DiscussionRuleEngine::TRIGGER_DISCUSSION_MOVED);

        return response()->json([
            'success' => true,
            'data' => $this->formatDetail($conversation->fresh(['assignee', 'sharedInbox', 'tags', 'participants']), $user),
        ]);
    }

    public function storeTag(Request $request)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (! $user->hasPermission('create_discussion_tags') && ! $user->hasPermission('create_inbox_tags') && ! $user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $tag = InboxTag::firstOrCreate(
            [
                'company_id' => $companyId,
                'name' => trim($validated['name']),
            ],
            ['color' => $validated['color'] ?? '#64748b']
        );

        return response()->json(['success' => true, 'data' => $tag], 201);
    }

    public function destroyTag(InboxTag $tag)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId || $tag->company_id !== $companyId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        if (! $user->hasPermission('create_discussion_tags') && ! $user->hasPermission('create_inbox_tags') && ! $user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $tag->discussionConversations()->detach();
        // Keep inbox tag itself if still used by mail; only detach from discussions.
        // If unused by inbox too, leave deletion to inbox UI. Soft approach: do not delete tag row.

        return response()->json(['success' => true]);
    }

    public function listRules()
    {
        $companyId = $this->requireCompany();
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $rules = DiscussionRule::query()
            ->where('company_id', $companyId)
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->map(fn (DiscussionRule $rule) => $this->formatRule($rule));

        return response()->json(['success' => true, 'data' => $rules]);
    }

    public function storeRule(Request $request)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if (! $user->hasPermission('create_discussion_rules') && ! $user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validated = $this->validateRule($request, $companyId);
        $rule = DiscussionRule::create(array_merge($validated, [
            'company_id' => $companyId,
            'created_by' => $user->id,
        ]));

        return response()->json(['success' => true, 'data' => $this->formatRule($rule)], 201);
    }

    public function updateRule(Request $request, DiscussionRule $discussionRule)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId || $discussionRule->company_id !== $companyId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        if (! $user->hasPermission('create_discussion_rules') && ! $user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validated = $this->validateRule($request, $companyId);
        $discussionRule->fill($validated)->save();

        return response()->json(['success' => true, 'data' => $this->formatRule($discussionRule)]);
    }

    public function destroyRule(DiscussionRule $discussionRule)
    {
        $user = Auth::user();
        $companyId = $this->requireCompany();
        if (! $companyId || $discussionRule->company_id !== $companyId) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }
        if (! $user->hasPermission('create_discussion_rules') && ! $user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $discussionRule->delete();

        return response()->json(['success' => true]);
    }

    public function uploadAttachment(Request $request)
    {
        $companyId = $this->requireCompany();
        if (! $companyId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:25600'],
        ]);

        $file = $request->file('file');
        $path = $file->store('discussions/'.$companyId, 'public');

        return response()->json([
            'success' => true,
            'data' => [
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'type' => $file->getMimeType(),
                'url' => Storage::disk('public')->url($path),
            ],
        ]);
    }

    private function validateRule(Request $request, int $companyId): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'stop_processing' => ['nullable', 'boolean'],
            'shared_inbox_id' => [
                'nullable',
                'integer',
                Rule::exists('shared_inboxes', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'triggers' => ['nullable', 'array'],
            'triggers.*' => ['string'],
            'conditions' => ['nullable', 'array'],
            'actions' => ['required', 'array', 'min:1'],
        ]);

        return [
            'name' => $validated['name'],
            'priority' => (int) ($validated['priority'] ?? 100),
            'is_active' => array_key_exists('is_active', $validated) ? (bool) $validated['is_active'] : true,
            'stop_processing' => array_key_exists('stop_processing', $validated) ? (bool) $validated['stop_processing'] : false,
            'shared_inbox_id' => $validated['shared_inbox_id'] ?? null,
            'triggers' => $validated['triggers'] ?? [DiscussionRuleEngine::TRIGGER_DISCUSSION_CREATED],
            'conditions' => $validated['conditions'] ?? [],
            'actions' => $validated['actions'],
        ];
    }

    private function requireCompany(): ?int
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            return null;
        }

        return (int) $user->company_id;
    }

    private function authorizeDiscussion(Conversation $conversation, int $companyId, User $user): bool
    {
        if ($conversation->company_id !== $companyId || ! $conversation->isDiscussion()) {
            return false;
        }

        $conversation->applySnoozeReopenIfDue();

        return $this->userCanAccess($conversation, $user);
    }

    private function userCanAccess(Conversation $conversation, User $user): bool
    {
        if ((int) $conversation->assigned_to === (int) $user->id) {
            return true;
        }

        if ($conversation->participants()->where('users.id', $user->id)->exists()) {
            return true;
        }

        if ($conversation->shared_inbox_id) {
            return SharedInbox::query()
                ->where('id', $conversation->shared_inbox_id)
                ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
                ->exists();
        }

        return false;
    }

    private function accessibleQuery(int $companyId, User $user)
    {
        $sharedInboxIds = SharedInbox::query()
            ->where('company_id', $companyId)
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->pluck('id');

        return Conversation::query()
            ->where('company_id', $companyId)
            ->where('kind', Conversation::KIND_DISCUSSION)
            ->where(function ($q) use ($user, $sharedInboxIds) {
                $q->where('assigned_to', $user->id)
                    ->orWhereHas('participants', fn ($p) => $p->where('users.id', $user->id));
                if ($sharedInboxIds->isNotEmpty()) {
                    $q->orWhereIn('shared_inbox_id', $sharedInboxIds);
                }
            });
    }

    private function reopenDueSnoozes(int $companyId): void
    {
        Conversation::query()
            ->where('company_id', $companyId)
            ->where('kind', Conversation::KIND_DISCUSSION)
            ->whereNotNull('reopen_at')
            ->where('reopen_at', '<=', now())
            ->update([
                'status' => Conversation::STATUS_OPEN,
                'reopen_at' => null,
            ]);
    }

    /**
     * @return array<string, int>
     */
    private function viewCounts(int $companyId, User $user): array
    {
        $base = fn () => $this->accessibleQuery($companyId, $user);

        return [
            'discussions' => (clone $base())->count(),
            'subscribed' => (clone $base())->whereHas('participants', fn ($q) => $q->where('users.id', $user->id))->count(),
            'open' => (clone $base())->where('status', Conversation::STATUS_OPEN)->whereNull('reopen_at')->count(),
            'assigned' => (clone $base())->where('assigned_to', $user->id)->where('status', Conversation::STATUS_OPEN)->count(),
            'snoozed' => (clone $base())->whereNotNull('reopen_at')->where('reopen_at', '>', now())->count(),
            'archived' => (clone $base())->where('status', Conversation::STATUS_ARCHIVED)->whereNull('reopen_at')->count(),
        ];
    }

    private function formatListItem(Conversation $c, User $user): array
    {
        $last = $c->latestMessage;
        $preview = $last
            ? (($last->user_id === $user->id ? 'You: ' : (($last->user->name ?? '').': ')).mb_substr((string) $last->body, 0, 120))
            : 'No comments yet';

        $pivot = $c->participants->firstWhere('id', $user->id)?->pivot;
        $lastRead = $pivot?->last_read_at;
        $unread = $c->messages()
            ->where('user_id', '!=', $user->id)
            ->where('created_at', '>', $lastRead ?? '1970-01-01')
            ->count();

        return [
            'id' => $c->id,
            'subject' => $c->name,
            'preview' => $preview,
            'status' => $c->status,
            'reopen_at' => $c->reopen_at?->toIso8601String(),
            'assigned_to' => $c->assigned_to,
            'assignee' => $c->assignee ? [
                'id' => $c->assignee->id,
                'name' => $c->assignee->name,
                'photo' => $c->assignee->photo ? public_media_url($c->assignee->photo) : null,
            ] : null,
            'shared_inbox' => $c->sharedInbox ? [
                'id' => $c->sharedInbox->id,
                'name' => $c->sharedInbox->name,
                'color' => $c->sharedInbox->color,
            ] : null,
            'tags' => $c->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->values(),
            'last_message_at' => $last?->created_at?->toIso8601String(),
            'unread_count' => max($unread, 0),
            'is_subscribed' => $c->participants->contains('id', $user->id),
        ];
    }

    private function formatDetail(Conversation $c, User $user): array
    {
        return array_merge($this->formatListItem($c, $user), [
            'created_by' => $c->created_by,
            'moved_to_shared_at' => $c->moved_to_shared_at?->toIso8601String(),
            'front_conversation_id' => $c->front_conversation_id,
            'participants' => $c->participants->map(fn (User $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'email' => $p->email,
                'photo' => $p->photo ? public_media_url($p->photo) : null,
                'initials' => $this->initials($p->name),
                'is_me' => $p->id === $user->id,
            ])->values(),
        ]);
    }

    private function formatMessage(Message $m): array
    {
        return [
            'id' => $m->id,
            'body' => $m->body,
            'user' => $m->user ? [
                'id' => $m->user->id,
                'name' => $m->user->name,
                'email' => $m->user->email,
                'photo' => $m->user->photo ? public_media_url($m->user->photo) : null,
                'initials' => $this->initials($m->user->name),
            ] : null,
            'attachment' => $m->attachment_path ? [
                'path' => $m->attachment_path,
                'name' => $m->attachment_name,
                'type' => $m->attachment_type,
                'url' => public_media_url($m->attachment_path),
            ] : null,
            'mentioned_user_ids' => $m->mentioned_user_ids ?? [],
            'created_at' => $m->created_at?->toIso8601String(),
            'edited_at' => $m->edited_at?->toIso8601String(),
        ];
    }

    private function formatRule(DiscussionRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'priority' => $rule->priority,
            'is_active' => $rule->is_active,
            'stop_processing' => $rule->stop_processing,
            'shared_inbox_id' => $rule->shared_inbox_id,
            'triggers' => $rule->triggers ?? [],
            'conditions' => $rule->conditions ?? [],
            'actions' => $rule->actions ?? [],
        ];
    }

    private function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : '?';
    }
}
