<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        $moduleKey = explode('.', $request->route()->getName() ?? '')[0];
        $module = collect(config('thehools.main_menu'))->firstWhere('key', $moduleKey);

        if ($module !== null && ! $module['available']) {
            return response()->view('placeholder', [
                'title' => $module['label'],
                'activeMenu' => $module['key'],
            ], $request->isMethod('GET') || $request->isMethod('HEAD') ? 200 : 503);
        }

        return $next($request);
    }
}
