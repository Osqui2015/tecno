import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from '@/bootstrap';

export interface OrderItemProduct {
    id: number;
    name: string;
    slug: string;
    image?: string | null;
    price: number | string;
}

export interface OrderItem {
    id: number;
    product_id: number;
    qty: number;
    price: number | string;
    confirmed_available?: boolean | null;
    confirmed_qty?: number | null;
    product?: OrderItemProduct;
}

export interface Order {
    id: number;
    user_id: number;
    total: number | string;
    status: 'pending' | 'confirmed' | 'preparing' | 'shipped' | 'delivered' | 'cancelled';
    shipping_address?: string;
    customer_name?: string;
    customer_lastname?: string;
    customer_phone?: string;
    customer_address?: string;
    customer_city?: string;
    customer_zip?: string;
    customer_notes?: string | null;
    customer_full_name?: string;
    admin_notes?: string | null;
    confirmed_at?: string | null;
    confirmed_by?: number | null;
    whatsapp_last_sent_at?: string | null;
    created_at: string;
    items: OrderItem[];
    origin_label?: 'daz' | 'tuc' | 'manual' | 'mixed' | 'empty';
    items_count_daz?: number;
    items_count_tuc?: number;
    items_count_manual?: number;
}

export const useOrdersStore = defineStore('orders', () => {
    const orders = ref<Order[]>([]);
    const currentOrder = ref<Order | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    /**
     * Crea el pedido a partir del carrito backend (no envía items).
     * Acepta overrides opcionales de los datos de envío.
     */
    async function checkout(overrides: {
        customer_address?: string;
        customer_city?: string;
        customer_zip?: string;
        customer_phone?: string;
        customer_notes?: string;
    } = {}): Promise<Order | null> {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await axios.post<Order>('/orders', overrides);
            currentOrder.value = data;
            return data;
        } catch (e: any) {
            if (e.response?.data?.errors?.profile) {
                error.value = e.response.data.errors.profile[0];
            } else if (e.response?.data?.errors?.cart) {
                error.value = e.response.data.errors.cart[0];
            } else {
                error.value =
                    e.response?.data?.message || 'Error al procesar el pedido';
            }
            return null;
        } finally {
            loading.value = false;
        }
    }

    async function fetchMyOrders() {
        loading.value = true;
        try {
            const { data } = await axios.get('/orders');
            // Laravel paginate devuelve { data: [], current_page, total, ... }
            orders.value = data.data ?? data ?? [];
        } catch (e: any) {
            error.value = 'Error al cargar tus pedidos';
        } finally {
            loading.value = false;
        }
    }

    async function fetchOrder(id: number): Promise<Order | null> {
        loading.value = true;
        try {
            const { data } = await axios.get<Order>(`/orders/${id}`);
            currentOrder.value = data;
            return data;
        } catch (e: any) {
            error.value = 'Pedido no encontrado';
            return null;
        } finally {
            loading.value = false;
        }
    }

    async function cancel(id: number): Promise<boolean> {
        loading.value = true;
        try {
            await axios.post(`/orders/${id}/cancel`);
            // Refrescar el pedido local si lo tenemos cargado
            if (currentOrder.value?.id === id) {
                await fetchOrder(id);
            }
            // Refrescar el listado
            orders.value = orders.value.map((o) =>
                o.id === id ? { ...o, status: 'cancelled' } : o
            );
            return true;
        } catch (e: any) {
            error.value =
                e.response?.data?.errors?.status?.[0] ||
                e.response?.data?.message ||
                'No se pudo cancelar el pedido';
            return false;
        } finally {
            loading.value = false;
        }
    }

    return {
        orders,
        currentOrder,
        loading,
        error,
        checkout,
        fetchMyOrders,
        fetchOrder,
        cancel,
    };
});
