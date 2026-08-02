<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_part_id',
        'order',
        'instruction',
        'passage',
        'audio_url',
        'max_plays',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(TestPart::class, 'test_part_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
