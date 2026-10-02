<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import {
    CheckCircleIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    PlusIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import Button from '../components/ui/Button.vue';
import Modal from '../components/ui/Modal.vue';
import ToggleSwitch from '../components/ui/ToggleSwitch.vue';
import { useAuthStore } from '../stores/auth';
import { useDevicesStore } from '../stores/devices';
import api from '../lib/api';

const auth = useAuthStore();
const devices = useDevicesStore();
const router = useRouter();

const search = ref('');
const claimOpen = ref(false);
const claimForm = ref({ serial: '', device_code: '' });
const claimSubmitting = ref(false);

const createOpen = ref(false);
const createForm = ref({ serial: '', name: '' });
const createSubmitting = ref(false);

const editTarget = ref(null);
const editForm = ref({ name: '' });
const editSubmitting = ref(false);

const deleteTarget = ref(null);
const deleteSubmitting = ref(false);

const users = ref([]);

async function fetchUsers() {
    if (!auth.isAdmin) return;
    try {
        const { data } = await api.get('/users');
        users.value = data.data;
    } catch (e) {
        console.error('Failed to fetch users', e);
    }
}

const columns = [
    { key: 'serial', label: 'Serial', sortable: true },
    { key: 'name', label: 'Tên thiết bị', sortable: true },
    { key: 'owner', label: 'Chủ sở hữu' },
    { key: 'verified_at', label: 'Đã xác minh' },
    { key: 'created_at', label: 'Tạo lúc', sortable: true },
];

const claimedFilter = computed({
    get: () => devices.filters.claimed,
    set: (value) => {
        devices.filters.claimed = value;
        devices.filters.page = 1;
    },
});

// Gõ tìm kiếm: chờ 300ms rồi mới gọi API.
let searchTimer = null;

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        devices.filters.search = value;
        devices.filters.page = 1;
        devices.fetch();
    }, 300);
});

watch(
    () => [devices.filters.page, devices.filters.per_page, devices.filters.sort, devices.filters.direction, devices.filters.claimed],
    () => devices.fetch(),
);

onMounted(() => {
    devices.fetch();
    fetchUsers();
});

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

async function submitClaim() {
    claimSubmitting.value = true;

    try {
        const ok = await devices.claim(claimForm.value.serial.trim(), claimForm.value.device_code.trim());

        if (ok) {
            claimOpen.value = false;
            claimForm.value = { serial: '', device_code: '' };
        }
    } finally {
        claimSubmitting.value = false;
    }
}

async function submitCreate() {
    createSubmitting.value = true;

    try {
        const payload = {
            serial: createForm.value.serial.trim(),
            name: createForm.value.name.trim() || null,
        };
        if (createForm.value.owner_id !== undefined) {
            payload.owner_id = createForm.value.owner_id;
        }

        const ok = await devices.create(payload);

        if (ok) {
            createOpen.value = false;
            createForm.value = { serial: '', name: '', owner_id: '' };
        }
    } finally {
        createSubmitting.value = false;
    }
}

function openEdit(device) {
    editTarget.value = device;
    editForm.value = { 
        name: device.name ?? '',
        owner_id: device.owner?.id ?? '',
    };
}

async function submitEdit() {
    editSubmitting.value = true;

    try {
        const payload = { name: editForm.value.name || null };
        if (auth.isAdmin) {
            payload.owner_id = editForm.value.owner_id || null;
        }

        const ok = await devices.update(editTarget.value.serial, payload);

        if (ok) {
            editTarget.value = null;
        }
    } finally {
        editSubmitting.value = false;
    }
}

async function confirmDelete() {
    deleteSubmitting.value = true;

    try {
        const ok = await devices.destroy(deleteTarget.value.serial);

        if (ok) {
            deleteTarget.value = null;
        }
    } finally {
        deleteSubmitting.value = false;
    }
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-xl font-bold tracking-tight text-ink ml-1">Thiết bị</h1>

            <Button v-if="auth.isAdmin" @click="createOpen = true">
                <PlusIcon class="h-4 w-4" />
                Tạo thiết bị
            </Button>
            <Button v-else @click="claimOpen = true">
                <PlusIcon class="h-4 w-4" />
                Thêm thiết bị giám sát
            </Button>
        </div>

        <div class="rounded-card border border-line bg-surface shadow-card">
            <!-- Bộ lọc -->
            <div class="flex flex-wrap items-center gap-3 border-b border-line px-4 py-3">
                <input v-model="search" class="field max-w-xs" type="search" placeholder="Tìm theo serial hoặc tên…">

                <select v-if="auth.isAdmin" v-model="claimedFilter" class="field w-auto">
                    <option value="">Mọi trạng thái gắn chủ</option>
                    <option value="1">Đã gắn chủ</option>
                    <option value="0">Chưa gắn</option>
                </select>

                <span v-if="devices.loading" class="text-sm text-muted">Đang tải…</span>
            </div>

            <!-- Bảng -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-alt text-xs font-semibold tracking-wide text-muted uppercase">
                        <tr>
                            <th v-for="column in columns" :key="column.key" class="px-4 py-3 whitespace-nowrap">
                                <button
                                    v-if="column.sortable"
                                    type="button"
                                    class="inline-flex items-center gap-1 uppercase transition hover:text-brand"
                                    @click="devices.toggleSort(column.key)"
                                >
                                    {{ column.label }}
                                    <ChevronUpIcon v-if="devices.filters.sort === column.key && devices.filters.direction === 'asc'" class="h-3.5 w-3.5" />
                                    <ChevronDownIcon v-else-if="devices.filters.sort === column.key" class="h-3.5 w-3.5" />
                                </button>
                                <template v-else>{{ column.label }}</template>
                            </th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">Hành động</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-line">
                        <tr 
                            v-for="device in devices.items" 
                            :key="device.serial" 
                            class="transition hover:bg-brand-soft/40 cursor-pointer"
                            @click="router.push({ name: 'devices.show', params: { serial: device.serial } })"
                        >
                            <td class="px-4 py-3 font-semibold text-brand">
                                {{ device.serial }}
                            </td>
                            <td class="px-4 py-3 text-ink">{{ device.name || '—' }}</td>
                            <td class="px-4 py-3 text-ink">
                                <span v-if="device.owner">{{ device.owner.name }}</span>
                                <span v-else class="text-muted">Chưa gắn</span>
                            </td>
                            <td class="px-4 py-3">
                                <CheckCircleIcon
                                    v-if="device.verified_at"
                                    class="h-5 w-5 text-ok"
                                    title="Đã xác minh"
                                />
                                <XCircleIcon v-else class="h-5 w-5 text-danger" title="Chưa xác minh" />
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-muted">
                                {{ formatDate(device.created_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-4 whitespace-nowrap">
                                    <button
                                        type="button"
                                        class="font-medium text-muted transition hover:text-ink"
                                        @click.stop="openEdit(device)"
                                    >
                                        Sửa
                                    </button>
                                    <button
                                        v-if="auth.isAdmin"
                                        type="button"
                                        class="font-medium text-danger transition hover:brightness-90"
                                        @click.stop="deleteTarget = device"
                                    >
                                        Xóa
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!devices.loading && devices.items.length === 0">
                            <td :colspan="columns.length + 1" class="px-4 py-12 text-center text-muted">
                                Không có thiết bị nào.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Phân trang -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line px-4 py-3 text-sm">
                <div class="flex items-center gap-2 text-muted">
                    <span>Mỗi trang</span>
                    <select v-model.number="devices.filters.per_page" class="field w-auto py-1">
                        <option v-for="size in [5, 10, 25, 50]" :key="size" :value="size">{{ size }}</option>
                    </select>
                    <span>{{ devices.meta.total }} thiết bị</span>
                </div>

                <div class="flex items-center gap-3">
                    <Button
                        variant="secondary"
                        size="sm"
                        :disabled="devices.filters.page <= 1"
                        @click="devices.filters.page--"
                    >
                        Trước
                    </Button>
                    <span class="text-muted">Trang {{ devices.meta.current_page }} / {{ devices.meta.last_page }}</span>
                    <Button
                        variant="secondary"
                        size="sm"
                        :disabled="devices.filters.page >= devices.meta.last_page"
                        @click="devices.filters.page++"
                    >
                        Sau
                    </Button>
                </div>
            </div>
        </div>

        <!-- Modal: thêm thiết bị giám sát (user) -->
        <Modal :open="claimOpen" title="Thêm thiết bị giám sát" @close="claimOpen = false">
            <p class="mb-4 text-sm text-muted">
                Nhập serial và mã thiết bị lấy từ trang web của ESP32 để xác minh.
            </p>

            <form id="claim-form" class="space-y-4" @submit.prevent="submitClaim">
                <div>
                    <label for="claim-serial" class="mb-1.5 block text-sm font-medium text-ink">
                        Serial thiết bị <span class="text-brand">*</span>
                    </label>
                    <input id="claim-serial" v-model="claimForm.serial" class="field" type="text" required>
                </div>

                <div>
                    <label for="claim-code" class="mb-1.5 block text-sm font-medium text-ink">
                        Mã thiết bị (từ ESP32) <span class="text-brand">*</span>
                    </label>
                    <input id="claim-code" v-model="claimForm.device_code" class="field" type="text" required>
                </div>
            </form>

            <template #footer>
                <Button variant="ghost" @click="claimOpen = false">Huỷ</Button>
                <Button type="submit" form="claim-form" :disabled="claimSubmitting">
                    {{ claimSubmitting ? 'Đang kiểm tra…' : 'Thêm thiết bị' }}
                </Button>
            </template>
        </Modal>

        <!-- Modal: tạo thiết bị (admin) -->
        <Modal :open="createOpen" title="Tạo thiết bị" @close="createOpen = false">
            <form id="create-form" class="space-y-4" @submit.prevent="submitCreate">
                <div>
                    <label for="create-serial" class="mb-1.5 block text-sm font-medium text-ink">
                        Serial <span class="text-brand">*</span>
                    </label>
                    <input id="create-serial" v-model="createForm.serial" class="field" type="text" required>
                </div>

                <div>
                    <label for="create-name" class="mb-1.5 block text-sm font-medium text-ink">Tên thiết bị</label>
                    <input id="create-name" v-model="createForm.name" class="field" type="text">
                </div>

                <div v-if="auth.isAdmin">
                    <label for="create-owner" class="mb-1.5 block text-sm font-medium text-ink">Chủ sở hữu</label>
                    <select id="create-owner" v-model="createForm.owner_id" class="field">
                        <option value="">Không gán</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }} ({{ user.username }})
                        </option>
                    </select>
                </div>
            </form>

            <template #footer>
                <Button variant="ghost" @click="createOpen = false">Huỷ</Button>
                <Button type="submit" form="create-form" :disabled="createSubmitting">
                    {{ createSubmitting ? 'Đang tạo…' : 'Tạo thiết bị' }}
                </Button>
            </template>
        </Modal>

        <!-- Modal: sửa thiết bị -->
        <Modal :open="editTarget !== null" title="Sửa thiết bị" @close="editTarget = null">
            <form id="edit-form" class="space-y-4" @submit.prevent="submitEdit">
                <div>
                    <label for="edit-name" class="mb-1.5 block text-sm font-medium text-ink">Tên thiết bị</label>
                    <input id="edit-name" v-model="editForm.name" class="field" type="text">
                </div>

                <div v-if="auth.isAdmin">
                    <label for="edit-owner" class="mb-1.5 block text-sm font-medium text-ink">Chủ sở hữu</label>
                    <select id="edit-owner" v-model="editForm.owner_id" class="field">
                        <option value="">Không gán</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }} ({{ user.username }})
                        </option>
                    </select>
                </div>
            </form>

            <template #footer>
                <Button variant="ghost" @click="editTarget = null">Huỷ</Button>
                <Button type="submit" form="edit-form" :disabled="editSubmitting">
                    {{ editSubmitting ? 'Đang lưu…' : 'Lưu' }}
                </Button>
            </template>
        </Modal>

        <!-- Modal: xác nhận xoá -->
        <Modal :open="deleteTarget !== null" title="Xoá thiết bị" @close="deleteTarget = null">
            <p class="text-sm text-muted">
                Thiết bị <strong class="text-ink">{{ deleteTarget?.serial }}</strong> sẽ bị xoá khỏi hệ thống.
                Hành động này không hoàn tác được.
            </p>

            <template #footer>
                <Button variant="ghost" @click="deleteTarget = null">Huỷ</Button>
                <Button variant="danger" :disabled="deleteSubmitting" @click="confirmDelete">
                    {{ deleteSubmitting ? 'Đang xoá…' : 'Xoá' }}
                </Button>
            </template>
        </Modal>
    </div>
</template>
