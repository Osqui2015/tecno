import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from '@/bootstrap';

export type Role = 'comprador' | 'admin' | 'super-admin' | 'admin-pedidos' | 'admin-productos';

export interface User {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    role: Role;
    roles?: Role[]; // Spatie roles
}

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(
        JSON.parse(localStorage.getItem('auth_user') || 'null')
    );
    const token = ref<string | null>(localStorage.getItem('auth_token'));
    const loading = ref(false);
    const error = ref<string | null>(null);

    const isAuthenticated = computed(() => !!token.value && !!user.value);

    // Roles efectivos (considera tanto role de la columna como roles de Spatie)
    const effectiveRoles = computed<Role[]>(() => {
        const r = user.value?.roles ?? [];
        if (user.value?.role && !r.includes(user.value.role)) {
            r.push(user.value.role);
        }
        return r;
    });

    const isAdmin = computed(() => effectiveRoles.value.some((r) =>
        ['super-admin', 'admin', 'admin-pedidos', 'admin-productos'].includes(r)
    ));
    const isSuperAdmin = computed(() => effectiveRoles.value.includes('super-admin'));
    const isComprador = computed(() => effectiveRoles.value.includes('comprador'));

    // Permisos granulares
    const canManageProducts = computed(() =>
        effectiveRoles.value.some((r) => ['super-admin', 'admin', 'admin-productos'].includes(r))
    );
    const canManageOrders = computed(() =>
        effectiveRoles.value.some((r) => ['super-admin', 'admin', 'admin-pedidos'].includes(r))
    );
    const canManageUsers = computed(() =>
        effectiveRoles.value.includes('super-admin')
    );

    function persist() {
        if (token.value && user.value) {
            localStorage.setItem('auth_token', token.value);
            localStorage.setItem('auth_user', JSON.stringify(user.value));
        } else {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
        }
    }

    if (typeof window !== 'undefined') {
        window.addEventListener('auth:unauthorized', () => {
            token.value = null;
            user.value = null;
        });
    }

    async function login(
        email: string,
        password: string
    ): Promise<{ success: boolean; requires2fa?: boolean }> {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await axios.post('/login', { email, password });
            if (data.requires_2fa) {
                return { success: false, requires2fa: true };
            }
            token.value = data.token;
            user.value = data.user;
            persist();
            return { success: true };
        } catch (e: any) {
            error.value = e.response?.data?.message || 'Error al iniciar sesión';
            return { success: false };
        } finally {
            loading.value = false;
        }
    }

    async function challenge2fa(email: string, code: string): Promise<boolean> {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await axios.post('/auth/2fa-challenge', { email, code });
            token.value = data.token;
            user.value = data.user;
            persist();
            return true;
        } catch (e: any) {
            error.value = e.response?.data?.message || 'Código de verificación inválido';
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function register(
        name: string,
        email: string,
        phone: string,
        password: string,
        password_confirmation: string,
        lastname?: string
    ): Promise<boolean> {
        loading.value = true;
        error.value = null;
        try {
            const payload: any = {
                name,
                email,
                phone,
                password,
                password_confirmation,
            };
            if (lastname) {
                payload.lastname = lastname;
            }
            const { data } = await axios.post('/register', payload);
            token.value = data.token;
            user.value = data.user;
            persist();
            return true;
        } catch (e: any) {
            // Mostrar mensaje del primer error de validación si existe
            const errs = e.response?.data?.errors;
            if (errs) {
                const first = Object.values(errs)[0] as string[] | undefined;
                if (first && first[0]) {
                    error.value = first[0];
                    return false;
                }
            }
            error.value =
                e.response?.data?.message || 'Error al registrarse';
            return false;
        } finally {
            loading.value = false;
        }
    }

    async function logout() {
        try {
            await axios.post('/logout');
        } catch (e) {
            // ignore
        } finally {
            token.value = null;
            user.value = null;
            persist();
        }
    }

    async function fetchUser() {
        if (!token.value) return;
        try {
            const { data } = await axios.get('/me');
            user.value = data;
            persist();
        } catch (e) {
            await logout();
        }
    }

    return {
        user,
        token,
        loading,
        error,
        isAuthenticated,
        isAdmin,
        isSuperAdmin,
        isComprador,
        canManageProducts,
        canManageOrders,
        canManageUsers,
        effectiveRoles,
        login,
        challenge2fa,
        register,
        logout,
        fetchUser,
    };
});
