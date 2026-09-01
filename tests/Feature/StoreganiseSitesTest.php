<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StoreganiseIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StoreganiseSitesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    protected function actingAsLeadViewer(): Company
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

        return $company;
    }

    public function test_sites_endpoint_returns_localized_facility_names(): void
    {
        $this->actingAsLeadViewer();

        Http::fake([
            'https://demo.storeganise.com/api/v1/admin/sites*' => Http::response([
                [
                    'id' => 'site-2',
                    'code' => 'L002',
                    'title' => ['en' => 'Urban Makati', 'de' => 'Urban'],
                ],
                [
                    'id' => 'site-1',
                    'code' => 'L001',
                    'title' => ['en' => 'Pasig'],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/integrations/storeganise/sites');

        $response->assertOk()
            ->assertJsonPath('sites.0.id', 'site-1')
            ->assertJsonPath('sites.0.name', 'Pasig')
            ->assertJsonPath('sites.0.code', 'L001')
            ->assertJsonPath('sites.1.id', 'site-2')
            ->assertJsonPath('sites.1.name', 'Urban Makati');
    }
}
