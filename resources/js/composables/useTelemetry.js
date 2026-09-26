import { onUnmounted, ref } from 'vue';
import Pusher from 'pusher-js';
import api from '../lib/api';

const APP_KEY = import.meta.env.VITE_REVERB_APP_KEY;
const HOST = import.meta.env.VITE_REVERB_HOST;
const PORT = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);
const FORCE_TLS = import.meta.env.VITE_REVERB_SCHEME === 'https';

/**
 * Telemetry realtime của một thiết bị qua WebSocket (Reverb).
 *
 * - Ảnh chụp đầu tiên lấy MỘT lần bằng HTTP để màn hình có số ngay khi mở trang.
 *   Đây không phải polling: daemon chỉ phát khi số liệu đổi, nên nếu ngồi chờ event
 *   đầu tiên thì có thể lâu (buổi tối `pv_power` đứng yên).
 * - Sau đó chỉ nghe kênh riêng `device.{serial}`, xác thực kênh qua
 *   `POST /api/v1/broadcasting/auth` bằng Bearer token — dùng chung cho SPA và mobile.
 * - Kết nối WebSocket đi thẳng tới daemon Reverb nên KHÔNG chiếm worker PHP
 *   (đó là lý do không dùng SSE: 1 worker/client, và trên Windows `php artisan serve`
 *   chỉ có 1 worker nên mở 1 trang là treo cả app).
 */
export function useTelemetry(serial) {
    const latest = ref(null);
    const online = ref(false);
    const timestamp = ref(null);
    const connected = ref(false);

    function apply(snapshot) {
        if (!snapshot) {
            return;
        }

        latest.value = snapshot.latest ?? null;
        online.value = snapshot.online === true;
        timestamp.value = snapshot.timestamp ?? null;
    }

    if (!APP_KEY) {
        // Không có key thì không có realtime — báo rõ thay vì im lặng.
        console.error('Thiếu VITE_REVERB_APP_KEY. Kiểm tra .env rồi chạy lại npm run dev / npm run build.');

        return { latest, online, timestamp, connected };
    }

    api.get(`/devices/${serial}/realtime`)
        .then(({ data }) => apply(data.data))
        .catch(() => {
            // Kênh WebSocket sẽ cập nhật ngay khi có bản ghi mới.
        });

    const pusher = new Pusher(APP_KEY, {
        wsHost: HOST,
        wsPort: PORT,
        wssPort: PORT,
        forceTLS: FORCE_TLS,
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
        cluster: 'mt1', // pusher-js bắt buộc phải có; Reverb không dùng tới
        channelAuthorization: {
            customHandler: ({ socketId, channelName }, callback) => {
                api.post('/broadcasting/auth', { socket_id: socketId, channel_name: channelName })
                    .then(({ data }) => callback(null, data))
                    .catch((error) => callback(error, null));
            },
        },
    });

    const channel = pusher.subscribe(`private-device.${serial}`);

    channel.bind('telemetry', (payload) => apply(payload?.snapshot));

    pusher.connection.bind('state_change', ({ current }) => {
        connected.value = current === 'connected';
    });

    onUnmounted(() => pusher.disconnect());

    return { latest, online, timestamp, connected };
}
