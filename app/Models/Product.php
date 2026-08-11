<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use HasFactory, Searchable;

    /**
     * Umbral de stock bajo. Si el stock es estrictamente menor a este valor,
     * el producto se fuerza a `active = false` para que no se muestre en
     * el catálogo público.
     */
    public const LOW_STOCK_THRESHOLD = 5;

    protected $fillable = [
        'external_id',
        'origin',
        'sku',
        'name',
        'slug',
        'description',
        'price',
        'list_price',
        'cash_price',
        'markup_percent',
        'stock',
        'image',
        'brand',
        'source_url',
        'categories_external',
        'category_id',
        'active',
        'last_seen_at',
        'missing_since',
    ];

    protected $casts = [
        'price'             => 'decimal:2',
        'list_price'        => 'decimal:2',
        'cash_price'        => 'decimal:2',
        'markup_percent'    => 'decimal:2',
        'stock'             => 'integer',
        'active'            => 'boolean',
        'categories_external' => 'array',
        'last_seen_at'      => 'datetime',
        'missing_since'     => 'datetime',
    ];

    /**
     * Accessors que se incluyen automáticamente al serializar a JSON/array.
     */
    protected $appends = ['final_price', 'image_url', 'is_from_daz'];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $slug = Str::slug($product->name);
                $product->slug = $slug . '-' . Str::random(4);
            }
        });

        // Regla de negocio: cualquier producto con stock < LOW_STOCK_THRESHOLD
        // queda forzado a `active = false`. Se aplica tanto al crear como al
        // actualizar, de modo que nadie pueda dejarlo activo con stock bajo.
        // Para reactivar un producto hay que primero reponer stock.
        static::saving(function (Product $product) {
            if ($product->stock !== null && (int) $product->stock < self::LOW_STOCK_THRESHOLD) {
                $product->active = false;
            }
        });
    }

    // ============== Scout (full-text search) ==============

    /**
     * Campos indexados por Scout.
     */
    public function toSearchableArray(): array
    {
        return [
            'id'          => (int) $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'brand'       => $this->brand,
            'sku'         => $this->sku,
            'category_id' => (int) $this->category_id,
            'price'       => (float) $this->price,
            'active'      => (bool) $this->active,
            'stock'       => (int) $this->stock,
            'origin'      => $this->origin,
        ];
    }

    /**
     * Solo productos activos son buscables.
     */
    public function shouldBeSearchable(): bool
    {
        return (bool) ($this->active ?? false);
    }

    // ============================================================
    //  Relaciones
    // ============================================================

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // ============================================================
    //  Scopes
    // ============================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('active', true)->where('stock', '>', 0);
    }

    public function scopeFromDaz(Builder $query): Builder
    {
        return $query->where('origin', 'daz');
    }

    public function scopeFromTuc(Builder $query): Builder
    {
        return $query->where('origin', 'tuc');
    }

    public function scopeFromOrigin(Builder $query, string $origin): Builder
    {
        return $query->where('origin', $origin);
    }

    public function scopeManual(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('origin')->orWhere('origin', 'manual');
        });
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhere('sku', 'like', "%{$term}%");
        });
    }

    // ============================================================
    //  Accessors
    // ============================================================

    /**
     * Precio principal a mostrar (efectivo si existe, si no lista, si no base).
     */
    public function getDisplayPriceAttribute(): float
    {
        return (float) ($this->cash_price ?? $this->list_price ?? $this->price);
    }

    /**
     * ¿Tiene descuento entre lista y efectivo?
     */
    public function getHasDiscountAttribute(): bool
    {
        if (! $this->list_price || ! $this->cash_price) {
            return false;
        }
        return (float) $this->list_price > (float) $this->cash_price;
    }

    public function getDiscountPercentAttribute(): ?int
    {
        if (! $this->has_discount) {
            return null;
        }
        $diff = (float) $this->list_price - (float) $this->cash_price;
        return (int) round(($diff / (float) $this->list_price) * 100);
    }

    /**
     * Precio final con markup aplicado (lo que paga el cliente).
     * price * (1 + markup_percent/100)
     */
    public function getFinalPriceAttribute(): float
    {
        $base    = (float) $this->price;
        $markup  = (float) $this->markup_percent;
        return round($base * (1 + $markup / 100), 2);
    }

    /**
     * ¿Este producto es de Dazimportadora?
     */
    public function getIsFromDazAttribute(): bool
    {
        return $this->origin === 'daz';
    }

    /**
     * ¿Este producto es de TustecnologiaTuc?
     */
    public function getIsFromTucAttribute(): bool
    {
        return $this->origin === 'tuc';
    }

    /**
     * Etiqueta legible del origen (para badges en UI).
     */
    public function getOriginLabelAttribute(): ?string
    {
        return match ($this->origin) {
            'daz'    => 'Dazimportadora',
            'tuc'    => 'TusTec-Tuc',
            'manual' => 'Manual',
            default  => null,
        };
    }

    /**
     * URL pública de la imagen.
     * - Si es http(s) externa (ej: dazimportadora) → se devuelve tal cual.
     * - Si es path local viejo → null (placeholder en frontend).
     */
    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }
        if (Str::startsWith($this->image, ['http://', 'https://'])) {
            return $this->image;
        }
        return null;
    }
}
