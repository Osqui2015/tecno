<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue';
import axios from '@/bootstrap';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import SvgIcon from '@/components/SvgIcon.vue';
import BaseModal from '@/components/BaseModal.vue';

interface Coupon {
    id: number;
    code: string;
    type: 'percent' | 'fixed';
    value: number;
    min_subtotal: number | null;
    max_uses: number | null;
    uses_count: number;
    starts_at: string | null;
    expires_at: string | null;
    active: boolean;
    created_at: string;
}

const coupons = ref<Coupon[]>([]);
const loading = ref(false);
const meta = ref<{ current_page: number; last_page: number; total: number }>({
    current_page: 1,
    last_page: 1,
    total: 0
});

const filters = reactive({
    search: '',
    active: '',
    page: 1
});

const showModal = ref(false);
const editingCoupon = ref<Coupon | null>(null);

const form = reactive({
    code: '',
    type: 'percent' as 'percent' | 'fixed',
    value: 10,
    min_subtotal: null as number | null,
    max_uses: null as number | null,
    starts_at: '',
    expires_at: '',
    active: true
});

const formErrors = ref<Record<string, string[]>>({});
const saving = ref(false);
const feedback = ref<{ message: string; type: 'success' | 'error' } | null>(null);
let feedbackTimer: any = null;

function notify(message: string, type: 'success' | 'error' = 'success') {
    feedback.value = { message, type };
    if (feedbackTimer) clearTimeout(feedbackTimer);
    feedbackTimer = setTimeout(() => {
        feedback.value = null;
    }, 4000);
}

async function loadCoupons() {
    loading.value = true;
    try {
        const params: any = { page: filters.page };
        if (filters.search.trim()) params.search = filters.search.trim();
        if (filters.active !== '') params.active = filters.active;

        const { data } = await axios.get('/admin/coupons', { params });
        coupons.value = data.data;
        meta.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            total: data.total
        };
    } catch (err: any) {
        console.error('Error cargando cupones', err);
        notify(err.response?.data?.message || 'Error al cargar los cupones.', 'error');
    } finally {
        loading.value = false;
    }
}

let searchTimer: any = null;

watch(
    () => [filters.search, filters.active],
    () => {
        filters.page = 1;
        if (searchTimer) clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            loadCoupons();
        }, 300);
    }
);

watch(
    () => filters.page,
    () => {
        loadCoupons();
    }
);

onMounted(() => {
    loadCoupons();
});

function openCreateModal() {
    editingCoupon.value = null;
    form.code = '';
    form.type = 'percent';
    form.value = 10;
    form.min_subtotal = null;
    form.max_uses = null;
    form.starts_at = '';
    form.expires_at = '';
    form.active = true;
    formErrors.value = {};
    showModal.value = true;
}

function openEditModal(coupon: Coupon) {
    editingCoupon.value = coupon;
    form.code = coupon.code;
    form.type = coupon.type;
    form.value = Number(coupon.value);
    form.min_subtotal = coupon.min_subtotal ? Number(coupon.min_subtotal) : null;
    form.max_uses = coupon.max_uses;
    form.starts_at = coupon.starts_at ? coupon.starts_at.substring(0, 10) : '';
    form.expires_at = coupon.expires_at ? coupon.expires_at.substring(0, 10) : '';
    form.active = coupon.active;
    formErrors.value = {};
    showModal.value = true;
}

async function saveCoupon() {
    saving.value = true;
    formErrors.value = {};
    try {
        const payload = {
            code: form.code,
            type: form.type,
            value: form.value,
            min_subtotal: form.min_subtotal || null,
            max_uses: form.max_uses || null,
            starts_at: form.starts_at || null,
            expires_at: form.expires_at || null,
            active: form.active
        };

        if (editingCoupon.value) {
            await axios.patch(`/admin/coupons/${editingCoupon.value.id}`, payload);
            notify('Cupón actualizado correctamente.');
        } else {
            await axios.post('/admin/coupons', payload);
            notify('Cupón creado correctamente.');
        }

        showModal.value = false;
        loadCoupons();
    } catch (err: any) {
        if (err.response?.status === 422) {
            formErrors.value = err.response.data.errors || {};
        } else {
            notify(err.response?.data?.message || 'Error al guardar el cupón.', 'error');
        }
    } finally {
        saving.value = false;
    }
}

async function toggleActive(coupon: Coupon) {
    try {
        const { data } = await axios.patch(`/admin/coupons/${coupon.id}/toggle`);
        coupon.active = data.coupon.active;
        notify(`Cupón ${coupon.active ? 'activado' : 'desactivado'} con éxito.`);
    } catch (err: any) {
        console.error('Error cambiando estado del cupón', err);
        notify(err.response?.data?.message || 'Error al cambiar estado del cupón.', 'error');
    }
}

const couponToDelete = ref<Coupon | null>(null);

function promptDelete(coupon: Coupon) {
    couponToDelete.value = coupon;
}

async function executeDelete() {
    if (!couponToDelete.value) return;
    try {
        await axios.delete(`/admin/coupons/${couponToDelete.value.id}`);
        notify(`Cupón "${couponToDelete.value.code}" eliminado con éxito.`);
        couponToDelete.value = null;
        loadCoupons();
    } catch (err: any) {
        console.error('Error eliminando cupón', err);
        notify(err.response?.data?.message || 'Error al eliminar cupón.', 'error');
    }
}

const formatCurrency = (val: number | string | null) => {
    if (val === null || val === undefined) return '-';
    return '$' + Math.round(Number(val)).toLocaleString('es-AR');
};
</script>

<template>
    <div class="space-y-5">
        <!-- Feedback alert -->
        <transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="feedback"
                :class="feedback.type === 'error' ? 'bg-red-500/15 border-red-500 text-red-700 dark:text-red-400' : 'bg-emerald-500/15 border-emerald-500 text-emerald-700 dark:text-emerald-400'"
                class="p-4 rounded-xl border flex items-center justify-between text-sm"
            >
                <span>{{ feedback.message }}</span>
                <button @click="feedback = null" class="text-xs font-semibold underline ml-3">Cerrar</button>
            </div>
        </transition>

        <!-- Encabezado & Filtros -->
        <div class="card p-5 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100">Gestión de Cupones</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Crea y administra promociones y códigos de descuento</p>
                </div>
                <button @click="openCreateModal" class="btn btn-primary flex items-center gap-2">
                    <SvgIcon name="plus" size="1rem" />
                    <span>Nuevo Cupón</span>
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <div class="flex-1 min-w-[200px] relative">
                    <SvgIcon name="search" size="1rem" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="filters.search"
                        type="text"
                        placeholder="Buscar por código..."
                        class="input pl-10"
                    />
                </div>
                <select v-model="filters.active" class="input max-w-[160px]">
                    <option value="">Todos los estados</option>
                    <option value="true">Activos</option>
                    <option value="false">Inactivos</option>
                </select>
            </div>
        </div>

        <!-- Tabla de Cupones -->
        <div class="card overflow-hidden">
            <div v-if="loading" class="p-8 flex justify-center">
                <LoadingSpinner />
            </div>

            <div v-else-if="coupons.length === 0" class="p-8 text-center text-slate-500 dark:text-slate-400">
                No se encontraron cupones configurados.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 uppercase text-xs">
                        <tr>
                            <th class="p-3.5">Código</th>
                            <th class="p-3.5">Tipo / Valor</th>
                            <th class="p-3.5">Mínimo Compra</th>
                            <th class="p-3.5">Usos</th>
                            <th class="p-3.5">Vencimiento</th>
                            <th class="p-3.5">Estado</th>
                            <th class="p-3.5 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="c in coupons" :key="c.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                            <td class="p-3.5 font-bold font-mono text-primary-600 dark:text-primary-400">
                                {{ c.code }}
                            </td>
                            <td class="p-3.5 font-medium">
                                <span v-if="c.type === 'percent'" class="badge badge-info">
                                    {{ c.value }}% OFF
                                </span>
                                <span v-else class="badge badge-success">
                                    -{{ formatCurrency(c.value) }}
                                </span>
                            </td>
                            <td class="p-3.5 text-slate-600 dark:text-slate-300">
                                {{ c.min_subtotal ? formatCurrency(c.min_subtotal) : 'Sin mínimo' }}
                            </td>
                            <td class="p-3.5 text-slate-600 dark:text-slate-300">
                                <div class="flex items-center gap-1.5">
                                    <span>{{ c.uses_count }} / {{ c.max_uses ? c.max_uses : '∞' }}</span>
                                    <span v-if="c.max_uses && c.uses_count >= c.max_uses" class="chip chip-danger text-[10px]">Agotado</span>
                                </div>
                            </td>
                            <td class="p-3.5 text-slate-600 dark:text-slate-300">
                                <span v-if="!c.expires_at">Sin fecha</span>
                                <span v-else-if="new Date(c.expires_at) < new Date()" class="text-rose-600 dark:text-rose-400 font-bold text-xs">Expirado ({{ new Date(c.expires_at).toLocaleDateString('es-AR') }})</span>
                                <span v-else>{{ new Date(c.expires_at).toLocaleDateString('es-AR') }}</span>
                            </td>
                            <td class="p-3.5">
                                <button
                                    @click="toggleActive(c)"
                                    :class="c.active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                                    class="px-2.5 py-1 text-xs font-semibold rounded-full transition-colors"
                                >
                                    {{ c.active ? 'Activo' : 'Inactivo' }}
                                </button>
                            </td>
                            <td class="p-3.5 text-right space-x-2">
                                <button @click="openEditModal(c)" class="btn btn-secondary text-xs px-2.5 py-1">
                                    Editar
                                </button>
                                <button @click="promptDelete(c)" class="btn btn-danger text-xs px-2.5 py-1">
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="meta.last_page > 1" class="p-4 border-t border-slate-100 dark:border-slate-800 flex justify-between items-center text-xs">
                <span class="text-slate-500">Página {{ meta.current_page }} de {{ meta.last_page }} ({{ meta.total }} registros)</span>
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

        <!-- Modal Crear/Editar accesible -->
        <BaseModal
            :show="showModal"
            :title="editingCoupon ? 'Editar Cupón' : 'Nuevo Cupón'"
            @close="showModal = false"
        >
            <form @submit.prevent="saveCoupon" class="space-y-4 text-sm">
                <div>
                    <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Código del Cupón</label>
                    <input
                        v-model="form.code"
                        type="text"
                        placeholder="Ej. VERANO2026"
                        class="input font-mono uppercase"
                        required
                    />
                    <p v-if="formErrors.code" class="text-xs text-red-500 mt-1">{{ formErrors.code[0] }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Tipo de Descuento</label>
                        <select v-model="form.type" class="input">
                            <option value="percent">Porcentaje (%)</option>
                            <option value="fixed">Monto Fijo ($)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Valor</label>
                        <input
                            v-model.number="form.value"
                            type="number"
                            step="0.01"
                            min="0.01"
                            class="input"
                            required
                        />
                        <p v-if="formErrors.value" class="text-xs text-red-500 mt-1">{{ formErrors.value[0] }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Subtotal Mínimo ($)</label>
                        <input
                            v-model.number="form.min_subtotal"
                            type="number"
                            step="0.01"
                            placeholder="Opcional"
                            class="input"
                        />
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Límite de Usos</label>
                        <input
                            v-model.number="form.max_uses"
                            type="number"
                            placeholder="Ilimitado si es vacío"
                            class="input"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Fecha Inicio</label>
                        <input
                            v-model="form.starts_at"
                            type="date"
                            class="input"
                        />
                    </div>

                    <div>
                        <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">Fecha Expiración</label>
                        <input
                            v-model="form.expires_at"
                            type="date"
                            class="input"
                        />
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <input
                        v-model="form.active"
                        type="checkbox"
                        id="active_check"
                        class="rounded text-primary-600"
                    />
                    <label for="active_check" class="font-medium text-slate-700 dark:text-slate-300">Cupón Activo</label>
                </div>

                <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button type="button" @click="showModal = false" class="btn btn-secondary">
                        Cancelar
                    </button>
                    <button type="submit" :disabled="saving" class="btn btn-primary flex items-center gap-2">
                        <LoadingSpinner v-if="saving" size="sm" />
                        <span>{{ editingCoupon ? 'Guardar Cambios' : 'Crear Cupón' }}</span>
                    </button>
                </div>
            </form>
        </BaseModal>

        <!-- Modal Confirmar Eliminación accesible -->
        <BaseModal
            :show="couponToDelete !== null"
            title="Confirmar eliminación"
            maxWidth="max-w-md"
            @close="couponToDelete = null"
        >
            <p class="text-sm text-slate-600 dark:text-slate-300">
                ¿Seguro que deseás eliminar el cupón <strong>"{{ couponToDelete?.code }}"</strong>? Esta acción no se puede deshacer.
            </p>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="couponToDelete = null" class="btn btn-secondary text-xs">
                    Cancelar
                </button>
                <button type="button" @click="executeDelete" class="btn btn-danger text-xs">
                    Sí, eliminar
                </button>
            </div>
        </BaseModal>
    </div>
</template>
