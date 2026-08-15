<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Métricas para el dashboard admin.
 *
 * El endpoint original `/admin/stats` carga TODO en una sola request. Eso es
 * ineficiente: el dashboard raramente necesita los 9 bloques al mismo tiempo,
 * y si uno falla, rompe los demás.
 *
 * Ahora cada bloque tiene su propio sub-endpoint con cache independiente
 * (TTL 60s). El endpoint legacy sigue funcionando para retrocompatibilidad.
 */
class AdminStatsController extends Controller
{
    private const CACHE_TTL = 60; // 1 minuto

    public function index(): JsonResponse
    {
        // Endpoint legacy: arma el payload completo en el formato que el
        // dashboard y los tests esperan.
        $kpis = $this->kpisData();
        $sales30 = $this->salesChartData(30)['data'];

        return response()->json([
            'products'             => $kpis['products'],
            'orders'               => $kpis['orders'],
            'revenue'              => $kpis['revenue'],
            'top_products'         => $this->topProductsData(5)['data'],
            'recent_orders'        => $this->recentOrdersData(5)['data'],
            'sales_7_days'         => $sales30,
            'sales_last_30_days'   => $sales30,
            'categories_sales'     => $this->categoriesSalesData()['data'],
        ]);
    }

    /**
     * GET /api/admin/stats/kpis
     */
    public function kpis(): JsonResponse
    {
        return response()->json($this->kpisData());
    }

    /**
     * @return array{products: array, orders: array, revenue: array}
     */
    private function kpisData(): array
    {
        return Cache::remember('admin:stats:kpis', self::CACHE_TTL, function () {
            $totalProducts  = Product::count();
            $activeProducts = Product::where('active', true)->count();
            $dazProducts    = Product::whereNotNull('external_id')->count();
            $outOfStock     = Product::where('stock', '<=', 0)->count();

            $totalOrders   = Order::count();
            $pendingOrders = Order::where('status', Order::STATUS_PENDING)->count();

            $totalRevenue = (float) Order::where('status', '!=', Order::STATUS_CANCELLED)->sum('total');
            $monthRevenue = (float) Order::where('status', '!=', Order::STATUS_CANCELLED)
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('total');
            $monthOrders = Order::where('created_at', '>=', now()->startOfMonth())->count();
            $avgTicket = $totalOrders > 0
                ? (float) Order::where('status', '!=', Order::STATUS_CANCELLED)->avg('total')
                : 0;

            $ordersByStatus = Order::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            return [
                'products' => [
                    'total'        => $totalProducts,
                    'active'       => $activeProducts,
                    'from_daz'     => $dazProducts,
                    'out_of_stock' => $outOfStock,
                ],
                'orders' => [
                    'total'     => $totalOrders,
                    'pending'   => $pendingOrders,
                    'by_status' => $ordersByStatus,
                ],
                'revenue' => [
                    'total'              => round($totalRevenue, 2),
                    'this_month'         => round($monthRevenue, 2),
                    'avg_ticket'         => round($avgTicket, 2),
                    'month_orders_count' => $monthOrders,
                ],
            ];
        });
    }

    /**
     * GET /api/admin/stats/sales-chart?days=30
     */
    public function salesChart(Request $request): JsonResponse
    {
        $days = max(1, min((int) $request->integer('days', 30), 90));
        return response()->json($this->salesChartData($days));
    }

    /**
     * @return array{data: \Illuminate\Support\Collection}
     */
    private function salesChartData(int $days = 30): array
    {
        return Cache::remember("admin:stats:sales:{$days}d", self::CACHE_TTL, function () use ($days) {
            $data = Order::where('status', '!=', Order::STATUS_CANCELLED)
                ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as orders_count'),
                    DB::raw('SUM(total) as revenue')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get();
            return ['data' => $data];
        });
    }

    /**
     * GET /api/admin/stats/top-products?limit=5
     */
    public function topProducts(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->integer('limit', 5), 20));
        return response()->json($this->topProductsData($limit));
    }

    /**
     * @return array{data: \Illuminate\Support\Collection}
     */
    private function topProductsData(int $limit = 5): array
    {
        return Cache::remember("admin:stats:top:{$limit}", self::CACHE_TTL, function () use ($limit) {
            $data = DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.status', '!=', Order::STATUS_CANCELLED)
                ->select(
                    'products.id',
                    'products.name',
                    'products.image',
                    'products.external_id',
                    DB::raw('SUM(order_items.qty) as sold_qty'),
                    DB::raw('SUM(order_items.qty * order_items.price) as revenue')
                )
                ->groupBy('products.id', 'products.name', 'products.image', 'products.external_id')
                ->orderByDesc('sold_qty')
                ->limit($limit)
                ->get();
            return ['data' => $data];
        });
    }

    /**
     * GET /api/admin/stats/recent-orders?limit=5
     */
    public function recentOrders(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->integer('limit', 5), 20));
        return response()->json($this->recentOrdersData($limit));
    }

    /**
     * @return array{data: \Illuminate\Support\Collection}
     */
    private function recentOrdersData(int $limit = 5): array
    {
        return Cache::remember("admin:stats:recent:{$limit}", self::CACHE_TTL, function () use ($limit) {
            // ⚠️ customer_full_name es un ACCESSOR (concatenación de customer_name + customer_lastname).
            // No es una columna real de la DB, por eso acá seleccionamos las dos columnas
            // base y dejamos que Eloquent compute el accessor al serializar.
            // (Bug pre-existente: el código original hacía `get(['...', 'customer_full_name'])`
            // lo cual se traduce a `SELECT customer_full_name FROM orders` y falla en MySQL
            // porque esa columna no existe.)
            $data = Order::with('user')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get(['id', 'user_id', 'total', 'status', 'created_at', 'customer_name', 'customer_lastname']);
            return ['data' => $data];
        });
    }

    /**
     * GET /api/admin/stats/categories-sales
     */
    public function categoriesSales(): JsonResponse
    {
        return response()->json($this->categoriesSalesData());
    }

    /**
     * @return array{data: \Illuminate\Support\Collection}
     */
    private function categoriesSalesData(): array
    {
        return Cache::remember('admin:stats:categories', self::CACHE_TTL, function () {
            $data = DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->join('categories', 'products.category_id', '=', 'categories.id')
                ->select(
                    'categories.name as category',
                    DB::raw('SUM(order_items.qty * order_items.price) as total_sales')
                )
                ->groupBy('categories.name')
                ->orderByDesc('total_sales')
                ->limit(5)
                ->get();
            return ['data' => $data];
        });
    }

    /**
     * GET /api/admin/products/margins
     *
     * Reporte de margen de ganancia por producto y agrupado.
     * El margen se calcula como: ((price * (1 + markup/100)) - price) / price
     * = markup_percent (es directo, pero el endpoint lo devuelve agrupado para
     * que el admin vea promedios por categoría / marca / origen).
     *
     * Query params:
     *   - group_by: 'category' | 'brand' | 'origin' | 'none' (default: 'category')
     *   - min_margin: filtrar productos con margen mínimo (ej: 30 = 30%)
     *   - max_margin: filtrar productos con margen máximo
     */
    public function margins(Request $request): JsonResponse
    {
        $groupBy = $request->string('group_by')->toString() ?: 'category';
        $minMargin = $request->filled('min_margin') ? (float) $request->float('min_margin') : null;
        $maxMargin = $request->filled('max_margin') ? (float) $request->float('max_margin') : null;

        // Base: solo productos activos con precio > 0
        $base = Product::query()
            ->where('active', true)
            ->where('price', '>', 0);

        if ($minMargin !== null) {
            $base->where('markup_percent', '>=', $minMargin);
        }
        if ($maxMargin !== null) {
            $base->where('markup_percent', '<=', $maxMargin);
        }

        // Resumen global
        $global = (clone $base)->selectRaw('
            COUNT(*) as product_count,
            AVG(markup_percent) as avg_markup,
            MIN(markup_percent) as min_markup,
            MAX(markup_percent) as max_markup,
            SUM(price * (1 + markup_percent / 100)) as total_final_price,
            SUM(price) as total_base_price
        ')->first();

        $totalMargin = 0.0;
        if ($global && $global->total_final_price && $global->total_base_price) {
            $totalMargin = round(
                (($global->total_final_price - $global->total_base_price) / $global->total_base_price) * 100,
                2
            );
        }

        // Agrupado
        $grouped = null;
        switch ($groupBy) {
            case 'category':
                $grouped = (clone $base)
                    ->join('categories', 'products.category_id', '=', 'categories.id')
                    ->selectRaw('
                        categories.id as group_id,
                        categories.name as group_name,
                        COUNT(*) as product_count,
                        AVG(products.markup_percent) as avg_markup,
                        SUM(products.price * (1 + products.markup_percent / 100)) as total_final_price,
                        SUM(products.price) as total_base_price
                    ')
                    ->groupBy('categories.id', 'categories.name')
                    ->orderByDesc('total_final_price')
                    ->get()
                    ->map(fn ($row) => $this->formatMarginRow($row));
                break;

            case 'brand':
                $grouped = (clone $base)
                    ->whereNotNull('brand')
                    ->where('brand', '!=', '')
                    ->selectRaw('
                        brand as group_id,
                        brand as group_name,
                        COUNT(*) as product_count,
                        AVG(markup_percent) as avg_markup,
                        SUM(price * (1 + markup_percent / 100)) as total_final_price,
                        SUM(price) as total_base_price
                    ')
                    ->groupBy('brand')
                    ->orderByDesc('total_final_price')
                    ->get()
                    ->map(fn ($row) => $this->formatMarginRow($row));
                break;

            case 'origin':
                $grouped = (clone $base)
                    ->selectRaw('
                        COALESCE(origin, "manual") as group_id,
                        COALESCE(origin, "manual") as group_name,
                        COUNT(*) as product_count,
                        AVG(markup_percent) as avg_markup,
                        SUM(price * (1 + markup_percent / 100)) as total_final_price,
                        SUM(price) as total_base_price
                    ')
                    ->groupBy('origin')
                    ->orderByDesc('total_final_price')
                    ->get()
                    ->map(fn ($row) => $this->formatMarginRow($row));
                break;

            case 'none':
            default:
                $grouped = null;
                break;
        }

        return response()->json([
            'global' => [
                'product_count'    => (int) ($global->product_count ?? 0),
                'avg_markup'       => round((float) ($global->avg_markup ?? 0), 2),
                'min_markup'       => round((float) ($global->min_markup ?? 0), 2),
                'max_markup'       => round((float) ($global->max_markup ?? 0), 2),
                'total_base_price' => round((float) ($global->total_base_price ?? 0), 2),
                'total_final_price' => round((float) ($global->total_final_price ?? 0), 2),
                'avg_margin_pct'   => $totalMargin,
            ],
            'grouped_by' => $groupBy,
            'groups'     => $grouped,
        ]);
    }

    private function formatMarginRow($row): array
    {
        $basePrice = (float) ($row->total_base_price ?? 0);
        $finalPrice = (float) ($row->total_final_price ?? 0);
        $marginPct = $basePrice > 0
            ? round((($finalPrice - $basePrice) / $basePrice) * 100, 2)
            : 0.0;

        return [
            'group_id'         => $row->group_id,
            'group_name'       => $row->group_name,
            'product_count'    => (int) $row->product_count,
            'avg_markup'       => round((float) $row->avg_markup, 2),
            'total_base_price' => round($basePrice, 2),
            'total_final_price' => round($finalPrice, 2),
            'margin_pct'       => $marginPct,
            'profit'           => round($finalPrice - $basePrice, 2),
        ];
    }
}
