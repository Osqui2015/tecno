<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'last_updated_at',
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
        'last_updated_at'   => 'datetime',
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
            // Marcar la última actualización en cada save (incluyendo creación).
            // El historial detallado se registra en el hook `updated`/`created`.
            $product->last_updated_at = now();
        });

        // ─── Historial: producto nuevo ───
        static::created(function (Product $product) {
            $product->recordUpdateHistory(
                event: \App\Models\ProductUpdateHistory::EVENT_CREATED,
                changedFields: array_keys($product->getAttributes()),
            );
        });

        // ─── Historial: cambios + cambios de stock ───
        static::updated(function (Product $product) {
            // Detectar TODOS los campos cambiados (no solo stock).
            $changed = array_keys($product->getDirty());

            // Filtrar campos que no son "contenido" del producto (ruido).
            $changed = array_values(array_filter($changed, fn ($f) => ! in_array($f, [
                'last_updated_at', // Siempre cambia, no es contenido del producto.
                'updated_at',      // Eloquent también lo actualiza.
            ])));

            // Historial de cambios general (solo si hubo cambios reales).
            if (! empty($changed)) {
                $product->recordUpdateHistory(
                    event: $product->wasChanged('active')
                        ? ($product->active
                            ? \App\Models\ProductUpdateHistory::EVENT_ACTIVATED
                            : \App\Models\ProductUpdateHistory::EVENT_DEACTIVATED)
                        : \App\Models\ProductUpdateHistory::EVENT_UPDATED,
                    changedFields: $changed,
                );
            }

            // Historial de stock (legado). Mantiene la tabla `product_stock_history`
            // funcionando como antes, pero ahora respetando si el cambio viene
            // de un scraper (para etiquetar bien el source).
            if ($product->wasChanged('stock')) {
                $ctx = self::resolveContext();
                if ($ctx['source'] === \App\Models\ProductUpdateHistory::SOURCE_ADMIN) {
                    // El hook legacy solo registraba stock desde admin (no desde scraper,
                    // porque los scrapers ya registraban su propio cambio).
                    $product->recordStockChange('admin', null, $ctx['actor_id']);
                }
                // Si viene de scraper, el scraper ya llama a recordStockChange()
                // explícitamente con source 'scraper'. No duplicamos.
            }
        });

        // Invalidar el cache público del catálogo cuando se crea/actualiza/borra.
        // Usamos el helper que cae a flush total si el driver no soporta tags.
        static::saved(function () {
            \App\Support\CacheHelper::flush(['products:public']);
        });
        static::deleted(function () {
            \App\Support\CacheHelper::flush(['products:public']);
        });
    }

    /**
     * Resuelve el contexto del cambio actual para etiquetar el historial:
     *  - source: 'admin' | 'scraper:daz' | 'scraper:tuc' | 'system'
     *  - actor_id: id del usuario si hay sesión activa
     *
     * Se basa en flags del container (app('scrape_origin') / app('scrape_in_progress'))
     * seteados por los scrapers y jobs.
     */
    public static function resolveContext(): array
    {
        $scrapeOrigin = app()->bound('scrape_origin') ? app('scrape_origin') : null;
        $scrapeActive = app()->bound('scrape_in_progress') ? (bool) app('scrape_in_progress') : false;

        if ($scrapeActive && $scrapeOrigin) {
            $source = match ($scrapeOrigin) {
                'daz'   => \App\Models\ProductUpdateHistory::SOURCE_SCRAPER_DAZ,
                'tuc'   => \App\Models\ProductUpdateHistory::SOURCE_SCRAPER_TUC,
                default => \App\Models\ProductUpdateHistory::SOURCE_SYSTEM,
            };
            return ['source' => $source, 'actor_id' => null];
        }

        $actorId = auth()->id();
        if ($actorId) {
            return ['source' => \App\Models\ProductUpdateHistory::SOURCE_ADMIN, 'actor_id' => $actorId];
        }

        return ['source' => \App\Models\ProductUpdateHistory::SOURCE_SYSTEM, 'actor_id' => null];
    }

    /**
     * Inserta una fila en product_update_history con el diff de los campos cambiados.
     * Llamar desde hooks del modelo o explícitamente desde admin/scrapers.
     *
     * @param  string                 $event          'created'|'updated'|'activated'|'deactivated'
     * @param  array<int, string>     $changedFields  Lista de campos que cambiaron
     * @param  string|null            $reference      Etiqueta libre (ej: 'daz:scrape')
     */
    public function recordUpdateHistory(string $event, array $changedFields, ?string $reference = null): void
    {
        if (! $this->exists) {
            return;
        }

        $ctx = self::resolveContext();

        // Construir diff { campo: { before, after } }
        $changes = [];
        foreach ($changedFields as $field) {
            $changes[$field] = [
                'before' => $this->getOriginal($field),
                'after'  => $this->getAttribute($field),
            ];
        }

        \App\Models\ProductUpdateHistory::create([
            'product_id'     => $this->id,
            'source'         => $ctx['source'],
            'event'          => $event,
            'changed_fields' => array_values($changedFields),
            'changes'        => $changes,
            'actor_id'       => $ctx['actor_id'],
            'reference'      => $reference,
        ]);
    }

    /**
     * Registra un cambio de stock en el historial.
     * Llamar explícitamente desde scrapers, orders, admin, etc.
     */
    public function recordStockChange(string $source, ?string $reference = null, ?int $actorId = null): void
    {
        $original = $this->getOriginal('stock');
        $current  = (int) $this->stock;

        if ((int) ($original ?? $current) === $current) {
            return; // No cambió
        }

        \App\Models\ProductStockHistory::create([
            'product_id'   => $this->id,
            'stock_before' => $original !== null ? (int) $original : null,
            'stock_after'  => $current,
            'source'       => $source,
            'reference'    => $reference,
            'actor_id'     => $actorId,
        ]);
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

    public function updateHistory(): HasMany
    {
        return $this->hasMany(ProductUpdateHistory::class)->orderByDesc('created_at');
    }

    public function latestUpdate(): HasOne
    {
        return $this->hasOne(ProductUpdateHistory::class)->latestOfMany('created_at');
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
