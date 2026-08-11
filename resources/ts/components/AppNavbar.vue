<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useCartStore } from '@/stores/cart';
import SvgIcon from '@/components/SvgIcon.vue';

const auth = useAuthStore();
const cart = useCartStore();
const router = useRouter();

const userInitial = computed(() =>
    auth.user?.name ? auth.user.name[0].toUpperCase() : '?'
);

const mobileOpen = ref(false);

onMounted(async () => {
    if (auth.isAuthenticated) {
        await auth.fetchUser();
        if (!cart.loaded) {
            cart.fetchCart();
        }
    }
});

async function handleLogout() {
    // 1. Vaciar el carrito (backend + frontend) ANTES del logout
    //    para no perder el token antes de la request.
    try {
        await cart.clear();
    } catch {
        // si falla (ej: token ya expiró), seguimos igual
        cart.clearLocal();
    }

    // 2. Cerrar sesión en el backend y limpiar token/user locales.
    await auth.logout();

    // 3. Cerrar el menú mobile si estaba abierto.
    mobileOpen.value = false;

    // 4. Redirigir al home. Usamos replace para que "atrás" no vuelva al perfil.
    await router.replace({ name: 'home' });
}

function closeMobile() {
    mobileOpen.value = false;
}
</script>

<template>
    <header
        class="sticky top-0 z-50 bg-inverse-surface border-b border-white/10 shadow-md"
    >
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <router-link
                    :to="{ name: 'home' }"
                    class="flex items-center gap-2.5 group"
                    @click="closeMobile"
                >
                    <span
                        class="w-9 h-9 rounded-xl bg-brand-500 flex items-center justify-center text-white shadow-md group-hover:scale-105 group-hover:rotate-3 transition-all"
                    >
                        <SvgIcon name="cart" size="1.2rem" />
                    </span>
                    <span
                        class="text-xl font-extrabold text-white tracking-tight"
                    >
                        Tecno-Rexs
                    </span>
                </router-link>
 
                <!-- Desktop menu -->
                <div class="hidden md:flex items-center gap-2">
                    <router-link
                        :to="{ name: 'home' }"
                        class="nav-link px-4 py-2 rounded-xl text-sm font-semibold text-white hover:bg-white/5 transition-all duration-200"
                        active-class="!bg-brand-500/20 !border !border-brand-500/30"
                    >
                        Inicio
                    </router-link>
                    <router-link
                        :to="{ name: 'products' }"
                        class="nav-link px-4 py-2 rounded-xl text-sm font-semibold text-white hover:bg-white/5 transition-all duration-200"
                        active-class="!bg-brand-500/20 !border !border-brand-500/30"
                    >
                        Productos
                    </router-link>
                    <!-- Admin link (solo si role=admin) -->
                    <router-link
                        v-if="auth.isAdmin"
                        :to="{ name: 'admin-dashboard' }"
                        class="nav-link px-4 py-2 rounded-xl text-sm font-semibold text-white hover:bg-white/5 transition-all duration-200"
                        active-class="!bg-purple-500/20 !border !border-purple-500/30"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            <SvgIcon name="info" size="0.85rem" />
                            Admin
                        </span>
                    </router-link>
                </div>
 
                <!-- Right side -->
                <div class="flex items-center gap-3">
                    <router-link
                        :to="{ name: 'cart' }"
                        class="nav-link relative p-2 rounded-xl text-white hover:bg-white/5 transition-all duration-200"
                        aria-label="Carrito"
                    >
                        <SvgIcon name="cart" size="1.45rem" />
                        <span
                            v-if="cart.itemsCount > 0"
                            class="absolute -top-0.5 -right-0.5 bg-gradient-to-br from-rose-500 to-pink-600 text-white text-[10px] font-bold rounded-full h-5 min-w-5 px-1 flex items-center justify-center shadow-md animate-scale-in"
                        >
                            {{ cart.itemsCount }}
                        </span>
                    </router-link>
 
                    <template v-if="auth.isAuthenticated">
                        <div class="relative group hidden sm:block">
                            <button
                                class="flex items-center gap-2 p-1 rounded-full hover:bg-white/5 transition-colors"
                            >
                                <span
                                    class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-500 to-purple-600 text-white flex items-center justify-center font-bold shadow-md cursor-pointer"
                                >
                                    {{ userInitial }}
                                </span>
                            </button>
                            <!-- Dropdown menu -->
                            <div
                                class="absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-slate-100/80 py-2.5 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 transform group-hover:translate-y-0 translate-y-2 z-50"
                            >
                                <div class="px-4 py-3 border-b border-slate-50">
                                    <p class="text-sm font-bold text-slate-800 line-clamp-1">
                                        {{ auth.user?.name }}
                                    </p>
                                    <p class="text-xs text-slate-400 truncate mt-0.5">
                                        {{ auth.user?.email }}
                                    </p>
                                    <div v-if="auth.isAdmin" class="mt-1.5 flex flex-wrap gap-1">
                                        <span
                                            v-if="auth.isSuperAdmin"
                                            class="inline-block text-[9px] font-extrabold uppercase tracking-wider text-purple-700 bg-purple-50 px-2 py-0.5 rounded-md"
                                        >
                                            Super Admin
                                        </span>
                                        <span
                                            v-else-if="auth.canManageOrders && !auth.canManageProducts"
                                            class="inline-block text-[9px] font-extrabold uppercase tracking-wider text-blue-700 bg-blue-50 px-2 py-0.5 rounded-md"
                                        >
                                            Admin Pedidos
                                        </span>
                                        <span
                                            v-else-if="auth.canManageProducts && !auth.canManageOrders"
                                            class="inline-block text-[9px] font-extrabold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-md"
                                        >
                                            Admin Productos
                                        </span>
                                        <span
                                            v-else
                                            class="inline-block text-[9px] font-extrabold uppercase tracking-wider text-purple-700 bg-purple-50 px-2 py-0.5 rounded-md"
                                        >
                                            Administrador
                                        </span>
                                    </div>
                                </div>
                                <router-link
                                    :to="{ name: 'profile' }"
                                    class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-brand-50 hover:text-brand-700 transition-colors"
                                >
                                    <SvgIcon name="user" size="1.05rem" class="text-slate-400" />
                                    Mi perfil
                                </router-link>
                                <router-link
                                    :to="{ name: 'my-orders' }"
                                    class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-brand-50 hover:text-brand-700 transition-colors"
                                >
                                    <SvgIcon name="box" size="1.05rem" class="text-slate-400" />
                                    Mis pedidos
                                </router-link>
                                <router-link
                                    :to="{ name: 'wishlist' }"
                                    class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-brand-50 hover:text-brand-700 transition-colors"
                                >
                                    <SvgIcon name="info" size="1.05rem" class="text-slate-400" />
                                    Favoritos
                                </router-link>
                                <router-link
                                    v-if="auth.isAdmin"
                                    :to="{ name: 'admin-dashboard' }"
                                    class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-purple-700 hover:bg-purple-50 transition-colors"
                                >
                                    <SvgIcon name="info" size="1.05rem" class="text-purple-400" />
                                    Panel admin
                                </router-link>
                                <hr class="border-slate-50 my-1" />
                                <button
                                    @click="handleLogout"
                                    class="flex items-center gap-2.5 w-full text-left px-4 py-2.5 text-sm font-bold text-rose-600 hover:bg-rose-50/70 transition-colors"
                                >
                                    <SvgIcon name="logout" size="1.05rem" />
                                    Cerrar sesión
                                </button>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <router-link
                            :to="{ name: 'login' }"
                            class="nav-link hidden sm:inline-flex btn text-white hover:bg-white/5 btn-sm transition-all duration-200"
                        >
                            Ingresar
                        </router-link>
                        <router-link
                            :to="{ name: 'register' }"
                            class="hidden sm:inline-flex btn btn-primary btn-sm"
                        >
                            Registrarse
                        </router-link>
                    </template>
 
                    <!-- Mobile menu button -->
                    <button
                        @click="mobileOpen = !mobileOpen"
                        class="md:hidden p-2 rounded-xl text-white opacity-70 hover:opacity-100 hover:bg-white/5 transition-all duration-200"
                        aria-label="Menú"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-6 w-6"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                v-if="!mobileOpen"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                            <path
                                v-else
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile menu -->
            <transition name="fade">
                <div
                    v-if="mobileOpen"
                    class="md:hidden border-t border-slate-100 py-4 space-y-1 animate-fade-in"
                >
                    <router-link
                        :to="{ name: 'home' }"
                        class="flex items-center gap-2.5 px-4 py-3 rounded-xl text-sm font-semibold text-slate-700 hover:bg-brand-50 hover:text-brand-700 transition"
                        active-class="!bg-brand-50 !text-brand-700"
                        @click="closeMobile"
                    >
                        <SvgIcon name="home" size="1.2rem" />
                        Inicio
                    </router-link>
                    <router-link
                        :to="{ name: 'products' }"
                        class="flex items-center gap-2.5 px-4 py-3 rounded-xl text-sm font-semibold text-slate-700 hover:bg-brand-50 hover:text-brand-700 transition"
                        active-class="!bg-brand-50 !text-brand-700"
                        @click="closeMobile"
                    >
                        <SvgIcon name="box" size="1.2rem" />
                        Productos
                    </router-link>
                    <router-link
                        v-if="auth.isAdmin"
                        :to="{ name: 'admin-dashboard' }"
                        class="flex items-center gap-2.5 px-4 py-3 rounded-xl text-sm font-semibold text-purple-700 hover:bg-purple-50 transition"
                        active-class="!bg-purple-50"
                        @click="closeMobile"
                    >
                        <SvgIcon name="info" size="1.2rem" />
                        Panel admin
                    </router-link>

                    <div v-if="auth.isAuthenticated" class="pt-3 border-t border-slate-100 mt-3">
                        <div class="px-4 py-2 mb-2">
                            <p class="text-sm font-bold text-slate-800">
                                {{ auth.user?.name }}
                            </p>
                            <p class="text-xs text-slate-400 truncate mt-0.5">
                                {{ auth.user?.email }}
                            </p>
                        </div>
                        <router-link
                            :to="{ name: 'profile' }"
                            class="flex items-center gap-2.5 px-4 py-3 rounded-xl text-sm font-medium text-slate-700 hover:bg-brand-50 hover:text-brand-700 transition"
                            @click="closeMobile"
                        >
                            <SvgIcon name="user" size="1.15rem" class="text-slate-400" />
                            Mi perfil
                        </router-link>
                        <router-link
                            :to="{ name: 'my-orders' }"
                            class="flex items-center gap-2.5 px-4 py-3 rounded-xl text-sm font-medium text-slate-700 hover:bg-brand-50 hover:text-brand-700 transition"
                            @click="closeMobile"
                        >
                            <SvgIcon name="box" size="1.15rem" class="text-slate-400" />
                            Mis pedidos
                        </router-link>
                        <button
                            @click="handleLogout"
                            class="flex items-center gap-2.5 w-full text-left px-4 py-3 rounded-xl text-sm font-semibold text-rose-600 hover:bg-rose-50 transition"
                        >
                            <SvgIcon name="logout" size="1.15rem" />
                            Cerrar sesión
                        </button>
                    </div>
                    <div v-else class="pt-3 border-t border-slate-100 mt-3 space-y-2">
                        <router-link
                            :to="{ name: 'login' }"
                            class="btn btn-secondary w-full"
                            @click="closeMobile"
                        >
                            Ingresar
                        </router-link>
                        <router-link
                            :to="{ name: 'register' }"
                            class="btn btn-primary w-full"
                            @click="closeMobile"
                        >
                            Registrarse
                        </router-link>
                    </div>
                </div>
            </transition>
        </nav>
    </header>
</template>
