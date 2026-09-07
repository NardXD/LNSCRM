<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadRoundRobinState;
use App\Models\LeadRule;
use App\Models\Module;
use App\Models\User;
use App\Services\LeadRuleEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateFacebookLeadRoundRobinRuleCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_rule_only_for_companies_with_facebook_enabled(): void
    {
        $withFacebook = $this->company('with-fb');
        $this->attachFacebookModule($withFacebook, enabled: true);

        $withoutFacebook = $this->company('without-fb');

        $facebookDisabled = $this->company('fb-disabled');
        $this->attachFacebookModule($facebookDisabled, enabled: false);

        $this->artisan('leads:create-facebook-round-robin-rule')->assertSuccessful();

        $this->assertSame(1, LeadRule::where('company_id', $withFacebook->id)->count());
        $this->assertSame(0, LeadRule::where('company_id', $withoutFacebook->id)->count());
        $this->assertSame(0, LeadRule::where('company_id', $facebookDisabled->id)->count());

        $rule = LeadRule::where('company_id', $withFacebook->id)->first();
        $this->assertTrue($rule->is_active);
        $this->assertSame([LeadRuleEngine::TRIGGER_INBOUND_MESSAGE_NEW], $rule->triggers);
        $this->assertSame([
            ['field' => 'channel', 'operator' => 'in', 'value' => ['facebook']],
        ], $rule->conditions);
        $this->assertSame('create_lead', $rule->actions[0]['type']);
        $this->assertSame('assign', $rule->actions[1]['type']);
        $this->assertSame(LeadRuleEngine::ASSIGN_ROUND_ROBIN, $rule->actions[1]['value']);
    }

    public function test_running_it_twice_skips_unless_forced(): void
    {
        $company = $this->company('idempotent');
        $this->attachFacebookModule($company, enabled: true);

        $this->artisan('leads:create-facebook-round-robin-rule', ['company_id' => $company->id])->assertSuccessful();
        $this->artisan('leads:create-facebook-round-robin-rule', ['company_id' => $company->id])->assertSuccessful();

        $this->assertSame(1, LeadRule::where('company_id', $company->id)->count());

        $this->artisan('leads:create-facebook-round-robin-rule', [
            'company_id' => $company->id,
            '--priority' => 50,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(1, LeadRule::where('company_id', $company->id)->count());
        $this->assertSame(50, LeadRule::where('company_id', $company->id)->first()->priority);
    }

    public function test_the_generated_rule_creates_a_lead_and_round_robins_it_for_a_new_facebook_conversation(): void
    {
        $company = $this->company('functional');
        $this->attachFacebookModule($company, enabled: true);

        $agentA = $this->teammate($company, 'agent-a@lns.test');
        $agentB = $this->teammate($company, 'agent-b@lns.test');

        $this->artisan('leads:create-facebook-round-robin-rule', ['company_id' => $company->id])->assertSuccessful();

        $engine = app(LeadRuleEngine::class);
        $lead = $engine->apply(
            null,
            'facebook',
            LeadRuleEngine::inboundTriggers(true),
            ['contact_name' => 'Jane Customer', 'facebook_name' => 'Jane Customer', 'message' => 'Hi, do you have storage units available?']
        );

        $this->assertInstanceOf(Lead::class, $lead);
        $this->assertSame($company->id, $lead->company_id);
        $this->assertNotNull($lead->assigned_to);
        $this->assertContains((int) $lead->assigned_to, [$agentA->id, $agentB->id]);

        // A second, unrelated new conversation should round-robin to the other teammate.
        $secondLead = $engine->apply(
            null,
            'facebook',
            LeadRuleEngine::inboundTriggers(true),
            ['contact_name' => 'John Other', 'facebook_name' => 'John Other', 'message' => 'What are your prices?']
        );

        $this->assertNotSame($lead->assigned_to, $secondLead->assigned_to);
        $this->assertSame(1, LeadRoundRobinState::where('company_id', $company->id)->count());
    }

    private function company(string $suffix): Company
    {
        return Company::query()->create([
            'name' => 'LNS '.$suffix,
            'subdomain' => 'lns-fb-rr-'.$suffix,
            'status' => 'active',
            'email' => 'admin-'.$suffix.'@lns.test',
        ]);
    }

    private function attachFacebookModule(Company $company, bool $enabled): void
    {
        $module = Module::firstOrCreate(
            ['slug' => 'facebook'],
            ['name' => 'Facebook & Instagram', 'route' => 'facebook', 'sort_order' => 11, 'is_active' => true]
        );

        $company->modules()->attach($module->id, ['is_enabled' => $enabled, 'granted_at' => now()]);
    }

    private function teammate(Company $company, string $email): User
    {
        return User::query()->create([
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);
    }
}
