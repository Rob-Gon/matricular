@extends('layouts.master-layout')

@push('styles')
    @vite(['resources/sass/main/_game.scss', 'resources/js/main/game-manager.js'])
    <link rel="icon" href="{{ asset('favicon.ico') }}">
@endpush

@section('content')
<div class="game-container">

    @if($countdownTotal)
        <div class="game-countdown">
            <h1>Tiempo restante: <span id="countdown-timer">{{ $countdownRemaining }}</span></h1>
            <div class="countdown-progress">
                <div class="progress-bar">
                    <div class="progress-fill" id="countdown-progress"></div>
                </div>
            </div>
        </div>
    @endif
    
    <div class="game-score">
        <h2>Puntuación: <span id="score">{{ $run->score }}</span> puntos</h2>
    </div>
    <div class="license-plate">
        <div class="plate-line top"></div>
        <div class="plate-line bottom"></div>
        <div class="screw screw-tl"></div>
        <div class="screw screw-tr"></div>
        <div class="screw screw-bl"></div>
        <div class="screw screw-br"></div>
        <div class="plate-content">
            @foreach($run->plateLetters() as $letter)
                <span class="letter">{{ $letter }}</span>
            @endforeach
        </div>
        <div class="plate-line bottom"></div>
    </div>

    <div class="input-container">
        <form id="game-form" class="word-form gap-2"
              data-check-word-url="{{ route('game.check-word', $run) }}"
              data-change-plate-url="{{ route('game.change-plate', $run) }}"
              data-end-url="{{ route('game.end', $run) }}">
            @csrf
            <input type="hidden" name="mode" id="mode" value="{{ $mode }}">
            @if($countdownTotal)
                <input type="hidden" id="countdown_total" value="{{ $countdownTotal }}">
                <input type="hidden" id="countdown_remaining" value="{{ $countdownRemaining }}">
            @endif

            <div class="form-group">
                <input type="text" 
                        name="word" 
                        id="word" 
                        class="form-control word-input" 
                        placeholder="Escribe una palabra..." 
                        autocomplete="off"
                        minlength="3"
                        maxlength="50" 
                        required>
            </div>
            <button type="submit" class="btn btn-primary submit-word" id="btn-submit-word">Enviar</button>
            <button type="button" class="btn btn-danger change-plate" id="btn-change-plate">Matrícula imposible</button>
        </form>
    </div>

    <div id="message"></div>

    <div class="game-buttons">
        @if($mode === 'Infinito')
            <button type="button" class="btn btn-danger end-game change-plate" id="btn-end-game">
                <i class="fas fa-stop-circle"></i> Terminar partida
            </button>
        @endif
    </div>
</div>

<!-- Modal de confirmación para finalizar la partida-->
<div class="modal fade" id="endGameModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="endGameModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center">
            <div class="modal-header">
                <h5 class="modal-title w-100" id="endGameModalLabel">Confirmar fin de partida</h5>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de que quieres terminar la partida?</p>
                <p class="text-muted">Tu puntuación actual se guardará.</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="confirm-end-game">Terminar partida</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de fin de partida -->
<div class="modal fade" id="gameOverModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="gameOverModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center">
        <div class="modal-header">
            <h5 class="modal-title w-100" id="gameOverModalLabel">Juego terminado</h5>
        </div>
        <div class="modal-body">
            <p>Puedes elegir entre volver a jugar o volver al menú</p>
            <form method="POST" action="{{ route('game.start') }}" class="d-inline">
                @csrf
                <input type="hidden" name="mode" value="{{ $run->mode }}">
                <input type="hidden" name="difficulty" value="{{ $run->difficulty }}">
                <input type="hidden" name="countdown_time" value="{{ $run->countdown_time }}">
                <button type="submit" class="btn btn-primary m-2">Volver a jugar</button>
            </form>
            <a href="{{ route('menu.view') }}" class="btn btn-secondary m-2">Volver al menú</a>
        </div>
        </div>
    </div>
</div>
@endsection
