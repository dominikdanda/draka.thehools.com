<?php

namespace App\Http\Controllers;

use App\Http\Requests\AllocateSkillPointsRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\Player\SkillPointAllocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly SkillPointAllocationService $skillPointAllocationService,
    ) {}

    public function index(): View
    {
        $player = Auth::user();
        $player->load(['stats', 'resources']);

        return view('profile.index', [
            'player' => $player,
            'stats' => $player->stats,
            'resources' => $player->resources,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $player = Auth::user();
        $player->update($request->validated());

        return redirect()
            ->route('profile.index')
            ->with('status', 'Profil zaktualizowany.');
    }

    public function allocateSkills(AllocateSkillPointsRequest $request): RedirectResponse
    {
        $player = Auth::user();

        $this->skillPointAllocationService->allocate(
            $player,
            $request->validated('stat'),
            (int) $request->validated('points'),
        );

        return redirect()
            ->route('profile.index')
            ->with('status', 'Punkty umiejętności rozdane.');
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->away(config('thehools.lobby_url'));
    }
}
