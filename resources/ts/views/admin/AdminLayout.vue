<script setup lang="ts">
import { onMounted, computed } from 'vue';
import { useAdminStore } from '@/stores/admin';
import { useAuthStore } from '@/stores/auth';
import SvgIcon from '@/components/SvgIcon.vue';

const admin = useAdminStore();
const auth = useAuthStore();

onMounted(() => {
    admin.fetchCategories();
});

const showProductsTab = computed(() => auth.canManageProducts);
const showOrdersTab = computed(() => auth.canManageOrders);
</script>

<template>
    <div class="space-y-6 animate-fade-in">
        <!-- Banner admin -->
        <div class="card overflow-hidden p-0 bg-gradient-to-r from-purple-600 via-violet-600 to-indigo-600 text-white">
            <div class="px-6 py-5 flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-white/15 backdrop-blur flex items-center justify-center shadow-lg">
                    <SvgIcon name="info" size="1.5rem" />
                </div>
                <div>
                    <h1 class="text-lg font-extrabold tracking-tight">Panel de administración</h1>
                    <p class="text-purple-100 text-xs">Gestioná productos, precios y pedidos</p>
                </div>
            </div>
            <!-- Tabs -->
            <nav class="bg-white/10 backdrop-blur px-3 py-1 flex flex-wrap gap-1 border-t border-white/10">
                <router-link
                    :to="{ name: 'admin-dashboard' }"
                    class="px-4 py-2.5 rounded-xl text-xs font-bold text-white/80 hover:bg-white/10 hover:text-white transition-colors"
                    active-class="!bg-white/20 !text-white"
                >
                    Dashboard
                </router-link>
                <router-link
                    v-if="showProductsTab"
                    :to="{ name: 'admin-products' }"
                    class="px-4 py-2.5 rounded-xl text-xs font-bold text-white/80 hover:bg-white/10 hover:text-white transition-colors"
                    active-class="!bg-white/20 !text-white"
                >
                    Productos
                </router-link>
                <router-link
                    v-if="showOrdersTab"
                    :to="{ name: 'admin-orders' }"
                    class="px-4 py-2.5 rounded-xl text-xs font-bold text-white/80 hover:bg-white/10 hover:text-white transition-colors"
                    active-class="!bg-white/20 !text-white"
                >
                    Pedidos
                </router-link>
            </nav>
        </div>

        <!-- Toast global -->
        <transition name="fade">
            <div
                v-if="admin.error"
                class="rounded-2xl bg-rose-50 border border-rose-200 px-5 py-4 flex items-center gap-3 shadow-sm"
            >
                <span class="w-9 h-9 rounded-full bg-rose-500 text-white flex items-center justify-center font-bold">!</span>
                <p class="font-semibold text-rose-900 text-sm">{{ admin.error }}</p>
            </div>
        </transition>
        <transition name="fade">
            <div
                v-if="admin.success"
                class="rounded-2xl bg-emerald-50 border border-emerald-200 px-5 py-4 flex items-center gap-3 shadow-sm"
            >
                <span class="w-9 h-9 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold">✓</span>
                <p class="font-semibold text-emerald-900 text-sm">{{ admin.success }}</p>
            </div>
        </transition>

        <router-view />
    </div>
</template>
