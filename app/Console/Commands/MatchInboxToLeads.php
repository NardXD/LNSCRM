<?php

namespace App\Console\Commands;

use App\Models\InboxConversation;
use App\Models\SharedInbox;
use App\Models\User;
use App\Services\LeadAutoCreateService;
use App\Services\LeadInboxAttachService;
use Illuminate\Console\Command;
use Throwable;

class MatchInboxToLeads extends Command
{
    protected $signature = 'inbox:match-leads
        {--shared-inbox= : Restrict to one shared inbox, by ID or name}
        {--user= : ID of the user activity should be attributed to (no mailbox membership required — this is a trusted backend job). Defaults to each matched conversation\'s own shared inbox creator when omitted.}
        {--company= : Restrict to one company ID (defaults to the --shared-inbox\'s or --user\'s company)}
        {--limit= : Max conversations to check, oldest first (default: no limit — checks every candidate)}
        {--dry-run : Preview matches without attaching anything}';

    protected $description = 'Attach shared-inbox email threads that have no lead yet to an existing lead whenever the sender\'s email matches one of that lead\'s identities.';

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

        $baseQuery = InboxConversation::query()
            ->whereNull('merged_into_id')
            ->whereNull('lead_id')
            ->whereNotNull('from_email')
            ->where('from_email', '!=', '')
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

        $conversations = $baseQuery
            ->with('inbox.creator')
            ->orderBy('id')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $this->info(sprintf(
            'Checking %d unattached shared-inbox conversation(s)%s.%s',
            $totalMatched,
            $limit && $limit < $totalMatched ? ", processing the first {$limit} (oldest)" : '',
            $dryRun ? ' (dry run — nothing will be attached)' : ''
        ));

        $attached = 0;
        $skipped = 0;
        $userCache = [];

        foreach ($conversations as $conversation) {
            $label = "#{$conversation->id} ".($conversation->subject ?: '(no subject)');

            $lead = $this->leadAutoCreate->fromInboxConversation($conversation);
            if (! $lead) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("  {$label}: would attach to lead \"{$lead->name}\" (#{$lead->id}) — {$conversation->from_email}.");

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
                $this->line("  {$label}: {$conversation->from_email} → lead #{$lead->id} \"{$lead->name}\" (attached).");
                $attached++;
            } catch (Throwable $e) {
                $this->line("  {$label}: matched lead #{$lead->id}, but could not attach — {$e->getMessage()}");
                $skipped++;
            }
        }

        if (! $dryRun) {
            $this->info("Done. Attached {$attached}, skipped {$skipped}.");
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
