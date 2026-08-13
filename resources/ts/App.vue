<script setup lang="ts">
import AppNavbar from './components/AppNavbar.vue';
import AppFooter from './components/AppFooter.vue';
import WhatsAppFloatingButton from './components/WhatsAppFloatingButton.vue';
</script>

<template>
    <div class="min-h-screen flex flex-col relative">
        <AppNavbar />
        <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <router-view v-slot="{ Component }">
                <transition name="fade" mode="out-in">
                    <component :is="Component" />
                </transition>
            </router-view>
        </main>
        <AppFooter />
        <WhatsAppFloatingButton />
    </div>
</template>