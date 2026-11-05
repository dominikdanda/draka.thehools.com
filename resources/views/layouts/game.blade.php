@extends('layouts.app')

@section('content')
    <div class="overlay" id="overlay"></div>

    <aside class="drawer" id="drawer" aria-hidden="true">
        <div class="drawer-head">
            <div>
                <strong class="drawer-logo">The Hools <span>2.0</span></strong>
                <span class="small-label">Menu gracza</span>
            </div>
            <button class="icon-btn" id="closeDrawer" aria-label="Zamknij menu">✕</button>
        </div>

        <nav class="drawer-nav">
            @include('partials.nav-links', ['activeMenu' => trim($__env->yieldContent('activeMenu')) ?: 'start'])
        </nav>

        <div class="drawer-foot">Twoja dzielnica. Twoje zasady.</div>
    </aside>

    <div class="app">
        <div class="layout">
            <aside class="sidebar">
                <div class="brand">
                    <div class="brand-mark">H</div>
                    <div>
                        <h1>The Hools <span>2.0</span></h1>
                        <small>Alpha MVP 0.1</small>
                    </div>
                </div>

                @include('partials.nav-links', ['activeMenu' => trim($__env->yieldContent('activeMenu')) ?: 'start'])
            </aside>

            <main class="main">
                <header class="topbar">
                    @include('partials.page-toolbar')

                    @isset($player)
                        <div class="player-strip">
                            @include('partials.player-strip', [
                                'player' => $player,
                                'stats' => $stats,
                                'resources' => $resources,
                                'requiredExp' => $requiredExp,
                                'expProgress' => $expProgress,
                            ])
                        </div>
                    @endisset
                </header>

                <div class="page-content">
                    @yield('page')
                </div>

                <nav class="bottom-nav mobile-only" aria-label="Nawigacja dolna">
                    @include('partials.bottom-nav', ['activeMenu' => trim($__env->yieldContent('activeMenu')) ?: 'start'])
                </nav>
            </main>
        </div>
    </div>
@endsection
