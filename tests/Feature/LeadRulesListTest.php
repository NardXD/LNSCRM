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

class LeadRulesListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_rules_list_paginates_and_searches_by_name(): void
    {
        [$user, $company] = $this->userWithPermissions(['view_leads']);

        foreach (range(1, 12) as $i) {
            LeadRule::query()->create([
                'company_id' => $company->id,
                'name' => 'Follow-up rule '.$i,
                'priority' => $i,
                'is_active' => true,
                'triggers' => ['inbound_message'],
                'conditions' => [],
                'actions' => [['type' => 'notify_assignee', 'value' => null]],
            ]);
        }

        LeadRule::query()->create([
            'company_id' => $company->id,
            'name' => 'Welcome SMS',
            'priority' => 99,
            'is_active' => true,
            'triggers' => ['inbound_message'],
            'conditions' => [],
            'actions' => [['type' => 'notify_assignee', 'value' => null]],
        ]);

        $page1 = $this->actingAs($user)
            ->getJson('/api/leads/rules?per_page=10&page=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('pagination.total', 13)
            ->json('data');

        $this->assertCount(10, $page1);
        $this->assertSame('Follow-up rule 1', $page1[0]['name']);

        $page2 = $this->actingAs($user)
            ->getJson('/api/leads/rules?per_page=10&page=2')
            ->assertOk()
            ->json('data');

        $this->assertCount(3, $page2);
        $this->assertSame('Welcome SMS', collect($page2)->last()['name']);

        $search = $this->actingAs($user)
            ->getJson('/api/leads/rules?search=welcome&per_page=10')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->json('data');

        $this->assertCount(1, $search);
        $this->assertSame('Welcome SMS', $search[0]['name']);
    }

    /**
     * @param  list<string>  $slugs
     * @return array{0: User, 1: Company}
     */
    private function userWithPermissions(array $slugs): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-rules-list',
            'status' => 'active',
            'email' => 'admin-rules-list@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager-rules-list',
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
            'email' => 'manager-rules-list@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$user, $company];
    }
}
