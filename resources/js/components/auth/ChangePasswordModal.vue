<script setup>
import { ref } from 'vue';
import Modal from '../ui/Modal.vue';
import Button from '../ui/Button.vue';
import api from '../../lib/api';
import { useToastStore } from '../../stores/toast';

const toast = useToastStore();

const props = defineProps({
    modelValue: Boolean
});

const emit = defineEmits(['update:modelValue']);

const form = ref({ current_password: '', password: '', password_confirmation: '' });
const loading = ref(false);
const error = ref('');

function close() {
    emit('update:modelValue', false);
    form.value = { current_password: '', password: '', password_confirmation: '' };
    error.value = '';
}

async function submit() {
    loading.value = true;
    error.value = '';
    
    try {
        await api.put('/auth/password', form.value);
        close();
        toast.success('Đổi mật khẩu thành công!');
    } catch (e) {
        error.value = e.response?.data?.message || 'Có lỗi xảy ra, vui lòng kiểm tra lại mật khẩu cũ.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Modal :open="modelValue" @close="close" title="Đổi mật khẩu">
        <form id="change-password-form" @submit.prevent="submit" class="space-y-4">
            <div>
                <label for="current_password" class="mb-1.5 block text-sm font-medium text-ink">Mật khẩu hiện tại</label>
                <input id="current_password" v-model="form.current_password" class="field" type="password" autocomplete="current-password" required autofocus>
            </div>
            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-ink">Mật khẩu mới</label>
                <input id="password" v-model="form.password" class="field" type="password" autocomplete="new-password" required minlength="8">
            </div>
            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-ink">Xác nhận mật khẩu</label>
                <input id="password_confirmation" v-model="form.password_confirmation" class="field" type="password" autocomplete="new-password" required minlength="8">
            </div>
            
            <div v-if="error" class="mb-4 rounded-field border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">
                {{ error }}
            </div>
        </form>

        <template #footer>
            <Button type="button" variant="secondary" @click="close">Hủy</Button>
            <Button type="submit" form="change-password-form" :disabled="loading">
                {{ loading ? 'Đang lưu...' : 'Lưu thay đổi' }}
            </Button>
        </template>
    </Modal>
</template>
