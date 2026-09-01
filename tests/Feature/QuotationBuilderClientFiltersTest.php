<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadLabel;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QuotationBuilderClientFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    protected function actingAsQuotationBuilderUser(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-qb-filters',
            'quotation_prefix' => 'LNS',
            'status' => 'active',
            'email' => 'admin@lns-filters.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Admin',
            'slug' => 'admin-qb-filters',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $permission = Permission::query()->create([
            'name' => 'view_quotation_builder',
            'slug' => 'view_quotation_builder',
            'display_name' => 'Quotation Builder',
            'company_id' => $company->id,
        ]);
        $role->permissions()->attach($permission->id);

        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@lns-filters.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);
        $user->roles()->attach($role->id);

        $this->actingAs($user);

        return compact('company', 'user');
    }

    public function test_clients_can_be_filtered_by_label_and_assignee(): void
    {
        ['company' => $company, 'user' => $user] = $this->actingAsQuotationBuilderUser();

        $assignee = User::query()->create([
            'name' => 'Sales Rep',
            'email' => 'sales@lns-filters.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $label = LeadLabel::query()->create([
            'company_id' => $company->id,
            'name' => 'Hot lead',
            'color' => '#ef4444',
        ]);

        $matched = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'status' => 'new',
            'assigned_to' => $assignee->id,
        ]);
        $matched->labels()->attach($label->id);

        Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'John Smith',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'status' => 'new',
            'assigned_to' => $user->id,
        ]);

        $response = $this->getJson('/api/quotation-builder/clients?'.http_build_query([
            'label_ids' => [$label->id],
            'assigned_to' => $assignee->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matched->id)
            ->assertJsonPath('data.0.assignee_name', 'Sales Rep')
            ->assertJsonPath('data.0.labels.0.name', 'Hot lead');
    }

    public function test_clients_can_be_filtered_to_unassigned_leads(): void
    {
        ['company' => $company] = $this->actingAsQuotationBuilderUser();

        $unassigned = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'No Owner',
            'first_name' => 'No',
            'last_name' => 'Owner',
            'status' => 'new',
        ]);

        Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Owned Lead',
            'first_name' => 'Owned',
            'last_name' => 'Lead',
            'status' => 'new',
            'assigned_to' => User::query()->create([
                'name' => 'Owner',
                'email' => 'owner@lns-filters.test',
                'password' => Hash::make('password'),
                'company_id' => $company->id,
                'status' => 'active',
            ])->id,
        ]);

        $this->getJson('/api/quotation-builder/clients?assigned_to=__none__')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unassigned->id);
    }

    public function test_client_filter_options_returns_labels_and_assignees(): void
    {
        ['company' => $company] = $this->actingAsQuotationBuilderUser();

        LeadLabel::query()->create([
            'company_id' => $company->id,
            'name' => 'VIP',
            'color' => '#2563eb',
        ]);

        $this->getJson('/api/quotation-builder/client-filters')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['name' => 'VIP'])
            ->assertJsonFragment(['name' => 'Admin']);
    }
}
