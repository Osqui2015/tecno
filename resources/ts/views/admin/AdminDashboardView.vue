<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed } from 'vue';
import axios from '@/bootstrap';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import SvgIcon from '@/components/SvgIcon.vue';
import SalesChart from '@/components/charts/SalesChart.vue';

interface Stats {
    products: {
        total: number;
        active: number;
        from_daz: number;
        out_of_stock: number;
    };
    orders: {
        total: number;
        pending: number;
        by_status: Record<string, number>;
    };
    revenue: {
        total: number;
        this_month: number;
        avg_ticket: number;
        month_orders_count: number;
    };
    top_products: Array<{
        id: number;
        name: string;
        image: string | null;
        sold_qty: number;
        revenue: number;
    }>;
    recent_orders: Array<any>;
    sales_7_days: Array<{ date: string; orders_count: number; revenue: number }>;
    sales_last_30_days?: Array<{ date: string; orders_count: number; revenue: number }>;
}

interface ScrapeStatus {
    now: string;
    next_run_at: string;
    next_run_human: string;
    seconds_until_next: number;
    interval_hours: number;
    last_actual_run_at: string | null;
    last_actual_run_human: string | null;
    last_run_stats: {
        last_run_at: string | null;
        updated: number;
        created: number;
        by_source: Record<string, number>;
    };
}

const stats = ref<Stats | null>(null);
const scrapeStatus = ref<ScrapeStatus | null>(null);
const loading = ref(true);
let nowInterval: number | null = null;
const now = ref(Date.now());

const secondsUntilNext = computed(() => {
    if (!scrapeStatus.value) return 0;
    const drift = Math.floor((Date.now() - now.value) / 1000);
    return Math.max(0, scrapeStatus.value.seconds_until_next - drift);
});

function formatCountdown(seconds: number): string {
    if (seconds <= 0) return 'ahora';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    if (h > 0) return `${h}h ${m}m`;
    if (m > 0) return `${m}m ${s}s`;
    return `${s}s`;
}

onMounted(async () => {
    try {
        const { data } = await axios.get<Stats>('/admin/stats');
        stats.value = data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
    // Cargar estado del scraper (no bloquea el dashboard)
    try {
        const { data } = await axios.get<ScrapeStatus>('/admin/scrape-status');
        scrapeStatus.value = data;
    } catch (e) {
        console.warn('No se pudo obtener estado del scraper', e);
    }
    now.value = Date.now();
    nowInterval = window.setInterval(() => { now.value = Date.now(); }, 1000);
});

onUnmounted(() => {
    if (nowInterval !== null) {
        clearInterval(nowInterval);
        nowInterval = null;
    }
});

const formatPrice = (n: number | string) =>
    '$' + Math.round(Number(n)).toLocaleString('es-AR');

const statusLabel: Record<string, string> = {
    pending: 'Pendiente',
    confirmed: 'Confirmado',
    preparing: 'Preparando',
    shipped: 'En camino',
    delivered: 'Entregado',
    cancelled: 'Cancelado',
    modified: 'Modificado',
};
</script>

<template>
    <LoadingSpinner v-if="loading" text="Cargando métricas..." />

    <div v-else-if="stats" class="space-y-6">
        <!-- KPIs principales -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="card p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Revenue total</p>
                <p class="text-2xl font-black text-zinc-800 dark:text-slate-100">{{ formatPrice(stats.revenue.total) }}</p>
                <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold mt-1">
                    +{{ formatPrice(stats.revenue.this_month) }} este mes
                </p>
            </div>
            <div class="card p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Pedidos totales</p>
                <p class="text-2xl font-black text-zinc-800 dark:text-slate-100">{{ stats.orders.total }}</p>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold mt-1">
                    {{ stats.orders.pending }} pendiente(s)
                </p>
            </div>
            <div class="card p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Ticket promedio</p>
                <p class="text-2xl font-black text-zinc-800 dark:text-slate-100">{{ formatPrice(stats.revenue.avg_ticket) }}</p>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold mt-1">por pedido no cancelado</p>
            </div>
            <div class="card p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Productos</p>
                <p class="text-2xl font-black text-zinc-800 dark:text-slate-100">{{ stats.products.total }}</p>
                <p class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">
                    {{ stats.products.out_of_stock }} sin stock
                </p>
            </div>
        </div>

        <!-- Card: Estado del scraper -->
        <div v-if="scrapeStatus" class="card p-5 flex flex-wrap items-center justify-between gap-4 bg-gradient-to-br from-sky-50/60 to-indigo-50/40 border-sky-100">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-sky-500 to-indigo-600 text-white flex items-center justify-center shadow-md">
                    <SvgIcon name="refresh" size="1.3rem" />
                </div>
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                        Sincronización con proveedores
                    </p>
                    <p class="text-base font-extrabold text-slate-800">
                        {{ scrapeStatus.last_actual_run_human || 'Aún no se ejecutó' }}
                    </p>
                    <p class="text-xs text-slate-500 mt-0.5">
                        <span v-if="scrapeStatus.last_run_stats?.last_run_at">
                            {{ scrapeStatus.last_run_stats.created }} nuevos ·
                            {{ scrapeStatus.last_run_stats.updated }} actualizados
                        </span>
                        <span v-else>
                            Los productos Daz y TusTec-Tuc se actualizan cada {{ scrapeStatus.interval_hours }}h automáticamente.
                        </span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2 px-5 py-3 rounded-2xl bg-emerald-50 border-2 border-emerald-200 shadow-sm">
                <SvgIcon name="clock" size="1.1rem" class="text-emerald-600" />
                <div>
                    <p class="text-[10px] uppercase font-extrabold text-emerald-700 leading-tight">Próxima corrida</p>
                    <p class="font-extrabold text-emerald-800 text-sm leading-tight">
                        {{ scrapeStatus.next_run_human }}
                    </p>
                    <p class="text-[10px] font-bold text-emerald-600 mt-0.5">
                        faltan {{ formatCountdown(secondsUntilNext) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Gráfico interactivo de tendencia de ventas -->
        <SalesChart :data="stats.sales_last_30_days || stats.sales_7_days" />

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Embudo por estado -->
            <div class="card p-6">
                <h3 class="font-bold text-sm text-zinc-800 dark:text-slate-100 mb-4">Pedidos por estado</h3>
                <div v-if="Object.keys(stats.orders.by_status).length === 0" class="text-sm text-slate-500 dark:text-slate-400">
                    Sin pedidos aún.
                </div>
                <div v-else class="space-y-3">
                    <div v-for="(count, status) in stats.orders.by_status" :key="status">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-bold text-zinc-700 dark:text-slate-300">{{ statusLabel[status] || status }}</span>
                            <span class="text-xs font-black text-zinc-800 dark:text-slate-100">{{ count }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="{
                                    'bg-amber-400': status === 'pending',
                                    'bg-blue-500': status === 'confirmed' || status === 'preparing' || status === 'shipped',
                                    'bg-emerald-500': status === 'delivered',
                                    'bg-rose-500': status === 'cancelled',
                                }"
                                :style="{ width: ((count / stats.orders.total) * 100) + '%' }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top productos -->
            <div class="card p-6 lg:col-span-2">
                <h3 class="font-bold text-sm text-zinc-800 dark:text-slate-100 mb-4">Top 5 productos más vendidos</h3>
                <div v-if="stats.top_products.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
                    Sin ventas registradas.
                </div>
                <ul v-else class="space-y-2">
                    <li
                        v-for="(p, idx) in stats.top_products"
                        :key="p.id"
                        class="flex items-center gap-3 p-2 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                    >
                        <span class="w-6 h-6 rounded-full bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 flex items-center justify-center font-black text-xs">
                            {{ idx + 1 }}
                        </span>
                        <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-800 overflow-hidden flex-shrink-0">
                            <img v-if="p.image" :src="p.image" class="w-full h-full object-cover" referrerpolicy="no-referrer" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-zinc-800 dark:text-slate-200 line-clamp-1">{{ p.name }}</p>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold">{{ p.sold_qty }} vendido(s)</p>
                        </div>
                        <span class="text-sm font-black text-zinc-800 dark:text-slate-100">{{ formatPrice(p.revenue) }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Acciones rápidas -->
        <div class="grid sm:grid-cols-2 gap-4">
            <router-link :to="{ name: 'admin-orders', query: { status: 'pending' } }" class="card p-5 hover:shadow-md transition-shadow flex items-center justify-between">
                <div>
                    <p class="font-bold text-sm text-zinc-800 dark:text-slate-100">Revisar pedidos pendientes</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ stats.orders.pending }} esperando confirmación</p>
                </div>
                <SvgIcon name="chevron-right" size="1rem" class="text-slate-400 dark:text-slate-500" />
            </router-link>
            <router-link :to="{ name: 'admin-products' }" class="card p-5 hover:shadow-md transition-shadow flex items-center justify-between">
                <div>
                    <p class="font-bold text-sm text-zinc-800 dark:text-slate-100">Gestionar catálogo</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ stats.products.total }} productos · {{ stats.products.out_of_stock }} sin stock</p>
                </div>
                <SvgIcon name="chevron-right" size="1rem" class="text-slate-400 dark:text-slate-500" />
            </router-link>
        </div>
    </div>
</template>
