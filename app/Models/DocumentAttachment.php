<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAttachment extends Model
{
    public $timestamps = false;

    protected $fillable = ['document_id', 'name', 'url', 'size_bytes', 'mime', 'order', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'size_bytes' => 'integer'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
