import { defineStore } from 'pinia';
import { ref, onMounted } from 'vue';

export type Theme = 'light' | 'dark' | 'system';

export const useThemeStore = defineStore('theme', () => {
    const theme = ref<Theme>('system');
    const isDark = ref(false);

    function applyTheme() {
        const root = document.documentElement;
        let activeDark = false;

        if (theme.value === 'dark') {
            activeDark = true;
        } else if (theme.value === 'light') {
            activeDark = false;
        } else {
            activeDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        }

        isDark.value = activeDark;
        if (activeDark) {
            root.classList.add('dark');
        } else {
            root.classList.remove('dark');
        }
    }

    function setTheme(newTheme: Theme) {
        theme.value = newTheme;
        localStorage.setItem('theme_preference', newTheme);
        applyTheme();
    }

    function toggleTheme() {
        if (isDark.value) {
            setTheme('light');
        } else {
            setTheme('dark');
        }
    }

    function initTheme() {
        const saved = localStorage.getItem('theme_preference') as Theme | null;
        if (saved && ['light', 'dark', 'system'].includes(saved)) {
            theme.value = saved;
        } else {
            theme.value = 'system';
        }
        applyTheme();
    }

    return {
        theme,
        isDark,
        setTheme,
        toggleTheme,
        initTheme,
    };
});
