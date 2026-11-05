@extends('layouts.game')

@section('title', 'Kariera – The Hools')
@section('page-title', 'Kariera')
@section('page-subtitle', 'Kampanie i historie')
@section('back-url', route('start'))
@section('back-label', '← Pulpit')
@section('activeMenu', 'career')

@section('page')
    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Kampanie</h2></div>
        <div class="panel-body">
            <div class="campaign-grid">
                @forelse ($campaigns as $campaign)
                    <article class="campaign-card">
                        <div class="campaign-card-body">
                            <div class="small-label">{{ strtoupper($campaign->type) }}</div>
                            <h3>{{ $campaign->name }}</h3>
                            <p class="muted">{{ $campaign->description }}</p>
                            <p class="muted">{{ $campaign->chapters_count }} rozdziałów</p>
                        </div>
                        <a class="btn btn-primary" href="{{ route('career.campaigns.show', $campaign) }}">Wejdź</a>
                    </article>
                @empty
                    <p>Brak aktywnych kampanii. Uruchom seeder kariery.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
