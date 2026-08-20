<?php

namespace App\Services;

use App\Models\InboxConversation;
use App\Models\InboxConversationActivity;
use App\Models\InboxConversationComment;
use App\Models\InboxMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InboxThreadMergeService
{
    /**
     * Merge $source into $target. Both must be visible conversations in the same
     * personal or shared mailbox (same SharedInbox id). Cross-inbox merges are blocked.
     */
    public function merge(InboxConversation $target, InboxConversation $source): InboxConversation
    {
        $target = $target->mergeRoot();
        $source = $source->fresh() ?? $source;

        $this->assertCanMerge($target, $source);

        DB::transaction(function () use ($target, $source) {
            $this->moveOwnedRecords($source, $target);

            $source->loadMissing('tags');
            if ($source->tags->isNotEmpty()) {
                $target->tags()->syncWithoutDetaching($source->tags->pluck('id')->all());
            }

            $source->merged_into_id = $target->id;
            $source->message_count = 0;
            $source->save();

            if (! $target->lead_id && $source->lead_id) {
                $target->lead_id = $source->lead_id;
            }

            $this->refreshStats($target);
        });

        return $target->fresh() ?? $target;
    }

    /**
     * Restore a conversation that was merged into $target.
     */
    public function unmerge(InboxConversation $target, InboxConversation $source): InboxConversation
    {
        $target = $target->mergeRoot();
        $source = $source->fresh() ?? $source;

        $this->assertCanUnmerge($target, $source);

        DB::transaction(function () use ($target, $source) {
            $ids = $this->descendantIds($source);

            $this->restoreOwnedRecords($target, $source, $ids);

            $source->merged_into_id = null;
            $this->refreshStats($source);
            $this->refreshStats($target);
        });

        return $target->fresh() ?? $target;
    }

    public function unmergeAll(InboxConversation $target): InboxConversation
    {
        $target = $target->mergeRoot();
        $children = InboxConversation::query()
            ->where('merged_into_id', $target->id)
            ->orderBy('id')
            ->get();

        foreach ($children as $source) {
            $this->unmerge($target, $source);
            $target = $target->fresh() ?? $target;
        }

        return $target;
    }

    /**
     * Visible conversations in the same inbox that can be merged into $target.
     *
     * @return Collection<int, InboxConversation>
     */
    public function candidates(InboxConversation $target, ?string $search = null, int $limit = 20): Collection
    {
        $target = $target->mergeRoot();

        $query = InboxConversation::query()
            ->notMerged()
            ->where('shared_inbox_id', $target->shared_inbox_id)
            ->where('id', '!=', $target->id)
            ->orderByDesc('last_message_at');

        $search = trim((string) $search);
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('from_email', 'like', "%{$search}%")
                    ->orWhere('from_name', 'like', "%{$search}%")
                    ->orWhere('snippet', 'like', "%{$search}%");
            });
        }

        return $query->limit($limit)->get();
    }

    private function assertCanMerge(InboxConversation $target, InboxConversation $source): void
    {
        if ((int) $target->id === (int) $source->id) {
            abort(422, 'Choose a different conversation to merge.');
        }

        if ((int) $target->shared_inbox_id !== (int) $source->shared_inbox_id) {
            abort(422, 'Conversations can only be merged within the same personal or shared inbox.');
        }

        if ($target->merged_into_id) {
            abort(422, 'This conversation is already merged into another thread.');
        }

        if ($source->merged_into_id) {
            abort(422, 'That conversation is already merged into another thread.');
        }

        if (in_array((int) $target->id, $this->descendantIds($source), true)) {
            abort(422, 'Cannot merge a conversation into one of its merged threads.');
        }
    }

    private function assertCanUnmerge(InboxConversation $target, InboxConversation $source): void
    {
        if ((int) $source->merged_into_id !== (int) $target->id) {
            abort(422, 'That conversation is not merged into this thread.');
        }

        if ((int) $target->shared_inbox_id !== (int) $source->shared_inbox_id) {
            abort(422, 'Conversations can only be unmerged within the same personal or shared inbox.');
        }
    }

    private function moveOwnedRecords(InboxConversation $from, InboxConversation $to): void
    {
        InboxMessage::query()
            ->where('inbox_conversation_id', $from->id)
            ->whereNull('source_conversation_id')
            ->update(['source_conversation_id' => $from->id]);

        $targetExternalIds = InboxMessage::query()
            ->where('inbox_conversation_id', $to->id)
            ->whereNotNull('external_message_id')
            ->pluck('external_message_id');

        if ($targetExternalIds->isNotEmpty()) {
            InboxMessage::query()
                ->where('inbox_conversation_id', $from->id)
                ->whereIn('external_message_id', $targetExternalIds)
                ->delete();
        }

        InboxMessage::query()
            ->where('inbox_conversation_id', $from->id)
            ->update(['inbox_conversation_id' => $to->id]);

        InboxConversationComment::query()
            ->where('inbox_conversation_id', $from->id)
            ->whereNull('source_conversation_id')
            ->update(['source_conversation_id' => $from->id]);
        InboxConversationComment::query()
            ->where('inbox_conversation_id', $from->id)
            ->update(['inbox_conversation_id' => $to->id]);

        InboxConversationActivity::query()
            ->where('inbox_conversation_id', $from->id)
            ->whereNull('source_conversation_id')
            ->update(['source_conversation_id' => $from->id]);
        InboxConversationActivity::query()
            ->where('inbox_conversation_id', $from->id)
            ->update(['inbox_conversation_id' => $to->id]);
    }

    /**
     * @param  list<int>  $sourceIds
     */
    private function restoreOwnedRecords(InboxConversation $from, InboxConversation $to, array $sourceIds): void
    {
        if ($sourceIds === []) {
            return;
        }

        InboxMessage::query()
            ->where('inbox_conversation_id', $from->id)
            ->whereIn('source_conversation_id', $sourceIds)
            ->update(['inbox_conversation_id' => $to->id]);

        InboxConversationComment::query()
            ->where('inbox_conversation_id', $from->id)
            ->whereIn('source_conversation_id', $sourceIds)
            ->update(['inbox_conversation_id' => $to->id]);

        InboxConversationActivity::query()
            ->where('inbox_conversation_id', $from->id)
            ->whereIn('source_conversation_id', $sourceIds)
            ->update(['inbox_conversation_id' => $to->id]);
    }

    /**
     * @return list<int>
     */
    private function descendantIds(InboxConversation $root): array
    {
        $ids = [(int) $root->id];
        $queue = [(int) $root->id];

        while ($queue !== []) {
            $children = InboxConversation::query()
                ->whereIn('merged_into_id', $queue)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $queue = [];
            foreach ($children as $id) {
                if (! in_array($id, $ids, true)) {
                    $ids[] = $id;
                    $queue[] = $id;
                }
            }
        }

        return $ids;
    }

    private function refreshStats(InboxConversation $conversation): void
    {
        $latest = $conversation->messages()
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();

        $conversation->message_count = $conversation->messages()->count();
        if ($latest) {
            $conversation->last_message_at = $latest->sent_at;
            $snippet = trim((string) ($latest->body_text ?: $conversation->snippet));
            $conversation->snippet = mb_substr($snippet, 0, 500);
            $conversation->from_name = $latest->from_name ?: $conversation->from_name;
            $conversation->from_email = $latest->from_email ?: $conversation->from_email;
        }
        $conversation->save();
    }
}
