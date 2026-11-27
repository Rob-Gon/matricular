/**
     * Gestor de Sesión
     * Gestiona el tiempo de expiración de la sesión y muestra el temporizador de cuenta regresiva
 */

import Swal from 'sweetalert2';

class SessionManager {
    constructor() {
        this.sessionTimeout = 43200; // 12 horas en segundos
        this.warningTime = 300; // Mostrar advertencia 5 minutos antes de la expiración
        this.countdownInterval = null;
        this.warningShown = false;
        this.init();
    }

    init() {
        // Solo ejecutar si el usuario está autenticado
        if (document.querySelector('[data-user-authenticated]')) {
            this.startCountdown();
            this.setupActivityTracking();
        }
    }

    /**
     * Inicializar cuenta atrás
     * 
     */
    startCountdown() {
        // Obtener el tiempo de inicio de la sesión desde la etiqueta meta o usar el tiempo actual
        const sessionStartTime = this.getSessionStartTime();
        const currentTime = Math.floor(Date.now() / 1000);
        const elapsedTime = currentTime - sessionStartTime;
        const remainingTime = this.sessionTimeout - elapsedTime;

        if (remainingTime <= 0) {
            this.handleSessionExpired();
            return;
        }

        this.countdownInterval = setInterval(() => {
            const now = Math.floor(Date.now() / 1000);
            const elapsed = now - sessionStartTime;
            const remaining = this.sessionTimeout - elapsed;

            if (remaining <= 0) {
                this.handleSessionExpired();
                return;
            }

            // Mostrar advertencia cuando queden 5 minutos
            if (remaining <= this.warningTime && !this.warningShown) {
                this.showSessionWarning(remaining);
            }
        }, 1000);
    }

    /**
     * Obtener la hora a la que la sesión inicio
     * 
     * @returns int
     */
    getSessionStartTime() {
        // Intentar obtener desde la etiqueta meta primero
        const metaTag = document.querySelector('meta[name="session-start-time"]');
        if (metaTag) {
            return parseInt(metaTag.getAttribute('content'));
        }
        
        // Usar el tiempo actual como respaldo
        return Math.floor(Date.now() / 1000);
    }

    /**
     * Mostrar mensaje de expiración de sesión cercana
     * 
     * @param {int} remainingSeconds 
     */
    showSessionWarning(remainingSeconds) {
        this.warningShown = true;
        let seconds = remainingSeconds;
        let interval;
        Swal.fire({
            icon: 'warning',
            title: '<i class="fas fa-exclamation-triangle me-2"></i> Sesión por expirar',
            html: `
                <p>Tu sesión expirará en <strong id="warning-countdown">${Math.floor(seconds / 60)} minutos</strong>.</p>
                <p>¿Deseas extender tu sesión?</p>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fas fa-refresh me-2"></i> Extender Sesión',
            cancelButtonText: 'Cancelar',
            focusConfirm: false,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                const warningCountdown = Swal.getHtmlContainer().querySelector('#warning-countdown');
                interval = setInterval(() => {
                    seconds--;
                    if (seconds <= 0) {
                        clearInterval(interval);
                        Swal.close();
                        this.handleSessionExpired();
                    } else {
                        warningCountdown.textContent = `${Math.floor(seconds / 60)} minutos`;
                    }
                }, 1000);
            },
            willClose: () => {
                if (interval) clearInterval(interval);
            }
        }).then((result) => {
            if (interval) clearInterval(interval);
            if (result.isConfirmed) {
                this.extendSession();
            }
        });
    }

    /**
     * Extender sesión
     * 
     */
    async extendSession() {
        try {
            const response = await fetch('/extend-session', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                }
            });

            if (response.ok) {
                // Reiniciar cuenta regresiva
                this.warningShown = false;
                const sessionStartTime = Math.floor(Date.now() / 1000);
                
                // Actualizar etiqueta meta
                const metaTag = document.querySelector('meta[name="session-start-time"]');
                if (metaTag) {
                    metaTag.setAttribute('content', sessionStartTime.toString());
                }

                // Cerrar modal de advertencia
                const modal = bootstrap.Modal.getInstance(document.getElementById('sessionWarningModal'));
                if (modal) {
                    modal.hide();
                }

                // Reiniciar cuenta regresiva
                if (this.countdownInterval) {
                    clearInterval(this.countdownInterval);
                }
                this.startCountdown();

                // Mostrar mensaje de éxito
                this.showMessage('Sesión extendida correctamente. Token de recordatorio actualizado.', 'success');
            }
        } catch (error) {
            console.error('Error al extender la sesión:', error);
            this.showMessage('Error al extender la sesión', 'error');
        }
    }

    /**
     * Manejo de la sesión expirada
     * 
     */
    handleSessionExpired() {
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }

        // Mostrar mensaje de expiración
        this.showMessage('Tu sesión ha expirado. Serás redirigido al login.', 'warning');

        // Redirigir al login después de 3 segundos
        setTimeout(() => {
            window.location.href = '/login';
        }, 3000);
    }

    /**
     * Reiniciar temporizador de sesión en actividad del usuario
     * 
     */
    setupActivityTracking() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.resetSessionTimer();
            }, true);
        });
    }

    /**
     * Actualizar tiempo de inicio de sesión en actividad
     * 
     */
    resetSessionTimer() {
        const sessionStartTime = Math.floor(Date.now() / 1000);
        const metaTag = document.querySelector('meta[name="session-start-time"]');
        if (metaTag) {
            metaTag.setAttribute('content', sessionStartTime.toString());
        }
    }

    /**
     * Mostrar mensaje
     * 
     * @param {*} message 
     * @param {*} type 
     */
    showMessage(message, type = 'info') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type,
            title: message,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    }
}

// Inicializar gestor de sesión cuando se carga el DOM
document.addEventListener('DOMContentLoaded', () => {
    let sessionManager;
    sessionManager = new SessionManager();
});

// Exportar para uso en otros módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SessionManager;
} 