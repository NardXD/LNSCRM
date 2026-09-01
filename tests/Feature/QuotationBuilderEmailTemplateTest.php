<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Quote\QuotationBuilderEmailTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QuotationBuilderEmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_renders_placeholders_in_subject_and_body(): void
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-template',
            'quotation_prefix' => 'LNS',
            'status' => 'active',
            'email' => 'admin@lns.test',
        ]);

        $service = app(QuotationBuilderEmailTemplateService::class);
        $service->saveTemplate($company->id, 'Quote for {{customer_name}} at {{facility}}', '<p>Total: PHP {{total_due}}</p>');

        $rendered = $service->renderForQuote($company->id, [
            'tenant' => ['first_name' => 'Jane', 'last_name' => 'Doe', 'email' => 'jane@example.com'],
            'facility_label' => 'Makati',
            'totals' => ['storage_fee' => 5000, 'total_due' => 6500],
            'terms' => [],
        ], 'Loc & Stor');

        $this->assertSame('Quote for Jane Doe at Makati', $rendered['subject']);
        $this->assertSame('<p>Total: PHP 6,500.00</p>', $rendered['body']);
    }

    public function test_sample_preview_context_uses_formatted_quote_values(): void
    {
        $service = app(QuotationBuilderEmailTemplateService::class);
        $context = $service->samplePreviewContext('Loc & Stor');

        $this->assertSame('Jane Doe', $context['customer_name']);
        $this->assertSame('jane.doe@example.com', $context['customer_email']);
        $this->assertSame('Loc&Stor Makati', $context['facility']);
        $this->assertSame('5,000.00', $context['storage_fee']);
        $this->assertSame('6,500.00', $context['total_due']);
        $this->assertSame('Sep 1, 2026', $context['start_date']);
        $this->assertSame('Aug 31, 2027', $context['end_date']);
        $this->assertSame('Loc & Stor', $context['company_name']);
    }

    public function test_email_template_store_api_saves_subject_and_body(): void
    {
        $company = Company::query()->create([
            'name' => 'Loc & Stor',
            'subdomain' => 'lns-template-store',
            'quotation_prefix' => 'LNS',
            'status' => 'active',
            'email' => 'staff@lns-store.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-template-store',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $permission = Permission::query()->create([
            'name' => 'view_quotation_builder_email_template',
            'slug' => 'view_quotation_builder_email_template',
            'display_name' => 'Email Template',
            'company_id' => $company->id,
        ]);
        $role->permissions()->attach($permission->id);

        $user = User::query()->create([
            'name' => 'Staff',
            'email' => 'staff@lns-store.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
        ]);
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->postJson('/api/quotation-builder/email-template', [
                'subject' => 'Quote for {{facility}}',
                'body' => '<p>Hello {{customer_name}}</p>',
            ])
            ->assertOk()
            ->assertJsonPath('template.subject', 'Quote for {{facility}}')
            ->assertJsonPath('template.body', '<p>Hello {{customer_name}}</p>');
    }

    public function test_email_template_api_requires_email_template_permission(): void
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-template-api',
            'quotation_prefix' => 'LNS',
            'status' => 'active',
            'email' => 'staff@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-template',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $permission = Permission::query()->create([
            'name' => 'view_quotation_builder_email_template',
            'slug' => 'view_quotation_builder_email_template',
            'display_name' => 'Email Template',
            'company_id' => $company->id,
        ]);
        $role->permissions()->attach($permission->id);

        $user = User::query()->create([
            'name' => 'Staff',
            'email' => 'staff@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
        ]);
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->getJson('/api/quotation-builder/email-template')
            ->assertOk()
            ->assertJsonStructure(['template' => ['subject', 'body'], 'placeholders']);
    }

    public function test_email_template_api_denied_without_email_template_permission(): void
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-template-denied',
            'quotation_prefix' => 'LNS',
            'status' => 'active',
            'email' => 'builder@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Builder Only',
            'slug' => 'builder-only',
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
            'name' => 'Builder',
            'email' => 'builder@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
        ]);
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->getJson('/api/quotation-builder/email-template')
            ->assertForbidden();
    }
}
