<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpaEntry extends Model
{
    protected $table = 'ipa_dictionary';

    public $timestamps = false;

    protected $fillable = ['word', 'ipa', 'pos', 'meaning_vi'];
}
