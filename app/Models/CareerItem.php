<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareerItem extends Model
{
    protected $fillable = [
        'legacy_id',
        'name',
        'value',
    ];

    public function playerHoldings(): HasMany
    {
        return $this->hasMany(PlayerCareerItem::class);
    }
}
