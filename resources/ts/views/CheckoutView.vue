<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useCartStore } from '@/stores/cart';
import { useOrdersStore } from '@/stores/orders';
import { useAuthStore } from '@/stores/auth';
import { useCouponStore } from '@/stores/coupon';
import { useStoreInfo, buildWhatsappUrl } from '@/composables/useStoreInfo';
import PageHeader from '@/components/PageHeader.vue';
import SvgIcon from '@/components/SvgIcon.vue';

const router = useRouter();
const cart = useCartStore();
const orders = useOrdersStore();
const auth = useAuthStore();
const coupon = useCouponStore();
const { whatsappNumber, name: storeName, load: loadStoreInfo } = useStoreInfo();
const imageErrors = ref<Record<number, boolean>>({});

const address = ref('');
const city = ref('');
const zip = ref('');
const phone = ref('');
const notes = ref('');
const submitting = ref(false);

const formatPrice = (n: number | string) =>
    '$' + Math.round(Number(n)).toLocaleString('es-AR');

onMounted(async () => {
    if (!auth.user) await auth.fetchUser();
    await cart.fetchCart();
    // Cargar el número de WhatsApp de la tienda (se cachea entre componentes).
    loadStoreInfo();
});

const valid = computed(() => {
    return (
        address.value.trim().length >= 5 &&
        city.value.trim().length >= 2 &&
        zip.value.trim().length >= 3 &&
        phone.value.trim().length >= 5 &&
        !cart.isEmpty &&
        cart.meetsMinimum
    );
});

async function applyCoupon() {
    if (cart.total <= 0) return;
    await coupon.validate(Number(cart.total));
}

/**
 * Arma el mensaje de WhatsApp con el resumen del pedido y abre
 * una pestaña nueva con wa.me apuntando al número de la tienda.
 * Si no hay número configurado, no hace nada.
 */
function openWhatsappForOrder(order: any) {
    if (!whatsappNumber.value) return;

    const lines: string[] = [];
    lines.push(`Hola ${storeName.value || 'Tecno-Rexs'}!`);
    lines.push('');
    lines.push(`Acabo de hacer el pedido #${order.id} en la web:`);
    lines.push('');

    for (const item of order.items ?? []) {
        const name = item.product?.name ?? `Producto #${item.product_id}`;
        const price = formatPrice(item.price);
        lines.push(`- ${item.qty}x ${name} - ${price}`);
    }

    lines.push('');
    lines.push(`Total: ${formatPrice(order.total)}`);
    lines.push('');
    lines.push('Mis datos:');
    if (order.customer_name || order.customer_lastname) {
        lines.push(`- Nombre: ${(order.customer_name ?? '') + ' ' + (order.customer_lastname ?? '')}`.trim());
    }
    if (order.customer_phone) {
        lines.push(`- Teléfono: ${order.customer_phone}`);
    }
    if (order.customer_address || order.customer_city) {
        const addr = [order.customer_address, order.customer_city, order.customer_zip]
            .filter(Boolean)
            .join(', ');
        lines.push(`- Dirección: ${addr}`);
    }
    if (order.customer_notes) {
        lines.push(`- Notas: ${order.customer_notes}`);
    }

    lines.push('');
    lines.push('Quedo atento a la confirmación. Gracias!');

    const url = buildWhatsappUrl(whatsappNumber.value, lines.join('\n'));
    if (url) {
        // Abrimos en pestaña nueva para no perder la página de "Mis pedidos".
        window.open(url, '_blank', 'noopener,noreferrer');
    }
}

async function placeOrder() {
    if (!valid.value) return;
    if (!cart.meetsMinimum) {
        alert(`La compra mínima es de ${formatPrice(cart.minPurchase)}. Te faltan ${formatPrice(cart.remaining)}.`);
        return;
    }
    submitting.value = true;
    try {
        const overrides: any = {
            customer_address: address.value,
            customer_city: city.value,
            customer_zip: zip.value,
            customer_phone: phone.value,
            customer_notes: notes.value || undefined,
        };
        if (coupon.appliedCoupon) {
            overrides.coupon_code = coupon.appliedCoupon.code;
        }

        const order = await orders.checkout(overrides);
        if (order) {
            // Abrimos WhatsApp con el resumen ANTES de vaciar el carrito
            // y redirigir, para que el cliente tenga el flujo natural:
            // confirma -> WhatsApp -> vuelve a la web a ver su pedido.
            openWhatsappForOrder(order);
            await cart.fetchCart();
            coupon.remove();
            router.push({ name: 'my-orders', query: { success: '1' } });
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="space-y-6">
        <PageHeader icon="box" title="Finalizar compra" />

        <div v-if="cart.isEmpty" class="card p-12 text-center max-w-lg mx-auto space-y-4">
            <div class="w-16 h-16 mx-auto rounded-full bg-slate-50 flex items-center justify-center text-slate-400">
                <SvgIcon name="cart" size="1.75rem" />
            </div>
            <p class="text-slate-650 font-bold text-sm">
                No tenés productos cargados en tu carrito.
            </p>
            <router-link :to="{ name: 'products' }" class="btn btn-primary">
                Explorar productos
            </router-link>
        </div>

        <div v-else class="grid lg:grid-cols-3 gap-6 animate-fade-in">
            <div class="lg:col-span-2 space-y-6">
                <!-- Cupón -->
                <section class="card p-6">
                    <div class="flex items-center gap-2.5 mb-4">
                        <span class="w-7 h-7 rounded-xl bg-amber-100/70 text-amber-700 flex items-center justify-center font-bold text-xs">
                            🎟️
                        </span>
                        <h2 class="text-sm font-extrabold text-slate-805">Cupón de descuento</h2>
                    </div>
                    <div v-if="!coupon.appliedCoupon" class="flex gap-2">
                        <input
                            v-model="coupon.code"
                            type="text"
                            placeholder="VERANO25"
                            class="input flex-1 uppercase"
                            @keyup.enter="applyCoupon"
                        />
                        <button
                            @click="applyCoupon"
                            :disabled="coupon.validating || !coupon.code.trim()"
                            class="btn btn-secondary"
                        >
                            {{ coupon.validating ? 'Validando...' : 'Aplicar' }}
                        </button>
                    </div>
                    <div v-else class="flex items-center justify-between p-4 rounded-2xl bg-emerald-50 border border-emerald-200">
                        <div>
                            <p class="font-bold text-emerald-900 text-sm">
                                ✓ Cupón {{ coupon.appliedCoupon.code }} aplicado
                            </p>
                            <p class="text-xs text-emerald-700 mt-0.5">
                                Descuento: -{{ formatPrice(coupon.discount) }}
                            </p>
                        </div>
                        <button
                            @click="coupon.remove"
                            class="text-rose-500 hover:text-rose-700 text-xs font-bold"
                        >
                            Quitar
                        </button>
                    </div>
                    <p v-if="coupon.error" class="text-xs text-rose-500 font-semibold mt-2">
                        {{ coupon.error }}
                    </p>
                </section>

                <!-- Envío -->
                <section class="card p-6">
                    <div class="flex items-center gap-2.5 mb-5">
                        <span class="w-7 h-7 rounded-xl bg-brand-100/70 text-brand-700 flex items-center justify-center font-bold text-xs">1</span>
                        <h2 class="text-sm font-extrabold text-slate-805">Datos</h2>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="label">Dirección</label>
                            <input v-model="address" class="input" placeholder="Av. Corrientes 1234" />
                        </div>
                        <div>
                            <label class="label">Ciudad</label>
                            <input v-model="city" class="input" placeholder="CABA" />
                        </div>
                        <div>
                            <label class="label">Código postal</label>
                            <input v-model="zip" class="input" placeholder="C1043" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label">Teléfono</label>
                            <input v-model="phone" type="tel" class="input" placeholder="+54 11 1234-5678" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label">Notas</label>
                            <textarea v-model="notes" class="input min-h-[70px]" placeholder="Ej: Dejar en portería"></textarea>
                        </div>
                    </div>
                </section>

                <!-- Items -->
                <section class="card p-6">
                    <div class="flex items-center gap-2.5 mb-5">
                        <span class="w-7 h-7 rounded-xl bg-brand-100/70 text-brand-700 flex items-center justify-center font-bold text-xs">2</span>
                        <h2 class="text-sm font-extrabold text-slate-805">Tu pedido</h2>
                    </div>
                    <ul class="divide-y divide-slate-100">
                        <li
                            v-for="item in cart.items"
                            :key="item.id"
                            class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <div class="w-12 h-12 bg-slate-50 border border-slate-100 rounded-xl overflow-hidden flex-shrink-0">
                                <img
                                    v-if="item.product.image && !imageErrors[item.id]"
                                    :src="item.product.image"
                                    class="w-full h-full object-cover"
                                    referrerpolicy="no-referrer"
                                    @error="imageErrors[item.id] = true"
                                />
                                <div v-else class="w-full h-full flex items-center justify-center bg-slate-50 text-slate-350">
                                    <SvgIcon name="box" size="0.95rem" />
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-slate-850 text-xs line-clamp-1">{{ item.product.name }}</p>
                                <p class="text-[10px] text-slate-400 font-semibold mt-1">
                                    Cant: {{ item.qty }} × {{ formatPrice(item.product.final_price ?? item.product.price) }}
                                </p>
                            </div>
                            <span class="font-extrabold text-slate-800 text-xs">
                                {{ formatPrice(item.subtotal) }}
                            </span>
                        </li>
                    </ul>
                </section>
            </div>

            <aside class="lg:sticky lg:top-24 lg:self-start space-y-4">
                <!-- Mínimo de compra (barra de progreso) -->
                <div class="card p-6">
                    <div class="flex items-center justify-between mb-2.5">
                        <span class="text-xs font-bold text-slate-700">
                            🛒 Compra mínima: {{ formatPrice(cart.minPurchase) }}
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
                        ✓ ¡Mínimo alcanzado! Podés confirmar tu pedido.
                    </p>
                </div>

                <div class="card p-6 space-y-4">
                    <h2 class="font-bold text-slate-850 text-sm tracking-tight border-b border-slate-100 pb-2">Total a pagar</h2>
                    <div class="space-y-3.5 text-xs font-semibold text-slate-500">
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span class="font-bold text-slate-800">{{ formatPrice(cart.total) }}</span>
                        </div>
                        <div v-if="coupon.appliedCoupon" class="flex justify-between text-emerald-600">
                            <span>Descuento ({{ coupon.appliedCoupon.code }})</span>
                            <span class="font-bold">-{{ formatPrice(coupon.discount) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Envío</span>
                            <span class="text-emerald-700">Gratis</span>
                        </div>
                    </div>
                    <hr class="border-slate-100" />
                    <div class="flex justify-between items-baseline">
                        <span class="font-bold text-slate-800 text-xs">Total</span>
                        <span class="text-2xl font-black text-slate-805">
                            {{ formatPrice(coupon.appliedCoupon ? coupon.finalSubtotal : cart.total) }}
                        </span>
                    </div>

                    <p v-if="orders.error" class="text-xs text-rose-500 font-semibold text-center">
                        {{ orders.error }}
                    </p>

                    <button @click="placeOrder" :disabled="!valid || submitting" class="btn btn-primary btn-lg w-full mt-4 disabled:opacity-50 disabled:cursor-not-allowed">
                        <SvgIcon name="check" size="0.95rem" />
                        <span>{{ submitting ? 'Procesando...' : 'Confirmar pedido' }}</span>
                    </button>

                    <div class="text-[10px] text-slate-400 font-semibold text-center flex items-center justify-center gap-1 mt-4">
                        <SvgIcon name="shield" size="0.75rem" class="text-emerald-600" />
                        <span>Canales encriptados SSL de alta seguridad</span>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
