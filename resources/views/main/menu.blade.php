@extends('layouts.master-layout')

@php
    use App\Enums\GameModes;
    use App\Enums\GameDifficulties;
    use App\Enums\CountdownModes;
@endphp

@push('styles')
    @vite(['resources/sass/components/_menu-button.scss', 'resources/sass/components/_modal.scss', 'resources/sass/main/_menu.scss'])
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
                            <button type="button" class="menu-button" id="btn-countdown-modes"><i class="fas fa-clock"></i> <span>{{ GameModes::CONTRARELOJ->value() }}</span></button>
                        </div>
                        <div class="col-md-6 px-4">
                            <button type="button" class="menu-button" id="btn-infinite-mode"><i class="fas fa-infinity"></i> <span>{{ GameModes::INFINITO->value() }}</span></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="countdownModesModal" tabindex="-1" aria-labelledby="countdownModesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="countdownModesModalLabel">Selecciona el tiempo de juego</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="countdownModesContent">
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="difficultyModesModal" tabindex="-1" aria-labelledby="difficultyModesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="difficultyModesModalLabel">Selecciona la dificultad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="difficultyModesContent">
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Botón para modo contrareloj
    $('#btn-countdown-modes').on('click', function() {
        console.log('Abriendo modal de tiempos para contrareloj');
        $.ajax({
            url: '{{ route("game.countdown-modes") }}',
            method: 'GET',
            success: function(response) {
                $('#countdownModesContent').html(response);
                $('#countdownModesModal').modal('show');
            },
            error: function() {
                alert('Error al cargar los modos de cuenta atrás');
            }
        });
    });

    // Botón para modo infinito
    $('#btn-infinite-mode').on('click', function() {
        console.log('Abriendo modal de dificultades para infinito');
        // Establecer el modo infinito y abrir directamente el modal de dificultades
        window.selectedGameMode = '{{ GameModes::INFINITO->value() }}';
        window.selectedCountdownTime = ''; // No hay tiempo para modo infinito
        console.log('Variables establecidas:', {
            selectedGameMode: window.selectedGameMode,
            selectedCountdownTime: window.selectedCountdownTime
        });
        
        // Cargar el modal de dificultades dinámicamente
        $.ajax({
            url: '{{ route("game.difficulty-modes") }}',
            method: 'GET',
            success: function(response) {
                $('#difficultyModesContent').html(response);
                $('#difficultyModesModal').modal('show');
            },
            error: function() {
                alert('Error al cargar las dificultades');
            }
        });
    });
});
</script>
@endpush 