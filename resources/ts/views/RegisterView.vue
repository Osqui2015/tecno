<script setup lang="ts">
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();

const name = ref('');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const showPassword = ref(false);
const acceptTerms = ref(false);

const passwordsMatch = computed(
    () => password.value === passwordConfirmation.value
);
const passwordStrength = computed(() => {
    const p = password.value;
    if (p.length < 8) return { label: 'Muy corta', value: 0, color: 'bg-rose-500' };
    if (p.length < 10) return { label: 'Aceptable', value: 1, color: 'bg-amber-500' };
    if (p.length < 12) return { label: 'Buena', value: 2, color: 'bg-sky-500' };
    return { label: 'Muy segura', value: 3, color: 'bg-emerald-500' };
});

async function handleSubmit() {
    if (!passwordsMatch.value) {
        alert('Las contraseñas no coinciden');
        return;
    }
    if (!acceptTerms.value) {
        alert('Aceptá los términos para continuar');
        return;
    }
    const ok = await auth.register(
        name.value,
        email.value,
        password.value,
        passwordConfirmation.value
    );
    if (ok) {
        router.push('/');
    }
}
</script>

<template>
    <div class="grid lg:grid-cols-2 gap-0 min-h-[calc(100vh-12rem)] rounded-3xl overflow-hidden shadow-xl animate-fade-in">
        <!-- Brand panel -->
        <div
            class="hidden lg:flex flex-col justify-between p-12 bg-gradient-to-br from-purple-600 via-pink-600 to-rose-600 text-white relative overflow-hidden"
        >
            <div class="absolute inset-0 opacity-20">
                <div class="absolute -top-32 -left-32 w-96 h-96 bg-white/20 rounded-full blur-3xl animate-float"></div>
                <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-yellow-300/30 rounded-full blur-3xl"></div>
            </div>

            <div class="relative">
                <router-link :to="{ name: 'home' }" class="inline-flex items-center gap-2">
                    <span class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center text-xl">
                        🛒
                    </span>
                    <span class="text-2xl font-extrabold">Ecomers</span>
                </router-link>
            </div>

            <div class="relative space-y-4">
                <h2 class="text-4xl font-extrabold leading-tight">
                    Unite a la comunidad
                    <span class="block bg-gradient-to-r from-yellow-200 to-cyan-200 bg-clip-text text-transparent">
                        y empezá a comprar.
                    </span>
                </h2>
                <p class="text-lg text-white/80 max-w-md">
                    Creá tu cuenta en segundos y desbloqueá beneficios exclusivos, descuentos y seguimiento de pedidos.
                </p>
                <ul class="space-y-2 pt-4 text-white/90 text-sm">
                    <li class="flex items-center gap-2">✅ 10% OFF en tu primera compra</li>
                    <li class="flex items-center gap-2">✅ Acceso a ofertas exclusivas</li>
                    <li class="flex items-center gap-2">✅ Seguimiento de pedidos en tiempo real</li>
                </ul>
            </div>

            <div class="relative text-xs text-white/50">
                © {{ new Date().getFullYear() }} Ecomers
            </div>
        </div>

        <!-- Form -->
        <div class="bg-white p-8 sm:p-12 flex flex-col justify-center">
            <div class="max-w-md mx-auto w-full">
                <h1 class="text-3xl font-extrabold text-gray-900 mb-2">
                    Crear cuenta
                </h1>
                <p class="text-gray-500 mb-8">
                    ¿Ya tenés cuenta?
                    <router-link
                        :to="{ name: 'login' }"
                        class="font-semibold text-brand-600 hover:text-brand-700"
                    >
                        Iniciá sesión
                    </router-link>
                </p>

                <form @submit.prevent="handleSubmit" class="space-y-5">
                    <div>
                        <label class="label">Nombre</label>
                        <input
                            v-model="name"
                            type="text"
                            required
                            autofocus
                            class="input"
                            placeholder="Juan Pérez"
                        />
                    </div>
                    <div>
                        <label class="label">Email</label>
                        <input
                            v-model="email"
                            type="email"
                            required
                            class="input"
                            placeholder="tu@email.com"
                        />
                    </div>

                    <div>
                        <label class="label">Contraseña</label>
                        <div class="relative">
                            <input
                                v-model="password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                minlength="8"
                                class="input pr-12"
                                placeholder="Mínimo 8 caracteres"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700"
                            >
                                {{ showPassword ? '🙈' : '👁️' }}
                            </button>
                        </div>
                        <!-- Strength meter -->
                        <div v-if="password" class="mt-2 flex items-center gap-2">
                            <div class="flex-1 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                <div
                                    :class="[
                                        'h-full transition-all duration-300',
                                        passwordStrength.color,
                                    ]"
                                    :style="{ width: `${(passwordStrength.value + 1) * 25}%` }"
                                ></div>
                            </div>
                            <span class="text-xs font-medium text-gray-500">
                                {{ passwordStrength.label }}
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="label">Confirmar contraseña</label>
                        <input
                            v-model="passwordConfirmation"
                            type="password"
                            required
                            minlength="8"
                            class="input"
                            :class="{
                                'border-emerald-500 focus:ring-emerald-500/30 focus:border-emerald-500':
                                    passwordConfirmation && passwordsMatch,
                                'border-rose-500 focus:ring-rose-500/30 focus:border-rose-500':
                                    passwordConfirmation && !passwordsMatch,
                            }"
                            placeholder="Repetí la contraseña"
                        />
                        <p
                            v-if="passwordConfirmation && !passwordsMatch"
                            class="mt-1.5 text-xs text-rose-600 font-medium"
                        >
                            ⚠️ Las contraseñas no coinciden
                        </p>
                        <p
                            v-else-if="passwordConfirmation && passwordsMatch"
                            class="mt-1.5 text-xs text-emerald-600 font-medium"
                        >
                            ✓ Las contraseñas coinciden
                        </p>
                    </div>

                    <label class="flex items-start gap-2 cursor-pointer">
                        <input
                            v-model="acceptTerms"
                            type="checkbox"
                            class="mt-1 w-4 h-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                        />
                        <span class="text-sm text-gray-600">
                            Acepto los
                            <a href="#" class="text-brand-600 hover:underline font-medium">Términos</a>
                            y la
                            <a href="#" class="text-brand-600 hover:underline font-medium">Política de privacidad</a>.
                        </span>
                    </label>

                    <div
                        v-if="auth.error"
                        class="rounded-xl bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 text-sm animate-fade-in"
                    >
                        ⚠️ {{ auth.error }}
                    </div>

                    <button
                        type="submit"
                        :disabled="auth.loading || !acceptTerms"
                        class="btn btn-primary btn-lg w-full"
                    >
                        {{ auth.loading ? '⏳ Creando cuenta...' : 'Registrarme →' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>