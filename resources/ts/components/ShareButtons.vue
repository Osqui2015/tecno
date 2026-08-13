<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import SvgIcon from './SvgIcon.vue';

/**
 * Componente reutilizable para compartir un enlace.
 *
 * Props:
 *  - url    : enlace a compartir. Si no se pasa, usa window.location.href
 *  - title  : título del recurso. Si no se pasa, usa document.title
 *  - text   : texto opcional (se antepone al link en WhatsApp / X)
 *  - label  : texto del botón principal (default "Compartir")
 *  - variant: 'inline' (default, botón amplio) | 'icon' (solo ícono circular)
 *  - align  : 'right' (default) | 'left' — alinea el popover
 */
const props = withDefaults(
    defineProps<{
        url?: string;
        title?: string;
        text?: string;
        label?: string;
        variant?: 'inline' | 'icon';
        align?: 'right' | 'left';
    }>(),
    {
        label: 'Compartir',
        variant: 'inline',
        align: 'right',
    },
);

const open = ref(false);
const copied = ref(false);
const rootRef = ref<HTMLElement | null>(null);

const shareUrl = computed(() => {
    if (props.url) return props.url;
    if (typeof window !== 'undefined') return window.location.href;
    return '';
});

const shareTitle = computed(() => props.title || (typeof document !== 'undefined' ? document.title : ''));
const shareText = computed(() => props.text || '');

// Mensaje completo (texto + URL) usado en WhatsApp / X
const fullMessage = computed(() => {
    const t = shareText.value ? `${shareText.value}\n` : '';
    return `${t}${shareUrl.value}`;
});

const encodedMessage = computed(() => encodeURIComponent(fullMessage.value));
const encodedUrl = computed(() => encodeURIComponent(shareUrl.value));

const whatsappUrl = computed(() => `https://wa.me/?text=${encodedMessage.value}`);
const facebookUrl = computed(
    () => `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl.value}`,
);
const twitterUrl = computed(
    () => `https://twitter.com/intent/tweet?text=${encodedMessage.value}`,
);

function toggle() {
    open.value = !open.value;
    copied.value = false;
}

function close() {
    open.value = false;
}

async function copyLink() {
    const url = shareUrl.value;
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(url);
        } else {
            // Fallback para navegadores sin Clipboard API
            const ta = document.createElement('textarea');
            ta.value = url;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch (e) {
        console.error('No se pudo copiar el enlace', e);
    }
}

// Instagram no expone un share intent público: copiamos el enlace y abrimos IG
async function shareInstagram() {
    await copyLink();
    window.open('https://www.instagram.com/', '_blank', 'noopener,noreferrer');
}

// Web Share API nativo (móvil la mayoría de las veces)
const canNativeShare =
    typeof navigator !== 'undefined' && typeof navigator.share === 'function';

async function nativeShare() {
    if (!canNativeShare) return;
    try {
        await navigator.share({
            title: shareTitle.value,
            text: shareText.value || undefined,
            url: shareUrl.value,
        });
        close();
    } catch {
        // El usuario canceló o no se pudo compartir — no hacemos nada
    }
}

function onDocumentClick(e: MouseEvent) {
    if (!rootRef.value) return;
    if (!rootRef.value.contains(e.target as Node)) close();
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') close();
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="rootRef" class="relative inline-block">
        <!-- Botón gatillo -->
        <button
            v-if="variant === 'inline'"
            type="button"
            @click.stop="toggle"
            class="flex items-center gap-2 px-4 h-12 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition-colors font-bold text-sm shadow-sm cursor-pointer"
            :aria-expanded="open"
            aria-haspopup="true"
            :aria-label="label"
        >
            <SvgIcon name="share" size="1.05rem" />
            <span>{{ label }}</span>
        </button>

        <button
            v-else
            type="button"
            @click.stop="toggle"
            class="w-11 h-11 rounded-full bg-white/95 backdrop-blur-sm border border-white/60 text-slate-700 hover:bg-white hover:text-brand-600 hover:shadow-lg transition-all flex items-center justify-center shadow-md cursor-pointer"
            :aria-expanded="open"
            aria-haspopup="true"
            aria-label="Compartir producto"
        >
            <SvgIcon name="share" size="1.05rem" />
        </button>

        <!-- Popover -->
        <transition name="fade">
            <div
                v-if="open"
                class="absolute z-50 mt-2 w-72 max-w-[calc(100vw-2rem)] rounded-2xl bg-white border border-slate-200 shadow-2xl p-2 animate-scale-in"
                :class="align === 'right' ? 'right-0' : 'left-0'"
                role="menu"
                @click.stop
            >
                <p class="px-3 pt-2 pb-1.5 text-[10px] uppercase tracking-wider font-extrabold text-slate-400">
                    Compartir producto
                </p>

                <!-- WhatsApp -->
                <a
                    :href="whatsappUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    @click="close"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-emerald-50 transition-colors group"
                    role="menuitem"
                >
                    <span
                        class="w-9 h-9 rounded-lg bg-[#25D366] text-white flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform shadow-sm"
                    >
                        <SvgIcon name="whatsapp" size="1.1rem" />
                    </span>
                    <span class="flex flex-col min-w-0">
                        <span class="text-sm font-bold text-slate-700">WhatsApp</span>
                        <span class="text-[10px] text-slate-400 truncate">Enviar a un contacto</span>
                    </span>
                </a>

                <!-- Facebook -->
                <a
                    :href="facebookUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    @click="close"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-blue-50 transition-colors group"
                    role="menuitem"
                >
                    <span
                        class="w-9 h-9 rounded-lg bg-[#1877F2] text-white flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform shadow-sm"
                    >
                        <SvgIcon name="facebook" size="1.05rem" />
                    </span>
                    <span class="flex flex-col min-w-0">
                        <span class="text-sm font-bold text-slate-700">Facebook</span>
                        <span class="text-[10px] text-slate-400 truncate">Compartir en tu muro</span>
                    </span>
                </a>

                <!-- X / Twitter -->
                <a
                    :href="twitterUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    @click="close"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-100 transition-colors group"
                    role="menuitem"
                >
                    <span
                        class="w-9 h-9 rounded-lg bg-black text-white flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform shadow-sm"
                    >
                        <SvgIcon name="twitter" size="1rem" />
                    </span>
                    <span class="flex flex-col min-w-0">
                        <span class="text-sm font-bold text-slate-700">X (Twitter)</span>
                        <span class="text-[10px] text-slate-400 truncate">Publicar un tweet</span>
                    </span>
                </a>

                <!-- Instagram (sin intent público: copiamos y abrimos IG) -->
                <button
                    type="button"
                    @click="shareInstagram"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-pink-50 transition-colors group text-left"
                    role="menuitem"
                >
                    <span
                        class="w-9 h-9 rounded-lg text-white flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform shadow-sm"
                        style="background: linear-gradient(135deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);"
                    >
                        <SvgIcon name="instagram" size="1.05rem" />
                    </span>
                    <span class="flex flex-col min-w-0">
                        <span class="text-sm font-bold text-slate-700">Instagram</span>
                        <span class="text-[10px] text-slate-400 truncate">
                            Copiamos el link para tu historia o DM
                        </span>
                    </span>
                </button>

                <div class="border-t border-slate-100 my-1"></div>

                <!-- Copiar enlace -->
                <button
                    type="button"
                    @click="copyLink"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-50 transition-colors group text-left"
                    role="menuitem"
                >
                    <span
                        class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform shadow-sm"
                        :class="copied ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-600'"
                    >
                        <SvgIcon :name="copied ? 'check' : 'copy'" size="1.05rem" />
                    </span>
                    <span class="flex flex-col min-w-0">
                        <span class="text-sm font-bold" :class="copied ? 'text-emerald-600' : 'text-slate-700'">
                            {{ copied ? '¡Enlace copiado!' : 'Copiar enlace' }}
                        </span>
                        <span class="text-[10px] text-slate-400 truncate max-w-[180px]">
                            {{ shareUrl }}
                        </span>
                    </span>
                </button>

                <!-- Web Share API nativo (móvil) -->
                <button
                    v-if="canNativeShare"
                    type="button"
                    @click="nativeShare"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-brand-50 transition-colors group text-left"
                    role="menuitem"
                >
                    <span
                        class="w-9 h-9 rounded-lg bg-brand-600 text-white flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform shadow-sm"
                    >
                        <SvgIcon name="share" size="1rem" />
                    </span>
                    <span class="flex flex-col min-w-0">
                        <span class="text-sm font-bold text-slate-700">Más opciones</span>
                        <span class="text-[10px] text-slate-400 truncate">
                            Abrir el menú del sistema
                        </span>
                    </span>
                </button>
            </div>
        </transition>
    </div>
</template>
