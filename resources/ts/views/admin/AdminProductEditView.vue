<script setup lang="ts">
import { onMounted, ref, reactive, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAdminStore } from '@/stores/admin';
import axios from '@/bootstrap';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import SvgIcon from '@/components/SvgIcon.vue';

const admin = useAdminStore();
const route = useRoute();
const router = useRouter();

const id = Number(route.params.id);
const loading = ref(true);
const saving = ref(false);

const form = reactive({
    name: '',
    description: '',
    price: 0,
    markup_percent: 0,
    stock: 0,
    category_id: null as number | null,
    sku: '',
    active: true,
});

// ─── Historial de actualizaciones ───
interface HistoryItem {
    id: number;
    source: string;
    source_label: string;
    event: string;
    summary: string;
    changed_fields: string[];
    changes: Record<string, { before: any; after: any }>;
    actor: { id: number; name: string; email: string } | null;
    reference: string | null;
    created_at: string;
    created_at_human: string;
}
interface HistoryResponse {
    product: {
        id: number;
        name: string;
        last_updated_at: string | null;
        last_updated_human: string | null;
    };
    data: HistoryItem[];
    meta: { current_page: number; last_page: number; total: number; per_page: number };
}
const historyLoading = ref(false);
const history = ref<HistoryItem[]>([]);
const historyMeta = ref<HistoryResponse['meta'] | null>(null);

async function fetchHistory() {
    historyLoading.value = true;
    try {
        const { data } = await axios.get<HistoryResponse>(`/admin/products/${id}/history`, {
            params: { per_page: 30 },
        });
        history.value = data.data;
        historyMeta.value = data.meta;
    } catch (e) {
        console.warn('No se pudo obtener el historial', e);
    } finally {
        historyLoading.value = false;
    }
}

onMounted(async () => {
    loading.value = true;
    await admin.fetchCategories();
    await admin.fetchProduct(id);
    const p = admin.currentProduct;
    if (p) {
        form.name = p.name;
        form.description = p.description ?? '';
        form.price = Number(p.price);
        form.markup_percent = Number(p.markup_percent ?? 0);
        form.stock = p.stock;
        form.category_id = p.category_id;
        form.sku = p.sku ?? '';
        form.active = p.active;
    }
    loading.value = false;
    fetchHistory();
});

const finalPrice = computed(() =>
    (form.price * (1 + form.markup_percent / 100)).toFixed(2)
);

async function save() {
    saving.value = true;
    const payload = { ...form };
    if (payload.category_id == null) delete (payload as any).category_id;
    const ok = await admin.updateProduct(id, payload);
    saving.value = false;
    if (ok) {
        // Refrescar historial después de guardar (puede haber cambios nuevos)
        await fetchHistory();
        router.push({ name: 'admin-products' });
    }
}

const formatPrice = (n: number) =>
    '$' + Math.round(n).toLocaleString('es-AR');

// Helpers de UI
function formatFieldValue(field: string, val: any): string {
    if (val === null || val === undefined || val === '') return '—';
    if (['price', 'list_price', 'cash_price', 'markup_percent'].includes(field)) {
        return '$' + Number(val).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    if (field === 'active') return val ? 'Activo' : 'Inactivo';
    if (field === 'description') {
        const s = String(val);
        return s.length > 60 ? s.slice(0, 60) + '…' : s;
    }
    return String(val);
}

function diffArrow(before: any, after: any): string {
    if (before === null || before === undefined) return '➕';
    if (after === null || after === undefined) return '➖';
    const nb = Number(before);
    const na = Number(after);
    if (!isNaN(nb) && !isNaN(na)) {
        if (na > nb) return '⬆️';
        if (na < nb) return '⬇️';
    }
    return '✏️';
}

const sourceBadgeClass: Record<string, string> = {
    'admin': 'bg-purple-100 text-purple-700',
    'scraper:daz': 'bg-blue-100 text-blue-700',
    'scraper:tuc': 'bg-amber-100 text-amber-700',
    'order': 'bg-emerald-100 text-emerald-700',
    'system': 'bg-slate-100 text-slate-700',
};

const eventLabel: Record<string, string> = {
    'created': 'Creado',
    'updated': 'Actualizado',
    'activated': 'Activado',
    'deactivated': 'Desactivado',
};
</script>

<template>
    <div>
        <div class="flex items-center gap-3 mb-5">
            <router-link :to="{ name: 'admin-products' }" class="btn btn-ghost btn-sm">
                <SvgIcon name="chevron-left" size="0.85rem" />
                Productos
            </router-link>
            <h2 class="text-lg font-extrabold text-slate-800">Editar producto</h2>
        </div>

        <LoadingSpinner v-if="loading" text="Cargando producto..." />

        <div v-else class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 card p-6 space-y-5">
                <div>
                    <label class="label">Nombre</label>
                    <input v-model="form.name" class="input" />
                </div>
                <div>
                    <label class="label">Descripción</label>
                    <textarea v-model="form.description" class="input min-h-[100px]"></textarea>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">SKU</label>
                        <input v-model="form.sku" class="input" />
                    </div>
                    <div>
                        <label class="label">Categoría</label>
                        <select v-model.number="form.category_id" class="input">
                            <option v-for="c in admin.productCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                </div>

                <hr class="border-slate-100" />

                <div>
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-500 mb-3">Precio y stock</h3>
                </div>

                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <label class="label">
                            Precio base
                            <span v-if="admin.currentProduct?.origin === 'daz'"> (Daz)</span>
                            <span v-else-if="admin.currentProduct?.origin === 'tuc'"> (TusTecnología)</span>
                            <span v-else> (Manual)</span>
                        </label>
                        <input v-model.number="form.price" type="number" step="0.01" min="0" class="input" />
                        <p class="text-[10px] text-slate-400 mt-1">
                            Lo que cuesta en
                            <span v-if="admin.currentProduct?.origin === 'daz'"> Daz.</span>
                            <span v-else-if="admin.currentProduct?.origin === 'tuc'"> TusTecnología.</span>
                            <span v-else> origen.</span>
                        </p>
                    </div>
                    <div>
                        <label class="label">Markup %</label>
                        <input v-model.number="form.markup_percent" type="number" step="0.01" min="0" max="999.99" class="input" />
                        <p class="text-[10px] text-slate-400 mt-1">% que se suma al base.</p>
                    </div>
                    <div>
                        <label class="label">Precio final</label>
                        <div class="input bg-emerald-50 border-emerald-200 font-black text-emerald-700 flex items-center">
                            {{ formatPrice(Number(finalPrice)) }}
                        </div>
                        <p class="text-[10px] text-emerald-600 mt-1">Lo que paga el cliente.</p>
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Stock</label>
                        <input v-model.number="form.stock" type="number" min="0" class="input" />
                    </div>
                    <div>
                        <label class="label">Estado</label>
                        <label class="flex items-center gap-3 mt-2 cursor-pointer">
                            <input type="checkbox" v-model="form.active" class="w-4 h-4 text-brand-600 rounded" />
                            <span class="text-sm font-semibold text-slate-700">{{ form.active ? 'Activo (visible)' : 'Inactivo (oculto)' }}</span>
                        </label>
                    </div>
                </div>

                <hr class="border-slate-100" />

                <div class="flex gap-3 pt-2">
                    <button @click="save" class="btn btn-primary" :disabled="saving">
                        {{ saving ? 'Guardando...' : 'Guardar cambios' }}
                    </button>
                    <router-link :to="{ name: 'admin-products' }" class="btn btn-ghost">
                        Cancelar
                    </router-link>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="card p-5">
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-2">Imagen actual</p>
                    <div class="aspect-square bg-slate-50 rounded-xl overflow-hidden">
                        <img
                            v-if="admin.currentProduct?.image"
                            :src="admin.currentProduct.image"
                            class="w-full h-full object-cover"
                            referrerpolicy="no-referrer"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center text-slate-400">
                            <SvgIcon name="box" size="2rem" />
                        </div>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-2 text-center">La imagen viene del scraper.</p>
                </div>

                <div v-if="admin.currentProduct?.origin === 'daz'" class="card p-5 bg-blue-50/40 border-blue-100">
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-blue-500 mb-1">Origen</p>
                    <p class="text-sm font-bold text-slate-800">Producto Daz</p>
                    <p class="text-[11px] text-slate-500 mt-1">ID Daz: <code>{{ admin.currentProduct.external_id }}</code></p>
                </div>
                <div v-else-if="admin.currentProduct?.origin === 'tuc'" class="card p-5 bg-amber-50/40 border-amber-100">
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-amber-500 mb-1">Origen</p>
                    <p class="text-sm font-bold text-slate-800">Producto TusTecnología</p>
                    <p class="text-[11px] text-slate-500 mt-1">ID Tuc: <code>{{ admin.currentProduct.external_id }}</code></p>
                </div>

                <!-- Historial de actualizaciones -->
                <div class="card p-5">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                            Historial de cambios
                        </p>
                        <span v-if="historyMeta" class="text-[10px] text-slate-400">
                            {{ historyMeta.total }} evento{{ historyMeta.total === 1 ? '' : 's' }}
                        </span>
                    </div>

                    <p v-if="admin.currentProduct?.last_updated_at" class="text-[11px] text-slate-500 mb-3">
                        Última modificación: <strong>{{ admin.currentProduct.last_updated_at ? new Date(admin.currentProduct.last_updated_at).toLocaleString('es-AR') : '—' }}</strong>
                    </p>

                    <div v-if="historyLoading" class="flex justify-center py-4">
                        <LoadingSpinner size="sm" />
                    </div>

                    <div v-else-if="history.length === 0" class="text-xs text-slate-400 italic py-2">
                        Aún no hay cambios registrados para este producto.
                    </div>

                    <ol v-else class="space-y-3 max-h-96 overflow-y-auto pr-1">
                        <li
                            v-for="h in history"
                            :key="h.id"
                            class="border-l-2 pl-3 py-1"
                            :class="{
                                'border-emerald-500': h.event === 'created',
                                'border-sky-400': h.event === 'updated' && h.source === 'admin',
                                'border-blue-400': h.source === 'scraper:daz',
                                'border-amber-400': h.source === 'scraper:tuc',
                                'border-slate-200': !['created','updated'].includes(h.event) || (!h.source.startsWith('scraper') && h.source !== 'admin'),
                            }"
                        >
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span
                                    class="chip text-[9px] font-bold"
                                    :class="sourceBadgeClass[h.source] || 'bg-slate-100 text-slate-700'"
                                >
                                    {{ h.source_label }}
                                </span>
                                <span class="text-[11px] font-bold text-slate-700">
                                    {{ eventLabel[h.event] || h.event }}
                                </span>
                                <span class="text-[10px] text-slate-400 ml-auto">
                                    {{ h.created_at_human }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-600">
                                <strong>{{ h.summary }}</strong>
                            </p>
                            <p v-if="h.actor" class="text-[10px] text-slate-400 mt-0.5">
                                por {{ h.actor.name }}
                            </p>

                            <!-- Detalle de cambios (colapsable) -->
                            <details v-if="h.changed_fields && h.changed_fields.length" class="mt-1.5">
                                <summary class="text-[10px] text-slate-500 cursor-pointer hover:text-slate-700">
                                    Ver detalle ({{ h.changed_fields.length }} campo{{ h.changed_fields.length === 1 ? '' : 's' }})
                                </summary>
                                <ul class="mt-1.5 space-y-1 text-[10px]">
                                    <li
                                        v-for="field in h.changed_fields"
                                        :key="field"
                                        class="flex items-start gap-1.5"
                                    >
                                        <span>{{ diffArrow(h.changes?.[field]?.before, h.changes?.[field]?.after) }}</span>
                                        <span class="font-mono text-slate-600">{{ field }}:</span>
                                        <span class="text-rose-500 line-through">{{ formatFieldValue(field, h.changes?.[field]?.before) }}</span>
                                        <span class="text-slate-400">→</span>
                                        <span class="text-emerald-600 font-semibold">{{ formatFieldValue(field, h.changes?.[field]?.after) }}</span>
                                    </li>
                                </ul>
                            </details>
                        </li>
                    </ol>
                </div>
            </aside>
        </div>
    </div>
</template>
