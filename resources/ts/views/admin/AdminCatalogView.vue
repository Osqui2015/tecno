<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import axios from '@/bootstrap';
import { useAdminStore } from '@/stores/admin';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import SvgIcon from '@/components/SvgIcon.vue';

interface Category {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
}

interface OriginSetting {
    origin: string;
    label: string;
    enabled: boolean;
    filter_by_category: boolean;
    category_ids: number[];
    sort_order: number;
    product_count: number;
}

interface OriginStats {
    origin: string;
    label: string;
    total: number;
    available: number;
    visible: number;
    hidden: number;
}

const admin = useAdminStore();

const settings = ref<OriginSetting[]>([]);
const categories = ref<Category[]>([]);
const stats = ref<OriginStats[]>([]);
const loading = ref(false);
const saving = ref(false);
const categorySearch = ref<Record<string, string>>({});

/** Búsqueda de categorías por proveedor (acceso seguro al ref). */
function searchFor(origin: string): string {
    return categorySearch.value[origin] ?? '';
}

function setSearchFor(origin: string, value: string) {
    categorySearch.value[origin] = value;
}

/** Formulario editable (copia local de settings). */
const form = reactive<Record<string, {
    enabled: boolean;
    filter_by_category: boolean;
    category_ids: number[];
    sort_order: number;
}>>({});

const categorySelectorOpen = reactive<Record<string, boolean>>({});

/**
 * Activa/desactiva la visibilidad de un proveedor.
 *
 * Al ocultar un proveedor, sus productos salen del catálogo público pero
 * NO se borran: siguen guardados y vuelven a aparecer si lo reactivás.
 */
function toggleOrigin(origin: string) {
    const entry = form[origin];
    if (!entry) return;
    entry.enabled = !entry.enabled;
}

async function load() {
    loading.value = true;
    try {
        const { data } = await axios.get('/admin/catalog-settings');
        settings.value = data.settings ?? [];
        categories.value = data.categories ?? [];

        // Reinicializa el formulario editable.
        for (const key of Object.keys(form)) delete form[key];
        for (const s of settings.value) {
            form[s.origin] = {
                enabled: s.enabled,
                filter_by_category: s.filter_by_category,
                category_ids: [...(s.category_ids ?? [])],
                sort_order: s.sort_order,
            };
        }

        // Estadísticas de visibilidad (cuántos productos quedan visibles).
        try {
            const { data: statsData } = await axios.get('/admin/catalog-settings/stats');
            stats.value = statsData.stats ?? [];
        } catch {
            stats.value = [];
        }
    } catch (err: any) {
        admin.flashError('No se pudo cargar la configuración del catálogo');
    } finally {
        loading.value = false;
    }
}

/** Categorías filtradas por el texto de búsqueda de cada proveedor. */
function filteredCategories(origin: string): Category[] {
    const term = searchFor(origin).trim().toLowerCase();
    if (!term) return categories.value;
    return categories.value.filter(c => c.name.toLowerCase().includes(term));
}

/** Total de categorías seleccionadas para un proveedor. */
function selectedCount(origin: string): number {
    return form[origin]?.category_ids.length ?? 0;
}

/** ¿Está la categoría seleccionada en este proveedor? */
function isSelected(origin: string, categoryId: number): boolean {
    return form[origin]?.category_ids.includes(categoryId) ?? false;
}

function toggleCategory(origin: string, categoryId: number) {
    const entry = form[origin];
    if (!entry) return;

    const idx = entry.category_ids.indexOf(categoryId);
    if (idx >= 0) {
        entry.category_ids.splice(idx, 1);
    } else {
        entry.category_ids.push(categoryId);
    }
}

/** Selecciona todas las categorías visibles del listado. */
function selectAll(origin: string) {
    const entry = form[origin];
    if (!entry) return;
    entry.category_ids = filteredCategories(origin).map(c => c.id);
}

/** Deselecciona todas las categorías. */
function clearAll(origin: string) {
    const entry = form[origin];
    if (!entry) return;
    entry.category_ids = [];
}

/**
 * Si se activa el filtro por categoría sin ninguna seleccionada, se
 * desactiva el filtro para no dejar al proveedor sin productos visibles.
 */
function onToggleFilter(origin: string, value: boolean) {
    const entry = form[origin];
    if (!entry) return;

    entry.filter_by_category = value;
    if (value && entry.category_ids.length === 0) {
        // Sin categorías elegidas el filtro no filtraría nada: se avisa
        // pero se mantiene el estado para que el usuario elija.
        return;
    }
}

const hasChanges = computed(() => {
    for (const s of settings.value) {
        const f = form[s.origin];
        if (!f) continue;
        if (f.enabled !== s.enabled) return true;
        if (f.filter_by_category !== s.filter_by_category) return true;
        if (f.sort_order !== s.sort_order) return true;
        if ([...f.category_ids].sort().join(',') !== [...(s.category_ids ?? [])].sort().join(',')) return true;
    }
    return false;
});

/**
 * Cuántos productos de un proveedor quedarían visibles con la configuración
 * actual (incluye cambios sin guardar).
 *
 * Si el proveedor está oculto → 0.
 * Si está en modo "todas las categorías" → todos los disponibles.
 * Si filtra por categoría → los que están en las categorías elegidas
 * (calculado por el servidor con la configuración guardada; al cambiar
 * las categorías sin guardar se muestra el dato guardado).
 */
function visibleCount(origin: string): number {
    const entry = form[origin];
    if (!entry) return 0;
    if (!entry.enabled) return 0;

    const stat = stats.value.find(s => s.origin === origin);
    if (!stat) return 0;

    // Sin filtro por categoría: se ve todo lo activo del proveedor.
    if (!entry.filter_by_category) return stat.available;

    // Con filtro por categoría: el server ya calculó el exacto.
    return stat.visible;
}

/** Total de productos visibles en todo el catálogo con la config actual. */
const totalVisible = computed(() => {
    return settings.value.reduce((acc, s) => acc + visibleCount(s.origin), 0);
});

async function save() {
    saving.value = true;
    try {
        const payload = settings.value.map(s => ({
            origin: s.origin,
            enabled: form[s.origin].enabled,
            filter_by_category: form[s.origin].filter_by_category,
            category_ids: form[s.origin].category_ids,
            sort_order: form[s.origin].sort_order,
        }));

        await axios.patch('/admin/catalog-settings', { settings: payload });
        admin.flashSuccess('Configuración del catálogo guardada');
        await load();
    } catch (err: any) {
        if (err.response?.status === 422) {
            admin.flashError('Revisá los datos: ' + Object.values(err.response.data.errors ?? {}).flat().join(' '));
        } else {
            admin.flashError('No se pudo guardar la configuración');
        }
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    load();
});
</script>

<template>
    <div class="space-y-5">
        <!-- Encabezado -->
        <div class="card p-5">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-2xl bg-brand-100 text-brand-700 flex items-center justify-center flex-shrink-0">
                    <SvgIcon name="box" size="1.4rem" />
                </div>
                <div class="flex-1">
                    <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">Catálogo y proveedores</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Elegí qué proveedores (y qué categorías de cada uno) se muestran en el sitio público.
                        Lo que no selecciones queda oculto del catálogo, pero sigue guardado en la base de datos.
                    </p>
                </div>
            </div>
        </div>

        <div v-if="loading" class="card p-12 flex justify-center">
            <LoadingSpinner />
        </div>

        <template v-else>
            <!-- Resumen -->
            <div class="card p-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-xl bg-brand-100 text-brand-700 flex items-center justify-center">
                            <SvgIcon name="eye" size="1rem" />
                        </span>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                Productos en el catálogo público
                            </p>
                            <p class="text-lg font-extrabold text-slate-800 dark:text-slate-100">
                                {{ totalVisible }}
                                <span class="text-xs font-semibold text-slate-400">productos visibles</span>
                            </p>
                        </div>
                    </div>

                    <div v-if="hasChanges" class="flex items-center gap-2">
                        <span class="chip chip-warning text-[10px]">Cambios sin guardar</span>
                    </div>
                </div>
            </div>

            <!-- Tarjeta por proveedor -->
            <div
                v-for="s in settings"
                :key="s.origin"
                class="card overflow-hidden"
                :class="form[s.origin]?.enabled ? '' : 'opacity-70'"
            >
                <!-- Header del proveedor -->
                <div class="px-5 py-4 flex flex-wrap items-center gap-4 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-3 flex-1 min-w-[220px]">
                        <span
                            class="w-10 h-10 rounded-xl flex items-center justify-center font-extrabold text-sm uppercase"
                            :class="form[s.origin]?.enabled
                                ? 'bg-brand-100 text-brand-700'
                                : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500'"
                        >
                            {{ s.label.slice(0, 2) }}
                        </span>
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100">{{ s.label }}</h3>
                            <p class="text-[11px] text-slate-500">
                                <template v-if="form[s.origin]?.enabled">
                                    <span class="font-bold text-emerald-600 dark:text-emerald-400">
                                        {{ visibleCount(s.origin) }} visibles
                                    </span>
                                    <span> de {{ s.product_count }} productos</span>
                                </template>
                                <template v-else>
                                    <span class="font-bold text-slate-400">{{ s.product_count }} productos</span>
                                    <span> · {{ s.origin }}</span>
                                </template>
                            </p>
                        </div>
                    </div>

                    <!-- Switch mostrar/ocultar -->
                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <p
                                class="text-xs font-extrabold"
                                :class="form[s.origin]?.enabled ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400'"
                            >
                                {{ form[s.origin]?.enabled ? 'VISIBLE' : 'OCULTO' }}
                            </p>
                            <p class="text-[10px] text-slate-400">
                                {{ form[s.origin]?.enabled ? 'Se muestra en el sitio' : 'No aparece en el sitio' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form[s.origin]?.enabled ? 'true' : 'false'"
                            :aria-label="`Mostrar productos de ${s.label}`"
                            :disabled="saving"
                            @click="toggleOrigin(s.origin)"
                            class="switch"
                        >
                            <span class="switch-knob"></span>
                        </button>
                    </div>
                </div>

                <!-- Cuerpo -->
                <div v-if="form[s.origin]?.enabled" class="p-5 space-y-5">
                    <!-- Filtro por categoría -->
                    <div class="flex flex-wrap items-center gap-4">
                        <label class="flex items-center gap-2.5 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                class="rounded text-primary-600"
                                :checked="form[s.origin].filter_by_category"
                                @change="onToggleFilter(s.origin, ($event.target as HTMLInputElement).checked)"
                            />
                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                Mostrar solo las categorías seleccionadas
                            </span>
                        </label>

                        <div v-if="form[s.origin].filter_by_category" class="flex items-center gap-2 text-[11px]">
                            <span class="px-2 py-1 rounded-lg bg-brand-50 text-brand-700 font-bold">
                                {{ selectedCount(s.origin) }} / {{ categories.length }} categorías
                            </span>
                        </div>
                    </div>

                    <p
                        v-if="form[s.origin].filter_by_category && selectedCount(s.origin) === 0"
                        class="text-[11px] text-amber-600 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2"
                    >
                        ⚠ No hay categorías seleccionadas: se mostrará el catálogo completo de {{ s.label }}.
                    </p>

                    <!-- Selector de categorías -->
                    <div v-if="form[s.origin].filter_by_category" class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <!-- Toolbar -->
                        <div class="px-4 py-3 flex flex-wrap items-center gap-3 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
                            <div class="flex-1 min-w-[180px] relative">
                                <SvgIcon name="search" size="1rem" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                                <input
                                    :value="searchFor(s.origin)"
                                    type="text"
                                    placeholder="Buscar categoría..."
                                    class="input pl-10 py-2 text-xs"
                                    @input="setSearchFor(s.origin, ($event.target as HTMLInputElement).value)"
                                />
                            </div>
                            <button type="button" class="btn btn-secondary text-xs px-2.5 py-1" @click="selectAll(s.origin)">
                                Seleccionar todas
                            </button>
                            <button type="button" class="btn btn-secondary text-xs px-2.5 py-1" @click="clearAll(s.origin)">
                                Quitar todas
                            </button>
                        </div>

                        <!-- Lista -->
                        <div class="max-h-[320px] overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800">
                            <label
                                v-for="c in filteredCategories(s.origin)"
                                :key="c.id"
                                class="flex items-center gap-3 px-4 py-2.5 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors"
                            >
                                <input
                                    type="checkbox"
                                    class="rounded text-primary-600"
                                    :checked="isSelected(s.origin, c.id)"
                                    @change="toggleCategory(s.origin, c.id)"
                                />
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-200 truncate">{{ c.name }}</p>
                                    <p v-if="c.description" class="text-[10px] text-slate-400 truncate">{{ c.description }}</p>
                                </div>
                            </label>

                            <p v-if="filteredCategories(s.origin).length === 0" class="px-4 py-6 text-center text-xs text-slate-400">
                                No hay categorías que coincidan con la búsqueda.
                            </p>
                        </div>
                    </div>

                    <!-- Orden -->
                    <div class="max-w-xs">
                        <label class="label">Orden de presentación</label>
                        <input
                            v-model.number="form[s.origin].sort_order"
                            type="number"
                            min="0"
                            max="999"
                            class="input"
                        />
                        <p class="text-[11px] text-slate-500 mt-1">
                            Número menor = aparece antes en el catálogo.
                        </p>
                    </div>
                </div>

                <!-- Estado oculto -->
                <div v-else class="px-5 py-4">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Los {{ s.product_count }} productos de <strong>{{ s.label }}</strong> están ocultos del
                        catálogo público. Siguen guardados en la base de datos: no se borran ni se desactiva
                        el stock. Volvé a activar el switch para mostrarlos de nuevo.
                    </p>
                </div>
            </div>

            <!-- Guardar -->
            <div class="flex flex-wrap justify-end gap-3">
                <span v-if="hasChanges" class="self-center text-[11px] text-amber-600 dark:text-amber-400 font-semibold">
                    Tenés cambios sin guardar
                </span>
                <button
                    type="button"
                    :disabled="saving || !hasChanges"
                    class="btn btn-primary flex items-center gap-2"
                    @click="save"
                >
                    <LoadingSpinner v-if="saving" size="sm" />
                    <SvgIcon v-else name="check" size="0.95rem" />
                    <span>{{ saving ? 'Guardando...' : 'Guardar configuración' }}</span>
                </button>
            </div>
        </template>
    </div>
</template>
