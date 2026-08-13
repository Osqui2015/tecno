<script setup lang="ts">
import { onMounted, ref } from 'vue';
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

const stats = ref<Stats | null>(null);
const loading = ref(true);

onMounted(async () => {
    try {
        const { data } = await axios.get<Stats>('/admin/stats');
        stats.value = data;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
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
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Revenue total</p>
                <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ formatPrice(stats.revenue.total) }}</p>
                <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold mt-1">
                    +{{ formatPrice(stats.revenue.this_month) }} este mes
                </p>
            </div>
            <div class="card p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Pedidos totales</p>
                <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ stats.orders.total }}</p>
                <p class="text-[10px] text-slate-400 font-bold mt-1">
                    {{ stats.orders.pending }} pendiente(s)
                </p>
            </div>
            <div class="card p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Ticket promedio</p>
                <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ formatPrice(stats.revenue.avg_ticket) }}</p>
                <p class="text-[10px] text-slate-400 font-bold mt-1">por pedido no cancelado</p>
            </div>
            <div class="card p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Productos</p>
                <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ stats.products.total }}</p>
                <p class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">
                    {{ stats.products.out_of_stock }} sin stock
                </p>
            </div>
        </div>

        <!-- Gráfico interactivo de tendencia de ventas -->
        <SalesChart :data="stats.sales_last_30_days || stats.sales_7_days" />

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Embudo por estado -->
            <div class="card p-6">
                <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100 mb-4">Pedidos por estado</h3>
                <div v-if="Object.keys(stats.orders.by_status).length === 0" class="text-sm text-slate-400">
                    Sin pedidos aún.
                </div>
                <div v-else class="space-y-3">
                    <div v-for="(count, status) in stats.orders.by_status" :key="status">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ statusLabel[status] || status }}</span>
                            <span class="text-xs font-black text-slate-800 dark:text-slate-100">{{ count }}</span>
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
                <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100 mb-4">Top 5 productos más vendidos</h3>
                <div v-if="stats.top_products.length === 0" class="text-sm text-slate-400">
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
                            <p class="text-sm font-bold text-slate-800 dark:text-slate-200 line-clamp-1">{{ p.name }}</p>
                            <p class="text-[10px] text-slate-400 font-semibold">{{ p.sold_qty }} vendido(s)</p>
                        </div>
                        <span class="text-sm font-black text-slate-800 dark:text-slate-100">{{ formatPrice(p.revenue) }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Acciones rápidas -->
        <div class="grid sm:grid-cols-2 gap-4">
            <router-link :to="{ name: 'admin-orders', query: { status: 'pending' } }" class="card p-5 hover:shadow-md transition-shadow flex items-center justify-between">
                <div>
                    <p class="font-bold text-sm text-slate-800 dark:text-slate-100">Revisar pedidos pendientes</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ stats.orders.pending }} esperando confirmación</p>
                </div>
                <SvgIcon name="chevron-right" size="1rem" class="text-slate-400" />
            </router-link>
            <router-link :to="{ name: 'admin-products' }" class="card p-5 hover:shadow-md transition-shadow flex items-center justify-between">
                <div>
                    <p class="font-bold text-sm text-slate-800 dark:text-slate-100">Gestionar catálogo</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ stats.products.total }} productos · {{ stats.products.out_of_stock }} sin stock</p>
                </div>
                <SvgIcon name="chevron-right" size="1rem" class="text-slate-400" />
            </router-link>
        </div>
    </div>
</template>
