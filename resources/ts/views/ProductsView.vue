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
import ProductFilters from '@/components/ProductFilters.vue';
import Pagination from '@/components/Pagination.vue';

const store = useProductsStore();
const { searchQuery, filterCategory, minPrice, maxPrice, sortBy, categories } = storeToRefs(store);
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

watch([filterCategory, minPrice, maxPrice, sortBy], () => {
    store.fetchProducts(1);
});

const products = computed(() => store.filteredProducts);

function clearFilters() {
    store.setCategory(null);
    store.minPrice = null;
    store.maxPrice = null;
    store.sortBy = 'name_asc';
    store.setSearch('');
    store.fetchProducts(1);
}

function handlePageChange(page: number) {
    store.fetchProducts(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

const activeFilters = computed(() => {
    const f: { label: string; clear: () => void }[] = [];
    if (store.filterCategory !== null) {
        const cat = store.categories.find((c) => c.id === store.filterCategory);
        if (cat) f.push({ label: cat.name, clear: () => store.setCategory(null) });
    }
    if (store.minPrice !== null || store.maxPrice !== null) {
        const min = store.minPrice ? `$${store.minPrice}` : '$0';
        const max = store.maxPrice ? `$${store.maxPrice}` : '∞';
        f.push({ label: `${min} - ${max}`, clear: () => { store.minPrice = null; store.maxPrice = null; } });
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
                    ? `${store.totalProducts} productos en el catálogo`
                    : 'Catálogo de productos'
            "
        />

        <!-- Search bar -->
        <div class="card p-4 animate-fade-in backdrop-blur-md bg-white/95 dark:bg-slate-900/95 border border-slate-100 dark:border-slate-800 shadow-sm">
            <ProductAutocomplete
                placeholder="Buscar por nombre, marca o SKU..."
                :max-results="10"
            />

            <!-- Chips de filtros activos -->
            <div
                v-if="activeFilters.length > 0"
                class="flex flex-wrap items-center gap-2 pt-3 mt-3 border-t border-slate-100 dark:border-slate-800"
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

        <!-- Grid Principal: Sidebar Filtros + Productos -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Sidebar Filtros -->
            <div class="lg:col-span-1">
                <ProductFilters
                    :categories="categories"
                    v-model:category-id="filterCategory"
                    v-model:min-price="minPrice"
                    v-model:max-price="maxPrice"
                    v-model:sort-by="sortBy"
                    @clear-filters="clearFilters"
                />
            </div>

            <!-- Listado de Productos & Paginación -->
            <div class="lg:col-span-3 space-y-6">
                <LoadingSpinner v-if="store.loading" text="Cargando productos..." />

                <EmptyState
                    v-else-if="products.length === 0"
                    icon="search"
                    title="No se encontraron productos"
                    description="Intenta modificar la búsqueda o los filtros de precio/categoría para encontrar lo que buscas."
                >
                    <template #actions>
                        <button @click="clearFilters" class="btn btn-primary">
                            Limpiar todos los filtros
                        </button>
                    </template>
                </EmptyState>

                <div v-else class="space-y-6">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <ProductCard
                            v-for="(product, i) in products"
                            :key="product.id"
                            :product="product"
                            :style="{ animationDelay: `${Math.min(i * 40, 320)}ms` }"
                        />
                    </div>

                    <!-- Componente Paginación -->
                    <Pagination
                        :current-page="store.currentPage"
                        :last-page="store.lastPage"
                        :total="store.totalProducts"
                        @page-change="handlePageChange"
                    />
                </div>
            </div>
        </div>
    </div>
</template>