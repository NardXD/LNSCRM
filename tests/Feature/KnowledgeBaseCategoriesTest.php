<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KnowledgeBaseCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private const ALL = ['view_knowledge_base', 'create_knowledge_base', 'edit_knowledge_base', 'delete_knowledge_base'];

    public function test_creates_nested_categories_and_articles_with_full_paths(): void
    {
        $user = $this->userWithPermissions(self::ALL);

        $billing = $this->actingAs($user)
            ->postJson('/api/knowledge-base/categories', ['type' => 'article', 'name' => 'Billing'])
            ->assertOk()
            ->json('category');

        $refunds = $this->actingAs($user)
            ->postJson('/api/knowledge-base/categories', ['type' => 'article', 'name' => 'Refunds', 'parent_id' => $billing['id']])
            ->assertOk()
            ->assertJsonPath('category.parent_id', $billing['id'])
            ->json('category');

        $article = $this->actingAs($user)
            ->postJson('/api/knowledge-base/articles', [
                'title' => 'How to refund',
                'content' => '<p>Go to <b>Billing</b> &amp; click refund.</p>',
                'category_id' => $refunds['id'],
                'visibility' => 'published',
            ])
            ->assertOk()
            ->assertJsonPath('article.category_id', $refunds['id'])
            ->assertJsonPath('article.category', 'Billing › Refunds')
            ->json('article');

        $this->assertSame('Go to Billing &amp; click refund.', $article['excerpt']);

        $this->actingAs($user)
            ->getJson('/api/knowledge-base/bootstrap')
            ->assertOk()
            ->assertJsonPath('categories.1.path', 'Billing › Refunds')
            ->assertJsonPath('articles.0.title', 'How to refund');
    }

    public function test_same_name_is_allowed_under_different_parents(): void
    {
        $user = $this->userWithPermissions(self::ALL);
        $a = $this->category($user, 'Product A');
        $b = $this->category($user, 'Product B');

        $setupA = $this->actingAs($user)->postJson('/api/knowledge-base/categories', ['type' => 'article', 'name' => 'Setup', 'parent_id' => $a->id])->assertOk()->json('category');
        $setupB = $this->actingAs($user)->postJson('/api/knowledge-base/categories', ['type' => 'article', 'name' => 'Setup', 'parent_id' => $b->id])->assertOk()->json('category');
        $again = $this->actingAs($user)->postJson('/api/knowledge-base/categories', ['type' => 'article', 'name' => 'setup', 'parent_id' => $a->id])->assertOk()->json('category');

        $this->assertNotSame($setupA['id'], $setupB['id']);
        $this->assertNotSame($setupA['slug'], $setupB['slug']);
        $this->assertSame($setupA['id'], $again['id']);
    }

    public function test_rename_and_move_updates_article_paths_without_touching_updated_at(): void
    {
        $user = $this->userWithPermissions(self::ALL);
        $billing = $this->category($user, 'Billing');
        $refunds = $this->category($user, 'Refunds', $billing->id);
        $support = $this->category($user, 'Support');
        $article = $this->article($user, $refunds, 'Billing › Refunds');
        $article->forceFill(['updated_at' => now()->subYear()])->saveQuietly();
        $rawUpdatedAt = fn () => DB::table('knowledge_base_articles')->where('id', $article->id)->value('updated_at');
        $originalUpdatedAt = $rawUpdatedAt();

        $this->actingAs($user)
            ->putJson("/api/knowledge-base/categories/{$refunds->id}", ['name' => 'Returns', 'parent_id' => $support->id])
            ->assertOk();

        $article->refresh();
        $this->assertSame('Support › Returns', $article->category);
        $this->assertSame($originalUpdatedAt, $rawUpdatedAt());
        $this->assertSame($support->id, $refunds->fresh()->parent_id);
    }

    public function test_cannot_move_category_inside_its_own_subtree(): void
    {
        $user = $this->userWithPermissions(self::ALL);
        $parent = $this->category($user, 'Parent');
        $child = $this->category($user, 'Child', $parent->id);
        $grandchild = $this->category($user, 'Grandchild', $child->id);

        $this->actingAs($user)
            ->putJson("/api/knowledge-base/categories/{$parent->id}", ['name' => 'Parent', 'parent_id' => $grandchild->id])
            ->assertStatus(422);

        $this->actingAs($user)
            ->putJson("/api/knowledge-base/categories/{$parent->id}", ['name' => 'Parent', 'parent_id' => $parent->id])
            ->assertStatus(422);

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_rename_rejects_duplicate_sibling_name(): void
    {
        $user = $this->userWithPermissions(self::ALL);
        $this->category($user, 'Billing');
        $other = $this->category($user, 'Support');

        $this->actingAs($user)
            ->putJson("/api/knowledge-base/categories/{$other->id}", ['name' => 'billing', 'parent_id' => null])
            ->assertStatus(422);
    }

    public function test_move_reorders_siblings(): void
    {
        $user = $this->userWithPermissions(self::ALL);
        $first = $this->category($user, 'First');
        $second = $this->category($user, 'Second');
        $third = $this->category($user, 'Third');

        $names = $this->actingAs($user)
            ->postJson("/api/knowledge-base/categories/{$third->id}/move", ['direction' => 'up'])
            ->assertOk()
            ->json('categories.*.name');
        $this->assertSame(['First', 'Third', 'Second'], $names);

        $names = $this->actingAs($user)
            ->postJson("/api/knowledge-base/categories/{$first->id}/move", ['direction' => 'up'])
            ->assertOk()
            ->json('categories.*.name');
        $this->assertSame(['First', 'Third', 'Second'], $names);

        $names = $this->actingAs($user)
            ->postJson("/api/knowledge-base/categories/{$first->id}/move", ['direction' => 'down'])
            ->assertOk()
            ->json('categories.*.name');
        $this->assertSame(['Third', 'First', 'Second'], $names);
    }

    public function test_delete_moves_articles_and_subcategories_to_parent(): void
    {
        $user = $this->userWithPermissions(self::ALL);
        $billing = $this->category($user, 'Billing');
        $refunds = $this->category($user, 'Refunds', $billing->id);
        $partial = $this->category($user, 'Partial', $refunds->id);
        $article = $this->article($user, $refunds, 'Billing › Refunds');
        $nested = $this->article($user, $partial, 'Billing › Refunds › Partial');

        $this->actingAs($user)
            ->deleteJson("/api/knowledge-base/categories/{$refunds->id}")
            ->assertOk();

        $this->assertNull(KnowledgeBaseCategory::query()->find($refunds->id));
        $this->assertSame($billing->id, $partial->fresh()->parent_id);
        $this->assertSame($billing->id, $article->fresh()->category_id);
        $this->assertSame('Billing', $article->fresh()->category);
        $this->assertSame('Billing › Partial', $nested->fresh()->category);
        $this->assertSame(2, KnowledgeBaseArticle::query()->count());

        $this->actingAs($user)->deleteJson("/api/knowledge-base/categories/{$billing->id}")->assertOk();

        $this->assertNull($article->fresh()->category_id);
        $this->assertNull($article->fresh()->category);
        $this->assertNull($partial->fresh()->parent_id);
    }

    public function test_article_rejects_category_from_another_company(): void
    {
        $user = $this->userWithPermissions(self::ALL);
        $otherUser = $this->userWithPermissions(self::ALL);
        $foreign = $this->category($otherUser, 'Theirs');

        $this->actingAs($user)
            ->postJson('/api/knowledge-base/articles', [
                'title' => 'Mine',
                'content' => '<p>x</p>',
                'category_id' => $foreign->id,
                'visibility' => 'draft',
            ])
            ->assertStatus(422);

        $this->actingAs($user)
            ->putJson("/api/knowledge-base/categories/{$foreign->id}", ['name' => 'Hijack', 'parent_id' => null])
            ->assertNotFound();
    }

    public function test_category_changes_require_permissions(): void
    {
        $viewer = $this->userWithPermissions(['view_knowledge_base']);
        $category = $this->category($viewer, 'Billing');

        $this->actingAs($viewer)->postJson('/api/knowledge-base/categories', ['type' => 'article', 'name' => 'New'])->assertForbidden();
        $this->actingAs($viewer)->putJson("/api/knowledge-base/categories/{$category->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->postJson("/api/knowledge-base/categories/{$category->id}/move", ['direction' => 'up'])->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/api/knowledge-base/categories/{$category->id}")->assertForbidden();
        $this->assertNotNull($category->fresh());
    }

    public function test_page_renders_three_pane_layout(): void
    {
        $user = $this->userWithPermissions(self::ALL);

        $this->actingAs($user)
            ->get('/knowledge-base')
            ->assertOk()
            ->assertSee('kb-shell', false)
            ->assertSee('id="kbTree"', false)
            ->assertSee('id="kbArticleList"', false)
            ->assertSee('id="kbEditor"', false)
            ->assertSee('data-action="new-category"', false);
    }

    private function category(User $user, string $name, ?int $parentId = null): KnowledgeBaseCategory
    {
        return KnowledgeBaseCategory::query()->create([
            'company_id' => $user->company_id,
            'type' => 'article',
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => KnowledgeBaseCategory::uniqueSlug($user->company_id, 'article', $name),
            'sort_order' => KnowledgeBaseCategory::nextSortOrder($user->company_id, 'article', $parentId),
        ]);
    }

    private function article(User $user, KnowledgeBaseCategory $category, string $path): KnowledgeBaseArticle
    {
        return KnowledgeBaseArticle::query()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'category' => $path,
            'title' => 'Article in '.$category->name,
            'excerpt' => 'Excerpt',
            'content' => '<p>Body</p>',
            'visibility' => 'published',
        ]);
    }

    /**
     * @param  list<string>  $slugs
     */
    private function userWithPermissions(array $slugs): User
    {
        $suffix = uniqid();
        $company = Company::query()->create([
            'name' => 'LNS',
            'subdomain' => 'lns-kb-'.$suffix,
            'status' => 'active',
            'email' => 'admin-kb-'.$suffix.'@lns.test',
        ]);

        $role = Role::query()->create([
            'name' => 'Staff',
            'slug' => 'staff-kb-'.$suffix,
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

        return User::query()->create([
            'name' => 'KB User',
            'email' => 'kb-'.$suffix.'@lns.test',
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }
}
