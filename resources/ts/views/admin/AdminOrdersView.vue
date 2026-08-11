<script setup lang="ts">
import { onMounted, reactive, watch, computed } from 'vue';
import { useAdminStore } from '@/stores/admin';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import SvgIcon from '@/components/SvgIcon.vue';

const admin = useAdminStore();

const filters = reactive({
    search: '',
    status: '',
    source: '' as '' | 'daz' | 'tuc' | 'manual' | 'mixed',
    page: 1,
    per_page: 25,
});

function load() {
    const params: any = { page: filters.page, per_page: filters.per_page };
    if (filters.search.trim()) params.search = filters.search.trim();
    if (filters.status) params.status = filters.status;
    if (filters.source) params.source = filters.source;
    admin.fetchOrders(params);
}

watch(filters, load, { deep: true });
onMounted(load);

function clearFilters() {
    filters.search = '';
    filters.status = '';
    filters.source = '';
    filters.page = 1;
}

function nextPage() { filters.page++; }
function prevPage() { if (filters.page > 1) filters.page--; }

const formatPrice = (n: number | string) =>
    '$' + Math.round(Number(n)).toLocaleString('es-AR');

const formatDate = (d: string) =>
    new Date(d).toLocaleString('es-AR', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });

function statusInfo(status: string) {
    const map: Record<string, { label: string; cls: string }> = {
        pending:   { label: 'Pendiente',  cls: 'chip-warning' },
        confirmed: { label: 'Confirmado', cls: 'chip-info' },
        preparing: { label: 'Preparando', cls: 'chip-info' },
        shipped:   { label: 'En camino',  cls: 'chip-info' },
        delivered: { label: 'Entregado',  cls: 'chip-success' },
        cancelled: { label: 'Cancelado',  cls: 'chip-danger' },
        modified:  { label: 'Modificado', cls: 'chip-warning' },
    };
    return map[status] ?? { label: status, cls: 'chip-muted' };
}

function originBadge(label?: string) {
    if (label === 'daz')    return { label: 'Daz',    cls: 'chip-info' };
    if (label === 'tuc')    return { label: 'Tuc',    cls: 'chip-brand' };
    if (label === 'manual') return { label: 'Manual', cls: 'chip-muted' };
    if (label === 'mixed')  return { label: 'Mixto',  cls: 'chip-warning' };
    return { label: '—', cls: 'chip-muted' };
}

const totalPages = computed(() => admin.ordersMeta?.last_page ?? 1);
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
                        placeholder="Buscar por nombre, email o ID..."
                        class="input pl-10"
                    />
                </div>
                <select v-model="filters.status" class="input max-w-[160px]">
                    <option value="">Todos los estados</option>
                    <option value="pending">Pendiente</option>
                    <option value="confirmed">Confirmado</option>
                    <option value="preparing">Preparando</option>
                    <option value="shipped">En camino</option>
                    <option value="delivered">Entregado</option>
                    <option value="cancelled">Cancelado</option>
                    <option value="modified">Modificado</option>
                </select>
                <select v-model="filters.source" class="input max-w-[140px]">
                    <option value="">Todos los orígenes</option>
                    <option value="daz">Daz</option>
                    <option value="tuc">TusTecnología</option>
                    <option value="manual">Manual</option>
                    <option value="mixed">Mixto</option>
                </select>
                <button @click="clearFilters" class="btn btn-ghost btn-sm">
                    Limpiar
                </button>
            </div>
        </div>

        <LoadingSpinner v-if="admin.loading" text="Cargando pedidos..." />

        <div v-else-if="admin.orders.length === 0" class="card p-12 text-center">
            <p class="text-slate-500 font-semibold">No hay pedidos con esos filtros.</p>
        </div>

        <div v-else class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr class="text-left text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">Pedido</th>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Origen</th>
                            <th class="px-4 py-3">Items</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="o in admin.orders" :key="o.id" class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-4 py-3">
                                <p class="font-bold text-slate-800 text-xs">#{{ o.id }}</p>
                                <p class="text-[10px] text-slate-400 font-semibold">{{ formatDate(o.created_at) }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-xs font-semibold text-slate-700 line-clamp-1">{{ o.customer_full_name || '—' }}</p>
                                <p class="text-[10px] text-slate-400 line-clamp-1">{{ o.customer_phone || '—' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['chip text-[10px]', originBadge(o.origin_label).cls]">
                                    {{ originBadge(o.origin_label).label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600 font-semibold">
                                {{ o.items?.length || 0 }}
                                <span v-if="o.origin_label === 'mixed'" class="text-[10px] text-slate-400">
                                    ({{ o.items_count_daz }}D/{{ o.items_count_tuc }}T/{{ o.items_count_manual }}M)
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-black text-slate-800 text-xs">
                                {{ formatPrice(o.total) }}
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['chip text-[10px]', statusInfo(o.status).cls]">
                                    {{ statusInfo(o.status).label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <router-link
                                    :to="{ name: 'admin-order-detail', params: { id: o.id } }"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-bold text-brand-600 hover:bg-brand-50 rounded-lg transition-colors"
                                >
                                    Ver
                                    <SvgIcon name="chevron-right" size="0.85rem" />
                                </router-link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-slate-100 bg-slate-50/50">
                <p class="text-xs text-slate-500">
                    Página <strong>{{ filters.page }}</strong> de {{ totalPages }}
                    · {{ admin.ordersMeta?.total ?? 0 }} pedidos
                </p>
                <div class="flex gap-1">
                    <button @click="prevPage" :disabled="filters.page === 1" class="btn btn-ghost btn-sm">
                        <SvgIcon name="chevron-left" size="0.85rem" /> Anterior
                    </button>
                    <button @click="nextPage" :disabled="filters.page >= totalPages" class="btn btn-ghost btn-sm">
                        Siguiente <SvgIcon name="chevron-right" size="0.85rem" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
