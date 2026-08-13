<?php

/**
 * Configuración del local / tienda para mensajes automáticos.
 * (WhatsApp, emails, comprobantes, etc.)
 */

return [
    'name'         => env('STORE_NAME', 'Tecno-Rexs'),
    'address'      => env('STORE_ADDRESS', 'Av. Aconquija 1234, San Miguel de Tucumán'),
    'phone'        => env('STORE_PHONE', '+54 381 555-1234'),
    // Número de WhatsApp de la tienda para el botón flotante y el checkout.
    // Formato wa.me: solo dígitos, con 549 si es AR celular.
    'whatsapp_number' => env('STORE_WHATSAPP', '5493813150800'),
    'min_purchase' => (float) env('STORE_MIN_PURCHASE', 50000),
];
