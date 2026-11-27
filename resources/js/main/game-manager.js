/**
     * Game Manager - Gestor del juego
     * Este archivo contiene todas las funciones JavaScript utilizadas en los modos de juego
 */

// Rutas de las peticiones AJAX
window.gameRoutes = window.gameRoutes || {
    checkWord: '/game/check-word',
    changePlate: '/game/change-plate',
    resetRun: '/game/reset-run'
};

/**
 * Clase que gestiona toda la lógica del juego
 */
class GameManager {
    
    /**
     * Constructor de la clase GameManager
     */
    constructor() {
        // Inicializar las variables de la cuenta atrás
        this.countdownInterval = null;
        this.countdownTime = null;
        this.initialTime = null;
        
        // Obtener el modo de juego
        this.gameMode = $('#mode').val();
        
        // Inicializar los event listeners
        this.initializeEventListeners();
        
        // Inicializar la cuenta atrás si el modo es contrareloj
        this.initializeCountdown();
    }

    /**
     * Inicializar todos los event listeners del juego
     */
    initializeEventListeners() {
        // Prevenir pegar en el input
        $('#word').on('paste', function(e) {
            e.preventDefault();
            return false;
        });

        // Prevenir pegar con Ctrl+V
        $('#word').on('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'v') {
                e.preventDefault();
                return false;
            }
        });

        // Prevenir pegar con el botón derecho del navegador
        $('#word').on('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });

        // Función para comprobar la palabra
        $('#game-form').on('submit', function(e) {
            e.preventDefault();
            GameManager.checkWord();
        });

        // Función para cambiar la matrícula
        $('#btn-change-plate').on('click', function(e) {
            e.preventDefault();
            GameManager.changePlate();
        });

        // Función para terminar la partida
        $('#btn-end-game').on('click', function(e) {
            e.preventDefault();
            // Mostrar modal de confirmación
            $('#endGameModal').modal('show');
        });

        // Función para confirmar el fin de partida
        $('#confirm-end-game').on('click', function(e) {
            e.preventDefault();
            $('#endGameModal').modal('hide');
            GameManager.endRun();
        });

        // Función para reiniciar la run en caso de que se cierre la pestaña
        window.addEventListener('beforeunload', function() {
            GameManager.resetRun();
        });
    }

    /**
     * Inicializar la cuenta atrás si el modo es contrareloj
     */
    initializeCountdown() {
        if (this.gameMode === 'Contrareloj') {
            const countdownTime = parseInt($('#countdown_time').val());
            this.startCountdown(countdownTime);
        }
    }

    /**
     * Iniciar la cuenta atrás
     * @param {number} countdownTime - Tiempo en segundos
     */
    startCountdown(countdownTime) {
        // Inicializa el tiempo inicial y el tiempo actual
        this.initialTime = countdownTime;
        this.countdownTime = this.initialTime;

        // Actualiza el display de la cuenta atrás
        this.updateCountdownDisplay();
        
        // Inicia el intervalo de la cuenta atrás
        this.countdownInterval = setInterval(() => {
            // Disminuye el tiempo en 1 segundo
            this.countdownTime--;
            this.updateCountdownDisplay();
            if (this.countdownTime <= 0) {
                GameManager.endRun(this.countdownInterval);
            }
        }, 1000);
    }

    /**
     * Actualiza el display de la cuenta atrás
     */
    updateCountdownDisplay() {
        const minutes = Math.floor(this.countdownTime / 60); // Calcula los minutos
        const seconds = this.countdownTime % 60; // Calcula los segundos
        
        // Si hay minutos, se muestra el tiempo en minutos y segundos, si no, se muestra el tiempo en segundos
        let timeDisplay;
        if (minutes > 0) {
            timeDisplay = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        } else {
            timeDisplay = `${seconds}s`;
        }
        
        // Actualizar el timer
        $('#countdown-timer').text(timeDisplay);
        
        // Actualizar la barra de progreso
        const progressPercent = (this.countdownTime / this.initialTime) * 100;
        $('#countdown-progress').css('width', `${progressPercent}%`);
        
        // Cambiar color de la barra según el tiempo restante
        if (this.countdownTime <= 10) {
            $('#countdown-progress').css('background-color', '#dc3545'); // Rojo
        } else {
            $('#countdown-progress').css('background-color', '#ffc107'); // Amarillo
        }
    }

    /**
     * Comprobar la palabra enviada por el usuario
     */
    static checkWord() {
        $.ajax({
            url: window.gameRoutes.checkWord,
            method: 'POST',
            data: {
                // Datos del formulario a enviar
                word: $('#word').val(),
                char1: $('#char1').val(),
                char2: $('#char2').val(),
                char3: $('#char3').val(),
                run_id: $('#run_id').val(),
                // Token de seguridad
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            // Maneja la respuesta del servidor
            success: function(response) {
                GameManager.handleCheckWordResponse(response);
            },
            // Maneja el error
            error: function(error) {
                console.error('Error al comprobar palabra:', error);
                $('#message').stop().show();
                $('#message').html('<div class="alert alert-danger">Error al procesar la palabra</div>');
            }
        });
    }

    /**
     * Cambiar la matrícula actual
     */
    static changePlate() {
        $.ajax({
            url: window.gameRoutes.changePlate,
            method: 'POST',
            data: {
                // Datos del formulario a enviar
                char1: $('#char1').val(),
                char2: $('#char2').val(),
                char3: $('#char3').val(),
                run_id: $('#run_id').val(),
                // Token de seguridad
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            // Maneja la respuesta del servidor
            success: function(response) {
                GameManager.handleChangePlateResponse(response);
            },
            // Maneja el error
            error: function(error) {
                console.error('Error al cambiar matrícula:', error);
                $('#message').stop().show();
                $('#message').html('<div class="alert alert-danger">Error al cambiar la matrícula</div>');
            }
        });
    }

    /**
     * Reiniciar la run cuando se cierra la pestaña
     */
    static resetRun() {
        // Envía una petición POST a la ruta de reinicio de la run
        fetch(window.gameRoutes.resetRun, {
            method: 'POST',
            // Cabeceras de la petición
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            // Datos del formulario a enviar
            body: JSON.stringify({
                run_id: $('#run_id').val()
            })
        // Maneja el error de la petición
        }).catch(error => {
            console.error('Error al reiniciar run:', error);
        });
    }

    /**
     * Terminar la run cuando se acaba el tiempo
     * @param {number} countdownInterval - Intervalo de la cuenta atrás
     */
    static endRun(countdownInterval = null) {
        // Si hay un intervalo de cuenta atrás, se limpia
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }

        // Mostrar el modal de fin de juego
        $('#gameOverModal').modal({
            backdrop: 'static',
            keyboard: false
        });
        
        $('#gameOverModal').modal('show');

        // Deshabilitar el formulario
        $('#game-form').find('input, button').prop('disabled', true);

        // Botón volver a jugar
        $('#play-again-btn').off('click').on('click', function() {
            location.reload();
        });

        // Botón volver al menú
        $('#go-menu-btn').off('click').on('click', function() {
            window.location.href = '/menu';
        });
    }

    /**
     * Manejo de la respuesta del servidor al comprobar una palabra
     */
    static handleCheckWordResponse(response) {
        // Muestra el mensaje de éxito o error
        $('#message').stop().show();
        
        // Muestra el mensaje de éxito o error
        if (response.success) {
            $('#message').html('<div class="alert alert-success">' + response.message + '</div>');
        } else {
            $('#message').html('<div class="alert alert-danger">' + response.message + '</div>');
        }
        
        // Actualiza la puntuación
        $('#score').text(response.score);
        
        // Actualiza la matrícula
        GameManager.updatePlate(response.chars);
        
        // Limpia el input de la palabra
        $('#word').val('');
    }

    /**
     * Manejo de la respuesta del servidor al cambiar la matrícula
     */
    static handleChangePlateResponse(response) {
        // Muestra el mensaje de éxito o error
        $('#message').stop().show();
        $('#message').html('<div class="alert alert-danger">' + response.message + '</div>');

        // Actualiza la puntuación
        $('#score').text(response.score);

        // Actualiza la matrícula
        GameManager.updatePlate(response.chars);

        // Limpia el input de la palabra
        $('#word').val('');
    }

    /**
     * Actualiza la matrícula en la interfaz
     */
    static updatePlate(chars) {
        // Actualiza los inputs de la matrícula
        $('#char1').val(chars[0]);
        $('#char2').val(chars[1]);
        $('#char3').val(chars[2]);

        // Actualiza los spans de la matrícula
        $('.letter').eq(0).text(chars[0]);
        $('.letter').eq(1).text(chars[1]);
        $('.letter').eq(2).text(chars[2]);
    }

    /**
     * Inicializa el juego cuando el DOM está listo
     */
    static init() {
        $(document).ready(function() {
            new GameManager();
        });
    }
}

// Inicializar el juego cuando se carga el archivo
GameManager.init();