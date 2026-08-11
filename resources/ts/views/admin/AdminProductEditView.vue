<script setup lang="ts">
import { onMounted, ref, reactive, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAdminStore } from '@/stores/admin';
import LoadingSpinner from '@/components/LoadingSpinner.vue';
import SvgIcon from '@/components/SvgIcon.vue';

const admin = useAdminStore();
const route = useRoute();
const router = useRouter();

const id = Number(route.params.id);
const loading = ref(true);
const saving = ref(false);

const form = reactive({
    name: '',
    description: '',
    price: 0,
    markup_percent: 0,
    stock: 0,
    category_id: null as number | null,
    sku: '',
    active: true,
});

onMounted(async () => {
    loading.value = true;
    await admin.fetchCategories();
    await admin.fetchProduct(id);
    const p = admin.currentProduct;
    if (p) {
        form.name = p.name;
        form.description = p.description ?? '';
        form.price = Number(p.price);
        form.markup_percent = Number(p.markup_percent ?? 0);
        form.stock = p.stock;
        form.category_id = p.category_id;
        form.sku = p.sku ?? '';
        form.active = p.active;
    }
    loading.value = false;
});

const finalPrice = computed(() =>
    (form.price * (1 + form.markup_percent / 100)).toFixed(2)
);

async function save() {
    saving.value = true;
    const payload = { ...form };
    if (payload.category_id == null) delete (payload as any).category_id;
    const ok = await admin.updateProduct(id, payload);
    saving.value = false;
    if (ok) {
        router.push({ name: 'admin-products' });
    }
}

const formatPrice = (n: number) =>
    '$' + Math.round(n).toLocaleString('es-AR');
</script>

<template>
    <div>
        <div class="flex items-center gap-3 mb-5">
            <router-link :to="{ name: 'admin-products' }" class="btn btn-ghost btn-sm">
                <SvgIcon name="chevron-left" size="0.85rem" />
                Productos
            </router-link>
            <h2 class="text-lg font-extrabold text-slate-800">Editar producto</h2>
        </div>

        <LoadingSpinner v-if="loading" text="Cargando producto..." />

        <div v-else class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 card p-6 space-y-5">
                <div>
                    <label class="label">Nombre</label>
                    <input v-model="form.name" class="input" />
                </div>
                <div>
                    <label class="label">Descripción</label>
                    <textarea v-model="form.description" class="input min-h-[100px]"></textarea>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">SKU</label>
                        <input v-model="form.sku" class="input" />
                    </div>
                    <div>
                        <label class="label">Categoría</label>
                        <select v-model.number="form.category_id" class="input">
                            <option v-for="c in admin.productCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                </div>

                <hr class="border-slate-100" />

                <div>
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-500 mb-3">Precio y stock</h3>
                </div>

                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <label class="label">
                            Precio base
                            <span v-if="admin.currentProduct?.origin === 'daz'"> (Daz)</span>
                            <span v-else-if="admin.currentProduct?.origin === 'tuc'"> (TusTecnología)</span>
                            <span v-else> (Manual)</span>
                        </label>
                        <input v-model.number="form.price" type="number" step="0.01" min="0" class="input" />
                        <p class="text-[10px] text-slate-400 mt-1">
                            Lo que cuesta en
                            <span v-if="admin.currentProduct?.origin === 'daz'"> Daz.</span>
                            <span v-else-if="admin.currentProduct?.origin === 'tuc'"> TusTecnología.</span>
                            <span v-else> origen.</span>
                        </p>
                    </div>
                    <div>
                        <label class="label">Markup %</label>
                        <input v-model.number="form.markup_percent" type="number" step="0.01" min="0" max="999.99" class="input" />
                        <p class="text-[10px] text-slate-400 mt-1">% que se suma al base.</p>
                    </div>
                    <div>
                        <label class="label">Precio final</label>
                        <div class="input bg-emerald-50 border-emerald-200 font-black text-emerald-700 flex items-center">
                            {{ formatPrice(Number(finalPrice)) }}
                        </div>
                        <p class="text-[10px] text-emerald-600 mt-1">Lo que paga el cliente.</p>
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Stock</label>
                        <input v-model.number="form.stock" type="number" min="0" class="input" />
                    </div>
                    <div>
                        <label class="label">Estado</label>
                        <label class="flex items-center gap-3 mt-2 cursor-pointer">
                            <input type="checkbox" v-model="form.active" class="w-4 h-4 text-brand-600 rounded" />
                            <span class="text-sm font-semibold text-slate-700">{{ form.active ? 'Activo (visible)' : 'Inactivo (oculto)' }}</span>
                        </label>
                    </div>
                </div>

                <hr class="border-slate-100" />

                <div class="flex gap-3 pt-2">
                    <button @click="save" class="btn btn-primary" :disabled="saving">
                        {{ saving ? 'Guardando...' : 'Guardar cambios' }}
                    </button>
                    <router-link :to="{ name: 'admin-products' }" class="btn btn-ghost">
                        Cancelar
                    </router-link>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="card p-5">
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-2">Imagen actual</p>
                    <div class="aspect-square bg-slate-50 rounded-xl overflow-hidden">
                        <img
                            v-if="admin.currentProduct?.image"
                            :src="admin.currentProduct.image"
                            class="w-full h-full object-cover"
                            referrerpolicy="no-referrer"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center text-slate-400">
                            <SvgIcon name="box" size="2rem" />
                        </div>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-2 text-center">La imagen viene del scraper.</p>
                </div>

                <div v-if="admin.currentProduct?.origin === 'daz'" class="card p-5 bg-blue-50/40 border-blue-100">
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-blue-500 mb-1">Origen</p>
                    <p class="text-sm font-bold text-slate-800">Producto Daz</p>
                    <p class="text-[11px] text-slate-500 mt-1">ID Daz: <code>{{ admin.currentProduct.external_id }}</code></p>
                </div>
                <div v-else-if="admin.currentProduct?.origin === 'tuc'" class="card p-5 bg-amber-50/40 border-amber-100">
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-amber-500 mb-1">Origen</p>
                    <p class="text-sm font-bold text-slate-800">Producto TusTecnología</p>
                    <p class="text-[11px] text-slate-500 mt-1">ID Tuc: <code>{{ admin.currentProduct.external_id }}</code></p>
                </div>
            </aside>
        </div>
    </div>
</template>
