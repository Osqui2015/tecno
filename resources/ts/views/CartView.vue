<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useCartStore } from '@/stores/cart';
import { useAuthStore } from '@/stores/auth';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import SvgIcon from '@/components/SvgIcon.vue';

const cart = useCartStore();
const auth = useAuthStore();
const router = useRouter();
const imageErrors = ref<Record<number, boolean>>({});

// Confirmación de borrado: cuando el usuario hace click en "Quitar" (qty=0),
// mostramos "¿Confirmar?" y sólo borramos en el segundo click.
const confirmRemoveId = ref<number | null>(null);

// Buffer local del input numérico para permitir edición fluida sin
// disparar un request en cada tecla. Se sincroniza al perder foco o al Enter.
const qtyDrafts = ref<Record<number, string>>({});

onMounted(() => {
    if (auth.isAuthenticated) cart.fetchCart();
});

function checkout() {
    if (!auth.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: '/checkout' } });
        return;
    }
    router.push({ name: 'checkout' });
}

const formatPrice = (n: number | string) => {
    const value = Number(n);
    if (!Number.isFinite(value)) return '$0';
    return '$' + Math.round(value).toLocaleString('es-AR');
};

async function changeQty(itemId: number, currentQty: number, delta: number) {
    // Si llega a 0 y el usuario toca − otra vez, mostramos "Quitar" (no borramos).
    // Si pulsa + cuando está en 0, cancelamos el estado de "Quitar" pendiente.
    if (currentQty <= 0 && delta < 0) {
        // qty ya está en 0 → mostrar botón Quitar
        confirmRemoveId.value = itemId;
        return;
    }
    confirmRemoveId.value = null;
    await cart.updateQty(itemId, currentQty + delta);
}

function getQtyDraft(itemId: number, realQty: number): string {
    if (qtyDrafts.value[itemId] !== undefined) {
        return qtyDrafts.value[itemId];
    }
    return String(realQty);
}

function onQtyInput(itemId: number, value: string) {
    // Sólo dígitos, máx 3 caracteres
    const sanitized = value.replace(/\D/g, '').slice(0, 3);
    qtyDrafts.value[itemId] = sanitized;
}

async function commitQty(itemId: number, realQty: number) {
    const draft = qtyDrafts.value[itemId];
    if (draft === undefined) return;

    const parsed = parseInt(draft, 10);
    if (Number.isNaN(parsed)) {
        qtyDrafts.value[itemId] = String(realQty);
        return;
    }

    const clamped = Math.max(0, Math.min(999, parsed));
    delete qtyDrafts.value[itemId];
    confirmRemoveId.value = null;

    if (clamped !== realQty) {
        await cart.updateQty(itemId, clamped);
    }
}

function askRemove(itemId: number) {
    confirmRemoveId.value = itemId;
}

function cancelRemove() {
    confirmRemoveId.value = null;
}

async function confirmRemove(itemId: number) {
    confirmRemoveId.value = null;
    delete qtyDrafts.value[itemId];
    await cart.remove(itemId);
}
</script>

<template>
    <div class="space-y-6">
        <PageHeader icon="cart" title="Mi carrito" />

        <div v-if="!auth.isAuthenticated" class="card p-12 text-center max-w-lg mx-auto space-y-4">
            <div class="w-16 h-16 mx-auto rounded-full bg-slate-50 flex items-center justify-center text-slate-400">
                <SvgIcon name="cart" size="1.75rem" />
            </div>
            <p class="text-slate-650 font-bold text-sm">Iniciá sesión para ver tu carrito.</p>
            <router-link :to="{ name: 'login' }" class="btn btn-primary">Ingresar</router-link>
        </div>

        <LoadingSpinner v-else-if="cart.loading && !cart.loaded" text="Cargando carrito..." />

        <EmptyState
            v-else-if="cart.isEmpty"
            icon="cart"
            title="Tu carrito está vacío"
            description="Aún no agregaste productos. ¡Explorá nuestro catálogo e iniciá tu compra!"
        >
            <template #actions>
                <router-link :to="{ name: 'products' }" class="btn btn-primary">
                    Ver productos
                </router-link>
            </template>
        </EmptyState>

        <div v-else class="grid lg:grid-cols-3 gap-6 animate-fade-in">
            <!-- Items -->
            <div class="lg:col-span-2 space-y-3">
                <article
                    v-for="item in cart.items"
                    :key="item.id"
                    class="card p-4 hover:shadow-md transition-shadow"
                    :class="item.qty === 0 ? 'opacity-60 ring-1 ring-rose-200/60' : ''"
                >
                    <div class="flex items-center gap-4">
                        <router-link
                            :to="{ name: 'product-detail', params: { id: item.product_id } }"
                            class="w-20 h-20 sm:w-24 sm:h-24 bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl overflow-hidden flex-shrink-0 border border-slate-100/60"
                        >
                            <img
                                v-if="item.product.image && !imageErrors[item.id]"
                                :src="item.product.image"
                                :alt="item.product.name"
                                class="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
                                referrerpolicy="no-referrer"
                                @error="imageErrors[item.id] = true"
                            />
                            <div
                                v-else
                                class="w-full h-full flex items-center justify-center bg-slate-50 text-slate-400"
                            >
                                <SvgIcon name="box" size="1.25rem" class="opacity-80" />
                            </div>
                        </router-link>

                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-slate-800 text-sm line-clamp-2 leading-snug">
                                {{ item.product.name }}
                            </h3>
                            <p class="text-xs text-slate-400 font-semibold mt-1">
                                {{ formatPrice(item.product.final_price ?? item.product.price) }} c/u
                            </p>
                            <p
                                v-if="item.qty === 0"
                                class="text-[10px] text-rose-500 font-bold mt-1 uppercase tracking-wider"
                            >
                                Marcado para quitar
                            </p>
                        </div>

                        <!-- Qty controls desktop (oculto si qty=0) -->
                        <div
                            v-if="item.qty > 0"
                            class="hidden sm:flex items-center bg-slate-50 border border-slate-200/50 rounded-xl overflow-hidden"
                        >
                            <button
                                @click="changeQty(item.id, item.qty, -1)"
                                :disabled="cart.loading"
                                class="w-9 h-9 flex items-center justify-center hover:bg-slate-100 font-bold transition-colors cursor-pointer disabled:opacity-40"
                                aria-label="Disminuir cantidad"
                            >
                                <SvgIcon name="minus" size="0.65rem" />
                            </button>
                            <input
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                :value="getQtyDraft(item.id, item.qty)"
                                @input="onQtyInput(item.id, ($event.target as HTMLInputElement).value)"
                                @blur="commitQty(item.id, item.qty)"
                                @keydown.enter="($event.target as HTMLInputElement).blur()"
                                :disabled="cart.loading"
                                class="w-10 h-9 text-center font-bold text-sm text-slate-800 bg-transparent outline-none border-x border-slate-200/50 focus:bg-white focus:ring-2 focus:ring-brand-300"
                                aria-label="Cantidad"
                            />
                            <button
                                @click="changeQty(item.id, item.qty, 1)"
                                :disabled="cart.loading"
                                class="w-9 h-9 flex items-center justify-center hover:bg-slate-100 font-bold transition-colors cursor-pointer disabled:opacity-40"
                                aria-label="Aumentar cantidad"
                            >
                                <SvgIcon name="plus" size="0.65rem" />
                            </button>
                        </div>

                        <!-- Botón Quitar visible sólo cuando qty=0 -->
                        <button
                            v-else
                            @click="confirmRemoveId === item.id ? confirmRemove(item.id) : askRemove(item.id)"
                            :disabled="cart.loading"
                            class="hidden sm:inline-flex items-center gap-1.5 px-3 h-9 text-[11px] font-bold uppercase tracking-wider rounded-xl border transition-all cursor-pointer disabled:opacity-50"
                            :class="confirmRemoveId === item.id
                                ? 'bg-rose-600 text-white border-rose-600 hover:bg-rose-700'
                                : 'bg-rose-50 text-rose-600 border-rose-200 hover:bg-rose-100'"
                        >
                            <SvgIcon name="trash" size="0.75rem" />
                            <span>{{ confirmRemoveId === item.id ? 'Confirmar' : 'Quitar' }}</span>
                        </button>

                        <!-- Subtotal -->
                        <div class="text-right flex-shrink-0 pl-2">
                            <p
                                class="font-black text-base"
                                :class="item.qty === 0 ? 'text-slate-400 line-through' : 'text-slate-800'"
                            >
                                {{ formatPrice(Number(item.subtotal || (Number(item.product.final_price ?? item.product.price) * item.qty))) }}
                            </p>
                            <button
                                v-if="item.qty > 0"
                                @click="askRemove(item.id)"
                                class="inline-flex items-center gap-1 text-[11px] text-slate-400 hover:text-rose-600 mt-1.5 font-bold transition-colors cursor-pointer"
                            >
                                <SvgIcon name="trash" size="0.7rem" />
                                <span>Eliminar</span>
                            </button>
                        </div>
                    </div>

                    <!-- Mobile: controles siempre accesibles -->
                    <div class="flex sm:hidden w-full mt-3 items-center justify-between gap-2">
                        <div class="flex items-center bg-slate-50 border border-slate-200/50 rounded-xl overflow-hidden">
                            <button
                                @click="changeQty(item.id, item.qty, -1)"
                                class="w-8 h-8 flex items-center justify-center"
                                aria-label="Disminuir cantidad"
                            >
                                <SvgIcon name="minus" size="0.6rem" />
                            </button>
                            <span class="w-8 text-center font-bold text-sm">
                                {{ item.qty }}
                            </span>
                            <button
                                @click="changeQty(item.id, item.qty, 1)"
                                class="w-8 h-8 flex items-center justify-center"
                                aria-label="Aumentar cantidad"
                            >
                                <SvgIcon name="plus" size="0.6rem" />
                            </button>
                        </div>
                        <button
                            @click="item.qty > 0 ? askRemove(item.id) : (confirmRemoveId === item.id ? confirmRemove(item.id) : askRemove(item.id))"
                            class="text-[11px] font-bold text-rose-600 px-3 py-1.5 rounded-lg bg-rose-50"
                        >
                            {{ confirmRemoveId === item.id ? 'Confirmar' : 'Quitar' }}
                        </button>
                    </div>
                </article>

                <p v-if="cart.error" class="text-xs text-rose-500 font-semibold text-center">{{ cart.error }}</p>
            </div>

            <!-- Summary -->
            <aside class="lg:sticky lg:top-24 lg:self-start space-y-4">
                <!-- Mínimo de compra (barra de progreso) -->
                <div class="card p-6">
                    <div class="flex items-center justify-between mb-2.5">
                        <span class="text-xs font-bold text-slate-700">
                            Compra mínima: {{ formatPrice(cart.minPurchase) }}
                        </span>
                        <span
                            class="text-xs font-bold"
                            :class="cart.meetsMinimum ? 'text-emerald-600' : 'text-rose-500'"
                        >
                            {{ Math.round(cart.progress) }}%
                        </span>
                    </div>

                    <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-500 ease-out"
                            :class="cart.meetsMinimum
                                ? 'bg-gradient-to-r from-emerald-400 to-emerald-600'
                                : 'bg-gradient-to-r from-amber-400 via-orange-500 to-rose-500'"
                            :style="{ width: cart.progress + '%' }"
                        ></div>
                    </div>

                    <p
                        v-if="!cart.meetsMinimum"
                        class="text-[11px] text-rose-500 font-bold mt-2 leading-snug"
                    >
                        Te faltan {{ formatPrice(cart.remaining) }} para alcanzar el mínimo.
                    </p>
                    <p
                        v-else
                        class="text-[11px] text-emerald-600 font-bold mt-2 leading-snug"
                    >
                        ¡Mínimo alcanzado! Podés finalizar tu compra.
                    </p>
                </div>

                <div class="card p-6">
                    <h2 class="font-bold text-slate-800 text-base mb-4 tracking-tight">Resumen del pedido</h2>
                    <div class="space-y-3.5 text-xs font-semibold text-slate-500">
                        <div class="flex justify-between">
                            <span>Subtotal ({{ cart.itemsCount }} items)</span>
                            <span class="text-slate-850 font-bold">{{ formatPrice(cart.total) }}</span>
                        </div>
                    </div>
                    <hr class="my-4 border-slate-100" />
                    <div class="flex justify-between items-baseline mb-6">
                        <span class="font-bold text-slate-800 text-sm">Total</span>
                        <span class="text-2xl font-black text-slate-800">
                            {{ formatPrice(cart.total) }}
                        </span>
                    </div>
                    <button
                        @click="checkout"
                        :disabled="!cart.meetsMinimum"
                        class="btn btn-primary btn-lg w-full disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span>Finalizar compra</span>
                        <SvgIcon name="chevron-right" size="0.9rem" />
                    </button>
                    <p
                        v-if="!cart.meetsMinimum"
                        class="text-[11px] text-rose-500 font-semibold text-center mt-2"
                    >
                        Necesitás llegar a {{ formatPrice(cart.minPurchase) }} para finalizar.
                    </p>
                    <button @click="cart.clear()" class="btn btn-ghost btn-sm w-full mt-2.5 font-bold">
                        Vaciar carrito
                    </button>
                </div>
            </aside>
        </div>
    </div>
</template>