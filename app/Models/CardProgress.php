<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardProgress extends Model
{
    use HasFactory;

    protected $table = 'card_progress';

    protected $fillable = [
        'user_id',
        'card_id',
        'status',
        'next_review_at',
        'ease',
        'review_count',
    ];

    protected function casts(): array
    {
        return [
            'next_review_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
