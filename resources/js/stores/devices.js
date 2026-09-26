import { defineStore } from 'pinia';
import { ref } from 'vue';
import api, { firstError } from '../lib/api';
import { useToastStore } from './toast';

/**
 * Danh sách thiết bị + các thao tác ghi.
 *
 * Thông báo lỗi được đẩy vào toast ngay tại đây để view không phải lặp try/catch;
 * hàm trả về true/false để view quyết định đóng modal hay không.
 */
export const useDevicesStore = defineStore('devices', () => {
    const toast = useToastStore();

    const items = ref([]);
    const meta = ref({ current_page: 1, last_page: 1, per_page: 10, total: 0 });
    const loading = ref(false);

    const filters = ref({
        search: '',
        sort: 'name',
        direction: 'asc',
        page: 1,
        per_page: 10,
        claimed: '',
    });

    async function fetch() {
        loading.value = true;

        try {
            const params = { ...filters.value };

            // Bỏ các tham số rỗng để không gửi filter vô nghĩa.
            Object.keys(params).forEach((key) => {
                if (params[key] === '' || params[key] === null) {
                    delete params[key];
                }
            });

            const { data } = await api.get('/devices', { params });

            items.value = data.data;
            meta.value = data.meta;

            return true;
        } catch (e) {
            toast.error(firstError(e));

            return false;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Đổi cột sắp xếp; bấm lại cùng cột thì đảo chiều.
     */
    function toggleSort(column) {
        if (filters.value.sort === column) {
            filters.value.direction = filters.value.direction === 'asc' ? 'desc' : 'asc';
        } else {
            filters.value.sort = column;
            filters.value.direction = 'asc';
        }

        filters.value.page = 1;
    }

    function replaceItem(device) {
        const index = items.value.findIndex((item) => item.serial === device.serial);

        if (index !== -1) {
            items.value[index] = device;
        }
    }

    async function update(serial, payload) {
        try {
            const { data } = await api.patch(`/devices/${serial}`, payload);
            replaceItem(data.data);
            toast.success('Đã lưu thiết bị.');

            return true;
        } catch (e) {
            toast.error(firstError(e));

            return false;
        }
    }

    async function create(payload) {
        try {
            await api.post('/devices', payload);
            toast.success('Đã tạo thiết bị.');
            await fetch();

            return true;
        } catch (e) {
            toast.error(firstError(e));

            return false;
        }
    }

    async function destroy(serial) {
        try {
            await api.delete(`/devices/${serial}`);
            toast.success('Đã xoá thiết bị.');
            await fetch();

            return true;
        } catch (e) {
            toast.error(firstError(e));

            return false;
        }
    }

    async function claim(serial, deviceCode) {
        try {
            await api.post('/devices/claim', { serial, device_code: deviceCode });
            toast.success('Đã thêm thiết bị giám sát.');
            await fetch();

            return true;
        } catch (e) {
            toast.error(firstError(e));

            return false;
        }
    }

    async function toggleEnabled(device) {
        return update(device.serial, { enabled: !device.enabled });
    }

    return {
        items,
        meta,
        loading,
        filters,
        fetch,
        toggleSort,
        update,
        create,
        destroy,
        claim,
        toggleEnabled,
    };
});
