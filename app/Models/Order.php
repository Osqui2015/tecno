<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    /** Estados posibles del pedido. */
    public const STATUS_PENDING   = 'pending';    // recién creado por el comprador
    public const STATUS_CONFIRMED = 'confirmed';  // el admin lo vio y lo aceptó
    public const STATUS_PREPARING = 'preparing';  // preparándose para enviar
    public const STATUS_SHIPPED   = 'shipped';    // despachado
    public const STATUS_DELIVERED = 'delivered';  // entregado
    public const STATUS_CANCELLED = 'cancelled';  // cancelado (por admin o comprador)

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_PREPARING,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    /**
     * Estados en los que el comprador puede cancelar el pedido.
     */
    public const CANCELLABLE_BY_BUYER = [
        self::STATUS_PENDING,
    ];

    protected $fillable = [
        'user_id',
        'total',
        'discount',
        'subtotal',
        'coupon_id',
        'status',
        'shipping_address',
        // Snapshot del cliente al momento de la compra
        'customer_name',
        'customer_lastname',
        'customer_phone',
        'customer_address',
        'customer_city',
        'customer_zip',
        'customer_notes',
        // Notas internas admin
        'admin_notes',
        // Auditoría de confirmación
        'confirmed_at',
        'confirmed_by',
        'whatsapp_last_sent_at',
    ];

    protected $casts = [
        'total'                 => 'decimal:2',
        'discount'              => 'decimal:2',
        'subtotal'              => 'decimal:2',
        'confirmed_at'          => 'datetime',
        'whatsapp_last_sent_at' => 'datetime',
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Accessors que se incluyen automáticamente al serializar a JSON/array.
     */
    protected $appends = [
        'origin_label',
        'items_count_daz',
        'items_count_tuc',
        'items_count_manual',
        'customer_full_name',
    ];

    // ============================================================
    //  Relaciones
    // ============================================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // ============================================================
    //  Helpers de estado
    // ============================================================

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canBeCancelledByBuyer(): bool
    {
        return in_array($this->status, self::CANCELLABLE_BY_BUYER, true);
    }

    // ============================================================
    //  Accessors de origen (Daz / Manual / Mixto)
    // ============================================================

    /**
     * Cantidad de items del pedido cuyo producto es de Daz.
     */
    public function getItemsCountDazAttribute(): int
    {
        return $this->items->filter(fn ($i) => $i->product?->origin === 'daz')->count();
    }

    /**
     * Cantidad de items del pedido cuyo producto es de TusTecnología.
     */
    public function getItemsCountTucAttribute(): int
    {
        return $this->items->filter(fn ($i) => $i->product?->origin === 'tuc')->count();
    }

    /**
     * Cantidad de items del pedido cuyo producto es manual.
     */
    public function getItemsCountManualAttribute(): int
    {
        return $this->items->filter(fn ($i) => empty($i->product?->origin) || $i->product?->origin === 'manual')->count();
    }

    /**
     * Etiqueta legible del origen: 'daz' | 'tuc' | 'manual' | 'mixed'.
     */
    public function getOriginLabelAttribute(): string
    {
        $daz    = $this->items_count_daz;
        $tuc    = $this->items_count_tuc;
        $manual = $this->items_count_manual;

        $origins = [];
        if ($daz > 0) $origins[] = 'daz';
        if ($tuc > 0) $origins[] = 'tuc';
        if ($manual > 0) $origins[] = 'manual';

        if (count($origins) === 1) {
            return $origins[0];
        } elseif (count($origins) > 1) {
            return 'mixed';
        }
        return 'empty';
    }

    /**
     * Nombre completo del cliente (para admin).
     */
    public function getCustomerFullNameAttribute(): string
    {
        return trim(($this->customer_name ?? '') . ' ' . ($this->customer_lastname ?? ''));
    }
}
