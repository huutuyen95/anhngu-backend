<?php

namespace App\Services;

use App\Models\Article;
use App\Repositories\ArticleRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ArticleService
{
    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->articles->paginate($filters);
    }

    public function create(array $data, int $creatorId): Article
    {
        $data = $this->derived($data);
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['created_by'] = $creatorId;
        if ($data['is_published'] ?? false) {
            $data['published_at'] = now();
        }

        return $this->articles->create($data);
    }

    public function show(Article $article): Article
    {
        return $this->articles->load($article);
    }

    public function update(Article $article, array $data): Article
    {
        $data = $this->derived($data);
        if (isset($data['title']) && $data['title'] !== $article->title) {
            $data['slug'] = $this->uniqueSlug($data['title'], $article->id);
        }
        if (($data['is_published'] ?? false) && ! $article->published_at) {
            $data['published_at'] = now();
        }

        return $this->articles->update($article, $data);
    }

    public function publish(Article $article, bool $published): Article
    {
        return $this->articles->update($article, [
            'is_published' => $published,
            'published_at' => $published ? ($article->published_at ?? now()) : $article->published_at,
        ]);
    }

    public function delete(Article $article): void
    {
        $this->articles->delete($article);
    }

    private function derived(array $data): array
    {
        if (array_key_exists('body', $data)) {
            $data['body'] = $this->sanitizer->clean($data['body'] ?? '');
            $plain = trim(preg_replace('/\s+/', ' ', strip_tags($data['body'])) ?? '');
            $data['reading_minutes'] = max(1, (int) ceil(str_word_count($plain) / 200));
            if (empty($data['excerpt'])) {
                $data['excerpt'] = Str::limit($plain, 220);
            }
        }

        return $data;
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'bai-viet';
        $slug = $base;
        $suffix = 2;

        while ($this->articles->slugExists($slug, $ignoreId)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
