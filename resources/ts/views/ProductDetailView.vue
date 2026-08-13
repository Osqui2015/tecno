<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useProductsStore } from '@/stores/products';
import { useCartStore } from '@/stores/cart';
import { useAuthStore } from '@/stores/auth';
import { useReviewsStore } from '@/stores/reviews';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import Breadcrumb from '@/components/Breadcrumb.vue';
import EmptyState from '@/components/EmptyState.vue';
import ProductCard from '@/components/ProductCard.vue';
import SvgIcon from '@/components/SvgIcon.vue';

const route = useRoute();
const router = useRouter();
const productsStore = useProductsStore();
const cart = useCartStore();
const auth = useAuthStore();
const reviewsStore = useReviewsStore();

const qty = ref(1);
const justAdded = ref(false);
const imageError = ref(false);
const adding = ref(false);

// Reviews state
const showReviewForm = ref(false);
const newRating = ref(5);
const newComment = ref('');
const submittingReview = ref(false);

// Recargar el producto cuando cambia el id de la ruta (Vue reusa el componente
// si navegás entre productos sin pasar por otro lado, así que onMounted no alcanza)
watch(
    () => route.params.id,
    async (rawId) => {
        if (!rawId) return;
        const id = Number(rawId);
        if (!Number.isFinite(id) || id <= 0) return;

        // Reseteamos estado del detalle al cambiar de producto
        qty.value = 1;
        imageError.value = false;
        justAdded.value = false;
        showReviewForm.value = false;
        newComment.value = '';
        newRating.value = 5;

        await productsStore.fetchProduct(id);
        await reviewsStore.fetchReviews(id);
    },
    { immediate: true },
);

function formatDate(d: string) {
    return new Date(d).toLocaleDateString('es-AR', { day: '2-digit', month: 'short', year: 'numeric' });
}

async function submitReview() {
    if (!product.value) return;
    submittingReview.value = true;
    const ok = await reviewsStore.submitReview(product.value.id, {
        rating: newRating.value,
        comment: newComment.value || undefined,
    });
    submittingReview.value = false;
    if (ok) {
        showReviewForm.value = false;
        newComment.value = '';
        newRating.value = 5;
    }
}

async function deleteReview(reviewId: number) {
    if (!confirm('¿Eliminar tu reseña?')) return;
    await reviewsStore.deleteReview(reviewId);
}

const product = computed(() => productsStore.currentProduct);

const displayPrice = computed(() => {
    if (!product.value) return 0;
    const p = product.value as any;
    if (p.final_price != null) return Number(p.final_price);
    const listPrice = Number(product.value.list_price ?? 0);
    return listPrice > 0 ? listPrice : Number(product.value.price ?? 0);
});

const formatPrice = (n: number) =>
    '$' + Math.round(n).toLocaleString('es-AR');

async function addToCart() {
    if (!product.value?.id) return;
    if (!auth.isAuthenticated) {
        router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } });
        return;
    }
    adding.value = true;
    const ok = await cart.add(product.value.id, qty.value);
    adding.value = false;
    if (ok) {
        justAdded.value = true;
        setTimeout(() => (justAdded.value = false), 1500);
    }
}

async function buyNow() {
    await addToCart();
    router.push({ name: 'checkout' });
}

const related = computed(() => {
    const current = product.value;
    if (!current || current.id == null) return [];
    const currentId = current.id;
    const currentCat = current.category_id;
    return (productsStore.products || [])
        // Defensa: descartamos cualquier item malformado (sin id o null)
        .filter((p): p is NonNullable<typeof p> => p != null && p.id != null)
        .filter((p) => p.id !== currentId && p.category_id === currentCat)
        .slice(0, 5);
});

function increment() {
    if (product.value && qty.value < product.value.stock) qty.value++;
}
function decrement() {
    if (qty.value > 1) qty.value--;
}
</script>

<template>
    <LoadingSpinner v-if="productsStore.loading" text="Cargando detalles..." />

    <EmptyState
        v-else-if="!product"
        icon="exclamation"
        title="Producto no encontrado"
        description="El producto que buscás no existe o fue eliminado temporalmente."
    >
        <template #actions>
            <router-link :to="{ name: 'products' }" class="btn btn-primary">
                Ver todos los productos
            </router-link>
        </template>
    </EmptyState>

    <div v-else-if="product" class="space-y-8">
        <Breadcrumb
            :items="[
                { label: 'Inicio', to: { name: 'home' } },
                { label: product.category?.name ?? 'Productos', to: { name: 'products' } },
                { label: product.name ?? '' },
            ]"
        />

        <div class="grid md:grid-cols-2 gap-8 items-start animate-fade-in">
            <div class="space-y-4">
                <div
                    class="aspect-square rounded-3xl overflow-hidden bg-gradient-to-br from-slate-50 to-slate-100/70 border border-slate-200/50 shadow-sm relative group"
                >
                    <img
                        v-if="product.image && !imageError"
                        :src="product.image"
                        :alt="product.name"
                        class="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-500"
                        referrerpolicy="no-referrer"
                        @error="imageError = true"
                    />
                    <div
                        v-else
                        class="w-full h-full flex flex-col items-center justify-center bg-slate-50 text-slate-400 gap-3 p-6 text-center"
                    >
                        <div class="w-16 h-16 rounded-full bg-slate-100/80 flex items-center justify-center text-slate-350 shadow-inner">
                            <SvgIcon name="box" size="2.35rem" />
                        </div>
                        <span class="text-xs uppercase font-extrabold tracking-wider text-slate-400">Sin imagen</span>
                    </div>
                </div>
            </div>

            <div class="flex flex-col space-y-6">
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <span v-if="product.category" class="chip chip-brand">
                            {{ product.category.name }}
                        </span>
                        <span v-if="product.brand" class="chip chip-muted flex items-center gap-1">
                            <SvgIcon name="tag" size="0.7rem" />
                            {{ product.brand }}
                        </span>
                    </div>
                    <h1 class="text-2xl md:text-3xl lg:text-4xl font-extrabold text-slate-800 tracking-tight leading-tight">
                        {{ product.name }}
                    </h1>
                </div>

                <div class="p-5 rounded-2xl bg-gradient-to-br from-slate-50 via-brand-50/5 to-brand-50/15 border border-slate-200/50">
                    <div class="flex items-baseline gap-2.5">
                        <span class="text-3xl md:text-4xl font-black text-slate-800">
                            {{ formatPrice(displayPrice) }}
                        </span>
                    </div>
                </div>

                <div v-if="product.description" class="space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-450">Descripción</h3>
                    <p class="text-sm text-slate-650 leading-relaxed font-medium">
                        {{ product.description }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <template v-if="product.stock > 0">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                        <span class="text-xs font-bold text-emerald-700">
                            {{ product.stock }} unidades con stock inmediato
                        </span>
                    </template>
                    <template v-else>
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                        <span class="text-xs font-bold text-rose-600">Sin stock disponible</span>
                    </template>
                </div>

                <div class="space-y-4 pt-4 border-t border-slate-100">
                    <div class="flex items-center gap-4" v-if="product.stock > 0">
                        <span class="text-sm font-bold text-slate-700">Cantidad:</span>
                        <div class="flex items-center bg-white border border-slate-205 rounded-xl overflow-hidden shadow-sm">
                            <button
                                @click="decrement"
                                :disabled="qty <= 1"
                                class="w-10 h-10 flex items-center justify-center hover:bg-slate-50 disabled:opacity-30 font-bold transition-colors cursor-pointer"
                            >
                                <SvgIcon name="minus" size="0.75rem" />
                            </button>
                            <span class="w-10 text-center font-bold text-sm text-slate-800">{{ qty }}</span>
                            <button
                                @click="increment"
                                :disabled="qty >= product.stock"
                                class="w-10 h-10 flex items-center justify-center hover:bg-slate-50 disabled:opacity-30 font-bold transition-colors cursor-pointer"
                            >
                                <SvgIcon name="plus" size="0.75rem" />
                            </button>
                        </div>
                    </div>

                    <p v-if="cart.error" class="text-xs text-rose-500 font-semibold">{{ cart.error }}</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" v-if="product.stock > 0">
                        <button @click="addToCart" class="btn btn-lg btn-primary shadow-indigo-200/50" :disabled="adding">
                            <SvgIcon :name="justAdded ? 'check' : 'cart'" size="1.1rem" />
                            <span>{{ justAdded ? 'Agregado' : 'Agregar al carrito' }}</span>
                        </button>
                        <button @click="buyNow" class="btn btn-lg btn-secondary">
                            <SvgIcon name="credit-card" size="1.1rem" />
                            <span>Comprar ahora</span>
                        </button>
                    </div>
                    <div v-else>
                        <button disabled class="btn btn-lg btn-primary w-full disabled:opacity-40">
                            Producto agotado
                        </button>
                    </div>
                </div>

                <div class="pt-6 border-t border-slate-100 grid grid-cols-3 gap-3 text-center">
                    <div class="flex flex-col items-center gap-1.5 text-[10px] text-slate-400 font-bold uppercase tracking-wider">
                        <div class="w-9 h-9 rounded-xl bg-slate-50 flex items-center justify-center text-slate-500">
                            <SvgIcon name="truck" size="1.05rem" />
                        </div>
                        <span>Envío Nacional</span>
                    </div>
                    <div class="flex flex-col items-center gap-1.5 text-[10px] text-slate-400 font-bold uppercase tracking-wider">
                        <div class="w-9 h-9 rounded-xl bg-slate-50 flex items-center justify-center text-slate-500">
                            <SvgIcon name="shield" size="1.05rem" />
                        </div>
                        <span>Compra Segura</span>
                    </div>
                    <div class="flex flex-col items-center gap-1.5 text-[10px] text-slate-400 font-bold uppercase tracking-wider">
                        <div class="w-9 h-9 rounded-xl bg-slate-50 flex items-center justify-center text-slate-500">
                            <SvgIcon name="refresh" size="1.05rem" />
                        </div>
                        <span>Garantía 60 días</span>
                    </div>
                </div>
            </div>
        </div>

        <section v-if="related.length > 0" class="pt-8 border-t border-slate-100/90">
            <h2 class="section-title mb-6">También te puede interesar</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                <ProductCard v-for="p in related" :key="p.id" :product="p" />
            </div>
        </section>

        <!-- Reviews Section -->
        <section class="pt-8 border-t border-slate-100/90">
            <div class="flex items-center justify-between mb-6">
                <h2 class="section-title">Reseñas</h2>
                <button
                    v-if="auth.isAuthenticated"
                    @click="showReviewForm = !showReviewForm"
                    class="btn btn-secondary btn-sm"
                >
                    <SvgIcon name="pencil" size="0.85rem" />
                    {{ showReviewForm ? 'Cancelar' : 'Escribir reseña' }}
                </button>
            </div>

            <!-- Rating promedio -->
            <div v-if="reviewsStore.total > 0" class="card p-5 mb-4 flex items-center gap-4">
                <div class="text-center">
                    <p class="text-4xl font-black text-slate-800">{{ reviewsStore.avgRating.toFixed(1) }}</p>
                    <div class="flex text-amber-400 text-sm mt-1 justify-center">
                        <span v-for="n in 5" :key="n">
                            {{ n <= Math.round(reviewsStore.avgRating) ? '★' : '☆' }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">{{ reviewsStore.total }} reseña(s)</p>
                </div>
            </div>

            <!-- Form para nueva reseña -->
            <transition name="fade">
                <form
                    v-if="showReviewForm"
                    @submit.prevent="submitReview"
                    class="card p-5 mb-4 space-y-4"
                >
                    <p class="text-sm font-bold text-slate-700">Tu calificación</p>
                    <div class="flex gap-1 text-3xl">
                        <button
                            v-for="n in 5"
                            :key="n"
                            type="button"
                            @click="newRating = n"
                            class="transition-colors"
                            :class="n <= newRating ? 'text-amber-400' : 'text-slate-300'"
                        >
                            ★
                        </button>
                    </div>
                    <div>
                        <label class="label">Comentario (opcional)</label>
                        <textarea
                            v-model="newComment"
                            class="input min-h-[80px]"
                            placeholder="¿Qué te pareció el producto?"
                        ></textarea>
                    </div>
                    <p v-if="reviewsStore.error" class="text-xs text-rose-500 font-semibold">
                        {{ reviewsStore.error }}
                    </p>
                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary" :disabled="submittingReview">
                            {{ submittingReview ? 'Enviando...' : 'Publicar reseña' }}
                        </button>
                        <button type="button" @click="showReviewForm = false" class="btn btn-ghost">
                            Cancelar
                        </button>
                    </div>
                </form>
            </transition>

            <!-- Lista de reseñas -->
            <LoadingSpinner v-if="reviewsStore.loading" text="Cargando reseñas..." />

            <div v-else-if="!Array.isArray(reviewsStore.reviews) || reviewsStore.reviews.length === 0" class="card p-8 text-center">
                <p class="text-slate-500 font-semibold">Aún no hay reseñas para este producto.</p>
                <p class="text-xs text-slate-400 mt-1">¡Sé el primero en dejar una!</p>
            </div>

            <div v-else class="space-y-3">
                <template v-for="review in reviewsStore.reviews" :key="review?.id ?? Math.random()">
                    <article
                        v-if="review && review.id"
                        class="card p-4"
                    >
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div>
                                <p class="font-bold text-sm text-slate-800">{{ review.user?.name || 'Anónimo' }}</p>
                                <p class="text-[11px] text-slate-400">{{ formatDate(review.created_at) }}</p>
                                <span
                                    v-if="review.is_verified_purchase"
                                    class="inline-block mt-1 text-[9px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded"
                                >
                                    ✓ Compra verificada
                                </span>
                            </div>
                            <div class="flex text-amber-400 text-sm">
                                <span v-for="n in 5" :key="n">{{ n <= (review.rating ?? 5) ? '★' : '☆' }}</span>
                            </div>
                        </div>
                        <p v-if="review.comment" class="text-sm text-slate-700 leading-relaxed">
                            {{ review.comment }}
                        </p>
                        <button
                            v-if="auth.user?.id === review.user_id"
                            @click="deleteReview(review.id)"
                            class="mt-2 text-xs text-rose-500 hover:text-rose-700 font-bold transition-colors"
                        >
                            Eliminar
                        </button>
                    </article>
                </template>
            </div>
        </section>
    </div>
</template>
