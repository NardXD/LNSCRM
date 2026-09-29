<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\InboxTemplate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InboxTemplateUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_a_template_used_sets_last_used_at(): void
    {
        $user = $this->agent();
        $template = InboxTemplate::query()->create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'name' => 'Quote',
            'body_html' => '<p>Hello</p>',
            'body_text' => 'Hello',
        ]);
        $this->assertNull($template->last_used_at);

        $response = $this->actingAs($user)
            ->postJson('/api/inbox/templates/'.$template->id.'/use')
            ->assertOk();

        $this->assertNotNull($response->json('last_used_at'));
        $this->assertNotNull($template->fresh()->last_used_at);

        $this->actingAs($user)
            ->getJson('/api/inbox/composer-tools')
            ->assertOk()
            ->assertJsonPath('templates.0.id', $template->id)
            ->assertJsonPath('templates.0.last_used_at', fn ($value) => $value !== null);
    }

    public function test_cannot_mark_another_companys_template_used(): void
    {
        $user = $this->agent();
        $otherCompany = Company::query()->create([
            'name' => 'Other Co',
            'subdomain' => 'other-co-template-usage',
            'status' => 'active',
            'email' => 'admin-other-co-template-usage@lns.test',
        ]);
        $template = InboxTemplate::query()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Not mine',
            'body_html' => '<p>Hello</p>',
            'body_text' => 'Hello',
        ]);

        $this->actingAs($user)
            ->postJson('/api/inbox/templates/'.$template->id.'/use')
            ->assertForbidden();

        $this->assertNull($template->fresh()->last_used_at);
    }

    private function agent(): User
    {
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-inbox-template-usage',
            'status' => 'active',
            'email' => 'admin-inbox-template-usage@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-inbox-template-usage',
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        $permission = Permission::query()->create([
            'name' => 'view_inbox',
            'slug' => 'view_inbox',
            'display_name' => 'View Inbox',
            'company_id' => $company->id,
        ]);
        $role->permissions()->attach($permission->id);

        return User::query()->create([
            'name' => 'Template User',
            'email' => 'template-usage@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }
}
