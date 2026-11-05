@extends('layouts.game')

@section('title', 'Kapitanowie – The Hools')
@section('page-title', 'Kapitanowie')
@section('page-subtitle', 'Twoi dowódcy')
@section('back-url', route('start'))
@section('back-label', '← Pulpit')
@section('activeMenu', 'captains')

@section('page')
    @if (session('status'))
        <section class="panel flash-panel"><div class="panel-body" role="status">{{ session('status') }}</div></section>
    @endif
    @if ($errors->any())
        <section class="panel flash-panel"><div class="panel-body" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div></section>
    @endif

    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Aktywni kapitanowie: {{ $captains->count() }} / {{ $limit }}</h2></div>
        <div class="panel-body">
            <div class="chapter-grid">
                @forelse ($captains as $captain)
                    <article class="chapter-card">
                        <h3>{{ $captain->name }}</h3>
                        @if ($currentCaptain?->id === $captain->id)
                            <p class="muted">Aktualnie wybrany kapitan</p>
                        @else
                            <form method="POST" action="{{ route('captains.select') }}">
                                @csrf
                                <input type="hidden" name="captain_id" value="{{ $captain->id }}">
                                <button class="btn btn-secondary" type="submit">Wybierz kapitana</button>
                            </form>
                        @endif
                    </article>
                @empty
                    <p class="muted">Nie masz jeszcze aktywnego kapitana.</p>
                @endforelse
            </div>
        </div>
    </section>

    @if ($captains->count() < $limit)
        <section class="panel">
            <div class="panel-header"><h2 class="panel-title">Rekrutacja kapitana</h2></div>
            <div class="panel-body">
                <p class="muted">Rekrutacja jest natychmiastowa i bezpłatna.</p>
                <form method="POST" action="{{ route('captains.store') }}" class="stack-form">
                    @csrf
                    <label for="captain-name">Imię kapitana (2–50 znaków)</label>
                    <input id="captain-name" type="text" name="name" value="{{ is_string(old('name')) ? old('name') : '' }}" minlength="2" maxlength="50" required @error('name') aria-invalid="true" @enderror>
                    <button class="btn btn-primary" type="submit">Zrekrutuj kapitana</button>
                </form>
            </div>
        </section>
    @else
        <p class="muted">Osiągnięto limit aktywnych kapitanów.</p>
    @endif
@endsection
