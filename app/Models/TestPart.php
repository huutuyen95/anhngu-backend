<?php

namespace App\Models;

use App\Enums\TestPartDisplayMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_id',
        'order',
        'title',
        'display_mode',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'display_mode' => TestPartDisplayMode::class,
        ];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(TestSection::class);
    }
}
