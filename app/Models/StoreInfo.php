<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Datos editables de la tienda (WhatsApp, dirección, redes, etc).
 *
 * Es un modelo singleton: la tabla siempre tiene 1 solo registro (id=1)
 * que es la fuente de verdad. Usá `StoreInfo::current()` para obtenerlo.
 *
 * El config('store.*') se sincroniza con esta tabla en AppServiceProvider,
 * por lo que el resto del código (OrderController, WhatsAppMessageBuilder,
 * etc.) sigue funcionando sin cambios.
 */
class StoreInfo extends Model
{
    protected $table = 'store_infos';

    protected $fillable = [
        'name',
        'address',
        'phone',
        'whatsapp_number',
        'instagram_url',
        'facebook_url',
        'tiktok_url',
        'email_contact',
        'schedule',
        'short_description',
        'min_purchase',
    ];

    protected $casts = [
        'min_purchase' => 'decimal:2',
    ];

    /**
     * Devuelve el registro único. Si la tabla está vacía, crea uno con
     * los defaults del config (útil para installs frescos o tests).
     */
    public static function current(): self
    {
        $instance = static::first();
        if (! $instance) {
            $instance = static::create([
                'name'            => config('store.name', 'Tecno-Rexs'),
                'address'         => config('store.address'),
                'phone'           => config('store.phone'),
                'whatsapp_number' => config('store.whatsapp_number'),
                'min_purchase'    => config('store.min_purchase', 50000),
            ]);
        }
        return $instance;
    }

    /**
     * Devuelve solo dígitos del número de WhatsApp (formato wa.me).
     * Si está vacío, devuelve null.
     */
    public function whatsappDigits(): ?string
    {
        if (! $this->whatsapp_number) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $this->whatsapp_number);
        return $digits !== '' ? $digits : null;
    }
}
