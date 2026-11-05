@extends('layouts.game')

@section('title', 'Profil – The Hools')
@section('page-title', 'Profil')
@section('page-subtitle', 'Ustawienia gracza')
@section('back-url', route('start'))
@section('back-label', '← Pulpit')
@section('activeMenu', 'profile')

@section('page')
    @if (session('status'))
        <section class="panel flash-panel">
            <div class="panel-body">{{ session('status') }}</div>
        </section>
    @endif

    <div class="profile-grid">
        <section class="panel">
            <div class="panel-header"><h2 class="panel-title">Dane gracza</h2></div>
            <div class="panel-body">
                <form method="POST" action="{{ route('profile.update') }}" class="stack-form">
                    @csrf
                    @method('PUT')

                    <label>
                        Avatar (emoji)
                        <input type="text" name="avatar" value="{{ old('avatar', $player->avatar) }}" maxlength="16">
                    </label>

                    <label>
                        Miasto
                        <input type="text" name="city" value="{{ old('city', $player->city) }}" maxlength="100">
                    </label>

                    <label>
                        Opis
                        <textarea name="description" rows="4" maxlength="1000">{{ old('description', $player->description) }}</textarea>
                    </label>

                    <button class="btn btn-primary" type="submit">Zapisz profil</button>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header"><h2 class="panel-title">Punkty umiejętności</h2></div>
            <div class="panel-body">
                <div class="stat-summary">
                    <div><span class="small-label">Wolne</span><strong>{{ $stats?->skill_points_unspent ?? 0 }}</strong></div>
                    <div><span class="small-label">Atak</span><strong>{{ $stats?->attackPoints() ?? 0 }}</strong></div>
                    <div><span class="small-label">Obrona</span><strong>{{ $stats?->defensePoints() ?? 0 }}</strong></div>
                    <div><span class="small-label">Max energia</span><strong>{{ $stats?->maxEnergy() ?? 0 }}</strong></div>
                </div>

                <form method="POST" action="{{ route('profile.skills') }}" class="stack-form">
                    @csrf
                    <label>
                        Statystyka
                        <select name="stat" required>
                            <option value="attack">Atak (+{{ config('thehools.formulas.player.attack_per_skill_point') }})</option>
                            <option value="defense">Obrona (+{{ config('thehools.formulas.player.defense_per_skill_point') }})</option>
                            <option value="energy">Energia (+{{ config('thehools.formulas.player.energy_per_skill_point') }})</option>
                        </select>
                    </label>
                    <label>
                        Punkty
                        <input type="number" name="points" min="1" value="1" required>
                    </label>
                    <button class="btn btn-secondary" type="submit">Rozdaj punkty</button>
                </form>
            </div>
        </section>
    </div>

    <section class="panel">
        <div class="panel-body panel-actions">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn btn-secondary" type="submit">Wyloguj do Lobby</button>
            </form>
        </div>
    </section>
@endsection
