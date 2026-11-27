/**
     * Bootstrap - Inicialización de imports
     * Este archivo configura los imports para el proyecto
 */

import axios from 'axios';
import Swal from 'sweetalert2';

window.axios = axios;
window.Swal = Swal;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
