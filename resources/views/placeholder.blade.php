@extends('layouts.game')

@section('title', $title ?? 'Moduł')
@section('page-title', $title ?? 'Moduł')
@section('page-subtitle', 'W przygotowaniu')
@section('back-url', route('start'))
@section('back-label', '← Pulpit')
@section('activeMenu', $activeMenu ?? 'start')

@section('page')
    <section class="panel">
        <div class="panel-body">
            <p class="muted intro-text">Ten moduł jest jeszcze w przygotowaniu.</p>
            <a href="{{ route('start') }}" class="btn btn-secondary">Wróć do pulpitu</a>
        </div>
    </section>
@endsection
