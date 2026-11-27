@php
    use App\Enums\GameModes;
    use App\Enums\GameDifficulties;
    use App\Enums\CountdownModes;
@endphp

<div class="difficulty-modes">
    <h1>Selecciona la dificultad</h1>
    <form action="{{ route('game.start') }}" method="POST" id="difficulty-form">
        @csrf
        <input type="hidden" name="mode" id="selected-mode" value="">
        <input type="hidden" name="countdown_time" id="selected-countdown-time" value="">
        
        <div class="difficulty-mode">
            <button type="submit" class="menu-button" name="difficulty" value="{{ GameDifficulties::FACIL->value() }}">
                <i class="fas fa-star"></i> 
                <span>{{ GameDifficulties::FACIL->value() }}</span>
                <small>Sin letras complejas</small>
            </button>
        </div>
        <div class="difficulty-mode">
            <button type="submit" class="menu-button" name="difficulty" value="{{ GameDifficulties::MEDIO->value() }}">
                <i class="fas fa-star"></i><i class="fas fa-star"></i> 
                <span>{{ GameDifficulties::MEDIO->value() }}</span>
                <small>Letras complejas</small>
            </button>
        </div>
        <div class="difficulty-mode">
            <button type="submit" class="menu-button" name="difficulty" value="{{ GameDifficulties::DIFICIL->value() }}">
                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i> 
                <span>{{ GameDifficulties::DIFICIL->value() }}</span>
                <small>Letras en orden</small>
            </button>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    // Cerrar el modal cuando se envía el formulario
    $('#difficulty-form').on('submit', function(e) {
        // Log para debuggear los valores antes del envío
        console.log('Valores antes del envío:', {
            mode: $('#selected-mode').val(),
            countdown_time: $('#selected-countdown-time').val(),
            difficulty: $(e.target).find('button[type="submit"]').val()
        });
        
        $('#difficultyModesModal').modal('hide');
    });
    
    // Usar los valores seleccionados del modal anterior
    if (window.selectedGameMode) {
        $('#selected-mode').val(window.selectedGameMode);
        console.log('Modo seleccionado:', window.selectedGameMode);
    } else {
        console.error('No se encontró selectedGameMode');
    }
    
    if (window.selectedCountdownTime) {
        $('#selected-countdown-time').val(window.selectedCountdownTime);
        console.log('Tiempo seleccionado:', window.selectedCountdownTime);
    } else {
        console.log('No hay tiempo seleccionado (modo infinito)');
    }
});
</script>