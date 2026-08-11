<?php

/**
 * Configuración del local / tienda para mensajes automáticos.
 * (WhatsApp, emails, comprobantes, etc.)
 */

return [
    'name'         => env('STORE_NAME', 'Tecno-Rexs'),
    'address'      => env('STORE_ADDRESS', 'Av. Aconquija 1234, San Miguel de Tucumán'),
    'phone'        => env('STORE_PHONE', '+54 381 555-1234'),
    'min_purchase' => (float) env('STORE_MIN_PURCHASE', 50000),
];
