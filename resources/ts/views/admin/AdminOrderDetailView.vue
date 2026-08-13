<script setup lang="ts">
import { onMounted, ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAdminStore } from '@/stores/admin';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import SvgIcon from '@/components/SvgIcon.vue';

const admin = useAdminStore();
const route = useRoute();
const router = useRouter();

const id = Number(route.params.id);
const saving = ref(false);

const newStatus = ref<string>('');
const adminNotes = ref<string>('');
const returnStock = ref(true);

onMounted(async () => {
    await admin.fetchOrder(id);
    if (admin.currentOrder) {
        newStatus.value = admin.currentOrder.status;
        adminNotes.value = admin.currentOrder.admin_notes ?? '';
    }
});

const formatPrice = (n: number | string) =>
    '$' + Math.round(Number(n)).toLocaleString('es-AR');
const formatDate = (d: string) =>
    new Date(d).toLocaleString('es-AR', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    });

const STATUSES = [
    { v: 'pending',   t: 'Pendiente' },
    { v: 'confirmed', t: 'Confirmado' },
    { v: 'preparing', t: 'Preparando' },
    { v: 'shipped',   t: 'En camino' },
    { v: 'delivered', t: 'Entregado' },
    { v: 'cancelled', t: 'Cancelado' },
];

async function save() {
    saving.value = true;
    const payload: any = {};
    if (newStatus.value !== admin.currentOrder?.status) payload.status = newStatus.value;
    if (adminNotes.value !== (admin.currentOrder?.admin_notes ?? '')) payload.admin_notes = adminNotes.value;
    if (newStatus.value === 'cancelled') payload.return_stock = returnStock.value;

    const ok = await admin.updateOrder(id, payload);
    saving.value = false;
    if (ok) router.push({ name: 'admin-orders' });
}

const isCancelled = computed(() => admin.currentOrder?.status === 'cancelled');

// ============================================================
// Confirmación de disponibilidad + WhatsApp
// ============================================================

interface ItemAvailability {
    item_id: number;
    available: boolean | null; // null = sin marcar
    qty: number | null;
}

// Mapa local itemId → {available, qty}. Inicializa con lo que ya está en la DB.
const availability = ref<Record<number, ItemAvailability>>({});

watch(
    () => admin.currentOrder?.items,
    (items) => {
        if (!items) return;
        const next: Record<number, ItemAvailability> = {};
        for (const item of items as any[]) {
            next[item.id] = {
                item_id: item.id,
                available:
                    item.confirmed_available === undefined
                        ? null
                        : item.confirmed_available,
                qty: item.confirmed_qty ?? item.qty,
            };
        }
        availability.value = next;
    },
    { immediate: true, deep: true }
);

const reviewedCount = computed(() => {
    return Object.values(availability.value).filter((a) => a.available !== null).length;
});
const totalCount = computed(() => Object.keys(availability.value).length);
const allReviewed = computed(
    () => reviewedCount.value === totalCount.value && totalCount.value > 0
);

// Cantidades recalculadas para el preview del mensaje
const availabilitySummary = computed(() => {
    const items = (admin.currentOrder?.items ?? []) as any[];
    let total = 0;
    let availableItems = 0;
    let unavailableItems = 0;
    let pendingItems = 0;

    for (const item of items) {
        const a = availability.value[item.id];
        if (!a || a.available === null) {
            pendingItems++;
            continue;
        }
        if (a.available) {
            const qty = a.qty ?? item.qty;
            total += Number(item.price) * Number(qty);
            availableItems++;
        } else {
            unavailableItems++;
        }
    }

    return {
        total,
        availableItems,
        unavailableItems,
        pendingItems,
        canConfirm: pendingItems === 0 && items.length > 0,
    };
});

function setAvailable(itemId: number, available: boolean) {
    const cur = availability.value[itemId];
    if (!cur) return;
    cur.available = available;
    // Si lo marca como NO disponible, no necesita qty; si lo marca como disponible
    // y no tiene qty válida, usamos la original.
    if (available && (cur.qty === null || cur.qty === undefined || cur.qty < 1)) {
        const item = (admin.currentOrder?.items ?? []).find((i: any) => i.id === itemId);
        cur.qty = item?.qty ?? 1;
    }
}

function setQty(itemId: number, rawQty: string | number) {
    const cur = availability.value[itemId];
    if (!cur) return;
    const n = Number(rawQty);
    cur.qty = isNaN(n) || n < 0 ? 0 : Math.floor(n);
}

// Modal state
const showWhatsAppModal = ref(false);
const whatsappMessage = ref('');
const whatsappUrl = ref<string | null>(null);
const hasPhone = ref(false);
const confirming = ref(false);
const copied = ref(false);

async function confirmAndGenerateWhatsApp() {
    if (!availabilitySummary.value.canConfirm) return;

    confirming.value = true;
    const itemsPayload = Object.values(availability.value).map((a) => ({
        item_id: a.item_id,
        available: !!a.available,
        qty: a.available ? (a.qty ?? null) : null,
    }));

    const result = await admin.confirmOrderAvailability(id, itemsPayload, {
        admin_notes: adminNotes.value,
        auto_send: true,
    });

    confirming.value = false;
    if (result) {
        whatsappMessage.value = result.message;
        whatsappUrl.value = result.whatsapp_url;
        hasPhone.value = result.has_phone;
        showWhatsAppModal.value = true;
    }
}

async function copyMessage() {
    try {
        await navigator.clipboard.writeText(whatsappMessage.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // fallback
        const ta = document.createElement('textarea');
        ta.value = whatsappMessage.value;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    }
}

function openWhatsApp() {
    if (whatsappUrl.value) {
        window.open(whatsappUrl.value, '_blank');
    }
}

function closeModal() {
    showWhatsAppModal.value = false;
}
</script>

<template>
    <div>
        <div class="flex items-center gap-3 mb-5">
            <router-link :to="{ name: 'admin-orders' }" class="btn btn-ghost btn-sm">
                <SvgIcon name="chevron-left" size="0.85rem" />
                Pedidos
            </router-link>
            <h2 class="text-lg font-extrabold text-slate-800">
                Pedido #{{ id }}
            </h2>
            <span v-if="admin.currentOrder" class="chip" :class="{
                'chip-warning': admin.currentOrder.status === 'pending',
                'chip-info': admin.currentOrder.status === 'confirmed' || admin.currentOrder.status === 'preparing' || admin.currentOrder.status === 'shipped',
                'chip-success': admin.currentOrder.status === 'delivered',
                'chip-danger': admin.currentOrder.status === 'cancelled',
            }">
                {{ admin.currentOrder.status }}
            </span>
            <span v-if="admin.currentOrder?.confirmed_at" class="chip chip-success text-[10px]">
                Confirmado {{ formatDate(admin.currentOrder.confirmed_at) }}
            </span>
        </div>

        <LoadingSpinner v-if="admin.loading && !admin.currentOrder" text="Cargando pedido..." />

        <div v-else-if="admin.currentOrder" class="grid lg:grid-cols-3 gap-6">
            <!-- Items + envío -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Items -->
                <div class="card p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-extrabold text-slate-800">
                            Productos ({{ admin.currentOrder.items?.length || 0 }})
                        </h3>
                        <span class="text-xs text-slate-400">
                            Revisados: <strong class="text-slate-700">{{ reviewedCount }}</strong> / {{ totalCount }}
                        </span>
                    </div>

                    <ul class="divide-y divide-slate-100">
                        <li
                            v-for="item in admin.currentOrder.items"
                            :key="item.id"
                            class="flex items-start gap-4 py-3 first:pt-0 last:pb-0"
                        >
                            <!-- Checkbox de disponibilidad -->
                            <div class="flex flex-col items-center gap-1.5 pt-1 shrink-0">
                                <button
                                    type="button"
                                    @click="setAvailable(item.id, true)"
                                    :disabled="isCancelled"
                                    :class="[
                                        'w-8 h-8 rounded-lg border flex items-center justify-center transition-all cursor-pointer',
                                        availability[item.id]?.available === true
                                            ? 'bg-emerald-600 border-emerald-600 text-white shadow-md shadow-emerald-600/20'
                                            : 'border-slate-200 bg-emerald-50/50 hover:bg-emerald-50 text-emerald-600 hover:border-emerald-300',
                                        isCancelled ? 'opacity-40 cursor-not-allowed' : '',
                                    ]"
                                    :title="'Marcar como disponible'"
                                >
                                    <SvgIcon name="check" size="0.95rem" />
                                </button>
                                <button
                                    type="button"
                                    @click="setAvailable(item.id, false)"
                                    :disabled="isCancelled"
                                    :class="[
                                        'w-8 h-8 rounded-lg border flex items-center justify-center transition-all cursor-pointer',
                                        availability[item.id]?.available === false
                                            ? 'bg-rose-600 border-rose-600 text-white shadow-md shadow-rose-600/20'
                                            : 'border-slate-200 bg-rose-50/50 hover:bg-rose-50 text-rose-600 hover:border-rose-300',
                                        isCancelled ? 'opacity-40 cursor-not-allowed' : '',
                                    ]"
                                    :title="'Marcar como NO disponible'"
                                >
                                    <SvgIcon name="close" size="0.95rem" />
                                </button>
                            </div>

                            <!-- Imagen -->
                            <div class="w-12 h-12 rounded-xl bg-slate-50 overflow-hidden flex-shrink-0">
                                <img
                                    v-if="item.product?.image"
                                    :src="item.product.image"
                                    class="w-full h-full object-cover"
                                    referrerpolicy="no-referrer"
                                />
                            </div>

                            <!-- Detalle -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-slate-800 line-clamp-1">
                                    {{ item.product?.name || `Producto #${item.product_id}` }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ item.qty }} × {{ formatPrice(item.price) }}
                                </p>
                                <div
                                    v-if="availability[item.id]?.available === true"
                                    class="mt-2 flex items-center gap-2"
                                >
                                    <label class="text-[10px] uppercase font-extrabold tracking-wider text-slate-500">
                                        Confirmadas:
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        :max="item.qty"
                                        :value="availability[item.id]?.qty ?? item.qty"
                                        @input="setQty(item.id, ($event.target as HTMLInputElement).value)"
                                        class="w-16 px-2 py-1 text-xs font-bold border border-slate-200 rounded-lg focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-100"
                                    />
                                    <span class="text-[10px] text-slate-400">/ {{ item.qty }} pedidas</span>
                                </div>
                            </div>

                            <!-- Subtotal -->
                            <div class="text-right shrink-0">
                                <p class="font-black text-slate-800">
                                    {{ formatPrice(Number(item.price) * Number(availability[item.id]?.qty ?? item.qty)) }}
                                </p>
                                <span
                                    v-if="availability[item.id]?.available === true && availability[item.id]?.qty !== item.qty"
                                    class="text-[10px] text-amber-600 font-bold"
                                >
                                    parcial
                                </span>
                            </div>
                        </li>
                    </ul>

                    <div class="mt-4 pt-4 border-t border-slate-100 flex justify-between items-baseline">
                        <span class="font-bold text-slate-800">Total a confirmar</span>
                        <span class="text-2xl font-black text-slate-800">
                            {{ formatPrice(availabilitySummary.total) }}
                        </span>
                    </div>

                    <!-- Resumen + botón confirmar -->
                    <div class="mt-5 pt-4 border-t border-slate-100 space-y-3">
                        <div class="flex flex-wrap items-center gap-3 text-xs">
                            <span v-if="availabilitySummary.availableItems > 0" class="chip chip-success">
                                <SvgIcon name="check" size="0.7rem" />
                                {{ availabilitySummary.availableItems }} disponible(s)
                            </span>
                            <span v-if="availabilitySummary.unavailableItems > 0" class="chip chip-danger">
                                <SvgIcon name="close" size="0.7rem" />
                                {{ availabilitySummary.unavailableItems }} no disponible(s)
                            </span>
                            <span v-if="availabilitySummary.pendingItems > 0" class="chip chip-warning">
                                {{ availabilitySummary.pendingItems }} sin revisar
                            </span>
                        </div>

                        <button
                            @click="confirmAndGenerateWhatsApp"
                            :disabled="!availabilitySummary.canConfirm || confirming || isCancelled"
                            class="btn btn-primary btn-lg w-full"
                            :class="{ 'opacity-50 cursor-not-allowed': !availabilitySummary.canConfirm }"
                        >
                            <SvgIcon name="whatsapp" size="1.1rem" />
                            <span v-if="confirming">Generando mensaje...</span>
                            <span v-else-if="!availabilitySummary.canConfirm">
                                Marcá todos los productos para continuar
                            </span>
                            <span v-else>Confirmar y preparar WhatsApp</span>
                        </button>

                        <p v-if="!admin.currentOrder.customer_phone" class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-2.5">
                            �️ Este pedido no tiene teléfono cargado. Solo podrás copiar el mensaje manualmente.
                        </p>
                    </div>
                </div>

                <!-- Envío -->
                <div class="card p-6">
                    <h3 class="text-sm font-extrabold text-slate-800 mb-4">Datos de envío</h3>
                    <div class="grid sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-[10px] uppercase font-extrabold tracking-wider text-slate-400 mb-1">Cliente</p>
                            <p class="font-bold text-slate-800">{{ admin.currentOrder.customer_full_name || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase font-extrabold tracking-wider text-slate-400 mb-1">Teléfono</p>
                            <p class="font-semibold text-slate-700">
                                {{ admin.currentOrder.customer_phone || '—' }}
                                <a
                                    v-if="admin.currentOrder.customer_phone"
                                    :href="`https://wa.me/${admin.currentOrder.customer_phone.replace(/\D/g, '')}`"
                                    target="_blank"
                                    class="ml-1 text-emerald-600 hover:text-emerald-700 inline-flex items-center gap-0.5 text-xs font-bold"
                                >
                                    <SvgIcon name="whatsapp" size="0.8rem" />
                                </a>
                            </p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-[10px] uppercase font-extrabold tracking-wider text-slate-400 mb-1">Dirección</p>
                            <p class="font-semibold text-slate-700">
                                {{ admin.currentOrder.customer_address || '—' }}<br />
                                <span class="text-slate-500 text-xs">
                                    {{ admin.currentOrder.customer_city }} {{ admin.currentOrder.customer_zip }}
                                </span>
                            </p>
                        </div>
                        <div v-if="admin.currentOrder.customer_notes" class="sm:col-span-2">
                            <p class="text-[10px] uppercase font-extrabold tracking-wider text-slate-400 mb-1">Notas del cliente</p>
                            <p class="text-sm text-slate-700 italic bg-slate-50 rounded-lg p-3 whitespace-pre-line">
                                {{ admin.currentOrder.customer_notes }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acciones -->
            <aside class="space-y-4">
                <div class="card p-6 space-y-5">
                    <h3 class="text-sm font-extrabold text-slate-800">Cambiar estado</h3>
                    <div>
                        <label class="label">Estado</label>
                        <select v-model="newStatus" class="input" :disabled="isCancelled">
                            <option v-for="s in STATUSES" :key="s.v" :value="s.v">{{ s.t }}</option>
                        </select>
                    </div>

                    <div v-if="newStatus === 'cancelled' && !isCancelled">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="returnStock" class="w-4 h-4 text-brand-600 rounded" />
                            <span class="text-xs font-semibold text-slate-700">Devolver stock al inventario</span>
                        </label>
                    </div>

                    <div>
                        <label class="label">Notas internas (solo admin)</label>
                        <textarea
                            v-model="adminNotes"
                            class="input min-h-[100px]"
                            placeholder="Ej: Modifiqué 2 unidades porque el stock era insuficiente..."
                        ></textarea>
                    </div>

                    <div class="flex flex-col gap-2 pt-2">
                        <button @click="save" class="btn btn-primary" :disabled="saving">
                            {{ saving ? 'Guardando...' : 'Guardar cambios' }}
                        </button>
                        <router-link :to="{ name: 'admin-orders' }" class="btn btn-ghost">
                            Volver
                        </router-link>
                    </div>
                </div>

                <div class="card p-5 bg-slate-50/60">
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 mb-2">Información</p>
                    <dl class="space-y-1.5 text-xs">
                        <div class="flex justify-between">
                            <dt class="text-slate-500">ID</dt>
                            <dd class="font-bold text-slate-700">#{{ admin.currentOrder.id }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">User ID</dt>
                            <dd class="font-bold text-slate-700">#{{ admin.currentOrder.user_id }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Origen</dt>
                            <dd class="font-bold text-slate-700">{{ admin.currentOrder.origin_label }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Items Daz</dt>
                            <dd class="font-bold text-slate-700">{{ admin.currentOrder.items_count_daz }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Items Tuc</dt>
                            <dd class="font-bold text-slate-700">{{ admin.currentOrder.items_count_tuc }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Items manual</dt>
                            <dd class="font-bold text-slate-700">{{ admin.currentOrder.items_count_manual }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Total original</dt>
                            <dd class="font-bold text-slate-700">{{ formatPrice(admin.currentOrder.total) }}</dd>
                        </div>
                        <div v-if="admin.currentOrder.confirmed_at" class="flex justify-between">
                            <dt class="text-slate-500">Confirmado</dt>
                            <dd class="font-bold text-slate-700">{{ formatDate(admin.currentOrder.confirmed_at) }}</dd>
                        </div>
                        <div v-if="admin.currentOrder.whatsapp_last_sent_at" class="flex justify-between">
                            <dt class="text-slate-500">Último WhatsApp</dt>
                            <dd class="font-bold text-slate-700">{{ formatDate(admin.currentOrder.whatsapp_last_sent_at) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Creado</dt>
                            <dd class="font-bold text-slate-700">{{ formatDate(admin.currentOrder.created_at) }}</dd>
                        </div>
                    </dl>
                </div>
            </aside>
        </div>

        <!-- ============== Modal WhatsApp ============== -->
        <transition name="modal">
            <div
                v-if="showWhatsAppModal"
                class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                @click.self="closeModal"
            >
                <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full max-h-[90vh] flex flex-col overflow-hidden">
                    <!-- Header -->
                    <div class="flex items-center justify-between p-5 border-b border-slate-100 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                                <SvgIcon name="whatsapp" size="1.3rem" />
                            </div>
                            <div>
                                <h3 class="font-extrabold text-base">Mensaje para WhatsApp</h3>
                                <p class="text-[11px] text-white/80">Pedido #{{ id }}</p>
                            </div>
                        </div>
                        <button
                            @click="closeModal"
                            class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-colors cursor-pointer"
                        >
                            <SvgIcon name="close" size="1rem" />
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="flex-1 overflow-y-auto p-5 bg-slate-50">
                        <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100">
                            <pre class="whitespace-pre-wrap font-sans text-sm text-slate-800 leading-relaxed">{{ whatsappMessage }}</pre>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-5 border-t border-slate-100 bg-white">
                        <div class="flex flex-col sm:flex-row gap-2">
                            <button
                                @click="copyMessage"
                                class="btn btn-secondary flex-1"
                            >
                                <SvgIcon :name="copied ? 'check' : 'copy'" size="0.95rem" />
                                {{ copied ? '¡Copiado!' : 'Copiar mensaje' }}
                            </button>
                            <button
                                v-if="hasPhone && whatsappUrl"
                                @click="openWhatsApp"
                                class="btn flex-1 text-white shadow-md"
                                style="background: linear-gradient(135deg, #25d366, #128c7e);"
                            >
                                <SvgIcon name="whatsapp" size="1.05rem" />
                                Abrir WhatsApp
                            </button>
                            <button
                                v-else
                                disabled
                                class="btn btn-secondary flex-1 opacity-50 cursor-not-allowed"
                                title="El cliente no tiene teléfono cargado"
                            >
                                <SvgIcon name="whatsapp" size="1.05rem" />
                                Sin teléfono
                            </button>
                        </div>
                        <button @click="closeModal" class="btn btn-ghost w-full mt-2">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
