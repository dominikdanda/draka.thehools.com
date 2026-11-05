<?php

namespace App\Providers;

use App\Services\Game\ExperienceCalculator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts.game', function ($view) {
            $player = Auth::user();

            if ($player === null) {
                return;
            }

            $player->loadMissing(['stats', 'resources']);

            $stats = $player->stats;
            $resources = $player->resources;
            $calculator = app(ExperienceCalculator::class);
            $requiredExp = $stats ? $calculator->requiredForLevel($stats->level) : 0;
            $expProgress = $requiredExp > 0 && $stats
                ? min(100, (int) floor(($stats->experience / $requiredExp) * 100))
                : 0;

            $view->with([
                'player' => $player,
                'stats' => $stats,
                'resources' => $resources,
                'requiredExp' => $requiredExp,
                'expProgress' => $expProgress,
                'headerCaptains' => request()->attributes->get('active_captains', collect()),
                'headerCurrentCaptain' => request()->attributes->get('current_captain'),
            ]);
        });
    }
}
