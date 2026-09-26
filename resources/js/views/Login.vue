<script setup>
import { ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import Button from '../components/ui/Button.vue';
import AuthLayout from '../layouts/AuthLayout.vue';
import { useAuthStore } from '../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const form = ref({ login: '', password: '' });
const showPassword = ref(false);

async function submit() {
    const ok = await auth.login({ ...form.value, device_name: 'web' });

    if (ok) {
        await router.replace(route.query.redirect?.toString() ?? '/');
    }
}
</script>

<template>
    <AuthLayout>
        <h2 class="text-xl font-bold text-ink">Đăng nhập</h2>
        <p class="mt-1 text-sm text-muted">
            hoặc
            <RouterLink to="/register" class="font-semibold text-brand transition hover:text-brand-strong">
                đăng ký tài khoản
            </RouterLink>
        </p>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <div>
                <label for="login" class="mb-1.5 block text-sm font-medium text-ink">
                    Email hoặc tên đăng nhập <span class="text-brand">*</span>
                </label>
                <input
                    id="login"
                    v-model="form.login"
                    class="field"
                    type="text"
                    autocomplete="username"
                    autofocus
                    required
                >
            </div>

            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label for="password" class="block text-sm font-medium text-ink">
                        Mật khẩu <span class="text-brand">*</span>
                    </label>
                    <RouterLink to="/forgot-password" class="text-xs font-semibold text-brand transition hover:text-brand-strong">Quên mật khẩu?</RouterLink>
                </div>
                <div class="relative">
                    <input
                        id="password"
                        v-model="form.password"
                        class="field pr-16"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password"
                        required
                    >
                    <button
                        type="button"
                        class="absolute inset-y-0 right-3 text-xs font-semibold text-muted transition hover:text-brand"
                        @click="showPassword = !showPassword"
                    >
                        {{ showPassword ? 'Ẩn' : 'Hiện' }}
                    </button>
                </div>
            </div>

            <p v-if="auth.error" class="rounded-field border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">
                {{ auth.error }}
            </p>

            <Button type="submit" :disabled="auth.loading" class="w-full">
                {{ auth.loading ? 'Đang đăng nhập…' : 'Đăng nhập' }}
            </Button>
        </form>
    </AuthLayout>
</template>
