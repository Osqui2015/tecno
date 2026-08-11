<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED   = 'fixed';

    protected $fillable = [
        'code', 'type', 'value', 'min_subtotal', 'max_uses',
        'uses_count', 'starts_at', 'expires_at', 'active',
    ];

    protected $casts = [
        'value'        => 'decimal:2',
        'min_subtotal' => 'decimal:2',
        'max_uses'     => 'integer',
        'uses_count'   => 'integer',
        'active'       => 'boolean',
        'starts_at'    => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function isAvailable(): bool
    {
        if (! $this->active) return false;
        if ($this->starts_at && now()->lt($this->starts_at)) return false;
        if ($this->expires_at && now()->gt($this->expires_at)) return false;
        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) return false;
        return true;
    }

    /**
     * Calcula el descuento para un subtotal.
     */
    public function discountFor(float $subtotal): float
    {
        if ($this->min_subtotal !== null && $subtotal < (float) $this->min_subtotal) {
            return 0;
        }

        $discount = match ($this->type) {
            self::TYPE_PERCENT => $subtotal * ((float) $this->value / 100),
            self::TYPE_FIXED   => (float) $this->value,
            default            => 0,
        };

        // El descuento no puede superar el subtotal
        return min(round($discount, 2), $subtotal);
    }
}
