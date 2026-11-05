<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStageProgress extends Model
{
    protected $table = 'player_stage_progress';

    protected $fillable = [
        'player_id',
        'stage_id',
        'mastery_progress',
        'completions',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CareerStage::class, 'stage_id');
    }
}
