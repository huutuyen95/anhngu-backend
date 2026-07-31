<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SessionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_session_id',
        'order',
        'itemable_type',
        'itemable_id',
    ];

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }
}
