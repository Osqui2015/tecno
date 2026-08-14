<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historial de actualizaciones de un producto.
 *
 * Una fila por cada vez que el producto se creó o se modificó cualquier campo.
 * Se complementa con `ProductStockHistory` (que es solo cambios de stock) y con
 * el campo `products.last_updated_at` (que es la última marca "rápida" para
 * listados).
 */
class ProductUpdateHistory extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    /** Solo created_at — es inmutable. */
    public const UPDATED_AT = null;

    protected $table = 'product_update_history';

    protected $fillable = [
        'product_id',
        'source',
        'event',
        'changed_fields',
        'changes',
        'actor_id',
        'reference',
    ];

    protected $casts = [
        'changed_fields' => 'array',
        'changes'        => 'array',
        'created_at'     => 'datetime',
    ];

    // ============== Constantes de source ==============

    public const SOURCE_ADMIN       = 'admin';
    public const SOURCE_SCRAPER_DAZ = 'scraper:daz';
    public const SOURCE_SCRAPER_TUC = 'scraper:tuc';
    public const SOURCE_ORDER       = 'order';
    public const SOURCE_SYSTEM      = 'system';

    public const SOURCES = [
        self::SOURCE_ADMIN,
        self::SOURCE_SCRAPER_DAZ,
        self::SOURCE_SCRAPER_TUC,
        self::SOURCE_ORDER,
        self::SOURCE_SYSTEM,
    ];

    // ============== Constantes de event ==============

    public const EVENT_CREATED     = 'created';
    public const EVENT_UPDATED     = 'updated';
    public const EVENT_ACTIVATED   = 'activated';
    public const EVENT_DEACTIVATED = 'deactivated';

    // ============== Relaciones ==============

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    // ============== Scopes ==============

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeRecent(Builder $query, int $limit = 50): Builder
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    public function scopeBySource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    // ============== Accessors / helpers ==============

    /**
     * Etiqueta legible del source para mostrar en UI.
     */
    public function getSourceLabelAttribute(): string
    {
        return match ($this->source) {
            self::SOURCE_ADMIN       => 'Admin',
            self::SOURCE_SCRAPER_DAZ => 'Scraper Daz',
            self::SOURCE_SCRAPER_TUC => 'Scraper Tuc',
            self::SOURCE_ORDER       => 'Pedido',
            self::SOURCE_SYSTEM      => 'Sistema',
            default                  => $this->source ?? '—',
        };
    }

    /**
     * Resumen corto de los cambios (ej: "precio, stock") para tooltips.
     */
    public function getSummaryAttribute(): string
    {
        $fields = $this->changed_fields ?? [];
        if (empty($fields)) {
            return '—';
        }
        // Mapear nombres técnicos a algo más legible
        $labels = array_map(function (string $f): string {
            return match ($f) {
                'price'             => 'precio',
                'list_price'        => 'precio lista',
                'cash_price'        => 'precio efectivo',
                'markup_percent'    => 'markup',
                'stock'             => 'stock',
                'name'              => 'nombre',
                'description'       => 'descripción',
                'image'             => 'imagen',
                'category_id'       => 'categoría',
                'active'            => 'estado',
                'sku'               => 'sku',
                'brand'             => 'marca',
                default             => $f,
            };
        }, $fields);

        return implode(', ', $labels);
    }
}
