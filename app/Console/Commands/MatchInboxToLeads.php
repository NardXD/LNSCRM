<?php

namespace App\Console\Commands;

use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Models\SharedInbox;
use App\Models\User;
use App\Services\LeadInboxAttachService;
use App\Services\MessageContactExtractor;
use App\Services\OutlookMailService;
use App\Support\EmailQuotedHistory;
use Illuminate\Console\Command;
use Throwable;

class MatchInboxToLeads extends Command
{
    /**
     * Email domains that are the company's own mailboxes/infrastructure, not a
     * customer's — kept in sync with SaveTaggedInboxAsLeads' list. A webform-style
     * inquiry often quotes one of these somewhere in its body/signature, which isn't
     * a way to reach the actual customer.
     */
    private const EXCLUDED_EMAIL_DOMAINS = [
        '@locnstor247.com',
        '@sezpr02mb6201.apcprd02.prod.outlook.com',
        '@c128513.sgvps.net',
    ];

    protected $signature = 'inbox:match-leads
        {--shared-inbox= : Restrict to one shared inbox, by ID or name}
        {--user= : ID of the user activity should be attributed to (no mailbox membership required — this is a trusted backend job). Defaults to each matched conversation\'s own shared inbox creator when omitted.}
        {--company= : Restrict to one company ID (defaults to the --shared-inbox\'s or --user\'s company)}
        {--limit= : Max conversations to check, oldest first (default: no limit — checks every candidate)}
        {--dry-run : Preview matches without attaching anything}';

    protected $description = 'Attach shared-inbox email threads to an existing lead — matching the sender\'s email header AND any name/phone/email found in the message body (same extraction "Save as lead"/inbox:tag-to-leads use, since a webform-style inquiry often carries the real contact info in the body, not the "From" address). A lead is only ever matched once — after it has one attached conversation, later matching emails need to be attached manually.';

    public function __construct(
        private readonly MessageContactExtractor $extractor,
        private readonly OutlookMailService $mailService,
        private readonly LeadInboxAttachService $inboxAttach,
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
        $explicitUser = $userId > 0 ? User::find($userId) : null;
        if ($userId > 0 && ! $explicitUser) {
            $this->error("No user with id {$userId}.");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;

        // Snapshot of leads that don't have a shared-inbox conversation attached yet,
        // taken once up front — a lead matched partway through this run stays eligible
        // for the rest of THIS run (so every currently-pending email for a
        // freshly-matched lead gets swept up together), but won't be reconsidered on
        // the next run once it has at least one attached conversation.
        $eligibleLeadIds = Lead::query()
            ->whereDoesntHave('inboxConversations')
            ->when($companyOption, fn ($q) => $q->where('company_id', $companyOption))
            ->when($sharedInbox, fn ($q) => $q->where('company_id', $sharedInbox->company_id))
            ->pluck('id')
            ->flip();

        $baseQuery = InboxConversation::query()
            ->whereNull('merged_into_id')
            ->whereNull('lead_id')
            ->when($companyOption, fn ($q) => $q->where('company_id', $companyOption))
            ->whereHas('inbox', function ($q) use ($sharedInbox) {
                $q->where('type', SharedInbox::TYPE_SHARED)->where('is_active', true);
                if ($sharedInbox) {
                    $q->whereKey($sharedInbox->id);
                }
            });

        $totalMatched = (clone $baseQuery)->count();

        if ($totalMatched === 0) {
            $this->info('No unattached shared-inbox conversations to check.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Checking %d unattached shared-inbox conversation(s)%s.%s',
            $totalMatched,
            $limit && $limit < $totalMatched ? ", processing the first {$limit} (oldest)" : '',
            $dryRun ? ' (dry run — nothing will be attached)' : ''
        ));

        $attached = 0;
        $skipped = 0;
        $processed = 0;
        $userCache = [];

        // Chunked (not ->get()) — with no --limit this can walk every unattached
        // shared-inbox conversation in the company, and eager-loading all of their
        // messages (HTML bodies included) in one query can exhaust PHP's memory
        // limit (see the same fix in SaveTaggedInboxAsLeads).
        $baseQuery
            ->with(['messages', 'inbox.account', 'inbox.creator'])
            ->chunkById(100, function ($conversations) use (
                $limit, &$processed, &$attached, &$skipped, $dryRun, $eligibleLeadIds, &$userCache, $explicitUser
            ) {
                foreach ($conversations as $conversation) {
                    if ($limit !== null && $processed >= $limit) {
                        return false;
                    }
                    $processed++;

                    $label = "#{$conversation->id} ".($conversation->subject ?: '(no subject)');

                    $extracted = $this->extractFromConversation($conversation);
                    $emails = $extracted['emails'];
                    if (($fromEmail = trim((string) $conversation->from_email)) !== '') {
                        $emails[] = $fromEmail;
                    }
                    $emails = array_values(array_unique($emails));

                    if ($emails === [] && $extracted['phones'] === []) {
                        $skipped++;

                        continue;
                    }

                    $identities = $this->identityList($extracted['phones'], $emails);
                    $conflict = $this->findIdentityConflict((int) $conversation->company_id, $identities);

                    if (! $conflict || ! $conflict->lead) {
                        $skipped++;

                        continue;
                    }

                    $lead = $conflict->lead;

                    if (! $eligibleLeadIds->has($lead->id)) {
                        $this->line("  {$label}: matches lead \"{$lead->name}\" (#{$lead->id}), but it already has a shared-inbox thread attached — skipped.");
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $this->line("  {$label}: would attach to lead \"{$lead->name}\" (#{$lead->id}).");
                        $attached++;

                        continue;
                    }

                    $user = $explicitUser ?? $this->attributionUser($conversation, $userCache);
                    if (! $user) {
                        $this->line("  {$label}: matched lead \"{$lead->name}\" (#{$lead->id}), but no user available to attribute the attach to — skipped.");
                        $skipped++;

                        continue;
                    }

                    try {
                        // requireMembership: false — this runs from the server as a trusted backend
                        // job, not through the UI, so the attribution user doesn't need to be a
                        // member of whichever shared inbox the matched email lives in.
                        $this->inboxAttach->attach($lead, $conversation, $user, requireMembership: false);
                        $this->line("  {$label}: → lead #{$lead->id} \"{$lead->name}\" (attached).");
                        $attached++;
                    } catch (Throwable $e) {
                        $this->line("  {$label}: matched lead #{$lead->id}, but could not attach — {$e->getMessage()}");
                        $skipped++;
                    }
                }
            }, 'inbox_conversations.id', 'id');

        if (! $dryRun) {
            $this->info("Done. Attached {$attached}, skipped {$skipped}.");
        }

        return self::SUCCESS;
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
            fn (string $email) => ! collect(self::EXCLUDED_EMAIL_DOMAINS)->contains(
                fn (string $domain) => str_ends_with(strtolower($email), $domain)
            )
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
     * Mirrors LeadsController::findIdentityConflict / SaveTaggedInboxAsLeads — same
     * phone/email lookup the "Save as lead" button and the tag-to-leads job rely on.
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
     * @param  array<string, ?User>  $userCache  keyed by "inbox:<shared_inbox_id>", filled in as inboxes are seen
     */
    private function attributionUser(InboxConversation $conversation, array &$userCache): ?User
    {
        $key = 'inbox:'.$conversation->shared_inbox_id;
        if (! array_key_exists($key, $userCache)) {
            $userCache[$key] = $conversation->inbox?->creator;
        }

        return $userCache[$key];
    }
}
