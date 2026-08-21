<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Thư mục đề thi — kho toàn cục, phân theo NHÓM nội dung `group`:
 * 'exam' (Đề thi) | 'exercise' (Bài tập). Cây 2 cấp (parent_id null = gốc).
 */
class TestCategory extends Model
{
    public const GROUP_EXAM = 'exam';

    public const GROUP_EXERCISE = 'exercise';

    public const GROUPS = [self::GROUP_EXAM, self::GROUP_EXERCISE];

    protected $fillable = [
        'name',
        'group',
        'classroom_id',
        'parent_id',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(Test::class, 'category_id');
    }
}
