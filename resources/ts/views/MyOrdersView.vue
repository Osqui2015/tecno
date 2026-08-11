<script setup lang="ts">
import { onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useOrdersStore } from '@/stores/orders';
import PageHeader from '@/components/PageHeader.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import EmptyState from '@/components/EmptyState.vue';

const ordersStore = useOrdersStore();
const route = useRoute();

onMounted(() => {
    ordersStore.fetchMyOrders();
});

const formatPrice = (n: number) =>
    '$' + Math.round(n).toLocaleString('es-AR');

const formatDate = (d: string) =>
    new Date(d).toLocaleString('es-AR', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

function statusInfo(status: string) {
    const map: Record<string, { label: string; cls: string; icon: string }> = {
        pending:   { label: 'Pendiente',  cls: 'chip-warning', icon: '⏳' },
        processing:{ label: 'Procesando', cls: 'chip-info',    icon: '⚙️' },
        shipped:   { label: 'En camino',  cls: 'chip-info',    icon: '🚚' },
        delivered: { label: 'Entregado',  cls: 'chip-success', icon: '✅' },
        completed: { label: 'Completado', cls: 'chip-success', icon: '✅' },
        cancelled: { label: 'Cancelado',  cls: 'chip-danger',  icon: '❌' },
    };
    return map[status] ?? { label: status, cls: 'chip-muted', icon: '📦' };
}
</script>

<template>
    <div class="space-y-6">
        <PageHeader icon="📋" title="Mis pedidos" subtitle="Revisá el estado de tus compras." />

        <!-- Success alert -->
        <transition name="fade">
            <div
                v-if="route.query.success"
                class="rounded-2xl bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 px-5 py-4 flex items-center gap-3 shadow-sm"
            >
                <span
                    class="w-10 h-10 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xl shadow-md animate-pulse-glow"
                >
                    ✓
                </span>
                <div>
                    <p class="font-bold text-emerald-900">¡Pedido confirmado!</p>
                    <p class="text-sm text-emerald-700">Te enviamos un email con el detalle.</p>
                </div>
            </div>
        </transition>

        <LoadingSpinner v-if="ordersStore.loading" text="Cargando pedidos..." />

        <EmptyState
            v-else-if="ordersStore.orders.length === 0"
            icon="📦"
            title="Aún no tenés pedidos"
            description="Cuando realices tu primera compra, aparecerá acá."
        >
            <template #actions>
                <router-link :to="{ name: 'products' }" class="btn btn-primary">
                    Explorar productos
                </router-link>
            </template>
        </EmptyState>

        <div v-else class="space-y-4">
            <article
                v-for="order in ordersStore.orders"
                :key="order.id"
                class="card p-6 hover:shadow-md transition-shadow animate-fade-in"
            >
                <div class="flex flex-wrap justify-between gap-3 items-start mb-4">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="font-bold text-lg text-gray-900">
                                Pedido #{{ order.id }}
                            </h3>
                            <span :class="statusInfo(order.status).cls">
                                {{ statusInfo(order.status).icon }}
                                {{ statusInfo(order.status).label }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 flex items-center gap-1">
                            🗓️ {{ formatDate(order.created_at) }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500">Total</p>
                        <p class="text-2xl font-extrabold text-gray-900">
                            {{ formatPrice(Number(order.total)) }}
                        </p>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-xl p-4 mb-3">
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-1">
                        Dirección de envío
                    </p>
                    <p class="text-sm text-gray-700 flex items-start gap-2">
                        <span>📍</span>
                        <span>{{ order.shipping_address }}</span>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-gray-100">
                    <span class="chip chip-muted">
                        📦 {{ order.items?.length || 0 }} {{ (order.items?.length || 0) === 1 ? 'producto' : 'productos' }}
                    </span>
                    <button
                        class="ml-auto btn btn-ghost btn-sm"
                        @click="(order as any)._expanded = !(order as any)._expanded"
                    >
                        {{ (order as any)._expanded ? 'Ocultar detalle' : 'Ver detalle' }}
                    </button>
                </div>

                <transition name="fade">
                    <ul
                        v-if="(order as any)._expanded && order.items?.length"
                        class="mt-4 space-y-2 pt-4 border-t border-gray-100"
                    >
                        <li
                            v-for="item in order.items"
                            :key="item.product_id"
                            class="flex items-center justify-between text-sm"
                        >
                            <span class="text-gray-700">
                                <span class="font-semibold">{{ item.qty }}×</span>
                                Producto #{{ item.product_id }}
                            </span>
                            <span class="font-medium text-gray-900">
                                {{ formatPrice(Number(item.price) * item.qty) }}
                            </span>
                        </li>
                    </ul>
                </transition>
            </article>
        </div>
    </div>
</template>