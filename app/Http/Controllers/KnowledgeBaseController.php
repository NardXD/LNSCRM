<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKnowledgeBaseArticleRequest;
use App\Http\Requests\StoreKnowledgeBaseCategoryRequest;
use App\Http\Requests\StoreKnowledgeBaseFaqRequest;
use App\Http\Requests\StoreKnowledgeBaseGuideRequest;
use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseCategory;
use App\Models\KnowledgeBaseFaq;
use App\Models\KnowledgeBaseGuide;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class KnowledgeBaseController extends Controller
{
    /**
     * Display the knowledge base page with company-scoped data.
     */
    public function index(): View
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id) {
            abort(403, 'Company context required.');
        }

        KnowledgeBaseCategory::ensureDefaultsForCompany($user->company_id);

        $articleCategories = KnowledgeBaseCategory::where('company_id', $user->company_id)
            ->where('type', 'article')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug]);

        $faqCategories = KnowledgeBaseCategory::where('company_id', $user->company_id)
            ->where('type', 'faq')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug]);

        $guideCategories = KnowledgeBaseCategory::where('company_id', $user->company_id)
            ->where('type', 'guide')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug]);

        $articles = KnowledgeBaseArticle::where('company_id', $user->company_id)
            ->with('user')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => $this->formatArticle($a));

        $faqs = KnowledgeBaseFaq::where('company_id', $user->company_id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($f) => $this->formatFaq($f));

        $guides = KnowledgeBaseGuide::where('company_id', $user->company_id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($g) => $this->formatGuide($g));

        return view('dashboard.knowledge-base', [
            'articles' => $articles,
            'faqs' => $faqs,
            'guides' => $guides,
            'articleCategories' => $articleCategories,
            'faqCategories' => $faqCategories,
            'guideCategories' => $guideCategories,
            'canCreateKnowledgeBase' => $user->hasPermission('create_knowledge_base'),
            'canEditKnowledgeBase' => $user->hasPermission('edit_knowledge_base'),
            'canDeleteKnowledgeBase' => $user->hasPermission('delete_knowledge_base'),
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

        $slug = \Illuminate\Support\Str::slug(trim($request->validated('name')));
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

        $category = KnowledgeBaseCategory::where('company_id', $user->company_id)
            ->where('type', 'article')
            ->where('slug', $request->validated('category'))
            ->firstOrFail();

        $article = KnowledgeBaseArticle::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'title' => $request->validated('title'),
            'excerpt' => $request->validated('excerpt'),
            'content' => $request->validated('content'),
            'category' => $category->name,
            'visibility' => $request->validated('visibility'),
        ]);

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

        $faq = KnowledgeBaseFaq::create([
            'company_id' => $user->company_id,
            'question' => $request->validated('question'),
            'answer' => $request->validated('answer'),
            'category' => $request->validated('category'),
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
        $category = KnowledgeBaseCategory::where('company_id', $user->company_id)
            ->where('type', 'article')
            ->where('slug', $request->validated('category'))
            ->firstOrFail();

        $article->update([
            'title' => $request->validated('title'),
            'excerpt' => $request->validated('excerpt'),
            'content' => $request->validated('content'),
            'category' => $category->name,
            'visibility' => $request->validated('visibility'),
        ]);

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
        $faq->update([
            'question' => $request->validated('question'),
            'answer' => $request->validated('answer'),
            'category' => $request->validated('category'),
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

    private function formatArticle(KnowledgeBaseArticle $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'content' => $article->content,
            'category' => $article->category,
            'visibility' => $article->visibility,
            'author' => $article->user?->name ?? 'Unknown',
            'date' => $article->created_at->format('M j, Y'),
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
