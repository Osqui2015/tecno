<script setup lang="ts">
defineProps<{
    text?: string;
    fullscreen?: boolean;
    small?: boolean;
}>();
</script>

<template>
    <div
        :class="[
            'flex flex-col items-center justify-center gap-3 text-gray-500',
            fullscreen ? 'min-h-[60vh]' : 'py-12',
            small ? 'flex-row py-0' : '',
        ]"
    >
        <div class="relative" :class="small ? 'w-4 h-4' : ''">
            <div
                :class="[
                    'rounded-full border-gray-200',
                    small ? 'w-4 h-4 border-2' : 'w-12 h-12 border-4',
                ]"
            ></div>
            <div
                :class="[
                    'absolute inset-0 rounded-full border-transparent border-t-brand-600 animate-spin',
                    small ? 'w-4 h-4 border-2' : 'w-12 h-12 border-4',
                ]"
            ></div>
        </div>
        <p v-if="text && !small" class="text-sm font-medium">{{ text }}</p>
        <span v-else-if="text" class="text-xs font-medium">{{ text }}</span>
    </div>
</template>