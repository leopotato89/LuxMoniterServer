import axios from 'axios';

export const TOKEN_KEY = 'lux_token';

const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        Accept: 'application/json',
    },
});

// Gắn Bearer token vào mọi request (SPA và mobile dùng chung 1 đường auth).
api.interceptors.request.use((config) => {
    const token = localStorage.getItem(TOKEN_KEY);

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
});

api.interceptors.response.use(
    (response) => response,
    (error) => {
        // Token hết hiệu lực hoặc bị thu hồi → xoá và quay về trang đăng nhập.
        if (error.response?.status === 401) {
            localStorage.removeItem(TOKEN_KEY);

            if (window.location.pathname !== '/login') {
                window.location.replace('/login');
            }
        }

        return Promise.reject(error);
    },
);

/**
 * Rút thông điệp lỗi đầu tiên từ response của Laravel (422 validation / message chung).
 */
export function firstError(error) {
    const errors = error?.response?.data?.errors;

    if (errors) {
        return Object.values(errors)[0]?.[0] ?? '';
    }

    return error?.response?.data?.message ?? 'Không kết nối được máy chủ.';
}

export default api;
