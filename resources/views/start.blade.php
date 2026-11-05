@extends('layouts.game')

@section('title', 'The Hools – Start')
@section('page-title', 'The Hools')
@section('page-subtitle', 'Panel gracza')
@section('activeMenu', 'start')

@section('page')
    @if (session('status'))
        <section class="panel flash-panel"><div class="panel-body" role="status">{{ session('status') }}</div></section>
    @endif
    <section class="dashboard-grid">
        <div class="left-col">
            <section class="panel">
                <div class="panel-body">
                    <div class="reward-card">
                        <div>
                            <div class="small-label">Główna akcja</div>
                            <h2 class="section-title">Kariera</h2>
                            <p class="muted intro-text">
                                Zużywaj energię, zdobywaj EXP, kasę i przedmioty kariery w kampanii Classic.
                            </p>
                            <a href="{{ route('career.index') }}" class="btn btn-primary">Idź do kariery</a>
                        </div>
                        <div class="reward-visual"></div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Szybki podgląd</h2>
                </div>
                <div class="panel-body">
                    <div class="cards-4">
                        <article class="feature-card">
                            <div>
                                <div class="feature-art">⚡</div>
                                <h3>Atak</h3>
                                <p>{{ $stats?->attackPoints() ?? 0 }} pkt</p>
                            </div>
                        </article>
                        <article class="feature-card">
                            <div>
                                <div class="feature-art">🛡️</div>
                                <h3>Obrona</h3>
                                <p>{{ $stats?->defensePoints() ?? 0 }} pkt</p>
                            </div>
                        </article>
                        <article class="feature-card">
                            <div>
                                <div class="feature-art">🎯</div>
                                <h3>Punkty UM</h3>
                                <p>{{ $stats?->skill_points_unspent ?? 0 }} wolnych</p>
                            </div>
                        </article>
                        <article class="feature-card">
                            <div>
                                <div class="feature-art">📍</div>
                                <h3>Miasto</h3>
                                <p>{{ $player->city ?? 'Nie ustawiono' }}</p>
                            </div>
                        </article>
                    </div>
                </div>
            </section>
        </div>

        <div class="right-col">
            <section class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">Alpha 0.1</h2>
                </div>
                <div class="panel-body">
                    <p class="muted intro-text">
                        Dostępne: dashboard, profil, regeneracja, pełna kariera Classic Campaign.
                    </p>
                </div>
            </section>
        </div>
    </section>
@endsection
