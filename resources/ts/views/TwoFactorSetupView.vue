<script setup lang="ts">
import { onMounted, ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from '@/bootstrap';
import PageHeader from '@/components/PageHeader.vue';
import SvgIcon from '@/components/SvgIcon.vue';

const router = useRouter();

const enabled = ref(false);
const confirmedAt = ref<string | null>(null);
const loading = ref(true);
const actionLoading = ref(false);

const setupData = ref<{
    secret: string;
    qr_url: string;
    recovery_codes: string[];
} | null>(null);

const verificationCode = ref('');
const error = ref<string | null>(null);
const success = ref<string | null>(null);
const step = ref<'idle' | 'setup' | 'verify' | 'done'>('idle');

async function fetchStatus() {
    loading.value = true;
    try {
        const { data } = await axios.get('/me/two-factor');
        enabled.value = data.enabled;
        confirmedAt.value = data.confirmed_at;
        step.value = data.enabled ? 'done' : 'idle';
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

async function startSetup() {
    actionLoading.value = true;
    error.value = null;
    try {
        const { data } = await axios.post('/me/two-factor/setup');
        setupData.value = data;
        step.value = 'setup';
    } catch (e: any) {
        error.value = 'No se pudo iniciar el setup';
    } finally {
        actionLoading.value = false;
    }
}

async function verify() {
    actionLoading.value = true;
    error.value = null;
    try {
        await axios.post('/me/two-factor/verify', { code: verificationCode.value });
        success.value = '2FA activado correctamente';
        await fetchStatus();
        step.value = 'done';
        verificationCode.value = '';
    } catch (e: any) {
        error.value = e.response?.data?.message || 'Código inválido';
    } finally {
        actionLoading.value = false;
    }
}

async function disable() {
    if (!confirm('¿Desactivar 2FA? Tu cuenta quedará menos segura.')) return;
    actionLoading.value = true;
    try {
        await axios.delete('/me/two-factor');
        success.value = '2FA desactivado';
        await fetchStatus();
    } catch (e) {
        error.value = 'No se pudo desactivar';
    } finally {
        actionLoading.value = false;
    }
}

onMounted(fetchStatus);

// QR generado server-side como URL otpauth://
// Para mostrarlo como imagen, podríamos usar una lib, pero por simplicidad
// mostramos la URL + instrucciones para pegarla en Google Authenticator.
const qrInstructions = computed(() => {
    if (!setupData.value) return '';
    return setupData.value.qr_url;
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <router-link :to="{ name: 'profile' }" class="btn btn-ghost btn-sm">
                <SvgIcon name="chevron-left" size="0.85rem" />
                Perfil
            </router-link>
        </div>

        <PageHeader icon="shield" title="Verificación en 2 pasos" subtitle="Protegé tu cuenta con códigos TOTP" />

        <LoadingSpinner v-if="loading && step === 'idle'" text="Cargando..." />

        <transition name="fade">
            <div v-if="success" class="rounded-2xl bg-emerald-50 border border-emerald-200 px-5 py-4 flex items-center gap-3 shadow-sm">
                <span class="w-9 h-9 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold">✓</span>
                <p class="font-semibold text-emerald-900 text-sm">{{ success }}</p>
            </div>
        </transition>

        <transition name="fade">
            <div v-if="error" class="rounded-2xl bg-rose-50 border border-rose-200 px-5 py-4 flex items-center gap-3 shadow-sm">
                <span class="w-9 h-9 rounded-full bg-rose-500 text-white flex items-center justify-center font-bold">!</span>
                <p class="font-semibold text-rose-900 text-sm">{{ error }}</p>
            </div>
        </transition>

        <!-- Estado: ya activado -->
        <div v-if="!loading && enabled && step === 'done'" class="card p-6 space-y-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
                    <SvgIcon name="check" size="1.5rem" />
                </div>
                <div>
                    <h3 class="font-bold text-base text-slate-800">2FA está activo</h3>
                    <p class="text-xs text-slate-500" v-if="confirmedAt">
                        Activado el {{ new Date(confirmedAt).toLocaleString('es-AR') }}
                    </p>
                </div>
            </div>
            <p class="text-sm text-slate-600">
                Cada vez que inicies sesión, te pediremos un código de 6 dígitos de tu app autenticadora.
            </p>
            <button @click="disable" :disabled="actionLoading" class="btn btn-danger">
                Desactivar 2FA
            </button>
        </div>

        <!-- Estado: no activado — paso 1 -->
        <div v-else-if="!loading && !enabled && step === 'idle'" class="card p-6 space-y-4">
            <h3 class="font-bold text-base">Activar 2FA</h3>
            <p class="text-sm text-slate-600">
                La verificación en 2 pasos agrega una capa extra de seguridad. Necesitás una app como
                <strong>Google Authenticator</strong>, <strong>Authy</strong> o <strong>1Password</strong>.
            </p>
            <button @click="startSetup" :disabled="actionLoading" class="btn btn-primary">
                {{ actionLoading ? 'Generando...' : 'Comenzar setup' }}
            </button>
        </div>

        <!-- Paso 2: QR + secret -->
        <div v-if="step === 'setup' && setupData" class="card p-6 space-y-5">
            <h3 class="font-bold text-base">1. Escaneá el código QR</h3>
            <p class="text-sm text-slate-600">
                Abrí tu app autenticadora y escaneá este código (o copiá la clave manual).
            </p>

            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-3">
                <div class="bg-white border border-slate-200 rounded-xl p-3 font-mono text-xs break-all text-slate-700">
                    <strong class="block text-[10px] uppercase tracking-wider text-slate-400 mb-1">Clave secreta</strong>
                    {{ setupData.secret }}
                </div>
                <div class="bg-white border border-slate-200 rounded-xl p-3 font-mono text-[10px] break-all text-slate-500">
                    <strong class="block text-[10px] uppercase tracking-wider text-slate-400 mb-1">URL otpauth (para QR)</strong>
                    {{ setupData.qr_url }}
                </div>
            </div>

            <h3 class="font-bold text-base pt-4">2. Copiá los códigos de recuperación</h3>
            <p class="text-sm text-slate-600">
                Guardalos en un lugar seguro. Si perdés tu teléfono, estos códigos son la única forma de entrar.
            </p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <div
                    v-for="code in setupData.recovery_codes"
                    :key="code"
                    class="font-mono text-xs bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-center font-bold text-amber-900"
                >
                    {{ code }}
                </div>
            </div>

            <h3 class="font-bold text-base pt-4">3. Ingresá el código de verificación</h3>
            <p class="text-sm text-slate-600">
                Tu app ya debe mostrar un código de 6 dígitos. Ingresalo abajo.
            </p>
            <div class="flex gap-2">
                <input
                    v-model="verificationCode"
                    type="text"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    placeholder="123456"
                    class="input flex-1 font-mono text-center text-lg tracking-widest"
                    @keyup.enter="verify"
                />
                <button @click="verify" :disabled="actionLoading || verificationCode.length !== 6" class="btn btn-primary">
                    {{ actionLoading ? 'Verificando...' : 'Verificar y activar' }}
                </button>
            </div>
        </div>
    </div>
</template>
