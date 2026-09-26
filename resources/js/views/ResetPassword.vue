<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import Button from '../components/ui/Button.vue';
import AuthLayout from '../layouts/AuthLayout.vue';
import api from '../lib/api';

const route = useRoute();
const router = useRouter();

const form = ref({
    token: '',
    email: '',
    password: '',
    password_confirmation: ''
});

const loading = ref(false);
const message = ref('');
const error = ref('');

onMounted(() => {
    form.value.token = route.query.token || '';
    form.value.email = route.query.email || '';
});

async function submit() {
    loading.value = true;
    message.value = '';
    error.value = '';

    try {
        const { data } = await api.post('/auth/reset-password', form.value);
        message.value = data.message || 'Đặt lại mật khẩu thành công.';
        setTimeout(() => router.push('/login'), 2000);
    } catch (e) {
        error.value = e.response?.data?.message || 'Có lỗi xảy ra hoặc token không hợp lệ.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <AuthLayout>
        <h2 class="text-xl font-bold text-ink">Đặt lại mật khẩu</h2>
        
        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-ink">Email</label>
                <input id="email" v-model="form.email" class="field bg-surface-hover text-muted" type="email" readonly>
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-ink">Mật khẩu mới <span class="text-brand">*</span></label>
                <input id="password" v-model="form.password" class="field" type="password" autocomplete="new-password" required autofocus minlength="8">
            </div>

            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-ink">Xác nhận mật khẩu <span class="text-brand">*</span></label>
                <input id="password_confirmation" v-model="form.password_confirmation" class="field" type="password" autocomplete="new-password" required minlength="8">
            </div>

            <p v-if="message" class="rounded-field border border-success/30 bg-success/5 px-3 py-2 text-sm text-success">
                {{ message }}
            </p>
            <p v-if="error" class="rounded-field border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">
                {{ error }}
            </p>

            <Button type="submit" :disabled="loading || message !== ''" class="w-full">
                {{ loading ? 'Đang lưu…' : 'Lưu mật khẩu mới' }}
            </Button>
            
            <div class="mt-4 text-center">
                <RouterLink to="/login" class="text-sm font-semibold text-muted transition hover:text-brand">
                    Về trang đăng nhập
                </RouterLink>
            </div>
        </form>
    </AuthLayout>
</template>
