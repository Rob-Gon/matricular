/**
     * Game Manager - Gestor del juego
     * Este archivo contiene todas las funciones JavaScript utilizadas en los modos de juego
 */

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
    }

    /**
     * Inicializar la cuenta atrás si el modo es contrareloj
     */
    initializeCountdown() {
        if (this.gameMode === 'Contrareloj') {
            const total = parseInt($('#countdown_total').val());
            const remaining = parseInt($('#countdown_remaining').val());
            this.startCountdown(total, remaining);
        }
    }

    /**
     * Iniciar la cuenta atrás
     * @param {number} total - Duración total en segundos
     * @param {number} remaining - Segundos restantes según el servidor
     */
    startCountdown(total, remaining) {
        // Inicializa el tiempo inicial y el tiempo actual
        this.initialTime = total;
        this.countdownTime = remaining;

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
        GameManager.post('checkWordUrl', { word: $('#word').val() }, 'Error al procesar la palabra');
    }

    /**
     * Cambiar la matrícula actual
     */
    static changePlate() {
        GameManager.post('changePlateUrl', {}, 'Error al cambiar la matrícula');
    }

    /**
     * Envía una acción de la partida al servidor, que es quien decide matrícula, tiempo y puntos
     * @param {string} urlKey - Clave jQuery.data() del formulario con la URL (data-check-word-url → checkWordUrl)
     * @param {object} data - Datos a enviar
     * @param {string} fallbackMessage - Mensaje si el servidor no devuelve uno
     */
    static post(urlKey, data, fallbackMessage) {
        $.ajax({
            url: $('#game-form').data(urlKey),
            method: 'POST',
            data: { ...data, _token: $('meta[name="csrf-token"]').attr('content') },
            success: function(response) {
                GameManager.handleResponse(response);
            },
            error: function(xhr) {
                const response = xhr.responseJSON || {};

                // 409: la partida ya ha terminado en el servidor
                if (xhr.status === 409) {
                    GameManager.handleResponse(response);
                    GameManager.endRun();
                    return;
                }

                GameManager.showMessage(response.message || fallbackMessage, false);
            }
        });
    }

    /**
     * Terminar la run cuando se acaba el tiempo o el jugador la termina
     * @param {number} countdownInterval - Intervalo de la cuenta atrás
     */
    static endRun(countdownInterval = null) {
        // Si hay un intervalo de cuenta atrás, se limpia
        if (countdownInterval) {
            clearInterval(countdownInterval);
        }

        // Registra el fin en el servidor; si falla, el servidor la da por terminada igualmente al pasar ends_at
        $.post($('#game-form').data('endUrl'), { _token: $('meta[name="csrf-token"]').attr('content') });

        // Mostrar el modal de fin de juego
        $('#gameOverModal').modal('show');

        // Deshabilitar el formulario
        $('#game-form').find('input, button').prop('disabled', true);
    }

    /**
     * Manejo de la respuesta del servidor
     */
    static handleResponse(response) {
        if (response.message) {
            GameManager.showMessage(response.message, response.success);
        }

        if (response.score !== undefined) {
            $('#score').text(response.score);
        }

        if (response.chars) {
            GameManager.updatePlate(response.chars);
        }

        // Limpia el input de la palabra
        $('#word').val('');
    }

    /**
     * Muestra un mensaje como texto, nunca como HTML
     */
    static showMessage(message, success) {
        const alert = $('<div>').addClass('alert ' + (success ? 'alert-success' : 'alert-danger')).text(message);
        $('#message').stop().show().empty().append(alert);
    }

    /**
     * Actualiza la matrícula en la interfaz
     */
    static updatePlate(chars) {
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