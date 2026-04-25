import axios from 'axios';

const BASE_URL = import.meta.env.VITE_API_BASE_URL || '';
const GUEST_AUTH_STORAGE_KEY = 'mytherra-guest-session';

const readToken = (): string | null => {
    try {
        const authStorageStr = localStorage.getItem('auth-storage');
        if (authStorageStr) {
            const authData = JSON.parse(authStorageStr);
            const token = authData?.state?.token;
            if (typeof token === 'string' && token.trim() !== '') {
                return token;
            }
        }

        const guestStorageStr = localStorage.getItem(GUEST_AUTH_STORAGE_KEY);
        if (guestStorageStr) {
            const guestData = JSON.parse(guestStorageStr) as { token?: string | null };
            if (typeof guestData?.token === 'string' && guestData.token.trim() !== '') {
                return guestData.token;
            }
        }
    } catch (error) {
        console.warn('Failed to parse auth token from local storage', error);
    }

    return null;
};

export const apiClient = axios.create({
    baseURL: BASE_URL,
    headers: {
        'Content-Type': 'application/json',
    },
});

apiClient.interceptors.request.use(
    (config) => {
        const token = readToken();
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => Promise.reject(error)
);

apiClient.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            const loginUrl =
                error.response?.data?.login_url ||
                import.meta.env.VITE_WEB_HATCHERY_LOGIN_URL;

            if (loginUrl) {
                try {
                    const raw = localStorage.getItem('auth-storage');
                    const parsed = raw ? JSON.parse(raw) : {};
                    const state = parsed?.state ?? {};
                    const next = {
                        ...parsed,
                        state: {
                            ...state,
                            loginUrl,
                        },
                    };
                    localStorage.setItem('auth-storage', JSON.stringify(next));
                    window.dispatchEvent(new CustomEvent('webhatchery:login-required', { detail: { loginUrl } }));
                } catch (storageError) {
                    console.warn('Failed to persist login URL to auth storage', storageError);
                }
            }
        }
        return Promise.reject(error);
    }
);
