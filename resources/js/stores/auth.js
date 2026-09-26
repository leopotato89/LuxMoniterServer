import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import api, { firstError, TOKEN_KEY } from '../lib/api';

export const useAuthStore = defineStore('auth', () => {
    const token = ref(localStorage.getItem(TOKEN_KEY) ?? '');
    const user = ref(null);
    const loading = ref(false);
    const error = ref('');

    const isAuthenticated = computed(() => token.value !== '');
    const isAdmin = computed(() => user.value?.is_admin === true);

    function setToken(value) {
        token.value = value ?? '';

        if (token.value) {
            localStorage.setItem(TOKEN_KEY, token.value);
        } else {
            localStorage.removeItem(TOKEN_KEY);
        }
    }

    /**
     * Khôi phục phiên từ token đã lưu (gọi 1 lần khi vào app / tải lại trang).
     */
    async function restore() {
        if (!isAuthenticated.value || user.value) {
            return;
        }

        try {
            const { data } = await api.get('/auth/me');
            user.value = data.data;
        } catch {
            // 401 đã được interceptor xử lý; ở đây chỉ cần quên token.
            setToken('');
        }
    }

    async function login(credentials) {
        return authenticate('/auth/login', credentials);
    }

    async function register(payload) {
        return authenticate('/auth/register', payload);
    }

    async function authenticate(url, payload) {
        loading.value = true;
        error.value = '';

        try {
            const { data } = await api.post(url, payload);
            setToken(data.data.token);
            user.value = data.data.user;

            return true;
        } catch (e) {
            error.value = firstError(e);

            return false;
        } finally {
            loading.value = false;
        }
    }

    async function logout() {
        try {
            await api.post('/auth/logout');
        } catch {
            // Token có thể đã hết hạn — vẫn phải xoá ở client.
        }

        setToken('');
        user.value = null;
    }

    return {
        token,
        user,
        loading,
        error,
        isAuthenticated,
        isAdmin,
        restore,
        login,
        register,
        logout,
    };
});
