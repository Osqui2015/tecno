import { ref, computed } from 'vue';
import axios from '@/bootstrap';

/**
 * Cache simple de la info pública de la tienda (número de WhatsApp, nombre, etc).
 * Se carga la primera vez que algún componente la necesita y se reutiliza
 * en los siguientes (así el botón flotante y el checkout no hacen 2 fetches).
 */
const name = ref<string>('');
const address = ref<string>('');
const phone = ref<string>('');
const whatsappNumber = ref<string>('');
const minPurchase = ref<number>(0);
const loaded = ref(false);
const loading = ref(false);

async function load(force = false) {
    if (loaded.value && !force) return;
    if (loading.value) return;
    loading.value = true;
    try {
        const { data } = await axios.get('/store-info');
        name.value = data.name ?? '';
        address.value = data.address ?? '';
        phone.value = data.phone ?? '';
        whatsappNumber.value = (data.whatsapp_number ?? '').toString();
        minPurchase.value = Number(data.min_purchase ?? 0);
        loaded.value = true;
    } catch (e) {
        // Si falla, dejamos whatsappNumber vacío y los componentes caen a fallback.
        // eslint-disable-next-line no-console
        console.warn('No se pudo cargar /store-info:', e);
    } finally {
        loading.value = false;
    }
}

export function useStoreInfo() {
    return {
        name: computed(() => name.value),
        address: computed(() => address.value),
        phone: computed(() => phone.value),
        whatsappNumber: computed(() => whatsappNumber.value),
        minPurchase: computed(() => minPurchase.value),
        loaded: computed(() => loaded.value),
        loading: computed(() => loading.value),
        load,
    };
}

/**
 * Helper: arma un link wa.me con un mensaje pre-codificado.
 * Devuelve null si no hay número configurado.
 */
export function buildWhatsappUrl(
    number: string,
    message: string
): string | null {
    const clean = (number || '').replace(/\D+/g, '');
    if (!clean) return null;
    return `https://wa.me/${clean}?text=${encodeURIComponent(message)}`;
}
