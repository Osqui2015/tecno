import { defineStore } from 'pinia';
import { ref } from 'vue';

export type Theme = 'light' | 'dark' | 'system';

export const useThemeStore = defineStore('theme', () => {
    // Por defecto el sitio arranca en modo CLARO, sin importar el tema
    // del sistema operativo. Si el usuario elige oscuro manualmente, se
    // respeta (queda guardado en localStorage).
    const theme = ref<Theme>('light');
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
        // Solo 'dark' se respeta como elección explícita. Cualquier otro
        // valor (inexistente o el viejo 'system') cae en modo claro.
        theme.value = saved === 'dark' ? 'dark' : 'light';
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
