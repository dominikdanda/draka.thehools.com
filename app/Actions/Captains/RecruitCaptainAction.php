<?php

namespace App\Actions\Captains;

use App\Models\Captain;
use App\Models\Player;
use Illuminate\Validation\ValidationException;

class RecruitCaptainAction
{
    public function execute(Player $player, string $name): Captain
    {
        return $player->getConnection()->transaction(function () use ($player, $name) {
            // Serialize recruitment even when the Player has no Captains yet.
            $current = $player->newQuery()->lockForUpdate()->findOrFail($player->getKey());

            if ($current->captains()->active()->count() >= (int) config('thehools.captains.starting_limit')) {
                throw ValidationException::withMessages([
                    'name' => 'Osiągnięto limit aktywnych kapitanów.',
                ]);
            }

            return $current->captains()->create(['name' => $name]);
        }, 3);
    }
}
