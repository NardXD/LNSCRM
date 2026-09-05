<?php

namespace App\Console\Commands;

use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\InboxTag;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Models\LeadLabel;
use App\Models\LeadStatus;
use App\Models\SharedInbox;
use App\Models\User;
use App\Services\LeadActivityService;
use App\Services\LeadInboxAttachService;
use App\Services\MessageContactExtractor;
use App\Services\OutlookMailService;
use App\Support\EmailQuotedHistory;
use Illuminate\Console\Command;
use Throwable;

class SaveTaggedInboxAsLeads extends Command
{
    protected $signature = 'inbox:tag-to-leads
        {tag : Inbox tag name to match, e.g. "Inquiry"}
        {--shared-inbox= : Restrict to one shared inbox, by ID or name}
        {--user= : ID of the user leads/activity should be attributed to (no mailbox membership required — this is a trusted backend job). Defaults to the --shared-inbox\'s creator when omitted.}
        {--company= : Restrict to one company ID (defaults to the --shared-inbox\'s or --user\'s company)}
        {--source=Inbox tag import : Value stored in the lead\'s "source" field}
        {--limit= : Max conversations to process, oldest first (default: no limit — processes every match)}
        {--dry-run : Preview matches without creating or attaching anything}';

    protected $description = 'Save every inbox conversation carrying a given tag as a CRM lead, using the same name/phone/email extraction and duplicate rules as the "Save as lead" button.';

    public function __construct(
        private readonly MessageContactExtractor $extractor,
        private readonly LeadInboxAttachService $inboxAttach,
        private readonly LeadActivityService $leadActivity,
        private readonly OutlookMailService $mailService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $companyOption = $this->option('company') ? (int) $this->option('company') : null;
        $sharedInboxOption = trim((string) $this->option('shared-inbox'));

        $sharedInbox = null;
        if ($sharedInboxOption !== '') {
            $sharedInbox = $this->resolveSharedInbox($sharedInboxOption, $companyOption);
            if (! $sharedInbox) {
                $this->error("No shared inbox matching \"{$sharedInboxOption}\"".($companyOption ? " for company #{$companyOption}." : '.'));

                return self::FAILURE;
            }
        }

        $userId = (int) $this->option('user');
        $user = $userId > 0 ? User::find($userId) : null;
        if ($userId > 0 && ! $user) {
            $this->error("No user with id {$userId}.");

            return self::FAILURE;
        }
        if (! $user && $sharedInbox) {
            $user = $sharedInbox->creator;
        }
        if (! $user) {
            $this->error('Pass --user=<id> to attribute the created leads/activity to someone (or pass --shared-inbox= so it can default to that mailbox\'s creator).');

            return self::FAILURE;
        }

        $companyId = $companyOption ?: (int) ($sharedInbox->company_id ?? $user->company_id);
        $tagName = trim((string) $this->argument('tag'));
        $dryRun = (bool) $this->option('dry-run');
        $source = trim((string) $this->option('source')) ?: 'Inbox tag import';

        // The inbox page renders a conversation's pills from two different sources —
        // plain InboxTag rows and LeadLabel rows (the latter is what Front-imported
        // labels use) — so a label typed in the UI could live in either table.
        $inboxTag = InboxTag::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($tagName)])
            ->first();
        $leadLabel = LeadLabel::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($tagName)])
            ->first();

        if (! $inboxTag && ! $leadLabel) {
            $this->error("No inbox tag or lead label named \"{$tagName}\" for company #{$companyId}.");

            return self::FAILURE;
        }

        $baseQuery = InboxConversation::query()
            ->where('company_id', $companyId)
            ->whereNull('merged_into_id')
            ->whereNull('lead_id')
            ->when($sharedInbox, fn ($q) => $q->where('shared_inbox_id', $sharedInbox->id))
            ->where(function ($q) use ($inboxTag, $leadLabel) {
                if ($inboxTag) {
                    $q->orWhereHas('tags', fn ($t) => $t->where('inbox_tags.id', $inboxTag->id));
                }
                if ($leadLabel) {
                    $q->orWhereHas('leadLabels', fn ($l) => $l->where('lead_labels.id', $leadLabel->id));
                }
            });

        $totalMatched = (clone $baseQuery)->count();

        if ($totalMatched === 0) {
            $this->info("No conversations labeled \"{$tagName}\" without a lead already attached.");

            return self::SUCCESS;
        }

        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;

        $conversations = $baseQuery
            ->with(['messages', 'inbox.account'])
            ->orderBy('id')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $this->info(sprintf(
            '%d conversation(s) tagged "%s" with no lead yet%s.%s',
            $totalMatched,
            $tagName,
            $limit && $limit < $totalMatched ? ", processing the first {$limit} (oldest)" : '',
            $dryRun ? ' (dry run — nothing will be saved)' : ''
        ));

        $created = 0;
        $matchedExisting = 0;
        $skipped = 0;

        foreach ($conversations as $conversation) {
            $label = "#{$conversation->id} ".($conversation->subject ?: '(no subject)');

            $extracted = $this->extractFromConversation($conversation);
            $name = $extracted['names'][0] ?? null;
            $phones = $extracted['phones'];
            $emails = $extracted['emails'];

            if (! $name || ($phones === [] && $emails === [])) {
                $this->line("  {$label}: skipped — could not find a name plus a phone or email in the body.");
                $skipped++;

                continue;
            }

            $identities = $this->identityList($phones, $emails);
            $conflict = $this->findIdentityConflict($companyId, $identities);

            if ($dryRun) {
                if ($conflict) {
                    $this->line("  {$label}: would attach to existing lead \"{$conflict->lead?->name}\" (#{$conflict->lead_id}) — {$name}.");
                } else {
                    $this->line("  {$label}: would create lead \"{$name}\" (".implode(', ', array_merge($phones, $emails)).').');
                }

                continue;
            }

            if ($conflict && $conflict->lead) {
                $lead = $conflict->lead;
                $matchedExisting++;
            } else {
                $lead = Lead::create([
                    'company_id' => $companyId,
                    'name' => $name,
                    'source' => $source,
                    'status' => LeadStatus::fallbackSlug($companyId),
                ]);
                $lead->syncIdentities($identities);
                $this->leadActivity->recordCreated($lead, $source, $user->id);
                $created++;
            }

            try {
                // requireMembership: false — this runs from the server as a trusted backend
                // job, not through the UI, so --user doesn't need to be a member of the
                // shared inbox the "Inquiry" emails live in. It's still only used to
                // attribute the created lead/activity records to someone.
                $this->inboxAttach->attach($lead, $conversation, $user, requireMembership: false);
                $this->line("  {$label}: {$name} → lead #{$lead->id} (attached).");
            } catch (Throwable $e) {
                $this->line("  {$label}: {$name} → lead #{$lead->id}, but could not attach the email — {$e->getMessage()}");
            }
        }

        if (! $dryRun) {
            $this->info("Done. Created {$created}, matched to an existing lead {$matchedExisting}, skipped {$skipped}.");
        }

        return self::SUCCESS;
    }

    private function resolveSharedInbox(string $value, ?int $companyId): ?SharedInbox
    {
        $query = SharedInbox::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        if (ctype_digit($value)) {
            $byId = (clone $query)->find((int) $value);
            if ($byId) {
                return $byId;
            }
        }

        return (clone $query)->whereRaw('LOWER(name) = ?', [strtolower($value)])->first();
    }

    /**
     * @return array{phones: list<string>, emails: list<string>, names: list<string>}
     */
    private function extractFromConversation(InboxConversation $conversation): array
    {
        if ($conversation->inbox) {
            try {
                $this->mailService->hydrateConversationBodies($conversation->inbox, $conversation);
                $conversation->load('messages');
            } catch (Throwable) {
                // Best effort — fall back to whatever body is already stored.
            }
        }

        $inbound = $conversation->messages->filter(
            fn (InboxMessage $m) => strtolower((string) $m->direction) !== 'outbound'
        );
        $messages = $inbound->isNotEmpty() ? $inbound : $conversation->messages;

        $texts = $messages
            ->map(function (InboxMessage $m) {
                $html = (string) ($m->body_html ?? '');
                $text = trim($html) !== ''
                    ? EmailQuotedHistory::plainFromHtml($html)
                    : (string) ($m->body_text ?: '');

                return EmailQuotedHistory::stripPlain($text);
            })
            ->filter(fn ($text) => trim($text) !== '')
            ->values()
            ->all();

        if ($texts === []) {
            return ['phones' => [], 'emails' => [], 'names' => []];
        }

        $extracted = $this->extractor->fromTexts($texts);
        $extracted['emails'] = array_values(array_filter(
            $extracted['emails'],
            fn (string $email) => ! str_ends_with(strtolower($email), '@locnstor247.com')
        ));

        return $extracted;
    }

    /**
     * @param  list<string>  $phones
     * @param  list<string>  $emails
     * @return list<array{type: string, value: string, label: ?string, is_primary: bool}>
     */
    private function identityList(array $phones, array $emails): array
    {
        $items = [];
        foreach (array_values($phones) as $i => $phone) {
            $items[] = ['type' => LeadIdentity::TYPE_PHONE, 'value' => $phone, 'label' => null, 'is_primary' => $i === 0];
        }
        foreach (array_values($emails) as $i => $email) {
            $items[] = ['type' => LeadIdentity::TYPE_EMAIL, 'value' => $email, 'label' => null, 'is_primary' => $i === 0];
        }

        return $items;
    }

    /**
     * Mirrors LeadsController::findIdentityConflict — same phone/email dedupe the
     * "Save as lead" button relies on, so this batch job never creates duplicates.
     *
     * @param  list<array{type: string, value: string, label: ?string, is_primary: bool}>  $identities
     */
    private function findIdentityConflict(int $companyId, array $identities): ?LeadIdentity
    {
        foreach ($identities as $item) {
            $normalized = LeadIdentity::normalize($item['type'], $item['value']);
            if ($normalized === '') {
                continue;
            }

            $match = LeadIdentity::query()
                ->where('type', $item['type'])
                ->where('normalized_value', $normalized)
                ->whereHas('lead', fn ($q) => $q->where('company_id', $companyId))
                ->with('lead:id,name,company_id')
                ->first();

            if ($match) {
                return $match;
            }
        }

        return null;
    }
}
