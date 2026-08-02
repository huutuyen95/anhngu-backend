<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    use HasFactory;

    protected $fillable = [
        'deck_id',
        'order',
        'term',
        'meaning',
        'pos',
        'ipa',
        'audio_url',
        'image_url',
        'example',
    ];

    public function deck(): BelongsTo
    {
        return $this->belongsTo(Deck::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(CardProgress::class);
    }
}
