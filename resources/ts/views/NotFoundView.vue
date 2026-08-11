<script setup lang="ts">
import { useRouter } from 'vue-router';

const router = useRouter();
</script>

<template>
    <div class="text-center py-16 animate-fade-in">
        <div class="relative inline-block">
            <div
                class="text-[10rem] md:text-[14rem] font-black leading-none bg-gradient-to-br from-brand-500 via-violet-500 to-purple-600 bg-clip-text text-transparent animate-float"
            >
                404
            </div>
            <div
                class="absolute inset-0 text-[10rem] md:text-[14rem] font-black leading-none bg-gradient-to-br from-brand-500/20 via-violet-500/20 to-purple-600/20 bg-clip-text text-transparent blur-2xl -z-10"
            >
                404
            </div>
        </div>

        <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-3">
            Página no encontrada
        </h1>
        <p class="text-gray-500 mb-8 max-w-md mx-auto">
            La página que buscás no existe, fue movida o nunca existió. Pero no te preocupes, tenemos muchos productos para vos.
        </p>

        <div class="flex flex-wrap justify-center gap-3">
            <button @click="router.back()" class="btn btn-secondary btn-lg">
                ← Volver
            </button>
            <router-link :to="{ name: 'home' }" class="btn btn-primary btn-lg">
                🏠 Ir al inicio
            </router-link>
        </div>
    </div>
</template>