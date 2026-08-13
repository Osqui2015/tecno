<script setup lang="ts">
import { computed, ref } from 'vue';
import type { Product } from '@/stores/products';
import { useCartStore } from '@/stores/cart';
import { useAuthStore } from '@/stores/auth';
import { useWishlistStore } from '@/stores/wishlist';
import { useRouter } from 'vue-router';
import SvgIcon from '@/components/SvgIcon.vue';

const props = defineProps<{
    product: Product;
}>();

const cart = useCartStore();
const auth = useAuthStore();
const wishlist = useWishlistStore();
const router = useRouter();

const isFav = computed(() => props.product?.id ? wishlist.has(props.product.id) : false);

async function toggleFav() {
    if (!props.product?.id) return;
    if (!auth.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } });
        return;
    }
    await wishlist.toggle(props.product.id);
}

const displayPrice = computed(() => {
    if (!props.product) return 0;
    const p = props.product as any;
    if (p.final_price != null) return Number(p.final_price);
    const listPrice = Number(props.product.list_price ?? 0);
    return listPrice > 0 ? listPrice : Number(props.product.price ?? 0);
});

const stockStatus = computed(() => {
    const s = props.product?.stock ?? 0;
    if (s <= 0) return { label: 'Sin stock', cls: 'chip-danger' };
    if (s < 10) return { label: `¡Últimas ${s}!`, cls: 'chip-warning' };
    return { label: `${s} disponibles`, cls: 'chip-success' };
});

const formatPrice = (n: number) =>
    '$' + Math.round(n).toLocaleString('es-AR');

const imageError = ref(false);
const added = ref(false);

async function addToCart() {
    if (!props.product?.id) return;
    if (!auth.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } });
        return;
    }
    const ok = await cart.add(props.product.id, 1);
    if (ok) {
        added.value = true;
        setTimeout(() => (added.value = false), 1200);
    }
}
</script>

<template>
    <article
        v-if="product && product.id"
        class="group bg-white rounded-xl border border-slate-200/50 overflow-hidden hover:shadow-[0_8px_30px_rgba(0,0,0,0.04)] hover:border-brand-100/50 transition-all duration-300 flex flex-col relative animate-fade-in"
    >
        <!-- Image -->
        <router-link
            :to="{ name: 'product-detail', params: { id: product.id } }"
            class="block aspect-square bg-[#eff4ff]/30 overflow-hidden relative border-b border-slate-100 p-6"
        >
            <img
                v-if="product.image && !imageError"
                :src="product.image"
                :alt="product.name"
                class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-505"
                loading="lazy"
                referrerpolicy="no-referrer"
                @error="imageError = true"
            />
            <div
                v-else
                class="w-full h-full flex flex-col items-center justify-center bg-slate-50 text-slate-400 gap-2 p-4 text-center"
            >
                <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-350">
                    <span class="material-symbols-outlined text-lg">package_2</span>
                </div>
                <span class="text-[9px] uppercase font-bold tracking-wider text-slate-400">
                    Sin imagen
                </span>
            </div>
            <div
                class="absolute inset-0 bg-gradient-to-t from-slate-900/40 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end justify-center pb-4"
            >
                <span
                    class="bg-white/95 backdrop-blur text-slate-800 text-xs font-bold px-3 py-1.5 rounded-full shadow-lg flex items-center gap-1.5 transform translate-y-2 group-hover:translate-y-0 transition-all duration-300"
                >
                    Ver detalle
                </span>
            </div>

            <!-- Botón favoritos -->
            <button
                @click.prevent="toggleFav"
                class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/95 backdrop-blur shadow-md flex items-center justify-center transition-all hover:scale-110 cursor-pointer"
                :aria-label="isFav ? 'Quitar de favoritos' : 'Agregar a favoritos'"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    class="w-4 h-4 transition-colors"
                    :class="isFav ? 'text-rose-500 fill-current' : 'text-slate-400'"
                    :fill="isFav ? 'currentColor' : 'none'"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
                </svg>
            </button>
        </router-link>

        <!-- Body -->
        <div class="p-5 flex-1 flex flex-col justify-between gap-2 bg-white">
            <div>
                <div class="flex items-center gap-1.5 mb-1.5">
                    <span
                        v-if="product.category"
                        class="text-[9px] font-bold uppercase tracking-wider text-brand-600 bg-brand-50 px-2.5 py-0.5 rounded-full"
                    >
                        {{ product.category.name }}
                    </span>
                    <span
                        v-if="product.brand"
                        class="text-[9px] font-bold uppercase tracking-wider text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-full"
                    >
                        {{ product.brand }}
                    </span>
                </div>

                <h3 class="font-bold text-slate-900 text-sm line-clamp-2 leading-snug group-hover:text-brand-600 transition-colors">
                    {{ product.name }}
                </h3>

                <p
                    v-if="product.description"
                    class="text-[11px] text-slate-400 line-clamp-1 mt-1 font-normal"
                >
                    {{ product.description }}
                </p>
            </div>

            <div class="mt-auto">
                <div class="pt-3 border-t border-slate-100 flex justify-between items-center">
                    <span class="text-lg font-black text-slate-900">
                        {{ formatPrice(displayPrice) }}
                    </span>
                    <span :class="['chip py-0.5 px-2 text-[9px] font-bold rounded-lg', stockStatus.cls]">
                        {{ stockStatus.label }}
                    </span>
                </div>

                <button
                    @click="addToCart"
                    :disabled="product.stock <= 0"
                    class="btn btn-primary w-full mt-3 py-2.5 rounded-lg text-xs font-bold flex items-center justify-center gap-1.5 cursor-pointer"
                >
                    <span class="material-symbols-outlined text-sm">{{ added ? 'check' : 'shopping_cart' }}</span>
                    <span>{{ added ? 'Agregado' : 'Agregar al Carrito' }}</span>
                </button>
            </div>
        </div>
    </article>
</template>
