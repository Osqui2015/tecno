<script setup lang="ts">
import { onMounted, computed } from 'vue';
import { useWishlistStore } from '@/stores/wishlist';
import { useCartStore } from '@/stores/cart';
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';
import PageHeader from '@/components/PageHeader.vue';
import EmptyState from '@/components/EmptyState.vue';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import SvgIcon from '@/components/SvgIcon.vue';

const wishlist = useWishlistStore();
const cart = useCartStore();
const auth = useAuthStore();
const router = useRouter();

onMounted(() => {
    if (auth.isAuthenticated) {
        wishlist.fetchWishlist();
    }
});

const formatPrice = (n: number | string) =>
    '$' + Math.round(Number(n)).toLocaleString('es-AR');

async function moveToCart(productId: number) {
    await cart.add(productId, 1);
    await wishlist.remove(productId);
}

async function browse() {
    if (!auth.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: '/favoritos' } });
    }
}
</script>

<template>
    <div class="space-y-6">
        <PageHeader icon="info" title="Mis favoritos" />

        <div v-if="!auth.isAuthenticated" class="card p-12 text-center max-w-lg mx-auto space-y-4">
            <SvgIcon name="user" size="1.75rem" class="text-slate-400 mx-auto" />
            <p class="font-bold">Iniciá sesión para ver tus favoritos</p>
            <router-link :to="{ name: 'login' }" class="btn btn-primary">Ingresar</router-link>
        </div>

        <LoadingSpinner v-else-if="wishlist.loading && !wishlist.loaded" text="Cargando favoritos..." />

        <EmptyState
            v-else-if="wishlist.items.length === 0"
            icon="info"
            title="No tenés favoritos aún"
            description="Tocá el corazón en cualquier producto para guardarlo acá."
        >
            <template #actions>
                <router-link :to="{ name: 'products' }" class="btn btn-primary">Explorar productos</router-link>
            </template>
        </EmptyState>

        <div v-else class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 animate-fade-in">
            <article
                v-for="item in wishlist.items"
                :key="item.id"
                class="card overflow-hidden hover:shadow-md transition-shadow flex flex-col"
            >
                <router-link
                    :to="{ name: 'product-detail', params: { id: item.product.id } }"
                    class="block aspect-square bg-gradient-to-br from-slate-50 to-slate-100 overflow-hidden"
                >
                    <img
                        v-if="item.product.image"
                        :src="item.product.image"
                        :alt="item.product.name"
                        class="w-full h-full object-cover hover:scale-105 transition-transform duration-500"
                        referrerpolicy="no-referrer"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center text-slate-300">
                        <SvgIcon name="box" size="2rem" />
                    </div>
                </router-link>
                <div class="p-4 flex-1 flex flex-col">
                    <router-link
                        :to="{ name: 'product-detail', params: { id: item.product.id } }"
                        class="font-bold text-sm text-slate-800 line-clamp-2 hover:text-brand-600 transition-colors"
                    >
                        {{ item.product.name }}
                    </router-link>
                    <p class="text-lg font-black text-slate-800 mt-2">
                        {{ formatPrice(item.product.final_price ?? item.product.price) }}
                    </p>
                    <div class="flex gap-1 mt-3 pt-3 border-t border-slate-100">
                        <button
                            @click="moveToCart(item.product.id)"
                            class="btn btn-primary btn-sm flex-1"
                            :disabled="item.product.stock <= 0"
                        >
                            <SvgIcon name="cart" size="0.85rem" />
                            Al carrito
                        </button>
                        <button
                            @click="wishlist.remove(item.product.id)"
                            class="btn btn-ghost btn-sm text-rose-500"
                            aria-label="Quitar"
                        >
                            <SvgIcon name="trash" size="0.85rem" />
                        </button>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>
