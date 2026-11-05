<?php

namespace App\Services\Player;

use App\Models\Captain;
use App\Models\Player;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection;

class CurrentCaptainService
{
    public function resolve(Player $player, Session $session, ?Collection $captains = null): ?Captain
    {
        $captains ??= $player->captains()->active()->orderBy('id')->get();
        $selection = $session->get('current_captain_id');
        $current = is_int($selection) || is_string($selection)
            ? $captains->firstWhere('id', $selection)
            : null;

        if ($current === null) {
            $session->forget('current_captain_id');
            $current = $captains->count() === 1 ? $captains->first() : null;
        }
        if ($current !== null) {
            $session->put('current_captain_id', $current->id);
        }

        return $current;
    }
}
