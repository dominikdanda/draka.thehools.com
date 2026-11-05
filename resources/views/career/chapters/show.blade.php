@extends('layouts.game')

@section('title', $chapter->title.' – Kariera')
@section('page-title', 'Rozdział '.$chapter->number)
@section('page-subtitle', $chapter->title)
@section('back-url', route('career.campaigns.show', $campaign))
@section('back-label', '← Rozdziały')
@section('activeMenu', 'career')

@section('page')
    <section class="panel">
        <div class="panel-body">
            <strong>Energia kariery: {{ $player->resources->energy }} / {{ $player->stats->maxEnergy() }}</strong>
            <span class="muted">· +1 co {{ $player->hasActivePremium() ? config('thehools.formulas.energy.premium_regen_seconds') : config('thehools.formulas.energy.regen_seconds') }} s</span>
        </div>
    </section>
    @if ($errors->any())
        <section class="panel flash-panel"><div class="panel-body" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div></section>
    @endif
    @if (session('stage_result'))
        @php $result = session('stage_result'); @endphp
        <section class="panel flash-panel flash-success">
            <div class="panel-body">
                <strong>Etap ukończony!</strong>
                +{{ $result['money'] }}$ · +{{ $result['experience'] }} EXP · -{{ $result['energy_spent'] }} energii
                · Mistrzostwo etapu: {{ $result['mastery_progress'] }}%
                @if ($result['chapter_mastery_advanced'])
                    · <strong>Nowy poziom mistrzostwa rozdziału!</strong>
                @endif
            </div>
        </section>
    @endif

    <div class="chapter-layout">
        <section class="panel chapter-intro">
            <div class="panel-header">
                <h2 class="panel-title">{{ $chapter->title }}</h2>
            </div>
            <div class="panel-body">
                <p class="intro-text">{{ $chapter->introduction }}</p>

                <div class="mastery-meter">
                    <div class="small-label">Mistrzostwo rozdziału</div>
                    <div class="mastery-levels">
                        @for ($i = 1; $i <= 5; $i++)
                            <span class="mastery-dot {{ $i <= $chapterProgress->mastery_level ? 'active' : '' }}"></span>
                        @endfor
                        <strong>{{ $chapterProgress->mastery_level }}/5</strong>
                    </div>
                </div>

                @unless($accessible)
                    <p class="muted lock-note"><strong>Rozdział zablokowany.</strong> Ukończ poprzedni rozdział.</p>
                @endunless
            </div>
        </section>

        <section class="panel">
            <div class="panel-header"><h2 class="panel-title">Etapy</h2></div>
            <div class="panel-body">
                <div class="stage-grid">
                    @foreach ($chapter->stages as $stage)
                        @php
                            $progress = $stageProgressMap->get($stage->id);
                            $mastery = $progress?->mastery_progress ?? 0;
                            $requirement = $stageRequirements[$stage->id];
                            $canRun = $requirement === null && ($player->resources?->energy ?? 0) >= $stage->energy_cost;
                            $isDone = $mastery >= 100;
                        @endphp
                        <article class="stage-card {{ $isDone ? 'stage-card-done' : '' }}">
                            <div class="stage-card-head">
                                <span class="stage-number">#{{ $stage->number }}</span>
                                <span class="small-label">{{ $mastery }}% mistrzostwa</span>
                            </div>

                            <h3>{{ trim($stage->title.' '.$stage->subtitle) }}</h3>

                            <div class="stage-rewards">
                                <span>⚡ {{ $stage->energy_cost }}</span>
                                <span>💵 {{ $stage->money_reward_min }}-{{ $stage->money_reward_max }}</span>
                                <span>XP {{ $stage->experience_reward }}</span>
                            </div>

                            @if ($stage->requiredItem)
                                <p class="muted">Wymaga: {{ $stage->requiredItem->name }}</p>
                            @endif
                            @if ($stage->rewardItem)
                                <p class="muted">Nagroda: {{ $stage->rewardItem->name }}</p>
                            @endif

                            <div class="progress stage-progress">
                                <span style="width: {{ min(100, intdiv($mastery, 5)) }}%"></span>
                            </div>

                            @if ($requirement !== null)
                                <p class="muted lock-note"><strong>Etap zablokowany.</strong> {{ $requirement }}</p>
                            @elseif ($accessible)
                                <form method="POST" action="{{ route('career.stages.complete', [$campaign, $chapter, $stage]) }}" class="stage-form">
                                    @csrf
                                    <button class="btn btn-primary btn-block" type="submit" @disabled(! $canRun)>
                                        {{ $canRun ? 'Wykonaj etap' : 'Brak energii' }}
                                    </button>
                                </form>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
@endsection
