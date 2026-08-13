<script setup lang="ts">
import { onMounted, ref, reactive, computed, watch } from 'vue';
import { useAdminStore } from '@/stores/admin';
import axios from '@/bootstrap';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import SvgIcon from '@/components/SvgIcon.vue';

const admin = useAdminStore();
const totalPages = computed(() => admin.productMeta?.last_page ?? 1);

// Umbral: productos con menos de este stock se ocultan por defecto.
const LOW_STOCK_THRESHOLD = 5;

// Si true, oculta los productos con stock < LOW_STOCK_THRESHOLD.
const hideLowStock = ref(true);

const filters = reactive({
    search: '',
    source: 'daz' as '' | 'daz' | 'tuc' | 'manual',
    stock_status: '' as '' | 'in_stock' | 'out_of_stock',
    active: '' as '' | 'true' | 'false',
    page: 1,
    per_page: 25,
});

const showBulkModal = ref(false);
const bulkForm = reactive({
    percent: 25,
    scope: 'all' as 'all' | 'daz' | 'tuc' | 'manual' | 'category',
    category_id: null as number | null,
});

function load() {
    const params: any = { page: filters.page, per_page: filters.per_page };
    if (filters.search.trim()) params.search = filters.search.trim();
    if (filters.source) params.source = filters.source;
    if (filters.stock_status) params.stock_status = filters.stock_status;
    if (filters.active) params.active = filters.active === 'true';
    if (hideLowStock.value) params.min_stock = LOW_STOCK_THRESHOLD;
    admin.fetchProducts(params);
}

watch(filters, load, { deep: true });
watch(hideLowStock, () => {
    filters.page = 1;
    load();
});

onMounted(() => {
    load();
    admin.fetchCategories();
});

async function applyBulkMarkup() {
    const payload: any = { percent: bulkForm.percent };
    if (bulkForm.scope === 'daz' || bulkForm.scope === 'tuc' || bulkForm.scope === 'manual') {
        payload.source = bulkForm.scope;
    } else if (bulkForm.scope === 'category' && bulkForm.category_id) {
        payload.category_id = bulkForm.category_id;
    }
    const ok = await admin.bulkMarkup(payload);
    if (ok) {
        showBulkModal.value = false;
        load();
    }
}

function clearFilters() {
    filters.search = '';
    filters.source = '';
    filters.stock_status = '';
    filters.active = '';
    filters.page = 1;
}

function nextPage() { filters.page++; }
function prevPage() { if (filters.page > 1) filters.page--; }

const formatPrice = (n: number | string) =>
    '$' + Math.round(Number(n)).toLocaleString('es-AR');

const showImportModal = ref(false);
const importFile = ref<File | null>(null);
const importing = ref(false);
const importMessage = ref('');

function exportCsv() {
    window.location.href = '/api/admin/products/export/csv';
}

function handleFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        importFile.value = target.files[0];
    }
}

async function uploadCsv() {
    if (!importFile.value) return;
    importing.value = true;
    importMessage.value = '';
    try {
        const formData = new FormData();
        formData.append('file', importFile.value);
        const { data } = await axios.post('/api/admin/products/import/csv', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        importMessage.value = data.message;
        load();
        setTimeout(() => {
            showImportModal.value = false;
            importFile.value = null;
            importMessage.value = '';
        }, 2000);
    } catch (err: any) {
        importMessage.value = err.response?.data?.message || 'Error al importar CSV';
    } finally {
        importing.value = false;
    }
}
</script>

<template>
    <div class="space-y-5">
        <!-- Filtros -->
        <div class="card p-5 space-y-4">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex-1 min-w-[200px] relative">
                    <SvgIcon name="search" size="1rem" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="filters.search"
                        type="text"
                        placeholder="Buscar por nombre o SKU..."
                        class="input pl-10"
                    />
                </div>
                <select v-model="filters.source" class="input max-w-[160px]">
                    <option value="daz">Catálogo Daz</option>
                    <option value="tuc">Catálogo TusTecnología</option>
                    <option value="manual">Productos manuales</option>
                </select>
                <select v-model="filters.stock_status" class="input max-w-[160px]">
                    <option value="">Cualquier stock</option>
                    <option value="in_stock">Con stock</option>
                    <option value="out_of_stock">Sin stock</option>
                </select>
                <button @click="clearFilters" class="btn btn-ghost btn-sm">
                    Limpiar
                </button>
                <button @click="exportCsv" class="btn btn-secondary btn-sm flex items-center gap-1">
                    <span>Exportar CSV</span>
                </button>
                <button @click="showImportModal = true" class="btn btn-secondary btn-sm flex items-center gap-1">
                    <span>Importar CSV</span>
                </button>
                <button @click="showBulkModal = true" class="btn btn-primary btn-sm">
                    <SvgIcon name="cog" size="0.95rem" />
                    Aumento global
                </button>
            </div>

            <!-- Toggle: ocultar productos con poco stock -->
            <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input
                        v-model="hideLowStock"
                        type="checkbox"
                        class="w-4 h-4 rounded text-brand-600 focus:ring-2 focus:ring-brand-200 cursor-pointer"
                    />
                    <span class="text-xs font-semibold text-slate-700">
                        Ocultar productos con stock menor a {{ LOW_STOCK_THRESHOLD }}
                    </span>
                </label>
                <span
                    v-if="hideLowStock"
                    class="chip chip-warning text-[10px]"
                >
                    <SvgIcon name="info" size="0.7rem" />
                    Filtro activo
                </span>
                <span
                    v-else
                    class="chip chip-muted text-[10px]"
                >
                    Mostrando todos los productos (incluido bajo stock)
                </span>
            </div>
        </div>

        <LoadingSpinner v-if="admin.loading" text="Cargando productos..." />

        <div v-else-if="admin.products.length === 0" class="card p-12 text-center">
            <p class="text-slate-500 font-semibold">Sin resultados con esos filtros.</p>
        </div>

        <div v-else class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr class="text-left text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">Producto</th>
                            <th class="px-4 py-3">Origen</th>
                            <th class="px-4 py-3 text-right">Precio base</th>
                            <th class="px-4 py-3 text-right">Markup</th>
                            <th class="px-4 py-3 text-right">Final</th>
                            <th class="px-4 py-3 text-right">Stock</th>
                            <th class="px-4 py-3 text-right">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in admin.products" :key="p.id" class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-slate-100 overflow-hidden flex-shrink-0">
                                        <img
                                            v-if="p.image"
                                            :src="p.image"
                                            class="w-full h-full object-cover"
                                            referrerpolicy="no-referrer"
                                        />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-800 line-clamp-1 text-xs">{{ p.name }}</p>
                                        <p class="text-[10px] text-slate-400 font-semibold">#{{ p.id }} · {{ p.sku || '—' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="p.origin === 'daz'" class="chip chip-info text-[10px]">Daz</span>
                                <span v-else-if="p.origin === 'tuc'" class="chip chip-brand text-[10px]">TusTec-Tuc</span>
                                <span v-else class="chip chip-muted text-[10px]">Manual</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-xs">{{ formatPrice(p.price) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-xs">{{ Number(p.markup_percent ?? 0).toFixed(0) }}%</td>
                            <td class="px-4 py-3 text-right font-mono text-xs font-bold text-slate-800">
                                {{ formatPrice((p as any).final_price ?? p.price) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span :class="[
                                    'font-bold text-xs',
                                    p.stock === 0 ? 'text-rose-600' :
                                    p.stock < LOW_STOCK_THRESHOLD ? 'text-amber-600' :
                                    'text-emerald-600'
                                ]">
                                    {{ p.stock }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <span v-if="p.active" class="chip chip-success text-[10px]">Activo</span>
                                <span v-else class="chip chip-muted text-[10px]">Inactivo</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <router-link :to="{ name: 'admin-product-edit', params: { id: p.id } }" class="btn btn-ghost btn-xs">
                                    Editar
                                </router-link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 bg-slate-50/50">
                <span class="text-xs text-slate-500">
                    Página {{ filters.page }} de {{ totalPages }}
                </span>
                <div class="flex gap-2">
                    <button @click="prevPage" :disabled="filters.page === 1" class="btn btn-ghost btn-xs">
                        <SvgIcon name="chevron-left" size="0.85rem" />
                        Anterior
                    </button>
                    <button @click="nextPage" :disabled="filters.page === totalPages" class="btn btn-ghost btn-xs">
                        Siguiente
                        <SvgIcon name="chevron-right" size="0.85rem" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal: Aumento global -->
        <transition name="modal">
            <div v-if="showBulkModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" @click.self="showBulkModal = false">
                <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden">
                    <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="font-extrabold text-base">Aumento global de precio</h3>
                        <button @click="showBulkModal = false" class="w-8 h-8 rounded-full hover:bg-slate-100 flex items-center justify-center">
                            <SvgIcon name="close" size="1rem" />
                        </button>
                    </div>
                    <div class="p-5 space-y-4">
                        <div>
                            <label class="label">Markup % (nuevo)</label>
                            <input v-model.number="bulkForm.percent" type="number" min="0" max="999" class="input" />
                        </div>
                        <div>
                            <label class="label">Aplicar a</label>
                            <select v-model="bulkForm.scope" class="input">
                                <option value="all">Todos los productos</option>
                                <option value="daz">Solo Daz</option>
                                <option value="tuc">Solo TusTecnología</option>
                                <option value="manual">Solo manuales</option>
                                <option value="category">Una categoría</option>
                            </select>
                        </div>
                        <div v-if="bulkForm.scope === 'category'">
                            <label class="label">Categoría</label>
                            <select v-model.number="bulkForm.category_id" class="input">
                                <option :value="null">Seleccionar...</option>
                                <option v-for="c in admin.productCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="p-5 border-t border-slate-100 flex gap-2">
                        <button @click="showBulkModal = false" class="btn btn-ghost flex-1">Cancelar</button>
                        <button @click="applyBulkMarkup" class="btn btn-primary flex-1">Aplicar</button>
                    </div>
                </div>
            </div>
        </transition>
        <!-- Modal: Importar CSV -->
        <transition name="modal">
            <div v-if="showImportModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" @click.self="showImportModal = false">
                <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-100 dark:border-slate-800">
                    <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <h3 class="font-extrabold text-base text-slate-800 dark:text-slate-100">Importar Productos desde CSV</h3>
                        <button @click="showImportModal = false" class="w-8 h-8 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center">
                            <SvgIcon name="close" size="1rem" />
                        </button>
                    </div>
                    <div class="p-5 space-y-4">
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Selecciona un archivo CSV con las columnas: <span class="font-mono bg-slate-100 dark:bg-slate-800 px-1 py-0.5 rounded">SKU, Nombre, Precio Base, Stock, Marca, ID Categoria</span>.
                        </p>

                        <div class="border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-2xl p-6 text-center hover:border-primary-500 transition-colors">
                            <input type="file" accept=".csv, .txt" @change="handleFileChange" class="hidden" id="csv_input" />
                            <label for="csv_input" class="cursor-pointer block space-y-2">
                                <SvgIcon name="box" size="2rem" class="mx-auto text-slate-400" />
                                <span class="block text-xs font-bold text-primary-600 dark:text-primary-400">
                                    {{ importFile ? importFile.name : 'Haz clic para seleccionar un archivo CSV' }}
                                </span>
                            </label>
                        </div>

                        <div v-if="importMessage" :class="importMessage.includes('completada') ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/50' : 'text-rose-600 bg-rose-50 dark:bg-rose-950/50'" class="p-3 rounded-xl text-xs font-semibold">
                            {{ importMessage }}
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button @click="showImportModal = false" class="btn btn-secondary text-xs">
                                Cancelar
                            </button>
                            <button @click="uploadCsv" :disabled="!importFile || importing" class="btn btn-primary text-xs flex items-center gap-2">
                                <LoadingSpinner v-if="importing" size="sm" />
                                <span>{{ importing ? 'Procesando...' : 'Iniciar Importación' }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>
