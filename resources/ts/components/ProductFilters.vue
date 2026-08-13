<script setup lang="ts">
import { ref, watch } from 'vue';

interface BrandCount {
    brand: string;
    count: number;
}

const props = defineProps<{
    availableBrands: BrandCount[];
    brand: string | null;
    minPrice: number | null;
    maxPrice: number | null;
    sortBy: string;
}>();

const emit = defineEmits<{
    (e: 'update:brand', value: string): void;
    (e: 'update:minPrice', value: number | null): void;
    (e: 'update:maxPrice', value: number | null): void;
    (e: 'update:sortBy', value: string): void;
    (e: 'clear-filters'): void;
}>();

const localMinPrice = ref<number | null>(props.minPrice);
const localMaxPrice = ref<number | null>(props.maxPrice);

watch(() => props.minPrice, (v) => localMinPrice.value = v);
watch(() => props.maxPrice, (v) => localMaxPrice.value = v);

function applyPriceFilter() {
    emit('update:minPrice', localMinPrice.value);
    emit('update:maxPrice', localMaxPrice.value);
}

function clearAll() {
    localMinPrice.value = null;
    localMaxPrice.value = null;
    emit('clear-filters');
}
</script>

<template>
    <div class="card p-5 space-y-6">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <h3 class="font-bold text-slate-800 dark:text-slate-100 text-sm tracking-wide uppercase">Filtros</h3>
            <button
                @click="clearAll"
                class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline"
            >
                Limpiar todo
            </button>
        </div>

        <!-- Ordenamiento -->
        <div class="space-y-2">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase">Ordenar Por</label>
            <select
                :value="sortBy"
                @change="emit('update:sortBy', ($event.target as HTMLSelectElement).value)"
                class="input text-xs"
            >
                <option value="name_asc">Nombre: A - Z</option>
                <option value="name_desc">Nombre: Z - A</option>
                <option value="price_asc">Precio: Menor a Mayor</option>
                <option value="price_desc">Precio: Mayor a Menor</option>
                <option value="newest">Más Recientes</option>
            </select>
        </div>

        <!-- Rango de Precio -->
        <div class="space-y-3">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase">Precio ($)</label>
            <div class="flex items-center gap-2">
                <input
                    v-model.number="localMinPrice"
                    type="number"
                    placeholder="Mínimo"
                    class="input text-xs p-2"
                />
                <span class="text-slate-400">-</span>
                <input
                    v-model.number="localMaxPrice"
                    type="number"
                    placeholder="Máximo"
                    class="input text-xs p-2"
                />
            </div>
            <button
                @click="applyPriceFilter"
                class="btn btn-secondary text-xs w-full py-1.5"
            >
                Aplicar Precio
            </button>
        </div>

        <!-- Marcas -->
        <div v-if="availableBrands && availableBrands.length > 0" class="space-y-2">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase">Marca</label>
            <div class="max-h-48 overflow-y-auto space-y-1 pr-1 custom-scrollbar">
                <button
                    @click="emit('update:brand', '')"
                    :class="!brand ? 'font-bold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-950/40' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800'"
                    class="w-full text-left px-2.5 py-1.5 rounded-lg text-xs flex justify-between items-center transition-colors"
                >
                    <span>Todas las marcas</span>
                </button>

                <button
                    v-for="b in availableBrands"
                    :key="b.brand"
                    @click="emit('update:brand', b.brand)"
                    :class="brand === b.brand ? 'font-bold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-950/40' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800'"
                    class="w-full text-left px-2.5 py-1.5 rounded-lg text-xs flex justify-between items-center transition-colors"
                >
                    <span class="truncate">{{ b.brand }}</span>
                    <span class="text-[10px] text-slate-400">({{ b.count }})</span>
                </button>
            </div>
        </div>
    </div>
</template>
