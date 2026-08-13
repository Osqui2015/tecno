<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStockHistory extends Model
{
    use HasFactory;

    public const UPDATED_AT = null; // solo created_at

    protected $table = 'product_stock_history';

    protected $fillable = [
        'product_id',
        'stock_before',
        'stock_after',
        'source',
        'reference',
        'actor_id',
    ];

    protected $casts = [
        'stock_before' => 'integer',
        'stock_after'  => 'integer',
        'created_at'   => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function getDeltaAttribute(): int
    {
        return ($this->stock_after ?? 0) - ($this->stock_before ?? 0);
    }
}
