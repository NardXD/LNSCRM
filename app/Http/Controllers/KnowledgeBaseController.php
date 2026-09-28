<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKnowledgeBaseArticleRequest;
use App\Http\Requests\StoreKnowledgeBaseCategoryRequest;
use App\Http\Requests\StoreKnowledgeBaseFaqRequest;
use App\Http\Requests\StoreKnowledgeBaseGuideRequest;
use App\Http\Requests\UpdateKnowledgeBaseCategoryRequest;
use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseCategory;
use App\Models\KnowledgeBaseFaq;
use App\Models\KnowledgeBaseGuide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KnowledgeBaseController extends Controller
{
    /**
     * Display the knowledge base page (shell; data loads via bootstrap API).
     */
    public function index(): View
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            abort(403, 'Company context required.');
        }

        return view('dashboard.knowledge-base', [
            'canCreateKnowledgeBase' => $user->hasPermission('create_knowledge_base'),
            'canEditKnowledgeBase' => $user->hasPermission('edit_knowledge_base'),
            'canDeleteKnowledgeBase' => $user->hasPermission('delete_knowledge_base'),
        ]);
    }

    /**
     * JSON payload for the knowledge base page (article category tree and articles).
     */
    public function bootstrap(): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        return response()->json([
            'success' => true,
            'categories' => $this->articleCategories($user->company_id),
            'articles' => KnowledgeBaseArticle::where('company_id', $user->company_id)
                ->with('user')
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn ($a) => $this->formatArticle($a))
                ->values(),
            'can_create' => $user->hasPermission('create_knowledge_base'),
            'can_edit' => $user->hasPermission('edit_knowledge_base'),
            'can_delete' => $user->hasPermission('delete_knowledge_base'),
        ]);
    }

    /**
     * Store a new category.
     */
    public function storeCategory(StoreKnowledgeBaseCategoryRequest $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        if ($request->validated('type') === 'article') {
            return $this->storeArticleCategory($user->company_id, trim($request->validated('name')), $request->validated('parent_id'));
        }

        $slug = Str::slug(trim($request->validated('name')));
        if ($slug === '') {
            return response()->json([
                'success' => false,
                'message' => 'Category name must contain at least one letter or number.',
            ], 422);
        }

        $existing = KnowledgeBaseCategory::where('company_id', $user->company_id)
            ->where('type', $request->validated('type'))
            ->where('slug', $slug)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'category' => ['id' => $existing->id, 'name' => $existing->name, 'slug' => $existing->slug],
            ]);
        }

        $maxOrder = KnowledgeBaseCategory::where('company_id', $user->company_id)
            ->where('type', $request->validated('type'))
            ->max('sort_order');

        $category = KnowledgeBaseCategory::create([
            'company_id' => $user->company_id,
            'type' => $request->validated('type'),
            'name' => trim($request->validated('name')),
            'slug' => $slug,
            'sort_order' => (int) $maxOrder + 1,
        ]);

        return response()->json([
            'success' => true,
            'category' => ['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug],
        ]);
    }

    /**
     * Store a new article.
     */
    public function storeArticle(StoreKnowledgeBaseArticleRequest $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $article = KnowledgeBaseArticle::create(array_merge(
            ['company_id' => $user->company_id, 'user_id' => $user->id],
            $this->articleValues($user->company_id, $request->validated())
        ));

        $article->load('user');

        return response()->json([
            'success' => true,
            'article' => $this->formatArticle($article),
        ]);
    }

    /**
     * Store a new FAQ.
     */
    public function storeFaq(StoreKnowledgeBaseFaqRequest $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $categoryName = $this->resolveCategoryName($user->company_id, 'faq', $request->validated('category'));

        $faq = KnowledgeBaseFaq::create([
            'company_id' => $user->company_id,
            'question' => $request->validated('question'),
            'answer' => $request->validated('answer'),
            'category' => $categoryName,
            'visibility' => $request->validated('visibility'),
        ]);

        return response()->json([
            'success' => true,
            'faq' => $this->formatFaq($faq),
        ]);
    }

    /**
     * Store a new guide.
     */
    public function storeGuide(StoreKnowledgeBaseGuideRequest $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $category = KnowledgeBaseCategory::where('company_id', $user->company_id)
            ->where('type', 'guide')
            ->where('slug', $request->validated('category'))
            ->firstOrFail();

        $guide = KnowledgeBaseGuide::create([
            'company_id' => $user->company_id,
            'title' => $request->validated('title'),
            'excerpt' => $request->validated('excerpt'),
            'category' => $category->name,
            'duration' => $request->validated('duration'),
            'icon' => $request->validated('icon') ?: '📖',
        ]);

        return response()->json([
            'success' => true,
            'guide' => $this->formatGuide($guide),
        ]);
    }

    /**
     * Update an article.
     */
    public function updateArticle(StoreKnowledgeBaseArticleRequest $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $article = KnowledgeBaseArticle::where('company_id', $user->company_id)->findOrFail($id);
        $article->update($this->articleValues($user->company_id, $request->validated()));

        $article->load('user');

        return response()->json([
            'success' => true,
            'article' => $this->formatArticle($article),
        ]);
    }

    /**
     * Delete an article.
     */
    public function destroyArticle(int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $article = KnowledgeBaseArticle::where('company_id', $user->company_id)->findOrFail($id);
        $article->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Update an FAQ.
     */
    public function updateFaq(StoreKnowledgeBaseFaqRequest $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $faq = KnowledgeBaseFaq::where('company_id', $user->company_id)->findOrFail($id);
        $categoryName = $this->resolveCategoryName($user->company_id, 'faq', $request->validated('category'));

        $faq->update([
            'question' => $request->validated('question'),
            'answer' => $request->validated('answer'),
            'category' => $categoryName,
            'visibility' => $request->validated('visibility'),
        ]);

        return response()->json([
            'success' => true,
            'faq' => $this->formatFaq($faq),
        ]);
    }

    /**
     * Delete an FAQ.
     */
    public function destroyFaq(int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $faq = KnowledgeBaseFaq::where('company_id', $user->company_id)->findOrFail($id);
        $faq->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Update a guide.
     */
    public function updateGuide(StoreKnowledgeBaseGuideRequest $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $guide = KnowledgeBaseGuide::where('company_id', $user->company_id)->findOrFail($id);
        $category = KnowledgeBaseCategory::where('company_id', $user->company_id)
            ->where('type', 'guide')
            ->where('slug', $request->validated('category'))
            ->firstOrFail();

        $guide->update([
            'title' => $request->validated('title'),
            'excerpt' => $request->validated('excerpt'),
            'category' => $category->name,
            'duration' => $request->validated('duration'),
            'icon' => $request->validated('icon') ?: '📖',
        ]);

        return response()->json([
            'success' => true,
            'guide' => $this->formatGuide($guide),
        ]);
    }

    /**
     * Delete a guide.
     */
    public function destroyGuide(int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $guide = KnowledgeBaseGuide::where('company_id', $user->company_id)->findOrFail($id);
        $guide->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Rename an article category and/or move it under another parent.
     */
    public function updateCategory(UpdateKnowledgeBaseCategoryRequest $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $category = KnowledgeBaseCategory::where('company_id', $user->company_id)
            ->where('type', 'article')
            ->findOrFail($id);

        $name = trim($request->validated('name'));
        $parentId = $request->validated('parent_id');
        $parentId = $parentId !== null ? (int) $parentId : null;

        if ($parentId !== null && in_array($parentId, KnowledgeBaseCategory::subtreeIds($user->company_id, $category->id), true)) {
            return response()->json([
                'success' => false,
                'message' => 'A category cannot be moved inside itself or one of its subcategories.',
            ], 422);
        }

        if ($this->siblingNameTaken($user->company_id, $parentId, $name, $category->id)) {
            return response()->json([
                'success' => false,
                'message' => 'A category with this name already exists here.',
            ], 422);
        }

        $category->name = $name;
        $category->slug = KnowledgeBaseCategory::uniqueSlug($user->company_id, 'article', $name, $category->id);
        if ($category->parent_id !== $parentId) {
            $category->parent_id = $parentId;
            $category->sort_order = KnowledgeBaseCategory::nextSortOrder($user->company_id, 'article', $parentId);
        }
        $category->save();

        KnowledgeBaseCategory::syncArticlePaths($user->company_id);

        return response()->json([
            'success' => true,
            'categories' => $this->articleCategories($user->company_id),
        ]);
    }

    /**
     * Move an article category one position up or down among its siblings.
     */
    public function moveCategory(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $direction = $request->validate(['direction' => 'required|string|in:up,down'])['direction'];

        $category = KnowledgeBaseCategory::where('company_id', $user->company_id)
            ->where('type', 'article')
            ->findOrFail($id);

        $siblings = KnowledgeBaseCategory::where('company_id', $user->company_id)
            ->where('type', 'article')
            ->where('parent_id', $category->parent_id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->values();

        $index = $siblings->search(fn ($c) => $c->id === $category->id);
        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && $swapWith >= 0 && $swapWith < $siblings->count()) {
            $ordered = $siblings->all();
            [$ordered[$index], $ordered[$swapWith]] = [$ordered[$swapWith], $ordered[$index]];

            DB::transaction(function () use ($ordered) {
                foreach ($ordered as $position => $sibling) {
                    if ($sibling->sort_order !== $position) {
                        $sibling->update(['sort_order' => $position]);
                    }
                }
            });
        }

        return response()->json([
            'success' => true,
            'categories' => $this->articleCategories($user->company_id),
        ]);
    }

    /**
     * Delete an article category. Its articles and subcategories move up to its parent.
     */
    public function destroyCategory(int $id): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $category = KnowledgeBaseCategory::where('company_id', $user->company_id)
            ->where('type', 'article')
            ->findOrFail($id);

        DB::transaction(function () use ($category, $user) {
            $nextOrder = KnowledgeBaseCategory::nextSortOrder($user->company_id, 'article', $category->parent_id);

            KnowledgeBaseCategory::where('company_id', $user->company_id)
                ->where('parent_id', $category->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->each(function (KnowledgeBaseCategory $child) use ($category, &$nextOrder) {
                    $child->update(['parent_id' => $category->parent_id, 'sort_order' => $nextOrder++]);
                });

            KnowledgeBaseArticle::where('company_id', $user->company_id)
                ->where('category_id', $category->id)
                ->toBase()
                ->update(['category_id' => $category->parent_id]);

            $category->delete();
        });

        KnowledgeBaseCategory::syncArticlePaths($user->company_id);

        return response()->json([
            'success' => true,
            'categories' => $this->articleCategories($user->company_id),
            'articles' => KnowledgeBaseArticle::where('company_id', $user->company_id)
                ->with('user')
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn ($a) => $this->formatArticle($a))
                ->values(),
        ]);
    }

    private function storeArticleCategory(int $companyId, string $name, mixed $parentId): JsonResponse
    {
        $parentId = $parentId !== null ? (int) $parentId : null;

        $existing = KnowledgeBaseCategory::where('company_id', $companyId)
            ->where('type', 'article')
            ->where('parent_id', $parentId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if (! $existing) {
            $existing = KnowledgeBaseCategory::create([
                'company_id' => $companyId,
                'type' => 'article',
                'parent_id' => $parentId,
                'name' => $name,
                'slug' => KnowledgeBaseCategory::uniqueSlug($companyId, 'article', $name),
                'sort_order' => KnowledgeBaseCategory::nextSortOrder($companyId, 'article', $parentId),
            ]);
        }

        return response()->json([
            'success' => true,
            'category' => ['id' => $existing->id, 'name' => $existing->name, 'slug' => $existing->slug, 'parent_id' => $existing->parent_id],
            'categories' => $this->articleCategories($companyId),
        ]);
    }

    private function siblingNameTaken(int $companyId, ?int $parentId, string $name, int $ignoreId): bool
    {
        return KnowledgeBaseCategory::where('company_id', $companyId)
            ->where('type', 'article')
            ->where('parent_id', $parentId)
            ->whereKeyNot($ignoreId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function articleCategories(int $companyId): array
    {
        $paths = KnowledgeBaseCategory::pathNames($companyId);

        return KnowledgeBaseCategory::where('company_id', $companyId)
            ->where('type', 'article')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug', 'sort_order'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'parent_id' => $c->parent_id,
                'name' => $c->name,
                'slug' => $c->slug,
                'path' => $paths[$c->id] ?? $c->name,
                'sort_order' => $c->sort_order,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function articleValues(int $companyId, array $validated): array
    {
        $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;
        $content = (string) ($validated['content'] ?? '');
        $excerpt = trim((string) ($validated['excerpt'] ?? ''));

        if ($excerpt === '') {
            $plain = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(
                strip_tags((string) preg_replace('#<(br|/p|/div|/li|/h[1-6])\b[^>]*>#i', ' ', $content)),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )));
            $excerpt = e(Str::limit($plain !== '' ? $plain : (string) $validated['title'], 200));
        }

        return [
            'title' => $validated['title'],
            'excerpt' => $excerpt,
            'content' => $content,
            'category_id' => $categoryId,
            'category' => $categoryId ? (KnowledgeBaseCategory::pathNames($companyId)[$categoryId] ?? null) : null,
            'visibility' => $validated['visibility'],
        ];
    }

    private function resolveCategoryName(int $companyId, string $type, ?string $categorySlug): ?string
    {
        if ($categorySlug === null || trim($categorySlug) === '') {
            return null;
        }

        $category = KnowledgeBaseCategory::where('company_id', $companyId)
            ->where('type', $type)
            ->where('slug', $categorySlug)
            ->firstOrFail();

        return $category->name;
    }

    private function formatArticle(KnowledgeBaseArticle $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'content' => $article->content,
            'category_id' => $article->category_id,
            'category' => $article->category,
            'visibility' => $article->visibility,
            'author' => $article->user?->name ?? 'Unknown',
            'date' => $article->created_at->format('M j, Y'),
            'updated' => $article->updated_at?->format('M j, Y'),
            'views' => $article->views,
        ];
    }

    private function formatFaq(KnowledgeBaseFaq $faq): array
    {
        return [
            'id' => $faq->id,
            'question' => $faq->question,
            'answer' => $faq->answer,
            'category' => $faq->category,
            'visibility' => $faq->visibility,
            'views' => $faq->views,
        ];
    }

    private function formatGuide(KnowledgeBaseGuide $guide): array
    {
        return [
            'id' => $guide->id,
            'title' => $guide->title,
            'excerpt' => $guide->excerpt,
            'category' => $guide->category,
            'duration' => $guide->duration ?? '10 min',
            'icon' => $guide->icon ?? '📖',
        ];
    }
}
