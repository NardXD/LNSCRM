<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Models\LeadLabel;
use App\Models\LeadStatus;
use App\Models\MessageTemplate;
use App\Models\User;
use App\Services\LeadFollowUpDayService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LeadFollowUpDaySeeder extends Seeder
{
    public const NOTE_MARKER = '[follow-up-seeder]';

    public function run(): void
    {
        $company = Company::current();
        if (! $company) {
            $this->command?->error('No company found. Seed companies first, or set COMPANY_ID.');

            return;
        }

        LeadStatus::ensureForCompany((int) $company->id);

        $followUp = app(LeadFollowUpDayService::class);
        $company->lead_follow_up_days = LeadFollowUpDayService::DEFAULT_DAYS;
        $company->save();
        $followUp->rememberDays((int) $company->id, LeadFollowUpDayService::DEFAULT_DAYS);
        $followUp->ensureForCompany((int) $company->id, true, true);

        $days = $followUp->configuredDays((int) $company->id);
        $timezone = $followUp->timezone($company);
        $today = now($timezone)->startOfDay();
        $assigneeId = User::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->value('id');

        $inquiry = LeadLabel::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => LeadFollowUpDayService::INQUIRY_LABEL],
            ['color' => '#9333ea']
        );
        $moveIn = LeadLabel::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => LeadFollowUpDayService::MOVE_IN_LABEL],
            ['color' => '#16a34a']
        );
        $notInterested = LeadLabel::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => LeadFollowUpDayService::NOT_INTERESTED_LABEL],
            ['color' => '#dc2626']
        );

        Lead::query()
            ->where('company_id', $company->id)
            ->where('notes', 'like', '%'.self::NOTE_MARKER.'%')
            ->delete();

        $created = 0;

        $created += $this->seedGroup($company, $assigneeId, [$inquiry], $today, 0, 'new', 2, 'Created today');
        foreach ($days as $day) {
            $created += $this->seedGroup($company, $assigneeId, [$inquiry], $today, $day, 'new', 3, $followUp->ordinalDayLabel($day));
            $created += $this->seedGroup($company, $assigneeId, [$inquiry], $today, $day, 'contacted', 1, $followUp->ordinalDayLabel($day).' contacted');
        }

        $created += $this->seedGroup($company, $assigneeId, [$inquiry], $today, 4, Lead::STATUS_SNOOZED, 2, 'Snoozed 4th Day FU', [
            'reopen_at' => now($timezone)->addDays(3),
            'reopen_status' => 'contacted',
        ]);
        $created += $this->seedGroup($company, $assigneeId, [$inquiry], $today, 4, 'converted', 1, 'Converted (skipped)');
        $created += $this->seedGroup($company, $assigneeId, [$inquiry, $moveIn], $today, 4, 'new', 1, 'Move in (no FU tag)');
        $created += $this->seedGroup($company, $assigneeId, [$inquiry, $notInterested], $today, 10, 'new', 1, 'Not Interested (no FU tag)');
        $created += $this->seedGroup($company, $assigneeId, [$inquiry], $today, 120, 'lost', 1, 'Lost older');
        $created += $this->seedGroup($company, $assigneeId, [$inquiry], $today, 120, Lead::STATUS_ARCHIVED, 1, 'Archived older');
        $created += $this->seedGroup($company, $assigneeId, [$inquiry], $today, 120, 'qualified', 1, 'Qualified 90th Day FU');

        $this->seedTemplates($company, $assigneeId);
        \Illuminate\Support\Facades\Artisan::call('leads:process-follow-up-days');

        $this->command?->info("Seeded {$created} follow-up test leads for {$company->name} (id {$company->id}).");
        $this->command?->info('Chips are 4th / 10th / 30th / 90th Day FU labels. Move in and Not Interested are not tagged.');
        $this->command?->info('Re-run replaces only leads whose notes contain '.self::NOTE_MARKER.'.');
    }

    /**
     * @param  list<LeadLabel>  $labels
     * @param  array<string, mixed>  $extra
     */
    private function seedGroup(
        Company $company,
        ?int $assigneeId,
        array $labels,
        \Carbon\CarbonInterface $today,
        int $calendarDaysAgo,
        string $status,
        int $count,
        string $bucket,
        array $extra = [],
    ): int {
        $faker = fake();
        $created = 0;

        for ($i = 1; $i <= $count; $i++) {
            $first = $faker->firstName();
            $last = $faker->lastName();
            $createdAt = $today->copy()->subDays($calendarDaysAgo)->addHours(rand(8, 17))->addMinutes(rand(0, 59));

            $lead = Lead::query()->create(array_merge([
                'company_id' => $company->id,
                'assigned_to' => $assigneeId,
                'name' => "{$first} {$last}",
                'title' => $faker->randomElement(Lead::TITLES),
                'first_name' => $first,
                'last_name' => $last,
                'address' => $faker->streetAddress(),
                'city' => $faker->city(),
                'postal_code' => $faker->postcode(),
                'status' => $status,
                'source' => $faker->randomElement(Lead::SOURCES),
                'customer_type' => Lead::CUSTOMER_TYPE_RESIDENTIAL,
                'residential_type' => $faker->randomElement(Lead::RESIDENTIAL_TYPES),
                'storage_reason' => $faker->randomElement(Lead::STORAGE_REASONS),
                'notes' => self::NOTE_MARKER.' '.$bucket,
            ], $extra));

            $lead->created_at = $createdAt;
            $lead->updated_at = $createdAt;
            $lead->save();

            $suffix = $lead->id.Str::lower(Str::random(4));
            $lead->syncIdentities([
                [
                    'type' => LeadIdentity::TYPE_EMAIL,
                    'value' => "followup.{$suffix}@example.test",
                    'is_primary' => true,
                ],
                [
                    'type' => LeadIdentity::TYPE_PHONE,
                    'value' => '+63917'.str_pad((string) (1000000 + ($lead->id % 8999999)), 7, '0', STR_PAD_LEFT),
                    'label' => 'Mobile',
                    'is_primary' => false,
                ],
            ]);

            $lead->labels()->syncWithoutDetaching(array_map(fn (LeadLabel $label) => $label->id, $labels));
            $created++;
        }

        return $created;
    }

    private function seedTemplates(Company $company, ?int $userId): void
    {
        $body = 'Hi {{first_name}}, this is a day {{follow_up_day}} follow-up from {{company}}.';

        foreach ([
            MessageTemplate::CHANNEL_SMS => 'Follow-up SMS',
            MessageTemplate::CHANNEL_FACEBOOK => 'Follow-up Facebook',
            MessageTemplate::CHANNEL_VIBER => 'Follow-up Viber',
            MessageTemplate::CHANNEL_WHATSAPP => 'Follow-up WhatsApp',
        ] as $channel => $name) {
            MessageTemplate::query()->firstOrCreate(
                [
                    'company_id' => $company->id,
                    'channel' => $channel,
                    'name' => $name,
                ],
                [
                    'created_by' => $userId,
                    'body_text' => $body,
                ]
            );
        }
    }
}
