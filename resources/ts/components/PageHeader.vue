<script setup lang="ts">
import SvgIcon from '@/components/SvgIcon.vue';

defineProps<{
    title: string;
    subtitle?: string;
    icon?: string;
}>();

function isSvgIcon(name?: string) {
    if (!name) return false;
    const knownIcons = [
        'cart', 'truck', 'shield', 'refresh', 'support', 'search', 'logout', 
        'user', 'box', 'star', 'home', 'chevron-right', 'chevron-left', 
        'eye', 'eye-off', 'exclamation', 'check', 'close', 'globe', 
        'credit-card', 'bank', 'cash', 'trash', 'plus', 'minus', 'tag', 'info'
    ];
    return knownIcons.includes(name);
}
</script>

<template>
    <header class="mb-8 animate-fade-in">
        <div class="flex items-center gap-3.5 mb-2">
            <span
                v-if="icon"
                class="w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-600 to-purple-650 flex items-center justify-center text-white text-xl shadow-lg border border-white/10 flex-shrink-0"
            >
                <SvgIcon v-if="isSvgIcon(icon)" :name="icon" size="1.4rem" />
                <span v-else>{{ icon }}</span>
            </span>
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-800 tracking-tight leading-tight">
                    {{ title }}
                </h1>
                <p v-if="subtitle" class="text-xs text-slate-405 mt-1 font-medium">
                    {{ subtitle }}
                </p>
            </div>
        </div>
    </header>
</template>