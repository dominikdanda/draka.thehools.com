<?php

namespace App\Services\Player;

use App\Models\Player;
use Illuminate\Support\Carbon;

class PlayerRegenerationService
{
    public function apply(Player $player): void
    {
        $state = $player->getConnection()->transaction(function () use ($player) {
            // All state writers should lock Player, then PlayerStats, then PlayerResources.
            $current = $player->newQuery()->lockForUpdate()->findOrFail($player->getKey());
            $stats = $current->stats()->lockForUpdate()->first();
            $resources = $current->resources()->lockForUpdate()->first();

            if ($stats === null || $resources === null) {
                return null;
            }

            $now = Carbon::now()->startOfSecond();
            $energyAnchor = $resources->getRawOriginal('regenerated_at');
            $healthAnchor = $resources->getRawOriginal('health_regenerated_at') ?? $energyAnchor;
            // Eloquent also parses original dates when detecting dirty attributes.
            // Clear only malformed originals so repaired timestamps can be saved.
            foreach (['regenerated_at', 'health_regenerated_at'] as $field) {
                $raw = $resources->getRawOriginal($field);
                if ($raw !== null) {
                    try {
                        Carbon::parse($raw);
                    } catch (\Exception) {
                        $resources->setAttribute($field, null);
                        $resources->syncOriginalAttribute($field);
                    }
                }
            }
            $premium = $current->hasActivePremium();
            $energyInterval = (int) config($premium
                ? 'thehools.formulas.energy.premium_regen_seconds'
                : 'thehools.formulas.energy.regen_seconds');
            $healthInterval = (int) config($premium
                ? 'thehools.formulas.health.premium_regen_seconds'
                : 'thehools.formulas.health.regen_seconds');

            [$resources->energy, $resources->regenerated_at] = $this->regenerate(
                $resources->energy, $stats->maxEnergy(), 1, $energyInterval, $energyAnchor, $now,
            );
            [$stats->health, $resources->health_regenerated_at] = $this->regenerate(
                $stats->health, $stats->maxHealth(),
                (int) config('thehools.formulas.health.regen_amount', 1),
                $healthInterval, $healthAnchor, $now,
            );

            if ($stats->isDirty()) {
                $stats->save();
            }
            if ($resources->isDirty()) {
                $resources->save();
            }

            return [$stats, $resources];
        }, 3);

        if ($state !== null) {
            $player->setRelation('stats', $state[0]);
            $player->setRelation('resources', $state[1]);
        }
    }

    /** @return array{int, Carbon} */
    private function regenerate(int $value, int $maximum, int $amount, int $interval, ?string $timestamp, Carbon $now): array
    {
        try {
            $anchor = $timestamp === null ? $now->copy() : Carbon::parse($timestamp);
        } catch (\Exception) {
            $anchor = $now->copy();
        }

        if ($anchor->year < 1 || $anchor->greaterThan($now)) {
            $anchor = $now->copy();
        }

        // Do not bank regeneration while capped or disabled, or reduce existing values.
        if ($value >= $maximum || $interval <= 0 || $amount <= 0) {
            return [$value, $now->copy()];
        }

        $ticks = intdiv((int) $anchor->diffInSeconds($now), $interval);
        $value = min($maximum, $value + $ticks * $amount);

        return [$value, $value >= $maximum ? $now->copy() : $anchor->copy()->addSeconds($ticks * $interval)];
    }
}
