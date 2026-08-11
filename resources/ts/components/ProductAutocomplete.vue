<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useProductsStore, type Product } from '@/stores/products';
import SvgIcon from '@/components/SvgIcon.vue';

const props = withDefaults(
    defineProps<{
        placeholder?: string;
        maxResults?: number;
        showCategory?: boolean;
        autofocus?: boolean;
    }>(),
    {
        placeholder: 'Buscar productos por nombre, marca o SKU...',
        maxResults: 8,
        showCategory: true,
        autofocus: false,
    }
);

const store = useProductsStore();
const router = useRouter();

const query = ref<string>(store.searchQuery ?? '');
const isOpen = ref(false);
const focusedIndex = ref(-1);
const inputRef = ref<HTMLInputElement | null>(null);
const containerRef = ref<HTMLDivElement | null>(null);

// Sincroniza el input con el store cuando cambia externamente (ej: filtros en otra vista).
watch(
    () => store.searchQuery,
    (val) => {
        if (val !== query.value) query.value = val ?? '';
    }
);

const suggestions = computed<Product[]>(() => {
    const q = query.value.trim().toLowerCase();
    if (q.length < 2) return [];

    // Buscar solo entre productos activos y de Daz.
    return store.products
        .filter((p) => {
            if (p.active === false) return false;
            return (
                p.name.toLowerCase().includes(q) ||
                p.description?.toLowerCase().includes(q) ||
                p.brand?.toLowerCase().includes(q) ||
                p.sku?.toLowerCase().includes(q)
            );
        })
        .slice(0, props.maxResults);
});

function onInput(e: Event) {
    const value = (e.target as HTMLInputElement).value;
    query.value = value;
    store.setSearch(value);
    isOpen.value = value.trim().length >= 2;
    focusedIndex.value = -1;
}

function clear() {
    query.value = '';
    store.setSearch('');
    isOpen.value = false;
    inputRef.value?.focus();
}

function selectProduct(p: Product) {
    isOpen.value = false;
    query.value = '';
    store.setSearch('');
    router.push({ name: 'product-detail', params: { id: p.id } });
}

function onKeydown(e: KeyboardEvent) {
    if (!isOpen.value) return;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        focusedIndex.value = Math.min(focusedIndex.value + 1, suggestions.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        focusedIndex.value = Math.max(focusedIndex.value - 1, -1);
    } else if (e.key === 'Enter') {
        if (focusedIndex.value >= 0) {
            e.preventDefault();
            selectProduct(suggestions.value[focusedIndex.value]);
        }
    } else if (e.key === 'Escape') {
        isOpen.value = false;
        focusedIndex.value = -1;
    }
}

function handleClickOutside(e: MouseEvent) {
    if (containerRef.value && !containerRef.value.contains(e.target as Node)) {
        isOpen.value = false;
        focusedIndex.value = -1;
    }
}

function onFocus() {
    if (query.value.trim().length >= 2) {
        isOpen.value = true;
    }
}

function formatPrice(n: number): string {
    return '$' + Math.round(n).toLocaleString('es-AR');
}

function highlight(text: string, q: string): string {
    if (!q.trim()) return text;
    const safe = text.replace(/</g, '&lt;');
    const re = new RegExp(`(${q.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'ig');
    return safe.replace(re, '<mark class="bg-amber-100 text-slate-900 rounded px-0.5">$1</mark>');
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    if (props.autofocus) {
        inputRef.value?.focus();
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div ref="containerRef" class="relative flex-1 min-w-[220px]">
        <!-- Search icon -->
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-450 pointer-events-none z-10">
            <SvgIcon name="search" size="1rem" />
        </span>

        <!-- Input -->
        <input
            ref="inputRef"
            :value="query"
            @input="onInput"
            @keydown="onKeydown"
            @focus="onFocus"
            type="text"
            :placeholder="placeholder"
            class="input pl-10 pr-10"
            autocomplete="off"
            spellcheck="false"
        />

        <!-- Clear button -->
        <button
            v-if="query.length > 0"
            @click="clear"
            type="button"
            class="absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 hover:text-slate-700 transition-colors cursor-pointer"
            aria-label="Limpiar búsqueda"
        >
            <SvgIcon name="close" size="0.7rem" />
        </button>

        <!-- Suggestions dropdown -->
        <transition name="dropdown">
            <div
                v-if="isOpen && suggestions.length > 0"
                class="absolute top-full left-0 right-0 mt-2 bg-white rounded-2xl shadow-2xl border border-slate-100 overflow-hidden z-50 max-h-[28rem] overflow-y-auto"
            >
                <div class="px-3 py-2 border-b border-slate-100 bg-slate-50/50">
                    <span class="text-[10px] uppercase tracking-wider font-bold text-slate-400">
                        {{ suggestions.length }} {{ suggestions.length === 1 ? 'sugerencia' : 'sugerencias' }}
                    </span>
                </div>
                <button
                    v-for="(p, i) in suggestions"
                    :key="p.id"
                    @click="selectProduct(p)"
                    @mouseenter="focusedIndex = i"
                    type="button"
                    :class="[
                        'w-full flex items-center gap-3 p-3 text-left transition-colors cursor-pointer border-b border-slate-50 last:border-b-0',
                        focusedIndex === i ? 'bg-brand-50' : 'hover:bg-slate-50',
                    ]"
                >
                    <div class="w-12 h-12 rounded-xl bg-slate-100 overflow-hidden shrink-0 border border-slate-100">
                        <img
                            v-if="p.image_url || p.image"
                            :src="(p.image_url ?? p.image) as string"
                            :alt="p.name"
                            class="w-full h-full object-cover"
                            loading="lazy"
                            referrerpolicy="no-referrer"
                            @error="($event.target as HTMLImageElement).style.display = 'none'"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center text-slate-300">
                            <SvgIcon name="box" size="1.1rem" />
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div
                            class="font-semibold text-sm text-slate-800 line-clamp-1"
                            v-html="highlight(p.name, query)"
                        ></div>
                        <div
                            v-if="showCategory && (p.category || p.brand)"
                            class="text-[10px] text-slate-400 uppercase font-bold tracking-wider mt-0.5"
                        >
                            <span v-if="p.category">{{ p.category.name }}</span>
                            <span v-if="p.brand" class="ml-1">· {{ p.brand }}</span>
                        </div>
                    </div>
                    <div class="text-sm font-black text-slate-700 shrink-0">
                        {{ formatPrice(Number(p.final_price ?? p.list_price ?? p.price)) }}
                    </div>
                </button>
            </div>
        </transition>

        <!-- No results -->
        <transition name="dropdown">
            <div
                v-if="isOpen && query.trim().length >= 2 && suggestions.length === 0"
                class="absolute top-full left-0 right-0 mt-2 bg-white rounded-2xl shadow-xl border border-slate-100 p-4 z-50 text-center"
            >
                <div class="flex flex-col items-center gap-1.5">
                    <SvgIcon name="search" size="1.4rem" class="text-slate-300" />
                    <div class="text-xs text-slate-500">
                        Sin resultados para <strong class="text-slate-700">"{{ query }}"</strong>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.dropdown-enter-active,
.dropdown-leave-active {
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
