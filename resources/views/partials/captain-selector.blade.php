@if ($headerCaptains->isNotEmpty())
    <div class="captain-selector">
        @if ($headerCaptains->count() === 1)
            <span class="small-label">Kapitan</span>
            <strong>{{ $headerCurrentCaptain?->name ?? $headerCaptains->first()->name }}</strong>
        @else
            <form method="POST" action="{{ route('captains.select') }}">
                @csrf
                <input type="hidden" name="return_to" value="{{ request()->getPathInfo() }}">
                <label class="small-label" for="header-captain">Kapitan</label>
                <div class="captain-selector-controls">
                    <select id="header-captain" name="captain_id" required>
                        @if ($headerCurrentCaptain === null)
                            <option value="" selected disabled>Wybierz kapitana</option>
                        @endif
                        @foreach ($headerCaptains as $headerCaptain)
                            <option value="{{ $headerCaptain->id }}" @selected($headerCurrentCaptain?->id === $headerCaptain->id)>{{ $headerCaptain->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-secondary" type="submit" data-captain-submit>Wybierz</button>
                </div>
            </form>
        @endif
        @error('captain_id')
            <span class="small-label" role="alert">{{ $message }}</span>
        @enderror
    </div>
@endif
