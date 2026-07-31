<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'name',
        'slug',
        'cover_url',
        'description',
        'is_active',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * Trạng thái suy ra từ ngày bắt đầu / kết thúc.
     * upcoming = chưa tới ngày bắt đầu; ended = đã qua ngày kết thúc; active = còn lại.
     */
    public function status(): string
    {
        $today = now()->startOfDay();

        if ($this->starts_on && $this->starts_on->gt($today)) {
            return 'upcoming';
        }
        if ($this->ends_on && $this->ends_on->lt($today)) {
            return 'ended';
        }

        return 'active';
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class)->orderBy('order');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'class_user')
            ->withPivot('status')
            ->withTimestamps();
    }
}
