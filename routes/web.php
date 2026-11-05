<?php

use App\Http\Controllers\Auth\LobbyLoginController;
use App\Http\Controllers\CaptainController;
use App\Http\Controllers\Career\CampaignController;
use App\Http\Controllers\Career\ChapterController;
use App\Http\Controllers\Career\StageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StartController;
use App\Http\Middleware\EnsureModuleAvailable;
use App\Http\Middleware\RequireActiveCaptain;
use Illuminate\Support\Facades\Route;

Route::get('/login/{token}', [LobbyLoginController::class, 'login'])
    ->where('token', '[a-f0-9]{40}')
    ->name('login');

Route::middleware(['auth', RequireActiveCaptain::class, 'player.regen', EnsureModuleAvailable::class])->group(function () {
    Route::get('/start', [StartController::class, 'index'])->name('start');

    Route::get('/captains', [CaptainController::class, 'index'])->name('captains.index');
    Route::post('/captains', [CaptainController::class, 'store'])->name('captains.store');
    Route::post('/captains/select', [CaptainController::class, 'select'])->name('captains.select');

    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/skills', [ProfileController::class, 'allocateSkills'])->name('profile.skills');
    Route::post('/logout', [ProfileController::class, 'logout'])->name('logout');

    Route::prefix('career')->name('career.')->group(function () {
        Route::get('/', [CampaignController::class, 'index'])->name('index');
        Route::get('/campaigns/{campaign:slug}', [CampaignController::class, 'show'])->name('campaigns.show');
        Route::get('/campaigns/{campaign:slug}/chapters/{chapter}', [ChapterController::class, 'show'])->name('chapters.show');
        Route::post('/campaigns/{campaign:slug}/chapters/{chapter}/stages/{stage}/complete', [StageController::class, 'complete'])
            ->name('stages.complete');
    });

    Route::view('/duel', 'placeholder', ['title' => 'Pojedynki'])->name('duel.index');
    Route::view('/team', 'placeholder', ['title' => 'Ekipa'])->name('team.index');
    Route::view('/investments', 'placeholder', ['title' => 'Inwestycje'])->name('investments.index');
    Route::view('/drugs', 'placeholder', ['title' => 'Narkotyki'])->name('drugs.index');
    Route::view('/arsenal', 'placeholder', ['title' => 'Zbrojownia'])->name('arsenal.index');
    Route::view('/quest', 'placeholder', ['title' => 'Zadania'])->name('quest.index');
    Route::view('/safe', 'placeholder', ['title' => 'Sejf'])->name('safe.index');
    Route::view('/ranking', 'placeholder', ['title' => 'Ranking'])->name('ranking.index');
    Route::view('/achievements', 'placeholder', ['title' => 'Osiągnięcia'])->name('achievements.index');
});
