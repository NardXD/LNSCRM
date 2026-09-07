<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\LeadRule;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LeadRuleToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggling_is_active_succeeds_even_though_global_middleware_merges_extra_request_data(): void
    {
        // IdentifyCompanyBySubdomain is prepended to the whole "web" middleware group and
        // merges a `company` key into every request. updateRule()'s toggle-only fast path
        // must not be fooled by that into falling through to full-payload validation.
        [$user, $company] = $this->userWithPermissions(['view_leads', 'create_lead_rules']);

        $rule = LeadRule::query()->create([
            'company_id' => $company->id,
            'name' => 'Toggle me',
            'priority' => 100,
            'is_active' => true,
            'triggers' => ['inbound_message'],
            'conditions' => [],
            'actions' => [['type' => 'notify_assignee', 'value' => null]],
        ]);

        $response = $this->actingAs($user)->patchJson('/api/leads/rules/'.$rule->id, [
            'is_active' => false,
        ]);

        $response->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertFalse($rule->fresh()->is_active);
    }

    /**
     * @param  list<string>  $slugs
     * @return array{0: User, 1: Company}
     */
    private function userWithPermissions(array $slugs): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-rule-toggle',
            'status' => 'active',
            'email' => 'admin-rule-toggle@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager-rule-toggle',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        foreach ($slugs as $slug) {
            $permission = Permission::query()->create([
                'name' => $slug,
                'slug' => $slug,
                'display_name' => $slug,
                'company_id' => $company->id,
            ]);
            $role->permissions()->attach($permission->id);
        }

        $user = User::query()->create([
            'name' => 'Manager',
            'email' => 'manager-rule-toggle@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$user, $company];
    }
}
