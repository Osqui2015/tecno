import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from '@/bootstrap';

export interface Review {
    id: number;
    user_id: number;
    product_id: number;
    rating: number;
    comment: string | null;
    is_verified_purchase: boolean;
    created_at: string;
    user?: { id: number; name: string };
}

export const useReviewsStore = defineStore('reviews', () => {
    const reviews = ref<Review[]>([]);
    const total = ref(0);
    const avgRating = ref(0);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const canReview = ref(false); // se setea según si compró

    async function fetchReviews(productId: number) {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await axios.get(`/products/${productId}/reviews`);
            const rawList = data.reviews?.data ?? (Array.isArray(data.reviews) ? data.reviews : (Array.isArray(data.data) ? data.data : (Array.isArray(data) ? data : [])));
            reviews.value = Array.isArray(rawList) ? rawList.filter((r: any) => r && r.id != null) : [];
            total.value = Number(data.total ?? reviews.value.length);
            avgRating.value = Number(data.avg_rating ?? 0);
        } catch (e: any) {
            error.value = 'No se pudieron cargar las reseñas';
            reviews.value = [];
        } finally {
            loading.value = false;
        }
    }

    async function submitReview(productId: number, payload: { rating: number; comment?: string }) {
        try {
            const { data } = await axios.post(`/products/${productId}/reviews`, payload);
            reviews.value = [data.review, ...reviews.value];
            total.value++;
            return true;
        } catch (e: any) {
            error.value = e.response?.data?.errors?.review?.[0]
                || e.response?.data?.message
                || 'No se pudo enviar la reseña';
            return false;
        }
    }

    async function deleteReview(reviewId: number) {
        try {
            await axios.delete(`/reviews/${reviewId}`);
            reviews.value = reviews.value.filter((r) => r.id !== reviewId);
            total.value = Math.max(0, total.value - 1);
            return true;
        } catch (e: any) {
            error.value = 'No se pudo eliminar la reseña';
            return false;
        }
    }

    return {
        reviews,
        total,
        avgRating,
        loading,
        error,
        canReview,
        fetchReviews,
        submitReview,
        deleteReview,
    };
});
