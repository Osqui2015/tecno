<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'qty',
        'price',
        'confirmed_available',
        'confirmed_qty',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'qty' => 'integer',
        'confirmed_qty' => 'integer',
        'confirmed_available' => 'boolean',
    ];

    /**
     * Cantidad efectiva para el mensaje / total final.
     * Si el admin confirmó una cantidad parcial, usa esa; si no, usa la original.
     */
    public function getEffectiveQtyAttribute(): int
    {
        return $this->confirmed_qty ?? $this->qty;
    }

    /**
     * Subtotal efectivo (qty efectivo × precio unitario).
     */
    public function getEffectiveSubtotalAttribute(): float
    {
        return (float) $this->price * $this->effective_qty;
    }

    /**
     * ¿Este item fue revisado por el admin?
     */
    public function isReviewed(): bool
    {
        return $this->confirmed_available !== null;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
