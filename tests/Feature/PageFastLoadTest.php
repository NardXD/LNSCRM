<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\KnowledgeBaseArticle;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PageFastLoadTest extends TestCase
{
    use RefreshDatabase;

    public function test_knowledge_base_page_is_a_shell_and_bootstrap_returns_articles(): void
    {
        [$user] = $this->userWithPermissions(['view_knowledge_base']);

        KnowledgeBaseArticle::query()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'title' => 'Secret KB Article Title XYZ',
            'excerpt' => 'Excerpt for lazy load test',
            'content' => 'Body for lazy load test',
            'visibility' => 'internal',
        ]);

        $page = $this->actingAs($user)->get('/knowledge-base');
        $page->assertOk();
        $page->assertSee('Knowledge Base', false);
        $page->assertSee('page-skel-grid-card', false);
        $page->assertDontSee('Secret KB Article Title XYZ', false);

        $this->actingAs($user)
            ->getJson('/api/knowledge-base/bootstrap')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['title' => 'Secret KB Article Title XYZ']);
    }

    public function test_user_management_page_does_not_embed_roles_and_roles_api_does(): void
    {
        [$user, $role] = $this->userWithPermissions([
            'view_user_management',
            'view_user_roles_permissions',
        ]);

        $secretPermission = Permission::query()->create([
            'name' => 'secret_audit_permission',
            'slug' => 'secret_audit_permission',
            'display_name' => 'Secret Permission XYZ',
            'company_id' => $user->company_id,
            'category' => 'main',
        ]);
        $role->permissions()->attach($secretPermission->id);
        $role->update([
            'name' => 'Secret Audit Role',
            'description' => 'Hidden until the roles API loads',
        ]);

        $page = $this->actingAs($user)->get('/user-management');
        $page->assertOk();
        $page->assertSee('User & Access Management', false);
        $page->assertSee('page-skel-role-card', false);
        $page->assertDontSee('Secret Permission XYZ', false);
        $page->assertDontSee('Secret Audit Role', false);
        $page->assertDontSee('Hidden until the roles API loads', false);

        $roles = $this->actingAs($user)
            ->getJson('/api/user-management/roles')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Secret Audit Role']);
        $this->assertStringContainsString('Secret Permission XYZ', $roles->getContent());
    }

    public function test_tickets_and_billing_pages_render_skeletons_without_lists(): void
    {
        [$user] = $this->userWithPermissions(['view_tickets', 'view_billing']);

        $this->actingAs($user)->get('/tickets')->assertOk()->assertSee('page-skel-table-row', false);
        $this->actingAs($user)->get('/billing')->assertOk()->assertSee('page-skel-table-row', false);
    }

    /**
     * @param  list<string>  $slugs
     * @return array{0: User, 1: Role}
     */
    private function userWithPermissions(array $slugs): array
    {
        $suffix = uniqid();
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-fast-'.$suffix,
            'status' => 'active',
            'email' => 'admin-fast-'.$suffix.'@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-fast-'.$suffix,
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        foreach ($slugs as $slug) {
            $permission = Permission::query()->create([
                'name' => $slug,
                'slug' => $slug,
                'display_name' => ucwords(str_replace('_', ' ', $slug)),
                'company_id' => $company->id,
                'category' => 'main',
            ]);
            $role->permissions()->attach($permission->id);
        }

        $user = User::query()->create([
            'name' => 'Fast Load User',
            'email' => 'fast-'.$suffix.'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$user, $role];
    }
}
