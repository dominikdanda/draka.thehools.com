<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LobbyLoginAction;
use App\Http\Controllers\Controller;
use App\Services\Player\CurrentCaptainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LobbyLoginController extends Controller
{
    public function __construct(
        private readonly LobbyLoginAction $lobbyLoginAction,
    ) {}

    public function login(string $token, Request $request, CurrentCaptainService $currentCaptainService): RedirectResponse
    {
        $clientDetails = $request->ip().'#'.$request->userAgent();

        $player = $this->lobbyLoginAction->execute($token, $clientDetails);
        $captain = $currentCaptainService->resolve($player, $request->session());

        return redirect()->route($captain === null ? 'captains.index' : 'start');
    }
}
