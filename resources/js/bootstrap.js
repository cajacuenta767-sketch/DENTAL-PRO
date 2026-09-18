import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

// Interceptor global para respuestas HTTP con Axios
window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (!error.response) {
            window.OdontoSuite?.toast?.('Sin conexión con el servidor. Revisa tu acceso a internet.', 'danger');
            return Promise.reject(error);
        }

        const status = error.response.status;
        const data = error.response.data;

        if (status === 419) {
            window.OdontoSuite?.toast?.(
                'Tu sesión ha expirado por inactividad. Haz clic para recargar la página.',
                'warning',
                8000,
                () => window.location.reload()
            );
        } else if (status === 403) {
            window.OdontoSuite?.toast?.(
                data?.message || 'Acceso restringido: no tienes permisos para realizar esta acción.',
                'danger'
            );
        } else if (status === 422) {
            const errores = data?.errors ? Object.values(data.errors).flat().join(' ') : (data?.message || 'Por favor revisa los datos ingresados.');
            window.OdontoSuite?.toast?.(errores, 'warning', 5000);
        } else if (status >= 500) {
            window.OdontoSuite?.toast?.(
                'Ocurrió un error inesperado en el servidor. Por favor intenta nuevamente.',
                'danger'
            );
        }

        return Promise.reject(error);
    }
);
