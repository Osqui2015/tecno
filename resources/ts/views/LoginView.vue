<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const step = ref<'credentials' | '2fa'>('credentials');
const email = ref('');
const password = ref('');
const showPassword = ref(false);
const twoFactorCode = ref('');

async function handleSubmit() {
    const res = await auth.login(email.value, password.value);
    if (res.requires2fa) {
        step.value = '2fa';
        return;
    }
    if (res.success) {
        const redirect = (route.query.redirect as string) || '/';
        router.push(redirect);
    }
}

async function handle2faSubmit() {
    const ok = await auth.challenge2fa(email.value, twoFactorCode.value);
    if (ok) {
        const redirect = (route.query.redirect as string) || '/';
        router.push(redirect);
    }
}

function cancel2fa() {
    step.value = 'credentials';
    twoFactorCode.value = '';
    auth.error = null;
}
</script>

<template>
    <div class="grid lg:grid-cols-2 gap-0 min-h-[calc(100vh-12rem)] rounded-3xl overflow-hidden shadow-xl animate-fade-in">
        <!-- Brand panel -->
        <div
            class="hidden lg:flex flex-col justify-between p-12 bg-gradient-to-br from-brand-600 via-violet-600 to-purple-700 text-white relative overflow-hidden"
        >
            <div class="absolute inset-0 opacity-20">
                <div class="absolute -top-32 -right-32 w-96 h-96 bg-white/20 rounded-full blur-3xl animate-float"></div>
                <div class="absolute -bottom-32 -left-32 w-96 h-96 bg-purple-300/30 rounded-full blur-3xl"></div>
            </div>

            <div class="relative">
                <router-link :to="{ name: 'home' }" class="inline-flex items-center gap-2">
                    <span class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center text-xl">
                        ⚡
                    </span>
                    <span class="text-2xl font-extrabold tracking-tight">Tecno-Rexs</span>
                </router-link>
            </div>

            <div class="relative space-y-4">
                <h2 class="text-4xl font-extrabold leading-tight">
                    Bienvenido de vuelta
                    <span class="block bg-gradient-to-r from-yellow-200 to-pink-200 bg-clip-text text-transparent">
                        a tu tienda de tecnología.
                    </span>
                </h2>
                <p class="text-lg text-white/80 max-w-md">
                    Ingresá a tu cuenta para ver tus pedidos, gestionar tu perfil y aprovechar ofertas exclusivas.
                </p>
                <div class="flex items-center gap-2 text-sm text-white/70 pt-4">
                    <span class="text-2xl">🛍️</span>
                    <span>Tu catálogo de confianza en tecnología</span>
                </div>
            </div>

            <div class="relative text-xs text-white/50">
                © {{ new Date().getFullYear() }} Tecno-Rexs
            </div>
        </div>

        <!-- Form -->
        <div class="bg-white dark:bg-slate-900 p-8 sm:p-12 flex flex-col justify-center">
            <div class="max-w-md mx-auto w-full">
                <!-- Credentials step -->
                <div v-if="step === 'credentials'">
                    <h1 class="text-3xl font-extrabold text-gray-900 mb-2">
                        Iniciar sesión
                    </h1>
                    <p class="text-gray-500 mb-8">
                        ¿No tenés cuenta?
                        <router-link
                            :to="{ name: 'register' }"
                            class="font-semibold text-brand-600 hover:text-brand-700"
                        >
                            Registrate acá
                        </router-link>
                    </p>

                    <form @submit.prevent="handleSubmit" class="space-y-5">
                        <div>
                            <label class="label">Email</label>
                            <input
                                v-model="email"
                                type="email"
                                required
                                autofocus
                                class="input"
                                placeholder="tu@email.com"
                            />
                        </div>

                        <div>
                            <div class="mb-1.5">
                                <label class="label !mb-0">Contraseña</label>
                            </div>
                            <div class="relative">
                                <input
                                    v-model="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    class="input pr-12"
                                    placeholder="••••••••"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700"
                                    :aria-label="showPassword ? 'Ocultar contraseña' : 'Ver contraseña'"
                                >
                                    {{ showPassword ? '🙈' : '👁️' }}
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="auth.error"
                            class="rounded-xl bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 text-sm animate-fade-in"
                        >
                            ⚠️ {{ auth.error }}
                        </div>

                        <button
                            type="submit"
                            :disabled="auth.loading"
                            class="btn btn-primary btn-lg w-full"
                        >
                            {{ auth.loading ? '⏳ Ingresando...' : 'Ingresar →' }}
                        </button>
                    </form>
                </div>

                <!-- 2FA Challenge step -->
                <div v-else class="animate-fade-in">
                    <div class="w-12 h-12 rounded-2xl bg-brand-50 text-brand-600 flex items-center justify-center text-2xl mb-4">
                        🔐
                    </div>
                    <h1 class="text-2xl font-extrabold text-gray-900 mb-2">
                        Verificación en dos pasos
                    </h1>
                    <p class="text-gray-500 text-sm mb-6">
                        Ingresá el código de 6 dígitos de tu aplicación autenticadora (Google Authenticator, Authy) o un código de recuperación.
                    </p>

                    <form @submit.prevent="handle2faSubmit" class="space-y-5">
                        <div>
                            <label class="label">Código de autenticación</label>
                            <input
                                v-model="twoFactorCode"
                                type="text"
                                required
                                autofocus
                                autocomplete="one-time-code"
                                class="input text-center text-lg font-mono tracking-widest"
                                placeholder="123456"
                            />
                        </div>

                        <div
                            v-if="auth.error"
                            class="rounded-xl bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 text-sm animate-fade-in"
                        >
                            ⚠️ {{ auth.error }}
                        </div>

                        <button
                            type="submit"
                            :disabled="auth.loading || !twoFactorCode.trim()"
                            class="btn btn-primary btn-lg w-full"
                        >
                            {{ auth.loading ? '⏳ Verificando...' : 'Verificar e ingresar →' }}
                        </button>

                        <button
                            type="button"
                            @click="cancel2fa"
                            class="btn btn-secondary w-full"
                        >
                            ← Volver al login
                        </button>
                    </form>
                </div>

                <div class="mt-8 pt-6 border-t border-gray-100">
                    <p class="text-xs text-gray-500 text-center">
                        Al continuar aceptás los Términos de servicio y la Política de privacidad de la tienda.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>