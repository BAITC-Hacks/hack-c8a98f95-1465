<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['interests' => 'array', 'skills' => 'array', 'technologies' => 'array'];
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }
}
