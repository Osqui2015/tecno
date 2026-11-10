<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    show: boolean;
    title: string;
    maxWidth?: string;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const modalRef = ref<HTMLElement | null>(null);

function handleKeyDown(e: KeyboardEvent) {
    if (e.key === 'Escape' && props.show) {
        emit('close');
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
    <teleport to="body">
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                @click.self="emit('close')"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                tabindex="-1"
                ref="modalRef"
            >
                <div
                    class="card p-6 w-full space-y-4 shadow-2xl animate-scale-in"
                    :class="maxWidth || 'max-w-lg'"
                >
                    <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-800 pb-3">
                        <h3 id="modal-title" class="font-bold text-lg text-slate-800 dark:text-slate-100">
                            {{ title }}
                        </h3>
                        <button
                            type="button"
                            @click="emit('close')"
                            class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 rounded-lg transition-colors cursor-pointer"
                            aria-label="Cerrar modal"
                        >
                            ✕
                        </button>
                    </div>

                    <slot />
                </div>
            </div>
        </transition>
    </teleport>
</template>
