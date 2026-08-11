<script setup lang="ts">
import SvgIcon from '@/components/SvgIcon.vue';

defineProps<{
    icon?: string;
    title: string;
    description?: string;
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
    <div class="card p-12 text-center animate-fade-in max-w-xl mx-auto">
        <div
            class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gradient-to-br from-brand-50 to-brand-100/70 border border-brand-100 flex items-center justify-center text-3xl text-brand-650 shadow-sm"
        >
            <SvgIcon v-if="isSvgIcon(icon)" :name="icon!" size="1.85rem" />
            <span v-else>{{ icon ?? '📬' }}</span>
        </div>
        <h3 class="text-base font-bold text-slate-800 mb-1.5 leading-snug">{{ title }}</h3>
        <p v-if="description" class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed">
            {{ description }}
        </p>
        <div v-if="$slots.actions" class="mt-6 flex justify-center">
            <slot name="actions" />
        </div>
    </div>
</template>