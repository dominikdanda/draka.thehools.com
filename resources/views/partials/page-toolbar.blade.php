<div class="topbar-main">
    <div class="mobile-left">
        <button class="menu-btn mobile-only" id="openDrawer" aria-label="Otwórz menu">☰</button>

        @hasSection('back-url')
            <a href="@yield('back-url')" class="btn btn-secondary btn-back mobile-only">@yield('back-label', '←')</a>
        @endif

        <div class="logo-wrap">
            <h1 class="logo">@yield('page-title', 'The Hools')</h1>
            @hasSection('page-subtitle')
                <span class="sublogo">@yield('page-subtitle')</span>
            @endif
        </div>
    </div>

    <div class="top-actions">
        @include('partials.captain-selector')
        @hasSection('back-url')
            <a href="@yield('back-url')" class="btn btn-secondary btn-back desktop-only">@yield('back-label', '← Wróć')</a>
        @endif
        <a href="{{ route('profile.index') }}" class="icon-btn" aria-label="Profil">👤</a>
    </div>
</div>
