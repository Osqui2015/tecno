<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductUpdateHistory;
use App\Models\User;
use App\Support\CacheHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * GET /api/admin/products
     * Lista TODOS los productos (incluyendo inactivos) con filtros.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category');

        // Filtros
        if ($request->filled('source')) {
            $source = (string) $request->string('source');
            if ($source === 'daz') {
                $query->fromDaz();
            } elseif ($source === 'tuc') {
                $query->fromTuc();
            } elseif ($source === 'manual') {
                $query->manual();
            }
        }
        // Si no se pasa source, se muestran todos los productos (de cualquier origen).

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->filled('stock_status')) {
            $status = $request->string('stock_status')->toString();
            if ($status === 'in_stock') {
                $query->where('stock', '>', 0);
            } elseif ($status === 'out_of_stock') {
                $query->where('stock', '<=', 0);
            }
        }

        // Filtro por stock mínimo (ej: min_stock=5 oculta productos con menos de 5)
        if ($request->filled('min_stock')) {
            $query->where('stock', '>=', $request->integer('min_stock'));
        }

        // Rango de precio (en pesos argentinos)
        if ($request->filled('price_min')) {
            $query->where('price', '>=', $request->float('price_min'));
        }
        if ($request->filled('price_max')) {
            $query->where('price', '<=', $request->float('price_max'));
        }

        // Filtrar por marca (además de source/category)
        if ($request->filled('brand')) {
            $query->where('brand', $request->string('brand')->toString());
        }

        // Filtrar por rango de fechas (última actualización)
        if ($request->filled('updated_from')) {
            $query->where('updated_at', '>=', $request->date('updated_from'));
        }
        if ($request->filled('updated_to')) {
            $query->where('updated_at', '<=', $request->date('updated_to'));
        }

        // Filtrar por margen (markup_percent)
        if ($request->filled('markup_min')) {
            $query->where('markup_percent', '>=', $request->float('markup_min'));
        }
        if ($request->filled('markup_max')) {
            $query->where('markup_percent', '<=', $request->float('markup_max'));
        }

        if ($request->filled('search')) {
            $query->search($request->string('search')->toString());
        }

        $perPage = min($request->integer('per_page', 25), 100);
        $products = $query->orderByDesc('updated_at')->paginate($perPage);

        return response()->json($products);
    }

    /**
     * POST /api/admin/products
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'description'    => 'nullable|string',
            'price'          => 'required|numeric|min:0',
            'markup_percent' => 'sometimes|numeric|min:0|max:999.99',
            'stock'          => 'required|integer|min:0',
            'image'          => 'nullable|string',
            'category_id'    => 'required|exists:categories,id',
            'active'         => 'sometimes|boolean',
            'sku'            => 'nullable|string|max:255',
        ]);

        $data['slug'] = Str::slug($data['name']) . '-' . Str::random(4);

        $product = Product::create($data);

        // El observer del modelo ya invalida el cache, pero lo dejamos explícito
        // para no depender del orden de eventos.
        CacheHelper::flush(['products:public']);

        return response()->json($product->load('category'), 201);
    }

    /**
     * GET /api/admin/products/{id}
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with('category')
            ->with(['latestUpdate.actor'])
            ->findOrFail($id);

        return response()->json($product);
    }

    /**
     * GET /api/admin/products/{id}/history
     * Historial paginado de cambios de un producto.
     */
    public function history(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $perPage = min($request->integer('per_page', 25), 100);

        $query = ProductUpdateHistory::query()
            ->where('product_id', $product->id)
            ->with('actor:id,name,email')
            ->orderByDesc('created_at');

        if ($request->filled('source')) {
            $query->where('source', $request->string('source'));
        }

        $rows = $query->paginate($perPage);

        // Transformar para que la UI no tenga que conocer los nombres de columnas
        $rows->getCollection()->transform(function (ProductUpdateHistory $h) {
            return [
                'id'              => $h->id,
                'source'          => $h->source,
                'source_label'    => $h->source_label,
                'event'           => $h->event,
                'summary'         => $h->summary,
                'changed_fields'  => $h->changed_fields ?? [],
                'changes'         => $h->changes ?? [],
                'actor'           => $h->actor ? [
                    'id'    => $h->actor->id,
                    'name'  => $h->actor->name,
                    'email' => $h->actor->email,
                ] : null,
                'reference'       => $h->reference,
                'created_at'      => $h->created_at?->toIso8601String(),
                'created_at_human'=> $h->created_at?->translatedFormat('d/m/Y H:i'),
            ];
        });

        return response()->json([
            'product' => [
                'id'              => $product->id,
                'name'            => $product->name,
                'last_updated_at' => $product->last_updated_at?->toIso8601String(),
                'last_updated_human' => $product->last_updated_at?->translatedFormat('d/m/Y H:i'),
            ],
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page'    => $rows->lastPage(),
                'total'        => $rows->total(),
                'per_page'     => $rows->perPage(),
            ],
        ]);
    }

    /**
     * PATCH /api/admin/products/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'name'           => 'sometimes|string|max:255',
            'description'    => 'sometimes|nullable|string',
            'price'          => 'sometimes|numeric|min:0',
            'markup_percent' => 'sometimes|numeric|min:0|max:999.99',
            'stock'          => 'sometimes|integer|min:0',
            'image'          => 'sometimes|nullable|string',
            'category_id'    => 'sometimes|exists:categories,id',
            'active'         => 'sometimes|boolean',
            'sku'            => 'sometimes|nullable|string|max:255',
        ]);

        $before = [
            'price'          => (float) $product->price,
            'markup_percent' => (float) $product->markup_percent,
            'stock'          => (int) $product->stock,
            'active'         => (bool) $product->active,
        ];

        $product->update($data);

        CacheHelper::flush(['products:public']);

        $after = [
            'price'          => (float) $product->fresh()->price,
            'markup_percent' => (float) $product->fresh()->markup_percent,
            'stock'          => (int) $product->fresh()->stock,
            'active'         => (bool) $product->fresh()->active,
        ];

        // Audit log si cambió algo importante
        $changes = array_diff_assoc($after, $before);
        if (! empty($changes)) {
            AuditLog::create([
                'action'       => 'product.updated',
                'description'  => "Producto \"{$product->name}\" actualizado",
                'subject_type' => Product::class,
                'subject_id'   => $product->id,
                'actor_type'   => User::class,
                'actor_id'     => $request->user()?->id,
                'meta'         => [
                    'before'  => $before,
                    'after'   => $after,
                    'changes' => array_keys($changes),
                ],
                'ip_address'   => $request->ip(),
            ]);
        }

        return response()->json($product->fresh('category'));
    }

    /**
     * DELETE /api/admin/products/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        CacheHelper::flush(['products:public']);

        return response()->json(['message' => 'Producto eliminado']);
    }

    /**
     * POST /api/admin/products/bulk-markup
     *
     * Body:
     *   { percent: 25 }                                     ← TODOS los productos
     *   { percent: 25, product_ids: [1,2,3] }               ← solo esos productos
     *   { percent: 25, source: 'daz' | 'manual' }           ← solo origen
     *   { percent: 25, category_id: 5 }                     ← solo categoría
     *
     * `percent` es el NUEVO markup_percent que se asigna (no incremental).
     */
    public function bulkMarkup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'percent'     => 'required|numeric|min:0|max:999.99',
            'product_ids' => 'sometimes|array',
            'product_ids.*' => 'integer|exists:products,id',
            'source'      => ['sometimes', Rule::in(['daz', 'tuc', 'manual'])],
            'category_id' => 'sometimes|integer|exists:categories,id',
        ]);

        $query = Product::query();

        if (! empty($data['product_ids'])) {
            $query->whereIn('id', $data['product_ids']);
        } else {
            if (! empty($data['source'])) {
                if ($data['source'] === 'daz') {
                    $query->fromDaz();
                } elseif ($data['source'] === 'tuc') {
                    $query->fromTuc();
                } else {
                    $query->manual();
                }
            }
            if (! empty($data['category_id'])) {
                $query->where('category_id', $data['category_id']);
            }
        }

        $count = DB::transaction(function () use ($query, $data) {
            return $query->update(['markup_percent' => $data['percent']]);
        });

        // Invalidar cache público del catálogo (final_price depende del markup).
        CacheHelper::flush(['products:public']);

        // Audit log del cambio global
        AuditLog::create([
            'action'       => 'product.bulk_markup',
            'description'  => "Aumento global del {$data['percent']}% aplicado a {$count} producto(s)",
            'subject_type' => Product::class,
            'subject_id'   => 0, // bulk, no single subject
            'actor_type'   => User::class,
            'actor_id'     => $request->user()?->id,
            'meta'         => [
                'percent'     => $data['percent'],
                'product_ids' => $data['product_ids'] ?? null,
                'source'      => $data['source'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'count'       => $count,
            ],
            'ip_address'   => $request->ip(),
        ]);

        return response()->json([
            'message' => "Markup del {$data['percent']}% aplicado a {$count} producto(s)",
            'updated' => $count,
        ]);
    }

    /**
     * GET /api/admin/products/export/csv
     * Exporta todo el catálogo de productos a un archivo CSV codificado en UTF-8 con BOM.
     */
    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $fileName = 'productos_' . date('Y-m-d_H-i') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            // Escribir UTF-8 BOM para que Excel abra los caracteres especiales (acentos, ñ) correctamente
            fprintf($handle, "\xEF\xBB\xBF");

            // Header del CSV
            fputcsv($handle, [
                'ID',
                'SKU',
                'Nombre',
                'Marca',
                'Precio Base',
                'Precio Lista',
                'Precio Efectivo',
                'Markup %',
                'Stock',
                'Activo',
                'ID Categoria',
            ]);

            Product::chunk(100, function ($products) use ($handle) {
                foreach ($products as $p) {
                    fputcsv($handle, [
                        $p->id,
                        $p->sku,
                        $p->name,
                        $p->brand,
                        $p->price,
                        $p->list_price,
                        $p->cash_price,
                        $p->markup_percent,
                        $p->stock,
                        $p->active ? 'SI' : 'NO',
                        $p->category_id,
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }

    /**
     * POST /api/admin/products/import/csv
     * Importa / Actualiza productos masivamente desde un archivo CSV.
     */
    public function importCsv(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        $handle = fopen($path, 'r');
        if (! $handle) {
            return response()->json(['message' => 'No se pudo abrir el archivo CSV'], 422);
        }

        // Leer primera línea (encabezados)
        $headers = fgetcsv($handle, 2000, ',');
        if (! $headers) {
            fclose($handle);
            return response()->json(['message' => 'El archivo CSV está vacío'], 422);
        }

        // Normalizar encabezados (quitar BOM y minúsculas)
        $cleanHeaders = array_map(function ($h) {
            $h = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $h);
            return strtolower(trim($h));
        }, $headers);

        // Cache de categorías válidas para validar id sin pegar a DB por fila
        $validCategoryIds = \App\Models\Category::pluck('id')->all();

        $createdCount = 0;
        $updatedCount = 0;
        $errorCount = 0;
        $errorSamples = [];

        DB::transaction(function () use ($handle, $cleanHeaders, $validCategoryIds, &$createdCount, &$updatedCount, &$errorCount, &$errorSamples) {
            $lineNumber = 1; // header
            while (($row = fgetcsv($handle, 2000, ',')) !== false) {
                $lineNumber++;
                if (count($row) < 2) {
                    $errorCount++;
                    $errorSamples[] = "Línea {$lineNumber}: fila vacía";
                    continue;
                }

                $data = array_combine(array_slice($cleanHeaders, 0, count($row)), $row);

                $sku   = trim($data['sku'] ?? $data['code'] ?? '');
                $name  = trim($data['nombre'] ?? $data['name'] ?? '');
                $price = $this->numericOrNull($data['precio base'] ?? $data['price'] ?? null);
                $stock = isset($data['stock']) ? (int) $data['stock'] : 0;
                $brand = trim($data['marca'] ?? $data['brand'] ?? '');
                $rawCatId = $data['id categoria'] ?? $data['category_id'] ?? null;
                $catId = $rawCatId !== null && $rawCatId !== '' ? (int) $rawCatId : null;
                $active = isset($data['activo']) ? in_array(strtoupper(trim($data['activo'])), ['SI', 'YES', '1', 'TRUE']) : true;

                if (empty($name) && empty($sku)) {
                    $errorCount++;
                    $errorSamples[] = "Línea {$lineNumber}: falta nombre y SKU";
                    continue;
                }

                // Validar categoría si se proveyó
                if ($catId !== null && ! in_array($catId, $validCategoryIds, true)) {
                    $errorCount++;
                    $errorSamples[] = "Línea {$lineNumber}: categoría {$catId} no existe";
                    continue;
                }

                // Buscar producto existente por SKU o por Nombre
                $product = null;
                if (! empty($sku)) {
                    $product = Product::where('sku', $sku)->first();
                }
                if (! $product && ! empty($name)) {
                    $product = Product::where('name', $name)->first();
                }

                if ($product) {
                    $product->update(array_filter([
                        'price'       => $price ?? $product->price,
                        'stock'       => $stock,
                        'brand'       => $brand ?: $product->brand,
                        'category_id' => $catId ?? $product->category_id,
                        'active'      => $active,
                    ], fn ($v) => $v !== null));
                    $updatedCount++;
                } else {
                    Product::create([
                        'sku'         => $sku ?: null,
                        'name'        => $name,
                        'slug'        => Str::slug($name) . '-' . Str::random(4),
                        'price'       => $price ?? 0,
                        'stock'       => $stock,
                        'brand'       => $brand ?: null,
                        'category_id' => $catId ?? 1, // default solo al crear; en update ya validamos arriba
                        'active'      => $active,
                    ]);
                    $createdCount++;
                }
            }
        });

        fclose($handle);

        // Mostrar como mucho 10 muestras de errores para no saturar la respuesta
        $errorSamples = array_slice($errorSamples, 0, 10);

        // Invalidar cache público: el import cambió muchos productos.
        CacheHelper::flush(['products:public']);

        return response()->json([
            'message'  => "Importación completada: {$createdCount} creados, {$updatedCount} actualizados, {$errorCount} omitidos.",
            'created'  => $createdCount,
            'updated'  => $updatedCount,
            'errors'   => $errorCount,
            'error_samples' => $errorSamples,
        ]);
    }

    /**
     * Convierte un valor de celda CSV a float, o null si no es numérico.
     * Acepta formatos con $, espacios, y separadores AR/US.
     */
    private function numericOrNull($val): ?float
    {
        if ($val === null || $val === '') return null;
        $val = str_replace(['$', ' '], '', (string) $val);
        return is_numeric($val) ? (float) $val : null;
    }
}
