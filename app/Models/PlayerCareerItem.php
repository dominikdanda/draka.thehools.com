<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerCareerItem extends Model
{
    protected $fillable = [
        'player_id',
        'career_item_id',
        'quantity',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function careerItem(): BelongsTo
    {
        return $this->belongsTo(CareerItem::class);
    }
}
