<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->teacher()->create();
    }

    public function test_teacher_can_create_and_update_a_sanitized_article(): void
    {
        $category = ArticleCategory::create(['name' => 'Ôn luyện', 'order' => 1]);

        $response = $this->actingAs($this->teacher)->postJson('/api/v1/articles', [
            'title' => 'Mẹo học IELTS',
            'category_id' => $category->id,
            'body' => '<h2>Bắt đầu</h2><p>Nội dung hữu ích</p><script>alert(1)</script>',
            'is_published' => true,
        ])->assertCreated()
            ->assertJsonPath('article.title', 'Mẹo học IELTS')
            ->assertJsonPath('article.is_published', true);

        $article = Article::findOrFail($response->json('article.id'));
        $this->assertStringNotContainsString('<script', $article->body);
        $this->assertSame('Bắt đầuNội dung hữu ích', $article->excerpt);
        $this->assertNotNull($article->published_at);

        $this->actingAs($this->teacher)
            ->putJson("/api/v1/articles/{$article->id}", ['title' => 'Mẹo học IELTS mới'])
            ->assertOk()
            ->assertJsonPath('article.slug', 'meo-hoc-ielts-moi');
    }

    public function test_student_only_lists_and_reads_published_articles(): void
    {
        $student = User::factory()->create();
        $category = ArticleCategory::create(['name' => 'Giải trí', 'order' => 1]);
        $published = $this->article(['title' => 'Bài đã mở', 'category_id' => $category->id, 'is_published' => true, 'published_at' => now()]);
        $draft = $this->article(['title' => 'Bản nháp', 'is_published' => false]);

        $this->actingAs($student)
            ->getJson('/api/v1/library/articles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Bài đã mở')
            ->assertJsonMissingPath('data.0.body');

        $this->actingAs($student)
            ->getJson('/api/v1/library/articles/categories')
            ->assertOk()
            ->assertJsonPath('data.0.articles_count', 1);

        $this->actingAs($student)
            ->getJson("/api/v1/articles/{$published->id}/read")
            ->assertOk()
            ->assertJsonPath('article.body', '<p>Nội dung bài viết</p>');
        $this->assertSame(1, $published->fresh()->view_count);

        $this->actingAs($student)->getJson("/api/v1/articles/{$draft->id}/read")->assertNotFound();
        $this->actingAs($student)->postJson('/api/v1/articles', ['title' => 'Không được tạo'])->assertForbidden();
    }

    public function test_deleting_article_category_keeps_articles_uncategorized(): void
    {
        $category = ArticleCategory::create(['name' => 'Tin tức IELTS', 'order' => 1]);
        $article = $this->article(['category_id' => $category->id]);

        $this->actingAs($this->teacher)->putJson('/api/v1/article-categories/sync', [
            'categories' => [],
            'deleted_ids' => [$category->id],
        ])->assertOk();

        $this->assertNull($article->fresh()->category_id);
        $this->assertDatabaseMissing('article_categories', ['id' => $category->id]);
    }

    private function article(array $attributes = []): Article
    {
        return Article::create(array_merge([
            'title' => 'Bài viết '.uniqid(),
            'slug' => 'bai-viet-'.uniqid(),
            'body' => '<p>Nội dung bài viết</p>',
            'excerpt' => 'Nội dung bài viết',
            'reading_minutes' => 1,
            'is_published' => false,
            'created_by' => $this->teacher->id,
        ], $attributes));
    }
}
