<?php

namespace App\Http\Controllers;

use App\Actions\Captains\RecruitCaptainAction;
use App\Http\Requests\RecruitCaptainRequest;
use App\Http\Requests\SelectCaptainRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CaptainController extends Controller
{
    public function index(Request $request): View
    {
        $captains = $request->attributes->get('active_captains');
        $currentCaptain = $request->attributes->get('current_captain');

        return view('captains.index', [
            'captains' => $captains,
            'currentCaptain' => $currentCaptain,
            'limit' => (int) config('thehools.captains.starting_limit'),
        ]);
    }

    public function store(RecruitCaptainRequest $request, RecruitCaptainAction $recruit): RedirectResponse
    {
        $captain = $recruit->execute($request->user(), $request->validated('name'));
        $request->session()->put('current_captain_id', $captain->id);

        return redirect()->route('start')->with('status', 'Kapitan został zrekrutowany i wybrany.');
    }

    public function select(SelectCaptainRequest $request): RedirectResponse
    {
        $captain = $request->user()->captains()->active()->find($request->validated('captain_id'));
        if ($captain === null) {
            throw ValidationException::withMessages([
                'captain_id' => 'Wybrany kapitan jest niedostępny.',
            ]);
        }
        $request->session()->put('current_captain_id', $captain->id);

        return redirect()->to($this->selectionReturnUrl($request))->with('status', 'Wybrano kapitana.');
    }

    private function selectionReturnUrl(Request $request): string
    {
        $path = $request->input('return_to');
        if (is_string($path) && preg_match('~\A/(?!/)[a-zA-Z0-9/_-]*\z~', $path)) {
            try {
                $route = app('router')->getRoutes()->match(Request::create($path, 'GET'));
                if (in_array($route->getName(), ['start', 'career.index', 'career.campaigns.show', 'career.chapters.show', 'captains.index', 'profile.index'], true)) {
                    return route($route->getName(), $route->parameters());
                }
            } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                // Unknown destinations fall back to Captain management.
            }
        }

        return route('captains.index');
    }
}
