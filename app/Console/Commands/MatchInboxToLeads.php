<?php

namespace App\Console\Commands;

use App\Models\InboxConversation;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Models\SharedInbox;
use App\Models\User;
use App\Services\LeadAutoCreateService;
use App\Services\LeadInboxAttachService;
use Illuminate\Console\Command;
use Throwable;

class MatchInboxToLeads extends Command
{
    protected $signature = 'inbox:match-leads
        {--shared-inbox= : Restrict candidate emails to one shared inbox, by ID or name}
        {--user= : ID of the user activity should be attributed to (no mailbox membership required — this is a trusted backend job). Defaults to each matched conversation\'s own shared inbox creator when omitted.}
        {--company= : Restrict to one company ID (defaults to the --shared-inbox\'s or --user\'s company)}
        {--limit= : Max leads to check, oldest first (default: no limit — checks every candidate lead)}
        {--dry-run : Preview matches without attaching anything}';

    protected $description = 'Backfill leads that have no shared-inbox email attached yet by finding an unattached conversation whose sender matches one of that lead\'s saved email identities. A lead is only checked once — after it has one attached conversation, later matching emails need to be attached manually.';

    public function __construct(
        private readonly LeadAutoCreateService $leadAutoCreate,
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

        $leadsQuery = Lead::query()
            ->whereDoesntHave('inboxConversations')
            ->whereHas('identities', fn ($q) => $q->where('type', LeadIdentity::TYPE_EMAIL))
            ->when($companyOption, fn ($q) => $q->where('company_id', $companyOption))
            ->when($sharedInbox, fn ($q) => $q->where('company_id', $sharedInbox->company_id));

        $totalLeads = (clone $leadsQuery)->count();

        if ($totalLeads === 0) {
            $this->info('No leads without an attached shared-inbox email to check.');

            return self::SUCCESS;
        }

        $leads = $leadsQuery
            ->with('identities')
            ->orderBy('id')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $this->info(sprintf(
            'Checking %d lead(s) with no shared-inbox email attached yet%s.%s',
            $totalLeads,
            $limit && $limit < $totalLeads ? ", processing the first {$limit} (oldest)" : '',
            $dryRun ? ' (dry run — nothing will be attached)' : ''
        ));

        $matchedLeads = 0;
        $attachedConversations = 0;
        $skipped = 0;
        $userCache = [];

        foreach ($leads as $lead) {
            $label = "Lead #{$lead->id} \"{$lead->name}\"";

            $emails = $lead->identities
                ->where('type', LeadIdentity::TYPE_EMAIL)
                ->pluck('normalized_value')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($emails === []) {
                $skipped++;

                continue;
            }

            $matches = $this->matchingConversations($lead, $emails, $sharedInbox);

            if ($matches->isEmpty()) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("  {$label}: would attach ".$matches->count().' conversation(s) — #'.$matches->pluck('id')->implode(', #'));
                $matchedLeads++;

                continue;
            }

            $attachedForLead = 0;
            foreach ($matches as $conversation) {
                $user = $explicitUser ?? $this->attributionUser($conversation, $userCache);
                if (! $user) {
                    $this->line("  {$label}: matched conversation #{$conversation->id}, but no user available to attribute the attach to — skipped.");

                    continue;
                }

                try {
                    // requireMembership: false — this runs from the server as a trusted backend
                    // job, not through the UI, so the attribution user doesn't need to be a
                    // member of whichever shared inbox the matched email lives in.
                    $this->inboxAttach->attach($lead, $conversation, $user, requireMembership: false);
                    $attachedForLead++;
                    $attachedConversations++;
                } catch (Throwable $e) {
                    $this->line("  {$label}: could not attach conversation #{$conversation->id} — {$e->getMessage()}");
                }
            }

            if ($attachedForLead > 0) {
                $this->line("  {$label}: attached {$attachedForLead} conversation(s).");
                $matchedLeads++;
            } else {
                $skipped++;
            }
        }

        if (! $dryRun) {
            $this->info("Done. Matched {$matchedLeads} lead(s), attached {$attachedConversations} conversation(s), skipped {$skipped}.");
        }

        return self::SUCCESS;
    }

    /**
     * Unattached shared-inbox conversations whose sender email is one of this lead's saved
     * identities. Re-validated through LeadAutoCreateService::fromInboxConversation() so the
     * matching rule (no-reply patterns, mailbox/staff-owned addresses excluded, etc.) lives in
     * exactly one place, shared with the real-time inbound-mail path.
     *
     * @param  list<string>  $normalizedEmails
     * @return \Illuminate\Support\Collection<int, InboxConversation>
     */
    private function matchingConversations(Lead $lead, array $normalizedEmails, ?SharedInbox $sharedInbox)
    {
        $candidates = InboxConversation::query()
            ->whereNull('merged_into_id')
            ->whereNull('lead_id')
            ->where('company_id', $lead->company_id)
            ->where(function ($q) use ($normalizedEmails) {
                foreach ($normalizedEmails as $email) {
                    $q->orWhereRaw('LOWER(TRIM(from_email)) = ?', [$email]);
                }
            })
            ->whereHas('inbox', function ($q) use ($sharedInbox) {
                $q->where('type', SharedInbox::TYPE_SHARED)->where('is_active', true);
                if ($sharedInbox) {
                    $q->whereKey($sharedInbox->id);
                }
            })
            ->with('inbox.creator')
            ->orderBy('id')
            ->get();

        return $candidates->filter(
            fn (InboxConversation $conversation) => $this->leadAutoCreate->fromInboxConversation($conversation)?->is($lead)
        )->values();
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
     * @param  array<int, ?User>  $userCache  keyed by shared_inbox_id, filled in as inboxes are seen
     */
    private function attributionUser(InboxConversation $conversation, array &$userCache): ?User
    {
        $inboxId = (int) $conversation->shared_inbox_id;
        if (! array_key_exists($inboxId, $userCache)) {
            $userCache[$inboxId] = $conversation->inbox?->creator;
        }

        return $userCache[$inboxId];
    }
}
