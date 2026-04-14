import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios';
import { getStoredValue, removeStoredValue } from '@/lib/branding';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8231/api/v1',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
    },
});

// ─── Request Interceptor: Attach Bearer Token ────────────────
api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
    const token = getStoredValue('token');
    if (token && config.headers) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// ─── Response Interceptor: Handle 401 Globally ───────────────
api.interceptors.response.use(
    (response) => response,
    (error: AxiosError) => {
        if (error.response?.status === 401) {
            removeStoredValue('token');
            // Only redirect if not already on login
            if (!window.location.pathname.startsWith('/login')) {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    },
);

export default api;
