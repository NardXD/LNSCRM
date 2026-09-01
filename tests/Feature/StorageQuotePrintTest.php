<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StorageQuotePrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    protected function actingAsQuotationBuilderUser(): User
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns',
            'quotation_prefix' => 'LNS',
            'status' => 'active',
            'email' => 'admin@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Admin',
            'slug' => 'admin-sq',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $permission = Permission::query()->create([
            'name' => 'View Quotation Builder',
            'slug' => 'view_quotation_builder',
            'display_name' => 'View Quotation Builder',
            'company_id' => $company->id,
        ]);
        $role->permissions()->attach($permission->id);

        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($user);

        return $user;
    }

    public function test_print_returns_pdf_not_company_json(): void
    {
        $this->actingAsQuotationBuilderUser();

        $response = $this->post('/api/quotation-builder/storage-quotes/print', [
            'tenant_company' => 'Acme Corp',
            'fname' => 'Jane',
            'lname' => 'Doe',
            'email' => 'jane@example.com',
            'lo_code' => 'L001',
            'start_date' => '2026-09-01',
            'initial_period_hdn' => 1,
            'unit1_print_hdn' => 'A101',
            'unit1_price_hdn' => 5000,
            'total_storage_fee_final' => 5000,
            'total_final_hdn' => 5000,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringNotContainsString('"subdomain"', $response->getContent());
    }

    public function test_print_get_is_not_allowed(): void
    {
        $this->actingAsQuotationBuilderUser();

        $response = $this->get('/api/quotation-builder/storage-quotes/print');

        $response->assertStatus(405);
    }

    public function test_print_ignores_merged_company_model_when_tenant_company_is_posted(): void
    {
        $this->actingAsQuotationBuilderUser();

        $response = $this->post('/api/quotation-builder/storage-quotes/print', [
            'tenant_company' => 'Tenant Business LLC',
            'company' => Company::query()->first(),
            'fname' => 'Jane',
            'lname' => 'Doe',
            'email' => 'jane@example.com',
            'lo_code' => 'L001',
            'start_date' => '2026-09-01',
            'initial_period_hdn' => 1,
            'unit1_print_hdn' => 'A101',
            'unit1_price_hdn' => 5000,
            'total_storage_fee_final' => 5000,
            'total_final_hdn' => 5000,
        ]);

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringNotContainsString('"quotation_prefix"', $response->getContent());
    }
}
