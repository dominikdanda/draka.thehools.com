@php
    $avatar = $player->avatar ?? '🥷';
    $level = $stats?->level ?? 1;
    $currentExp = $stats?->experience ?? 0;
    $requiredExp = $requiredExp ?? 0;
    $expProgress = $expProgress ?? 0;
@endphp

<section class="player-card">
    <div class="avatar">{{ $avatar }}</div>

    <div class="player-meta">
        <h2>Gracz: {{ $player->nickname ?? 'Brak nicku' }}</h2>

        <div class="xp-line">
            <span>XP {{ number_format($currentExp, 0, ',', ' ') }} / {{ number_format($requiredExp, 0, ',', ' ') }}</span>
            <span>Poziom {{ $level }}@if($player->hasActivePremium()) · Premium @endif</span>
        </div>

        <div class="progress">
            <span style="width: {{ $expProgress }}%"></span>
        </div>
    </div>

    <div class="level-box">
        <div class="label">Poziom</div>
        <div class="value">{{ $level }}</div>
    </div>
</section>

@if ($headerCurrentCaptain !== null)
    <section class="resources captain-resources" aria-label="Zasoby kapitana">
        @foreach (['money' => ['Hajs', '💵'], 'materials' => ['Materiały', '📦'], 'chemicals' => ['Chemia', '⚗']] as $field => [$label, $icon])
            <div class="res {{ $field }}">
                <div class="res-top"><span class="res-icon" aria-hidden="true">{{ $icon }}</span><span class="label">{{ $label }}</span></div>
                <div class="res-value" data-captain-resource="{{ $field }}">{{ number_format($headerCurrentCaptain->$field, 0, ',', ' ') }}</div>
            </div>
        @endforeach
        <div class="res respect">
            <div class="res-top"><span class="res-icon" aria-hidden="true">★</span><span class="label">Respekt</span></div>
            <div class="res-value" data-captain-resource="respect">{{ number_format($headerCurrentCaptain->respect_demand, 0, ',', ' ') }} / {{ number_format($headerCurrentCaptain->respect_capacity, 0, ',', ' ') }}</div>
            <div class="small-label">Zapotrzebowanie / pojemność</div>
        </div>
    </section>
@endif