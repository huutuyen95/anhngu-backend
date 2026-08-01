<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentView extends Model
{
    public $timestamps = false;

    protected $fillable = ['document_id', 'user_id', 'progress_pct', 'completed_at', 'updated_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'updated_at' => 'datetime'];
    }
}
