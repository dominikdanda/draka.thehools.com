@extends('layouts.game')

@section('title', $campaign->name.' – Kariera')
@section('page-title', $campaign->name)
@section('page-subtitle', 'Lista rozdziałów')
@section('back-url', route('career.index'))
@section('back-label', '← Kampanie')
@section('activeMenu', 'career')

@section('page')
    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Rozdziały</h2></div>
        <div class="panel-body">
            <div class="chapter-grid">
                @forelse ($chapters as $chapter)
                    @php
                        $progress = $player->chapterProgress->firstWhere('chapter_id', $chapter->id);
                        $mastery = $progress?->mastery_level ?? 0;
                    @endphp
                    <article class="chapter-card">
                        <div class="chapter-card-top">
                            <span class="chapter-badge">{{ $chapter->number }}</span>
                            <span class="small-label">Mistrzostwo {{ $mastery }}/5</span>
                        </div>
                        <h3>{{ $chapter->title }}</h3>
                        <p class="muted">{{ \Illuminate\Support\Str::limit($chapter->introduction, 140) }}</p>
                        @if ($requirements[$chapter->id] === [])
                            <a class="btn btn-secondary btn-block" href="{{ route('career.chapters.show', [$campaign, $chapter]) }}">Otwórz rozdział</a>
                        @else
                            <p class="muted lock-note"><strong>Rozdział zablokowany.</strong></p>
                            @foreach ($requirements[$chapter->id] as $requirement)
                                <p class="muted">{{ $requirement }}</p>
                            @endforeach
                        @endif
                    </article>
                @empty
                    <p class="muted">Brak rozdziałów w tej kampanii.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
