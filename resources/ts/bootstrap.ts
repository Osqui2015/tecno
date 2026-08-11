import axios from 'axios';

// Usar el mismo origen en el que está cargada la app.
// Esto funciona tanto en localhost, 127.0.0.1, ecomers.test, o cualquier dominio.
const apiUrl =
    (import.meta.env.VITE_API_URL as string | undefined) ??
    `${window.location.origin}/api`;

const axiosInstance = axios.create({
    baseURL: apiUrl,
    withCredentials: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
});

// Lee el CSRF token del meta tag y lo aplica a cada request
const token = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content');

if (token) {
    axiosInstance.defaults.headers.common['X-CSRF-TOKEN'] = token;
}

// Interceptor para adjuntar el Bearer token desde localStorage
axiosInstance.interceptors.request.use((config) => {
    const authToken = localStorage.getItem('auth_token');
    if (authToken) {
        config.headers.Authorization = `Bearer ${authToken}`;
    }
    return config;
});

// Interceptor de respuesta para manejo global de errores
axiosInstance.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
        }
        return Promise.reject(error);
    }
);

export default axiosInstance;
