<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeckCategory extends Model
{
    protected $fillable = ['name', 'order'];

    public function decks(): HasMany
    {
        return $this->hasMany(Deck::class, 'category_id');
    }
}
