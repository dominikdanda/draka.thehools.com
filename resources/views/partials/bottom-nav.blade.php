@php
    $modules = collect(config('thehools.main_menu'))->keyBy('key');
@endphp
@foreach (['start' => 'Start', 'career' => 'Kariera', 'captains' => 'Kapitanowie', 'investments' => 'Rozwój', 'duel' => 'Walka', 'profile' => 'Profil'] as $key => $label)
    @php $item = $modules[$key]; @endphp
    <a href="{{ route($item['route']) }}" class="bottom-link {{ ($activeMenu ?? '') === $key ? 'active' : '' }}{{ ! $item['available'] ? ' module-unavailable' : '' }}">
        <span>{{ $item['icon'] }}</span><span>{{ $label }}</span>
        @unless ($item['available'])
            <span class="module-badge">Wkrótce</span>
        @endunless
    </a>
@endforeach
