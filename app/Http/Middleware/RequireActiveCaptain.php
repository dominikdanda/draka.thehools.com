<?php

namespace App\Http\Middleware;

use App\Services\Player\CurrentCaptainService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveCaptain
{
    public function __construct(private readonly CurrentCaptainService $currentCaptainService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $captains = $request->user()->captains()->active()->orderBy('id')->get();
        $captain = $this->currentCaptainService->resolve($request->user(), $request->session(), $captains);
        $request->attributes->set('active_captains', $captains);
        $request->attributes->set('current_captain', $captain);

        if ($captain === null && ! $request->routeIs('captains.*', 'logout')) {
            return redirect()->route('captains.index');
        }

        return $next($request);
    }
}
