<script setup lang="ts">
interface Crumb {
    label: string;
    to?: string | object;
}

defineProps<{
    items: Crumb[];
}>();
</script>

<template>
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6 animate-fade-in">
        <template v-for="(item, i) in items" :key="i">
            <router-link
                v-if="item.to && i < items.length - 1"
                :to="item.to"
                class="hover:text-brand-600 transition-colors"
            >
                {{ item.label }}
            </router-link>
            <span
                v-else
                :class="[
                    i === items.length - 1
                        ? 'text-gray-900 font-medium'
                        : '',
                ]"
            >
                {{ item.label }}
            </span>
            <span
                v-if="i < items.length - 1"
                class="text-gray-300"
                aria-hidden="true"
            >
                /
            </span>
        </template>
    </nav>
</template>