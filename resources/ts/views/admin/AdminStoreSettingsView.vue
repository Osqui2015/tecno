<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import axios from '@/bootstrap';
import { useAdminStore } from '@/stores/admin';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import SvgIcon from '@/components/SvgIcon.vue';

interface StoreInfo {
    id: number;
    name: string;
    address: string | null;
    phone: string | null;
    whatsapp_number: string | null;
    instagram_url: string | null;
    facebook_url: string | null;
    tiktok_url: string | null;
    email_contact: string | null;
    schedule: string | null;
    short_description: string | null;
    min_purchase: number | string;
}

const admin = useAdminStore();

const form = reactive({
    name: '',
    address: '',
    phone: '',
    whatsapp_number: '',
    instagram_url: '',
    facebook_url: '',
    tiktok_url: '',
    email_contact: '',
    schedule: '',
    short_description: '',
    min_purchase: 0,
});

const loading = ref(false);
const saving = ref(false);
const formErrors = ref<Record<string, string[]>>({});

async function loadSettings() {
    loading.value = true;
    try {
        const { data } = await axios.get<StoreInfo>('/admin/store-info');
        form.name = data.name ?? '';
        form.address = data.address ?? '';
        form.phone = data.phone ?? '';
        form.whatsapp_number = data.whatsapp_number ?? '';
        form.instagram_url = data.instagram_url ?? '';
        form.facebook_url = data.facebook_url ?? '';
        form.tiktok_url = data.tiktok_url ?? '';
        form.email_contact = data.email_contact ?? '';
        form.schedule = data.schedule ?? '';
        form.short_description = data.short_description ?? '';
        form.min_purchase = Number(data.min_purchase ?? 0);
    } catch (err: any) {
        admin.flashError('No se pudo cargar la configuración de la tienda');
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    formErrors.value = {};
    try {
        await axios.patch('/admin/store-info', { ...form });
        admin.flashSuccess('Configuración guardada');
    } catch (err: any) {
        if (err.response?.status === 422) {
            formErrors.value = err.response.data.errors || {};
            admin.flashError('Revisá los campos marcados');
        } else {
            admin.flashError('No se pudo guardar la configuración');
        }
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    loadSettings();
});
</script>

<template>
    <div class="space-y-5">
        <!-- Encabezado -->
        <div class="card p-5">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-2xl bg-purple-100 text-purple-600 flex items-center justify-center flex-shrink-0">
                    <SvgIcon name="cog" size="1.4rem" />
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">Configuración de la tienda</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Estos datos se usan en el botón flotante de WhatsApp, en los mensajes automáticos
                        al cliente y en el footer del sitio.
                    </p>
                </div>
            </div>
        </div>

        <div v-if="loading" class="card p-12 flex justify-center">
            <LoadingSpinner />
        </div>

        <form v-else @submit.prevent="save" class="space-y-5">
            <!-- Datos básicos -->
            <div class="card p-6 space-y-4">
                <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <span class="w-7 h-7 rounded-xl bg-brand-100/70 text-brand-700 flex items-center justify-center">
                        <SvgIcon name="info" size="0.9rem" />
                    </span>
                    <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100">Datos básicos</h3>
                </div>

                <div>
                    <label class="label">Nombre de la tienda</label>
                    <input v-model="form.name" type="text" class="input" placeholder="Tecno-Rexs" />
                    <p v-if="formErrors.name" class="text-xs text-rose-500 mt-1">{{ formErrors.name[0] }}</p>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Dirección</label>
                        <input v-model="form.address" type="text" class="input" placeholder="Av. Aconquija 1234, San Miguel de Tucumán" />
                        <p v-if="formErrors.address" class="text-xs text-rose-500 mt-1">{{ formErrors.address[0] }}</p>
                    </div>
                    <div>
                        <label class="label">Horario de atención</label>
                        <input v-model="form.schedule" type="text" class="input" placeholder="Lun-Vie 9-18, Sáb 9-13" />
                        <p v-if="formErrors.schedule" class="text-xs text-rose-500 mt-1">{{ formErrors.schedule[0] }}</p>
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Teléfono</label>
                        <input v-model="form.phone" type="tel" class="input" placeholder="+54 381 555-1234" />
                        <p v-if="formErrors.phone" class="text-xs text-rose-500 mt-1">{{ formErrors.phone[0] }}</p>
                    </div>
                    <div>
                        <label class="label">Email de contacto</label>
                        <input v-model="form.email_contact" type="email" class="input" placeholder="hola@mitienda.com" />
                        <p v-if="formErrors.email_contact" class="text-xs text-rose-500 mt-1">{{ formErrors.email_contact[0] }}</p>
                    </div>
                </div>
            </div>

            <!-- WhatsApp -->
            <div class="card p-6 space-y-4">
                <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <span class="w-7 h-7 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
                        <SvgIcon name="whatsapp" size="0.9rem" />
                    </span>
                    <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100">WhatsApp de la tienda</h3>
                </div>

                <div>
                    <label class="label">Número de WhatsApp</label>
                    <input
                        v-model="form.whatsapp_number"
                        type="text"
                        class="input font-mono"
                        placeholder="5493813150800"
                    />
                    <p class="text-[11px] text-slate-500 mt-1">
                        Formato wa.me: solo dígitos, con <strong>549</strong> si es celular argentino.
                        Ej: <span class="font-mono">+54 9 381 3150800</span> → <span class="font-mono">5493813150800</span>
                    </p>
                    <p v-if="formErrors.whatsapp_number" class="text-xs text-rose-500 mt-1">{{ formErrors.whatsapp_number[0] }}</p>
                </div>
            </div>

            <!-- Redes sociales -->
            <div class="card p-6 space-y-4">
                <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <span class="w-7 h-7 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center">
                        <SvgIcon name="share" size="0.9rem" />
                    </span>
                    <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100">Redes sociales</h3>
                </div>

                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <label class="label">Instagram</label>
                        <input v-model="form.instagram_url" type="url" class="input text-xs" placeholder="https://instagram.com/..." />
                        <p v-if="formErrors.instagram_url" class="text-xs text-rose-500 mt-1">{{ formErrors.instagram_url[0] }}</p>
                    </div>
                    <div>
                        <label class="label">Facebook</label>
                        <input v-model="form.facebook_url" type="url" class="input text-xs" placeholder="https://facebook.com/..." />
                        <p v-if="formErrors.facebook_url" class="text-xs text-rose-500 mt-1">{{ formErrors.facebook_url[0] }}</p>
                    </div>
                    <div>
                        <label class="label">TikTok</label>
                        <input v-model="form.tiktok_url" type="url" class="input text-xs" placeholder="https://tiktok.com/@..." />
                        <p v-if="formErrors.tiktok_url" class="text-xs text-rose-500 mt-1">{{ formErrors.tiktok_url[0] }}</p>
                    </div>
                </div>
            </div>

            <!-- Configuración comercial -->
            <div class="card p-6 space-y-4">
                <div class="flex items-center gap-2.5 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <span class="w-7 h-7 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
                        <SvgIcon name="box" size="0.9rem" />
                    </span>
                    <h3 class="text-sm font-extrabold text-slate-800 dark:text-slate-100">Configuración comercial</h3>
                </div>

                <div>
                    <label class="label">Compra mínima ($)</label>
                    <input
                        v-model.number="form.min_purchase"
                        type="number"
                        min="0"
                        step="0.01"
                        class="input max-w-xs"
                    />
                    <p class="text-[11px] text-slate-500 mt-1">
                        Monto mínimo del carrito para confirmar el pedido.
                    </p>
                    <p v-if="formErrors.min_purchase" class="text-xs text-rose-500 mt-1">{{ formErrors.min_purchase[0] }}</p>
                </div>

                <div>
                    <label class="label">Descripción corta (opcional)</label>
                    <textarea
                        v-model="form.short_description"
                        class="input min-h-[80px]"
                        placeholder="Una línea que se muestra en el footer o en los mails automáticos."
                    />
                    <p v-if="formErrors.short_description" class="text-xs text-rose-500 mt-1">{{ formErrors.short_description[0] }}</p>
                </div>
            </div>

            <!-- Botón guardar -->
            <div class="flex justify-end gap-3">
                <button type="submit" :disabled="saving" class="btn btn-primary flex items-center gap-2">
                    <LoadingSpinner v-if="saving" size="sm" />
                    <SvgIcon v-else name="check" size="0.95rem" />
                    <span>{{ saving ? 'Guardando...' : 'Guardar configuración' }}</span>
                </button>
            </div>
        </form>
    </div>
</template>
