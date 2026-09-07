<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadRule;
use App\Models\Module;
use App\Services\LeadRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateFacebookFollowUpLeadRulesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_all_three_rules_for_companies_with_facebook_enabled(): void
    {
        $company = $this->company('with-fb');
        $this->attachFacebookModule($company);

        $this->artisan('leads:create-facebook-follow-up-rules')->assertSuccessful();

        $names = LeadRule::where('company_id', $company->id)->pluck('name')->all();
        $this->assertEqualsCanonicalizing([
            'Facebook message - notify assignee',
            'Facebook message - wake up snoozed lead',
            'Facebook message mentions pricing/availability',
        ], $names);

        $labelRule = LeadRule::where('company_id', $company->id)
            ->where('name', 'Facebook message mentions pricing/availability')
            ->first();
        $this->assertSame('message', $labelRule->conditions[1]['field']);
        $this->assertSame('contains_any', $labelRule->conditions[1]['operator']);
        $this->assertSame(['quote', 'price', 'cost', 'how much', 'available', 'size'], $labelRule->conditions[1]['value']);
    }

    public function test_keywords_option_can_be_customized_and_blank_skips_the_label_rule(): void
    {
        $company = $this->company('custom-kw');
        $this->attachFacebookModule($company);

        $this->artisan('leads:create-facebook-follow-up-rules', [
            'company_id' => $company->id,
            '--keywords' => '',
        ])->assertSuccessful();

        $names = LeadRule::where('company_id', $company->id)->pluck('name')->all();
        $this->assertEqualsCanonicalizing([
            'Facebook message - notify assignee',
            'Facebook message - wake up snoozed lead',
        ], $names);
    }

    public function test_notify_assignee_rule_does_not_error_for_an_unassigned_lead(): void
    {
        $company = $this->company('notify');
        $this->attachFacebookModule($company);
        $this->artisan('leads:create-facebook-follow-up-rules', ['company_id' => $company->id])->assertSuccessful();

        $lead = Lead::create(['company_id' => $company->id, 'name' => 'Jane', 'status' => 'new', 'source' => 'facebook']);

        $result = app(LeadRuleEngine::class)->apply($lead, 'facebook', [LeadRuleEngine::TRIGGER_INBOUND_MESSAGE], [
            'message' => 'just checking in',
        ]);

        $this->assertSame($lead->id, $result->id);
        $this->assertNull($result->assigned_to);
    }

    public function test_wake_up_snoozed_lead_rule_unsnoozes_on_reply(): void
    {
        $company = $this->company('unsnooze');
        $this->attachFacebookModule($company);
        $this->artisan('leads:create-facebook-follow-up-rules', ['company_id' => $company->id])->assertSuccessful();

        $lead = Lead::create([
            'company_id' => $company->id,
            'name' => 'Snoozed Sam',
            'status' => 'snoozed',
            'source' => 'facebook',
            'reopen_status' => 'contacted',
            'reopen_at' => now()->addDays(5),
        ]);

        $result = app(LeadRuleEngine::class)->apply($lead, 'facebook', [LeadRuleEngine::TRIGGER_INBOUND_MESSAGE], [
            'message' => 'sorry for the late reply, still interested',
        ]);

        $result->refresh();
        $this->assertSame('contacted', $result->status);
        $this->assertNull($result->reopen_at);
    }

    public function test_pricing_label_rule_matches_any_configured_keyword_and_ignores_unrelated_messages(): void
    {
        $company = $this->company('pricing');
        $this->attachFacebookModule($company);
        $this->artisan('leads:create-facebook-follow-up-rules', ['company_id' => $company->id])->assertSuccessful();

        $priced = Lead::create(['company_id' => $company->id, 'name' => 'Price Asker', 'status' => 'new', 'source' => 'facebook']);
        app(LeadRuleEngine::class)->apply($priced, 'facebook', [LeadRuleEngine::TRIGGER_INBOUND_MESSAGE], [
            'message' => 'How much for a 10x10 unit?',
        ]);
        $this->assertTrue($priced->fresh()->labels()->where('name', 'Pricing inquiry')->exists());

        $unrelated = Lead::create(['company_id' => $company->id, 'name' => 'Just Saying Hi', 'status' => 'new', 'source' => 'facebook']);
        app(LeadRuleEngine::class)->apply($unrelated, 'facebook', [LeadRuleEngine::TRIGGER_INBOUND_MESSAGE], [
            'message' => 'hey there, just saying hello',
        ]);
        $this->assertFalse($unrelated->fresh()->labels()->where('name', 'Pricing inquiry')->exists());
    }

    public function test_running_it_twice_skips_unless_forced(): void
    {
        $company = $this->company('idempotent');
        $this->attachFacebookModule($company);

        $this->artisan('leads:create-facebook-follow-up-rules', ['company_id' => $company->id])->assertSuccessful();
        $this->artisan('leads:create-facebook-follow-up-rules', ['company_id' => $company->id])->assertSuccessful();

        $this->assertSame(3, LeadRule::where('company_id', $company->id)->count());

        $this->artisan('leads:create-facebook-follow-up-rules', [
            'company_id' => $company->id,
            '--label' => 'Storage inquiry',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(3, LeadRule::where('company_id', $company->id)->count());
        $labelRule = LeadRule::where('company_id', $company->id)
            ->where('name', 'Facebook message mentions pricing/availability')
            ->first();
        $this->assertSame('Storage inquiry', $labelRule->actions[0]['value']);
    }

    private function company(string $suffix): Company
    {
        return Company::query()->create([
            'name' => 'LNS '.$suffix,
            'subdomain' => 'lns-fb-followup-'.$suffix,
            'status' => 'active',
            'email' => 'admin-followup-'.$suffix.'@lns.test',
        ]);
    }

    private function attachFacebookModule(Company $company): void
    {
        $module = Module::firstOrCreate(
            ['slug' => 'facebook'],
            ['name' => 'Facebook & Instagram', 'route' => 'facebook', 'sort_order' => 11, 'is_active' => true]
        );

        $company->modules()->attach($module->id, ['is_enabled' => true, 'granted_at' => now()]);
    }
}
