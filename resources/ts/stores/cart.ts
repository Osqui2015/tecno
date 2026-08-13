import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from '@/bootstrap';

/**
 * Carrito persistido en BACKEND (en la DB).
 *
 * El frontend solo mantiene una copia en memoria (ref) y la sincroniza
 * con el backend en cada acción. NO usa localStorage.
 *
 * Si el usuario cierra la pestaña y vuelve, el carrito sigue ahí.
 * Si cambia de dispositivo, también.
 */

export interface CartProduct {
    id: number;
    name: string;
    slug?: string;
    price: number | string;
    final_price?: number | string;
    stock: number;
    image?: string | null;
    category?: { id: number; name: string; slug: string };
}

export interface CartItem {
    id: number;          // cart_item.id
    product_id: number;
    qty: number;
    product: CartProduct;
    subtotal: number | string;
}

export interface CartResponse {
    items: CartItem[];
    total: number | string;
    items_count: number;
    min_purchase: number;
    remaining: number;
    meets_minimum: boolean;
}

export const useCartStore = defineStore('cart', () => {
    const items = ref<CartItem[]>([]);
    const total = ref<number>(0);
    const itemsCount = ref<number>(0);
    const minPurchase = ref<number>(0);
    const remaining = ref<number>(0);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const loaded = ref(false);

    const isEmpty = computed(() => items.value.length === 0);
    const meetsMinimum = computed(() => {
        if (minPurchase.value <= 0) return true;
        return Number(total.value) >= minPurchase.value;
    });
    const progress = computed(() => {
        if (minPurchase.value <= 0) return 100;
        const pct = (Number(total.value) / minPurchase.value) * 100;
        return Math.min(100, Math.max(0, pct));
    });

    /**
     * Trae el carrito del backend para el usuario autenticado.
     * Si no está autenticado, limpia el carrito local.
     */
    async function fetchCart() {
        const token = localStorage.getItem('auth_token');
        if (!token) {
            clearLocal();
            loaded.value = true;
            return;
        }

        loading.value = true;
        error.value = null;
        try {
            const { data } = await axios.get<CartResponse>('/cart');
            const rawItems = data.items ?? [];
            items.value = rawItems.filter((i: any) => i && i.product && i.product.id);
            total.value = Number(data.total ?? 0);
            itemsCount.value = Number(data.items_count ?? 0);
            minPurchase.value = Number(data.min_purchase ?? 0);
            remaining.value = Number(data.remaining ?? 0);
        } catch (e: any) {
            error.value = e.response?.data?.message || 'Error al cargar el carrito';
        } finally {
            loading.value = false;
            loaded.value = true;
        }
    }

    async function add(productId: number, qty = 1): Promise<boolean> {
        loading.value = true;
        error.value = null;
        try {
            await axios.post('/cart/items', {
                product_id: productId,
                qty,
            });
            await fetchCart();
            return true;
        } catch (e: any) {
            error.value =
                e.response?.data?.qty?.[0] ||
                e.response?.data?.message ||
                'No se pudo agregar al carrito';
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function updateQty(itemId: number, qty: number): Promise<boolean> {
        // qty=0 NO borra el item: lo deja "marcado para quitar".
        // El borrado real sólo ocurre con remove() (botón Quitar).
        const safeQty = Math.max(0, Math.min(999, Math.floor(qty)));
        loading.value = true;
        error.value = null;
        try {
            await axios.patch(`/cart/items/${itemId}`, { qty: safeQty });
            await fetchCart();
            return true;
        } catch (e: any) {
            error.value =
                e.response?.data?.qty?.[0] ||
                e.response?.data?.message ||
                'No se pudo actualizar la cantidad';
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function remove(itemId: number): Promise<boolean> {
        loading.value = true;
        try {
            await axios.delete(`/cart/items/${itemId}`);
            await fetchCart();
            return true;
        } catch (e: any) {
            error.value = 'No se pudo quitar el producto';
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function clear(): Promise<boolean> {
        loading.value = true;
        try {
            await axios.delete('/cart');
            clearLocal();
            return true;
        } catch (e: any) {
            error.value = 'No se pudo vaciar el carrito';
            return false;
        } finally {
            loading.value = false;
        }
    }

    function clearLocal() {
        items.value = [];
        total.value = 0;
        itemsCount.value = 0;
        minPurchase.value = 0;
        remaining.value = 0;
    }

    return {
        items,
        total,
        itemsCount,
        minPurchase,
        remaining,
        loading,
        error,
        loaded,
        isEmpty,
        meetsMinimum,
        progress,
        fetchCart,
        add,
        updateQty,
        remove,
        clear,
        clearLocal,
    };
});
