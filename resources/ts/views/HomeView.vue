<script setup lang="ts">
import { onMounted, computed, ref } from 'vue';
import { useProductsStore } from '@/stores/products';
import ProductCard from '@/components/ProductCard.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import EmptyState from '@/components/EmptyState.vue';
import SvgIcon from '@/components/SvgIcon.vue';
import ProductAutocomplete from '@/components/ProductAutocomplete.vue';

const store = useProductsStore();
const heroImageError = ref(false);

onMounted(() => {
    if (store.products.length === 0) {
        store.fetchProducts();
    }
    if (store.categories.length === 0) {
        store.fetchCategories();
    }
});

// Destacados: productos con stock disponible, ordenados por más recientes.
// Antes se mostraban "Best Sellers" y "Top Articles" que eran slices arbitrarios
// del array (no eran realmente los más vendidos). Ahora se muestran como
// "Destacados" y "Recientes" sin mentir al usuario.
const bestSellers = computed(() =>
    store.products
        .filter((p) => p.stock > 0)
        .slice(0, 5)
);

// "Recientes": últimos actualizados que tengan stock. No pretende ser top ventas.
const topArticles = computed(() =>
    store.products
        .filter((p) => p.stock > 0)
        .slice(5, 15)
);

// Productos extra para mostrar abajo (después de bestSellers + topArticles).
// A medida que el usuario hace clic en "Cargar más" en el Home, esta lista crece.
const moreProducts = computed(() => store.products.slice(15));

// Productos "Explorá más" ya filtrados por categoría / marca / búsqueda.
// Los filtros NO afectan a bestSellers ni a topArticles (siempre son curados).
const filteredMoreProducts = computed(() => {
    const list = moreProducts.value;
    let result = list;

    if (store.filterCategory) {
        result = result.filter((p) => p.category_id === store.filterCategory);
    }
    if (store.filterBrand) {
        result = result.filter((p) => p.brand === store.filterBrand);
    }
    if (store.searchQuery.trim()) {
        const q = store.searchQuery.toLowerCase();
        result = result.filter(
            (p) =>
                p.name.toLowerCase().includes(q) ||
                p.description?.toLowerCase().includes(q) ||
                p.brand?.toLowerCase().includes(q) ||
                p.sku?.toLowerCase().includes(q)
        );
    }
    return result;
});

// Marcas disponibles SOLO dentro del bloque "Explorá más" (para que el filtro
// muestre solo marcas que efectivamente se puedan seleccionar).
const moreBrands = computed(() => {
    const set = new Set<string>();
    moreProducts.value.forEach((p) => {
        if (p.brand) set.add(p.brand);
    });
    return Array.from(set).sort();
});

// Cantidad de productos cargados que matchean el filtro actual.
// Sirve para el mensaje "X productos matchean tu filtro".
const filteredMatchCount = computed(() => {
    let result = store.products.slice(15);
    if (store.filterCategory) {
        result = result.filter((p) => p.category_id === store.filterCategory);
    }
    if (store.filterBrand) {
        result = result.filter((p) => p.brand === store.filterBrand);
    }
    if (store.searchQuery.trim()) {
        const q = store.searchQuery.toLowerCase();
        result = result.filter((p) =>
            p.name.toLowerCase().includes(q) ||
            p.description?.toLowerCase().includes(q) ||
            p.brand?.toLowerCase().includes(q) ||
            p.sku?.toLowerCase().includes(q)
        );
    }
    return result.length;
});

const hasActiveFilters = computed(
    () =>
        store.filterCategory !== null ||
        !!store.filterBrand ||
        store.searchQuery.trim().length > 0
);

function clearMoreFilters() {
    store.setCategory(null);
    store.setBrand(null);
    store.setSearch('');
}

// Highlighted hero product for the media block on the right of the Hero (sketch layout)
const heroProduct = computed(() => store.products.find(p => p.stock > 0) || store.products[0]);

// Categories formatted for sketch grid
const categoriesWithCount = computed(() => {
    const counts: Record<number, number> = {};
    store.products.forEach((p) => {
        counts[p.category_id] = (counts[p.category_id] ?? 0) + 1;
    });
    return store.categories
        .map((c) => ({ ...c, count: counts[c.id] ?? 0 }))
        .sort((a, b) => b.count - a.count);
});

const smallCategories = computed(() => categoriesWithCount.value.slice(0, 4));
const largeCategory = computed(() => categoriesWithCount.value[4] || categoriesWithCount.value[0]);

const categoryProducts = computed(() => {
    const map: Record<number, any> = {};
    store.categories.forEach((cat) => {
        const catProds = store.products.filter((p) => p.category_id === cat.id);
        if (catProds.length > 0) {
            // Selección aleatoria pero estable usando el ID de la categoría
            const idx = cat.id % catProds.length;
            map[cat.id] = catProds[idx];
        }
    });
    return map;
});

function getMaterialIcon(name: string): string {
    const n = name.toLowerCase();
    if (n.includes('auric') || n.includes('audi') || n.includes('soni')) return 'headphones';
    if (n.includes('celu') || n.includes('movil') || n.includes('tel') || n.includes('smart')) return 'smartphone';
    if (n.includes('comp') || n.includes('notebook') || n.includes('pc') || n.includes('tec')) return 'devices';
    if (n.includes('juego') || n.includes('consola') || n.includes('game') || n.includes('play')) return 'sports_esports';
    if (n.includes('reloj') || n.includes('smartwatch') || n.includes('hora')) return 'watch';
    if (n.includes('camara') || n.includes('foto') || n.includes('optic')) return 'photo_camera';
    if (n.includes('hogar') || n.includes('cocina')) return 'home';
    if (n.includes('ferre') || n.includes('herra')) return 'handyman';
    if (n.includes('deporte') || n.includes('fit') || n.includes('ejer')) return 'sports_soccer';
    if (n.includes('libro')) return 'menu_book';
    return 'label';
}

function getCategoryDescription(cat: { name: string; description?: string | null }) {
    const name = cat.name.toLowerCase();
    const hasDaz = cat.description && (
        cat.description.toLowerCase().includes('daz') || 
        cat.description.toLowerCase().includes('importado')
    );
    
    if (!cat.description || hasDaz) {
        if (name.includes('audio') || name.includes('auricular') || name.includes('sonido')) {
            return 'Auriculares, parlantes y sonido de alta fidelidad.';
        }
        if (name.includes('celular') || name.includes('movil') || name.includes('teléfono') || name.includes('accesorio')) {
            return 'Fundas, cargadores y accesorios para tu smartphone.';
        }
        if (name.includes('hogar') || name.includes('cocina') || name.includes('decoración') || name.includes('sabana') || name.includes('olla')) {
            return 'Artículos, ollas y equipamiento para tu casa.';
        }
        if (name.includes('ferretería') || name.includes('herramienta')) {
            return 'Herramientas y accesorios para tus proyectos.';
        }
        if (name.includes('tecnología') || name.includes('electrónica') || name.includes('computación') || name.includes('tv')) {
            return 'Gadgets, informática y lo último en electrónica.';
        }
        if (name.includes('indumentaria') || name.includes('ropa') || name.includes('moda') || name.includes('jean') || name.includes('remera')) {
            return 'Prendas y accesorios para todos los estilos.';
        }
        if (name.includes('deporte') || name.includes('fitness') || name.includes('ejercicio') || name.includes('pelota')) {
            return 'Equipamiento deportivo, indumentaria y accesorios.';
        }
        if (name.includes('libro') || name.includes('novela') || name.includes('lectura')) {
            return 'Novelas, best sellers y novedades literarias.';
        }
        return 'Selección exclusiva de productos de excelente calidad.';
    }
    return cat.description;
}

const formatPrice = (n: number) => {
    return '$' + Math.round(n).toLocaleString('es-AR');
};
</script>

<template>
    <div class="space-y-16 pb-12">
        <!-- 1. SPLIT HERO (Matches mockup: Title left, Large image right) -->
        <section
            class="relative overflow-hidden rounded-3xl bg-[#0b1c30] text-white p-8 md:p-14 shadow-xl border border-white/10 animate-fade-in"
        >
            <div class="absolute inset-0 opacity-10 pointer-events-none">
                <div class="absolute top-0 right-0 w-96 h-96 bg-brand-600/40 rounded-full blur-3xl animate-float"></div>
                <div class="absolute bottom-0 left-0 w-80 h-80 bg-purple-500/20 rounded-full blur-3xl"></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center relative">
                <!-- Hero Left Column -->
                <div class="lg:col-span-7 space-y-6">
                    <span
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-white/5 border border-white/10 text-xs font-semibold text-brand-300 animate-scale-in"
                    >
                        <span class="material-symbols-outlined text-sm text-brand-300">stars</span>
                        <span class="uppercase tracking-widest text-[10px]">Calidad Importada</span>
                    </span>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold tracking-tight leading-tight">
                        Explorá lo último en <br />
                        <span class="bg-gradient-to-r from-brand-300 via-purple-300 to-pink-300 bg-clip-text text-transparent">
                            tecnología y tendencias
                        </span>
                    </h1>
                    <p class="text-sm md:text-base text-slate-300 max-w-lg leading-relaxed font-normal">
                        Descubrí productos exclusivos con envíos exprés a todo el país. La mejor selección tecnológica en un solo lugar.
                    </p>
                    <div class="flex flex-wrap gap-3.5 pt-2">
                        <router-link
                            :to="{ name: 'products' }"
                            class="btn btn-lg btn-primary bg-brand-500 hover:bg-brand-600 hover:shadow-lg hover:shadow-brand-500/20"
                        >
                            Ver Catálogo
                            <span class="material-symbols-outlined text-sm">arrow_forward</span>
                        </router-link>
                        <a
                            href="#categorias-seccion"
                            class="btn btn-lg btn-secondary bg-white/5 border-white/10 text-white hover:bg-white/10 transition-all"
                        >
                            Categorías
                        </a>
                    </div>
                </div>

                <!-- Hero Right Column (Sleek image card with overlay) -->
                <div class="lg:col-span-5 flex justify-center w-full">
                    <div
                        v-if="heroProduct"
                        class="relative w-full h-[380px] md:h-[420px] rounded-2xl overflow-hidden border border-white/10 shadow-2xl group"
                    >
                        <img
                            v-if="heroProduct.image && !heroImageError"
                            :src="heroProduct.image"
                            :alt="heroProduct.name"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                            referrerpolicy="no-referrer"
                            @error="heroImageError = true"
                        />
                        <div v-else class="w-full h-full flex flex-col items-center justify-center bg-slate-950 text-white/30 gap-2 p-4">
                            <span class="material-symbols-outlined text-4xl text-white/20">videogame_asset</span>
                            <span class="text-[9px] uppercase font-bold tracking-wider text-white/45">
                                Imagen no disponible
                            </span>
                        </div>
                        <div class="absolute bottom-4 left-4 right-4 bg-[#0b1c30]/80 backdrop-blur-md p-4 rounded-xl border border-white/10">
                            <div class="flex justify-between items-end gap-2">
                                <div class="min-w-0">
                                    <p class="text-[9px] font-bold text-brand-300 uppercase tracking-wider mb-0.5">Producto Destacado</p>
                                    <p class="text-sm font-bold text-white line-clamp-1">{{ heroProduct.name }}</p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-base font-black text-white">
                                        {{ formatPrice(Number(heroProduct.list_price ?? heroProduct.price)) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. CATEGORIES HORIZONTAL PILLS BAR -->
        <section id="categorias-seccion" class="animate-slide-up py-4">
            <div class="flex flex-nowrap overflow-x-auto gap-3 pb-3 w-full justify-start px-2 scrollbar-thin">
                <button
                    v-for="cat in store.categories"
                    :key="cat.id"
                    @click="store.setCategory(cat.id); $router.push({ name: 'products' })"
                    class="flex items-center gap-2 px-6 py-3 bg-slate-100 hover:bg-brand-50 border border-slate-200/50 hover:border-brand-200 text-slate-800 font-bold text-xs rounded-full transition-all shadow-sm hover:scale-[1.03] cursor-pointer flex-shrink-0"
                >
                    <span class="material-symbols-outlined text-[18px] text-slate-500">{{ getMaterialIcon(cat.name) }}</span>
                    {{ cat.name }}
                </button>
            </div>
        </section>

        <!-- 3. BEST PRODUCTS -->
        <section class="animate-slide-up">
            <div class="flex items-end justify-between mb-6">
                <div>
                    <h2 class="section-title">Productos Destacados</h2>
                    <p class="text-xs text-slate-400 mt-2.5 max-w-md">
                        Nuestra selección premium con la mejor calidad y precios exclusivos.
                    </p>
                </div>
                <router-link
                    :to="{ name: 'products' }"
                    class="hidden sm:inline-flex items-center gap-1 text-xs font-bold text-brand-600 hover:text-brand-700"
                >
                    <span>Ver todos</span>
                    <span class="material-symbols-outlined text-sm">arrow_forward</span>
                </router-link>
            </div>

            <LoadingSpinner v-if="store.loading" text="Cargando catálogo..." />

            <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                <ProductCard
                    v-for="product in bestSellers"
                    :key="product.id"
                    :product="product"
                />
            </div>
        </section>

        <!-- 4. ÚLTIMAS NOVEDADES -->
        <section class="animate-slide-up">
            <div class="mb-6">
                <h2 class="section-title">Últimas Novedades</h2>
                <p class="text-xs text-slate-400 mt-2.5">
                    Novedades agregadas recientemente a nuestro stock.
                </p>
            </div>

            <EmptyState
                v-if="topArticles.length === 0"
                icon="box"
                title="Aún no hay productos adicionales"
                description="Importá productos desde el panel administrativo de Dazimportadora usando el comando."
            />

            <div
                v-else
                class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4"
            >
                <ProductCard
                    v-for="product in topArticles"
                    :key="product.id"
                    :product="product"
                />
            </div>
        </section>

        <!-- 5. EXPLORE MORE FROM CATALOG -->
        <section v-if="moreProducts.length > 0" class="animate-slide-up">
            <div class="flex items-end justify-between mb-6">
                <div>
                    <h2 class="section-title">Explorá más del catálogo</h2>
                    <p class="text-xs text-slate-400 mt-2.5">
                        Continúa descubriendo productos importados de Dazimportadora.
                    </p>
                </div>
                <router-link
                    :to="{ name: 'products' }"
                    class="hidden sm:inline-flex items-center gap-1 text-xs font-bold text-brand-650 hover:text-brand-700"
                >
                    <span>Ver catálogo completo</span>
                    <SvgIcon name="chevron-right" size="0.85rem" />
                </router-link>
            </div>

            <!-- Filter bar (sticky + autocomplete, solo afecta a esta sección) -->
            <div class="sticky top-16 z-40 card p-4 mb-6 space-y-3 backdrop-blur-md bg-white/95 border border-slate-100 shadow-lg">
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Autocomplete search -->
                    <ProductAutocomplete
                        placeholder="Buscar por nombre, marca o SKU..."
                        :max-results="8"
                    />

                    <!-- Categoría -->
                    <div class="relative">
                        <select
                            :value="store.filterCategory ?? ''"
                            @change="store.setCategory(Number(($event.target as HTMLSelectElement).value) || null)"
                            class="input cursor-pointer pr-8 bg-no-repeat appearance-none min-w-[180px]"
                            style="background-image: url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22 fill=%22none%22%3E%3Cpath d=%22M7 9l3 3 3-3%22 stroke=%22%236b7280%22 stroke-width=%221.5%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22/%3E%3C/svg%3E'); background-position: right 0.75rem center; background-size: 1.25rem;"
                        >
                            <option value="">Todas las categorías</option>
                            <option
                                v-for="cat in store.categories"
                                :key="cat.id"
                                :value="cat.id"
                            >
                                {{ cat.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Marca -->
                    <div class="relative" v-if="moreBrands.length > 0">
                        <select
                            :value="store.filterBrand ?? ''"
                            @change="store.setBrand(($event.target as HTMLSelectElement).value || null)"
                            class="input cursor-pointer pr-8 bg-no-repeat appearance-none min-w-[160px]"
                            style="background-image: url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22 fill=%22none%22%3E%3Cpath d=%22M7 9l3 3 3-3%22 stroke=%22%236b7280%22 stroke-width=%221.5%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22/%3E%3C/svg%3E'); background-position: right 0.75rem center; background-size: 1.25rem;"
                        >
                            <option value="">Todas las marcas</option>
                            <option
                                v-for="brand in moreBrands"
                                :key="brand"
                                :value="brand"
                            >
                                {{ brand }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Active filters -->
                <div
                    v-if="hasActiveFilters"
                    class="flex flex-wrap items-center gap-2 pt-3 border-t border-slate-100/80"
                >
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Filtros:</span>
                    <span
                        v-if="store.filterCategory"
                        class="chip chip-brand"
                    >
                        {{ store.categories.find((c) => c.id === store.filterCategory)?.name }}
                    </span>
                    <span
                        v-if="store.filterBrand"
                        class="chip chip-brand"
                    >
                        {{ store.filterBrand }}
                    </span>
                    <span
                        v-if="store.searchQuery.trim()"
                        class="chip chip-brand"
                    >
                        "{{ store.searchQuery }}"
                    </span>
                    <button
                        @click="clearMoreFilters"
                        class="ml-auto text-xs text-slate-400 hover:text-rose-600 font-bold transition-colors cursor-pointer"
                    >
                        Limpiar filtros
                    </button>
                </div>

                <!-- Filter result counter -->
                <div v-if="hasActiveFilters" class="text-xs text-slate-400">
                    <template v-if="filteredMatchCount > 0">
                        <strong class="text-slate-600">{{ filteredMatchCount }}</strong>
                        {{ filteredMatchCount === 1 ? 'producto coincide' : 'productos coinciden' }}
                        con tu filtro (de {{ store.totalProducts }} totales).
                        <span v-if="store.hasMore" class="block mt-1 italic">
                            Tip: cargá más productos para ver más resultados.
                        </span>
                    </template>
                    <template v-else>
                        <span class="text-rose-600">Ningún producto coincide con tu filtro.</span>
                    </template>
                </div>
            </div>

            <!-- Grid -->
            <div
                v-if="filteredMoreProducts.length > 0"
                class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4"
            >
                <ProductCard
                    v-for="(product, i) in filteredMoreProducts"
                    :key="product.id"
                    :product="product"
                    :style="{ animationDelay: `${Math.min(i * 30, 300)}ms` }"
                />
            </div>

            <!-- Empty state when filters return 0 -->
            <EmptyState
                v-else-if="hasActiveFilters"
                icon="search"
                title="Sin resultados en esta sección"
                :description="`Ningún producto cargado coincide con tu filtro. Probá limpiar los filtros o cargá más productos desde el catálogo completo.`"
            >
                <template #actions>
                    <button @click="clearMoreFilters" class="btn btn-primary">
                        Limpiar filtros
                    </button>
                    <router-link :to="{ name: 'products' }" class="btn btn-secondary">
                        Ir al catálogo completo
                    </router-link>
                </template>
            </EmptyState>

            <!-- Load more button (reutiliza la misma paginación del store) -->
            <div
                v-if="!hasActiveFilters"
                class="flex flex-col items-center gap-2 pt-8"
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
                    v-else-if="store.totalProducts > 0 && moreProducts.length > 0"
                    class="flex items-center gap-2 text-xs text-slate-400 font-medium"
                >
                    <SvgIcon name="check" size="0.85rem" class="text-emerald-500" />
                    <span>Mostrando todos los {{ store.totalProducts }} productos del catálogo</span>
                </div>

                <div v-if="store.hasMore" class="text-xs text-slate-400">
                    Mostrando {{ store.products.length }} de {{ store.totalProducts }} productos
                </div>
            </div>
        </section>
    </div>
</template>