/**
     * Maneja los SweetAlert para los middleware de autenticación
 */

// Función para mostrar alertas de middleware
function showMiddlewareAlert(type, message) {
    const alertConfig = {
        title: type === 'error' ? 'Error' : 'Información',
        text: message,
        icon: type === 'error' ? 'error' : 'info',
        timer: 3000,
        timerProgressBar: true,
        showConfirmButton: false,
        background: '#ffffff',
        color: '#000000',
        customClass: {
            popup: 'swal2-popup-custom',
            title: 'swal2-title-custom',
            content: 'swal2-content-custom',
        }
    };

    Swal.fire(alertConfig);
}

document.addEventListener('DOMContentLoaded', function() {
    const errorMessage = document.querySelector('meta[name="session-error"]')?.getAttribute('content');

    if (errorMessage) {
        showMiddlewareAlert('error', errorMessage);
    }
});