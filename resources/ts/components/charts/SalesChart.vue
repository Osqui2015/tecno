<script setup lang="ts">
import { computed } from 'vue';

interface SalesPoint {
    date: string;
    orders_count: number;
    revenue: number | string;
}

const props = defineProps<{
    data: SalesPoint[];
}>();

const formatCurrency = (val: number) => {
    return '$' + Math.round(val).toLocaleString('es-AR');
};

const chartPoints = computed(() => {
    if (!props.data || props.data.length === 0) return { path: '', points: [], maxRevenue: 1 };

    const revenues = props.data.map(d => Number(d.revenue));
    const maxRevenue = Math.max(...revenues, 100);
    const width = 600;
    const height = 180;
    const padding = 20;

    const points = props.data.map((d, index) => {
        const x = padding + (index / Math.max(props.data.length - 1, 1)) * (width - 2 * padding);
        const y = height - padding - (Number(d.revenue) / maxRevenue) * (height - 2 * padding);
        return { x, y, date: d.date, revenue: Number(d.revenue), orders: d.orders_count };
    });

    const path = points.reduce((acc, p, i) => {
        return i === 0 ? `M ${p.x} ${p.y}` : `${acc} L ${p.x} ${p.y}`;
    }, '');

    // Path cerrado para gradiente de área
    const areaPath = points.length > 0
        ? `${path} L ${points[points.length - 1].x} ${height - padding} L ${points[0].x} ${height - padding} Z`
        : '';

    return { path, areaPath, points, maxRevenue };
});
</script>

<template>
    <div class="card p-5 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-bold text-zinc-800 dark:text-slate-100 text-sm">Tendencia de Ventas (Últimos 30 días)</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Ingresos diarios acumulados</p>
            </div>
            <div class="text-right" v-if="chartPoints.points.length > 0">
                <span class="text-xs text-slate-500 dark:text-slate-400">Pico máximo:</span>
                <span class="block text-sm font-bold text-emerald-600 dark:text-emerald-400">
                    {{ formatCurrency(chartPoints.maxRevenue) }}
                </span>
            </div>
        </div>

        <div v-if="data.length === 0" class="h-44 flex items-center justify-center text-xs text-slate-500 dark:text-slate-400">
            No hay datos de ventas registrados en este período.
        </div>

        <div v-else class="relative w-full overflow-hidden">
            <svg viewBox="0 0 600 180" class="w-full h-44 overflow-visible">
                <defs>
                    <linearGradient id="chartGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#8b5cf6" stop-opacity="0.35" />
                        <stop offset="100%" stop-color="#8b5cf6" stop-opacity="0.0" />
                    </linearGradient>
                </defs>

                <!-- Grid de fondo: slate-200 en light, slate-800 en dark (suficiente contraste) -->
                <line x1="20" y1="20" x2="580" y2="20" stroke="currentColor" class="text-slate-200 dark:text-slate-800" stroke-width="1" stroke-dasharray="4" />
                <line x1="20" y1="90" x2="580" y2="90" stroke="currentColor" class="text-slate-200 dark:text-slate-800" stroke-width="1" stroke-dasharray="4" />
                <line x1="20" y1="160" x2="580" y2="160" stroke="currentColor" class="text-slate-200 dark:text-slate-800" stroke-width="1" />

                <!-- Área sombreada -->
                <path :d="chartPoints.areaPath" fill="url(#chartGradient)" />

                <!-- Línea de gráfico -->
                <path :d="chartPoints.path" fill="none" stroke="#8b5cf6" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />

                <!-- Puntos interactivos -->
                <g v-for="(p, i) in chartPoints.points" :key="i">
                    <circle
                        :cx="p.x"
                        :cy="p.y"
                        r="4"
                        class="fill-purple-600 dark:fill-purple-400 stroke-white dark:stroke-slate-900 stroke-2 hover:r-6 transition-all cursor-pointer"
                    >
                        <title>{{ p.date }}: {{ formatCurrency(p.revenue) }} ({{ p.orders }} pedidos)</title>
                    </circle>
                </g>
            </svg>

            <!-- Fechas eje X -->
            <div class="flex justify-between text-[10px] text-slate-500 dark:text-slate-400 pt-2 px-1">
                <span>{{ data[0]?.date }}</span>
                <span>{{ data[Math.floor(data.length / 2)]?.date }}</span>
                <span>{{ data[data.length - 1]?.date }}</span>
            </div>
        </div>
    </div>
</template>
