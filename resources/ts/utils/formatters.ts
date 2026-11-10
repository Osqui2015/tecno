/**
 * Formateadores centralizados para moneda, números y fechas.
 * Reutiliza instancias de Intl.NumberFormat y Intl.DateTimeFormat
 * para máximo rendimiento sin instanciaciones repetitivas por render.
 */

const currencyFormatter = new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
});

export function formatCurrency(value: number | string | null | undefined): string {
    if (value === null || value === undefined || value === '') return '$0';
    const num = Number(value);
    if (isNaN(num)) return '$0';
    return currencyFormatter.format(num);
}

export function formatDate(value: string | Date | null | undefined, options?: Intl.DateTimeFormatOptions): string {
    if (!value) return '-';
    const date = typeof value === 'string' ? new Date(value) : value;
    if (isNaN(date.getTime())) return '-';
    return date.toLocaleDateString('es-AR', options ?? {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export function formatDateTime(value: string | Date | null | undefined): string {
    if (!value) return '-';
    const date = typeof value === 'string' ? new Date(value) : value;
    if (isNaN(date.getTime())) return '-';
    return date.toLocaleString('es-AR', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
