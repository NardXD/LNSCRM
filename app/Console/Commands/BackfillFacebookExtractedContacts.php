<?php

namespace App\Console\Commands;

use App\Models\FacebookConversation;
use App\Services\MessageContactExtractor;
use Illuminate\Console\Command;

class BackfillFacebookExtractedContacts extends Command
{
    protected $signature = 'facebook:backfill-extracted-contacts
        {--company= : Restrict to one company ID}
        {--limit= : Max conversations to process (default: no limit — processes every candidate)}';

    protected $description = 'Cache each Facebook/Instagram conversation\'s extracted phone/email (added so the conversation list can match assigned leads the same way opening a thread already does) — a one-time backfill for conversations that existed before this cache was introduced.';

    public function handle(MessageContactExtractor $extractor): int
    {
        $companyId = $this->option('company') ? (int) $this->option('company') : null;
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;

        $query = FacebookConversation::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderBy('id');

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No Facebook conversations to backfill.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Backfilling %d conversation(s)%s.',
            $total,
            $limit && $limit < $total ? ", processing the first {$limit}" : ''
        ));

        $processed = 0;
        $updated = 0;

        $query->chunkById(200, function ($conversations) use ($extractor, $limit, &$processed, &$updated) {
            foreach ($conversations as $conversation) {
                if ($limit !== null && $processed >= $limit) {
                    return false;
                }
                $processed++;

                $before = [$conversation->extracted_phone, $conversation->extracted_email];
                $extractor->applyToConversation($conversation);
                if ($before !== [$conversation->extracted_phone, $conversation->extracted_email]) {
                    $updated++;
                }
            }
        });

        $this->info("Done. Checked {$processed}, updated {$updated}.");

        return self::SUCCESS;
    }
}
