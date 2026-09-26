<script setup>
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import Button from '../components/ui/Button.vue';
import AuthLayout from '../layouts/AuthLayout.vue';
import api from '../lib/api';

const email = ref('');
const loading = ref(false);
const message = ref('');
const error = ref('');

async function submit() {
    loading.value = true;
    message.value = '';
    error.value = '';

    try {
        const { data } = await api.post('/auth/forgot-password', { email: email.value });
        message.value = data.message || 'Link khôi phục mật khẩu đã được gửi đến email của bạn.';
    } catch (e) {
        error.value = e.response?.data?.message || 'Có lỗi xảy ra, vui lòng thử lại.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <AuthLayout>
        <h2 class="text-xl font-bold text-ink">Quên mật khẩu</h2>
        <p class="mt-1 text-sm text-muted">
            Nhập email của bạn để nhận liên kết đặt lại mật khẩu.
        </p>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-ink">
                    Email <span class="text-brand">*</span>
                </label>
                <input
                    id="email"
                    v-model="email"
                    class="field"
                    type="email"
                    autocomplete="email"
                    autofocus
                    required
                >
            </div>

            <p v-if="message" class="rounded-field border border-success/30 bg-success/5 px-3 py-2 text-sm text-success">
                {{ message }}
            </p>
            <p v-if="error" class="rounded-field border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">
                {{ error }}
            </p>

            <Button type="submit" :disabled="loading" class="w-full">
                {{ loading ? 'Đang gửi…' : 'Gửi liên kết' }}
            </Button>

            <div class="mt-4 text-center">
                <RouterLink to="/login" class="text-sm font-semibold text-muted transition hover:text-brand">
                    Quay lại đăng nhập
                </RouterLink>
            </div>
        </form>
    </AuthLayout>
</template>
