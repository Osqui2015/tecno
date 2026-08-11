import { defineStore } from 'pinia';
import { ref, computed, watch } from 'vue';
import axios from '@/bootstrap';

export interface Product {
    id: number;
    external_id?: string | null;
    origin?: 'daz' | 'tuc' | 'manual' | null;
    sku?: string | null;
    name: string;
    slug: string;
    description: string;
    price: number | string;
    list_price?: number | null | string;
    cash_price?: number | null | string;
    markup_percent?: number | string | null;
    final_price?: number | string | null;
    stock: number;
    image?: string | null;
    image_url?: string | null;
    brand?: string | null;
    source_url?: string | null;
    categories_external?: string[] | null;
    category_id: number;
    active: boolean;
    is_from_daz?: boolean;
    category?: { id: number; name: string; slug: string };
}

export interface Category {
    id: number;
    name: string;
    slug: string;
    description?: string | null;
}

export const useProductsStore = defineStore('products', () => {
    const products = ref<Product[]>([]);
    const categories = ref<Category[]>([]);
    const currentProduct = ref<Product | null>(null);
    const loading = ref(false);
    const loadingMore = ref(false);
    const error = ref<string | null>(null);
    const currentPage = ref(1);
    const lastPage = ref(1);
    const totalProducts = ref(0);
    const hasMore = computed(() => currentPage.value < lastPage.value);
    const filterCategory = ref<number | null>(null);
    const filterBrand = ref<string | null>(null);
    const filterSource = ref<'all' | 'daz' | 'local'>('all');
    const searchQuery = ref('');

    const filteredProducts = computed(() => {
        let result = products.value;
        if (filterCategory.value) {
            result = result.filter((p) => p.category_id === filterCategory.value);
        }
        if (filterBrand.value) {
            result = result.filter((p) => p.brand === filterBrand.value);
        }
        if (filterSource.value === 'daz') {
            result = result.filter((p) => !!p.external_id);
        } else if (filterSource.value === 'local') {
            result = result.filter((p) => !p.external_id);
        }
        if (searchQuery.value.trim()) {
            const q = searchQuery.value.toLowerCase();
            result = result.filter(
                (p) =>
                    p.name.toLowerCase().includes(q) ||
                    p.description?.toLowerCase().includes(q) ||
                    p.brand?.toLowerCase().includes(q) ||
                    p.sku?.toLowerCase().includes(q)
            );
        }
        return result;
    });

    const brands = computed(() => {
        const set = new Set<string>();
        products.value.forEach((p) => {
            if (p.brand) {
                set.add(p.brand);
            }
        });
        return Array.from(set).sort();
    });

    async function fetchProducts() {
        loading.value = true;
        error.value = null;
        try {
            const params: any = { page: 1, per_page: 60 };
            if (searchQuery.value.trim()) {
                params.search = searchQuery.value.trim();
            }
            if (filterCategory.value) {
                params.category_id = filterCategory.value;
            }
            const { data } = await axios.get('/products', { params });
            applyPageResponse(data, /*reset=*/ true);
        } catch (e: any) {
            error.value = e.response?.data?.message || 'Error al cargar productos';
        } finally {
            loading.value = false;
        }
    }

    async function searchProducts(q: string) {
        if (!q.trim()) {
            searchQuery.value = '';
            await fetchProducts();
            return;
        }
        loading.value = true;
        error.value = null;
        try {
            const params: any = { page: 1, per_page: 60, search: q.trim() };
            if (filterCategory.value) {
                params.category_id = filterCategory.value;
            }
            const { data } = await axios.get('/products/searchproduc', { params });
            applyPageResponse(data, /*reset=*/ true);
        } catch (e: any) {
            error.value = 'Error al buscar productos';
        } finally {
            loading.value = false;
        }
    }

    async function fetchMore() {
        if (!hasMore.value || loadingMore.value || loading.value) return;
        const nextPage = currentPage.value + 1;
        loadingMore.value = true;
        try {
            const params: any = { page: nextPage, per_page: 60 };
            if (searchQuery.value.trim()) {
                params.search = searchQuery.value.trim();
            }
            if (filterCategory.value) {
                params.category_id = filterCategory.value;
            }
            const { data } = await axios.get('/products', { params });
            applyPageResponse(data, /*reset=*/ false);
        } catch (e: any) {
            error.value = 'Error al cargar más productos';
        } finally {
            loadingMore.value = false;
        }
    }

    function applyPageResponse(payload: any, reset: boolean) {
        // El backend puede responder como array plano o como paginador Laravel.
        if (Array.isArray(payload)) {
            if (reset) products.value = payload;
            else products.value.push(...payload);
            lastPage.value = 1;
            totalProducts.value = payload.length;
            return;
        }
        const items = payload?.data ?? [];
        if (reset) {
            products.value = items;
        } else {
            products.value.push(...items);
        }
        currentPage.value = payload?.current_page ?? currentPage.value;
        lastPage.value    = payload?.last_page ?? lastPage.value;
        totalProducts.value = payload?.total ?? products.value.length;
    }

    async function refresh() {
        await fetchProducts();
    }

    async function fetchProduct(id: number) {
        loading.value = true;
        try {
            const { data } = await axios.get(`/products/${id}`);
            currentProduct.value = data;
        } catch (e: any) {
            error.value = 'Producto no encontrado';
            currentProduct.value = null;
        } finally {
            loading.value = false;
        }
    }

    async function fetchCategories() {
        try {
            const { data } = await axios.get('/categories');
            categories.value = Array.isArray(data) ? data : data.data ?? [];
        } catch (e) {
            console.error('Error cargando categorías', e);
        }
    }

    function setCategory(id: number | null) {
        filterCategory.value = id;
        fetchProducts();
    }

    function setBrand(brand: string | null) {
        filterBrand.value = brand;
        fetchProducts();
    }

    function setSource(src: 'all' | 'daz' | 'local') {
        filterSource.value = src;
        fetchProducts();
    }

    let searchDebounceTimeout: any = null;
    function setSearch(q: string) {
        searchQuery.value = q;
        if (searchDebounceTimeout) clearTimeout(searchDebounceTimeout);
        searchDebounceTimeout = setTimeout(() => {
            searchProducts(q);
        }, 300);
    }

    return {
        products,
        categories,
        currentProduct,
        loading,
        loadingMore,
        error,
        currentPage,
        lastPage,
        totalProducts,
        hasMore,
        filterCategory,
        filterBrand,
        filterSource,
        searchQuery,
        filteredProducts,
        brands,
        fetchProducts,
        searchProducts,
        fetchMore,
        refresh,
        fetchProduct,
        fetchCategories,
        setCategory,
        setBrand,
        setSource,
        setSearch,
    };
});
