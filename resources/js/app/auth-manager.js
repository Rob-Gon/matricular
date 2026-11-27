/**
     * Auth Manager - Gestor de autenticación
     * Este archivo maneja las notificaciones SweetAlert2 para login, registro y actualización de perfil
 */

// Configuración personalizada de SweetAlert2
const swalConfig = {
    customClass: {
        popup: 'swal-custom-popup',
        title: 'swal-custom-title',
        content: 'swal-custom-content',
        confirmButton: 'swal-custom-confirm',
        cancelButton: 'swal-custom-cancel'
    },
    buttonsStyling: false,
    confirmButtonColor: '#6f42c1',
    cancelButtonColor: '#dc3545'
};

class AuthManager {
    constructor() {
        this.initializeAuthForms();
    }

    /**
     * Inicializa los formularios de autenticación
     */
    initializeAuthForms() {
        $('#login-form').on('submit', function(e) {
            e.preventDefault();
            AuthManager.handleLogin();
        });

        $('#register-form').on('submit', function(e) {
            e.preventDefault();
            AuthManager.handleRegister();
        });

        $('#profile-form').on('submit', function(e) {
            e.preventDefault();
            AuthManager.handleProfileUpdate();
        });
    }

    /**
     * Maneja el envío del formulario de login
     */
    static handleLogin() {
        const formData = new FormData(document.getElementById('login-form'));
        
        Swal.fire({
            title: 'Iniciando sesión...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            },
            ...swalConfig
        });

        fetch('/login', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.close();
                
                Swal.fire({
                    icon: 'success',
                    title: '¡Bienvenido!',
                    text: data.message || 'Sesión iniciada correctamente',
                    showConfirmButton: true,
                    ...swalConfig
                }).then(() => {
                    window.location.href = data.redirect || '/home';
                });
            } else {
                let errorMessage = data.message || 'Credenciales incorrectas';
                if (data.errors) {
                    errorMessage = Object.values(data.errors).flat().join('\n');
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error de autenticación',
                    text: errorMessage,
                    confirmButtonText: 'Intentar de nuevo',
                    ...swalConfig
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Ha ocurrido un error al iniciar sesión',
                confirmButtonText: 'Intentar de nuevo',
                ...swalConfig
            });
        });
    }

    /**
     * Maneja el envío del formulario de registro
     */
    static handleRegister() {
        const formData = new FormData(document.getElementById('register-form'));
        
        const password = formData.get('password');
        const password_confirmation = formData.get('password_confirmation');
        
        if (password !== password_confirmation) {
            Swal.fire({
                icon: 'error',
                title: 'Error de validación',
                text: 'Las contraseñas no coinciden',
                confirmButtonText: 'Entendido',
                ...swalConfig
            });
            return;
        }

        Swal.fire({
            title: 'Creando cuenta...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            },
            ...swalConfig
        });

        fetch('/register', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.close();
                
                Swal.fire({
                    icon: 'success',
                    title: '¡Cuenta creada!',
                    text: data.message || 'Tu cuenta ha sido creada exitosamente',
                    showConfirmButton: true,
                    ...swalConfig
                }).then(() => {
                    window.location.href = data.redirect || '/login';
                });
            } else {
                let errorMessage = 'Ha ocurrido un error al crear la cuenta';
                if (data.errors) {
                    errorMessage = Object.values(data.errors).flat().join('\n');
                } else if (data.message) {
                    errorMessage = data.message;
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error de registro',
                    text: errorMessage,
                    confirmButtonText: 'Intentar de nuevo',
                    ...swalConfig
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Ha ocurrido un error al crear la cuenta',
                confirmButtonText: 'Intentar de nuevo',
                ...swalConfig
            });
        });
    }

    /**
     * Muestra un mensaje de confirmación antes de cerrar sesión
     */
    static confirmLogout() {
        Swal.fire({
            title: '¿Cerrar sesión?',
            text: "¿Estás seguro de que quieres cerrar tu sesión?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, cerrar sesión',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#6f42c1',
            cancelButtonColor: '#dc3545',
            reverseButtons: true,
            ...swalConfig
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Cerrando sesión...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    ...swalConfig
                });
                
                fetch('/logout', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.close();
                        
                        Swal.fire({
                            icon: 'success',
                            title: '¡Sesión cerrada!',
                            text: data.message || 'Has cerrado tu sesión correctamente',
                            showConfirmButton: true,
                            ...swalConfig
                        }).then(() => {
                            window.location.href = data.redirect || '/login';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Ha ocurrido un error al cerrar la sesión',
                            confirmButtonText: 'Intentar de nuevo',
                            ...swalConfig
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Ha ocurrido un error al cerrar la sesión',
                        confirmButtonText: 'Intentar de nuevo',
                        ...swalConfig
                    });
                });
            }
        });
    }

    /**
     * Maneja la actualización del perfil
     */
    static handleProfileUpdate() {
        const formData = new FormData(document.getElementById('profile-form'));
        
        const newPassword = formData.get('new_password');
        const newPasswordConfirmation = formData.get('new_password_confirmation');
        const currentPassword = formData.get('current_password');
        
        if (newPassword || newPasswordConfirmation || currentPassword) {
            if (!currentPassword) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de validación',
                    text: 'La contraseña actual es requerida para cambiar la contraseña',
                    confirmButtonText: 'Entendido',
                    ...swalConfig
                });
                return;
            }
            
            if (newPassword !== newPasswordConfirmation) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de validación',
                    text: 'Las nuevas contraseñas no coinciden',
                    confirmButtonText: 'Entendido',
                    ...swalConfig
                });
                return;
            }
        }

        Swal.fire({
            title: 'Actualizando perfil...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            },
            ...swalConfig
        });

        fetch('/profile', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.close();
                
                Swal.fire({
                    icon: 'success',
                    title: '¡Perfil actualizado!',
                    text: data.message || 'Tu perfil ha sido actualizado correctamente',
                    showConfirmButton: true,
                    ...swalConfig
                }).then(() => {
                    window.location.href = '/profile';
                });
            } else {
                let errorMessage = 'Ha ocurrido un error al actualizar el perfil';
                if (data.errors) {
                    errorMessage = Object.values(data.errors).flat().join('\n');
                } else if (data.message) {
                    errorMessage = data.message;
                }
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error de actualización',
                    text: errorMessage,
                    confirmButtonText: 'Intentar de nuevo',
                    ...swalConfig
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Ha ocurrido un error al actualizar el perfil',
                confirmButtonText: 'Intentar de nuevo',
                ...swalConfig
            });
        });
    }

    /**
     * Muestra un mensaje de confirmación antes de eliminar la cuenta
     */
    static confirmDeleteProfile() {
        Swal.fire({
            title: '¿Eliminar cuenta?',
            text: "Esta acción no se puede deshacer. ¿Estás seguro de que quieres eliminar tu cuenta permanentemente?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar cuenta',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            reverseButtons: true,
            ...swalConfig
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Eliminando cuenta...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    ...swalConfig
                });
                
                fetch('/profile', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    body: new FormData(document.getElementById('delete-profile-form'))
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.close();
                        
                        Swal.fire({
                            icon: 'success',
                            title: '¡Cuenta eliminada!',
                            text: data.message || 'Tu cuenta ha sido eliminada correctamente',
                            showConfirmButton: true,
                            confirmButtonText: 'Entendido',
                            ...swalConfig
                        }).then(() => {
                            window.location.href = data.redirect || '/login';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Ha ocurrido un error al eliminar la cuenta',
                            confirmButtonText: 'Intentar de nuevo',
                            ...swalConfig
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Ha ocurrido un error al eliminar la cuenta',
                        confirmButtonText: 'Intentar de nuevo',
                        ...swalConfig
                    });
                });
            }
        });
    }

    /**
     * Inicializa el gestor de autenticación cuando el DOM está listo
     */
    static init() {
        $(document).ready(function() {
            new AuthManager();
        });
    }
}

// Inicializar el gestor de autenticación cuando se carga el archivo
AuthManager.init();

// Funcion global para confirmar logout
window.confirmLogout = function() {
    AuthManager.confirmLogout();
};

// Funcion global para eliminar la cuenta
window.confirmDeleteProfile = function() {
    AuthManager.confirmDeleteProfile();
}; 