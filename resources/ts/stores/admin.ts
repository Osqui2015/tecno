import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from '@/bootstrap';

// Reutilizamos la interfaz de Product del store público
import type { Product } from './products';
import type { Order } from './orders';

/**
 * Store para todo lo que hace el admin.
 * - Listar / editar / crear / eliminar productos
 * - Aplicar markup global
 * - Listar / ver / cambiar estado de pedidos
 */

export interface AdminProductFilters {
    search?: string;
    source?: 'daz' | 'tuc' | 'manual';
    category_id?: number;
    active?: boolean;
    stock_status?: 'in_stock' | 'out_of_stock';
    min_stock?: number; // oculta productos con stock menor a este valor
    per_page?: number;
    page?: number;
}

export interface AdminOrderFilters {
    search?: string;
    status?: string;
    source?: 'daz' | 'tuc' | 'manual' | 'mixed';
    user_id?: number;
    date_from?: string;
    date_to?: string;
    per_page?: number;
    page?: number;
}

export const useAdminStore = defineStore('admin', () => {
    // ============== PRODUCTOS ==============
    const products = ref<Product[]>([]);
    const productMeta = ref<any>(null);
    const currentProduct = ref<Product | null>(null);
    const productCategories = ref<{ id: number; name: string }[]>([]);

    // ============== PEDIDOS ==============
    const orders = ref<Order[]>([]);
    const ordersMeta = ref<any>(null);
    const currentOrder = ref<Order | null>(null);

    // ============== ESTADO ==============
    const loading = ref(false);
    const error = ref<string | null>(null);
    const success = ref<string | null>(null);

    function flashError(msg: string) {
        error.value = msg;
        setTimeout(() => (error.value = null), 5000);
    }

    function flashSuccess(msg: string) {
        success.value = msg;
        setTimeout(() => (success.value = null), 3000);
    }

    // ============== PRODUCTOS ==============

    async function fetchProducts(filters: AdminProductFilters = {}) {
        loading.value = true;
        try {
            const { data } = await axios.get('/admin/products', { params: filters });
            products.value = data.data ?? [];
            productMeta.value = {
                total: data.total,
                per_page: data.per_page,
                current_page: data.current_page,
                last_page: data.last_page,
            };
        } catch (e: any) {
            flashError(e.response?.data?.message || 'Error al cargar productos');
        } finally {
            loading.value = false;
        }
    }

    async function fetchProduct(id: number) {
        loading.value = true;
        try {
            const { data } = await axios.get(`/admin/products/${id}`);
            currentProduct.value = data;
        } catch (e: any) {
            flashError('Producto no encontrado');
            currentProduct.value = null;
        } finally {
            loading.value = false;
        }
    }

    async function updateProduct(id: number, payload: Record<string, any>): Promise<boolean> {
        loading.value = true;
        try {
            await axios.patch(`/admin/products/${id}`, payload);
            flashSuccess('Producto actualizado');
            return true;
        } catch (e: any) {
            const errs = e.response?.data?.errors;
            flashError(
                errs ? Object.values(errs).flat()[0] as string : 'Error al actualizar'
            );
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function createProduct(payload: Record<string, any>): Promise<Product | null> {
        loading.value = true;
        try {
            const { data } = await axios.post<Product>('/admin/products', payload);
            flashSuccess('Producto creado');
            return data;
        } catch (e: any) {
            const errs = e.response?.data?.errors;
            flashError(
                errs ? Object.values(errs).flat()[0] as string : 'Error al crear'
            );
            return null;
        } finally {
            loading.value = false;
        }
    }

    async function deleteProduct(id: number): Promise<boolean> {
        loading.value = true;
        try {
            await axios.delete(`/admin/products/${id}`);
            flashSuccess('Producto eliminado');
            return true;
        } catch (e: any) {
            flashError('Error al eliminar');
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function bulkMarkup(payload: {
        percent: number;
        product_ids?: number[];
        source?: 'daz' | 'tuc' | 'manual';
        category_id?: number;
    }): Promise<boolean> {
        loading.value = true;
        try {
            const { data } = await axios.post('/admin/products/bulk-markup', payload);
            flashSuccess(data.message || `Actualizado: ${data.updated} producto(s)`);
            return true;
        } catch (e: any) {
            const errs = e.response?.data?.errors;
            flashError(
                errs ? Object.values(errs).flat()[0] as string : 'Error al aplicar markup'
            );
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function fetchCategories() {
        try {
            const { data } = await axios.get('/categories');
            productCategories.value = data.data ?? data ?? [];
        } catch (e) {
            console.error('Error cargando categorías', e);
        }
    }

    // ============== PEDIDOS ==============

    async function fetchOrders(filters: AdminOrderFilters = {}) {
        loading.value = true;
        try {
            const { data } = await axios.get('/admin/orders', { params: filters });
            orders.value = data.data ?? [];
            ordersMeta.value = {
                total: data.total,
                per_page: data.per_page,
                current_page: data.current_page,
                last_page: data.last_page,
            };
        } catch (e: any) {
            flashError('Error al cargar pedidos');
        } finally {
            loading.value = false;
        }
    }

    async function fetchOrder(id: number) {
        loading.value = true;
        try {
            const { data } = await axios.get<Order>(`/admin/orders/${id}`);
            currentOrder.value = data;
        } catch (e: any) {
            flashError('Pedido no encontrado');
            currentOrder.value = null;
        } finally {
            loading.value = false;
        }
    }

    async function updateOrder(
        id: number,
        payload: { status?: string; admin_notes?: string; return_stock?: boolean }
    ): Promise<boolean> {
        loading.value = true;
        try {
            await axios.patch(`/admin/orders/${id}`, payload);
            flashSuccess('Pedido actualizado');
            await fetchOrder(id);
            return true;
        } catch (e: any) {
            const errs = e.response?.data?.errors;
            flashError(
                errs ? Object.values(errs).flat()[0] as string : 'Error al actualizar'
            );
            return false;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Confirma la disponibilidad de los productos del pedido.
     * Devuelve el mensaje WhatsApp generado y la URL wa.me.
     */
    async function confirmOrderAvailability(
        id: number,
        items: { item_id: number; available: boolean; qty?: number | null }[],
        options: { admin_notes?: string; auto_send?: boolean } = {}
    ): Promise<{
        message: string;
        whatsapp_url: string | null;
        has_phone: boolean;
        order: Order;
    } | null> {
        loading.value = true;
        try {
            const { data } = await axios.post(`/admin/orders/${id}/confirm-availability`, {
                items,
                admin_notes: options.admin_notes,
                auto_send: options.auto_send ?? true,
            });
            flashSuccess('Disponibilidad confirmada');
            // Refrescar el pedido local
            currentOrder.value = data.order;
            return {
                message: data.message,
                whatsapp_url: data.whatsapp_url,
                has_phone: data.has_phone,
                order: data.order,
            };
        } catch (e: any) {
            const errs = e.response?.data?.errors;
            flashError(
                errs ? Object.values(errs).flat()[0] as string : 'Error al confirmar disponibilidad'
            );
            return null;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Preview del mensaje WhatsApp sin guardar cambios.
     */
    async function previewWhatsAppMessage(
        id: number
    ): Promise<{ message: string; whatsapp_url: string | null; has_phone: boolean } | null> {
        try {
            const { data } = await axios.get(`/admin/orders/${id}/whatsapp-preview`);
            return {
                message: data.message,
                whatsapp_url: data.whatsapp_url,
                has_phone: data.has_phone,
            };
        } catch (e: any) {
            flashError('No se pudo obtener el preview');
            return null;
        }
    }

    return {
        // state
        products,
        productMeta,
        currentProduct,
        productCategories,
        orders,
        ordersMeta,
        currentOrder,
        loading,
        error,
        success,
        flashError,
        flashSuccess,

        // products
        fetchProducts,
        fetchProduct,
        updateProduct,
        createProduct,
        deleteProduct,
        bulkMarkup,
        fetchCategories,

        // orders
        fetchOrders,
        fetchOrder,
        updateOrder,
        confirmOrderAvailability,
        previewWhatsAppMessage,
    };
});
