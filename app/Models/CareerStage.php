<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerStage extends Model
{
    protected $fillable = [
        'chapter_id',
        'number',
        'global_number',
        'title',
        'subtitle',
        'energy_cost',
        'money_reward_min',
        'money_reward_max',
        'experience_reward',
        'reward_item_id',
        'reward_item_id_2',
        'required_item_id',
        'required_item_id_2',
    ];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(CareerChapter::class, 'chapter_id');
    }

    public function rewardItem(): BelongsTo
    {
        return $this->belongsTo(CareerItem::class, 'reward_item_id');
    }

    public function rewardItem2(): BelongsTo
    {
        return $this->belongsTo(CareerItem::class, 'reward_item_id_2');
    }

    public function requiredItem(): BelongsTo
    {
        return $this->belongsTo(CareerItem::class, 'required_item_id');
    }

    public function requiredItem2(): BelongsTo
    {
        return $this->belongsTo(CareerItem::class, 'required_item_id_2');
    }
}
