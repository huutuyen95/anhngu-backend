<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentAttachment;
use App\Models\SessionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentService
{
    /** Hạn mức dung lượng toàn trung tâm: 5 GB. */
    public const QUOTA_BYTES = 5 * 1024 * 1024 * 1024;

    public function __construct(private readonly HtmlSanitizer $sanitizer) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $teacher): Document
    {
        $type = $data['type'] ?? 'document';
        $body = $this->sanitizer->clean($data['body'] ?? '');

        return DB::transaction(function () use ($data, $teacher, $type, $body) {
            $doc = Document::create([
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
            ]);
            $doc->classrooms()->sync($data['classroom_ids'] ?? []);

            return $doc;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Document $doc, array $data): Document
    {
        DB::transaction(function () use ($doc, $data) {
            if (array_key_exists('body', $data)) {
                $body = $this->sanitizer->clean($data['body']);
                $doc->body = $body;
                $doc->excerpt = $this->excerpt($body);
                $doc->reading_minutes = $this->readingMinutes($body);
            }
            foreach (['title', 'category_id', 'thumbnail_url'] as $f) {
                if (array_key_exists($f, $data)) {
                    $doc->{$f} = $data[$f];
                }
            }
            if (array_key_exists('type', $data)) {
                $doc->type = $data['type'];
                if ($data['type'] === 'lecture') {
                    $doc->is_published = false;
                }
            }
            if (array_key_exists('is_published', $data) && $doc->type !== 'lecture') {
                $doc->is_published = (bool) $data['is_published'];
            }
            $doc->save();

            if (array_key_exists('classroom_ids', $data)) {
                $doc->classrooms()->sync($data['classroom_ids'] ?? []);
            }
        });

        return $doc->fresh();
    }

    /**
     * Buổi đang giao tài liệu này (để cảnh báo khi xoá). Không chặn xoá.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sessionsUsing(Document $doc): array
    {
        return SessionItem::where('itemable_type', $doc->getMorphClass())
            ->where('itemable_id', $doc->id)
            ->with('classSession.classroom')
            ->get()
            ->map(fn (SessionItem $i) => [
                'id' => $i->classSession?->id,
                'title' => $i->classSession?->title,
                'classroom' => $i->classSession?->classroom?->name,
            ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function storageUsage(): array
    {
        $total = (int) DocumentAttachment::sum('size_bytes');
        $byType = DocumentAttachment::selectRaw('mime, SUM(size_bytes) as bytes')
            ->groupBy('mime')->get()
            ->groupBy(fn ($r) => $this->group($r->mime))
            ->map(fn ($rows, $group) => ['type' => $group, 'bytes' => (int) $rows->sum('bytes')])
            ->values();

        $biggest = DocumentAttachment::with('document:id,title')
            ->orderByDesc('size_bytes')->take(10)->get()
            ->map(fn (DocumentAttachment $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'ext' => pathinfo($a->name, PATHINFO_EXTENSION),
                'size' => $a->size_bytes,
                'parent' => $a->document?->title,
            ]);

        return [
            'total_bytes' => $total,
            'limit_bytes' => self::QUOTA_BYTES,
            'by_type' => $byType,
            'biggest' => $biggest,
        ];
    }

    public function remainingBytes(): int
    {
        return max(0, self::QUOTA_BYTES - (int) DocumentAttachment::sum('size_bytes'));
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
        while (Document::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-".(++$i);
        }

        return $slug;
    }
}
