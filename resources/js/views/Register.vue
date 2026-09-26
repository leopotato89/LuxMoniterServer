<script setup>
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import Button from '../components/ui/Button.vue';
import AuthLayout from '../layouts/AuthLayout.vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();

const form = ref({
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
});

async function submit() {
    const ok = await auth.register({ ...form.value, device_name: 'web' });

    if (ok) {
        await router.replace('/');
    }
}
</script>

<template>
    <AuthLayout>
        <h2 class="text-xl font-bold text-ink">Đăng ký tài khoản</h2>
        <p class="mt-1 text-sm text-muted">
            đã có tài khoản?
            <RouterLink to="/login" class="font-semibold text-brand transition hover:text-brand-strong">
                đăng nhập
            </RouterLink>
        </p>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-ink">
                    Tên <span class="text-brand">*</span>
                </label>
                <input id="name" v-model="form.name" class="field" type="text" autocomplete="off" required>
            </div>

            <div>
                <label for="username" class="mb-1.5 block text-sm font-medium text-ink">
                    Tên đăng nhập <span class="text-brand">*</span>
                </label>
                <input id="username" v-model="form.username" class="field" type="text" maxlength="50" autocomplete="off" required>
            </div>

            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-ink">
                    Email <span class="text-brand">*</span>
                </label>
                <input id="email" v-model="form.email" class="field" type="email" autocomplete="off" required>
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-ink">
                    Mật khẩu <span class="text-brand">*</span>
                </label>
                <input id="password" v-model="form.password" class="field" type="password" autocomplete="new-password" required>
            </div>

            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-ink">
                    Xác nhận mật khẩu <span class="text-brand">*</span>
                </label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    class="field"
                    type="password"
                    autocomplete="new-password"
                    required
                >
            </div>

            <p v-if="auth.error" class="rounded-field border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">
                {{ auth.error }}
            </p>

            <Button type="submit" :disabled="auth.loading" class="w-full">
                {{ auth.loading ? 'Đang tạo tài khoản…' : 'Đăng ký' }}
            </Button>
        </form>
    </AuthLayout>
</template>
