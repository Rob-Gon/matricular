@extends('layouts.master-layout')

@php
    use App\Enums\GameModes;
@endphp

@push('styles')
    @vite(['resources/sass/components/_menu-button.scss', 'resources/sass/main/_menu.scss'])
    <link rel="icon" href="{{ asset('favicon.ico') }}">
@endpush

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="text-center mt-3">
                    <h1 class="display-3 mb-0">
                        Bienvenido, {{ Auth::user()->name }}
                    </h1>
                    <h2 class="display-6 mb-0">
                        Elige entre los modos de juego disponibles:
                    </h2>
                </div>
                <div class="button-container">
                    <div class="row">
                        <div class="col-md-6 px-4">
                            <a class="menu-button" href="{{ route('game.setup', ['mode' => GameModes::CONTRARELOJ->value]) }}"><i class="fas fa-clock"></i> <span>Contrarreloj</span></a>
                        </div>
                        <div class="col-md-6 px-4">
                            <a class="menu-button" href="{{ route('game.setup', ['mode' => GameModes::INFINITO->value]) }}"><i class="fas fa-infinity"></i> <span>Sin límite</span></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
