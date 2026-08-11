<script setup lang="ts">
import { ref } from 'vue';

const year = new Date().getFullYear();
const email = ref('');
const subscribed = ref(false);

function subscribe() {
    if (!email.value.trim()) return;
    subscribed.value = true;
    email.value = '';
    setTimeout(() => (subscribed.value = false), 4000);
}
</script>

<template>
    <footer class="mt-16 bg-inverse-surface border-t border-white/10 text-slate-300">
        <!-- Main footer -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid grid-cols-1 md:grid-cols-4 gap-8">
            <div class="flex flex-col gap-4 text-white">
                <router-link :to="{ name: 'home' }" class="text-xl font-extrabold tracking-tight">
                    Tecno-Rexs
                </router-link>
                <p class="text-sm text-slate-400 leading-relaxed max-w-xs mt-2">
                    Tu tienda online de tecnología. Productos exclusivos importados de forma directa.
                </p>
            </div>

            <div class="flex flex-col gap-3 text-white">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Secciones</h4>
                <router-link :to="{ name: 'home' }" class="text-sm text-slate-400 hover:text-brand-300 transition-colors">
                    Inicio
                </router-link>
                <router-link :to="{ name: 'products' }" class="text-sm text-slate-400 hover:text-brand-300 transition-colors">
                    Productos
                </router-link>
                <router-link :to="{ name: 'cart' }" class="text-sm text-slate-400 hover:text-brand-300 transition-colors">
                    Carrito
                </router-link>
            </div>

            <div class="flex flex-col gap-3 text-white">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Ayuda</h4>
                <a href="#" class="text-sm text-slate-400 hover:text-brand-300 transition-colors">Centro de Ayuda</a>
                <a href="#" class="text-sm text-slate-400 hover:text-brand-300 transition-colors">Envíos</a>
                <a href="#" class="text-sm text-slate-400 hover:text-brand-300 transition-colors">Devoluciones</a>
            </div>

            <div class="flex flex-col gap-3 text-white">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Legal</h4>
                <a href="#" class="text-sm text-slate-400 hover:text-brand-300 transition-colors">Términos</a>
                <a href="#" class="text-sm text-slate-400 hover:text-brand-300 transition-colors">Contacto</a>
            </div>
        </div>

        <div class="border-t border-white/10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center text-xs text-slate-500">
                <p>© {{ year }} Tecno-Rexs. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>
</template>
