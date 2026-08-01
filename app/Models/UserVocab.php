<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserVocab extends Model
{
    protected $table = 'user_vocab';

    public $timestamps = false;

    protected $fillable = ['user_id', 'word', 'meaning', 'ipa', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
