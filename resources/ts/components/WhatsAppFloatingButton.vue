<script setup lang="ts">
import SvgIcon from './SvgIcon.vue';

// Número oficial de WhatsApp de la empresa (o link configurable)
const whatsappNumber = '5493815551234';
const defaultMessage = encodeURIComponent('Hola Tecno-Rexs! Quisiera consultar por un producto.');
const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${defaultMessage}`;
</script>

<template>
    <a
        :href="whatsappUrl"
        target="_blank"
        rel="noopener noreferrer"
        class="fixed bottom-6 right-6 z-50 group flex items-center gap-2 bg-[#25D366] text-white p-3.5 rounded-full shadow-[0_4px_24px_rgba(37,211,102,0.45)] hover:shadow-[0_6px_32px_rgba(37,211,102,0.65)] hover:scale-110 active:scale-95 transition-all duration-300 cursor-pointer"
        aria-label="Contactar por WhatsApp"
    >
        <!-- Ícono WhatsApp con efecto pulso -->
        <div class="relative flex items-center justify-center">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-20"></span>
            <SvgIcon name="whatsapp" size="1.6rem" />
        </div>

        <!-- Tooltip flotante al hacer hover -->
        <span class="max-w-0 overflow-hidden group-hover:max-w-xs transition-all duration-300 ease-out whitespace-nowrap text-xs font-extrabold pr-1 text-white opacity-0 group-hover:opacity-100">
            Consultanos por WhatsApp
        </span>
    </a>
</template>
