import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';

const routes: RouteRecordRaw[] = [
    {
        path: '/',
        name: 'home',
        component: () => import('@/views/HomeView.vue'),
    },
    {
        path: '/productos',
        name: 'products',
        component: () => import('@/views/ProductsView.vue'),
    },
    {
        path: '/productos/:id',
        name: 'product-detail',
        component: () => import('@/views/ProductDetailView.vue'),
        props: true,
    },
    {
        path: '/categorias/:slug',
        name: 'products-by-category',
        component: () => import('@/views/ProductsView.vue'),
        props: true,
    },
    {
        path: '/carrito',
        name: 'cart',
        component: () => import('@/views/CartView.vue'),
    },
    {
        path: '/checkout',
        name: 'checkout',
        component: () => import('@/views/CheckoutView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('@/views/LoginView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('@/views/RegisterView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/perfil',
        name: 'profile',
        component: () => import('@/views/ProfileView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/perfil/2fa',
        name: 'two-factor-setup',
        component: () => import('@/views/TwoFactorSetupView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/mis-pedidos',
        name: 'my-orders',
        component: () => import('@/views/MyOrdersView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/favoritos',
        name: 'wishlist',
        component: () => import('@/views/WishlistView.vue'),
        meta: { requiresAuth: true },
    },

    // ============== RUTAS ADMIN ==============
    {
        path: '/admin',
        component: () => import('@/views/admin/AdminLayout.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
        children: [
            {
                path: '',
                name: 'admin-dashboard',
                component: () => import('@/views/admin/AdminDashboardView.vue'),
            },
            {
                path: 'productos',
                name: 'admin-products',
                component: () => import('@/views/admin/AdminProductsView.vue'),
                meta: { requiresProducts: true },
            },
            {
                path: 'productos/:id',
                name: 'admin-product-edit',
                component: () => import('@/views/admin/AdminProductEditView.vue'),
                props: true,
                meta: { requiresProducts: true },
            },
            {
                path: 'cupones',
                name: 'admin-coupons',
                component: () => import('@/views/admin/AdminCouponsView.vue'),
            },
            {
                path: 'usuarios',
                name: 'admin-users',
                component: () => import('@/views/admin/AdminUsersView.vue'),
            },
            {
                path: 'pedidos',
                name: 'admin-orders',
                component: () => import('@/views/admin/AdminOrdersView.vue'),
                meta: { requiresOrders: true },
            },
            {
                path: 'pedidos/:id',
                name: 'admin-order-detail',
                component: () => import('@/views/admin/AdminOrderDetailView.vue'),
                props: true,
                meta: { requiresOrders: true },
            },
        ],
    },

    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/views/NotFoundView.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 };
    },
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    const cart = useCartStore();

    // Cargar info del user si está autenticado pero no tenemos role
    if (auth.isAuthenticated && auth.user && !auth.user.role) {
        await auth.fetchUser();
    }

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.requiresAdmin && !auth.isAdmin) {
        if (auth.isAuthenticated) {
            return { name: 'home' };
        }
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    // Permisos granulares
    if (to.meta.requiresProducts && !auth.canManageProducts) {
        return { name: 'admin-dashboard' };
    }
    if (to.meta.requiresOrders && !auth.canManageOrders) {
        return { name: 'admin-dashboard' };
    }

    if (to.meta.guestOnly && auth.isAuthenticated) {
        return { name: 'home' };
    }

    if (to.meta.requiresAuth && !cart.loaded) {
        cart.fetchCart();
    }
});

export default router;
