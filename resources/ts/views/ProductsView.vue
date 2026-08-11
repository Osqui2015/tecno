<script setup lang="ts">
import { onMounted, watch, computed } from 'vue';
import { storeToRefs } from 'pinia';
import { useProductsStore } from '@/stores/products';
import { useRoute } from 'vue-router';
import ProductCard from '@/components/ProductCard.vue';
import PageHeader from '@/components/PageHeader.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import EmptyState from '@/components/EmptyState.vue';
import SvgIcon from '@/components/SvgIcon.vue';
import ProductAutocomplete from '@/components/ProductAutocomplete.vue';

const store = useProductsStore();
const { searchQuery, filterCategory, filterBrand, filterSource } = storeToRefs(store);
const route = useRoute();

onMounted(() => {
    store.fetchProducts();
    store.fetchCategories();
    if (route.params.slug) {
        const cat = store.categories.find(
            (c) => c.slug === route.params.slug
        );
        if (cat) store.setCategory(cat.id);
    }
});

watch(
    () => route.params.slug,
    (slug) => {
        if (!slug) {
            store.setCategory(null);
        } else {
            const cat = store.categories.find((c) => c.slug === slug);
            if (cat) store.setCategory(cat.id);
        }
    }
);



const products = computed(() => store.filteredProducts);

function clearFilters() {
    store.setCategory(null);
    store.setBrand(null);
    store.setSource('all');
    store.setSearch('');
}

const activeFilters = computed(() => {
    const f: { label: string; clear: () => void }[] = [];
    if (store.filterCategory !== null) {
        const cat = store.categories.find((c) => c.id === store.filterCategory);
        if (cat) f.push({ label: cat.name, clear: () => store.setCategory(null) });
    }
    if (store.filterBrand) {
        f.push({ label: store.filterBrand, clear: () => store.setBrand(null) });
    }
    if (store.filterSource !== 'all') {
        f.push({
            label: store.filterSource === 'daz' ? 'Dazimport' : 'Locales',
            clear: () => store.setSource('all'),
        });
    }
    if (store.searchQuery.trim()) {
        f.push({ label: `"${store.searchQuery}"`, clear: () => store.setSearch('') });
    }
    return f;
});
</script>

<template>
    <div class="space-y-6">
        <PageHeader
            icon="box"
            title="Todos los productos"
            :subtitle="
                store.totalProducts > 0
                    ? `${store.totalProducts} productos en el catálogo · ${products.length} visibles`
                    : `${products.length} productos encontrados`
            "
        />

        <!-- Search bar & filters card (sticky) -->
        <div class="sticky top-16 z-40 card p-5 animate-fade-in space-y-4 backdrop-blur-md bg-white/95 border border-slate-100 shadow-lg">
            <div class="relative">
                <ProductAutocomplete
                    placeholder="Buscar por nombre, marca o SKU..."
                    :max-results="10"
                />
            </div>

            <!-- Filter row -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex flex-wrap gap-2.5 flex-1 min-w-[280px]">
                    <div class="relative flex-1 max-w-[220px]">
                        <select
                            :value="store.filterCategory ?? ''"
                            @change="store.setCategory(Number(($event.target as HTMLSelectElement).value) || null)"
                            class="input cursor-pointer pr-8 bg-no-repeat appearance-none"
                            style="background-image: url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22 fill=%22none%22%3E%3Cpath d=%22M7 9l3 3 3-3%22 stroke=%22%236b7280%22 stroke-width=%221.5%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22/%3E%3C/svg%3E'); background-position: right 0.75rem center; background-size: 1.25rem;"
                        >
                            <option value="">Categorías</option>
                            <option
                                v-for="cat in store.categories"
                                :key="cat.id"
                                :value="cat.id"
                            >
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>

                    <div class="relative flex-1 max-w-[220px]" v-if="store.brands.length > 0">
                        <select
                            :value="store.filterBrand ?? ''"
                            @change="store.setBrand(($event.target as HTMLSelectElement).value || null)"
                            class="input cursor-pointer pr-8 bg-no-repeat appearance-none"
                            style="background-image: url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22 fill=%22none%22%3E%3Cpath d=%22M7 9l3 3 3-3%22 stroke=%22%236b7280%22 stroke-width=%221.5%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22/%3E%3C/svg%3E'); background-position: right 0.75rem center; background-size: 1.25rem;"
                        >
                            <option value="">Marcas</option>
                            <option
                                v-for="brand in store.brands"
                                :key="brand"
                                :value="brand"
                            >
                                {{ brand }}
                            </option>
                        </select>
                    </div>
                </div>


            </div>

            <!-- Active filters drawer -->
            <div
                v-if="activeFilters.length > 0"
                class="flex flex-wrap items-center gap-2 pt-4 border-t border-slate-100/80"
            >
                <span class="text-[11px] text-slate-400 font-bold uppercase tracking-wider">Filtros activos:</span>
                <button
                    v-for="(f, i) in activeFilters"
                    :key="i"
                    @click="f.clear()"
                    class="chip chip-brand hover:bg-brand-200/50 hover:text-brand-900 transition-colors cursor-pointer"
                >
                    <span>{{ f.label }}</span>
                    <SvgIcon name="close" size="0.7rem" class="opacity-80" />
                </button>
                <button
                    @click="clearFilters"
                    class="ml-auto text-xs text-slate-400 hover:text-rose-600 font-bold transition-colors cursor-pointer"
                >
                    Limpiar filtros
                </button>
            </div>
        </div>

        <!-- Products List Results -->
        <LoadingSpinner v-if="store.loading" text="Buscando en catálogo..." />

        <EmptyState
            v-else-if="products.length === 0"
            icon="search"
            title="No se encontraron productos"
            description="Intenta modificar la búsqueda o los filtros seleccionados para encontrar lo que buscas."
        >
            <template #actions>
                <button @click="clearFilters" class="btn btn-primary">
                    Limpiar todos los filtros
                </button>
            </template>
        </EmptyState>

        <div
            v-else
            class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4"
        >
            <ProductCard
                v-for="(product, i) in products"
                :key="product.id"
                :product="product"
                :style="{ animationDelay: `${Math.min(i * 40, 320)}ms` }"
            />
        </div>

        <!-- Load more button -->
        <div
            v-if="!store.loading && products.length > 0"
            class="flex flex-col items-center gap-2 pt-6"
        >
            <button
                v-if="store.hasMore"
                @click="store.fetchMore()"
                :disabled="store.loadingMore"
                class="btn btn-lg btn-primary px-8 rounded-xl shadow-md"
            >
                <LoadingSpinner v-if="store.loadingMore" small />
                <template v-else>
                    <SvgIcon name="plus" size="1rem" />
                    <span>Cargar más productos</span>
                </template>
            </button>

            <div
                v-else-if="products.length > 0"
                class="flex items-center gap-2 text-xs text-slate-400 font-medium"
            >
                <SvgIcon name="check" size="0.85rem" class="text-emerald-500" />
                <span>Mostrando todos los {{ store.totalProducts }} productos</span>
            </div>

            <div
                v-if="store.hasMore"
                class="text-xs text-slate-400"
            >
                Mostrando {{ products.length }} de {{ store.totalProducts }} productos
            </div>
        </div>
    </div>
</template>