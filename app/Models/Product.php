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
        'gallery',
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
        'gallery'           => 'array',
        'last_seen_at'      => 'datetime',
        'last_updated_at'   => 'datetime',
        'missing_since'     => 'datetime',
    ];

    /**
     * Accessors que se incluyen automáticamente al serializar a JSON/array.
     */
    protected $appends = ['final_price', 'image_url', 'is_from_daz'];

    /**
     * Invalida el cache de uno o varios productos.
     *
     * Necesario cuando se usan operaciones de query builder
     * (`increment`, `decrement`, `updateQuietly`) que no disparan los
     * eventos `saved`/`deleted` del modelo y, por lo tanto, no limpian
     * el cache automáticamente (por ejemplo, al descontar stock en un
     * checkout o al reponerlo al cancelar un pedido).
     *
     * @param  int|array<int, int>  $ids
     */
    public static function flushCacheFor(int|array $ids): void
    {
        foreach ((array) $ids as $id) {
            \Illuminate\Support\Facades\Cache::forget("products:show:{$id}");
        }

        \App\Support\CacheHelper::flush(['products:public']);
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $slug = Str::slug($product->name);
                $product->slug = $slug . '-' . Str::random(4);
            }
        });

        // Regla de negocio: cualquier producto sin stock (stock <= 0)
        // queda forzado a `active = false`. Los productos con stock bajo (1-4)
        // permanecen activos para mostrar "¡Últimas N!" en el catálogo.
        static::saving(function (Product $product) {
            if ($product->stock !== null && (int) $product->stock <= 0) {
                $product->active = false;
            }

            // Marcar last_updated_at solo si es creación o si cambian campos de contenido
            // (evita que un cambio exclusivo de stock marque el contenido como actualizado).
            if (! $product->exists) {
                $product->last_updated_at = now();
            } else {
                $contentFields = [
                    'name', 'description', 'price', 'list_price', 'cash_price',
                    'brand', 'image', 'sku', 'category_id', 'markup_percent', 'active',
                ];
                if ($product->isDirty($contentFields)) {
                    $product->last_updated_at = now();
                }
            }
        });

        // ─── Historial: producto nuevo ───
        static::created(function (Product $product) {
            $coreFields = ['name', 'price', 'list_price', 'cash_price', 'stock', 'sku', 'origin', 'category_id', 'active'];
            $fields = array_values(array_intersect($coreFields, array_keys($product->getAttributes())));
            $product->recordUpdateHistory(
                event: \App\Models\ProductUpdateHistory::EVENT_CREATED,
                changedFields: $fields,
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

        // Invalidar el cache del producto individual y el del catálogo público.
        // Si hay un scraping masivo en progreso, el catálogo se invalida una sola vez
        // al finalizar el job para no vaciar el cache en cada save().
        static::saved(function (Product $product) {
            \Illuminate\Support\Facades\Cache::forget("products:show:{$product->id}");
            $isScrape = app()->bound('scrape_in_progress') && (bool) app('scrape_in_progress');
            if (! $isScrape) {
                \App\Support\CacheHelper::flush(['products:public']);
            }
        });
        static::deleted(function (Product $product) {
            \Illuminate\Support\Facades\Cache::forget("products:show:{$product->id}");
            $isScrape = app()->bound('scrape_in_progress') && (bool) app('scrape_in_progress');
            if (! $isScrape) {
                \App\Support\CacheHelper::flush(['products:public']);
            }
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
            $before = $this->getOriginal($field);
            $after  = $this->getAttribute($field);

            // Truncar textos largos para evitar explosión de tamaño en JSON
            if (is_string($before) && strlen($before) > 255) {
                $before = mb_substr($before, 0, 252) . '...';
            }
            if (is_string($after) && strlen($after) > 255) {
                $after = mb_substr($after, 0, 252) . '...';
            }

            $changes[$field] = [
                'before' => $before,
                'after'  => $after,
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
        $asciiName = Str::ascii($this->name ?? '');
        $asciiDesc = Str::ascii($this->description ?? '');

        return [
            'id'          => (int) $this->id,
            'name'        => trim($this->name . ' ' . $asciiName),
            'description' => trim(($this->description ?? '') . ' ' . $asciiDesc),
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

    /**
     * Genera variantes con y sin tildes de una palabra para búsquedas insensibles a acentos
     * independientemente del motor de base de datos (MySQL o SQLite).
     *
     * @return array<string>
     */
    public static function getAccentVariants(string $word): array
    {
        $ascii = Str::ascii($word);
        $variants = [$word, $ascii, mb_strtolower($word), mb_strtolower($ascii)];

        $vowelMap = [
            'a' => 'á',
            'e' => 'é',
            'i' => 'í',
            'o' => 'ó',
            'u' => 'ú',
        ];

        $len = mb_strlen($ascii);
        // Para cada vocal en la versión ASCII, generar la variante con tilde en esa posición
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($ascii, $i, 1);
            $lowerChar = mb_strtolower($char);
            if (isset($vowelMap[$lowerChar])) {
                $accented = $char === $lowerChar ? $vowelMap[$lowerChar] : mb_strtoupper($vowelMap[$lowerChar]);
                $variants[] = mb_substr($ascii, 0, $i) . $accented . mb_substr($ascii, $i + 1);
            }
        }

        return array_values(array_unique(array_filter($variants)));
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        $words = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $query->where(function (Builder $q) use ($words) {
            foreach ($words as $word) {
                $variants = self::getAccentVariants($word);
                $q->where(function (Builder $wordQuery) use ($variants) {
                    foreach ($variants as $variant) {
                        $wordQuery->orWhere('name', 'like', "%{$variant}%")
                            ->orWhere('description', 'like', "%{$variant}%")
                            ->orWhere('sku', 'like', "%{$variant}%")
                            ->orWhere('brand', 'like', "%{$variant}%");
                    }
                });
            }
        });
    }

    /**
     * Filtra productos que pertenezcan a la categoría indicada, ya sea como
     * `category_id` principal o dentro del array `categories_external`.
     */
    public function scopeInCategory(Builder $query, int $categoryId, ?string $categoryName = null): Builder
    {
        if ($categoryName === null) {
            $categoryName = \Illuminate\Support\Facades\Cache::remember(
                "category:name:{$categoryId}",
                3600,
                fn () => Category::where('id', $categoryId)->value('name')
            );
        }

        return $query->where(function (Builder $q) use ($categoryId, $categoryName) {
            $q->where('category_id', $categoryId);
            if ($categoryName !== null && $categoryName !== '') {
                $q->orWhereJsonContains('categories_external', $categoryName);
            }
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
     * - Si es path local (ej: products/52.jpg o storage/...) → resuelve asset.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }
        if (Str::startsWith($this->image, ['http://', 'https://'])) {
            return $this->image;
        }
        if (Str::startsWith($this->image, 'products/')) {
            return asset('storage/' . $this->image);
        }
        if (Str::startsWith($this->image, 'storage/')) {
            return asset($this->image);
        }
        return asset($this->image);
    }
}
