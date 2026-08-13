<script setup lang="ts">
import { onMounted } from 'vue';
import { useThemeStore } from '@/stores/themeStore';
import SvgIcon from '@/components/SvgIcon.vue';

const themeStore = useThemeStore();

onMounted(() => {
    themeStore.initTheme();
});
</script>

<template>
    <button
        @click="themeStore.toggleTheme()"
        class="p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors shadow-sm flex items-center justify-center cursor-pointer"
        :title="themeStore.isDark ? 'Cambiar a Modo Claro' : 'Cambiar a Modo Oscuro'"
    >
        <!-- Sol (Light Mode Icon) -->
        <svg v-if="themeStore.isDark" class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>

        <!-- Luna (Dark Mode Icon) -->
        <svg v-else class="w-4 h-4 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
        </svg>
    </button>
</template>
