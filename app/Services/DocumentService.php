<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\User;
use App\Repositories\DocumentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class DocumentService
{
    /** Hạn mức dung lượng toàn trung tâm: 5 GB. */
    public const QUOTA_BYTES = 5 * 1024 * 1024 * 1024;

    public function __construct(private readonly HtmlSanitizer $sanitizer, private readonly DocumentRepository $documents) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->documents->paginate($filters);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $teacher): Document
    {
        $type = $data['type'] ?? 'document';
        $body = $this->sanitizer->clean($data['body'] ?? '');

        return $this->documents->create([
            'type' => $type,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title']),
            'category_id' => $data['category_id'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'body' => $body,
            'excerpt' => $this->excerpt($body),
            'reading_minutes' => $this->readingMinutes($body),
            // Bài giảng LUÔN không publish (chỉ đến HS qua giao bài).
            'is_published' => $type === 'lecture' ? false : (bool) ($data['is_published'] ?? false),
            'created_by' => $teacher->id,
        ], $data['classroom_ids'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Document $doc, array $data): Document
    {
        $attributes = [];
        if (array_key_exists('body', $data)) {
            $body = $this->sanitizer->clean($data['body']);
            $attributes['body'] = $body;
            $attributes['excerpt'] = $this->excerpt($body);
            $attributes['reading_minutes'] = $this->readingMinutes($body);
        }
        foreach (['title', 'category_id', 'thumbnail_url'] as $f) {
            if (array_key_exists($f, $data)) {
                $attributes[$f] = $data[$f];
            }
        }
        if (array_key_exists('type', $data)) {
            $attributes['type'] = $data['type'];
            if ($data['type'] === 'lecture') {
                $attributes['is_published'] = false;
            }
        }
        if (array_key_exists('is_published', $data) && ($attributes['type'] ?? $doc->type) !== 'lecture') {
            $attributes['is_published'] = (bool) $data['is_published'];
        }

        return $this->documents->update($doc, $attributes, array_key_exists('classroom_ids', $data) ? ($data['classroom_ids'] ?? []) : null);
    }

    /**
     * Buổi đang giao tài liệu này (để cảnh báo khi xoá). Không chặn xoá.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sessionsUsing(Document $doc): array
    {
        return $this->documents->sessionsUsing($doc);
    }

    /**
     * @return array<string, mixed>
     */
    public function storageUsage(): array
    {
        $stats = $this->documents->attachmentStats();
        $byType = $stats['by_mime']
            ->groupBy(fn ($r) => $this->group($r->mime))
            ->map(fn ($rows, $group) => ['type' => $group, 'bytes' => (int) $rows->sum('bytes')])
            ->values();

        $biggest = $stats['biggest']
            ->map(fn (DocumentAttachment $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'ext' => pathinfo($a->name, PATHINFO_EXTENSION),
                'size' => $a->size_bytes,
                'parent' => $a->document?->title,
            ]);

        return [
            'total_bytes' => $stats['total'],
            'limit_bytes' => self::QUOTA_BYTES,
            'by_type' => $byType,
            'biggest' => $biggest,
        ];
    }

    public function remainingBytes(): int
    {
        return max(0, self::QUOTA_BYTES - $this->documents->attachmentStats()['total']);
    }

    private function group(?string $mime): string
    {
        $m = strtolower((string) $mime);

        return match (true) {
            str_contains($m, 'video') => 'video',
            str_contains($m, 'audio') => 'audio',
            str_contains($m, 'image') => 'image',
            default => 'document',
        };
    }

    private function excerpt(string $body): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? ''), 160);
    }

    private function readingMinutes(string $body): int
    {
        $words = str_word_count(strip_tags($body));

        return max(1, (int) ceil($words / 200));
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'noi-dung';
        $slug = $base;
        $i = 1;
        while ($this->documents->slugExists($slug)) {
            $slug = "{$base}-".(++$i);
        }

        return $slug;
    }

    public function detail(Document $document): Document
    {
        return $this->documents->detail($document);
    }

    public function publish(Document $document, bool $published): Document
    {
        abort_if($document->type === 'lecture', 422, 'Bài giảng không có công tắc thư viện — chỉ đến học sinh qua giao bài.');

        return $this->documents->update($document, ['is_published' => $published]);
    }

    public function delete(Document $document): array
    {
        $sessions = $this->sessionsUsing($document);
        $this->documents->delete($document);

        return $sessions;
    }
}
