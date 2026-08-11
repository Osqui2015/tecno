<script setup lang="ts">
import { computed, onMounted, ref, reactive } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useRouter } from 'vue-router';
import axios from '@/bootstrap';
import PageHeader from '@/components/PageHeader.vue';
import SvgIcon from '@/components/SvgIcon.vue';

const auth = useAuthStore();
const router = useRouter();

const editing = ref(false);
const saving = ref(false);
const success = ref<string | null>(null);
const error = ref<string | null>(null);

const form = reactive({
    name: '',
    lastname: '',
    phone: '',
    address: '',
    city: '',
    zip_code: '',
});

onMounted(async () => {
    await auth.fetchUser();
    loadForm();
});

function loadForm() {
    if (!auth.user) return;
    form.name = (auth.user as any).name ?? '';
    form.lastname = (auth.user as any).lastname ?? '';
    form.phone = (auth.user as any).phone ?? '';
    form.address = (auth.user as any).address ?? '';
    form.city = (auth.user as any).city ?? '';
    form.zip_code = (auth.user as any).zip_code ?? '';
}

function startEdit() {
    loadForm();
    editing.value = true;
}

async function save() {
    saving.value = true;
    error.value = null;
    success.value = null;
    try {
        await axios.patch('/me/profile', form);
        await auth.fetchUser();
        success.value = 'Perfil actualizado';
        editing.value = false;
        setTimeout(() => (success.value = null), 2500);
    } catch (e: any) {
        error.value = e.response?.data?.message || 'Error al guardar';
    } finally {
        saving.value = false;
    }
}

const initials = computed(() => {
    const n = auth.user?.name ?? '';
    return n.split(' ').map((p) => p[0]).join('').toUpperCase().slice(0, 2) || '?';
});

async function logout() {
    await auth.logout();
    router.push({ name: 'home' });
}
</script>

<template>
    <div class="space-y-6">
        <PageHeader icon="user" title="Mi perfil" />

        <transition name="fade">
            <div
                v-if="success"
                class="rounded-2xl bg-emerald-50 border border-emerald-200 px-5 py-4 flex items-center gap-3 shadow-sm"
            >
                <span class="w-9 h-9 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold">✓</span>
                <p class="font-semibold text-emerald-900 text-sm">{{ success }}</p>
            </div>
        </transition>

        <div v-if="auth.user" class="grid md:grid-cols-3 gap-6 animate-fade-in">
            <div class="card p-6 text-center md:col-span-1">
                <div class="w-28 h-28 mx-auto rounded-full bg-gradient-to-br from-brand-500 via-violet-500 to-purple-600 text-white flex items-center justify-center text-4xl font-extrabold shadow-lg mb-4 animate-pulse-glow">
                    {{ initials }}
                </div>
                <h2 class="font-bold text-xl text-gray-900">{{ auth.user.name }}</h2>
                <p class="text-sm text-gray-500 mt-1">{{ auth.user.email }}</p>
                <span
                    v-if="auth.isAdmin"
                    class="inline-block mt-3 text-[9px] font-extrabold uppercase tracking-wider text-purple-700 bg-purple-50 px-2 py-1 rounded-md"
                >
                    Administrador
                </span>
            </div>

            <div class="card p-6 md:col-span-2 space-y-5">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-lg">Información de envío</h3>
                    <button v-if="!editing" @click="startEdit" class="btn btn-secondary btn-sm">
                        <SvgIcon name="pencil" size="0.85rem" />
                        Editar
                    </button>
                </div>

                <div v-if="!editing" class="grid sm:grid-cols-2 gap-4">
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Nombre</p>
                        <p class="font-semibold text-gray-900">{{ auth.user.name }}</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Apellido</p>
                        <p class="font-semibold text-gray-900">{{ (auth.user as any).lastname || '—' }}</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Teléfono</p>
                        <p class="font-semibold text-gray-900">{{ (auth.user as any).phone || '—' }}</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Ciudad</p>
                        <p class="font-semibold text-gray-900">{{ (auth.user as any).city || '—' }}</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-xl sm:col-span-2">
                        <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Dirección</p>
                        <p class="font-semibold text-gray-900">{{ (auth.user as any).address || '—' }}</p>
                    </div>
                </div>

                <form v-else @submit.prevent="save" class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Nombre</label>
                        <input v-model="form.name" class="input" required />
                    </div>
                    <div>
                        <label class="label">Apellido</label>
                        <input v-model="form.lastname" class="input" required />
                    </div>
                    <div>
                        <label class="label">Teléfono</label>
                        <input v-model="form.phone" type="tel" class="input" required />
                    </div>
                    <div>
                        <label class="label">Ciudad</label>
                        <input v-model="form.city" class="input" required />
                    </div>
                    <div>
                        <label class="label">Código postal</label>
                        <input v-model="form.zip_code" class="input" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Dirección</label>
                        <input v-model="form.address" class="input" required />
                    </div>

                    <p v-if="error" class="sm:col-span-2 text-xs text-rose-500 font-semibold">{{ error }}</p>

                    <div class="sm:col-span-2 flex gap-2">
                        <button type="submit" class="btn btn-primary" :disabled="saving">
                            {{ saving ? 'Guardando...' : 'Guardar' }}
                        </button>
                        <button type="button" @click="editing = false" class="btn btn-ghost">Cancelar</button>
                    </div>
                </form>

                <hr class="border-gray-100" />

                <div class="flex flex-wrap gap-3">
                    <router-link :to="{ name: 'my-orders' }" class="btn btn-primary">
                        <SvgIcon name="box" size="0.95rem" /> Mis pedidos
                    </router-link>
                    <router-link v-if="auth.isAdmin" :to="{ name: 'admin-dashboard' }" class="btn btn-secondary">
                        <SvgIcon name="info" size="0.95rem" /> Panel admin
                    </router-link>
                    <router-link :to="{ name: 'two-factor-setup' }" class="btn btn-ghost">
                        <SvgIcon name="shield" size="0.95rem" /> Seguridad 2FA
                    </router-link>
                    <button @click="logout" class="btn btn-danger">
                        <SvgIcon name="logout" size="0.95rem" /> Cerrar sesión
                    </button>
                </div>
            </div>
        </div>

        <div v-else class="card p-12 text-center text-gray-500">
            Cargando...
        </div>
    </div>
</template>
