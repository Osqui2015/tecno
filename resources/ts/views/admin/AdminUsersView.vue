<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue';
import axios from '@/bootstrap';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import SvgIcon from '@/components/SvgIcon.vue';
import { useAuthStore } from '@/stores/auth';

interface UserItem {
    id: number;
    name: string;
    lastname: string | null;
    email: string;
    role: string;
    phone: string | null;
    city: string | null;
    address: string | null;
    created_at: string;
    orders_count?: number;
}

const auth = useAuthStore();
const users = ref<UserItem[]>([]);
const loading = ref(false);
const meta = ref<{ current_page: number; last_page: number; total: number }>({
    current_page: 1,
    last_page: 1,
    total: 0,
});

const filters = reactive({
    search: '',
    role: '',
    page: 1,
});

const showModal = ref(false);
const showDetailModal = ref(false);
const editingUser = ref<UserItem | null>(null);
const selectedUserDetail = ref<any | null>(null);

const form = reactive({
    name: '',
    lastname: '',
    email: '',
    password: '',
    role: 'comprador',
    phone: '',
    city: '',
    address: '',
});

const formErrors = ref<Record<string, string[]>>({});
const saving = ref(false);

async function loadUsers() {
    loading.value = true;
    try {
        const params: any = { page: filters.page };
        if (filters.search.trim()) params.search = filters.search.trim();
        if (filters.role) params.role = filters.role;

        const { data } = await axios.get('/admin/users', { params });
        users.value = data.data;
        meta.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            total: data.total,
        };
    } catch (err) {
        console.error('Error cargando usuarios', err);
    } finally {
        loading.value = false;
    }
}

watch(filters, loadUsers, { deep: true });

onMounted(() => {
    loadUsers();
});

function openCreateModal() {
    editingUser.value = null;
    form.name = '';
    form.lastname = '';
    form.email = '';
    form.password = '';
    form.role = 'comprador';
    form.phone = '';
    form.city = '';
    form.address = '';
    formErrors.value = {};
    showModal.value = true;
}

function openEditModal(user: UserItem) {
    editingUser.value = user;
    form.name = user.name;
    form.lastname = user.lastname || '';
    form.email = user.email;
    form.password = ''; // Opcional al editar
    form.role = user.role;
    form.phone = user.phone || '';
    form.city = user.city || '';
    form.address = user.address || '';
    formErrors.value = {};
    showModal.value = true;
}

async function openDetailModal(user: UserItem) {
    try {
        const { data } = await axios.get(`/admin/users/${user.id}`);
        selectedUserDetail.value = data;
        showDetailModal.value = true;
    } catch (err) {
        console.error('Error cargando detalle del usuario', err);
    }
}

async function saveUser() {
    saving.value = true;
    formErrors.value = {};
    try {
        const payload: any = {
            name: form.name,
            lastname: form.lastname || null,
            email: form.email,
            role: form.role,
            phone: form.phone || null,
            city: form.city || null,
            address: form.address || null,
        };

        if (form.password) {
            payload.password = form.password;
        }

        if (editingUser.value) {
            await axios.patch(`/admin/users/${editingUser.value.id}`, payload);
        } else {
            await axios.post('/admin/users', payload);
        }

        showModal.value = false;
        loadUsers();
    } catch (err: any) {
        if (err.response?.status === 422) {
            formErrors.value = err.response.data.errors || {};
        }
    } finally {
        saving.value = false;
    }
}

async function deleteUser(user: UserItem) {
    if (user.id === auth.user?.id) {
        alert('No puedes eliminar tu propio perfil de usuario.');
        return;
    }

    if (!confirm(`¿Seguro que deseas eliminar el perfil de "${user.name} ${user.lastname || ''}" (${user.email})?`)) return;

    try {
        await axios.delete(`/admin/users/${user.id}`);
        loadUsers();
    } catch (err: any) {
        alert(err.response?.data?.message || 'Error al eliminar usuario');
    }
}

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('es-AR');
};
</script>

<template>
    <div class="space-y-5">
        <!-- Header & Filtros -->
        <div class="card p-5 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">Gestión de Perfiles y Usuarios</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Administra cuentas de compradores y privilegios de administradores</p>
                </div>
                <button @click="openCreateModal" class="btn btn-primary flex items-center gap-2">
                    <SvgIcon name="plus" size="1rem" />
                    <span>Nuevo Perfil</span>
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <div class="flex-1 min-w-[200px] relative">
                    <SvgIcon name="search" size="1rem" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="filters.search"
                        type="text"
                        placeholder="Buscar por nombre, email o teléfono..."
                        class="input pl-10"
                    />
                </div>
                <select v-model="filters.role" class="input max-w-[160px]">
                    <option value="">Todos los roles</option>
                    <option value="comprador">Compradores</option>
                    <option value="admin">Administradores</option>
                </select>
            </div>
        </div>

        <!-- Tabla de Usuarios -->
        <div class="card overflow-hidden">
            <div v-if="loading" class="p-8 flex justify-center">
                <LoadingSpinner />
            </div>

            <div v-else-if="users.length === 0" class="p-8 text-center text-slate-500 dark:text-slate-400">
                No se encontraron perfiles de usuario.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 uppercase text-xs">
                        <tr>
                            <th class="p-3.5">Usuario</th>
                            <th class="p-3.5">Contacto</th>
                            <th class="p-3.5">Ciudad</th>
                            <th class="p-3.5">Rol</th>
                            <th class="p-3.5">Fecha Registro</th>
                            <th class="p-3.5 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="u in users" :key="u.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                            <td class="p-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-brand-500 to-purple-600 text-white flex items-center justify-center font-bold text-xs shadow-sm">
                                        {{ u.name[0].toUpperCase() }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-800 dark:text-slate-100">{{ u.name }} {{ u.lastname || '' }}</p>
                                        <p class="text-xs text-slate-400 font-mono">{{ u.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3.5 text-slate-600 dark:text-slate-300">
                                {{ u.phone || 'Sin teléfono' }}
                            </td>
                            <td class="p-3.5 text-slate-600 dark:text-slate-300">
                                {{ u.city || 'No especificada' }}
                            </td>
                            <td class="p-3.5">
                                <span
                                    v-if="u.role === 'admin'"
                                    class="chip bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 font-bold"
                                >
                                    Administrador
                                </span>
                                <span v-else class="chip chip-muted">
                                    Comprador
                                </span>
                            </td>
                            <td class="p-3.5 text-slate-500 dark:text-slate-400 text-xs">
                                {{ formatDate(u.created_at) }}
                            </td>
                            <td class="p-3.5 text-right space-x-2">
                                <button @click="openDetailModal(u)" class="btn btn-ghost text-xs px-2 py-1" title="Ver detalle">
                                    Ver
                                </button>
                                <button @click="openEditModal(u)" class="btn btn-secondary text-xs px-2.5 py-1">
                                    Editar
                                </button>
                                <button
                                    @click="deleteUser(u)"
                                    :disabled="u.id === auth.user?.id"
                                    class="btn btn-danger text-xs px-2.5 py-1 disabled:opacity-40"
                                >
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="meta.last_page > 1" class="p-4 border-t border-slate-100 dark:border-slate-800 flex justify-between items-center text-xs">
                <span class="text-slate-500">Página {{ meta.current_page }} de {{ meta.last_page }} ({{ meta.total }} usuarios)</span>
                <div class="flex gap-2">
                    <button
                        :disabled="filters.page <= 1"
                        @click="filters.page--"
                        class="btn btn-secondary text-xs"
                    >
                        Anterior
                    </button>
                    <button
                        :disabled="filters.page >= meta.last_page"
                        @click="filters.page++"
                        class="btn btn-secondary text-xs"
                    >
                        Siguiente
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal Crear/Editar Perfil -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="card p-6 w-full max-w-lg space-y-4 shadow-xl">
                <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="font-bold text-lg text-slate-800 dark:text-slate-100">
                        {{ editingUser ? 'Editar Perfil de Usuario' : 'Nuevo Perfil de Usuario' }}
                    </h3>
                    <button @click="showModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <form @submit.prevent="saveUser" class="space-y-4 text-sm">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Nombre</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="input"
                                required
                            />
                            <p v-if="formErrors.name" class="text-xs text-red-500 mt-1">{{ formErrors.name[0] }}</p>
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Apellido</label>
                            <input
                                v-model="form.lastname"
                                type="text"
                                class="input"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Correo Electrónico</label>
                        <input
                            v-model="form.email"
                            type="email"
                            class="input"
                            required
                        />
                        <p v-if="formErrors.email" class="text-xs text-red-500 mt-1">{{ formErrors.email[0] }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Contraseña</label>
                            <input
                                v-model="form.password"
                                type="password"
                                :placeholder="editingUser ? 'Dejar en blanco para no cambiar' : 'Mínimo 8 caracteres'"
                                class="input"
                                :required="!editingUser"
                            />
                            <p v-if="formErrors.password" class="text-xs text-red-500 mt-1">{{ formErrors.password[0] }}</p>
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Rol</label>
                            <select v-model="form.role" class="input">
                                <option value="comprador">Comprador</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Teléfono</label>
                            <input
                                v-model="form.phone"
                                type="text"
                                class="input"
                            />
                        </div>
                        <div>
                            <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Ciudad</label>
                            <input
                                v-model="form.city"
                                type="text"
                                class="input"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Dirección</label>
                        <input
                            v-model="form.address"
                            type="text"
                            class="input"
                        />
                    </div>

                    <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showModal = false" class="btn btn-secondary">
                            Cancelar
                        </button>
                        <button type="submit" :disabled="saving" class="btn btn-primary flex items-center gap-2">
                            <LoadingSpinner v-if="saving" size="sm" />
                            <span>{{ editingUser ? 'Guardar Cambios' : 'Crear Perfil' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Detalle del Usuario -->
        <div v-if="showDetailModal && selectedUserDetail" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="card p-6 w-full max-w-lg space-y-4 shadow-xl">
                <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-800 pb-3">
                    <h3 class="font-bold text-lg text-slate-800 dark:text-slate-100">
                        Detalle del Usuario #{{ selectedUserDetail.id }}
                    </h3>
                    <button @click="showDetailModal = false" class="text-slate-400 hover:text-slate-600">✕</button>
                </div>

                <div class="space-y-3 text-sm">
                    <div class="grid grid-cols-2 gap-2 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
                        <div>
                            <span class="text-xs text-slate-400 block">Nombre Completo:</span>
                            <span class="font-bold text-slate-800 dark:text-slate-100">{{ selectedUserDetail.name }} {{ selectedUserDetail.lastname }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-slate-400 block">Email:</span>
                            <span class="font-bold text-slate-800 dark:text-slate-100">{{ selectedUserDetail.email }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-slate-400 block">Rol:</span>
                            <span class="font-bold text-purple-600 uppercase text-xs">{{ selectedUserDetail.role }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-slate-400 block">Teléfono:</span>
                            <span>{{ selectedUserDetail.phone || '-' }}</span>
                        </div>
                    </div>

                    <div>
                        <h4 class="font-bold text-xs text-slate-500 uppercase tracking-wider mb-2">Pedidos Realizados ({{ selectedUserDetail.orders_count }})</h4>
                        <div v-if="!selectedUserDetail.orders || selectedUserDetail.orders.length === 0" class="text-xs text-slate-400 italic">
                            Este usuario aún no tiene pedidos registrados.
                        </div>
                        <ul v-else class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                            <li v-for="o in selectedUserDetail.orders" :key="o.id" class="flex justify-between items-center text-xs p-2 rounded bg-slate-50 dark:bg-slate-800/30">
                                <span>Pedido #{{ o.id }} ({{ formatDate(o.created_at) }})</span>
                                <span class="font-bold text-emerald-600">${{ Math.round(o.total).toLocaleString('es-AR') }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="flex justify-end pt-2 border-t border-slate-100 dark:border-slate-800">
                    <button @click="showDetailModal = false" class="btn btn-secondary text-xs">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
