@php
    use App\Enums\GameModes;
    use App\Enums\GameDifficulties;
    use App\Enums\CountdownModes;
@endphp

<div class="countdown-modes">
    <h1>Modos de cuenta atrás</h1>
    <form action="#" method="POST" id="countdown-form">
        @csrf
        <input type="hidden" name="mode" value="{{ GameModes::CONTRARELOJ->value() }}">
        <input type="hidden" name="difficulty" value="{{ GameDifficulties::FACIL->value() }}">
        
        <div class="countdown-mode">
            <button type="button" class="menu-button countdown-btn" data-time="{{ CountdownModes::TREINTA_SEGUNDOS->value() }}">
                <i class="fas fa-clock"></i> 
                <span>{{ CountdownModes::TREINTA_SEGUNDOS->value() }}</span>
            </button>
        </div>
        <div class="countdown-mode">
            <button type="button" class="menu-button countdown-btn" data-time="{{ CountdownModes::UN_MINUTO->value() }}">
                <i class="fas fa-clock"></i> 
                <span>{{ CountdownModes::UN_MINUTO->value() }}</span>
            </button>
        </div>
        <div class="countdown-mode">
            <button type="button" class="menu-button countdown-btn" data-time="{{ CountdownModes::DOS_MINUTOS->value() }}">
                <i class="fas fa-clock"></i> 
                <span>{{ CountdownModes::DOS_MINUTOS->value() }}</span>
            </button>
        </div>
        <div class="countdown-mode">
            <button type="button" class="menu-button countdown-btn" data-time="{{ CountdownModes::TRES_MINUTOS->value() }}">
                <i class="fas fa-clock"></i> 
                <span>{{ CountdownModes::TRES_MINUTOS->value() }}</span>
            </button>
        </div>
        <div class="countdown-mode">
            <button type="button" class="menu-button countdown-btn" data-time="{{ CountdownModes::CUATRO_MINUTOS->value() }}">
                <i class="fas fa-clock"></i> 
                <span>{{ CountdownModes::CUATRO_MINUTOS->value() }}</span>
            </button>
        </div>
        <div class="countdown-mode">
            <button type="button" class="menu-button countdown-btn" data-time="{{ CountdownModes::CINCO_MINUTOS->value() }}">
                <i class="fas fa-clock"></i> 
                <span>{{ CountdownModes::CINCO_MINUTOS->value() }}</span>
            </button>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    // Manejar clic en botones de tiempo
    $('.countdown-btn').on('click', function() {
        const selectedTime = $(this).data('time');
        console.log('Tiempo seleccionado:', selectedTime);
        
        // Cerrar el modal actual
        $('#countdownModesModal').modal('hide');
        
        // Abrir el modal de dificultades con el tiempo seleccionado
        setTimeout(function() {
            window.selectedGameMode = '{{ GameModes::CONTRARELOJ->value() }}';
            window.selectedCountdownTime = selectedTime;
            console.log('Variables establecidas para contrareloj:', {
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
        }, 300);
    });
});
</script>