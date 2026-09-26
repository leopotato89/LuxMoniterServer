<script setup>
import { onMounted, ref, watch } from 'vue';
import { PlusIcon, CheckCircleIcon, ChevronDownIcon, ChevronUpIcon } from '@heroicons/vue/24/outline';
import Button from '../components/ui/Button.vue';
import Modal from '../components/ui/Modal.vue';
import ToggleSwitch from '../components/ui/ToggleSwitch.vue';
import { useAuthStore } from '../stores/auth';
import api from '../lib/api';
import { useToastStore } from '../stores/toast';

const auth = useAuthStore();
const toast = useToastStore();

const users = ref([]);
const loading = ref(false);

const search = ref('');
const filters = ref({
    page: 1,
    per_page: 10,
    sort: 'id',
    direction: 'desc',
    is_active: '',
    is_admin: ''
});

const meta = ref({ current_page: 1, last_page: 1, total: 0 });

const createOpen = ref(false);
const createForm = ref({ name: '', username: '', email: '', password: '', is_admin: false, is_active: true });
const createSubmitting = ref(false);

const editTarget = ref(null);
const editForm = ref({ name: '', username: '', email: '', password: '', is_admin: false });
const editSubmitting = ref(false);

const deleteTarget = ref(null);
const deleteSubmitting = ref(false);

const toggleTarget = ref(null);
const toggleSubmitting = ref(false);

const columns = [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'name', label: 'Tên', sortable: true },
    { key: 'username', label: 'Username', sortable: true },
    { key: 'email', label: 'Email', sortable: true },
    { key: 'is_admin', label: 'Admin', sortable: false },
    { key: 'is_active', label: 'Hoạt động', sortable: false },
    { key: 'created_at', label: 'Tạo lúc', sortable: true },
];

let searchTimer = null;

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        filters.value.search = value;
        filters.value.page = 1;
        fetchUsers();
    }, 300);
});

watch(
    () => [filters.value.page, filters.value.per_page, filters.value.sort, filters.value.direction, filters.value.is_active, filters.value.is_admin],
    () => fetchUsers(),
);

async function fetchUsers() {
    loading.value = true;
    try {
        const params = { ...filters.value };
        if (filters.value.search) params.search = filters.value.search;
        
        // Convert empty strings to null for backend
        if (params.is_active === '') delete params.is_active;
        if (params.is_admin === '') delete params.is_admin;

        const { data } = await api.get('/users', { params });
        users.value = data.data;
        meta.value = data.meta;
    } catch (e) {
        toast.error('Không thể tải danh sách người dùng');
    } finally {
        loading.value = false;
    }
}

onMounted(() => fetchUsers());

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('vi-VN', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function toggleSort(column) {
    if (filters.value.sort === column) {
        filters.value.direction = filters.value.direction === 'asc' ? 'desc' : 'asc';
    } else {
        filters.value.sort = column;
        filters.value.direction = 'asc';
    }
}

async function submitCreate() {
    createSubmitting.value = true;
    try {
        await api.post('/users', createForm.value);
        toast.success('Đã tạo người dùng');
        createOpen.value = false;
        createForm.value = { name: '', username: '', email: '', password: '', is_admin: false, is_active: true };
        fetchUsers();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Có lỗi xảy ra');
    } finally {
        createSubmitting.value = false;
    }
}

function openEdit(user) {
    editTarget.value = user;
    editForm.value = {
        name: user.name,
        username: user.username,
        email: user.email,
        password: '',
        is_admin: user.is_admin,
    };
}

async function submitEdit() {
    editSubmitting.value = true;
    try {
        await api.put(`/users/${editTarget.value.id}`, editForm.value);
        toast.success('Đã cập nhật');
        editTarget.value = null;
        fetchUsers();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Có lỗi xảy ra');
    } finally {
        editSubmitting.value = false;
    }
}

function requestToggle(user) {
    toggleTarget.value = user;
}

async function confirmToggle() {
    toggleSubmitting.value = true;
    try {
        const user = toggleTarget.value;
        await api.post(`/users/${user.id}/toggle-active`);
        user.is_active = !user.is_active;
        toast.success('Đã đổi trạng thái hoạt động');
        toggleTarget.value = null;
    } catch (e) {
        toast.error(e.response?.data?.message || 'Có lỗi xảy ra');
    } finally {
        toggleSubmitting.value = false;
    }
}

async function confirmDelete() {
    deleteSubmitting.value = true;
    try {
        await api.delete(`/users/${deleteTarget.value.id}`);
        toast.success('Đã xoá người dùng');
        deleteTarget.value = null;
        fetchUsers();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Có lỗi xảy ra');
    } finally {
        deleteSubmitting.value = false;
    }
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold tracking-tight text-ink ml-1">Người dùng</h1>

            <Button @click="createOpen = true">
                <PlusIcon class="h-4 w-4" />
                Thêm người dùng
            </Button>
        </div>

        <div class="rounded-card border border-line bg-surface shadow-card">
            <!-- Bộ lọc -->
            <div class="flex flex-wrap items-center gap-3 border-b border-line px-4 py-3">
                <input v-model="search" class="field max-w-xs" type="search" placeholder="Tìm theo tên, username, email…">

                <select v-model="filters.is_admin" class="field w-auto" @change="filters.page = 1">
                    <option value="">Tất cả vai trò</option>
                    <option value="1">Admin</option>
                    <option value="0">Người dùng</option>
                </select>

                <select v-model="filters.is_active" class="field w-auto" @change="filters.page = 1">
                    <option value="">Mọi trạng thái</option>
                    <option value="1">Đang hoạt động</option>
                    <option value="0">Bị đình chỉ</option>
                </select>

                <span v-if="loading" class="text-sm text-muted">Đang tải…</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-alt text-xs font-semibold tracking-wide text-muted uppercase">
                        <tr>
                            <th v-for="column in columns" :key="column.key" class="px-4 py-3 whitespace-nowrap">
                                <button
                                    v-if="column.sortable"
                                    type="button"
                                    class="inline-flex items-center gap-1 uppercase transition hover:text-brand"
                                    @click="toggleSort(column.key)"
                                >
                                    {{ column.label }}
                                    <ChevronUpIcon v-if="filters.sort === column.key && filters.direction === 'asc'" class="h-3.5 w-3.5" />
                                    <ChevronDownIcon v-else-if="filters.sort === column.key" class="h-3.5 w-3.5" />
                                </button>
                                <template v-else>{{ column.label }}</template>
                            </th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">Hành động</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-line">
                        <tr v-for="user in users" :key="user.id" class="transition hover:bg-brand-soft/40">
                            <td class="px-4 py-3 text-ink font-semibold">{{ user.id }}</td>
                            <td class="px-4 py-3 text-ink">{{ user.name }}</td>
                            <td class="px-4 py-3 text-ink">{{ user.username }}</td>
                            <td class="px-4 py-3 text-ink">{{ user.email }}</td>
                            <td class="px-4 py-3">
                                <CheckCircleIcon v-if="user.is_admin" class="h-5 w-5 text-brand" title="Admin" />
                                <span v-else class="text-muted">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <ToggleSwitch
                                    :model-value="user.is_active"
                                    :disabled="auth.user?.id === user.id"
                                    @update:model-value="requestToggle(user)"
                                />
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-muted">
                                {{ formatDate(user.created_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-4 whitespace-nowrap">
                                    <button
                                        type="button"
                                        class="font-medium text-muted transition hover:text-ink"
                                        @click="openEdit(user)"
                                    >
                                        Sửa
                                    </button>
                                    <button
                                        v-if="auth.user?.id !== user.id"
                                        type="button"
                                        class="font-medium text-danger transition hover:brightness-90"
                                        @click="deleteTarget = user"
                                    >
                                        Xóa
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!loading && users.length === 0">
                            <td :colspan="columns.length + 1" class="px-4 py-12 text-center text-muted">
                                Không có người dùng nào.
                            </td>
                        </tr>
                        <tr v-else-if="loading && users.length === 0">
                            <td :colspan="columns.length + 1" class="px-4 py-12 text-center text-muted">
                                Đang tải...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Phân trang -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3 text-sm">
                <div class="flex items-center gap-2 text-muted">
                    <span>Mỗi trang</span>
                    <select v-model.number="filters.per_page" class="field w-auto py-1">
                        <option v-for="size in [5, 10, 25, 50]" :key="size" :value="size">{{ size }}</option>
                    </select>
                    <span>{{ meta.total }} người dùng</span>
                </div>

                <div class="flex items-center gap-3">
                    <Button
                        variant="secondary"
                        size="sm"
                        :disabled="filters.page <= 1"
                        @click="filters.page--"
                    >
                        Trước
                    </Button>
                    <span class="text-muted">Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
                    <Button
                        variant="secondary"
                        size="sm"
                        :disabled="filters.page >= meta.last_page"
                        @click="filters.page++"
                    >
                        Sau
                    </Button>
                </div>
            </div>
        </div>

        <Modal :open="createOpen" title="Thêm người dùng" @close="createOpen = false">
            <form id="create-form" class="space-y-4" @submit.prevent="submitCreate">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-ink">Tên <span class="text-brand">*</span></label>
                    <input v-model="createForm.name" class="field" type="text" required>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-ink">Username <span class="text-brand">*</span></label>
                    <input v-model="createForm.username" class="field" type="text" autocomplete="off" required>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-ink">Email <span class="text-brand">*</span></label>
                    <input v-model="createForm.email" class="field" type="email" autocomplete="off" required>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-ink">Mật khẩu <span class="text-brand">*</span></label>
                    <input v-model="createForm.password" class="field" type="password" autocomplete="new-password" required>
                </div>
                <div class="flex items-center justify-between">
                    <label class="text-sm font-medium text-ink">Admin</label>
                    <ToggleSwitch v-model="createForm.is_admin" />
                </div>
            </form>
            <template #footer>
                <Button variant="ghost" @click="createOpen = false">Huỷ</Button>
                <Button type="submit" form="create-form" :disabled="createSubmitting">
                    {{ createSubmitting ? 'Đang tạo…' : 'Tạo' }}
                </Button>
            </template>
        </Modal>

        <Modal :open="editTarget !== null" title="Sửa người dùng" @close="editTarget = null">
            <form id="edit-form" class="space-y-4" @submit.prevent="submitEdit">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-ink">Tên <span class="text-brand">*</span></label>
                    <input v-model="editForm.name" class="field" type="text" required>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-ink">Username <span class="text-brand">*</span></label>
                    <input v-model="editForm.username" class="field" type="text" autocomplete="off" required>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-ink">Email <span class="text-brand">*</span></label>
                    <input v-model="editForm.email" class="field" type="email" autocomplete="off" required>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-ink">Mật khẩu mới (bỏ trống nếu không đổi)</label>
                    <input v-model="editForm.password" class="field" type="password" autocomplete="new-password">
                </div>
                <div class="flex items-center justify-between">
                    <label class="text-sm font-medium text-ink">Admin</label>
                    <ToggleSwitch v-model="editForm.is_admin" :disabled="auth.user?.id === editTarget?.id" />
                </div>
            </form>
            <template #footer>
                <Button variant="ghost" @click="editTarget = null">Huỷ</Button>
                <Button type="submit" form="edit-form" :disabled="editSubmitting">
                    {{ editSubmitting ? 'Đang lưu…' : 'Lưu' }}
                </Button>
            </template>
        </Modal>

        <Modal :open="deleteTarget !== null" title="Xoá người dùng" @close="deleteTarget = null">
            <p class="text-sm text-muted">
                Người dùng <strong class="text-ink">{{ deleteTarget?.name }}</strong> sẽ bị xoá khỏi hệ thống.
                Hành động này không hoàn tác được.
            </p>
            <template #footer>
                <Button variant="ghost" @click="deleteTarget = null">Huỷ</Button>
                <Button variant="danger" :disabled="deleteSubmitting" @click="confirmDelete">
                    {{ deleteSubmitting ? 'Đang xoá…' : 'Xoá' }}
                </Button>
            </template>
        </Modal>

        <Modal :open="toggleTarget !== null" title="Thay đổi trạng thái" @close="toggleTarget = null">
            <p class="text-sm text-muted">
                Bạn có chắc chắn muốn <span class="font-medium text-ink">{{ toggleTarget?.is_active ? 'đình chỉ' : 'kích hoạt' }}</span> người dùng <strong class="text-ink">{{ toggleTarget?.name }}</strong>?
            </p>
            <template #footer>
                <Button variant="ghost" @click="toggleTarget = null">Huỷ</Button>
                <Button :variant="toggleTarget?.is_active ? 'danger' : 'primary'" :disabled="toggleSubmitting" @click="confirmToggle">
                    {{ toggleSubmitting ? 'Đang lưu…' : 'Xác nhận' }}
                </Button>
            </template>
        </Modal>
    </div>
</template>
