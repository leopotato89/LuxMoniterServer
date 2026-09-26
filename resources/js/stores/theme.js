import { defineStore } from 'pinia';
import { ref } from 'vue';

const THEME_KEY = 'lux_theme';

/**
 * Chế độ sáng/tối — cùng cơ chế với 9router: class `dark` trên <html>,
 * lựa chọn được nhớ trong localStorage, mặc định là tối.
 */
export const useThemeStore = defineStore('theme', () => {
    const isDark = ref(document.documentElement.classList.contains('dark'));

    function apply(dark) {
        isDark.value = dark;
        document.documentElement.classList.toggle('dark', dark);
        localStorage.setItem(THEME_KEY, dark ? 'dark' : 'light');
    }

    function toggle() {
        apply(!isDark.value);
    }

    return { isDark, toggle };
});
