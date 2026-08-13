import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from '@/bootstrap';

export interface WishlistItem {
    id: number;
    product_id: number;
    product: any;
    created_at: string;
}

export const useWishlistStore = defineStore('wishlist', () => {
    const items = ref<WishlistItem[]>([]);
    const count = ref(0);
    const loading = ref(false);
    const loaded = ref(false);
    const error = ref<string | null>(null);

    const productIds = computed(() => items.value.map((i) => i.product_id));

    function has(productId: number): boolean {
        return productIds.value.includes(productId);
    }

    async function fetchWishlist() {
        const token = localStorage.getItem('auth_token');
        if (!token) {
            items.value = [];
            count.value = 0;
            loaded.value = true;
            return;
        }
        loading.value = true;
        try {
            const { data } = await axios.get('/wishlist');
            const rawItems = data.items ?? [];
            items.value = rawItems.filter((i: any) => i && i.product && i.product.id);
            count.value = data.count ?? 0;
        } catch (e: any) {
            error.value = 'Error al cargar favoritos';
        } finally {
            loading.value = false;
            loaded.value = true;
        }
    }

    async function add(productId: number): Promise<boolean> {
        try {
            await axios.post('/wishlist', { product_id: productId });
            await fetchWishlist();
            return true;
        } catch (e: any) {
            error.value = 'No se pudo agregar a favoritos';
            return false;
        }
    }

    async function remove(productId: number): Promise<boolean> {
        try {
            await axios.delete(`/wishlist/${productId}`);
            items.value = items.value.filter((i) => i.product_id !== productId);
            count.value = items.value.length;
            return true;
        } catch (e: any) {
            error.value = 'No se pudo quitar de favoritos';
            return false;
        }
    }

    async function toggle(productId: number): Promise<boolean> {
        if (has(productId)) {
            return remove(productId);
        }
        return add(productId);
    }

    function clearLocal() {
        items.value = [];
        count.value = 0;
        loaded.value = false;
    }

    return {
        items,
        count,
        loading,
        loaded,
        error,
        productIds,
        has,
        fetchWishlist,
        add,
        remove,
        toggle,
        clearLocal,
    };
});
