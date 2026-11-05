<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerChapterProgress extends Model
{
    protected $table = 'player_chapter_progress';

    protected $fillable = [
        'player_id',
        'chapter_id',
        'mastery_level',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(CareerChapter::class, 'chapter_id');
    }
}
