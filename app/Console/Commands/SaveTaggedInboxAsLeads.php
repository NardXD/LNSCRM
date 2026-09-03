<?php

namespace App\Console\Commands;

use App\Models\InboxConversation;
use App\Models\InboxMessage;
use App\Models\InboxTag;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Models\LeadStatus;
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
        {--user= : ID of the user to run this as — controls which shared mailboxes can be attached, and who leads/activity are attributed to}
        {--company= : Restrict to one company ID (defaults to the --user\'s company)}
        {--source=Inbox tag import : Value stored in the lead\'s "source" field}
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
        $userId = (int) $this->option('user');
        if ($userId < 1) {
            $this->error('Pass --user=<id> — the user this should run as (their mailbox membership controls which emails can be attached to leads, and activity/leads are attributed to them).');

            return self::FAILURE;
        }

        $user = User::find($userId);
        if (! $user) {
            $this->error("No user with id {$userId}.");

            return self::FAILURE;
        }

        $companyId = (int) ($this->option('company') ?: $user->company_id);
        $tagName = trim((string) $this->argument('tag'));
        $dryRun = (bool) $this->option('dry-run');
        $source = trim((string) $this->option('source')) ?: 'Inbox tag import';

        $tag = InboxTag::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [strtolower($tagName)])
            ->first();

        if (! $tag) {
            $this->error("No inbox tag named \"{$tagName}\" for company #{$companyId}.");

            return self::FAILURE;
        }

        $conversations = InboxConversation::query()
            ->where('company_id', $companyId)
            ->whereNull('merged_into_id')
            ->whereNull('lead_id')
            ->whereHas('tags', fn ($q) => $q->where('inbox_tags.id', $tag->id))
            ->with(['messages', 'inbox.account'])
            ->orderBy('id')
            ->get();

        if ($conversations->isEmpty()) {
            $this->info("No conversations tagged \"{$tagName}\" without a lead already attached.");

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%d conversation(s) tagged "%s" with no lead yet.%s',
            $conversations->count(),
            $tagName,
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
                $this->inboxAttach->attach($lead, $conversation, $user);
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

        return $this->extractor->fromTexts($texts);
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
