<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    currentPage: number;
    lastPage: number;
    total: number;
}>();

const emit = defineEmits<{
    (e: 'page-change', page: number): void;
}>();

const pages = computed(() => {
    const p: number[] = [];
    const maxVisible = 5;
    let start = Math.max(1, props.currentPage - 2);
    let end = Math.min(props.lastPage, start + maxVisible - 1);

    if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1);
    }

    for (let i = start; i <= end; i++) {
        p.push(i);
    }
    return p;
});

function changePage(page: number) {
    if (page >= 1 && page <= props.lastPage && page !== props.currentPage) {
        emit('page-change', page);
    }
}
</script>

<template>
    <div v-if="lastPage > 1" class="flex flex-col sm:flex-row items-center justify-between gap-4 py-4 border-t border-slate-100 dark:border-slate-800">
        <span class="text-xs text-slate-500 dark:text-slate-400">
            Página {{ currentPage }} de {{ lastPage }} ({{ total }} productos)
        </span>

        <div class="flex items-center gap-1">
            <button
                :disabled="currentPage <= 1"
                @click="changePage(currentPage - 1)"
                class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
                Anterior
            </button>

            <button
                v-for="page in pages"
                :key="page"
                @click="changePage(page)"
                :class="page === currentPage ? 'bg-primary-600 text-white font-bold border-primary-600' : 'border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800'"
                class="w-8 h-8 rounded-lg text-xs font-semibold flex items-center justify-center transition-colors"
            >
                {{ page }}
            </button>

            <button
                :disabled="currentPage >= lastPage"
                @click="changePage(currentPage + 1)"
                class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
                Siguiente
            </button>
        </div>
    </div>
</template>
