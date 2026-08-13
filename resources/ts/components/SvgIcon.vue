<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        name: string;
        size?: string | number;
    }>(),
    {
        size: '1.25rem',
    }
);

const iconPaths = computed(() => {
    switch (props.name) {
        case 'cart':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />',
            };
        case 'truck':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10M13 16h6m-6 0H9m10 0V9h-6M19 9l3 3v4h-3" />',
            };
        case 'shield':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />',
            };
        case 'refresh':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 7.89M9 11l3-3 3 3m-3-3v12" />',
            };
        case 'support':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />',
            };
        case 'search':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />',
            };
        case 'logout':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />',
            };
        case 'user':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />',
            };
        case 'box':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />',
            };
        case 'star':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.907c.969 0 1.371 1.24.588 1.81l-3.97 2.883a1 1 0 00-.364 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.971-2.883a1 1 0 00-1.18 0l-3.97 2.883c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h4.906a1 1 0 00.951-.69l1.519-4.674z" />',
            };
        case 'home':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />',
            };
        case 'chevron-right':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />',
            };
        case 'chevron-left':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />',
            };
        case 'eye':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />',
            };
        case 'eye-off':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />',
            };
        case 'exclamation':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />',
            };
        case 'check':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7" />',
            };
        case 'close':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12" />',
            };
        case 'globe':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />',
            };
        case 'credit-card':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />',
            };
        case 'bank':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />',
            };
        case 'cash':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />',
            };
        case 'trash':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />',
            };
        case 'plus':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />',
            };
        case 'minus':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />',
            };
        case 'tag':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h.01M6 20h.01M9 16H8M6 8a4 4 0 018-8h2a4 4 0 014 4v2a4 4 0 01-4 4h-2a4 4 0 01-8-8zM19.5 9.5L13 16l-4-4 6.5-6.5" />',
            };
        case 'info':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
            };
        case 'cog':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />',
            };
        case 'pencil':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />',
            };
        case 'whatsapp':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 21l1.5-4.5A8.5 8.5 0 1112 20.5a8.46 8.46 0 01-4.5-1.3L3 21z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8.5 9.5c.5 2 2.5 4 4.5 4.5l1.5-1.5c.3-.3.7-.4 1-.2l1.8.8c.4.2.6.6.4 1l-.6 1.5c-.2.4-.5.6-.9.6-4.5-.5-8-4-8.5-8.5 0-.4.2-.7.6-.9l1.5-.6c.4-.2.8 0 1 .4l.8 1.8c.2.3.1.7-.2 1l-1.4 1.3z" />',
            };
        case 'copy':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />',
            };
        case 'share':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />',
            };
        case 'instagram':
            return {
                viewBox: '0 0 24 24',
                path: '<rect x="2.5" y="2.5" width="19" height="19" rx="5" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" /><circle cx="12" cy="12" r="4.5" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" /><circle cx="17.5" cy="6.5" r="1.3" fill="currentColor" stroke="none" />',
            };
        case 'facebook':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z" />',
            };
        case 'twitter':
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231 5.45-6.231zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z" />',
            };
        default:
            return {
                viewBox: '0 0 24 24',
                path: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
            };
    }
});
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        :viewBox="iconPaths.viewBox"
        :style="{ width: size, height: size, stroke: 'currentColor' }"
        v-html="iconPaths.path"
    ></svg>
</template>
