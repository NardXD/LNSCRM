<?php

namespace App\Services\Front;

use App\Models\Company;
use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseCategory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FrontKnowledgeBaseImportService
{
    /** @var array<string, int|null> */
    private array $editorUserIds = [];

    /**
     * @param  array{
     *     dry_run?: bool,
     *     refresh?: bool,
     *     knowledge_base?: string|null,
     *     user_id?: int|null,
     * }  $options
     * @return array<string, mixed>
     */
    public function importFromApi(Company $company, FrontApiClient $client, array $options = []): array
    {
        $this->editorUserIds = [];
        $stats = $this->emptyStats();
        $defaultUserId = $this->resolveDefaultUserId($company, $options['user_id'] ?? null);

        try {
            $knowledgeBases = $client->listKnowledgeBases();
        } catch (\Throwable $e) {
            throw new RuntimeException('Could not list Front knowledge bases (token needs the knowledge_bases:read scope): '.$e->getMessage(), 0, $e);
        }

        $onlyId = trim((string) ($options['knowledge_base'] ?? ''));
        if ($onlyId !== '') {
            $knowledgeBases = array_values(array_filter(
                $knowledgeBases,
                fn (array $kb) => (string) ($kb['id'] ?? '') === $onlyId
            ));
            if ($knowledgeBases === []) {
                throw new RuntimeException("Front knowledge base {$onlyId} was not found.");
            }
        }

        foreach ($knowledgeBases as $knowledgeBase) {
            $knowledgeBaseId = (string) ($knowledgeBase['id'] ?? '');
            if ($knowledgeBaseId === '') {
                continue;
            }

            $stats['knowledge_bases_scanned']++;
            $categoryIds = $this->syncCategories($company, $client, $knowledgeBaseId, (bool) ($options['dry_run'] ?? false), $stats);

            foreach ($client->listKnowledgeBaseArticles($knowledgeBaseId) as $slimArticle) {
                $this->importArticle($company, $client, $slimArticle, $categoryIds, $defaultUserId, $options, $stats);
            }
        }

        if (! ($options['dry_run'] ?? false)) {
            KnowledgeBaseCategory::syncArticlePaths($company->id);
        }

        return $stats;
    }

    public static function clientForCompany(Company $company, ?string $tokenOverride = null): FrontApiClient
    {
        return FrontTeammatesAndTemplatesImportService::clientForCompany($company, $tokenOverride);
    }

    /**
     * Mirror the Front category tree as nested CRM article categories.
     *
     * @param  array<string, mixed>  $stats
     * @return array<string, int> CRM category id keyed by Front category id
     */
    private function syncCategories(Company $company, FrontApiClient $client, string $knowledgeBaseId, bool $dryRun, array &$stats): array
    {
        $categories = $this->loadCategories($client, $knowledgeBaseId, $stats);
        $crmIds = [];

        $ensure = function (string $frontId, array $visiting = []) use (&$ensure, &$crmIds, &$stats, $categories, $company, $dryRun): ?int {
            if (array_key_exists($frontId, $crmIds)) {
                return $crmIds[$frontId];
            }
            if (! isset($categories[$frontId]) || isset($visiting[$frontId])) {
                return null;
            }
            $visiting[$frontId] = true;

            $parentFrontId = $categories[$frontId]['parent_id'];
            $parentId = $parentFrontId !== null ? $ensure($parentFrontId, $visiting) : null;
            $name = $categories[$frontId]['name'] !== '' ? $categories[$frontId]['name'] : 'Untitled category';

            $existing = KnowledgeBaseCategory::query()
                ->where('company_id', $company->id)
                ->where('front_category_id', $frontId)
                ->first()
                ?? KnowledgeBaseCategory::query()
                    ->where('company_id', $company->id)
                    ->where('type', 'article')
                    ->whereNull('front_category_id')
                    ->where('parent_id', $parentId)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->first();

            if ($existing) {
                if ($existing->front_category_id === null && ! $dryRun) {
                    $existing->update(['front_category_id' => $frontId]);
                }

                return $crmIds[$frontId] = (int) $existing->id;
            }

            $stats['categories_created']++;
            if ($dryRun) {
                return $crmIds[$frontId] = null;
            }

            $name = Str::limit($name, 255, '');
            $created = KnowledgeBaseCategory::query()->create([
                'company_id' => $company->id,
                'type' => 'article',
                'parent_id' => $parentId,
                'front_category_id' => $frontId,
                'name' => $name,
                'slug' => KnowledgeBaseCategory::uniqueSlug($company->id, 'article', $name),
                'sort_order' => KnowledgeBaseCategory::nextSortOrder($company->id, 'article', $parentId),
            ]);

            return $crmIds[$frontId] = (int) $created->id;
        };

        foreach (array_keys($categories) as $frontId) {
            $ensure($frontId);
        }

        return array_filter($crmIds, fn ($id) => $id !== null);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, array{name: string, parent_id: string|null}>
     */
    private function loadCategories(FrontApiClient $client, string $knowledgeBaseId, array &$stats): array
    {
        $categories = [];
        foreach ($client->listKnowledgeBaseCategories($knowledgeBaseId) as $slim) {
            $id = (string) ($slim['id'] ?? '');
            if ($id === '') {
                continue;
            }

            try {
                $content = $client->getKnowledgeBaseCategoryContent($id);
            } catch (\Throwable $e) {
                $stats['warnings'][] = "Could not load Front category {$id}: ".$e->getMessage();

                continue;
            }

            $categories[$id] = [
                'name' => trim((string) ($content['name'] ?? '')),
                'parent_id' => $this->idFromLink($content['_links']['related']['parent_category'] ?? $slim['_links']['related']['parent_category'] ?? null),
            ];
            $stats['categories_scanned']++;
        }

        return $categories;
    }

    /**
     * @param  array<string, mixed>  $slimArticle
     * @param  array<string, int>  $categoryIds
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $stats
     */
    private function importArticle(
        Company $company,
        FrontApiClient $client,
        array $slimArticle,
        array $categoryIds,
        ?int $defaultUserId,
        array $options,
        array &$stats,
    ): void {
        $frontId = (string) ($slimArticle['id'] ?? '');
        if ($frontId === '') {
            return;
        }

        $stats['articles_scanned']++;
        $dryRun = (bool) ($options['dry_run'] ?? false);

        $existing = KnowledgeBaseArticle::query()
            ->where('company_id', $company->id)
            ->where('front_article_id', $frontId)
            ->first();

        if ($existing && ! ($options['refresh'] ?? false)) {
            $stats['articles_existing']++;

            return;
        }

        try {
            $article = $client->getKnowledgeBaseArticleContent($frontId);
        } catch (\Throwable $e) {
            $stats['articles_failed']++;
            $stats['warnings'][] = "Could not load Front article {$frontId}: ".$e->getMessage();

            return;
        }

        $title = trim((string) ($article['name'] ?? ''));
        if ($title === '') {
            $title = 'Untitled article';
            $stats['warnings'][] = "Front article {$frontId} has no name; imported as \"Untitled article\".";
        }

        $status = strtolower(trim((string) ($article['status'] ?? '')));
        $visibility = match ($status) {
            'published' => 'published',
            'archived' => 'archived',
            default => 'draft',
        };
        if (! in_array($status, ['published', 'draft', 'archived'], true)) {
            $stats['warnings'][] = "Front article {$frontId} has status \"{$status}\"; imported as draft.";
        }

        $frontCategoryId = $this->idFromLink($article['_links']['related']['category'] ?? $slimArticle['_links']['related']['category'] ?? null);
        $categoryId = $frontCategoryId !== null ? ($categoryIds[$frontCategoryId] ?? null) : null;

        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', (string) ($article['content'] ?? '')) ?? '';
        $plain = $this->plainText($html);
        $excerpt = $plain !== '' ? e(Str::limit($plain, 200)) : e($title);

        if ($dryRun) {
            $stats[$existing ? 'articles_updated' : 'articles_created']++;

            return;
        }

        $html = $this->localizeFiles($client, $company, $frontId, $html, $article['attachments'] ?? [], $stats);

        $values = [
            'user_id' => $this->resolveEditorUserId($company, $client, $article['_links']['related']['last_editor'] ?? null, $stats) ?? $defaultUserId,
            'title' => Str::limit($title, 255, ''),
            'excerpt' => $excerpt,
            'content' => $html,
            'category_id' => $categoryId,
            'visibility' => $visibility,
        ];

        $model = $existing ?? new KnowledgeBaseArticle([
            'company_id' => $company->id,
            'front_article_id' => $frontId,
        ]);
        $model->fill($values);

        if (! $existing) {
            $createdAt = $this->timestamp($article['created_at'] ?? $slimArticle['created_at'] ?? null);
            if ($createdAt) {
                $model->created_at = $createdAt;
            }
        }
        $updatedAt = $this->timestamp($article['updated_at'] ?? $slimArticle['updated_at'] ?? null);
        if ($updatedAt) {
            $model->updated_at = $updatedAt;
        }

        $model->save();

        $stats[$existing ? 'articles_updated' : 'articles_created']++;
    }

    /**
     * Download Front-hosted files (they need the API token) and point the article at local copies.
     *
     * @param  mixed  $attachments
     * @param  array<string, mixed>  $stats
     */
    private function localizeFiles(FrontApiClient $client, Company $company, string $frontId, string $html, mixed $attachments, array &$stats): string
    {
        $dir = "knowledge-base/front/{$company->id}/{$frontId}";
        $listed = [];

        foreach (is_array($attachments) ? $attachments : [] as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }
            $url = trim((string) ($attachment['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $filename = trim((string) ($attachment['filename'] ?? '')) ?: basename((string) parse_url($url, PHP_URL_PATH));
            $local = $this->storeFile($client, $url, $dir, $filename, $stats);
            if ($local === null) {
                continue;
            }

            $cid = trim((string) ($attachment['metadata']['cid'] ?? ''));
            $inContent = str_contains($html, $url) || ($cid !== '' && str_contains($html, 'cid:'.$cid));
            $html = str_replace($url, $local, $html);
            if ($cid !== '') {
                $html = str_replace('cid:'.$cid, $local, $html);
            }

            if (! $inContent) {
                $listed[] = '<li><a href="'.e($local).'" target="_blank" rel="noopener">'.e($filename).'</a></li>';
            }
        }

        $html = preg_replace_callback(
            '#(["\'])(https?://[^"\']*frontapp\.com/[^"\']*download/[^"\']+)\1#i',
            function (array $m) use ($client, $dir, &$stats) {
                $url = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $local = $this->storeFile($client, $url, $dir, basename((string) parse_url($url, PHP_URL_PATH)), $stats);

                return $local !== null ? $m[1].$local.$m[1] : $m[0];
            },
            $html
        ) ?? $html;

        if ($listed !== []) {
            $html .= '<h3>Attachments</h3><ul>'.implode('', $listed).'</ul>';
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function storeFile(FrontApiClient $client, string $url, string $dir, string $filename, array &$stats): ?string
    {
        try {
            $file = $client->download($url);
        } catch (\Throwable $e) {
            $stats['warnings'][] = "Could not download {$url}: ".$e->getMessage();

            return null;
        }

        $safeName = Str::limit(preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'file', 120, '');
        $path = $dir.'/'.substr(sha1($url), 0, 10).'-'.$safeName;
        Storage::disk('public')->put($path, $file['body']);
        $stats['files_downloaded']++;

        return '/media/'.$path;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function resolveEditorUserId(Company $company, FrontApiClient $client, mixed $teammateLink, array &$stats): ?int
    {
        $link = is_string($teammateLink) ? trim($teammateLink) : '';
        if ($link === '') {
            return null;
        }

        if (! array_key_exists($link, $this->editorUserIds)) {
            $userId = null;
            try {
                $email = strtolower(trim((string) ($client->getJson($link)['email'] ?? '')));
                if ($email !== '') {
                    $userId = User::query()
                        ->where('company_id', $company->id)
                        ->whereRaw('LOWER(email) = ?', [$email])
                        ->value('id');
                }
            } catch (\Throwable $e) {
                $stats['warnings'][] = "Could not load Front teammate {$link}: ".$e->getMessage();
            }
            $this->editorUserIds[$link] = $userId !== null ? (int) $userId : null;
        }

        return $this->editorUserIds[$link];
    }

    private function resolveDefaultUserId(Company $company, mixed $userId): ?int
    {
        if (is_numeric($userId) && (int) $userId > 0) {
            $id = User::query()->where('company_id', $company->id)->whereKey((int) $userId)->value('id');
            if ($id === null) {
                throw new RuntimeException("User #{$userId} does not belong to company #{$company->id}.");
            }

            return (int) $id;
        }

        $id = User::query()->where('company_id', $company->id)->orderBy('id')->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function idFromLink(mixed $link): ?string
    {
        $link = is_string($link) ? trim($link) : '';
        if ($link === '') {
            return null;
        }

        $id = basename(rtrim((string) parse_url($link, PHP_URL_PATH), '/'));

        return $id !== '' ? $id : null;
    }

    private function timestamp(mixed $value): ?Carbon
    {
        return is_numeric($value) && (float) $value > 0
            ? Carbon::createFromTimestamp((float) $value, config('app.timezone'))
            : null;
    }

    private function plainText(string $html): string
    {
        $text = preg_replace('#<(br|/p|/div|/li|/h[1-6])\b[^>]*>#i', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyStats(): array
    {
        return [
            'knowledge_bases_scanned' => 0,
            'categories_scanned' => 0,
            'categories_created' => 0,
            'articles_scanned' => 0,
            'articles_created' => 0,
            'articles_updated' => 0,
            'articles_existing' => 0,
            'articles_failed' => 0,
            'files_downloaded' => 0,
            'warnings' => [],
        ];
    }
}
