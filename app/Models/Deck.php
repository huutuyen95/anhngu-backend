<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deck extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'classroom_id',
        'name',
        'slug',
        'description',
        'tts_voice',
        'tts_rate',
        'tts_repeat',
        'is_public',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_published' => 'boolean',
            'tts_rate' => 'decimal:2',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class)->orderBy('order');
    }

    /** Các lớp được gán bộ từ này (gán nhiều lớp qua bảng nối). */
    public function classrooms(): BelongsToMany
    {
        return $this->belongsToMany(Classroom::class, 'deck_classroom')->withTimestamps();
    }
}
