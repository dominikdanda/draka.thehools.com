@foreach (config('thehools.main_menu') as $item)
    <a
        class="nav-link {{ ($activeMenu ?? '') === $item['key'] ? 'active' : '' }}{{ ! $item['available'] ? ' module-unavailable' : '' }}"
        href="{{ route($item['route']) }}"
    >
        <span class="nav-ico">{{ $item['icon'] }}</span>
        {{ $item['label'] }}
        @unless ($item['available'])
            <span class="module-badge">Wkrótce</span>
        @endunless
    </a>
@endforeach
