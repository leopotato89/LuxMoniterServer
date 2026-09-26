import { reactive, watch } from 'vue';

const DURATION = 700;

/**
 * Nội suy số theo thời gian (easeOutCubic) — để số liệu đếm lên/xuống thay vì nhảy.
 *
 * Port từ `anim()` của bản Alpine: dùng rAF + object reactive, nên template chỉ cần
 * đọc `values.pv_power` là tự cập nhật.
 *
 * @param {import('vue').Ref<Record<string, number>>} targets giá trị đích mỗi lần telemetry về
 * @param {string[]} keys các khoá cần nội suy
 */
export function useTween(targets, keys) {
    const values = reactive({});
    const frames = {};
    const running = {};

    function tween(key, from, to) {
        cancelAnimationFrame(frames[key]);
        running[key] = to;

        const startedAt = performance.now();

        const step = (now) => {
            const k = Math.min(1, (now - startedAt) / DURATION);
            const eased = 1 - (1 - k) ** 3;

            values[key] = from + (to - from) * eased;

            if (k < 1) {
                frames[key] = requestAnimationFrame(step);
            } else {
                values[key] = to;
                delete frames[key];
                delete running[key];
            }
        };

        frames[key] = requestAnimationFrame(step);
    }

    watch(
        targets,
        (next) => {
            keys.forEach((key) => {
                const to = Number(next?.[key]) || 0;

                // Chưa có giá trị ⇒ gán thẳng, không đếm từ 0.
                if (!(key in values)) {
                    values[key] = to;

                    return;
                }

                if (values[key] === to || running[key] === to) {
                    return;
                }

                tween(key, values[key], to);
            });
        },
        { immediate: true },
    );

    function stop() {
        Object.keys(frames).forEach((key) => cancelAnimationFrame(frames[key]));
    }

    return { values, stop };
}
