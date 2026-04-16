import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios';
import { getStoredValue, removeStoredValue } from '@/lib/branding';

const fallbackApiUrl = typeof window !== 'undefined'
    ? `${window.location.origin}/api/v1`
    : 'http://127.0.0.1:8231/api/v1';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || fallbackApiUrl,
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
