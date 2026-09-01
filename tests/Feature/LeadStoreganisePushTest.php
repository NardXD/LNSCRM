<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadIdentity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StoreganiseIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadStoreganisePushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    protected function storeganisePayloadAssertions(array $data): bool
    {
        return ($data['email'] ?? null) === 'jane@example.com'
            && ($data['siteId'] ?? null) === 'site-1'
            && ($data['companyName'] ?? null) === 'Loc & Stor'
            && is_array($data['customFields'] ?? null)
            && ($data['customFields']['lns_mrms'] ?? null) === 'Ms'
            && ($data['customFields']['lns_city'] ?? null) === 'Pasay'
            && ($data['customFields']['lns_postal'] ?? null) === '1300'
            && ($data['customFields']['lns_hearAbout'] ?? null) === 'Facebook'
            && ($data['customFields']['lns_customerType'] ?? null) === 'Residential'
            && ($data['customFields']['lns_residentialType'] ?? null) === 'Condominium'
            && ($data['customFields']['lns_residentialReason'] ?? null) === 'Moving'
            && ($data['customFields']['lns_siteCode'] ?? null) === 'nwp';
    }

    protected function actingAsLeadViewer(): array
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-sg',
            'status' => 'active',
            'email' => 'admin-sg@lns.test',
        ]);
        $role = Role::query()->create([
            'name' => 'Manager',
            'slug' => 'manager-sg',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $permission = Permission::query()->create([
            'name' => 'view_leads',
            'slug' => 'view_leads',
            'display_name' => 'View Leads',
            'company_id' => $company->id,
        ]);
        $role->permissions()->attach($permission->id);
        $user = User::query()->create([
            'name' => 'Manager',
            'email' => 'manager-sg@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        StoreganiseIntegration::query()->create([
            'company_id' => $company->id,
            'business_code' => 'demo',
            'api_key' => Crypt::encryptString('test-api-key'),
            'is_active' => true,
        ]);

        $this->actingAs($user);

        return [$company, $user];
    }

    public function test_push_lead_creates_storeganise_user_for_selected_facility(): void
    {
        [$company] = $this->actingAsLeadViewer();

        Http::fake([
            'https://demo.storeganise.com/api/v1/admin/sites/site-1' => Http::response([
                'id' => 'site-1',
                'code' => 'nwp',
                'name' => 'Makati',
            ]),
            'https://demo.storeganise.com/api/v1/admin/users/jane%40example.com' => Http::response([], 404),
            'https://demo.storeganise.com/api/v1/admin/users' => Http::response([
                'id' => 'sg-user-1',
                'email' => 'jane@example.com',
            ], 201),
        ]);

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'title' => 'Ms',
            'city' => 'Pasay',
            'postal_code' => '1300',
            'source' => 'Facebook',
            'customer_type' => Lead::CUSTOMER_TYPE_RESIDENTIAL,
            'residential_type' => 'Condominium',
            'storage_reason' => 'Moving',
            'company_name' => 'Loc & Stor',
            'status' => 'new',
        ]);
        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'jane@example.com');

        $response = $this->postJson('/api/leads/'.$lead->id.'/storeganise/push', [
            'site_id' => 'site-1',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.storeganise_user_id', 'sg-user-1')
            ->assertJsonPath('data.storeganise_site_id', 'site-1');

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'storeganise_user_id' => 'sg-user-1',
            'storeganise_site_id' => 'site-1',
        ]);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST' || ! str_contains($request->url(), '/api/v1/admin/users')) {
                return false;
            }

            return $this->storeganisePayloadAssertions($request->data());
        });
    }

    public function test_push_lead_blocks_when_storeganise_user_already_exists(): void
    {
        [$company] = $this->actingAsLeadViewer();

        Http::fake([
            'https://demo.storeganise.com/api/v1/admin/sites/site-1' => Http::response([
                'id' => 'site-1',
                'code' => 'nwp',
                'name' => 'Makati',
            ]),
            'https://demo.storeganise.com/api/v1/admin/users/jane%40example.com' => Http::response([
                'id' => 'sg-existing',
                'email' => 'jane@example.com',
                'firstName' => 'Jane',
                'lastName' => 'Existing',
                'siteId' => 'site-1',
            ]),
        ]);

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'status' => 'new',
        ]);
        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'jane@example.com');

        $response = $this->postJson('/api/leads/'.$lead->id.'/storeganise/push', [
            'site_id' => 'site-1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_status_returns_update_when_storeganise_user_exists(): void
    {
        [$company] = $this->actingAsLeadViewer();

        Http::fake([
            'https://demo.storeganise.com/api/v1/admin/users/jane%40example.com' => Http::response([
                'id' => 'sg-existing',
                'email' => 'jane@example.com',
                'siteId' => 'site-1',
            ]),
        ]);

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'status' => 'new',
        ]);
        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'jane@example.com');

        $response = $this->getJson('/api/leads/'.$lead->id.'/storeganise/status?site_id=site-1');

        $response->assertOk()
            ->assertJsonPath('action', 'update')
            ->assertJsonPath('exists', true)
            ->assertJsonPath('user_id', 'sg-existing');
    }

    public function test_status_returns_push_when_no_storeganise_user_exists(): void
    {
        [$company] = $this->actingAsLeadViewer();

        Http::fake([
            'https://demo.storeganise.com/api/v1/admin/users/jane%40example.com' => Http::response([], 404),
            'https://demo.storeganise.com/api/v1/admin/users*' => Http::response([]),
        ]);

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'status' => 'new',
        ]);
        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'jane@example.com');

        $response = $this->getJson('/api/leads/'.$lead->id.'/storeganise/status?site_id=site-1');

        $response->assertOk()
            ->assertJsonPath('action', 'push')
            ->assertJsonPath('exists', false);
    }

    public function test_push_lead_can_link_existing_storeganise_user(): void
    {
        [$company] = $this->actingAsLeadViewer();

        Http::fake([
            'https://demo.storeganise.com/api/v1/admin/sites/site-1' => Http::response([
                'id' => 'site-1',
                'code' => 'nwp',
                'name' => 'Makati',
            ]),
            'https://demo.storeganise.com/api/v1/admin/users/sg-existing' => Http::response([
                'id' => 'sg-existing',
                'email' => 'jane@example.com',
            ]),
        ]);

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'status' => 'new',
        ]);
        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'jane@example.com');

        $response = $this->postJson('/api/leads/'.$lead->id.'/storeganise/push', [
            'site_id' => 'site-1',
            'link_user_id' => 'sg-existing',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.storeganise_user_id', 'sg-existing');

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'action' => 'storeganise_push',
        ]);
    }

    public function test_update_lead_syncs_existing_storeganise_user(): void
    {
        [$company] = $this->actingAsLeadViewer();

        Http::fake([
            'https://demo.storeganise.com/api/v1/admin/sites/site-1' => Http::response([
                'id' => 'site-1',
                'code' => 'nwp',
                'name' => 'Makati',
            ]),
            'https://demo.storeganise.com/api/v1/admin/users/sg-user-1' => Http::response([
                'id' => 'sg-user-1',
                'email' => 'jane@example.com',
            ]),
        ]);

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'status' => 'new',
            'storeganise_site_id' => 'site-1',
            'storeganise_user_id' => 'sg-user-1',
            'storeganise_pushed_at' => now(),
        ]);
        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'jane@example.com');

        $response = $this->postJson('/api/leads/'.$lead->id.'/storeganise/update', [
            'site_id' => 'site-1',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'action' => 'storeganise_update',
        ]);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), '/api/v1/admin/users/sg-user-1');
        });
    }

    public function test_duplicates_endpoint_returns_email_matches(): void
    {
        [$company] = $this->actingAsLeadViewer();

        Http::fake([
            'https://demo.storeganise.com/api/v1/admin/users/jane%40example.com' => Http::response([
                'id' => 'sg-existing',
                'email' => 'jane@example.com',
                'firstName' => 'Jane',
                'lastName' => 'Existing',
            ]),
        ]);

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'Jane Doe',
            'status' => 'new',
        ]);
        $lead->addIdentity(LeadIdentity::TYPE_EMAIL, 'jane@example.com');

        $response = $this->getJson('/api/leads/'.$lead->id.'/storeganise/duplicates');

        $response->assertOk()
            ->assertJsonPath('duplicates.0.id', 'sg-existing')
            ->assertJsonPath('duplicates.0.match_types.0', 'email');
    }

    public function test_push_lead_requires_email(): void
    {
        [$company] = $this->actingAsLeadViewer();

        $lead = Lead::query()->create([
            'company_id' => $company->id,
            'name' => 'No Email Lead',
            'status' => 'new',
        ]);

        $response = $this->postJson('/api/leads/'.$lead->id.'/storeganise/push', [
            'site_id' => 'site-1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
