<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useOrdersStore } from '@/stores/orders';
import PageHeader from '@/components/PageHeader.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import EmptyState from '@/components/EmptyState.vue';
import SvgIcon from '@/components/SvgIcon.vue';
import { formatCurrency, formatDateTime } from '@/utils/formatters';

const ordersStore = useOrdersStore();
const route = useRoute();
const imageErrors = ref<Record<number, boolean>>({});

onMounted(() => {
    ordersStore.fetchMyOrders();
});

const formatPrice = formatCurrency;
const formatDate = formatDateTime;

function statusInfo(status: string) {
    const map: Record<string, { label: string; cls: string; icon: string }> = {
        pending:   { label: 'Pendiente',  cls: 'chip-warning', icon: 'clock' },
        confirmed: { label: 'Confirmado', cls: 'chip-info',    icon: 'check' },
        preparing: { label: 'Preparando', cls: 'chip-info',    icon: 'box' },
        shipped:   { label: 'En camino',  cls: 'chip-info',    icon: 'truck' },
        delivered: { label: 'Entregado',  cls: 'chip-success', icon: 'check' },
        cancelled: { label: 'Cancelado',  cls: 'chip-danger',  icon: 'close' },
        // Fallbacks
        processing:{ label: 'En proceso', cls: 'chip-info',    icon: 'refresh' },
        completed: { label: 'Entregado',  cls: 'chip-success', icon: 'check' },
    };
    return map[status] ?? { label: status, cls: 'chip-muted', icon: 'box' };
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
                            <h3 class="font-bold text-lg text-slate-900 dark:text-slate-100">
                                Pedido #{{ order.id }}
                            </h3>
                            <span :class="statusInfo(order.status).cls" class="inline-flex items-center gap-1.5">
                                <SvgIcon :name="statusInfo(order.status).icon" size="0.75rem" />
                                <span>{{ statusInfo(order.status).label }}</span>
                            </span>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 flex items-center gap-1">
                            <SvgIcon name="clock" size="0.8rem" />
                            <span>{{ formatDate(order.created_at) }}</span>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Total</p>
                        <p class="text-2xl font-extrabold text-slate-900 dark:text-slate-100">
                            {{ formatPrice(Number(order.total)) }}
                        </p>
                    </div>
                </div>

                <div class="bg-slate-50 dark:bg-slate-800/60 rounded-xl p-4 mb-3">
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase mb-1">
                        Dirección de envío
                    </p>
                    <p class="text-sm text-slate-700 dark:text-slate-300 flex items-start gap-2">
                        <span>📍</span>
                        <span>{{ order.shipping_address }}</span>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
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
                        class="mt-4 space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800"
                    >
                        <li
                            v-for="item in order.items"
                            :key="item.id || item.product_id"
                            class="flex items-center justify-between text-sm py-2 border-b border-slate-50 dark:border-slate-800/50 last:border-0"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-12 h-12 bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-800 rounded-xl overflow-hidden flex-shrink-0">
                                    <img
                                        v-if="item.product?.image && !imageErrors[item.id]"
                                        :src="item.product.image"
                                        :alt="item.product?.name"
                                        width="48"
                                        height="48"
                                        loading="lazy"
                                        decoding="async"
                                        class="w-full h-full object-cover"
                                        referrerpolicy="no-referrer"
                                        @error="imageErrors[item.id] = true"
                                    />
                                    <div v-else class="w-full h-full flex items-center justify-center bg-slate-50 dark:bg-slate-800 text-slate-400">
                                        <SvgIcon name="box" size="0.95rem" />
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100 text-xs line-clamp-1">
                                        {{ item.product?.name || `Producto #${item.product_id}` }}
                                    </p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">
                                        {{ item.qty }} × {{ formatPrice(Number(item.price)) }}
                                    </p>
                                </div>
                            </div>
                            <span class="font-bold text-slate-900 dark:text-slate-100 text-sm ml-4 whitespace-nowrap">
                                {{ formatPrice(Number(item.price) * item.qty) }}
                            </span>
                        </li>
                    </ul>
                </transition>
            </article>
        </div>
    </div>
</template>