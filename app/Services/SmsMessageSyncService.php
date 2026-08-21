<?php

namespace App\Services;

use App\Models\Company;
use App\Models\SmsMessage;
use App\Models\TwilioPhoneNumber;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SmsMessageSyncService
{
    public function __construct(
        protected TwilioCompanyService $twilioCompany,
        protected SmsConversationService $smsConversations
    ) {}

    /**
     * Pull recent Twilio SMS for a company and import any missed messages.
     */
    public function ingestRecent(Company $company, int $minutes = 45, int $limit = 120): int
    {
        $integration = $this->twilioCompany->getActiveIntegration($company);
        if (! $integration) {
            return 0;
        }

        $credentials = $this->twilioCompany->getCredentials($integration);
        if (! $credentials) {
            return 0;
        }

        $numbers = $this->companySmsNumbers($company);
        if ($numbers === []) {
            return 0;
        }

        $twilio = new TwilioService($credentials['sid'], $credentials['token']);
        $after = Carbon::now()->subMinutes(max(5, $minutes));
        $ourLookup = array_fill_keys($numbers, true);
        $bySid = [];

        foreach ($numbers as $number) {
            foreach ($twilio->listChannelMessages($number, $after, $limit) as $message) {
                if ($this->isSocialChannel((string) ($message->from ?? ''), (string) ($message->to ?? ''))) {
                    continue;
                }
                $bySid[(string) $message->sid] = $message;
                if (count($bySid) >= $limit) {
                    break 2;
                }
            }
        }

        if ($bySid === []) {
            return 0;
        }

        $existing = SmsMessage::query()
            ->where('company_id', $company->id)
            ->whereIn('message_sid', array_keys($bySid))
            ->pluck('message_sid')
            ->all();
        $existingLookup = array_fill_keys($existing, true);

        $imported = 0;
        foreach ($bySid as $sid => $message) {
            if (isset($existingLookup[$sid])) {
                continue;
            }

            try {
                if ($this->importTwilioMessage($company, $message, $ourLookup)) {
                    $imported++;
                    $existingLookup[$sid] = true;
                }
            } catch (\Throwable $e) {
                Log::warning('SMS catch-up import failed', [
                    'company_id' => $company->id,
                    'message_sid' => $sid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $imported;
    }

    /**
     * @param  array<string, bool>  $ourLookup
     */
    private function importTwilioMessage(
        Company $company,
        object $message,
        array $ourLookup
    ): bool {
        $sid = (string) ($message->sid ?? '');
        if ($sid === '') {
            return false;
        }

        $from = $this->twilioCompany->normalizePhone((string) ($message->from ?? ''));
        $to = $this->twilioCompany->normalizePhone((string) ($message->to ?? ''));
        if ($from === '' || $to === '') {
            return false;
        }

        $fromIsOurs = isset($ourLookup[$from]);
        $toIsOurs = isset($ourLookup[$to]);
        if (! $fromIsOurs && ! $toIsOurs) {
            return false;
        }

        $direction = $fromIsOurs ? 'outbound' : 'inbound';
        $peer = $direction === 'inbound' ? $from : $to;
        $our = $direction === 'inbound' ? $to : $from;

        $conversation = $this->smsConversations->upsert(
            (int) $company->id,
            $peer,
            $our
        );

        $sentAt = null;
        if (! empty($message->dateSent)) {
            try {
                $sentAt = Carbon::parse((string) $message->dateSent)->timezone(config('app.timezone'));
            } catch (\Throwable) {
                $sentAt = now();
            }
        }

        $user = $this->twilioCompany->resolveUserFromNumbers($to, $from, $direction);

        $record = SmsMessage::query()->updateOrCreate(
            ['message_sid' => $sid],
            [
                'company_id' => $company->id,
                'sms_conversation_id' => $conversation->id,
                'user_id' => $user?->id,
                'direction' => $direction,
                'from_number' => $from,
                'to_number' => $to,
                'body' => (string) ($message->body ?? ''),
                'status' => (string) ($message->status ?? ($direction === 'inbound' ? 'received' : 'sent')),
                'sent_at' => $sentAt ?: now(),
            ]
        );

        if ($record->wasRecentlyCreated) {
            $this->smsConversations->touch(
                $conversation,
                $record,
                $direction === 'inbound'
            );
        }

        return $record->wasRecentlyCreated;
    }

    /**
     * @return array<int, string>
     */
    private function companySmsNumbers(Company $company): array
    {
        $numbers = TwilioPhoneNumber::query()
            ->where('company_id', $company->id)
            ->pluck('phone_number')
            ->map(fn ($n) => $this->twilioCompany->normalizePhone((string) $n))
            ->filter()
            ->values()
            ->all();

        $userQuery = $company->users();
        if (Schema::hasColumn('users', 'twilio_sms_number')) {
            $userNumbers = $userQuery
                ->whereNotNull('twilio_sms_number')
                ->pluck('twilio_sms_number')
                ->map(fn ($n) => $this->twilioCompany->normalizePhone((string) $n))
                ->filter()
                ->all();
            $numbers = array_merge($numbers, $userNumbers);
        }

        return array_values(array_unique(array_filter($numbers)));
    }

    private function isSocialChannel(string $from, string $to): bool
    {
        $haystack = strtolower($from.' '.$to);

        return str_contains($haystack, 'messenger:')
            || str_contains($haystack, 'instagram:')
            || str_contains($haystack, 'whatsapp:')
            || str_contains($haystack, 'viber:');
    }
}
