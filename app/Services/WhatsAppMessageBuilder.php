<?php

namespace App\Services;

use App\Models\Order;

/**
 * Genera el mensaje de WhatsApp que el admin envia al cliente
 * luego de confirmar la disponibilidad de los productos del pedido.
 *
 * El mensaje se arma en base a:
 *   - order_items.confirmed_available (true/false/null)
 *   - order_items.confirmed_qty       (cantidad confirmada, puede ser parcial)
 *
 * Casos:
 *   - Todos disponibles       -> muestra solo lista de disponibles.
 *   - Todos no disponibles    -> mensaje "no hay stock".
 *   - Mixto                   -> muestra ambas secciones + total recalculado.
 */
class WhatsAppMessageBuilder
{
    public function __construct(
        private readonly Order $order
    ) {}

    /**
     * Limpia emojis problematicos para que se vean bien en WhatsApp.
     * Quita el variation selector-16 (FE0F) y reemplaza emojis
     * que en algunas versiones se renderizan como "" (tofu).
     */
    private function sanitize(string $message): string
    {
        // Quitar variation selector-16 (FE0F)
        $message = preg_replace('/\x{FE0F}/u', '', $message);

        // Reemplazar emojis problematicos por alternativas 100% portables.
        $replacements = [
            "\u{1F44B}" => '',         // mano saludando -> nada
            "\u{2705}"  => '[OK]',     // checkmark en cuadro -> texto
            "\u{274C}"  => '[X]',      // cross en cuadro -> texto
            "\u{1F4B0}" => '$',        // money bag -> signo pesos
            "\u{1F4CD}" => '>>',       // pushpin redondo -> flecha
            "\u{1F64F}" => '',         // folded hands -> nada
            "\u{1F525}" => '*',        // fuego -> asterisco
        ];

        $message = str_replace(array_keys($replacements), array_values($replacements), $message);

        return $message;
    }

    /**
     * Devuelve el texto del mensaje listo para enviar por WhatsApp.
     */
    public function build(): string
    {
        $items = $this->order->items()->with('product')->get();

        $available = $items->where('confirmed_available', true)->values();
        $unavailable = $items->where('confirmed_available', false)->values();
        $pending = $items->whereNull('confirmed_available')->values();

        $firstName = $this->firstName($this->order->customer_name);
        $greeting = $firstName !== '' ? "Hola {$firstName}!" : 'Hola!';
        $orderRef = '#' . $this->order->id;
        $storeName = config('store.name', 'Tecno-Rexs');

        // Si hay items sin revisar, el admin aun no termino -> mensaje "borrador".
        if ($pending->count() > 0) {
            return $this->sanitize($greeting . "\n\n"
                . "Estamos revisando la disponibilidad de tu pedido {$orderRef}. "
                . "Te confirmaremos a la brevedad.\n\n"
                . "-- {$storeName}");
        }

        // Caso: TODO no disponible
        if ($available->isEmpty()) {
            $lines = [];
            foreach ($unavailable as $item) {
                $name = $item->product?->name ?? "Producto #{$item->product_id}";
                $lines[] = "- {$item->qty}x {$name}";
            }
            $body = $lines ? implode("\n", $lines) : '-';

            return $this->sanitize($greeting . "\n\n"
                . "Lamentamos informarte que estos productos no se encuentran disponibles:\n\n"
                . "[X] NO DISPONIBLES:\n"
                . $body . "\n\n"
                . "Por favor contactanos para ofrecerte alternativas. Disculpa las molestias.\n\n"
                . "-- {$storeName}");
        }

        // Caso: TODO disponible (o mixto)
        $lines = [];
        $total = 0.0;
        foreach ($available as $item) {
            $name = $item->product?->name ?? "Producto #{$item->product_id}";
            $qty = (int) ($item->confirmed_qty ?? $item->qty);
            $price = (float) $item->price;
            $subtotal = $qty * $price;
            $total += $subtotal;

            $partial = '';
            if ($qty < (int) $item->qty) {
                $partial = ' (de ' . $item->qty . ' solicitados)';
            }

            $lines[] = "- {$qty}x {$name}{$partial} - \$" . $this->money($subtotal);
        }
        $productsBlock = implode("\n", $lines);

        $header = "DISPONIBLES:\n";

        $msg = $greeting . "\n\n"
            . "Tu pedido {$orderRef} ha sido confirmado. Pronto a retirar.\n\n"
            . $header
            . $productsBlock . "\n";

        // Si hay NO disponibles, los anadimos
        if ($unavailable->isNotEmpty()) {
            $unavailableLines = [];
            foreach ($unavailable as $item) {
                $name = $item->product?->name ?? "Producto #{$item->product_id}";
                $unavailableLines[] = "- {$item->qty}x {$name}";
            }
            $msg .= "\n[X] NO DISPONIBLES:\n" . implode("\n", $unavailableLines) . "\n";
        }

        // Total a pagar (solo items disponibles)
        $msg .= "\nTOTAL A PAGAR: \$" . $this->money($total) . "\n";

        // Direccion del local
        $address = config('store.address');
        if ($address) {
            $msg .= "\nTe esperamos en {$address} para que retires tu pedido.\n";
        } else {
            $msg .= "\nTe esperamos en el local para que retires tu pedido.\n";
        }

        $msg .= "\nGracias por tu compra!\n\n-- {$storeName}";

        return $this->sanitize($msg);
    }

    /**
     * Construye la URL wa.me lista para abrir en WhatsApp Web / app.
     * Si el cliente no tiene telefono, devuelve null.
     */
    public function whatsappUrl(): ?string
    {
        $phone = $this->normalizePhone($this->order->customer_phone);
        if (! $phone) {
            return null;
        }
        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($this->build());
    }

    /**
     * Normaliza el telefono a formato wa.me (solo digitos, con 549 si es AR movil).
     */
    private function normalizePhone(?string $phone): ?string
    {
        if (! $phone) return null;

        // Quitar todo lo que no sea digito
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) < 8) return null;

        // Si ya empieza con 549 (Argentina movil), dejar.
        // Si empieza con 54 (sin el 9), agregarlo.
        // Si no empieza con 54, agregarlo.
        if (str_starts_with($digits, '549')) {
            return $digits;
        }
        if (str_starts_with($digits, '54')) {
            return '549' . substr($digits, 2);
        }
        return '549' . $digits;
    }

    private function firstName(?string $full): string
    {
        if (! $full) return '';
        $parts = preg_split('/\s+/', trim($full)) ?: [];
        return $parts[0] ?? '';
    }

    private function money(float $n): string
    {
        return number_format($n, 0, ',', '.');
    }
}
