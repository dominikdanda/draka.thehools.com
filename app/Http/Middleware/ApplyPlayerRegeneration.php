<?php

namespace App\Http\Middleware;

use App\Services\Player\PlayerRegenerationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApplyPlayerRegeneration
{
    public function __construct(
        private readonly PlayerRegenerationService $regenerationService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $player = Auth::user();

        if ($player !== null) {
            $this->regenerationService->apply($player);
        }

        return $next($request);
    }
}
